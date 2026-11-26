<?php
// Restaurant staff: change an order's status (called by dashboard.php and rest_orders.php)
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

function out(bool $ok, string $error = '', int $code = 200): void
{
    http_response_code($code);
    echo json_encode(['ok' => $ok, 'error' => $error]);
    exit;
}

if (($_SESSION['role'] ?? '') !== 'restaurant_staff') out(false, 'Not allowed.', 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST')            out(false, 'POST only.', 405);

$sess = $_SESSION['csrf'] ?? '';
if ($sess === '' || !hash_equals($sess, $_POST['csrf'] ?? '')) out(false, 'Session expired. Reload the page.', 403);

$id  = (int)($_POST['order_id'] ?? 0);
$new = $_POST['status'] ?? '';

// Allowed moves for the kitchen
$flow = [
    'New'       => ['Preparing', 'Cancelled'],
    'Preparing' => ['Ready'],
    'Ready'     => ['Completed'],
];

try {
    $pdo->beginTransaction();

    $cur = $pdo->prepare('SELECT status FROM orders WHERE order_id = ? FOR UPDATE');
    $cur->execute([$id]);
    $status = $cur->fetchColumn();

    if ($status === false) {
        $pdo->rollBack();
        out(false, 'Order not found.', 404);
    }
    if (!in_array($new, $flow[$status] ?? [], true)) {
        $pdo->rollBack();
        out(false, "Can't move an order from $status to $new.", 422);
    }

    $pdo->prepare('UPDATE orders SET status = ? WHERE order_id = ?')->execute([$new, $id]);

    // Ready = riders can now see it on their dashboard
    if ($new === 'Ready') {
        $pdo->prepare('INSERT IGNORE INTO deliveries (order_id) VALUES (?)')->execute([$id]);
    }

    $pdo->commit();
    out(true);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    out(false, 'Database error.', 500);
}
