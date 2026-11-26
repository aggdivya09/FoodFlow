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

$self = basename($_SERVER['PHP_SELF']);   // this page's own file name

/* ===============================================================
 * SETTINGS - match these to YOUR orders table
 * ---------------------------------------------------------------
 * $ordersTable : the table that holds your orders
 * $map         : 'what this page calls it' => 'your real column name'
 *                (set a column to null if you don't have it)
 * =============================================================== */
$ordersTable = 'orders';
$map = [
    'id'       => 'id',
    'customer' => 'customer_name',
    'address'  => 'delivery_address',
    'total'    => 'total',
    'payment'  => 'payment_method',
    'status'   => 'status',
    'created'  => 'created_at',
];
$defaultStatuses = ['New', 'Preparing', 'Ready', 'Completed', 'Cancelled'];

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

function flash(string $type, string $msg): void
{
    global $self;
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
    header('Location: ' . $self);
    exit;
}

function tableColumns(PDO $db, string $table): array
{
    $cols = [];
    try {   // MySQL / MariaDB
        foreach ($db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $cols[] = $r['Field'];
        }
        return $cols;
    } catch (PDOException $e) {
    }
    try {   // SQLite
        foreach ($db->query("PRAGMA table_info(`$table`)")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $cols[] = $r['name'];
        }
    } catch (PDOException $e) {
    }
    return $cols;
}

/* ---------------------------------------------------------------
 * Find the orders table. If it doesn't exist yet, show a read-only
 * demo list so you can see the page.
 * --------------------------------------------------------------- */
$demo = false;
$db   = $pdo;
$cols = tableColumns($pdo, $ordersTable);

if (!$cols) {
    $demo = true;
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec(
        'CREATE TABLE `' . $ordersTable . '` (`' . $map['id'] . '` INTEGER PRIMARY KEY, `' . $map['customer'] . '` TEXT, `'
        . $map['address'] . '` TEXT, `' . $map['total'] . '` REAL, `' . $map['payment'] . '` TEXT, `'
        . $map['status'] . '` TEXT, `' . $map['created'] . '` TEXT)'
    );
    $names = ['Rohit Sharma', 'Neha Panwar', 'Anita Rawat', 'Karan Negi', 'Priya Bisht', 'Amit Joshi', 'Sneha Thapa', 'Vikas Rana'];
    $areas = ['Rajpur Road', 'Ballupur Chowk', 'Race Course', 'Clement Town', 'Dalanwala', 'Patel Nagar'];
    $stat  = ['New', 'Preparing', 'Ready', 'Completed', 'Completed', 'Completed', 'Cancelled'];
    $ins = $db->prepare('INSERT INTO `' . $ordersTable . '` VALUES (?, ?, ?, ?, ?, ?, ?)');
    for ($i = 1; $i <= 30; $i++) {
        $ins->execute([
            1000 + $i, $names[$i % 8], rand(3, 90) . ', ' . $areas[$i % 6] . ', Dehradun',
            [249, 418, 655, 299, 1499, 477, 368][$i % 7], $i % 3 ? 'Paid online' : 'Cash on delivery',
            $stat[$i % 7], date('Y-m-d H:i:s', time() - $i * 1500),
        ]);
    }
    $cols = array_values($map);
}

// Keep only the mapped columns that really exist
$c = [];
foreach ($map as $key => $realName) {
    if ($realName && in_array($realName, $cols, true)) {
        $c[$key] = $realName;
    }
}

$setupError = '';
foreach (['id', 'total', 'status'] as $must) {
    if (!isset($c[$must])) {
        $setupError = 'Your orders table needs at least an id, a total and a status column. Check the SETTINGS at the top of this file.';
    }
}

function col(string $key): string
{
    global $c;
    return '`' . $c[$key] . '`';
}

