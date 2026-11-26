<?php
// Delivery partner: update a delivery or the online switch (called by delivery_dashboard.php)
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

function out(bool $ok, string $error = '', int $code = 200): void
{
    http_response_code($code);
    echo json_encode(['ok' => $ok, 'error' => $error]);
    exit;
}

if (($_SESSION['role'] ?? '') !== 'delivery_person' || empty($_SESSION['user_id'])) out(false, 'Not allowed.', 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(false, 'POST only.', 405);

$sess = $_SESSION['csrf'] ?? '';
if ($sess === '' || !hash_equals($sess, $_POST['csrf'] ?? '')) out(false, 'Session expired. Reload the page.', 403);

$rider  = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    if ($action === 'online') {
        $pdo->prepare('UPDATE users SET is_online = ? WHERE user_id = ?')
            ->execute([empty($_POST['online']) ? 0 : 1, $rider]);
        out(true);
    }

    if ($action === 'status') {
        $oid = (int)($_POST['order_id'] ?? 0);
        $new = $_POST['status'] ?? '';

        $pdo->beginTransaction();

        if ($new === 'Picked Up') {
            // A rider claims an unassigned delivery (or continues one already assigned to them)
            $st = $pdo->prepare(
                "UPDATE deliveries
                 SET status = 'Picked Up', delivery_person_id = ?, picked_up_at = NOW()
                 WHERE order_id = ? AND status = 'Assigned'
                   AND (delivery_person_id IS NULL OR delivery_person_id = ?)"
            );
            $st->execute([$rider, $oid, $rider]);
            if ($st->rowCount() !== 1) {
                $pdo->rollBack();
                out(false, 'This delivery is no longer available.', 409);
            }
            // Picking it up also closes the order on the kitchen side
            $pdo->prepare("UPDATE orders SET status = 'Completed' WHERE order_id = ? AND status = 'Ready'")
                ->execute([$oid]);

        } elseif ($new === 'Delivered') {
            $st = $pdo->prepare(
                "UPDATE deliveries SET status = 'Delivered', delivered_at = NOW()
                 WHERE order_id = ? AND status = 'Picked Up' AND delivery_person_id = ?"
            );
            $st->execute([$oid, $rider]);
            if ($st->rowCount() !== 1) {
                $pdo->rollBack();
                out(false, 'This delivery cannot be marked delivered.', 409);
            }
        } else {
            $pdo->rollBack();
            out(false, 'Invalid status.', 422);
        }

        $pdo->commit();
        out(true);
    }

    out(false, 'Unknown action.', 400);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    out(false, 'Database error.', 500);
}
