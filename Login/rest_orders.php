<?php
session_start();

require_once __DIR__ . '/../db.php';

// Only logged-in restaurant staff can see this page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'restaurant_staff') {
    header('Location: ../Login/restaurant.php');
    exit;
}

$staffName = $_SESSION['full_name'] ?? 'Staff';
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

// Restaurant details (same as dashboard)
$stmt = $pdo->prepare("SELECT * FROM restaurants WHERE is_active = 1 LIMIT 1");
$stmt->execute();
$restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
$restaurantName = $restaurant['restaurant_name'] ?? 'FoodFlow Kitchen';

$orders = $pdo->query(
    "SELECT order_id AS id, customer_name AS customer, COALESCE(customer_phone, '') AS phone,
            delivery_address AS address, payment_method AS payment, status,
            COALESCE(note, '') AS note,
            TIMESTAMPDIFF(MINUTE, created_at, NOW()) AS mins
     FROM orders ORDER BY order_id DESC LIMIT 100"
)->fetchAll(PDO::FETCH_ASSOC);

$itemsByOrder = [];
if ($orders) {
    $ids = implode(',', array_map('intval', array_column($orders, 'id')));
    foreach ($pdo->query("SELECT order_id, item_name, quantity, price FROM order_items WHERE order_id IN ($ids)") as $r) {
        $itemsByOrder[$r['order_id']][] = [$r['item_name'], (int)$r['quantity'], (float)$r['price']];
    }
}
foreach ($orders as &$o) {
    $o['time']  = ago_label((int)$o['mins']);
    $o['items'] = $itemsByOrder[$o['id']] ?? [];
}

unset($o);

$statuses = ['New', 'Preparing', 'Ready', 'Completed', 'Cancelled'];
$counts = array_fill_keys($statuses, 0);
foreach ($orders as $o) { $counts[$o['status']]++; }

$badge = [
    'New'       => 'bg-orange-100 text-[#ff4d0a]',
    'Preparing' => 'bg-yellow-100 text-yellow-700',
    'Ready'     => 'bg-green-100 text-green-700',
    'Completed' => 'bg-blue-100 text-blue-700',
    'Cancelled' => 'bg-red-100 text-red-700',
];

