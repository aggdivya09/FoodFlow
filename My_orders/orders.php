<?php
session_start();
require_once __DIR__ . '/_lib.php';
$uid = customer_guard();

$st = $pdo->prepare(
    'SELECT o.*, d.status AS d_status
     FROM orders o LEFT JOIN deliveries d ON d.order_id = o.order_id
     WHERE o.customer_id = ? ORDER BY o.order_id DESC LIMIT 50'
);
$st->execute([$uid]);
$orders = $st->fetchAll(PDO::FETCH_ASSOC);

$items = [];
if ($orders) {
    $ids = implode(',', array_map('intval', array_column($orders, 'order_id')));
    foreach ($pdo->query("SELECT order_id, item_name, quantity, price FROM order_items WHERE order_id IN ($ids) ORDER BY id") as $r) {
        $items[$r['order_id']][] = $r;
    }
}

$badge = [-1 => 'bg-red-100 text-red-700', 0 => 'bg-orange-100 text-orange-700', 1 => 'bg-yellow-100 text-yellow-700',
           2 => 'bg-blue-100 text-blue-700', 3 => 'bg-purple-100 text-purple-700', 4 => 'bg-green-100 text-green-700'];
$active = 'orders';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | FoodFlow</title>
    <link rel="icon" type="image/png" href="../favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body class="min-h-screen bg-[#fffaf5] text-slate-900">
<?php include __DIR__ . '/_header.php'; ?>

<main class="mx-auto max-w-4xl px-4 py-8">
    <h1 class="text-3xl font-extrabold">My Orders</h1>
    <p class="mt-1 text-gray-500">Your recent orders and their status.</p>
    <div id="msg" class="mt-4 hidden rounded-xl bg-red-50 px-4 py-3 font-semibold text-red-600"></div>

    <?php if (!$orders): ?>
        <div class="mt-8 rounded-3xl bg-white p-10 text-center shadow">
            <i class="fa-solid fa-bag-shopping text-5xl text-orange-300"></i>
            <p class="mt-3 text-xl font-bold">No orders yet</p>
            <a href="../Menu/menuafterlogin.html" class="mt-4 inline-block rounded-xl bg-orange-600 px-6 py-3 font-bold text-white hover:bg-orange-700">Browse the menu</a>
        </div>
    <?php endif; ?>

    <div class="mt-6 space-y-5">
    <?php foreach ($orders as $o):
        $stage = order_stage($o, $o['d_status'] ? ['status' => $o['d_status']] : null);
        $lines = $items[$o['order_id']] ?? [];
        $reorder = array_map(fn($l) => ['name' => $l['item_name'], 'price' => (float)$l['price'], 'qty' => (int)$l['quantity']], $lines);
    ?>
        <article class="rounded-3xl bg-white p-6 shadow">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-lg font-extrabold">Order #<?= (int)$o['order_id'] ?></p>
                    <p class="text-sm text-gray-500"><?= e(date('d M Y, h:i A', strtotime($o['created_at']))) ?> · <?= e($o['payment_method']) ?></p>
                </div>
                <span class="rounded-full px-4 py-1.5 text-sm font-bold <?= $badge[$stage] ?>"><?= e(STAGE_LABELS[$stage]) ?></span>
            </div>

            <ul class="mt-4 space-y-1 text-gray-700">
                <?php foreach ($lines as $l): ?>
                    <li class="flex justify-between"><span><?= (int)$l['quantity'] ?> × <?= e($l['item_name']) ?></span>
                        <span>₹<?= number_format($l['quantity'] * $l['price']) ?></span></li>
                <?php endforeach; ?>
            </ul>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                <p class="text-lg font-extrabold">Total <span class="text-orange-600">₹<?= number_format($o['total_amount']) ?></span></p>
                <div class="flex flex-wrap gap-2">
                    <?php if ($stage >= 0 && $stage < 4): ?>
                        <a href="track_order.php?id=<?= (int)$o['order_id'] ?>" class="rounded-xl bg-orange-600 px-4 py-2 text-sm font-bold text-white hover:bg-orange-700">
                            <i class="fa-solid fa-location-dot mr-1"></i>Track
                        </a>
                    <?php else: ?>
                        <a href="track_order.php?id=<?= (int)$o['order_id'] ?>" class="rounded-xl bg-gray-100 px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-200">Details</a>
                    <?php endif; ?>
                    <?php if ($o['status'] === 'New'): ?>
                        <button data-cancel="<?= (int)$o['order_id'] ?>" class="rounded-xl bg-red-50 px-4 py-2 text-sm font-bold text-red-600 hover:bg-red-100">Cancel</button>
                    <?php endif; ?>
                    <button data-reorder="<?= e(json_encode($reorder)) ?>" class="rounded-xl bg-gray-100 px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-200">
                        <i class="fa-solid fa-rotate-right mr-1"></i>Order again
                    </button>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
</main>

<script>
const UID  = <?= json_encode((string)$uid) ?>;
const CSRF = <?= json_encode($_SESSION['csrf']) ?>;
const KEY  = 'ff2_cart_' + UID;
try { localStorage.setItem('ff_user', UID); } catch (e) {}
const slug = s => s.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

document.addEventListener('click', async e => {
    const re = e.target.closest('[data-reorder]');
    if (re) {
        let cart = {};
        try { cart = JSON.parse(localStorage.getItem(KEY)) || {}; } catch (x) {}
        JSON.parse(re.dataset.reorder).forEach(i => {
            const id = slug(i.name);
            cart[id] = cart[id] || { id, name: i.name, price: i.price, img: '', cat: '', href: '', qty: 0 };
            cart[id].qty += i.qty;
        });
        localStorage.setItem(KEY, JSON.stringify(cart));
        location.href = 'checkout.php';
        return;
    }
    const c = e.target.closest('[data-cancel]');
    if (c) {
        if (!confirm('Cancel this order?')) return;
        const res = await fetch('order_api.php', { method: 'POST',
            body: new URLSearchParams({ action: 'cancel', order_id: c.dataset.cancel, csrf: CSRF }) });
        const out = await res.json();
        if (out.ok) { location.reload(); return; }
        const m = document.getElementById('msg'); m.textContent = out.error; m.classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
});
</script>
</body>
</html>
