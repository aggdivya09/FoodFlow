<?php
// Customer AJAX endpoint:  quote | place | cancel | track
session_start();
require_once __DIR__ . '/_lib.php';
header('Content-Type: application/json');

function out(array $d, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($d);
    exit;
}

$uid = customer_guard(true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => false, 'error' => 'POST only.'], 405);

$sess = $_SESSION['csrf'] ?? '';
if ($sess === '' || !hash_equals($sess, $_POST['csrf'] ?? '')) out(['ok' => false, 'error' => 'Session expired. Reload the page.'], 403);

$action = $_POST['action'] ?? '';

try {
    /* ---------- price the cart from the database ---------- */
    if ($action === 'quote') {
        [$lines, $subtotal, $bad] = price_cart($pdo, json_decode($_POST['cart'] ?? '[]', true));
        out(['ok' => true, 'lines' => $lines, 'subtotal' => $subtotal, 'bad' => $bad]);
    }

    /* ---------- place the order ---------- */
    if ($action === 'place') {
        $open = (int)$pdo->query('SELECT COUNT(*) FROM restaurants WHERE is_active = 1')->fetchColumn();
        if ($open === 0) out(['ok' => false, 'error' => "We're not taking orders right now. Please try again later."], 409);

        [$lines, $subtotal, $bad] = price_cart($pdo, json_decode($_POST['cart'] ?? '[]', true));
        if ($bad)    out(['ok' => false, 'error' => $bad[0]['msg'], 'bad' => $bad], 422);
        if (!$lines) out(['ok' => false, 'error' => 'Your cart is empty.'], 422);

        $phone   = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $note    = trim($_POST['note'] ?? '');
        $payment = ($_POST['payment'] ?? '') === 'online' ? 'Paid online' : 'Cash on delivery';

        if (!preg_match('/^[0-9+\-\s]{10,15}$/', $phone)) out(['ok' => false, 'error' => 'Enter a valid phone number.'], 422);
        if (mb_strlen($address) < 10 || mb_strlen($address) > 255) out(['ok' => false, 'error' => 'Enter a full delivery address (10-255 characters).'], 422);
        if (mb_strlen($note) > 255) $note = mb_substr($note, 0, 255);

        $u = $pdo->prepare('SELECT full_name, phone, address FROM users WHERE user_id = ?');
        $u->execute([$uid]);
        $user = $u->fetch(PDO::FETCH_ASSOC);
        if (!$user) out(['ok' => false, 'error' => 'Account not found.'], 404);

        $pdo->beginTransaction();

        $pdo->prepare(
            "INSERT INTO orders (customer_id, customer_name, customer_phone, delivery_address, payment_method, total_amount, status, note)
             VALUES (?, ?, ?, ?, ?, ?, 'New', ?)"
        )->execute([$uid, $user['full_name'], $phone, $address, $payment, $subtotal, $note]);
        $orderId = (int)$pdo->lastInsertId();

        $ins = $pdo->prepare('INSERT INTO order_items (order_id, food_id, item_name, quantity, price) VALUES (?, ?, ?, ?, ?)');
        foreach ($lines as $l) {
            $ins->execute([$orderId, $l['food_id'], $l['name'], $l['qty'], $l['price']]);
        }

        // remember phone / address for next time if the profile has none
        $pdo->prepare(
            "UPDATE users SET phone = IF(phone IS NULL OR phone = '', ?, phone),
                              address = IF(address IS NULL OR address = '', ?, address)
             WHERE user_id = ?"
        )->execute([$phone, $address, $uid]);

        $pdo->commit();
        out(['ok' => true, 'order_id' => $orderId]);
    }

    /* ---------- cancel (only before the kitchen starts) ---------- */
    if ($action === 'cancel') {
        $st = $pdo->prepare("UPDATE orders SET status = 'Cancelled' WHERE order_id = ? AND customer_id = ? AND status = 'New'");
        $st->execute([(int)($_POST['order_id'] ?? 0), $uid]);
        if ($st->rowCount() !== 1) out(['ok' => false, 'error' => 'The kitchen has already started this order, so it can no longer be cancelled.'], 409);
        out(['ok' => true]);
    }

    /* ---------- live status for the track page ---------- */
    if ($action === 'track') {
        $t = track_data($pdo, (int)($_POST['order_id'] ?? 0), $uid);
        if (!$t) out(['ok' => false, 'error' => 'Order not found.'], 404);
        out(['ok' => true, 'stage' => $t['stage'], 'label' => $t['label'], 'rider' => $t['rider']]);
    }

    out(['ok' => false, 'error' => 'Unknown action.'], 400);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('FoodFlow order error: ' . $e->getMessage());
    out(['ok' => false, 'error' => 'Something went wrong. Please try again.'], 500);
}
