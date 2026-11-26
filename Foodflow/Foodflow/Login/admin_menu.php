
<?php
session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../Login/admin.php');
    exit;
}

$adminName = $_SESSION['full_name'] ?? 'Admin';
$self = basename($_SERVER['PHP_SELF']);

$menuTable = 'food';
$demo = false;

// These are virtual fields used by the existing HTML design.
// Category comes from the categories table through a JOIN.
$c = [
    'id'        => 'id',
    'name'      => 'name',
    'category'  => 'category_name',
    'price'     => 'price',
    'desc'      => 'description',
    'available' => 'available'
];

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

// Check that the required database tables exist.
$setupError = '';

try {
    $pdo->query("SELECT f.id, f.name, f.price, f.description,
                        f.available, f.category_id, c.name AS category_name
                 FROM food f
                 LEFT JOIN categories c ON f.category_id = c.id
                 LIMIT 1");
} catch (PDOException $e) {
    $setupError = 'Could not find the food or categories table. Please check your database.';
}

// Handle Add, Edit, Toggle and Delete.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $setupError === '') {

    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        flash('error', 'Your session expired. Please try again.');
    }

    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['item_id'] ?? 0);

    try {

        // Change availability.
        if ($action === 'toggle') {
            $stmt = $pdo->prepare(
                "UPDATE food
                 SET available = CASE WHEN available = 1 THEN 0 ELSE 1 END
                 WHERE id = ?"
            );
            $stmt->execute([$id]);

            flash('success', 'Availability updated.');
        }

        // Delete a dish.
        if ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM food WHERE id = ?");
            $stmt->execute([$id]);

            flash('success', 'Dish deleted.');
        }

        // Add or edit a dish.
        if ($action === 'add' || $action === 'edit') {

            $name = trim($_POST['name'] ?? '');
            $price = $_POST['price'] ?? '';
            $category = trim($_POST['category'] ?? '');
            $description = trim($_POST['desc'] ?? '');
            $available = isset($_POST['available']) ? 1 : 0;

            if ($name === '' || mb_strlen($name) > 100) {
                flash('error', 'Enter a valid dish name.');
            }

            if (!is_numeric($price) || $price < 0 || $price > 100000) {
                flash('error', 'Enter a valid price.');
            }

            if ($category === '') {
                flash('error', 'Please select a category.');
            }

            if (mb_strlen($description) > 255) {
                flash('error', 'Description must be within 255 characters.');
            }

            // Find the category ID using its name.
            $stmt = $pdo->prepare(
                "SELECT id FROM categories WHERE name = ? LIMIT 1"
            );
            $stmt->execute([$category]);
            $categoryId = $stmt->fetchColumn();

            if (!$categoryId) {
                flash('error', 'Please select an existing category from the list.');
            }

            if ($action === 'add') {

                $stmt = $pdo->prepare(
                    "INSERT INTO food
                    (category_id, name, description, price, available)
                    VALUES (?, ?, ?, ?, ?)"
                );

                $stmt->execute([
                    $categoryId,
                    $name,
                    $description,
                    $price,
                    $available
                ]);

                flash('success', 'Dish added successfully.');
            }

            if ($action === 'edit') {

                $stmt = $pdo->prepare(
                    "UPDATE food
                     SET category_id = ?,
                         name = ?,
                         description = ?,
                         price = ?,
                         available = ?
                     WHERE id = ?"
                );

                $stmt->execute([
                    $categoryId,
                    $name,
                    $description,
                    $price,
                    $available,
                    $id
                ]);

                flash('success', 'Dish updated successfully.');
            }
        }

        flash('error', 'Unknown action.');

    } catch (PDOException $e) {
        error_log($e->getMessage());
        flash('error', 'Database error. Could not complete the action.');
    }
}

// Read flash message.
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Search, category filter and pagination.
$q = trim($_GET['q'] ?? '');
$catSel = $_GET['cat'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;

$items = [];
$categories = [];
$total = 0;
$pages = 1;
$allCount = 0;
$availCount = 0;
$loadError = '';

try {

    // Total and availability counts.
    $allCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM food"
    )->fetchColumn();

    $availCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM food WHERE available = 1"
    )->fetchColumn();

    // Category tabs and their dish counts.
    $rows = $pdo->query(
        "SELECT c.name AS cname, COUNT(f.id) AS n
         FROM categories c
         LEFT JOIN food f ON f.category_id = c.id
         GROUP BY c.id, c.name
         ORDER BY c.name"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $categories[$row['cname']] = (int)$row['n'];
    }

    // Build search and category conditions.
    $where = [];
    $params = [];

    if ($q !== '') {
        $where[] = "(f.name LIKE ? OR c.name LIKE ?)";
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }

    if ($catSel !== 'all') {
        $where[] = "c.name = ?";
        $params[] = $catSel;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Count filtered dishes.
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM food f
         LEFT JOIN categories c ON f.category_id = c.id
         $whereSql"
    );
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    // Fetch dishes for the current page.
    $stmt = $pdo->prepare(
        "SELECT
            f.id AS f_id,
            f.name AS f_name,
            c.name AS f_category,
            f.price AS f_price,
            f.description AS f_desc,
            f.available AS f_available
         FROM food f
         LEFT JOIN categories c ON f.category_id = c.id
         $whereSql
         ORDER BY f.id DESC
         LIMIT $perPage OFFSET $offset"
    );

    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    $loadError = 'Unable to load the menu right now.';
}

