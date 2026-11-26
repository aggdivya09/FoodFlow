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

// Handle delivery status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = (int)($_POST['order_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    if ($orderId > 0 && $status !== '') {
        $delStatus = strtolower(str_replace(' ', '_', $status));
        try {
            $stmt = $pdo->prepare("UPDATE deliveries SET status = ?, delivered_at = CASE WHEN ? = 'delivered' THEN NOW() ELSE delivered_at END WHERE order_id = ?");
            $stmt->execute([$delStatus, $delStatus, $orderId]);

            $orderStatus = ($delStatus === 'delivered') ? 'delivered' : 'out_for_delivery';
            $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$orderStatus, $orderId]);

            if ($delStatus === 'delivered') {
                $pdo->prepare("UPDATE payments SET status = 'paid', paid_at = NOW() WHERE order_id = ?")->execute([$orderId]);
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        } catch (PDOException $e) {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}

$riderName = $_SESSION['full_name'] ?? 'Rider';

$deliveries = [];
try {
    $dbDel = $pdo->query("SELECT d.order_id AS id, o.customer_name, u.full_name, u.phone,
                                 o.delivery_address AS address, o.total, o.payment_method AS payment,
                                 d.status, d.assigned_at,
                                 (SELECT GROUP_CONCAT(CONCAT(oi.quantity, 'x ', f.name) SEPARATOR ', ')
                                  FROM order_items oi JOIN food f ON oi.food_id = f.id WHERE oi.order_id = o.id) AS items
                          FROM deliveries d
                          JOIN orders o ON d.order_id = o.id
                          LEFT JOIN users u ON o.customer_id = u.user_id
                          ORDER BY d.assigned_at DESC")->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($dbDel)) {
        foreach ($dbDel as $row) {
            $st = 'Assigned';
            $rawStatus = strtolower($row['status']);
            if ($rawStatus === 'picked_up') $st = 'Picked Up';
            if ($rawStatus === 'delivered') $st = 'Delivered';

            $deliveries[] = [
                'id'         => (int)$row['id'],
                'restaurant' => 'FoodFlow Kitchen',
                'pickup'     => '14, Rajpur Road, Dehradun',
                'customer'   => $row['customer_name'] ?: ($row['full_name'] ?: 'Customer #' . $row['id']),
                'phone'      => $row['phone'] ?: '9012345678',
                'address'    => $row['address'],
                'items'      => $row['items'] ?: 'Food Items',
                'total'      => (float)$row['total'],
                'payment'    => $row['payment'],
                'distance'   => '3.5 km',
                'time'       => date('h:i A', strtotime($row['assigned_at'])),
                'status'     => $st,
            ];
        }
    }
} catch (PDOException $e) {
    error_log("delivery_dashboard DB error: " . $e->getMessage());
}

if (empty($deliveries)) {
    $deliveries = [
        ['id' => 1044, 'restaurant' => 'FoodFlow Kitchen', 'pickup' => '14, Rajpur Road, Dehradun',
         'customer' => 'Priya Bisht',  'phone' => '9012345678', 'address' => '3, Clement Town, Dehradun',
         'items' => 'Margherita Pizza, 2x Thums Up', 'total' => 247, 'payment' => 'Cash on delivery',
         'distance' => '6.2 km', 'time' => '4 min ago', 'status' => 'Assigned'],
        ['id' => 1043, 'restaurant' => 'FoodFlow Kitchen', 'pickup' => '14, Rajpur Road, Dehradun',
         'customer' => 'Amit Joshi',   'phone' => '9835199001', 'address' => '21, Dalanwala, Dehradun',
         'items' => 'Family Pizza Feast', 'total' => 699, 'payment' => 'Paid online',
         'distance' => '2.8 km', 'time' => '9 min ago', 'status' => 'Assigned'],
        ['id' => 1041, 'restaurant' => 'FoodFlow Kitchen', 'pickup' => '14, Rajpur Road, Dehradun',
         'customer' => 'Vikas Rana',   'phone' => '9720055667', 'address' => '9, Mussoorie Road, Dehradun',
         'items' => 'Grand Feast Combo', 'total' => 1499, 'payment' => 'Cash on delivery',
         'distance' => '4.1 km', 'time' => '16 min ago', 'status' => 'Picked Up'],
        ['id' => 1038, 'restaurant' => 'FoodFlow Kitchen', 'pickup' => '14, Rajpur Road, Dehradun',
         'customer' => 'Sneha Thapa',  'phone' => '9876033445', 'address' => '56, Patel Nagar, Dehradun',
         'items' => 'Classic Alfredo Pasta, Choco Churros', 'total' => 418, 'payment' => 'Paid online',
         'distance' => '3.5 km', 'time' => '1 hr ago', 'status' => 'Delivered'],
        ['id' => 1036, 'restaurant' => 'FoodFlow Kitchen', 'pickup' => '14, Rajpur Road, Dehradun',
         'customer' => 'Rahul Bhatt',  'phone' => '9897011223', 'address' => '88, Sahastradhara Road, Dehradun',
         'items' => 'Double Cheese Pizza, Blue Lagoon', 'total' => 368, 'payment' => 'Cash on delivery',
         'distance' => '5.0 km', 'time' => '2 hr ago', 'status' => 'Delivered'],
    ];
}

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
                    <input id="onlineToggle" type="checkbox" checked class="peer sr-only">
                    <span class="h-6 w-11 rounded-full bg-gray-300 transition peer-checked:bg-green-500
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
                            <span class="mx-1">•</span>
                            <i class="fa-solid fa-route mr-1"></i><?= htmlspecialchars($d['distance']) ?>
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

    function setStatus(card, status) {
        card.dataset.status = status;
        const badge = card.querySelector('.status-badge');
        badge.textContent = status;
        badge.className = 'status-badge rounded-full px-4 py-1.5 text-sm font-bold ' + BADGE[status];
        renderAction(card);
        updateCounts();
        applyFilter();
        showToast('Order #' + card.dataset.id + ' marked ' + status);

        // Save status update to database
        fetch('delivery_dashboard.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ order_id: card.dataset.id, status: status })
        }).catch(err => console.error('Delivery status update failed:', err));
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
    toggle.addEventListener('change', () => {
        const text = document.getElementById('onlineText');
        text.textContent = toggle.checked ? 'Online' : 'Offline';
        text.classList.toggle('text-green-600', toggle.checked);
        text.classList.toggle('text-gray-500', !toggle.checked);
        document.getElementById('offlineNotice').classList.toggle('hidden', toggle.checked);
        showToast(toggle.checked ? 'You are online' : 'You are offline');
    });

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

</body>
</html>
