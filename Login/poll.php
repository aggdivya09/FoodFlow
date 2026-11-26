<?php
// Tiny endpoint used by live_updates.js: tells staff / rider / admin pages when orders changed.
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!in_array($_SESSION['role'] ?? '', ['restaurant_staff', 'delivery_person', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['sig' => '']);
    exit;
}

try {
    $o = $pdo->query(
        "SELECT COUNT(*) AS n, COALESCE(MAX(order_id), 0) AS last_id,
                COALESCE(SUM(CRC32(status)), 0) AS s,
                COALESCE(SUM(status = 'New'), 0) AS new_orders
         FROM orders"
    )->fetch(PDO::FETCH_ASSOC);

    $d = $pdo->query(
        "SELECT COUNT(*) AS n, COALESCE(SUM(CRC32(status)), 0) AS s, COALESCE(SUM(IFNULL(delivery_person_id, 0)), 0) AS p
         FROM deliveries"
    )->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'sig' => implode('-', [$o['n'], $o['last_id'], $o['s'], $d['n'], $d['s'], $d['p']]),
        'new' => (int)$o['new_orders'],
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['sig' => '']);
}
