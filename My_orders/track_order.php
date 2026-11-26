<?php
session_start();
require_once __DIR__ . '/_lib.php';
$uid = customer_guard();

// no id given: open the newest order that is still active
$id = (int)($_GET['id'] ?? 0);
if ($id === 0) {
    $st = $pdo->prepare(
        'SELECT o.*, d.status AS d_status FROM orders o
         LEFT JOIN deliveries d ON d.order_id = o.order_id
         WHERE o.customer_id = ? ORDER BY o.order_id DESC LIMIT 20'
    );
    $st->execute([$uid]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $s = order_stage($row, $row['d_status'] ? ['status' => $row['d_status']] : null);
        if ($s >= 0 && $s < 4) { $id = (int)$row['order_id']; break; }
    }
    if ($id === 0) { header('Location: orders.php'); exit; }
}

$t = track_data($pdo, $id, $uid);
if (!$t) { http_response_code(404); header('Location: orders.php'); exit; }

$o = $t['order'];
$stage = $t['stage'];
$steps = [
    0 => ['Order placed',        'We have received your order.',            'fa-receipt'],
    1 => ['Preparing',           'The kitchen is cooking your food.',       'fa-fire-burner'],
    2 => ['Ready for pickup',    'Waiting for a delivery partner.',         'fa-bag-shopping'],
    3 => ['Out for delivery',    'Your rider is on the way.',               'fa-motorcycle'],
    4 => ['Delivered',           'Enjoy your meal!',                        'fa-circle-check'],
];
$total = 0.0;
foreach ($t['items'] as $i) $total += $i['quantity'] * $i['price'];
$active = 'track';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Order #<?= (int)$o['order_id'] ?> | FoodFlow</title>
    <link rel="icon" type="image/png" href="../favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body class="min-h-screen bg-[#fffaf5] text-slate-900">
<?php include __DIR__ . '/_header.php'; ?>

<main class="mx-auto max-w-4xl px-4 py-8">
    <?php if (isset($_GET['placed'])): ?>
        <div class="mb-6 rounded-2xl bg-green-50 px-5 py-4 font-semibold text-green-700">
            <i class="fa-solid fa-circle-check mr-2"></i>Order placed! The restaurant has been notified.
        </div>
    <?php endif; ?>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-3xl font-extrabold">Order #<?= (int)$o['order_id'] ?></h1>
            <p class="text-gray-500">Placed <?= e(date('d M Y, h:i A', strtotime($o['created_at']))) ?> · <?= e($o['payment_method']) ?></p>
        </div>
        <a href="orders.php" class="rounded-xl bg-gray-100 px-4 py-2 font-bold text-gray-700 hover:bg-gray-200">← All orders</a>
    </div>

    <!-- Status -->
    <section class="mt-6 rounded-3xl bg-white p-6 shadow">
        <?php if ($stage === -1): ?>
            <div class="flex items-center gap-4 text-red-600">
                <i class="fa-solid fa-circle-xmark text-4xl"></i>
                <div><p class="text-xl font-extrabold">Order cancelled</p>
                <p class="text-sm text-gray-500">This order was cancelled.</p></div>
            </div>
        <?php else: ?>
            <p class="text-sm font-bold uppercase tracking-wide text-orange-600">Current status</p>
            <p id="stageLabel" class="text-2xl font-extrabold"><?= e($t['label']) ?></p>

            <ol class="mt-6 space-y-0">
                <?php foreach ($steps as $n => [$title, $sub, $icon]):
                    $done = $stage >= $n; $now = $stage === $n; ?>
                    <li class="flex gap-4">
                        <div class="flex flex-col items-center">
                            <span class="flex h-11 w-11 items-center justify-center rounded-full text-lg
                                <?= $done ? 'bg-orange-600 text-white' : 'bg-gray-100 text-gray-400' ?> <?= $now && $stage < 4 ? 'ring-4 ring-orange-200' : '' ?>">
                                <i class="fa-solid <?= $icon ?>"></i>
                            </span>
                            <?php if ($n < 4): ?><span class="h-8 w-1 <?= $stage > $n ? 'bg-orange-600' : 'bg-gray-200' ?>"></span><?php endif; ?>
                        </div>
                        <div class="pb-6">
                            <p class="font-bold <?= $done ? 'text-slate-900' : 'text-gray-400' ?>"><?= e($title) ?></p>
                            <p class="text-sm <?= $done ? 'text-gray-500' : 'text-gray-300' ?>"><?= e($sub) ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <?php if ($t['rider']): ?>
            <div class="mt-2 flex items-center justify-between rounded-2xl bg-orange-50 p-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-orange-600 text-white"><i class="fa-solid fa-motorcycle"></i></div>
                    <div><p class="font-bold"><?= e($t['rider']['name']) ?></p><p class="text-sm text-gray-500">Your delivery partner</p></div>
                </div>
                <?php if ($t['rider']['phone']): ?>
                    <a href="tel:<?= e($t['rider']['phone']) ?>" class="rounded-xl bg-orange-600 px-4 py-2 font-bold text-white hover:bg-orange-700">
                        <i class="fa-solid fa-phone mr-1"></i>Call
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($o['status'] === 'New'): ?>
            <button id="cancelBtn" class="mt-4 rounded-xl bg-red-50 px-4 py-2 font-bold text-red-600 hover:bg-red-100">Cancel order</button>
            <p id="cancelMsg" class="mt-2 hidden text-sm font-semibold text-red-600"></p>
        <?php endif; ?>
    </section>

    <!-- Summary -->
    <section class="mt-6 grid gap-6 md:grid-cols-2">
        <div class="rounded-3xl bg-white p-6 shadow">
            <h2 class="text-lg font-extrabold">Items</h2>
            <ul class="mt-3 space-y-2">
                <?php foreach ($t['items'] as $i): ?>
                    <li class="flex justify-between"><span><?= (int)$i['quantity'] ?> × <?= e($i['item_name']) ?></span>
                        <span class="font-semibold">₹<?= number_format($i['quantity'] * $i['price']) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <p class="mt-4 flex justify-between border-t pt-3 text-lg font-extrabold"><span>Total</span><span class="text-orange-600">₹<?= number_format($total) ?></span></p>
        </div>
        <div class="rounded-3xl bg-white p-6 shadow">
            <h2 class="text-lg font-extrabold">Delivery address</h2>
            <p class="mt-3 text-gray-700"><?= e($o['delivery_address']) ?></p>
            <p class="mt-2 text-sm text-gray-500"><i class="fa-solid fa-phone mr-1"></i><?= e($o['customer_phone']) ?></p>
            <?php if ($o['note'] && $stage !== -1): ?>
                <p class="mt-3 rounded-xl bg-gray-50 p-3 text-sm text-gray-600"><b>Your note:</b> <?= e($o['note']) ?></p>
            <?php endif; ?>
        </div>
    </section>
</main>

<script>
const CSRF = <?= json_encode($_SESSION['csrf']) ?>;
const ORDER_ID = <?= (int)$o['order_id'] ?>;
let stage = <?= (int)$stage ?>;
let riderName = <?= json_encode($t['rider']['name'] ?? '') ?>;

// refresh the page when the status changes (stops once delivered or cancelled)
if (stage >= 0 && stage < 4) {
    const timer = setInterval(async () => {
        if (document.hidden) return;
        try {
            const res = await fetch('order_api.php', { method: 'POST',
                body: new URLSearchParams({ action: 'track', order_id: ORDER_ID, csrf: CSRF }) });
            const out = await res.json();
            if (out.ok && (out.stage !== stage || (out.rider ? out.rider.name : '') !== riderName)) {
                clearInterval(timer); location.reload();
            }
        } catch (e) {}
    }, 8000);
}

const cancel = document.getElementById('cancelBtn');
if (cancel) cancel.addEventListener('click', async () => {
    if (!confirm('Cancel this order?')) return;
    const res = await fetch('order_api.php', { method: 'POST',
        body: new URLSearchParams({ action: 'cancel', order_id: ORDER_ID, csrf: CSRF }) });
    const out = await res.json();
    if (out.ok) { location.reload(); return; }
    const m = document.getElementById('cancelMsg'); m.textContent = out.error; m.classList.remove('hidden');
});
</script>
</body>
</html>
