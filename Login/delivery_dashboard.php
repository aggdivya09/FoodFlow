<?php
session_start();

// Don't let the browser cache this page (Back button after logout shows login instead)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../db.php';

// Only logged-in delivery staff can see this page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'delivery_person') {
    header('Location: delivery.php');
    exit;
}

$riderName = $_SESSION['full_name'] ?? 'Rider';
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$riderId = (int)$_SESSION['user_id'];

$st = $pdo->prepare("SELECT is_online FROM users WHERE user_id = ?");
$st->execute([$riderId]);
$isOnline = (bool)$st->fetchColumn();

$rest = $pdo->query(
    "SELECT restaurant_name, CONCAT_WS(', ', address, city) AS addr FROM restaurants WHERE is_active = 1 LIMIT 1"
)->fetch(PDO::FETCH_ASSOC) ?: ['restaurant_name' => 'FoodFlow Kitchen', 'addr' => ''];

// My deliveries + (when online) unclaimed ones waiting for pickup
$st = $pdo->prepare(
    "SELECT o.order_id AS id, o.customer_name AS customer, COALESCE(o.customer_phone, '') AS phone,
            o.delivery_address AS address, o.total_amount AS total, o.payment_method AS payment,
            d.status, TIMESTAMPDIFF(MINUTE, d.assigned_at, NOW()) AS mins,
            (SELECT GROUP_CONCAT(CONCAT(oi.quantity, 'x ', oi.item_name) SEPARATOR ', ')
               FROM order_items oi WHERE oi.order_id = o.order_id) AS items
     FROM deliveries d
     JOIN orders o ON o.order_id = d.order_id
     WHERE (d.delivery_person_id = ?
            OR (d.delivery_person_id IS NULL AND d.status = 'Assigned' AND ? = 1))
       AND (d.status <> 'Delivered' OR DATE(d.delivered_at) = CURDATE())
       AND o.status <> 'Cancelled'
     ORDER BY FIELD(d.status, 'Assigned', 'Picked Up', 'Delivered'), d.assigned_at DESC"
);
$st->execute([$riderId, $isOnline ? 1 : 0]);
$deliveries = $st->fetchAll(PDO::FETCH_ASSOC);
foreach ($deliveries as &$d) {
    $d['restaurant'] = $rest['restaurant_name'];
    $d['pickup']     = $rest['addr'];
    $d['time']       = ago_label((int)$d['mins']);
    $d['items']      = $d['items'] ?? '';
}
unset($d);

$statuses = ['Assigned', 'Picked Up', 'Delivered'];
$counts = array_fill_keys($statuses, 0);
$earnings = 0;
foreach ($deliveries as $d) {
    $counts[$d['status']]++;
    if ($d['status'] === 'Delivered') {
        $earnings += 40;   // sample flat fee per delivery - change to your rule
    }
}

