<?php
session_start();
require_once __DIR__ . '/../db.php';

// Redirect guests to login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../Login/customer.php');
    exit;
}

$userId   = (int)$_SESSION['user_id'];
$userName = $_SESSION['full_name'] ?? 'Customer';
$userEmail = $_SESSION['email'] ?? '';
$userRole = $_SESSION['role'] ?? 'customer';

// Ensure orders are attributed to customer account
if ($userRole !== 'customer') {
    $cStmt = $pdo->prepare("SELECT user_id, full_name, email FROM users WHERE role = 'customer' LIMIT 1");
    $cStmt->execute();
    if ($cust = $cStmt->fetch(PDO::FETCH_ASSOC)) {
        $userId = (int)$cust['user_id'];
        $userName = $cust['full_name'];
        $userEmail = $cust['email'];
    }
}

$message = '';
$messageType = '';

// Handle Order Placement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cartData = $_POST['cart_json'] ?? '';
    $address  = trim($_POST['address'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $payment  = trim($_POST['payment_method'] ?? 'Cash on delivery');
    $notes    = trim($_POST['notes'] ?? '');

    $cartItems = json_decode($cartData, true) ?: [];

    if (empty($cartItems)) {
        $message = "Your cart is empty. Please add items before checking out.";
        $messageType = "error";
    } elseif ($address === '') {
        $message = "Please provide a delivery address.";
        $messageType = "error";
    } else {
        try {
            $pdo->beginTransaction();

            $total = 0;
            foreach ($cartItems as $item) {
                $qty = max(1, (int)($item['qty'] ?? 1));
                $price = (float)($item['price'] ?? 0);
                $total += $qty * $price;
            }

            // 1. Insert into orders
            $stmt = $pdo->prepare("
                INSERT INTO orders (customer_id, customer_name, total, payment_method, status, delivery_address, notes)
                VALUES (?, ?, ?, ?, 'placed', ?, ?)
            ");
            $stmt->execute([$userId, $userName, $total, $payment, $address, $notes]);
            $orderId = (int)$pdo->lastInsertId();

            // 2. Insert order items
            $itemStmt = $pdo->prepare("
                INSERT INTO order_items (order_id, food_id, quantity, price)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($cartItems as $item) {
                $qty = max(1, (int)($item['qty'] ?? 1));
                $price = (float)($item['price'] ?? 0);
                $name = trim($item['name'] ?? 'Dish');

                // Match with food table or create/fallback
                $foodId = (int)($item['food_id'] ?? 0);
                if ($foodId <= 0) {
                    $fQuery = $pdo->prepare("SELECT id FROM food WHERE name = ? LIMIT 1");
                    $fQuery->execute([$name]);
                    $foodId = (int)$fQuery->fetchColumn();
                    if ($foodId <= 0) {
                        $foodId = 1; // Fallback to first menu item
                    }
                }

                $itemStmt->execute([$orderId, $foodId, $qty, $price]);
            }

            // 3. Insert payments record
            $payStatus = ($payment === 'Cash on delivery') ? 'pending' : 'paid';
            $payStmt = $pdo->prepare("
                INSERT INTO payments (order_id, amount, method, status, paid_at)
                VALUES (?, ?, ?, ?, ?)
            ");
            $payStmt->execute([
                $orderId,
                $total,
                $payment,
                $payStatus,
                $payStatus === 'paid' ? date('Y-m-d H:i:s') : null
            ]);

            // 4. Insert deliveries record (auto-assign to hub)
            $delStmt = $pdo->prepare("
                INSERT INTO deliveries (order_id, delivery_person_id, status)
                VALUES (?, 3, 'assigned')
            ");
            $delStmt->execute([$orderId]);

            $pdo->commit();

            // Update user phone / address if empty
            if ($phone !== '' || $address !== '') {
                $upStmt = $pdo->prepare("UPDATE users SET phone = COALESCE(NULLIF(phone, ''), ?), address = COALESCE(NULLIF(address, ''), ?) WHERE user_id = ?");
                $upStmt->execute([$phone, $address, $userId]);
            }

            // Redirect to order tracking
            header("Location: ../My_orders/track_order.php?order_id=" . $orderId . "&new=1");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Order checkout failed: " . $e->getMessage());
            $message = "Unable to place your order right now: " . $e->getMessage();
            $messageType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - FoodFlow</title>
    <link rel="icon" type="image/png" href="../favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <script>
        window.FF_USER = <?= json_encode((string)$userId) ?>;
    </script>
</head>
<body class="m-0 min-h-screen bg-[#fffaf5] text-slate-900">

    <!-- Navbar -->
    <header class="flex h-16 w-full items-center justify-between bg-white px-6 shadow-sm">
        <a href="../dashboard/customer.php" class="flex items-center gap-2 no-underline">
            <i class="fa-solid fa-kitchen-set text-3xl text-[#ff4d0a]"></i>
            <span class="text-3xl font-extrabold text-slate-900">Food<span class="text-[#ff4d0a]">Flow</span></span>
        </a>
        <div class="flex items-center gap-4 text-sm font-semibold">
            <a href="../Menu/menuafterlogin.html" class="hover:text-[#ff4d0a]">Menu</a>
            <a href="../My_orders/orders.php" class="hover:text-[#ff4d0a]">My Orders</a>
            <a href="../dashboard/customer.php" class="hover:text-[#ff4d0a]">Dashboard</a>
        </div>
    </header>

    <main class="mx-auto max-w-4xl p-6 md:p-10">
        <h1 class="text-3xl font-extrabold md:text-4xl">Complete Your <span class="text-[#ff4d0a]">Order</span> 🛒</h1>
        <p class="mt-1 text-gray-500 font-medium">Review your items and confirm delivery details.</p>

        <?php if ($message): ?>
            <div class="mt-4 rounded-2xl p-4 <?= $messageType === 'error' ? 'bg-red-50 text-red-600' : 'bg-green-50 text-green-700' ?> font-semibold">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form id="checkoutForm" method="POST" action="checkout.php" class="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-3">
            <input type="hidden" name="cart_json" id="cartJsonInput">

            <!-- Delivery Details -->
            <div class="space-y-6 lg:col-span-2">
                <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <i class="fa-solid fa-location-dot text-[#ff4d0a]"></i> Delivery Address
                    </h2>
                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="block text-xs font-bold uppercase text-gray-500">Full Name</label>
                            <input type="text" readonly value="<?= htmlspecialchars($userName) ?>" class="mt-1 w-full rounded-2xl border border-gray-200 bg-gray-50 p-3.5 font-semibold text-gray-700 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-gray-500">Contact Phone Number</label>
                            <input type="tel" name="phone" required placeholder="e.g. 9876543210" class="mt-1 w-full rounded-2xl border border-gray-200 p-3.5 font-semibold outline-none focus:border-orange-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-gray-500">Delivery Address</label>
                            <textarea name="address" rows="3" required placeholder="House/Flat number, Street, Landmark, City" class="mt-1 w-full rounded-2xl border border-gray-200 p-3.5 font-semibold outline-none focus:border-orange-500"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-gray-500">Kitchen Instructions (Optional)</label>
                            <input type="text" name="notes" placeholder="e.g. Less spicy, Extra napkins, Ring doorbell" class="mt-1 w-full rounded-2xl border border-gray-200 p-3.5 outline-none focus:border-orange-500">
                        </div>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <i class="fa-solid fa-credit-card text-[#ff4d0a]"></i> Payment Method
                    </h2>
                    <div class="mt-4 space-y-3">
                        <label class="flex cursor-pointer items-center justify-between rounded-2xl border border-gray-200 p-4 hover:border-orange-500 has-[:checked]:border-orange-500 has-[:checked]:bg-orange-50/50">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="payment_method" value="Cash on delivery" checked class="accent-[#ff4d0a]">
                                <span class="font-bold text-slate-800">Cash on Delivery (COD)</span>
                            </div>
                            <i class="fa-solid fa-money-bill-wave text-xl text-green-600"></i>
                        </label>

                        <label class="flex cursor-pointer items-center justify-between rounded-2xl border border-gray-200 p-4 hover:border-orange-500 has-[:checked]:border-orange-500 has-[:checked]:bg-orange-50/50">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="payment_method" value="UPI" class="accent-[#ff4d0a]">
                                <span class="font-bold text-slate-800">UPI / QR Code</span>
                            </div>
                            <i class="fa-solid fa-qrcode text-xl text-blue-600"></i>
                        </label>

                        <label class="flex cursor-pointer items-center justify-between rounded-2xl border border-gray-200 p-4 hover:border-orange-500 has-[:checked]:border-orange-500 has-[:checked]:bg-orange-50/50">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="payment_method" value="Card" class="accent-[#ff4d0a]">
                                <span class="font-bold text-slate-800">Credit / Debit Card</span>
                            </div>
                            <i class="fa-solid fa-credit-card text-xl text-purple-600"></i>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="lg:col-span-1">
                <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-bold">Order <span class="text-[#ff4d0a]">Summary</span></h2>
                    <div id="cartItemsList" class="mt-4 divide-y divide-gray-100 max-h-80 overflow-y-auto">
                        <!-- Populated by JS -->
                    </div>

                    <div class="mt-4 space-y-2 border-t border-gray-100 pt-4 text-sm font-semibold text-gray-600">
                        <div class="flex justify-between"><span>Items Total</span><span id="subtotalVal">₹0</span></div>
                        <div class="flex justify-between"><span>Delivery Fee</span><span class="text-green-600 font-bold">FREE</span></div>
                        <div class="flex justify-between text-base font-extrabold text-slate-900 border-t border-gray-100 pt-2">
                            <span>Grand Total</span><span id="grandTotalVal" class="text-xl text-[#ff4d0a]">₹0</span>
                        </div>
                    </div>

                    <button type="submit" id="submitBtn" class="mt-6 w-full rounded-2xl bg-[#ff4d0a] py-4 text-lg font-bold text-white shadow hover:bg-[#e94305] disabled:opacity-50">
                        Confirm &amp; Place Order →
                    </button>
                    <a href="../dashboard/customer.php" class="mt-3 block text-center text-sm font-bold text-gray-500 hover:text-gray-800">
                        ← Continue Ordering
                    </a>
                </div>
            </div>
        </form>
    </main>

    <script>
        const cartKey = "ff2_cart_" + window.FF_USER;
        let cart = {};
        try {
            cart = JSON.parse(localStorage.getItem(cartKey)) || {};
        } catch(e) {}

        const listEl = document.getElementById("cartItemsList");
        const subtotalEl = document.getElementById("subtotalVal");
        const grandTotalEl = document.getElementById("grandTotalVal");
        const cartInput = document.getElementById("cartJsonInput");
        const submitBtn = document.getElementById("submitBtn");

        const items = Object.values(cart);
        if (items.length === 0) {
            listEl.innerHTML = '<p class="py-6 text-center text-sm font-semibold text-gray-400">Your cart is empty.</p>';
            submitBtn.disabled = true;
            cartInput.value = "[]";
        } else {
            let total = 0;
            listEl.innerHTML = items.map(item => {
                const itemTotal = item.qty * item.price;
                total += itemTotal;
                return `
                    <div class="flex items-center justify-between py-3">
                        <div class="min-w-0 flex-1 pr-2">
                            <p class="truncate font-bold text-sm text-slate-800">${item.name}</p>
                            <p class="text-xs text-gray-500">₹${item.price} × ${item.qty}</p>
                        </div>
                        <span class="font-extrabold text-sm text-slate-900">₹${itemTotal}</span>
                    </div>
                `;
            }).join("");

            subtotalEl.textContent = "₹" + total.toLocaleString("en-IN");
            grandTotalEl.textContent = "₹" + total.toLocaleString("en-IN");
            cartInput.value = JSON.stringify(items);
        }

        // On successful order submission, clear localStorage cart
        document.getElementById("checkoutForm").addEventListener("submit", function() {
            try {
                localStorage.removeItem(cartKey);
            } catch(e) {}
        });
    </script>
</body>
</html>