// Dashboard's "History" button opens ?filter=history
$startTab = (($_GET['filter'] ?? '') === 'history') ? 'Completed' : 'All';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - FoodFlow</title>
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
               class="flex items-center gap-3 rounded-xl px-4 py-3 text-gray-700 hover:bg-orange-50">
                <i class="fa-solid fa-table-columns w-5"></i> Dashboard
            </a>
            <a href="#"
               class="flex items-center gap-3 rounded-xl bg-[#ff4d0a] px-4 py-3 text-white">
                <i class="fa-solid fa-receipt w-5"></i> Orders
            </a>
            <a href="rest_menu.php"
               class="flex items-center gap-3 rounded-xl px-4 py-3 text-gray-700 hover:bg-orange-50">
                <i class="fa-solid fa-utensils w-5"></i> Menu
            </a>
            <a href="rest_reports.php"
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

        <!-- Header -->
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-3xl font-extrabold md:text-4xl">
                    Customer <span class="text-[#ff4d0a]">Orders</span> 🧾
                </h2>
                <p class="mt-1 font-medium text-gray-500">
                    Accept new orders, track the kitchen and hand over to riders.
                </p>
            </div>

            <div class="flex items-center gap-3 rounded-2xl bg-white px-5 py-3 shadow">
                <span class="h-3 w-3 animate-pulse rounded-full bg-green-500"></span>
                <span class="font-bold">Live</span>
            </div>
        </header>

        <!-- Search + status tabs -->
        <section class="mt-8 rounded-3xl bg-white p-5 shadow">
            <div class="flex flex-wrap items-center gap-4">

                <div class="flex min-w-[220px] flex-1 items-center gap-3 rounded-2xl
                            border border-gray-200 px-4 py-3 focus-within:border-orange-500">
                    <i class="fa-solid fa-magnifying-glass text-gray-500"></i>
                    <input id="searchInput" type="text"
                           placeholder="Search by order #, customer or dish..."
                           class="w-full outline-none">
                </div>

                <div id="tabs" class="flex flex-wrap gap-2">
                    <button type="button" data-status="All"
                            class="tab-btn rounded-full px-4 py-2 text-sm font-bold">
                        All (<span data-count="All"><?= count($orders) ?></span>)
                    </button>
                    <?php foreach ($statuses as $s): ?>
                        <button type="button" data-status="<?= $s ?>"
                                class="tab-btn rounded-full px-4 py-2 text-sm font-bold">
                            <?= $s ?> (<span data-count="<?= $s ?>"><?= $counts[$s] ?></span>)
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Orders -->
        <section id="orderGrid" class="mt-6 grid grid-cols-1 gap-5 xl:grid-cols-2">
            <?php foreach ($orders as $o): ?>
                <?php
                    $search = strtolower('#' . $o['id'] . ' ' . $o['customer'] . ' ' .
                        implode(' ', array_map(fn($i) => $i[0], $o['items'])));
                ?>
                <article class="order-card rounded-3xl bg-white p-6 shadow"
                         data-status="<?= htmlspecialchars($o['status'], ENT_QUOTES, 'UTF-8') ?>"
                         data-id="<?= (int)$o['id'] ?>"
                         data-search="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">

                    <!-- Top row -->
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-2xl font-extrabold">Order <span class="text-[#ff4d0a]">#<?= (int)$o['id'] ?></span></h3>
                            <p class="mt-1 text-sm font-semibold text-gray-400">
                                <i class="fa-regular fa-clock mr-1"></i><?= htmlspecialchars($o['time']) ?>
                            </p>
                        </div>
                        <span class="status-badge rounded-full px-4 py-1.5 text-sm font-bold <?= $badge[$o['status']] ?>">
                            <?= htmlspecialchars($o['status']) ?>
                        </span>
                    </div>

                    <!-- Customer -->
                    <div class="mt-4 space-y-1 rounded-2xl bg-orange-50/70 p-4 text-sm">
                        <p class="font-bold">
                            <i class="fa-solid fa-user mr-2 w-4 text-[#ff4d0a]"></i><?= htmlspecialchars($o['customer']) ?>
                        </p>
                        <p class="text-gray-600">
                            <i class="fa-solid fa-phone mr-2 w-4 text-[#ff4d0a]"></i><?= htmlspecialchars($o['phone']) ?>
                        </p>
                        <p class="text-gray-600">
                            <i class="fa-solid fa-location-dot mr-2 w-4 text-[#ff4d0a]"></i><?= htmlspecialchars($o['address']) ?>
                        </p>
                    </div>

                    <!-- Items -->
                    <ul class="mt-4 divide-y text-sm">
                        <?php foreach ($o['items'] as $it): ?>
                            <li class="flex items-center justify-between py-2">
                                <span class="font-semibold">
                                    <span class="mr-2 rounded-md bg-gray-100 px-2 py-0.5 text-xs font-bold"><?= (int)$it[1] ?>x</span>
                                    <?= htmlspecialchars($it[0]) ?>
                                </span>
                                <span class="font-bold">₹<?= number_format($it[1] * $it[2]) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if ($o['note'] !== ''): ?>
                        <p class="mt-3 rounded-xl bg-yellow-50 px-4 py-2 text-sm font-semibold text-yellow-800">
                            <i class="fa-regular fa-note-sticky mr-2"></i><?= htmlspecialchars($o['note']) ?>
                        </p>
                    <?php endif; ?>

                    <!-- Footer -->
                    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                        <div>
                            <p class="text-2xl font-extrabold text-[#ff4d0a]">₹<?= number_format($o['total']) ?></p>
                            <p class="text-xs font-semibold text-gray-500">
                                <i class="fa-solid fa-wallet mr-1"></i><?= htmlspecialchars($o['payment']) ?>
                            </p>
                        </div>
                        <div class="actions flex flex-wrap gap-2"></div>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <p id="emptyMsg" class="mt-10 hidden text-center text-lg font-semibold text-gray-500">
            No orders found.
        </p>
    </main>
</div>

<!-- Toast -->
<div id="toast"
     class="pointer-events-none fixed bottom-6 right-6 z-50 translate-y-4 rounded-2xl bg-gray-900 px-5 py-3
            font-semibold text-white opacity-0 shadow-lg transition"></div>

<script>
    const BADGE = <?= json_encode($badge) ?>;
    const CSRF = <?= json_encode($_SESSION['csrf']) ?>;
    const cards = Array.from(document.querySelectorAll('.order-card'));
    const searchEl = document.getElementById('searchInput');
    const emptyMsg = document.getElementById('emptyMsg');
    let activeTab = <?= json_encode($startTab) ?>;

    // What staff can do at each stage
    const ACTIONS = {
        'New': [
            { label: 'Accept', next: 'Preparing', cls: 'bg-[#ff4d0a] text-white hover:bg-orange-500', icon: 'fa-check' },
            { label: 'Reject', next: 'Cancelled', cls: 'bg-red-50 text-red-600 hover:bg-red-100', icon: 'fa-xmark', confirm: 'Reject this order?' }
        ],
        'Preparing': [
            { label: 'Mark Ready', next: 'Ready', cls: 'bg-yellow-500 text-white hover:bg-yellow-400', icon: 'fa-bell-concierge' }
        ],
        'Ready': [
            { label: 'Handed to Rider', next: 'Completed', cls: 'bg-green-600 text-white hover:bg-green-500', icon: 'fa-motorcycle' }
        ],
        'Completed': [],
        'Cancelled': []
    };

    function renderActions(card) {
        const box = card.querySelector('.actions');
        const status = card.dataset.status;
        box.innerHTML = '';
        const list = ACTIONS[status];
        if (!list.length) {
            box.innerHTML = '<span class="text-sm font-semibold text-gray-400">No further action</span>';
            return;
        }
        list.forEach(a => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'rounded-xl px-4 py-2 text-sm font-bold ' + a.cls;
            btn.innerHTML = '<i class="fa-solid ' + a.icon + ' mr-2"></i>' + a.label;
            btn.addEventListener('click', () => {
                if (a.confirm && !confirm(a.confirm)) return;
                setStatus(card, a.next);
            });
            box.appendChild(btn);
        });
    }

   async function setStatus(card, status) {
    try {
        const res = await fetch('rest_order_update.php', {
            method: 'POST',
            body: new URLSearchParams({ order_id: card.dataset.id, status: status, csrf: CSRF })
        });
        const out = await res.json();
        if (!out.ok) throw new Error(out.error || 'Update failed');
    } catch (err) {
        showToast(err.message);
        return;
    }
    card.dataset.status = status;
    const badge = card.querySelector('.status-badge');
    badge.textContent = status;
    badge.className = 'status-badge rounded-full px-4 py-1.5 text-sm font-bold ' + BADGE[status];
    renderActions(card);
    updateCounts();
    applyFilters();
    showToast('Order #' + card.dataset.id + ' marked ' + status);
}
    function updateCounts() {
        const counts = { All: cards.length };
        cards.forEach(c => counts[c.dataset.status] = (counts[c.dataset.status] || 0) + 1);
        document.querySelectorAll('[data-count]').forEach(el => {
            el.textContent = counts[el.dataset.count] || 0;
        });
    }

    function applyFilters() {
        const q = searchEl.value.trim().toLowerCase();
        let shown = 0;
        cards.forEach(card => {
            const okTab  = activeTab === 'All' || card.dataset.status === activeTab;
            const okText = card.dataset.search.includes(q);
            card.classList.toggle('hidden', !(okTab && okText));
            if (okTab && okText) shown++;
        });
        emptyMsg.classList.toggle('hidden', shown !== 0);
    }

    function paintTabs() {
        document.querySelectorAll('.tab-btn').forEach(b => {
            const on = b.dataset.status === activeTab;
            b.classList.toggle('bg-[#ff4d0a]', on);
            b.classList.toggle('text-white', on);
            b.classList.toggle('bg-orange-50', !on);
            b.classList.toggle('text-gray-700', !on);
        });
    }

    document.querySelectorAll('.tab-btn').forEach(b => {
        b.addEventListener('click', () => {
            activeTab = b.dataset.status;
            paintTabs();
            applyFilters();
        });
    });

    searchEl.addEventListener('input', applyFilters);

    let toastTimer;
    function showToast(msg) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.classList.remove('opacity-0', 'translate-y-4');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => t.classList.add('opacity-0', 'translate-y-4'), 2200);
    }

    cards.forEach(renderActions);
    paintTabs();
    applyFilters();
</script>
<script src="live_updates.js"></script>
</body>
</html>
