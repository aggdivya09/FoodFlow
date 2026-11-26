<?php
session_start();

// Don't let the browser cache this page (Back button after logout shows login instead)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../db.php';

// Only logged-in admins can see this page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../Login/admin.php');
    exit;
}

$adminName = $_SESSION['full_name'] ?? 'Admin';

/*
 * ---------------------------------------------------------------
 * REAL DATA from your existing tables (users, restaurants).
 * Wrapped in try/catch so the page still opens if a query fails.
 * ---------------------------------------------------------------
 */
$roleCounts   = ['restaurant_staff' => 0, 'delivery_person' => 0, 'admin' => 0, 'customers' => 0];
$restaurants  = 0;
$recentUsers  = [];

try {
    $rows = $pdo->query("SELECT role, COUNT(*) AS c FROM users GROUP BY role")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        if (isset($roleCounts[$r['role']])) {
            $roleCounts[$r['role']] = (int)$r['c'];
        } else {
            $roleCounts['customers'] += (int)$r['c'];   // any other role counts as a customer
        }
    }

    $restaurants = (int)$pdo->query("SELECT COUNT(*) FROM restaurants WHERE is_active = 1")->fetchColumn();

    $recentUsers = $pdo->query(
        "SELECT user_id, full_name, email, role FROM users ORDER BY user_id DESC LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
}

$totalUsers = array_sum($roleCounts);

/*
 * ---------------------------------------------------------------
 * SAMPLE DATA - replace with queries on your orders tables.
 * ---------------------------------------------------------------
 */
$todayOrders = $yesterdayOrders = 0;
$todayRevenue = $yesterdayRevenue = 0.0;
$revenue7 = $orderStatus = $recentOrders = $topDishes = [];