function pageUrl(int $p, string $q, string $cat): string
{
    return $GLOBALS['self'] . '?' . http_build_query([
        'q' => $q,
        'cat' => $cat,
        'page' => $p
    ]);
}

$soldOut = $allCount - $availCount;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu - FoodFlow Admin</title>
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
            <a href="admin_orders.php"
               class="flex items-center gap-3 rounded-xl px-4 py-3 text-gray-700 hover:bg-orange-50">
                <i class="fa-solid fa-receipt w-5"></i> Orders
            </a>
            <a href="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>"
               class="flex items-center gap-3 rounded-xl bg-[#ff4d0a] px-4 py-3 text-white">
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
                    Manage <span class="text-[#ff4d0a]">Menu</span> 🍕
                </h2>
                <p class="mt-1 font-medium text-gray-500">
                    Add dishes, change prices and control what customers can order.
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
                        <i class="fa-solid fa-plus mr-2"></i>Add Dish
                    </button>
                <?php endif; ?>
            </div>
        </header>

        <!-- Messages -->
        <?php if ($demo): ?>
            <div class="mt-6 rounded-xl border border-yellow-300 bg-yellow-50 p-4 font-semibold text-yellow-800" role="alert">
                <i class="fa-solid fa-flask mr-2"></i>
                Demo menu — your database has no <code class="rounded bg-yellow-100 px-1"><?= htmlspecialchars($menuTable) ?></code>
                table yet, so you're seeing sample dishes and changes can't be saved.
                Fix the SETTINGS at the top of this file to connect your real menu.
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
            <div class="flex items-center gap-4 rounded-3xl bg-white p-5 shadow">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-orange-100 text-2xl text-[#ff4d0a]">
                    <i class="fa-solid fa-utensils"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-500">Total Dishes</p>
                    <p class="text-3xl font-extrabold"><?= $allCount ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4 rounded-3xl bg-white p-5 shadow">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-green-100 text-2xl text-green-600">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-500">Available</p>
                    <p class="text-3xl font-extrabold"><?= isset($c['available']) ? $availCount : '—' ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4 rounded-3xl bg-white p-5 shadow">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-red-100 text-2xl text-red-600">
                    <i class="fa-solid fa-ban"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-500">Sold Out</p>
                    <p class="text-3xl font-extrabold"><?= isset($c['available']) ? $soldOut : '—' ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4 rounded-3xl bg-white p-5 shadow">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-blue-100 text-2xl text-blue-600">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-500">Categories</p>
                    <p class="text-3xl font-extrabold"><?= isset($c['category']) ? count($categories) : '—' ?></p>
                </div>
            </div>
        </section>

        <!-- Search + category tabs -->
        <section class="mt-6 rounded-3xl bg-white p-5 shadow">
            <form method="GET" action="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>"
                  class="flex flex-wrap items-center gap-4">
                <input type="hidden" name="cat" value="<?= htmlspecialchars($catSel, ENT_QUOTES, 'UTF-8') ?>">

                <div class="flex min-w-[220px] flex-1 items-center gap-3 rounded-2xl
                            border border-gray-200 px-4 py-3 focus-within:border-orange-500">
                    <i class="fa-solid fa-magnifying-glass text-gray-500"></i>
                    <input type="search" name="q" id="searchInput" autocomplete="off"
                           value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Search dishes..." class="w-full outline-none">
                </div>

                <button type="submit"
                        class="rounded-2xl bg-[#ff4d0a] px-6 py-3 font-bold text-white hover:bg-orange-500">
                    Search
                </button>

                <?php if ($q !== ''): ?>
                    <a href="<?= htmlspecialchars(pageUrl(1, '', $catSel), ENT_QUOTES, 'UTF-8') ?>"
                       class="rounded-2xl bg-gray-100 px-5 py-3 font-bold text-gray-700 hover:bg-gray-200">
                        Clear
                    </a>
                <?php endif; ?>
            </form>

            <?php if ($categories): ?>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="<?= htmlspecialchars(pageUrl(1, $q, 'all'), ENT_QUOTES, 'UTF-8') ?>"
                       class="rounded-full px-4 py-2 text-sm font-bold
                              <?= $catSel === 'all' ? 'bg-[#ff4d0a] text-white' : 'bg-orange-50 text-gray-700 hover:bg-orange-100' ?>">
                        All (<?= $allCount ?>)
                    </a>
                    <?php foreach ($categories as $name => $n): ?>
                        <a href="<?= htmlspecialchars(pageUrl(1, $q, $name), ENT_QUOTES, 'UTF-8') ?>"
                           class="rounded-full px-4 py-2 text-sm font-bold
                                  <?= $catSel === $name ? 'bg-[#ff4d0a] text-white' : 'bg-orange-50 text-gray-700 hover:bg-orange-100' ?>">
                            <?= htmlspecialchars($name) ?> (<?= $n ?>)
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Dishes table -->
        <section class="mt-6 rounded-3xl bg-white p-6 shadow">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left">
                    <thead>
                        <tr class="border-b text-sm text-gray-500">
                            <th class="pb-3">Dish</th>
                            <?php if (isset($c['category'])): ?><th class="pb-3">Category</th><?php endif; ?>
                            <th class="pb-3">Price</th>
                            <?php if (isset($c['available'])): ?><th class="pb-3">Status</th><?php endif; ?>
                            <th class="pb-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$items): ?>
                        <tr>
                            <td colspan="5" class="py-10 text-center text-lg font-semibold text-gray-400">
                                No dishes found.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($items as $it): ?>
                        <?php
                            $rid       = (int)$it['f_id'];
                            $available = isset($c['available']) ? ((int)$it['f_available'] === 1) : true;
                            $isVeg     = isset($c['veg']) ? ((int)$it['f_veg'] === 1) : null;
                            $payload = [
                                'id'        => $rid,
                                'name'      => $it['f_name'],
                                'category'  => (string)($it['f_category'] ?? ''),
                                'price'     => $it['f_price'],
                                'desc'      => (string)($it['f_desc'] ?? ''),
                                'veg'       => $isVeg === null ? true : $isVeg,
                                'available' => $available,
                            ];
                        ?>
                        <tr class="border-b last:border-0">
                            <td class="py-4">
                                <div class="flex items-center gap-3">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <?php if ($isVeg !== null): ?>
                                                <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded border-2
                                                             <?= $isVeg ? 'border-green-600' : 'border-red-600' ?>"
                                                      title="<?= $isVeg ? 'Veg' : 'Non-veg' ?>">
                                                    <span class="h-2 w-2 rounded-full <?= $isVeg ? 'bg-green-600' : 'bg-red-600' ?>"></span>
                                                </span>
                                            <?php endif; ?>
                                            <p class="font-bold"><?= htmlspecialchars($it['f_name']) ?></p>
                                        </div>
                                        <?php if (isset($c['desc'])): ?>
                                            <p class="mt-0.5 max-w-xs truncate text-xs text-gray-500">
                                                <?= htmlspecialchars((string)$it['f_desc']) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <?php if (isset($c['category'])): ?>
                                <td class="py-4">
                                    <span class="rounded-full bg-orange-100 px-3 py-1 text-xs font-bold text-[#ff4d0a]">
                                        <?= htmlspecialchars((string)$it['f_category']) ?>
                                    </span>
                                </td>
                            <?php endif; ?>

                            <td class="py-4 text-lg font-extrabold text-[#ff4d0a]">
                                ₹<?= number_format((float)$it['f_price']) ?>
                            </td>

                            <?php if (isset($c['available'])): ?>
                                <td class="py-4">
                                    <span class="rounded-full px-3 py-1 text-sm font-bold
                                                 <?= $available ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                        <?= $available ? 'Available' : 'Sold out' ?>
                                    </span>
                                </td>
                            <?php endif; ?>

                            <td class="py-4">
                                <div class="flex justify-end gap-2">
                                    <?php if (isset($c['available'])): ?>
                                        <form method="POST" action="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="item_id" value="<?= $rid ?>">
                                            <button type="submit"
                                                    class="rounded-xl px-4 py-2 text-sm font-bold
                                                           <?= $available ? 'bg-yellow-50 text-yellow-700 hover:bg-yellow-100' : 'bg-green-50 text-green-700 hover:bg-green-100' ?>">
                                                <i class="fa-solid <?= $available ? 'fa-ban' : 'fa-check' ?> mr-1"></i>
                                                <?= $available ? 'Sold out' : 'Available' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <button type="button"
                                            onclick='openModal(<?= json_encode($payload, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'
                                            class="rounded-xl bg-orange-50 px-4 py-2 text-sm font-bold text-gray-800 hover:bg-orange-100">
                                        <i class="fa-solid fa-pen mr-1"></i>Edit
                                    </button>

                                    <form method="POST" action="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>"
                                          onsubmit="return confirm('Delete this dish? This cannot be undone.');">
                                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="item_id" value="<?= $rid ?>">
                                        <button type="submit"
                                                class="rounded-xl bg-red-50 px-4 py-2 text-sm font-bold text-red-600 hover:bg-red-100"
                                                aria-label="Delete dish">
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
                        Showing page <?= $page ?> of <?= $pages ?> (<?= $total ?> dishes)
                    </p>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="<?= htmlspecialchars(pageUrl($page - 1, $q, $catSel), ENT_QUOTES, 'UTF-8') ?>"
                               class="rounded-xl bg-orange-50 px-4 py-2 font-bold hover:bg-orange-100">← Prev</a>
                        <?php endif; ?>
                        <?php if ($page < $pages): ?>
                            <a href="<?= htmlspecialchars(pageUrl($page + 1, $q, $catSel), ENT_QUOTES, 'UTF-8') ?>"
                               class="rounded-xl bg-[#ff4d0a] px-4 py-2 font-bold text-white hover:bg-orange-500">Next →</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<!-- Add / Edit modal -->
<div id="modal" class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto bg-black/50 p-4">
    <div class="my-auto w-full max-w-lg rounded-3xl bg-white p-8 shadow-xl">

        <div class="flex items-center justify-between">
            <h3 id="modalTitle" class="text-2xl font-extrabold">
                Add <span class="text-[#ff4d0a]">Dish</span>
            </h3>
            <button type="button" onclick="closeModal()" class="text-2xl text-gray-500 hover:text-gray-800"
                    aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>" class="mt-6 space-y-4">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" id="f_action" value="add">
            <input type="hidden" name="item_id" id="f_id" value="">

            <input type="text" name="name" id="f_name" placeholder="Dish name" required maxlength="100"
                   class="w-full rounded-2xl border border-gray-200 p-4 outline-none focus:border-orange-500">

            <div class="grid grid-cols-2 gap-4">
                <?php if (isset($c['category'])): ?>
                    <div>
                        <input type="text" name="category" id="f_category" list="catList" placeholder="Category"
                               required maxlength="50"
                               class="w-full rounded-2xl border border-gray-200 p-4 outline-none focus:border-orange-500">
                        <datalist id="catList">
                            <?php foreach (array_keys($categories) as $name): ?>
                                <option value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                <?php endif; ?>

                <input type="number" name="price" id="f_price" placeholder="Price (₹)" min="0" step="1" required
                       class="w-full rounded-2xl border border-gray-200 p-4 outline-none focus:border-orange-500
                              <?= isset($c['category']) ? '' : 'col-span-2' ?>">
            </div>

            <?php if (isset($c['desc'])): ?>
                <textarea name="desc" id="f_desc" rows="3" maxlength="500" placeholder="Short description"
                          class="w-full rounded-2xl border border-gray-200 p-4 outline-none focus:border-orange-500"></textarea>
            <?php endif; ?>

            <div class="flex flex-wrap items-center gap-6 font-semibold">
                <?php if (isset($c['veg'])): ?>
                    <label class="flex items-center gap-2">
                        <input type="radio" name="veg" value="1" id="f_veg" checked class="accent-green-600"> Veg
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" name="veg" value="0" id="f_nonveg" class="accent-red-600"> Non-veg
                    </label>
                <?php endif; ?>
                <?php if (isset($c['available'])): ?>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="available" id="f_available" checked class="h-4 w-4 accent-orange-600">
                        Available to order
                    </label>
                <?php endif; ?>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeModal()"
                        class="w-1/3 rounded-2xl bg-gray-100 p-3 font-bold text-gray-700 hover:bg-gray-200">
                    Cancel
                </button>
                <button type="submit"
                        class="w-2/3 rounded-2xl bg-[#ff4d0a] p-3 text-lg font-bold text-white hover:bg-orange-500">
                    Save Dish
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
    const $ = id => document.getElementById(id);

    function openModal(d) {
        const editing = !!d;
        $('modalTitle').innerHTML = (editing ? 'Edit' : 'Add') + ' <span class="text-[#ff4d0a]">Dish</span>';
        $('f_action').value = editing ? 'edit' : 'add';
        $('f_id').value     = editing ? d.id : '';
        $('f_name').value   = editing ? d.name : '';
        $('f_price').value  = editing ? Math.round(d.price) : '';
        if ($('f_category'))  $('f_category').value = editing ? d.category : '';
        if ($('f_desc'))      $('f_desc').value     = editing ? d.desc : '';