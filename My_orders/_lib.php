<?php
// Shared helpers for the customer order pages (checkout, my orders, track order)
require_once __DIR__ . '/../db.php';

function customer_guard(bool $json = false): int
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (($_SESSION['role'] ?? '') !== 'customer' || empty($_SESSION['user_id'])) {
        if ($json) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Please log in again.']);
        } else {
            header('Location: ../Login/customer.php');
        }
        exit;
    }
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return (int)$_SESSION['user_id'];
}

function slugify(string $s): string
{
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-');
}

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/**
 * Re-price a cart sent by the browser using the prices in the database.
 * $cart = [['id' => 'margherita-pizza', 'name' => '...', 'qty' => 2], ...]
 * Returns [lines, subtotal, bad]
 */
function price_cart(PDO $pdo, $cart): array
{
    // the two beverage cards on the website use slogans as their heading
    $alias = ['unleash-the-thunder' => 'thums-up', 'refresh-your-senses' => 'sprite'];

    if (!is_array($cart)) return [[], 0.0, []];

    $bySlug = [];
    foreach ($pdo->query('SELECT id, name, price, available, is_veg FROM food') as $f) {
        $bySlug[slugify($f['name'])] = $f;
    }

    $lines = [];
    $bad   = [];
    foreach ($cart as $row) {
        if (!is_array($row)) continue;
        $cid = (string)($row['id'] ?? '');
        $qty = (int)($row['qty'] ?? 0);
        if ($cid === '' || $qty < 1) continue;

        $key = slugify($cid);
        $key = $alias[$key] ?? $key;
        $f   = $bySlug[$key] ?? null;

        if (!$f) {
            $bad[] = ['id' => $cid, 'msg' => (string)($row['name'] ?? 'This item') . ' is not on our menu right now.'];
            continue;
        }
        if (!(int)$f['available']) {
            $bad[] = ['id' => $cid, 'msg' => $f['name'] . ' is sold out.'];
            continue;
        }

        $fid = (int)$f['id'];
        if (!isset($lines[$fid])) {
            $lines[$fid] = ['cid' => $cid, 'food_id' => $fid, 'name' => $f['name'], 'price' => (float)$f['price'],
                            'veg' => (bool)$f['is_veg'], 'qty' => 0];
        }
        $lines[$fid]['qty'] = min(20, $lines[$fid]['qty'] + $qty);
    }

    $subtotal = 0.0;
    foreach ($lines as &$l) {
        $l['line_total'] = $l['price'] * $l['qty'];
        $subtotal += $l['line_total'];
    }
    unset($l);

    return [array_values($lines), $subtotal, $bad];
}

/** 0 placed, 1 preparing, 2 ready for pickup, 3 out for delivery, 4 delivered, -1 cancelled */
function order_stage(array $order, ?array $delivery): int
{
    if ($order['status'] === 'Cancelled') return -1;
    if ($delivery) {
        return ['Assigned' => 2, 'Picked Up' => 3, 'Delivered' => 4][$delivery['status']] ?? 2;
    }
    return ['New' => 0, 'Preparing' => 1, 'Ready' => 2, 'Completed' => 4][$order['status']] ?? 0;
}

const STAGE_LABELS = [
    -1 => 'Cancelled',
    0  => 'Order placed',
    1  => 'Preparing your food',
    2  => 'Ready, waiting for rider',
    3  => 'Out for delivery',
    4  => 'Delivered',
];

/** Everything the track page needs for one order that belongs to this customer. */
function track_data(PDO $pdo, int $orderId, int $uid): ?array
{
    $st = $pdo->prepare('SELECT * FROM orders WHERE order_id = ? AND customer_id = ?');
    $st->execute([$orderId, $uid]);
    $order = $st->fetch(PDO::FETCH_ASSOC);
    if (!$order) return null;

    $st = $pdo->prepare(
        'SELECT d.status, d.picked_up_at, d.delivered_at, u.full_name AS rider_name, u.phone AS rider_phone
         FROM deliveries d LEFT JOIN users u ON u.user_id = d.delivery_person_id
         WHERE d.order_id = ?'
    );
    $st->execute([$orderId]);
    $delivery = $st->fetch(PDO::FETCH_ASSOC) ?: null;

    $st = $pdo->prepare('SELECT item_name, quantity, price FROM order_items WHERE order_id = ? ORDER BY id');
    $st->execute([$orderId]);
    $items = $st->fetchAll(PDO::FETCH_ASSOC);

    $stage = order_stage($order, $delivery);
    return [
        'order'    => $order,
        'items'    => $items,
        'delivery' => $delivery,
        'stage'    => $stage,
        'label'    => STAGE_LABELS[$stage],
        // the rider is only shown once the food is on its way
        'rider'    => ($delivery && $stage === 3 && $delivery['rider_name'])
                        ? ['name' => $delivery['rider_name'], 'phone' => $delivery['rider_phone']] : null,
    ];
}
