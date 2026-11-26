<?php
session_start();
require_once __DIR__ . '/../db.php';

// Redirect guests
if (!isset($_SESSION['user_id'])) {
    header('Location: ../Login/customer.php');
    exit;
}

$userId   = (int)$_SESSION['user_id'];
$userName = $_SESSION['full_name'] ?? 'Customer';

// Handle order cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order_id'])) {
    $cancelId = (int)$_POST['cancel_order_id'];
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND customer_id = ? AND status = 'placed'");
        $stmt->execute([$cancelId, $userId]);
        header("Location: orders.php?msg=cancelled");
        exit;
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}

// Fetch customer's orders
$orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT o.id, o.total, o.payment_method, o.status, o.delivery_address, o.created_at,
               (SELECT GROUP_CONCAT(CONCAT(oi.quantity, 'x ', f.name) SEPARATOR ', ')
                FROM order_items oi JOIN food f ON oi.food_id = f.id WHERE oi.order_id = o.id) AS items
        FROM orders o
        WHERE o.customer_id = ?
        ORDER BY o.id DESC
    ");
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Orders fetch error: " . $e->getMessage());
}

$statusBadge = [
    'placed'           => 'bg-orange-100 text-[#ff4d0a]',
    'New'              => 'bg-orange-100 text-[#ff4d0a]',
    'preparing'        => 'bg-yellow-100 text-yellow-700',
    'Preparing'        => 'bg-yellow-100 text-yellow-700',
    'ready'            => 'bg-blue-100 text-blue-700',
    'Ready'            => 'bg-blue-100 text-blue-700',
    'out_for_delivery' => 'bg-indigo-100 text-indigo-700',
    'delivered'        => 'bg-green-100 text-green-700',
    'Delivered'        => 'bg-green-100 text-green-700',
    'Completed'        => 'bg-green-100 text-green-700',
    'cancelled'        => 'bg-red-100 text-red-700',
    'Cancelled'        => 'bg-red-100 text-red-700',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - FoodFlow</title>
    <link rel="icon" type="image/png" href="../favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body class="m-0 min-h-screen bg-[#fffaf5] text-slate-900">

    <!-- Top Navbar -->
    <header class="flex h-16 w-full items-center justify-between bg-white px-6 shadow-sm">
        <a href="../dashboard/customer.php" class="flex items-center gap-2 no-underline">
            <i class="fa-solid fa-kitchen-set text-3xl text-[#ff4d0a]"></i>
            <span class="text-3xl font-extrabold text-slate-900">Food<span class="text-[#ff4d0a]">Flow</span></span>
        </a>

        <div class="flex items-center gap-6">
            <a href="../Menu/menuafterlogin.html" class="font-semibold text-slate-700 hover:text-[#ff4d0a]">Menu</a>
            <a href="orders.php" class="font-bold text-[#ff4d0a]">My Orders</a>
            <a href="../dashboard/profile.php" class="flex items-center gap-2 rounded-full bg-orange-100 px-3 py-1 font-bold text-[#ff4d0a]">
                <i class="fa-regular fa-user"></i> <?= htmlspecialchars($userName) ?>
            </a>
            <a href="../logout.php" class="text-red-500 font-semibold hover:underline">Logout</a>
        </div>
    </header>

    <div class="flex min-h-[calc(100vh-64px)]">
        <!-- Sidebar -->
        <aside class="hidden w-64 shrink-0 bg-[#fff3e6] px-4 py-6 md:block">
            <nav class="flex flex-col gap-2 font-semibold">
                <a href="../dashboard/customer.php" class="flex items-center gap-4 rounded-xl px-4 py-3 text-slate-900 hover:bg-[#ffe3d2]">
                    <i class="fa-solid fa-house text-xl"></i><span>Dashboard</span>
                </a>
                <a href="../Menu/menuafterlogin.html" class="flex items-center gap-4 rounded-xl px-4 py-3 text-slate-900 hover:bg-[#ffe3d2]">
                    <i class="fa-solid fa-utensils text-xl"></i><span>Menu</span>
                </a>
                <a href="orders.php" class="flex items-center gap-4 rounded-xl bg-[#ffe3d2] px-4 py-3 font-bold text-[#ff4d0a]">
                    <i class="fa-solid fa-clipboard-list text-xl"></i><span>My Orders</span>
                </a>
                <a href="track_order.php" class="flex items-center gap-4 rounded-xl px-4 py-3 text-slate-900 hover:bg-[#ffe3d2]">
                    <i class="fa-solid fa-location-dot text-xl"></i><span>Track Order</span>
                </a>
                <a href="../dashboard/profile.php" class="flex items-center gap-4 rounded-xl px-4 py-3 text-slate-900 hover:bg-[#ffe3d2]">
                    <i class="fa-regular fa-user text-xl"></i><span>Profile</span>
                </a>
                <a href="../Help&Support/help.html" class="flex items-center gap-4 rounded-xl px-4 py-3 text-slate-900 hover:bg-[#ffe3d2]">
                    <i class="fa-regular fa-circle-question text-xl"></i><span>Help &amp; Support</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-6 md:p-10">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-extrabold md:text-4xl">Order <span class="text-[#ff4d0a]">History</span> 📦</h1>
                    <p class="mt-1 font-medium text-gray-500">Track and view all orders placed with your FoodFlow account.</p>
                </div>
                <a href="../Menu/menuafterlogin.html" class="rounded-2xl bg-[#ff4d0a] px-5 py-2.5 font-bold text-white shadow hover:bg-[#e94305]">
                    + Order More
                </a>
            </div>

            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'cancelled'): ?>
                <div class="mt-4 rounded-2xl bg-yellow-50 p-4 font-semibold text-yellow-800">
                    Order has been successfully cancelled.
                </div>
            <?php endif; ?>

            <div class="mt-8 space-y-5">
                <?php if (empty($orders)): ?>
                    <div class="rounded-3xl border border-dashed border-orange-200 bg-white p-12 text-center">
                        <i class="fa-solid fa-box-open mb-3 text-5xl text-orange-300"></i>
                        <h3 class="text-xl font-bold text-slate-800">No Orders Yet!</h3>
                        <p class="mt-1 text-sm font-medium text-gray-500">Explore our delicious pizzas, burgers, pasta and more.</p>
                        <a href="../Menu/menuafterlogin.html" class="mt-5 inline-block rounded-2xl bg-[#ff4d0a] px-6 py-3 font-bold text-white hover:bg-[#e94305]">
                            Explore Food Menu
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($orders as $o): ?>
                        <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm transition hover:shadow-md">
                            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 pb-4">
                                <div>
                                    <span class="text-lg font-black text-slate-900">Order #FF-<?= (int)$o['id'] ?></span>
                                    <span class="ml-3 text-xs font-semibold text-gray-500">
                                        <?= date('M d, Y · h:i A', strtotime($o['created_at'])) ?>
                                    </span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="rounded-full px-4 py-1 text-xs font-extrabold uppercase <?= $statusBadge[$o['status']] ?? 'bg-gray-100 text-gray-700' ?>">
                                        <?= htmlspecialchars(str_replace('_', ' ', $o['status'])) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
                                <div class="space-y-1">
                                    <p class="font-bold text-slate-800"><?= htmlspecialchars($o['items'] ?: 'Food Items') ?></p>
                                    <p class="text-xs text-gray-500">
                                        <i class="fa-solid fa-location-dot mr-1 text-[#ff4d0a]"></i>
                                        <?= htmlspecialchars($o['delivery_address']) ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        <i class="fa-solid fa-wallet mr-1 text-gray-400"></i>
                                        Payment: <?= htmlspecialchars($o['payment_method']) ?>
                                    </p>
                                </div>

                                <div class="flex items-center gap-4">
                                    <div class="text-right">
                                        <p class="text-xs font-bold text-gray-400">Total Paid</p>
                                        <p class="text-2xl font-black text-[#ff4d0a]">₹<?= number_format((float)$o['total']) ?></p>
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <a href="track_order.php?order_id=<?= (int)$o['id'] ?>" class="rounded-xl bg-[#ff4d0a] px-4 py-2 text-center text-sm font-bold text-white hover:bg-[#e94305]">
                                            Track Order →
                                        </a>
                                        <?php if (in_array(strtolower($o['status']), ['placed', 'new'])): ?>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                                                <input type="hidden" name="cancel_order_id" value="<?= (int)$o['id'] ?>">
                                                <button type="submit" class="w-full text-xs font-semibold text-red-500 hover:underline">
                                                    Cancel Order
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
