<?php
session_start();

require_once __DIR__ . '/../db.php';

// Only logged-in restaurant staff can see this page
// (change 'restaurant_staff' if your users.role value is different)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'restaurant_staff') {
    header('Location: ../Login/restaurant.php');
    exit;
}

$staffName = $_SESSION['full_name'] ?? 'Staff';
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
// Fetch restaurant details
$stmt = $pdo->prepare(
    "SELECT * FROM restaurants
     WHERE is_active = 1
     LIMIT 1"
);

$stmt->execute();

$restaurant = $stmt->fetch(PDO::FETCH_ASSOC);

$restaurantName = $restaurant['restaurant_name'] ?? 'FoodFlow Kitchen';

$cnt = $pdo->query("SELECT status, COUNT(*) FROM orders GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$todayRevenue = (float)$pdo->query(
    "SELECT COALESCE(SUM(total_amount), 0) FROM orders
     WHERE DATE(created_at) = CURDATE() AND status <> 'Cancelled'"
)->fetchColumn();

$stats = [
    ['label' => 'New Orders',       'value' => (int)($cnt['New'] ?? 0),       'icon' => 'fa-bell',         'color' => 'bg-orange-100 text-[#ff4d0a]'],
    ['label' => 'Preparing',        'value' => (int)($cnt['Preparing'] ?? 0), 'icon' => 'fa-fire-burner',  'color' => 'bg-yellow-100 text-yellow-600'],
    ['label' => 'Ready for Pickup', 'value' => (int)($cnt['Ready'] ?? 0),     'icon' => 'fa-bag-shopping', 'color' => 'bg-green-100 text-green-600'],
    ['label' => "Today's Revenue",  'value' => '₹' . number_format($todayRevenue), 'icon' => 'fa-indian-rupee-sign', 'color' => 'bg-blue-100 text-blue-600'],
];

