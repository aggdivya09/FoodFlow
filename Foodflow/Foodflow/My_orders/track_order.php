<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Login/customer.php');
    exit;
}

$userId   = (int)$_SESSION['user_id'];
$userName = $_SESSION['full_name'] ?? 'Customer';
$orderId  = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

$order = null;
$orderItems = [];

try {
    if ($orderId > 0) {
        $stmt = $pdo->prepare("
            SELECT o.*, u.full_name AS customer_name, u.phone AS customer_phone,
                   d.status AS delivery_status, d.assigned_at, d.delivered_at,
                   rider.full_name AS rider_name, rider.phone AS rider_phone
            FROM orders o
            JOIN users u ON o.customer_id = u.user_id
            LEFT JOIN deliveries d ON o.id = d.order_id
            LEFT JOIN users rider ON d.delivery_person_id = rider.user_id
            WHERE o.id = ? AND o.customer_id = ?
            LIMIT 1
        ");
        $stmt->execute([$orderId, $userId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$order) {
        // Fetch latest active or recent order for this user
        $stmt = $pdo->prepare("
            SELECT o.*, u.full_name AS customer_name, u.phone AS customer_phone,
                   d.status AS delivery_status, d.assigned_at, d.delivered_at,
                   rider.full_name AS rider_name, rider.phone AS rider_phone
            FROM orders o
            JOIN users u ON o.customer_id = u.user_id
            LEFT JOIN deliveries d ON o.id = d.order_id
            LEFT JOIN users rider ON d.delivery_person_id = rider.user_id
            WHERE o.customer_id = ?
            ORDER BY o.id DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($order) {
        $itemStmt = $pdo->prepare("
            SELECT oi.quantity, oi.price, f.name
            FROM order_items oi
            JOIN food f ON oi.food_id = f.id
            WHERE oi.order_id = ?
        ");
        $itemStmt->execute([$order['id']]);
        $orderItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    error_log("Track order error: " . $e->getMessage());
}

// Compute active stage (1 to 4)
$stage = 1;
if ($order) {
    $st = strtolower($order['status']);
    if (in_array($st, ['placed', 'new'])) $stage = 1;
    elseif (in_array($st, ['preparing'])) $stage = 2;
    elseif (in_array($st, ['ready', 'out_for_delivery'])) $stage = 3;
    elseif (in_array($st, ['delivered', 'completed'])) $stage = 4;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Order - FoodFlow</title>
    <link rel="icon" type="image/png" href="../favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body class="m-0 min-h-screen bg-[#fffaf5] text-slate-900">

    <!-- Navbar -->
    <header class="flex h-16 w-full items-center justify-between bg-white px-6 shadow-sm">
        <a href="../dashboard/customer.php" class="flex items-center gap-2 no-underline">
            <i class="fa-solid fa-kitchen-set text-3xl text-[#ff4d0a]"></i>
            <span class="text-3xl font-extrabold text-slate-900">Food<span class="text-[#ff4d0a]">Flow</span></span>
        </a>

        <div class="flex items-center gap-6">
            <a href="../Menu/menuafterlogin.html" class="font-semibold text-slate-700 hover:text-[#ff4d0a]">Menu</a>
            <a href="orders.php" class="font-semibold text-slate-700 hover:text-[#ff4d0a]">My Orders</a>
            <a href="../dashboard/profile.php" class="flex items-center gap-2 rounded-full bg-orange-100 px-3 py-1 font-bold text-[#ff4d0a]">
                <i class="fa-regular fa-user"></i> <?= htmlspecialchars($userName) ?>
            </a>
            <a href="../logout.php" class="text-red-500 font-semibold hover:underline">Logout</a>
        </div>
    </header>

    <main class="mx-auto max-w-4xl p-6 md:p-10">
        <?php if (!$order): ?>
            <div class="rounded-3xl border border-dashed border-orange-200 bg-white p-12 text-center shadow-sm">
                <i class="fa-solid fa-location-crosshairs mb-3 text-5xl text-orange-300"></i>
                <h2 class="text-2xl font-bold text-slate-800">No Active Order Found</h2>
                <p class="mt-2 text-gray-500">You don't have any placed orders yet.</p>
                <a href="../Menu/menuafterlogin.html" class="mt-6 inline-block rounded-2xl bg-[#ff4d0a] px-6 py-3 font-bold text-white hover:bg-[#e94305]">
                    Browse Delicious Food
                </a>
            </div>
        <?php else: ?>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold md:text-4xl">Track Order <span class="text-[#ff4d0a]">#FF-<?= (int)$order['id'] ?></span></h1>
                    <p class="mt-1 font-medium text-gray-500">
                        Placed on <?= date('M d, Y · h:i A', strtotime($order['created_at'])) ?> · Estimated 30 mins
                    </p>
                </div>
                <button onclick="window.location.reload()" class="rounded-2xl border border-gray-200 bg-white px-4 py-2 text-sm font-bold shadow-sm hover:bg-gray-50">
                    <i class="fa-solid fa-rotate-right mr-2 text-[#ff4d0a]"></i> Refresh Status
                </button>
            </div>

            <!-- Progress Timeline -->
            <section class="mt-8 rounded-3xl border border-gray-200 bg-white p-6 md:p-8 shadow-sm">
                <div class="relative flex items-center justify-between">
                    <div class="absolute left-0 top-1/2 -z-0 h-1.5 w-full -translate-y-1/2 bg-gray-200">
                        <div class="h-1.5 bg-[#ff4d0a] transition-all duration-500" style="width: <?= ($stage - 1) * 33.33 ?>%"></div>
                    </div>

                    <!-- Step 1 -->
                    <div class="relative z-10 flex flex-col items-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full <?= $stage >= 1 ? 'bg-[#ff4d0a] text-white shadow-lg shadow-orange-500/30' : 'bg-gray-200 text-gray-500' ?> font-bold">
                            <i class="fa-solid fa-check"></i>
                        </div>
                        <p class="mt-2 text-xs font-bold text-slate-800">Order Placed</p>
                    </div>

                    <!-- Step 2 -->
                    <div class="relative z-10 flex flex-col items-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full <?= $stage >= 2 ? 'bg-[#ff4d0a] text-white shadow-lg shadow-orange-500/30' : 'bg-gray-200 text-gray-500' ?> font-bold">
                            <i class="fa-solid fa-fire-burner"></i>
                        </div>
                        <p class="mt-2 text-xs font-bold text-slate-800">In Kitchen</p>
                    </div>

                    <!-- Step 3 -->
                    <div class="relative z-10 flex flex-col items-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full <?= $stage >= 3 ? 'bg-[#ff4d0a] text-white shadow-lg shadow-orange-500/30' : 'bg-gray-200 text-gray-500' ?> font-bold">
                            <i class="fa-solid fa-motorcycle"></i>
                        </div>
                        <p class="mt-2 text-xs font-bold text-slate-800">Out for Delivery</p>
                    </div>

                    <!-- Step 4 -->
                    <div class="relative z-10 flex flex-col items-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full <?= $stage >= 4 ? 'bg-green-600 text-white shadow-lg shadow-green-500/30' : 'bg-gray-200 text-gray-500' ?> font-bold">
                            <i class="fa-solid fa-house-chimney"></i>
                        </div>
                        <p class="mt-2 text-xs font-bold text-slate-800">Delivered</p>
                    </div>
                </div>

                <div class="mt-8 rounded-2xl bg-orange-50 p-4 text-center font-bold text-slate-800">
                    <?php if ($stage === 1): ?>
                        <i class="fa-solid fa-clock mr-2 text-[#ff4d0a]"></i> Your order has been placed and sent to FoodFlow Kitchen!
                    <?php elseif ($stage === 2): ?>
                        <i class="fa-solid fa-fire-burner mr-2 text-[#ff4d0a]"></i> The chef is freshly preparing your meal right now!
                    <?php elseif ($stage === 3): ?>
                        <i class="fa-solid fa-motorcycle mr-2 text-[#ff4d0a]"></i> Food is on the way with your delivery partner!
                    <?php else: ?>
                        <i class="fa-solid fa-circle-check mr-2 text-green-600"></i> Order delivered! Enjoy your meal!
                    <?php endif; ?>
                </div>
            </section>

            <!-- Order Details Grid -->
            <div class="mt-8 grid grid-cols-1 gap-6 md:grid-cols-2">
                <!-- Delivery Info -->
                <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-bold"><i class="fa-solid fa-location-dot mr-2 text-[#ff4d0a]"></i> Delivery Details</h3>
                    <div class="mt-4 space-y-3 text-sm">
                        <div>
                            <p class="text-xs font-bold text-gray-400">Deliver To</p>
                            <p class="font-bold text-slate-800"><?= htmlspecialchars($userName) ?></p>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-400">Address</p>
                            <p class="font-medium text-slate-700"><?= htmlspecialchars($order['delivery_address']) ?></p>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-gray-400">Payment</p>
                            <p class="font-bold text-slate-800"><?= htmlspecialchars($order['payment_method']) ?> (Total: ₹<?= number_format((float)$order['total']) ?>)</p>
                        </div>
                    </div>
                </div>

                <!-- Delivery Partner -->
                <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-bold"><i class="fa-solid fa-helmet-safety mr-2 text-[#ff4d0a]"></i> Delivery Partner</h3>
                    <div class="mt-4 flex items-center gap-4">
                        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-green-100 text-2xl text-green-600">
                            <i class="fa-solid fa-motorcycle"></i>
                        </div>
                        <div>
                            <p class="font-extrabold text-slate-900"><?= htmlspecialchars($order['rider_name'] ?: 'Rider Aviral') ?></p>
                            <p class="text-xs font-semibold text-gray-500">FoodFlow Express Rider</p>
                            <p class="mt-1 text-xs font-bold text-green-600">
                                <i class="fa-solid fa-phone mr-1"></i> <?= htmlspecialchars($order['rider_phone'] ?: '+91 98765 43212') ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Breakdown -->
            <div class="mt-8 rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-bold">Ordered Items</h3>
                <div class="mt-4 divide-y divide-gray-100">
                    <?php if (empty($orderItems)): ?>
                        <p class="py-3 text-sm text-gray-500">1x Food Order</p>
                    <?php else: ?>
                        <?php foreach ($orderItems as $item): ?>
                            <div class="flex items-center justify-between py-3">
                                <div>
                                    <p class="font-bold text-slate-800"><?= htmlspecialchars($item['name']) ?></p>
                                    <p class="text-xs text-gray-500">Qty: <?= (int)$item['quantity'] ?> × ₹<?= number_format((float)$item['price']) ?></p>
                                </div>
                                <span class="font-bold text-slate-900">₹<?= number_format($item['quantity'] * $item['price']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