// Statuses an order can be moved to: the defaults plus any already in your table
$statusOptions = $defaultStatuses;
if ($setupError === '') {
    try {
        foreach ($db->query('SELECT DISTINCT ' . col('status') . " FROM `$ordersTable`")->fetchAll(PDO::FETCH_COLUMN) as $s) {
            if ($s !== null && $s !== '' && !in_array($s, $statusOptions, true)) {
                $statusOptions[] = $s;
            }
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}

/* ---------------------------------------------------------------
 * Handle status change / delete (POST)
 * --------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $setupError === '') {

    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        flash('error', 'Your session expired. Please try again.');
    }
    if ($demo) {
        flash('error', "You're looking at demo orders. Create the '$ordersTable' table (or fix SETTINGS at the top of this file) to save changes.");
    }

    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['order_id'] ?? 0);

    try {
        if ($action === 'status') {
            $new = $_POST['status'] ?? '';
            if (!in_array($new, $statusOptions, true)) {
                flash('error', 'Please choose a valid status.');
            }
            $db->prepare("UPDATE `$ordersTable` SET " . col('status') . ' = ? WHERE ' . col('id') . ' = ?')
               ->execute([$new, $id]);
            flash('success', "Order #$id marked as $new.");
        }

        if ($action === 'delete') {
            $db->prepare("DELETE FROM `$ordersTable` WHERE " . col('id') . ' = ?')->execute([$id]);
            flash('success', "Order #$id deleted.");
        }

        flash('error', 'Unknown action.');

    } catch (PDOException $e) {
        error_log($e->getMessage());
        flash('error', 'Could not save the change. If you were deleting, this order is probably linked to other records — mark it Cancelled instead.');
    }
}

/* ---------------------------------------------------------------
 * List orders (GET): search + status filter + pagination
 * --------------------------------------------------------------- */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$q       = trim($_GET['q'] ?? '');
$statSel = $_GET['status'] ?? 'all';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;

$where  = [];
$params = [];

if ($q !== '' && $setupError === '') {
    $parts = [col('id') . ' LIKE ?'];
    $params[] = '%' . ltrim($q, '#') . '%';
    if (isset($c['customer'])) {
        $parts[]  = col('customer') . ' LIKE ?';
        $params[] = '%' . $q . '%';
    }
    $where[] = '(' . implode(' OR ', $parts) . ')';
}
if ($statSel !== 'all' && $setupError === '') {
    $where[]  = col('status') . ' = ?';
    $params[] = $statSel;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$orders = [];
$statusCounts = [];
$allCount = 0;
$revenue  = 0;
$pages    = 1;
$total    = 0;
$loadError = '';

if ($setupError === '') {
    try {
        foreach ($db->query('SELECT ' . col('status') . ' AS s, COUNT(*) AS n FROM `' . $ordersTable . '` GROUP BY ' . col('status'))
                    ->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $statusCounts[(string)$r['s']] = (int)$r['n'];
            $allCount += (int)$r['n'];
        }
        // Revenue = everything that isn't cancelled
        $revenue = (float)$db->query(
            'SELECT COALESCE(SUM(' . col('total') . "), 0) FROM `$ordersTable` WHERE LOWER(" . col('status') . ") <> 'cancelled'"
        )->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM `$ordersTable` $whereSql");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $pages  = max(1, (int)ceil($total / $perPage));
        $page   = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $select = [];
        foreach ($c as $key => $realName) {
            $select[] = "`$realName` AS `f_$key`";
        }
        $stmt = $db->prepare(
            'SELECT ' . implode(', ', $select) . " FROM `$ordersTable` $whereSql ORDER BY " . col('id')
            . " DESC LIMIT $perPage OFFSET $offset"
        );
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $loadError = 'Unable to load orders right now.';
    }
}

function pageUrl(int $p, string $q, string $status): string
{
    return $GLOBALS['self'] . '?' . http_build_query(['q' => $q, 'status' => $status, 'page' => $p]);
}

$badge = [
    'new'       => 'bg-orange-100 text-[#ff4d0a]',
    'preparing' => 'bg-yellow-100 text-yellow-700',
    'ready'     => 'bg-green-100 text-green-700',
    'completed' => 'bg-blue-100 text-blue-700',
    'cancelled' => 'bg-red-100 text-red-700',
];
$cancelled = $statusCounts['Cancelled'] ?? 0;
$active    = ($statusCounts['New'] ?? 0) + ($statusCounts['Preparing'] ?? 0) + ($statusCounts['Ready'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - FoodFlow Admin</title>
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
            <a href="admin_dashboard.php"
               class="flex items-center gap-3 rounded-xl px-4 py-3 text-gray-700 hover:bg-orange-50">
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
            <a href="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>"
               class="flex items-center gap-3 rounded-xl bg-[#ff4d0a] px-4 py-3 text-white">
                <i class="fa-solid fa-receipt w-5"></i> Orders
            </a>
            <a href="admin_menu.php"
               class="flex items-center gap-3 rounded-xl px-4 py-3 text-gray-700 hover:bg-orange-50">
                <i class="fa-solid fa-utensils w-5"></i> Menu
            </a>
            <a href="admin_reports.php"
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

        <!-- Header -->
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-3xl font-extrabold md:text-4xl">
                    All <span class="text-[#ff4d0a]">Orders</span> 🧾
                </h2>
                <p class="mt-1 font-medium text-gray-500">
                    Track every order, update its status and keep things moving.
                </p>
            </div>

            <a href="../logout.php"
               class="flex items-center gap-2 rounded-2xl bg-white px-4 py-3 font-bold text-red-600 shadow hover:bg-red-50 lg:hidden">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        </header>

        <!-- Messages -->
        <?php if ($demo): ?>
            <div class="mt-6 rounded-xl border border-yellow-300 bg-yellow-50 p-4 font-semibold text-yellow-800" role="alert">
                <i class="fa-solid fa-flask mr-2"></i>
                Demo orders — your database has no <code class="rounded bg-yellow-100 px-1"><?= htmlspecialchars($ordersTable) ?></code>
                table yet, so you're seeing sample orders and changes can't be saved.
                Fix the SETTINGS at the top of this file to connect your real orders.
            </div>
        <?php endif; ?>
        <?php if ($flash): ?>
            <div class="mt-6 rounded-xl border p-4 font-semibold
                        <?= $flash['type'] === 'success'
                            ? 'border-green-300 bg-green-50 text-green-700'
                            : 'border-red-300 bg-red-50 text-red-700' ?>" role="alert">
                <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?> mr-2"></i>
                <?= htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <?php foreach ([$setupError, $loadError] as $err): ?>
            <?php if ($err !== ''): ?>
                <div class="mt-6 rounded-xl border border-red-300 bg-red-50 p-4 font-semibold text-red-700" role="alert">
                    <i class="fa-solid fa-circle-exclamation mr-2"></i><?= htmlspecialchars($err) ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Summary -->
        <section class="mt-6 grid grid-cols-2 gap-5 xl:grid-cols-4">
            <?php foreach ([
                ['Total Orders', $allCount,                      'fa-receipt',           'bg-orange-100 text-[#ff4d0a]'],
                ['In Progress',  $active,                        'fa-fire-burner',       'bg-yellow-100 text-yellow-600'],
                ['Revenue',      '₹' . number_format($revenue),  'fa-indian-rupee-sign', 'bg-green-100 text-green-600'],
                ['Cancelled',    $cancelled,                     'fa-ban',               'bg-red-100 text-red-600'],
            ] as $card): ?>
                <div class="flex items-center gap-4 rounded-3xl bg-white p-5 shadow">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl text-2xl <?= $card[3] ?>">
                        <i class="fa-solid <?= $card[2] ?>"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-500"><?= $card[0] ?></p>
                        <p class="text-3xl font-extrabold"><?= htmlspecialchars((string)$card[1]) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <!-- Search + status tabs -->
        <section class="mt-6 rounded-3xl bg-white p-5 shadow">
            <form method="GET" action="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>"
                  class="flex flex-wrap items-center gap-4">
                <input type="hidden" name="status" value="<?= htmlspecialchars($statSel, ENT_QUOTES, 'UTF-8') ?>">

                <div class="flex min-w-[220px] flex-1 items-center gap-3 rounded-2xl
                            border border-gray-200 px-4 py-3 focus-within:border-orange-500">
                    <i class="fa-solid fa-magnifying-glass text-gray-500"></i>
                    <input type="search" name="q" id="searchInput" autocomplete="off"
                           value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Search by order # or customer..." class="w-full outline-none">
                </div>

                <button type="submit"
                        class="rounded-2xl bg-[#ff4d0a] px-6 py-3 font-bold text-white hover:bg-orange-500">
                    Search
                </button>

                <?php if ($q !== ''): ?>
                    <a href="<?= htmlspecialchars(pageUrl(1, '', $statSel), ENT_QUOTES, 'UTF-8') ?>"
                       class="rounded-2xl bg-gray-100 px-5 py-3 font-bold text-gray-700 hover:bg-gray-200">
                        Clear
                    </a>
                <?php endif; ?>
            </form>

            <div class="mt-4 flex flex-wrap gap-2">
                <a href="<?= htmlspecialchars(pageUrl(1, $q, 'all'), ENT_QUOTES, 'UTF-8') ?>"
                   class="rounded-full px-4 py-2 text-sm font-bold
                          <?= $statSel === 'all' ? 'bg-[#ff4d0a] text-white' : 'bg-orange-50 text-gray-700 hover:bg-orange-100' ?>">
                    All (<?= $allCount ?>)
                </a>
                <?php foreach ($statusOptions as $s): ?>
                    <a href="<?= htmlspecialchars(pageUrl(1, $q, $s), ENT_QUOTES, 'UTF-8') ?>"
                       class="rounded-full px-4 py-2 text-sm font-bold
                              <?= $statSel === $s ? 'bg-[#ff4d0a] text-white' : 'bg-orange-50 text-gray-700 hover:bg-orange-100' ?>">
                        <?= htmlspecialchars($s) ?> (<?= $statusCounts[$s] ?? 0 ?>)
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Orders table -->
        <section class="mt-6 rounded-3xl bg-white p-6 shadow">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left">
                    <thead>
                        <tr class="border-b text-sm text-gray-500">
                            <th class="pb-3">Order</th>
                            <?php if (isset($c['customer'])): ?><th class="pb-3">Customer</th><?php endif; ?>
                            <th class="pb-3">Total</th>
                            <?php if (isset($c['payment'])): ?><th class="pb-3">Payment</th><?php endif; ?>
                            <th class="pb-3">Status</th>
                            <th class="pb-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$orders): ?>
                        <tr>
                            <td colspan="6" class="py-10 text-center text-lg font-semibold text-gray-400">
                                No orders found.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($orders as $o): ?>
                        <?php
                            $oid    = (int)$o['f_id'];
                            $status = (string)$o['f_status'];
                            $when   = isset($c['created']) && $o['f_created'] !== null
                                ? (strtotime((string)$o['f_created']) ? date('d M, h:i A', strtotime((string)$o['f_created'])) : (string)$o['f_created'])
                                : '';
                        ?>
                        <tr class="border-b last:border-0">
                            <td class="py-4">
                                <p class="font-bold">#<?= $oid ?></p>
                                <?php if ($when !== ''): ?>
                                    <p class="text-xs text-gray-400"><?= htmlspecialchars($when) ?></p>
                                <?php endif; ?>
                            </td>

                            <?php if (isset($c['customer'])): ?>
                                <td class="py-4">
                                    <p class="font-semibold"><?= htmlspecialchars((string)$o['f_customer']) ?></p>
                                    <?php if (isset($c['address'])): ?>
                                        <p class="max-w-[220px] truncate text-xs text-gray-500"><?= htmlspecialchars((string)$o['f_address']) ?></p>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>

                            <td class="py-4 font-extrabold text-[#ff4d0a]">₹<?= number_format((float)$o['f_total']) ?></td>

                            <?php if (isset($c['payment'])): ?>
                                <td class="py-4 text-sm text-gray-600"><?= htmlspecialchars((string)$o['f_payment']) ?></td>
                            <?php endif; ?>

                            <td class="py-4">
                                <span class="rounded-full px-3 py-1 text-sm font-bold
                                             <?= $badge[strtolower($status)] ?? 'bg-gray-100 text-gray-700' ?>">
                                    <?= htmlspecialchars($status) ?>
                                </span>
                            </td>

                            <td class="py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <!-- Change status -->
                                    <form method="POST" action="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="action" value="status">
                                        <input type="hidden" name="order_id" value="<?= $oid ?>">
                                        <select name="status" onchange="this.form.submit()" aria-label="Change status"
                                                class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold outline-none focus:border-orange-500">
                                            <?php foreach ($statusOptions as $s): ?>
                                                <option value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>"
                                                        <?= $s === $status ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>

                                    <form method="POST" action="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>"
                                          onsubmit="return confirm('Delete order #<?= $oid ?>? This cannot be undone.');">
                                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="order_id" value="<?= $oid ?>">
                                        <button type="submit"
                                                class="rounded-xl bg-red-50 px-4 py-2 text-sm font-bold text-red-600 hover:bg-red-100"
                                                aria-label="Delete order">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($pages > 1): ?>
                <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                    <p class="text-sm font-semibold text-gray-500">
                        Showing page <?= $page ?> of <?= $pages ?> (<?= $total ?> orders)
                    </p>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="<?= htmlspecialchars(pageUrl($page - 1, $q, $statSel), ENT_QUOTES, 'UTF-8') ?>"
                               class="rounded-xl bg-orange-50 px-4 py-2 font-bold hover:bg-orange-100">← Prev</a>
                        <?php endif; ?>
                        <?php if ($page < $pages): ?>
                            <a href="<?= htmlspecialchars(pageUrl($page + 1, $q, $statSel), ENT_QUOTES, 'UTF-8') ?>"
                               class="rounded-xl bg-[#ff4d0a] px-4 py-2 font-bold text-white hover:bg-orange-500">Next →</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<script>
    // Live search: results update shortly after you stop typing
    const searchInput = document.getElementById('searchInput');
    let searchTimer;
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => searchInput.form.submit(), 450);
    });
    if (searchInput.value) {   // keep typing where you left off after the page reloads
        searchInput.focus();
        searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
    }
</script>

</body>
</html>