$badge = [
    'Assigned'  => 'bg-orange-100 text-[#ff4d0a]',
    'Picked Up' => 'bg-yellow-100 text-yellow-700',
    'Delivered' => 'bg-green-100 text-green-700',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Dashboard - FoodFlow</title>
    <link rel="icon" type="image/png" href="../favicon.png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>

<body class="m-0 min-h-screen bg-orange-50/60 text-gray-900">

<!-- Top bar -->
<header class="sticky top-0 z-40 bg-white shadow">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-4 md:px-8">

        <div>
            <h1 class="text-3xl font-bold">
                Food<span class="text-[#ff4d0a]">Flow</span>
            </h1>
            <p class="text-xs font-semibold text-gray-500">Delivery Partner</p>
        </div>

        <div class="flex items-center gap-4">

            <!-- Online / offline -->
            <label class="flex cursor-pointer items-center gap-3 rounded-2xl bg-orange-50 px-4 py-2">
                <span class="relative inline-flex items-center">
<input id="onlineToggle" type="checkbox" <?= $isOnline ? 'checked' : '' ?> class="peer sr-only">                    <span class="h-6 w-11 rounded-full bg-gray-300 transition peer-checked:bg-green-500
                                 after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5
                                 after:rounded-full after:bg-white after:transition
                                 peer-checked:after:translate-x-5"></span>
                </span>
                <span id="onlineText" class="text-sm font-bold text-green-600">Online</span>
            </label>

            <a href="../logout.php"
               class="flex items-center gap-2 rounded-xl px-4 py-2 font-semibold
                      text-red-600 hover:bg-red-50">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span class="hidden sm:inline">Logout</span>
            </a>
        </div>
    </div>
</header>

<main class="mx-auto max-w-6xl p-4 md:p-8">

    <!-- Greeting -->
    <section>
        <h2 class="text-3xl font-extrabold md:text-4xl">
            Hello, <span class="text-[#ff4d0a]"><?= htmlspecialchars($riderName, ENT_QUOTES, 'UTF-8') ?></span> 🛵
        </h2>
        <p class="mt-1 font-medium text-gray-500">
            Pick up orders, deliver them safely and keep customers happy.
        </p>
    </section>

    <!-- Stat cards -->
    <section class="mt-8 grid grid-cols-2 gap-4 xl:grid-cols-4">
        <div class="flex items-center gap-4 rounded-3xl bg-white p-5 shadow">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-orange-100 text-2xl text-[#ff4d0a]">
                <i class="fa-solid fa-bag-shopping"></i>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-500">To Pick Up</p>
                <p class="text-3xl font-extrabold" data-stat="Assigned"><?= $counts['Assigned'] ?></p>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-3xl bg-white p-5 shadow">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-yellow-100 text-2xl text-yellow-600">
                <i class="fa-solid fa-motorcycle"></i>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-500">On the Way</p>
                <p class="text-3xl font-extrabold" data-stat="Picked Up"><?= $counts['Picked Up'] ?></p>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-3xl bg-white p-5 shadow">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-green-100 text-2xl text-green-600">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-500">Delivered Today</p>
                <p class="text-3xl font-extrabold" data-stat="Delivered"><?= $counts['Delivered'] ?></p>
            </div>
        </div>
        <div class="flex items-center gap-4 rounded-3xl bg-white p-5 shadow">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-blue-100 text-2xl text-blue-600">
                <i class="fa-solid fa-indian-rupee-sign"></i>
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-500">Earnings Today</p>
                <p class="text-3xl font-extrabold">₹<span id="earnings"><?= $earnings ?></span></p>
            </div>
        </div>
    </section>

    <!-- Offline notice -->
    <div id="offlineNotice"
         class="mt-6 hidden rounded-2xl border border-yellow-300 bg-yellow-50 p-4 font-semibold text-yellow-800">
        <i class="fa-solid fa-circle-pause mr-2"></i>
        You're offline. You won't receive new deliveries until you go online.
    </div>

    <!-- Tabs -->
    <section class="mt-8 flex flex-wrap gap-2">
        <button type="button" data-status="All" class="tab-btn rounded-full px-5 py-2 text-sm font-bold">
            All (<span data-count="All"><?= count($deliveries) ?></span>)
        </button>
        <?php foreach ($statuses as $s): ?>
            <button type="button" data-status="<?= $s ?>" class="tab-btn rounded-full px-5 py-2 text-sm font-bold">
                <?= $s ?> (<span data-count="<?= $s ?>"><?= $counts[$s] ?></span>)
            </button>
        <?php endforeach; ?>
    </section>

    <!-- Deliveries -->
    <section id="grid" class="mt-6 grid grid-cols-1 gap-5 lg:grid-cols-2">
        <?php foreach ($deliveries as $d): ?>
            <?php
                $cod  = $d['payment'] === 'Cash on delivery';
                $maps = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($d['address']);
            ?>
            <article class="delivery-card rounded-3xl bg-white p-6 shadow"
                     data-id="<?= (int)$d['id'] ?>"
                     data-status="<?= htmlspecialchars($d['status'], ENT_QUOTES, 'UTF-8') ?>"
                     data-cod="<?= $cod ? '1' : '0' ?>"
                     data-total="<?= (int)$d['total'] ?>">

                <!-- Top row -->
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-2xl font-extrabold">Order <span class="text-[#ff4d0a]">#<?= (int)$d['id'] ?></span></h3>
                        <p class="mt-1 text-sm font-semibold text-gray-400">
                            <i class="fa-regular fa-clock mr-1"></i><?= htmlspecialchars($d['time']) ?>
                        </p>
                    </div>
                    <span class="status-badge rounded-full px-4 py-1.5 text-sm font-bold <?= $badge[$d['status']] ?>">
                        <?= htmlspecialchars($d['status']) ?>
                    </span>
                </div>

                <!-- Route -->
                <div class="mt-4 space-y-3 text-sm">
                    <div class="flex gap-3 rounded-2xl bg-orange-50/70 p-4">
                        <i class="fa-solid fa-store mt-1 w-4 text-[#ff4d0a]"></i>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Pick up from</p>
                            <p class="font-bold"><?= htmlspecialchars($d['restaurant']) ?></p>
                            <p class="text-gray-600"><?= htmlspecialchars($d['pickup']) ?></p>
                        </div>
                    </div>

                    <div class="flex gap-3 rounded-2xl bg-orange-50/70 p-4">
                        <i class="fa-solid fa-location-dot mt-1 w-4 text-[#ff4d0a]"></i>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Deliver to</p>
                            <p class="font-bold"><?= htmlspecialchars($d['customer']) ?></p>
                            <p class="text-gray-600"><?= htmlspecialchars($d['address']) ?></p>
                        </div>
                    </div>
                </div>

                <p class="mt-4 text-sm text-gray-600">
                    <i class="fa-solid fa-utensils mr-2 text-[#ff4d0a]"></i><?= htmlspecialchars($d['items']) ?>
                </p>

                <!-- Contact buttons -->
                <div class="mt-4 flex gap-3">
                    <a href="tel:<?= htmlspecialchars(preg_replace('/\D/', '', $d['phone']), ENT_QUOTES, 'UTF-8') ?>"
                       class="flex-1 rounded-xl bg-green-50 px-4 py-2 text-center text-sm font-bold text-green-700 hover:bg-green-100">
                        <i class="fa-solid fa-phone mr-2"></i>Call
                    </a>
                    <a href="<?= htmlspecialchars($maps, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"
                       class="flex-1 rounded-xl bg-blue-50 px-4 py-2 text-center text-sm font-bold text-blue-700 hover:bg-blue-100">
                        <i class="fa-solid fa-diamond-turn-right mr-2"></i>Navigate
                    </a>
                </div>

                <!-- Footer -->
                <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                    <div>
                        <?php if ($cod): ?>
                            <p class="text-xs font-bold uppercase tracking-wide text-red-500">Collect cash</p>
                        <?php else: ?>
                            <p class="text-xs font-bold uppercase tracking-wide text-green-600">Already paid</p>
                        <?php endif; ?>
                        <p class="text-2xl font-extrabold text-[#ff4d0a]">₹<?= number_format($d['total']) ?></p>
                    </div>
                    <div class="actions"></div>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <p id="emptyMsg" class="mt-10 hidden text-center text-lg font-semibold text-gray-500">
        No deliveries here yet.
    </p>
</main>

<!-- Toast -->
<div id="toast"
     class="pointer-events-none fixed bottom-6 right-6 z-50 translate-y-4 rounded-2xl bg-gray-900 px-5 py-3
            font-semibold text-white opacity-0 shadow-lg transition"></div>

<script>
    const BADGE = <?= json_encode($badge) ?>;
    const CSRF = <?= json_encode($_SESSION['csrf']) ?>;
    const FEE   = 40;   // sample earning per delivery - match the PHP value above
    const cards = Array.from(document.querySelectorAll('.delivery-card'));
    const emptyMsg = document.getElementById('emptyMsg');
    let activeTab = 'All';

    function renderAction(card) {
        const box = card.querySelector('.actions');
        const status = card.dataset.status;
        box.innerHTML = '';

        const make = (label, icon, cls, handler) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'rounded-xl px-5 py-2.5 text-sm font-bold ' + cls;
            b.innerHTML = '<i class="fa-solid ' + icon + ' mr-2"></i>' + label;
            b.addEventListener('click', handler);
            box.appendChild(b);
        };

        if (status === 'Assigned') {
            make('Picked Up', 'fa-bag-shopping', 'bg-[#ff4d0a] text-white hover:bg-orange-500',
                () => setStatus(card, 'Picked Up'));
        } else if (status === 'Picked Up') {
            make('Mark Delivered', 'fa-circle-check', 'bg-green-600 text-white hover:bg-green-500', () => {
                if (card.dataset.cod === '1') {
                    if (!confirm('Have you collected ₹' + card.dataset.total + ' in cash from the customer?')) return;
                }
                setStatus(card, 'Delivered');
            });
        } else {
            box.innerHTML = '<span class="text-sm font-semibold text-green-600"><i class="fa-solid fa-check mr-1"></i>Completed</span>';
        }
    }

    async function setStatus(card, status) {
    try {
        const res = await fetch('delivery_update.php', {
            method: 'POST',
            body: new URLSearchParams({ action: 'status', order_id: card.dataset.id, status: status, csrf: CSRF })
        });
        const out = await res.json();
        if (!out.ok) throw new Error(out.error || 'Update failed');
    } catch (err) {
        showToast(err.message);
        setTimeout(() => location.reload(), 1500);
        return;
    }
    card.dataset.status = status;
    const badge = card.querySelector('.status-badge');
    badge.textContent = status;
    badge.className = 'status-badge rounded-full px-4 py-1.5 text-sm font-bold ' + BADGE[status];
    renderAction(card);
    updateCounts();
    applyFilter();
    showToast('Order #' + card.dataset.id + ' marked ' + status);
}

    function updateCounts() {
        const counts = { All: cards.length, 'Assigned': 0, 'Picked Up': 0, 'Delivered': 0 };
        cards.forEach(c => counts[c.dataset.status]++);
        document.querySelectorAll('[data-count]').forEach(el => el.textContent = counts[el.dataset.count]);
        document.querySelectorAll('[data-stat]').forEach(el => el.textContent = counts[el.dataset.stat]);
        document.getElementById('earnings').textContent = counts['Delivered'] * FEE;
    }

    function applyFilter() {
        let shown = 0;
        cards.forEach(c => {
            const ok = activeTab === 'All' || c.dataset.status === activeTab;
            c.classList.toggle('hidden', !ok);
            if (ok) shown++;
        });
        emptyMsg.classList.toggle('hidden', shown !== 0);
    }

    function paintTabs() {
        document.querySelectorAll('.tab-btn').forEach(b => {
            const on = b.dataset.status === activeTab;
            b.classList.toggle('bg-[#ff4d0a]', on);
            b.classList.toggle('text-white', on);
            b.classList.toggle('bg-white', !on);
            b.classList.toggle('text-gray-700', !on);
            b.classList.toggle('shadow', !on);
        });
    }

    document.querySelectorAll('.tab-btn').forEach(b => {
        b.addEventListener('click', () => {
            activeTab = b.dataset.status;
            paintTabs();
            applyFilter();
        });
    });

    // Online / offline switch (UI only - save it to your database if you need to)
        const toggle = document.getElementById('onlineToggle');
    function paintOnline() {
        const text = document.getElementById('onlineText');
        text.textContent = toggle.checked ? 'Online' : 'Offline';
        text.classList.toggle('text-green-600', toggle.checked);
        text.classList.toggle('text-gray-500', !toggle.checked);
        document.getElementById('offlineNotice').classList.toggle('hidden', toggle.checked);
    }
    toggle.addEventListener('change', async () => {
        paintOnline();
        try {
            await fetch('delivery_update.php', {
                method: 'POST',
                body: new URLSearchParams({ action: 'online', online: toggle.checked ? 1 : 0, csrf: CSRF })
            });
            showToast(toggle.checked ? 'You are online' : 'You are offline');
            setTimeout(() => location.reload(), 800);   // refresh the list of available deliveries
        } catch (e) { showToast('Could not save'); }
    });
    paintOnline();

    let toastTimer;
    function showToast(msg) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.classList.remove('opacity-0', 'translate-y-4');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => t.classList.add('opacity-0', 'translate-y-4'), 2200);
    }

    cards.forEach(renderAction);
    paintTabs();
    applyFilter();
</script>
<script src="live_updates.js"></script>
</body>
</html>
