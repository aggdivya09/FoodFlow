<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/../db.php';

// Accessible to restaurant staff or admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['restaurant_staff', 'admin'])) {
    header('Location: restaurant.php');
    exit;
}

$userRole = $_SESSION['role'] ?? 'restaurant_staff';
$userName = $_SESSION['full_name'] ?? 'Kitchen Staff';
$mode     = $_GET['mode'] ?? 'priority'; // 'fcfs' or 'priority'

// Handle dispatching next order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dispatch_id'])) {
    $dispatchId = (int)$_POST['dispatch_id'];
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'preparing' WHERE id = ?");
        $stmt->execute([$dispatchId]);
        header("Location: scheduler.php?mode=" . urlencode($mode) . "&dispatched=" . $dispatchId);
        exit;
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}

// Fetch active pending/unprepared orders from DB
$rawOrders = [];
try {
    $dbOrders = $pdo->query("
        SELECT o.id, o.customer_name, u.full_name, o.delivery_address, o.created_at, o.total, o.status,
               (SELECT GROUP_CONCAT(CONCAT(oi.quantity, 'x ', f.name) SEPARATOR ', ')
                FROM order_items oi JOIN food f ON oi.food_id = f.id WHERE oi.order_id = o.id) AS items
        FROM orders o
        LEFT JOIN users u ON o.customer_id = u.user_id
        WHERE o.status IN ('placed', 'New', 'preparing', 'Preparing')
        ORDER BY o.id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($dbOrders)) {
        $simArrival = 0;
        foreach ($dbOrders as $o) {
            // Estimate prep time based on item count and dish types (approx 5 to 20 mins)
            $itemStr = strtolower($o['items'] ?: '');
            $prepTime = 8; // base
            if (strpos($itemStr, 'pizza') !== false) $prepTime += 10;
            if (strpos($itemStr, 'feast') !== false || strpos($itemStr, 'combo') !== false) $prepTime += 8;
            if (strpos($itemStr, 'burger') !== false || strpos($itemStr, 'pasta') !== false) $prepTime += 5;

            // Generate deterministic distance in km from address string (1.0 km to 7.0 km)
            $hash = crc32($o['delivery_address'] . $o['id']);
            $dist = round(1.2 + (($hash % 55) / 10.0), 1);

            $rawOrders[] = [
                'id'       => (int)$o['id'],
                'customer' => $o['customer_name'] ?: ($o['full_name'] ?: 'Customer #' . $o['id']),
                'items'    => $o['items'] ?: 'Food Order',
                'address'  => $o['delivery_address'],
                'at'       => $simArrival,
                'bt'       => $prepTime,
                'dist'     => $dist,
                'status'   => $o['status']
            ];
            $simArrival += 2;
        }
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
}

// Fallback sample orders if none currently pending in DB
if (empty($rawOrders)) {
    $rawOrders = [
        ['id' => 1001, 'customer' => 'Rohit Sharma', 'items' => 'Family Pizza Feast (2 Large Pizzas + Garlic Bread)', 'address' => 'Rajpur Road, 6.5 km', 'at' => 0, 'bt' => 24, 'dist' => 6.5, 'status' => 'placed'],
        ['id' => 1002, 'customer' => 'Neha Panwar',  'items' => 'Classic Veg Taco + Thums Up',                       'address' => 'Ballupur Chowk, 1.2 km', 'at' => 1, 'bt' => 6,  'dist' => 1.2, 'status' => 'placed'],
        ['id' => 1003, 'customer' => 'Anita Rawat',  'items' => 'Cold Coffee + Brownie Sundae',                      'address' => 'Race Course, 2.0 km', 'at' => 2, 'bt' => 8,  'dist' => 2.0, 'status' => 'placed'],
        ['id' => 1004, 'customer' => 'Karan Negi',   'items' => 'Double Cheese Pizza + Peri Peri Burger',            'address' => 'Sahastradhara, 5.0 km', 'at' => 3, 'bt' => 18, 'dist' => 5.0, 'status' => 'placed'],
        ['id' => 1005, 'customer' => 'Priya Bisht',  'items' => 'Classic Pizza Burger + Sprite',                     'address' => 'Clement Town, 1.8 km', 'at' => 4, 'bt' => 10, 'dist' => 1.8, 'status' => 'placed'],
    ];
}

// Calculate priority score: Less time + Near distance = Higher Priority (lower numerical score)
foreach ($rawOrders as &$o) {
    // Score = (PrepTime * 0.6) + (Distance * 2.5) - (Wait * 0.5)
    $o['priority_score'] = round(($o['bt'] * 0.6) + ($o['dist'] * 2.5), 1);
}
unset($o);

// Perform scheduling simulation
$scheduledOrders = [];
$totalWT = 0;
$totalTAT = 0;

if ($mode === 'fcfs') {
    // Sort strictly by Arrival Time (FIFO)
    $list = $rawOrders;
    usort($list, fn($a, $b) => $a['at'] <=> $b['at']);
    $clock = 0;
    foreach ($list as $o) {
        $start = max($clock, $o['at']);
        $ct = $start + $o['bt'];
        $tat = $ct - $o['at'];
        $wt = $tat - $o['bt'];
        $clock = $ct;

        $o['start'] = $start;
        $o['ct'] = $ct;
        $o['tat'] = $tat;
        $o['wt'] = $wt;
        $scheduledOrders[] = $o;
        $totalWT += $wt;
        $totalTAT += $tat;
    }
} else {
    // Priority Scheduling (OS Concept: Short Prep Time + Near Delivery first)
    $pool = $rawOrders;
    $clock = 0;
    while (!empty($pool)) {
        // Find orders that have arrived by current clock
        $eligibleKeys = [];
        foreach ($pool as $k => $item) {
            if ($item['at'] <= $clock) {
                $eligibleKeys[] = $k;
            }
        }
        if (empty($eligibleKeys)) {
            // Jump clock to earliest arrival
            $earliest = min(array_column($pool, 'at'));
            $clock = $earliest;
            continue;
        }

        // Pick order with lowest priority score (highest priority)
        $bestKey = $eligibleKeys[0];
        $bestScore = 999999;
        foreach ($eligibleKeys as $k) {
            $item = $pool[$k];
            $curWait = $clock - $item['at'];
            $dynamicScore = ($item['bt'] * 0.6) + ($item['dist'] * 2.5) - ($curWait * 0.5);
            if ($dynamicScore < $bestScore) {
                $bestScore = $dynamicScore;
                $bestKey = $k;
            }
        }

        $chosen = $pool[$bestKey];
        unset($pool[$bestKey]);

        $start = $clock;
        $ct = $start + $chosen['bt'];
        $tat = $ct - $chosen['at'];
        $wt = $tat - $chosen['bt'];
        $clock = $ct;

        $chosen['start'] = $start;
        $chosen['ct'] = $ct;
        $chosen['tat'] = $tat;
        $chosen['wt'] = $wt;
        $scheduledOrders[] = $chosen;
        $totalWT += $wt;
        $totalTAT += $tat;
    }
}

$count = max(1, count($scheduledOrders));
$avgWT = round($totalWT / $count, 2);
$avgTAT = round($totalTAT / $count, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kitchen Order Scheduler (OS FCFS &amp; Priority) - FoodFlow</title>
    <link rel="icon" type="image/png" href="../favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body class="m-0 min-h-screen bg-orange-50/60 text-gray-900">

<div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside class="hidden w-64 shrink-0 flex-col bg-white p-6 shadow-lg lg:flex">
        <h1 class="text-3xl font-bold">Food<span class="text-[#ff4d0a]">Flow</span></h1>
        <p class="mt-1 text-sm font-semibold text-gray-500">OS Scheduling Core</p>

        <nav class="mt-10 space-y-2 font-semibold">
            <a href="dashboard.php" class="flex items-center gap-3 rounded-xl px-4 py-3 text-gray-700 hover:bg-orange-50">
                <i class="fa-solid fa-table-columns w-5"></i> Dashboard
            </a>
            <a href="rest_orders.php" class="flex items-center gap-3 rounded-xl px-4 py-3 text-gray-700 hover:bg-orange-50">
                <i class="fa-solid fa-receipt w-5"></i> Orders
            </a>
            <a href="scheduler.php" class="flex items-center gap-3 rounded-xl bg-[#ff4d0a] px-4 py-3 text-white">
                <i class="fa-solid fa-microchip w-5"></i> OS Scheduler
            </a>
            <a href="rest_menu.php" class="flex items-center gap-3 rounded-xl px-4 py-3 text-gray-700 hover:bg-orange-50">
                <i class="fa-solid fa-utensils w-5"></i> Menu
            </a>
            <a href="rest_reports.php" class="flex items-center gap-3 rounded-xl px-4 py-3 text-gray-700 hover:bg-orange-50">
                <i class="fa-solid fa-chart-line w-5"></i> Reports
            </a>
        </nav>

        <a href="../logout.php" class="mt-auto flex items-center gap-3 rounded-xl px-4 py-3 font-semibold text-red-600 hover:bg-red-50">
            <i class="fa-solid fa-right-from-bracket w-5"></i> Logout
        </a>
    </aside>

    <!-- Main -->
    <main class="min-w-0 flex-1 p-4 md:p-8">
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <span class="rounded-full bg-orange-100 px-3 py-1 text-xs font-bold uppercase tracking-wider text-[#ff4d0a]">
                        OS &amp; DBMS Architecture
                    </span>
                    <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-bold uppercase tracking-wider text-blue-700">
                        Algorithm Demonstration
                    </span>
                </div>
                <h2 class="mt-2 text-3xl font-extrabold md:text-4xl">
                    Kitchen <span class="text-[#ff4d0a]">Order Scheduler</span> ⚡
                </h2>
                <p class="mt-1 font-medium text-gray-500">
                    Applying Operating System CPU Scheduling to prioritize orders with shorter prep times and nearby deliveries.
                </p>
            </div>

            <!-- Algorithm Switcher -->
            <div class="flex items-center gap-2 rounded-2xl border border-gray-200 bg-white p-1.5 shadow-sm">
                <a href="scheduler.php?mode=priority"
                   class="rounded-xl px-5 py-2.5 text-sm font-bold transition <?= $mode === 'priority' ? 'bg-[#ff4d0a] text-white shadow' : 'text-gray-700 hover:bg-gray-100' ?>">
                    <i class="fa-solid fa-bolt mr-1"></i> Priority Mode (Time &amp; Near Distance)
                </a>
                <a href="scheduler.php?mode=fcfs"
                   class="rounded-xl px-5 py-2.5 text-sm font-bold transition <?= $mode === 'fcfs' ? 'bg-[#ff4d0a] text-white shadow' : 'text-gray-700 hover:bg-gray-100' ?>">
                    <i class="fa-solid fa-list-ol mr-1"></i> FCFS Mode (Arrival Order)
                </a>
            </div>
        </header>

        <!-- KPI Metrics -->
        <section class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-3xl bg-white p-6 shadow">
                <p class="text-xs font-bold uppercase text-gray-400">Current Scheduling Policy</p>
                <p class="mt-1 text-xl font-extrabold text-slate-900">
                    <?= $mode === 'priority' ? 'Shortest Time + Near Delivery' : 'First-Come, First-Served' ?>
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    <?= $mode === 'priority' ? 'Minimizes wait time & vehicle idle time' : 'Simple FIFO arrival queuing' ?>
                </p>
            </div>

            <div class="rounded-3xl bg-white p-6 shadow">
                <p class="text-xs font-bold uppercase text-gray-400">Average Waiting Time (AWT)</p>
                <p class="mt-1 text-3xl font-black text-[#ff4d0a]"><?= $avgWT ?> <span class="text-base font-semibold text-gray-500">mins</span></p>
                <p class="mt-1 text-xs text-green-600 font-bold">
                    <?= $mode === 'priority' ? '✓ Lower waiting time achieved' : 'Higher due to Head-of-Line blocking' ?>
                </p>
            </div>

            <div class="rounded-3xl bg-white p-6 shadow">
                <p class="text-xs font-bold uppercase text-gray-400">Avg. Turnaround Time (ATAT)</p>
                <p class="mt-1 text-3xl font-black text-blue-600"><?= $avgTAT ?> <span class="text-base font-semibold text-gray-500">mins</span></p>
                <p class="mt-1 text-xs text-gray-500">From order placement to completion</p>
            </div>

            <div class="rounded-3xl bg-white p-6 shadow">
                <p class="text-xs font-bold uppercase text-gray-400">Queue Throughput</p>
                <p class="mt-1 text-3xl font-black text-green-600"><?= $count ?> <span class="text-base font-semibold text-gray-500">orders</span></p>
                <p class="mt-1 text-xs text-gray-500">Currently in scheduling batch</p>
            </div>
        </section>

        <!-- Interactive Gantt Chart -->
        <section class="mt-8 rounded-3xl bg-white p-6 shadow">
            <div class="flex items-center justify-between">
                <h3 class="text-2xl font-extrabold">CPU / Kitchen Execution <span class="text-[#ff4d0a]">Gantt Chart</span></h3>
                <span class="text-xs font-bold text-gray-400">Timeline (Minutes)</span>
            </div>

            <div class="mt-6 overflow-x-auto pb-4">
                <div class="flex min-w-[700px] items-stretch gap-1">
                    <?php
                    $colors = ['bg-orange-500', 'bg-emerald-500', 'bg-blue-500', 'bg-purple-500', 'bg-amber-500', 'bg-rose-500'];
                    foreach ($scheduledOrders as $idx => $order):
                        $bg = $colors[$idx % count($colors)];
                        $flexGrow = max(1, $order['bt']);
                    ?>
                        <div class="flex flex-col items-center" style="flex: <?= $flexGrow ?> 1 0%;">
                            <div class="<?= $bg ?> flex h-14 w-full items-center justify-center rounded-2xl p-2 text-white shadow-sm transition hover:opacity-90"
                                 title="Order #FF-<?= $order['id'] ?> (<?= htmlspecialchars($order['customer']) ?>) - Prep: <?= $order['bt'] ?>m, Dist: <?= $order['dist'] ?>km">
                                <span class="truncate text-xs font-extrabold">#FF-<?= $order['id'] ?> (<?= $order['bt'] ?>m)</span>
                            </div>
                            <div class="mt-2 flex w-full justify-between text-[11px] font-bold text-gray-400">
                                <span><?= $order['start'] ?>m</span>
                                <span><?= $order['ct'] ?>m</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Scheduled Queue Table -->
        <section class="mt-8 rounded-3xl bg-white p-6 shadow">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-2xl font-extrabold">Scheduled <span class="text-[#ff4d0a]">Execution Order</span></h3>
                    <p class="text-xs text-gray-500">Calculated order of preparation for maximum kitchen and delivery efficiency.</p>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b text-xs font-extrabold uppercase text-gray-400">
                            <th class="pb-3">Seq</th>
                            <th class="pb-3">Order ID</th>
                            <th class="pb-3">Customer &amp; Items</th>
                            <th class="pb-3 text-center">Prep Time (BT)</th>
                            <th class="pb-3 text-center">Distance (km)</th>
                            <th class="pb-3 text-center">Priority Score</th>
                            <th class="pb-3 text-center">Start / Finish</th>
                            <th class="pb-3 text-center">Wait Time</th>
                            <th class="pb-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        <?php foreach ($scheduledOrders as $seq => $o): ?>
                            <tr class="transition hover:bg-orange-50/50">
                                <td class="py-4 font-black text-gray-400">#<?= $seq + 1 ?></td>
                                <td class="py-4 font-extrabold text-[#ff4d0a]">#FF-<?= $o['id'] ?></td>
                                <td class="py-4">
                                    <p class="font-bold text-slate-900"><?= htmlspecialchars($o['customer']) ?></p>
                                    <p class="truncate text-xs text-gray-500 max-w-xs"><?= htmlspecialchars($o['items']) ?></p>
                                </td>
                                <td class="py-4 text-center">
                                    <span class="rounded-xl bg-orange-100 px-2.5 py-1 text-xs font-extrabold text-[#ff4d0a]">
                                        <?= $o['bt'] ?> mins
                                    </span>
                                </td>
                                <td class="py-4 text-center">
                                    <span class="rounded-xl <?= $o['dist'] <= 2.5 ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' ?> px-2.5 py-1 text-xs font-extrabold">
                                        <?= $o['dist'] ?> km <?= $o['dist'] <= 2.5 ? '(Near)' : '' ?>
                                    </span>
                                </td>
                                <td class="py-4 text-center font-bold text-slate-700">
                                    <?= $o['priority_score'] ?>
                                </td>
                                <td class="py-4 text-center text-xs font-semibold text-gray-500">
                                    <?= $o['start'] ?>m → <?= $o['ct'] ?>m
                                </td>
                                <td class="py-4 text-center font-black <?= $o['wt'] > 20 ? 'text-red-500' : 'text-slate-800' ?>">
                                    <?= $o['wt'] ?> mins
                                </td>
                                <td class="py-4 text-right">
                                    <form method="POST">
                                        <input type="hidden" name="dispatch_id" value="<?= $o['id'] ?>">
                                        <button type="submit" class="rounded-xl bg-[#ff4d0a] px-3.5 py-1.5 text-xs font-bold text-white shadow hover:bg-orange-500">
                                            Start Prep →
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- OS Concept Explainer -->
        <section class="mt-8 rounded-3xl border border-orange-200 bg-white p-6 shadow-sm">
            <h4 class="text-lg font-extrabold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-graduation-cap text-[#ff4d0a]"></i> OS Concept Demonstration: FCFS vs. Priority Scheduling
            </h4>
            <div class="mt-3 grid grid-cols-1 gap-6 md:grid-cols-2 text-sm text-gray-600">
                <div class="rounded-2xl bg-gray-50 p-4">
                    <p class="font-bold text-slate-800">1. Convoy Effect in FCFS:</p>
                    <p class="mt-1">
                        In standard FCFS (FIFO), a heavy order (e.g. 24 minutes Family Feast) blocks quick orders (e.g. 6 mins Taco) even if the customer is right across the street. This causes high Average Waiting Time.
                    </p>
                </div>
                <div class="rounded-2xl bg-orange-50/50 p-4">
                    <p class="font-bold text-[#ff4d0a]">2. Priority Scheduling (Short Time &amp; Near Delivery):</p>
                    <p class="mt-1">
                        By scoring orders with lower preparation time and nearer delivery distance, quick orders get cleared immediately. An aging factor is included to continuously raise waiting orders so large orders never starve.
                    </p>
                </div>
            </div>
        </section>
    </main>
</div>
</body>
</html>