$orders = $pdo->query(
    "SELECT o.order_id AS id, o.customer_name AS customer, o.total_amount AS total, o.status,
            TIMESTAMPDIFF(MINUTE, o.created_at, NOW()) AS mins,
            (SELECT GROUP_CONCAT(CONCAT(oi.quantity, 'x ', oi.item_name) SEPARATOR ', ')
               FROM order_items oi WHERE oi.order_id = o.order_id) AS items
     FROM orders o
     WHERE o.status IN ('New', 'Preparing', 'Ready')
     ORDER BY o.created_at DESC LIMIT 8"
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($orders as &$o) {
    $o['time']  = ago_label((int)$o['mins']);
    $o['items'] = $o['items'] ?? '';
}
unset($o);

$menu = $pdo->query("SELECT id, name, price, available FROM food ORDER BY id LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
$badge = [
    'New'       => 'bg-orange-100 text-[#ff4d0a]',
    'Preparing' => 'bg-yellow-100 text-yellow-700',
    'Ready'     => 'bg-green-100 text-green-700',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant Dashboard - FoodFlow</title>
    <link rel="icon" type="image/png" href="../favicon.png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>

<body class="m-0 min-h-screen bg-orange-50/60 text-gray-900">

<div class="flex min-h-screen">

    <!-- Sidebar -->
    <aside class="hidden w-64 shrink-0 flex-col bg-white p-6 shadow-lg lg:flex">

        <h1 class="text-3xl font-bold">
    Food<span class="text-[#ff4d0a]">Flow</span>
</h1>

<p class="mt-1 text-sm font-semibold text-gray-500">
    <?= htmlspecialchars($restaurantName, ENT_QUOTES, 'UTF-8') ?>
</p>

        <nav class="mt-10 space-y-2 font-semibold">
            <a href="dashboard.php"
               class="flex items-center gap-3 rounded-xl bg-[#ff4d0a] px-4 py-3 text-white">
                <i class="fa-solid fa-table-columns w-5"></i> Dashboard
            </a>
            <a href="rest_orders.php"
               class="flex items-center gap-3 rounded-xl px-4 py-3 text-gray-700 hover:bg-orange-50">
                <i class="fa-solid fa-receipt w-5"></i> Orders
            </a>
            <a href="rest_menu.php"
               class="flex items-center gap-3 rounded-xl px-4 py-3 text-gray-700 hover:bg-orange-50">
                <i class="fa-solid fa-utensils w-5"></i> Menu
            </a>
            <a href="reports.php"
               class="flex items-center gap-3 rounded-xl px-4 py-3 text-gray-700 hover:bg-orange-50">
                <i class="fa-solid fa-chart-line w-5"></i> Reports
            </a>
        </nav>

        <a href="../logout.php"
           class="mt-auto flex items-center gap-3 rounded-xl px-4 py-3 font-semibold
                  text-red-600 hover:bg-red-50">
            <i class="fa-solid fa-right-from-bracket w-5"></i> Logout
        </a>
    </aside>

    <!-- Main -->
    <main class="flex-1 p-4 md:p-8">

        <!-- Top bar -->
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-3xl font-extrabold md:text-4xl">
                    Hello, <span class="text-[#ff4d0a]"><?= htmlspecialchars($staffName, ENT_QUOTES, 'UTF-8') ?></span> 👨‍🍳
                </h2>
                <p class="mt-1 font-medium text-gray-500">
                    Here's what's cooking in your kitchen today.
                </p>
            </div>

            <div class="flex items-center gap-3 rounded-2xl bg-white px-5 py-3 shadow">
                <span class="h-3 w-3 animate-pulse rounded-full bg-green-500"></span>
                <span class="font-bold">Restaurant Open</span>
            </div>
        </header>

        <!-- Stat cards -->
        <section class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <?php foreach ($stats as $s): ?>
                <div class="flex items-center gap-4 rounded-3xl bg-white p-6 shadow">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl text-2xl <?= $s['color'] ?>">
                        <i class="fa-solid <?= $s['icon'] ?>"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-500"><?= htmlspecialchars($s['label']) ?></p>
                        <p class="text-3xl font-extrabold"><?= htmlspecialchars((string)$s['value']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <div class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-3">

            <!-- Live orders -->
            <section class="rounded-3xl bg-white p-6 shadow xl:col-span-2">
                <div class="flex items-center justify-between">
                    <h3 class="text-2xl font-extrabold">Live <span class="text-[#ff4d0a]">Orders</span></h3>
                    <a href="rest_orders.php" class="font-bold text-[#ff4d0a] hover:underline">View all →</a>
                </div>

                <div class="mt-5 overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left">
                        <thead>
                            <tr class="border-b text-sm text-gray-500">
                                <th class="pb-3">Order</th>
                                <th class="pb-3">Customer</th>
                                <th class="pb-3">Items</th>
                                <th class="pb-3">Total</th>
                                <th class="pb-3">Status</th>
                                <th class="pb-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($orders as $o): ?>
                            <tr class="border-b last:border-0">
                                <td class="py-4 font-bold">#<?= (int)$o['id'] ?>
                                    <div class="text-xs font-medium text-gray-400"><?= htmlspecialchars($o['time']) ?></div>
                                </td>
                                <td class="py-4 font-semibold"><?= htmlspecialchars($o['customer']) ?></td>
                                <td class="py-4 text-sm text-gray-600"><?= htmlspecialchars($o['items']) ?></td>
                                <td class="py-4 font-bold">₹<?= (int)$o['total'] ?></td>
                                <td class="py-4">
                                    <span class="rounded-full px-3 py-1 text-sm font-bold <?= $badge[$o['status']] ?>">
                                        <?= htmlspecialchars($o['status']) ?>
                                    </span>
                                </td>
                                <td class="py-4 text-right">
                                    <?php if ($o['status'] === 'New'): ?>
                                        <button data-order="<?= (int)$o['id'] ?>" data-next="Preparing"
                                                class="order-btn rounded-xl bg-[#ff4d0a] px-4 py-2 text-sm font-bold text-white hover:bg-orange-500">
                                            Accept
                                        </button>
                                    <?php elseif ($o['status'] === 'Preparing'): ?>
                                        <button data-order="<?= (int)$o['id'] ?>" data-next="Ready"
                                                class="order-btn rounded-xl bg-yellow-500 px-4 py-2 text-sm font-bold text-white hover:bg-yellow-400">
                                            Mark Ready
                                        </button>
                                    <?php else: ?>
                                        <span class="text-sm font-semibold text-green-600">
                                            <i class="fa-solid fa-motorcycle mr-1"></i>Awaiting rider
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Right column -->
            <div class="space-y-6">

                <!-- Menu availability -->
                <section class="rounded-3xl bg-white p-6 shadow">
                    <h3 class="text-2xl font-extrabold">Menu <span class="text-[#ff4d0a]">Availability</span></h3>

                    <ul class="mt-4 space-y-3">
                        <?php foreach ($menu as $m): ?>
                            <li class="flex items-center justify-between rounded-2xl border border-gray-200 p-4">
                                <div>
                                    <p class="font-bold"><?= htmlspecialchars($m['name']) ?></p>
                                    <p class="text-sm text-gray-500">₹<?= (int)$m['price'] ?></p>
                                </div>
                                <label class="relative inline-flex cursor-pointer items-center">
<input type="checkbox" class="menu-toggle peer sr-only" data-id="<?= (int)$m['id'] ?>" <?= $m['available'] ? 'checked' : '' ?>>                                    <div class="h-6 w-11 rounded-full bg-gray-300 transition peer-checked:bg-green-500
                                                after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5
                                                after:rounded-full after:bg-white after:transition
                                                peer-checked:after:translate-x-5"></div>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>

                <!-- Quick actions -->
                <section class="rounded-3xl bg-white p-6 shadow">
                    <h3 class="text-2xl font-extrabold">Quick <span class="text-[#ff4d0a]">Actions</span></h3>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <a href="rest_menu.php"
                           class="rounded-2xl bg-orange-50 p-4 text-center font-bold hover:bg-orange-100">
                            <i class="fa-solid fa-plus mb-2 block text-2xl text-[#ff4d0a]"></i> Add Item
                        </a>
                        <a href="rest_menu.php"
                           class="rounded-2xl bg-orange-50 p-4 text-center font-bold hover:bg-orange-100">
                            <i class="fa-solid fa-boxes-stacked mb-2 block text-2xl text-[#ff4d0a]"></i> Stock
                        </a>
                        <a href="reports.php"
                           class="rounded-2xl bg-orange-50 p-4 text-center font-bold hover:bg-orange-100">
                            <i class="fa-solid fa-chart-line mb-2 block text-2xl text-[#ff4d0a]"></i> Reports
                        </a>
                        <a href="rest_orders.php?filter=history"
                           class="rounded-2xl bg-orange-50 p-4 text-center font-bold hover:bg-orange-100">
                            <i class="fa-solid fa-clock-rotate-left mb-2 block text-2xl text-[#ff4d0a]"></i> History
                        </a>
                    </div>
                </section>

            </div>
        </div>
    </main>
</div>

<script>
    const CSRF = <?= json_encode($_SESSION['csrf']) ?>;

    async function post(url, data) {
        const res = await fetch(url, { method: 'POST', body: new URLSearchParams({ ...data, csrf: CSRF }) });
        const out = await res.json();
        if (!out.ok) throw new Error(out.error || 'Request failed');
    }

    document.querySelectorAll('.order-btn').forEach(btn => btn.addEventListener('click', async () => {
        btn.disabled = true;
        try {
            await post('rest_order_update.php', { order_id: btn.dataset.order, status: btn.dataset.next });
            location.reload();
        } catch (e) { alert(e.message); btn.disabled = false; }
    }));

    document.querySelectorAll('.menu-toggle').forEach(t => t.addEventListener('change', async () => {
        try {
            await post('rest_menu_action.php', { action: 'toggle', id: t.dataset.id, available: t.checked ? 1 : 0 });
        } catch (e) { alert(e.message); t.checked = !t.checked; }
    }));
</script>
<script src="live_updates.js"></script>
</body>
</html>
