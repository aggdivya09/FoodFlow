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

$adminName = $_SESSION['full_name'] ?? 'Admin';
$self      = basename($_SERVER['PHP_SELF']);   // this page's own file name

// CSRF token for the add / edit / delete forms
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

/* ---------------------------------------------------------------
 * Look at the restaurants table so the page fits your columns.
 * Needs: restaurant_name and is_active (you already use both).
 * Optional columns are shown automatically if they exist.
 * --------------------------------------------------------------- */
$cols = [];
$pk   = null;

try {
    try {   // MySQL / MariaDB
        foreach ($pdo->query("SHOW COLUMNS FROM restaurants")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $cols[] = $r['Field'];
            if (($r['Key'] ?? '') === 'PRI') {
                $pk = $r['Field'];
            }
        }
    } catch (PDOException $e) {   // SQLite fallback
        foreach ($pdo->query("PRAGMA table_info(restaurants)")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $cols[] = $r['name'];
            if ((int)$r['pk'] === 1) {
                $pk = $r['name'];
            }
        }
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
}

if ($pk === null) {
    foreach (['restaurant_id', 'id'] as $guess) {
        if (in_array($guess, $cols, true)) {
            $pk = $guess;
            break;
        }
    }
}

$setupError = '';
if (!in_array('restaurant_name', $cols, true) || !in_array('is_active', $cols, true) || $pk === null) {
    $setupError = 'The restaurants table needs the columns restaurant_name and is_active, and a primary key.';
}

// Extra details we show / edit when the column exists
$optionalDefs = [
    'address' => ['Address', 'fa-location-dot'],
    'city'    => ['City',    'fa-city'],
    'phone'   => ['Phone',   'fa-phone'],
    'email'   => ['Email',   'fa-envelope'],
];
$optional = array_filter($optionalDefs, fn($k) => in_array($k, $cols, true), ARRAY_FILTER_USE_KEY);

/* ---------------------------------------------------------------
 * Handle add / edit / toggle / delete (POST)
 * --------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $setupError === '') {

    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        flash('error', 'Your session expired. Please try again.');
    }

    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['restaurant_id'] ?? 0);

    try {
        if ($action === 'toggle') {
            $stmt = $pdo->prepare(
                "UPDATE restaurants SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE `$pk` = ?"
            );
            $stmt->execute([$id]);
            flash('success', 'Restaurant status updated.');
        }

        if ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM restaurants WHERE `$pk` = ?");
            $stmt->execute([$id]);
            flash('success', 'Restaurant deleted.');
        }

        if ($action === 'add' || $action === 'edit') {
            $name = trim($_POST['restaurant_name'] ?? '');
            if ($name === '' || mb_strlen($name) > 100) {
                flash('error', 'Please enter a restaurant name (up to 100 characters).');
            }

            $values = [];
            foreach ($optional as $col => $def) {
                $v = trim($_POST[$col] ?? '');
                if (mb_strlen($v) > 255) {
                    flash('error', $def[0] . ' is too long.');
                }
                if ($col === 'email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                    flash('error', 'Please enter a valid email address.');
                }
                $values[$col] = $v;
            }

            if ($action === 'add') {
                $names  = ['restaurant_name', 'is_active'];
                $params = [$name, 1];
                foreach ($values as $col => $v) {
                    $names[]  = $col;
                    $params[] = $v;
                }
                $sql = 'INSERT INTO restaurants (`' . implode('`, `', $names) . '`) VALUES ('
                     . implode(', ', array_fill(0, count($names), '?')) . ')';
                $pdo->prepare($sql)->execute($params);
                flash('success', 'Restaurant added.');
            }

            // edit
            $sets   = ['restaurant_name = ?'];
            $params = [$name];
            foreach ($values as $col => $v) {
                $sets[]   = "`$col` = ?";
                $params[] = $v;
            }
            $params[] = $id;
            $pdo->prepare('UPDATE restaurants SET ' . implode(', ', $sets) . " WHERE `$pk` = ?")->execute($params);
            flash('success', 'Restaurant updated.');
        }

        flash('error', 'Unknown action.');

    } catch (PDOException $e) {
        error_log($e->getMessage());
        flash('error', 'Could not save the change. If you were deleting, this restaurant is probably linked to menu items or orders — deactivate it instead.');
    }
}

/* ---------------------------------------------------------------
 * List restaurants (GET): search + status filter + pagination
 * --------------------------------------------------------------- */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$q      = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'all';
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$where  = [];
$params = [];

if ($q !== '') {
    $likeCols = array_merge(['restaurant_name'], array_keys($optional));
    $parts = [];
    foreach ($likeCols as $c) {
        $parts[]  = "`$c` LIKE ?";
        $params[] = '%' . $q . '%';
    }
    $where[] = '(' . implode(' OR ', $parts) . ')';
}
if ($status === 'active') {
    $where[] = 'is_active = 1';
} elseif ($status === 'inactive') {
    $where[] = 'is_active <> 1';
} else {
    $status = 'all';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$restaurants = [];
$total  = 0;
$pages  = 1;
$counts = ['all' => 0, 'active' => 0, 'inactive' => 0];
$loadError = '';

if ($setupError === '') {
    try {
        foreach ($pdo->query("SELECT is_active, COUNT(*) AS c FROM restaurants GROUP BY is_active")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $counts['all'] += (int)$r['c'];
            $counts[(int)$r['is_active'] === 1 ? 'active' : 'inactive'] += (int)$r['c'];
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM restaurants $whereSql");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $pages  = max(1, (int)ceil($total / $perPage));
        $page   = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare("SELECT * FROM restaurants $whereSql ORDER BY `$pk` DESC LIMIT $perPage OFFSET $offset");
        $stmt->execute($params);
        $restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $loadError = 'Unable to load restaurants right now.';
    }
}

function pageUrl(int $p, string $q, string $status): string
{
    return $GLOBALS['self'] . '?' . http_build_query(['q' => $q, 'status' => $status, 'page' => $p]);
}

$tabs = ['all' => 'All', 'active' => 'Active', 'inactive' => 'Inactive'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurants - FoodFlow Admin</title>
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
            <a href="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>"
               class="flex items-center gap-3 rounded-xl bg-[#ff4d0a] px-4 py-3 text-white">
                <i class="fa-solid fa-store w-5"></i> Restaurants
            </a>
            <a href="admin_orders.php"
               class="flex items-center gap-3 rounded-xl px-4 py-3 text-gray-700 hover:bg-orange-50">
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
                    Manage <span class="text-[#ff4d0a]">Restaurants</span> 🏪
                </h2>
                <p class="mt-1 font-medium text-gray-500">
                    Add restaurants, update their details and switch them on or off.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <a href="../logout.php"
                   class="flex items-center gap-2 rounded-2xl bg-white px-4 py-3 font-bold text-red-600 shadow hover:bg-red-50 lg:hidden">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
                <?php if ($setupError === ''): ?>
                    <button type="button" onclick="openModal()"
                            class="rounded-2xl bg-[#ff4d0a] px-6 py-3 text-lg font-bold text-white shadow hover:bg-orange-500">
                        <i class="fa-solid fa-plus mr-2"></i>Add Restaurant
                    </button>
                <?php endif; ?>
            </div>
        </header>

        <!-- Messages -->
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
        <section class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div class="flex items-center gap-4 rounded-3xl bg-white p-6 shadow">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-orange-100 text-2xl text-[#ff4d0a]">
                    <i class="fa-solid fa-store"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-500">Total</p>
                    <p class="text-3xl font-extrabold"><?= $counts['all'] ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4 rounded-3xl bg-white p-6 shadow">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-green-100 text-2xl text-green-600">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-500">Active</p>
                    <p class="text-3xl font-extrabold"><?= $counts['active'] ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4 rounded-3xl bg-white p-6 shadow">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-red-100 text-2xl text-red-600">
                    <i class="fa-solid fa-circle-pause"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-500">Inactive</p>
                    <p class="text-3xl font-extrabold"><?= $counts['inactive'] ?></p>
                </div>
            </div>
        </section>

        <!-- Search + status tabs -->
        <section class="mt-6 rounded-3xl bg-white p-5 shadow">
            <form method="GET" action="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>"
                  class="flex flex-wrap items-center gap-4">
                <input type="hidden" name="status" value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">

                <div class="flex min-w-[220px] flex-1 items-center gap-3 rounded-2xl
                            border border-gray-200 px-4 py-3 focus-within:border-orange-500">
                    <i class="fa-solid fa-magnifying-glass text-gray-500"></i>
                    <input type="search" name="q" id="searchInput" autocomplete="off"
                           value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Search restaurants..." class="w-full outline-none">
                </div>

                <button type="submit"
                        class="rounded-2xl bg-[#ff4d0a] px-6 py-3 font-bold text-white hover:bg-orange-500">
                    Search
                </button>

                <?php if ($q !== ''): ?>
                    <a href="<?= htmlspecialchars(pageUrl(1, '', $status), ENT_QUOTES, 'UTF-8') ?>"
                       class="rounded-2xl bg-gray-100 px-5 py-3 font-bold text-gray-700 hover:bg-gray-200">
                        Clear
                    </a>
                <?php endif; ?>
            </form>

            <div class="mt-4 flex flex-wrap gap-2">
                <?php foreach ($tabs as $key => $label): ?>
                    <a href="<?= htmlspecialchars(pageUrl(1, $q, $key), ENT_QUOTES, 'UTF-8') ?>"
                       class="rounded-full px-4 py-2 text-sm font-bold
                              <?= $status === $key ? 'bg-[#ff4d0a] text-white' : 'bg-orange-50 text-gray-700 hover:bg-orange-100' ?>">
                        <?= htmlspecialchars($label) ?> (<?= (int)$counts[$key] ?>)
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Restaurants table -->
        <section class="mt-6 rounded-3xl bg-white p-6 shadow">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left">
                    <thead>
                        <tr class="border-b text-sm text-gray-500">
                            <th class="pb-3">Restaurant</th>
                            <?php if ($optional): ?><th class="pb-3">Details</th><?php endif; ?>
                            <th class="pb-3">Status</th>
                            <th class="pb-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$restaurants): ?>
                        <tr>
                            <td colspan="4" class="py-10 text-center text-lg font-semibold text-gray-400">
                                No restaurants found.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($restaurants as $r): ?>
                        <?php
                            $rid     = (int)$r[$pk];
                            $active  = ((int)$r['is_active'] === 1);
                            $initial = strtoupper(mb_substr(trim($r['restaurant_name']) ?: '?', 0, 1));
                            $payload = ['id' => $rid, 'restaurant_name' => $r['restaurant_name']];
                            foreach ($optional as $col => $def) {
                                $payload[$col] = (string)($r[$col] ?? '');
                            }
                        ?>
                        <tr class="border-b last:border-0">
                            <td class="py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-orange-100 font-extrabold text-[#ff4d0a]">
                                        <?= htmlspecialchars($initial) ?>
                                    </div>
                                    <div>
                                        <p class="font-bold"><?= htmlspecialchars($r['restaurant_name']) ?></p>
                                        <p class="text-xs text-gray-400">ID #<?= $rid ?></p>
                                    </div>
                                </div>
                            </td>

                            <?php if ($optional): ?>
                                <td class="py-4 text-sm text-gray-600">
                                    <?php $any = false; foreach ($optional as $col => $def): ?>
                                        <?php if (trim((string)($r[$col] ?? '')) !== ''): $any = true; ?>
                                            <p><i class="fa-solid <?= $def[1] ?> mr-2 w-4 text-[#ff4d0a]"></i><?= htmlspecialchars($r[$col]) ?></p>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <?php if (!$any): ?><span class="text-gray-300">—</span><?php endif; ?>
                                </td>
                            <?php endif; ?>

                            <td class="py-4">
                                <span class="rounded-full px-3 py-1 text-sm font-bold
                                             <?= $active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                    <?= $active ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>

                            <td class="py-4">
                                <div class="flex justify-end gap-2">
                                    <!-- Activate / deactivate -->
                                    <form method="POST" action="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="restaurant_id" value="<?= $rid ?>">
                                        <button type="submit"
                                                class="rounded-xl px-4 py-2 text-sm font-bold
                                                       <?= $active ? 'bg-yellow-50 text-yellow-700 hover:bg-yellow-100' : 'bg-green-50 text-green-700 hover:bg-green-100' ?>">
                                            <i class="fa-solid <?= $active ? 'fa-pause' : 'fa-play' ?> mr-1"></i>
                                            <?= $active ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>

                                    <button type="button"
                                            onclick='openModal(<?= json_encode($payload, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'
                                            class="rounded-xl bg-orange-50 px-4 py-2 text-sm font-bold text-gray-800 hover:bg-orange-100">
                                        <i class="fa-solid fa-pen mr-1"></i>Edit
                                    </button>

                                    <form method="POST" action="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>"
                                          onsubmit="return confirm('Delete this restaurant? This cannot be undone.');">
                                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="restaurant_id" value="<?= $rid ?>">
                                        <button type="submit"
                                                class="rounded-xl bg-red-50 px-4 py-2 text-sm font-bold text-red-600 hover:bg-red-100"
                                                aria-label="Delete restaurant">
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
                        Showing page <?= $page ?> of <?= $pages ?> (<?= $total ?> restaurants)
                    </p>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="<?= htmlspecialchars(pageUrl($page - 1, $q, $status), ENT_QUOTES, 'UTF-8') ?>"
                               class="rounded-xl bg-orange-50 px-4 py-2 font-bold hover:bg-orange-100">← Prev</a>
                        <?php endif; ?>
                        <?php if ($page < $pages): ?>
                            <a href="<?= htmlspecialchars(pageUrl($page + 1, $q, $status), ENT_QUOTES, 'UTF-8') ?>"
                               class="rounded-xl bg-[#ff4d0a] px-4 py-2 font-bold text-white hover:bg-orange-500">Next →</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<!-- Add / Edit modal -->
<div id="modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
    <div class="w-full max-w-lg rounded-3xl bg-white p-8 shadow-xl">

        <div class="flex items-center justify-between">
            <h3 id="modalTitle" class="text-2xl font-extrabold">
                Add <span class="text-[#ff4d0a]">Restaurant</span>
            </h3>
            <button type="button" onclick="closeModal()" class="text-2xl text-gray-500 hover:text-gray-800"
                    aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>" class="mt-6 space-y-4">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" id="f_action" value="add">
            <input type="hidden" name="restaurant_id" id="f_id" value="">

            <input type="text" name="restaurant_name" id="f_restaurant_name" placeholder="Restaurant name"
                   required maxlength="100"
                   class="w-full rounded-2xl border border-gray-200 p-4 outline-none focus:border-orange-500">

            <?php foreach ($optional as $col => $def): ?>
                <input type="<?= $col === 'email' ? 'email' : 'text' ?>" name="<?= $col ?>" id="f_<?= $col ?>"
                       placeholder="<?= htmlspecialchars($def[0]) ?> (optional)" maxlength="255"
                       class="w-full rounded-2xl border border-gray-200 p-4 outline-none focus:border-orange-500">
            <?php endforeach; ?>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeModal()"
                        class="w-1/3 rounded-2xl bg-gray-100 p-3 font-bold text-gray-700 hover:bg-gray-200">
                    Cancel
                </button>
                <button type="submit"
                        class="w-2/3 rounded-2xl bg-[#ff4d0a] p-3 text-lg font-bold text-white hover:bg-orange-500">
                    Save Restaurant
                </button>
            </div>
        </form>
    </div>
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

    const modal = document.getElementById('modal');
    const OPTIONAL = <?= json_encode(array_keys($optional)) ?>;

    function openModal(r) {
        const editing = !!r;
        document.getElementById('modalTitle').innerHTML =
            (editing ? 'Edit' : 'Add') + ' <span class="text-[#ff4d0a]">Restaurant</span>';
        document.getElementById('f_action').value = editing ? 'edit' : 'add';
        document.getElementById('f_id').value = editing ? r.id : '';
        document.getElementById('f_restaurant_name').value = editing ? r.restaurant_name : '';
        OPTIONAL.forEach(c => { document.getElementById('f_' + c).value = editing ? (r[c] || '') : ''; });

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.getElementById('f_restaurant_name').focus();
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>

</body>
</html>