try {
    $t = $pdo->query(
        "SELECT
            COALESCE(SUM(DATE(created_at) = CURDATE()), 0) AS t_orders,
            COALESCE(SUM(DATE(created_at) = CURDATE() - INTERVAL 1 DAY), 0) AS y_orders,
            COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() AND status <> 'Cancelled' THEN total_amount END), 0) AS t_rev,
            COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() - INTERVAL 1 DAY AND status <> 'Cancelled' THEN total_amount END), 0) AS y_rev
         FROM orders"
    )->fetch(PDO::FETCH_ASSOC);
    $todayOrders = (int)$t['t_orders'];     $yesterdayOrders = (int)$t['y_orders'];
    $todayRevenue = (float)$t['t_rev'];     $yesterdayRevenue = (float)$t['y_rev'];

    // last 7 days revenue (days with no orders show 0)
    $byDay = $pdo->query(
        "SELECT DATE(created_at) AS d, SUM(total_amount) AS r FROM orders
         WHERE status <> 'Cancelled' AND created_at >= CURDATE() - INTERVAL 6 DAY
         GROUP BY DATE(created_at)"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
    $base = new DateTime($pdo->query("SELECT CURDATE()")->fetchColumn());
    for ($i = 6; $i >= 0; $i--) {
        $day = (clone $base)->modify("-$i day");
        $revenue7[] = [$day->format('D'), (float)($byDay[$day->format('Y-m-d')] ?? 0)];
    }

    // today's orders by status
    $todayStatus = $pdo->query(
        "SELECT status, COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE() GROUP BY status"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
    $statusColors = ['New' => 'bg-[#ff4d0a]', 'Preparing' => 'bg-yellow-500', 'Ready' => 'bg-green-500',
                     'Completed' => 'bg-blue-500', 'Cancelled' => 'bg-red-500'];
    foreach ($statusColors as $s => $cls) {
        $orderStatus[] = [$s, (int)($todayStatus[$s] ?? 0), $cls];
    }

    $recentOrders = $pdo->query(
        "SELECT order_id AS id, customer_name AS customer, total_amount AS total, status
         FROM orders ORDER BY order_id DESC LIMIT 6"
    )->fetchAll(PDO::FETCH_ASSOC);

    $topDishes = $pdo->query(
        "SELECT oi.item_name, SUM(oi.quantity) AS q
         FROM order_items oi JOIN orders o ON o.order_id = oi.order_id
         WHERE o.status <> 'Cancelled'
         GROUP BY oi.item_name ORDER BY q DESC LIMIT 5"
    )->fetchAll(PDO::FETCH_NUM);
} catch (PDOException $e) {
    error_log($e->getMessage());
}

$vs = fn($now, $prev) => $prev > 0
    ? sprintf('%+d%% vs yesterday', round(($now - $prev) / $prev * 100))
    : 'No orders yesterday';

$stats = [
    ['label' => "Today's Orders",    'value' => $todayOrders,                       'icon' => 'fa-receipt',           'color' => 'bg-orange-100 text-[#ff4d0a]', 'note' => $vs($todayOrders, $yesterdayOrders)],
    ['label' => "Today's Revenue",   'value' => '₹' . number_format($todayRevenue), 'icon' => 'fa-indian-rupee-sign', 'color' => 'bg-green-100 text-green-600',  'note' => $vs($todayRevenue, $yesterdayRevenue)],
    ['label' => 'Total Users',       'value' => $totalUsers,                        'icon' => 'fa-users',             'color' => 'bg-blue-100 text-blue-600',    'note' => 'All roles'],
    ['label' => 'Active Restaurants','value' => $restaurants,                       'icon' => 'fa-store',             'color' => 'bg-yellow-100 text-yellow-600','note' => 'Currently open'],
];

$maxRevenue  = max(array_merge([1], array_column($revenue7, 1)));
$statusTotal = max(1, array_sum(array_column($orderStatus, 1)));
$maxDish     = max(array_merge([1], array_column($topDishes, 1)));


$badge = [
    'New'       => 'bg-orange-100 text-[#ff4d0a]',
    'Preparing' => 'bg-yellow-100 text-yellow-700',
    'Ready'     => 'bg-green-100 text-green-700',
    'Completed' => 'bg-blue-100 text-blue-700',
    'Cancelled' => 'bg-red-100 text-red-700',
];

$roleLabel = [
    'restaurant_staff' => ['Restaurant Staff', 'bg-orange-100 text-[#ff4d0a]'],
    'delivery_person'  => ['Delivery',         'bg-green-100 text-green-700'],
    'admin'            => ['Admin',            'bg-purple-100 text-purple-700'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FoodFlow</title>
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
        <p class="mt-1 text-sm font-semibold text-gray-500">Admin Panel</p>

        <nav class="mt-10 space-y-2 font-semibold">
            <a href="#"
               class="flex items-center gap-3 rounded-xl bg-[#ff4d0a] px-4 py-3 text-white">
                <i class="fa-solid fa-table-columns w-5"></i> Dashboard
            </a>
            <a href="admin_users.php"
               class="flex items-center gap-3 rounded-xl px-4 py-3 text-gray-700 hover:bg-orange-50">
                <i class="fa-solid fa-users w-5"></i> Users
            </a>
            <a href="admin_restaurants.php"
               class="flex items-center gap-3 rounded-xl px-4 py-3 text-gray-700 hover:bg-orange-50">
                <i class="fa-solid fa-store w-5"></i> Restaurants
            </a>
            <a href="admin_orders.php"
               class="flex items-center gap-3 rounded-xl px-4 py-3 text-gray-700 hover:bg-orange-50">
                <i class="fa-solid fa-receipt w-5"></i> Orders
            </a>
            <a href="admin_menu.php"
               class="flex items-center gap-3 rounded-xl px-4 py-3 text-gray-700 hover:bg-orange-50">
                <i class="fa-solid fa-utensils w-5"></i> Menu
            </a>
            <a href="admin_orders.php"
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
    <main class="min-w-0 flex-1 p-4 md:p-8">

        <!-- Top bar -->
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-3xl font-extrabold md:text-4xl">
                    Hello, <span class="text-[#ff4d0a]"><?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?></span> 👋
                </h2>
                <p class="mt-1 font-medium text-gray-500">
                    Here's how FoodFlow is doing today.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <div class="flex items-center gap-3 rounded-2xl bg-white px-5 py-3 shadow">
                    <i class="fa-regular fa-calendar text-[#ff4d0a]"></i>
                    <span class="font-bold"><?= date('D, d M Y') ?></span>
                </div>
                <a href="../logout.php"
                   class="flex items-center gap-2 rounded-2xl bg-white px-4 py-3 font-bold text-red-600 shadow hover:bg-red-50 lg:hidden">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </header>

        <!-- Stat cards -->
        <section class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <?php foreach ($stats as $s): ?>
                <div class="flex items-center gap-4 rounded-3xl bg-white p-6 shadow">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl text-2xl <?= $s['color'] ?>">
                        <i class="fa-solid <?= $s['icon'] ?>"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-500"><?= htmlspecialchars($s['label']) ?></p>
                        <p class="text-3xl font-extrabold"><?= htmlspecialchars((string)$s['value']) ?></p>
                        <p class="text-xs font-semibold text-gray-400"><?= htmlspecialchars($s['note']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <div class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-3">

            <!-- Revenue chart -->
            <section class="rounded-3xl bg-white p-6 shadow xl:col-span-2">
                <div class="flex items-center justify-between">
                    <h3 class="text-2xl font-extrabold">Revenue <span class="text-[#ff4d0a]">This Week</span></h3>
                    <a href="reports.php" class="font-bold text-[#ff4d0a] hover:underline">Full report →</a>
                </div>

                <div class="mt-6 flex h-56 items-end gap-3 sm:gap-5">
                    <?php foreach ($revenue7 as $i => $d): ?>
                        <?php $h = max(8, (int)round($d[1] / $maxRevenue * 100)); ?>
                        <div class="flex h-full flex-1 flex-col items-center justify-end gap-2">
                            <span class="text-xs font-bold text-gray-500">₹<?= number_format($d[1] / 1000, 1) ?>k</span>
                            <div class="w-full rounded-t-xl <?= $i === count($revenue7) - 1 ? 'bg-[#ff4d0a]' : 'bg-orange-200' ?>"
                                 style="height: <?= $h ?>%"></div>
                            <span class="text-sm font-bold text-gray-600"><?= htmlspecialchars($d[0]) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Order status -->
            <section class="rounded-3xl bg-white p-6 shadow">
                <h3 class="text-2xl font-extrabold">Orders <span class="text-[#ff4d0a]">Today</span></h3>

                <ul class="mt-5 space-y-4">
                    <?php foreach ($orderStatus as $o): ?>
                        <li>
                            <div class="flex items-center justify-between text-sm font-bold">
                                <span><?= htmlspecialchars($o[0]) ?></span>
                                <span><?= (int)$o[1] ?></span>
                            </div>
                            <div class="mt-1 h-2.5 w-full rounded-full bg-gray-100">
                                <div class="h-2.5 rounded-full <?= $o[2] ?>"
                                     style="width: <?= round($o[1] / $statusTotal * 100) ?>%"></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">

            <!-- Recent orders -->
            <section class="rounded-3xl bg-white p-6 shadow xl:col-span-2">
                <div class="flex items-center justify-between">
                    <h3 class="text-2xl font-extrabold">Recent <span class="text-[#ff4d0a]">Orders</span></h3>
                    <a href="admin_orders.php" class="font-bold text-[#ff4d0a] hover:underline">View all →</a>
                </div>

                <div class="mt-5 overflow-x-auto">
                    <table class="w-full min-w-[460px] text-left">
                        <thead>
                            <tr class="border-b text-sm text-gray-500">
                                <th class="pb-3">Order</th>
                                <th class="pb-3">Customer</th>
                                <th class="pb-3">Total</th>
                                <th class="pb-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentOrders as $o): ?>
                            <tr class="border-b last:border-0">
                                <td class="py-4 font-bold">#<?= (int)$o['id'] ?></td>
                                <td class="py-4 font-semibold"><?= htmlspecialchars($o['customer']) ?></td>
                                <td class="py-4 font-bold">₹<?= number_format($o['total']) ?></td>
                                <td class="py-4">
                                    <span class="rounded-full px-3 py-1 text-sm font-bold <?= $badge[$o['status']] ?>">
                                        <?= htmlspecialchars($o['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Top dishes -->
            <section class="rounded-3xl bg-white p-6 shadow">
                <h3 class="text-2xl font-extrabold">Top <span class="text-[#ff4d0a]">Dishes</span></h3>

                <ul class="mt-5 space-y-4">
                    <?php foreach ($topDishes as $i => $d): ?>
                        <li>
                            <div class="flex items-center justify-between text-sm font-bold">
                                <span><?= $i + 1 ?>. <?= htmlspecialchars($d[0]) ?></span>
                                <span class="text-gray-500"><?= (int)$d[1] ?> sold</span>
                            </div>
                            <div class="mt-1 h-2.5 w-full rounded-full bg-gray-100">
                                <div class="h-2.5 rounded-full bg-[#ff4d0a]"
                                     style="width: <?= round($d[1] / $maxDish * 100) ?>%"></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">

            <!-- Team overview (real counts) -->
            <section class="rounded-3xl bg-white p-6 shadow">
                <h3 class="text-2xl font-extrabold">Team <span class="text-[#ff4d0a]">Overview</span></h3>

                <ul class="mt-5 space-y-3">
                    <li class="flex items-center justify-between rounded-2xl bg-orange-50 p-4">
                        <span class="font-bold"><i class="fa-solid fa-kitchen-set mr-3 text-[#ff4d0a]"></i>Restaurant Staff</span>
                        <span class="text-xl font-extrabold"><?= $roleCounts['restaurant_staff'] ?></span>
                    </li>
                    <li class="flex items-center justify-between rounded-2xl bg-green-50 p-4">
                        <span class="font-bold"><i class="fa-solid fa-motorcycle mr-3 text-green-600"></i>Delivery Partners</span>
                        <span class="text-xl font-extrabold"><?= $roleCounts['delivery_person'] ?></span>
                    </li>
                    <li class="flex items-center justify-between rounded-2xl bg-blue-50 p-4">
                        <span class="font-bold"><i class="fa-solid fa-user mr-3 text-blue-600"></i>Customers</span>
                        <span class="text-xl font-extrabold"><?= $roleCounts['customers'] ?></span>
                    </li>
                    <li class="flex items-center justify-between rounded-2xl bg-purple-50 p-4">
                        <span class="font-bold"><i class="fa-solid fa-user-shield mr-3 text-purple-600"></i>Admins</span>
                        <span class="text-xl font-extrabold"><?= $roleCounts['admin'] ?></span>
                    </li>
                </ul>
            </section>

            <!-- Newest users (real data) -->
            <section class="rounded-3xl bg-white p-6 shadow">
                <div class="flex items-center justify-between">
                    <h3 class="text-2xl font-extrabold">New <span class="text-[#ff4d0a]">Users</span></h3>
                    <a href="admin_users.php" class="font-bold text-[#ff4d0a] hover:underline">All →</a>
                </div>

                <ul class="mt-5 space-y-3">
                    <?php if (!$recentUsers): ?>
                        <li class="text-sm font-semibold text-gray-400">No users found.</li>
                    <?php endif; ?>
                    <?php foreach ($recentUsers as $u): ?>
                        <?php
                            $label = $roleLabel[$u['role']] ?? ['Customer', 'bg-blue-100 text-blue-700'];
                            $initial = strtoupper(mb_substr(trim($u['full_name']) ?: '?', 0, 1));
                        ?>
                        <li class="flex items-center gap-3 rounded-2xl border border-gray-200 p-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-orange-100 font-extrabold text-[#ff4d0a]">
                                <?= htmlspecialchars($initial) ?>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-bold"><?= htmlspecialchars($u['full_name']) ?></p>
                                <p class="truncate text-xs text-gray-500"><?= htmlspecialchars($u['email']) ?></p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold <?= $label[1] ?>">
                                <?= htmlspecialchars($label[0]) ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <!-- Quick actions -->
            <section class="rounded-3xl bg-white p-6 shadow">
                <h3 class="text-2xl font-extrabold">Quick <span class="text-[#ff4d0a]">Actions</span></h3>
                <div class="mt-5 grid grid-cols-2 gap-3">
                    <a href="admin_users.php"
                       class="rounded-2xl bg-orange-50 p-4 text-center font-bold hover:bg-orange-100">
                        <i class="fa-solid fa-user-plus mb-2 block text-2xl text-[#ff4d0a]"></i> Add Staff
                    </a>
                    <a href="admin_users.php"
                       class="rounded-2xl bg-orange-50 p-4 text-center font-bold hover:bg-orange-100">
                        <i class="fa-solid fa-motorcycle mb-2 block text-2xl text-[#ff4d0a]"></i> Add Rider
                    </a>
                    <a href="admin_restaurants.php"
                       class="rounded-2xl bg-orange-50 p-4 text-center font-bold hover:bg-orange-100">
                        <i class="fa-solid fa-store mb-2 block text-2xl text-[#ff4d0a]"></i> Add Restaurant
                    </a>
                    <a href="reports.php"
                       class="rounded-2xl bg-orange-50 p-4 text-center font-bold hover:bg-orange-100">
                        <i class="fa-solid fa-chart-line mb-2 block text-2xl text-[#ff4d0a]"></i> Reports
                    </a>
                </div>
            </section>
        </div>
    </main>
</div>
<script src="live_updates.js"></script>
</body>
</html>
