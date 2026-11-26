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
$adminId   = (int)$_SESSION['user_id'];
$self      = basename($_SERVER['PHP_SELF']);   // this page's own file name

// CSRF token for the add / edit / delete forms
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

// Roles an admin can create. Any other role in the table is shown as "Customer".
$staffRoles = [
    'restaurant_staff' => 'Restaurant Staff',
    'delivery_person'  => 'Delivery Partner',
    'admin'            => 'Admin',
];
$roleStyle = [
    'restaurant_staff' => 'bg-orange-100 text-[#ff4d0a]',
    'delivery_person'  => 'bg-green-100 text-green-700',
    'admin'            => 'bg-purple-100 text-purple-700',
];

function flash(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
    global $self;
    header('Location: ' . $self);
    exit;
}

/* ---------------------------------------------------------------
 * Handle add / edit / delete (POST)
 * --------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        flash('error', 'Your session expired. Please try again.');
    }

    $action = $_POST['action'] ?? '';

    try {
        // ---------- DELETE ----------
        if ($action === 'delete') {
            $id = (int)($_POST['user_id'] ?? 0);

            if ($id === $adminId) {
                flash('error', 'You cannot delete your own account.');
            }

            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$id]);
            flash('success', 'User deleted.');
        }

        // ---------- ADD / EDIT ----------
        if ($action === 'add' || $action === 'edit') {
            $id       = (int)($_POST['user_id'] ?? 0);
            $name     = trim($_POST['full_name'] ?? '');
            $email    = trim($_POST['email'] ?? '');
            $role     = $_POST['role'] ?? '';
            $password = $_POST['password'] ?? '';

            if ($name === '' || mb_strlen($name) > 100) {
                flash('error', 'Please enter a name (up to 100 characters).');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
                flash('error', 'Please enter a valid email address.');
            }

            // Email must be unique
            $dup = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id <> ? LIMIT 1");
            $dup->execute([$email, $id]);
            if ($dup->fetch()) {
                flash('error', 'That email is already used by another account.');
            }

            if ($action === 'add') {
                if (!isset($staffRoles[$role])) {
                    flash('error', 'Please choose a valid role.');
                }
                if (strlen($password) < 8) {
                    flash('error', 'Password must be at least 8 characters.');
                }

                $stmt = $pdo->prepare(
                    "INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
                flash('success', 'User added.');
            }

            // edit
            $cur = $pdo->prepare("SELECT role FROM users WHERE user_id = ? LIMIT 1");
            $cur->execute([$id]);
            $existing = $cur->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                flash('error', 'User not found.');
            }

            // Allowed roles: the staff roles, or the user's current role (e.g. customer)
            if (!isset($staffRoles[$role]) && $role !== $existing['role']) {
                flash('error', 'Please choose a valid role.');
            }
            if ($id === $adminId && $role !== 'admin') {
                flash('error', 'You cannot remove your own admin access.');
            }
            if ($password !== '' && strlen($password) < 8) {
                flash('error', 'New password must be at least 8 characters.');
            }

            if ($password !== '') {
                $stmt = $pdo->prepare(
                    "UPDATE users SET full_name = ?, email = ?, role = ?, password_hash = ? WHERE user_id = ?"
                );
                $stmt->execute([$name, $email, $role, password_hash($password, PASSWORD_DEFAULT), $id]);
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE users SET full_name = ?, email = ?, role = ? WHERE user_id = ?"
                );
                $stmt->execute([$name, $email, $role, $id]);
            }

            if ($id === $adminId) {
                $_SESSION['full_name'] = $name;
            }
            flash('success', 'User updated.');
        }

        flash('error', 'Unknown action.');

    } catch (PDOException $e) {
        error_log($e->getMessage());
        flash('error', 'Could not save the change. This user may be linked to orders, or the users table needs extra columns.');
    }
}

/* ---------------------------------------------------------------
 * List users (GET): search + role filter + pagination
 * --------------------------------------------------------------- */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$q          = trim($_GET['q'] ?? '');
$roleFilter = $_GET['role'] ?? 'all';
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 12;

$staffKeys    = array_keys($staffRoles);
$placeholders = implode(',', array_fill(0, count($staffKeys), '?'));

$where  = [];
$params = [];

if ($q !== '') {
    $where[]  = '(full_name LIKE ? OR email LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if (isset($staffRoles[$roleFilter])) {
    $where[]  = 'role = ?';
    $params[] = $roleFilter;
} elseif ($roleFilter === 'customer') {
    $where[] = "role NOT IN ($placeholders)";
    $params  = array_merge($params, $staffKeys);
} else {
    $roleFilter = 'all';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$users = [];
$total = 0;
$counts = ['all' => 0, 'restaurant_staff' => 0, 'delivery_person' => 0, 'admin' => 0, 'customer' => 0];
$loadError = '';

try {
    // Tab counts
    foreach ($pdo->query("SELECT role, COUNT(*) AS c FROM users GROUP BY role")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $counts['all'] += (int)$r['c'];
        if (isset($staffRoles[$r['role']])) {
            $counts[$r['role']] = (int)$r['c'];
        } else {
            $counts['customer'] += (int)$r['c'];
        }
    }

    // Matching total
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users $whereSql");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $pages = max(1, (int)ceil($total / $perPage));
    $page  = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare(
        "SELECT user_id, full_name, email, role FROM users $whereSql
         ORDER BY user_id DESC LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $loadError = 'Unable to load users right now.';
    $pages = 1;
}

function pageUrl(int $p, string $q, string $role): string
{
    return $GLOBALS['self'] . '?' . http_build_query(['q' => $q, 'role' => $role, 'page' => $p]);
}

$tabs = [
    'all'              => 'All',
    'restaurant_staff' => 'Restaurant Staff',
    'delivery_person'  => 'Delivery',
    'customer'         => 'Customers',
    'admin'            => 'Admins',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - FoodFlow Admin</title>
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
            <a href="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>"
               class="flex items-center gap-3 rounded-xl bg-[#ff4d0a] px-4 py-3 text-white">
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
                    Manage <span class="text-[#ff4d0a]">Users</span> 👥
                </h2>
                <p class="mt-1 font-medium text-gray-500">
                    Add staff and riders, update accounts and remove access.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <a href="../logout.php"
                   class="flex items-center gap-2 rounded-2xl bg-white px-4 py-3 font-bold text-red-600 shadow hover:bg-red-50 lg:hidden">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
                <button type="button" onclick="openModal()"
                        class="rounded-2xl bg-[#ff4d0a] px-6 py-3 text-lg font-bold text-white shadow hover:bg-orange-500">
                    <i class="fa-solid fa-user-plus mr-2"></i>Add User
                </button>
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
        <?php if ($loadError !== ''): ?>
            <div class="mt-6 rounded-xl border border-red-300 bg-red-50 p-4 font-semibold text-red-700" role="alert">
                <i class="fa-solid fa-circle-exclamation mr-2"></i><?= htmlspecialchars($loadError) ?>
            </div>
        <?php endif; ?>

        <!-- Search + role tabs -->
        <section class="mt-6 rounded-3xl bg-white p-5 shadow">
            <form method="GET" action="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>" class="flex flex-wrap items-center gap-4">
                <input type="hidden" name="role" value="<?= htmlspecialchars($roleFilter, ENT_QUOTES, 'UTF-8') ?>">

                <div class="flex min-w-[220px] flex-1 items-center gap-3 rounded-2xl
                            border border-gray-200 px-4 py-3 focus-within:border-orange-500">
                    <i class="fa-solid fa-magnifying-glass text-gray-500"></i>
                    <input type="search" name="q" id="searchInput" autocomplete="off"
                           value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Search by name or email..." class="w-full outline-none">
                </div>

                <button type="submit"
                        class="rounded-2xl bg-[#ff4d0a] px-6 py-3 font-bold text-white hover:bg-orange-500">
                    Search
                </button>

                <?php if ($q !== ''): ?>
                    <a href="<?= htmlspecialchars(pageUrl(1, '', $roleFilter), ENT_QUOTES, 'UTF-8') ?>"
                       class="rounded-2xl bg-gray-100 px-5 py-3 font-bold text-gray-700 hover:bg-gray-200">
                        Clear
                    </a>
                <?php endif; ?>
            </form>

            <div class="mt-4 flex flex-wrap gap-2">
                <?php foreach ($tabs as $key => $label): ?>
                    <a href="<?= htmlspecialchars(pageUrl(1, $q, $key), ENT_QUOTES, 'UTF-8') ?>"
                       class="rounded-full px-4 py-2 text-sm font-bold
                              <?= $roleFilter === $key ? 'bg-[#ff4d0a] text-white' : 'bg-orange-50 text-gray-700 hover:bg-orange-100' ?>">
                        <?= htmlspecialchars($label) ?> (<?= (int)$counts[$key] ?>)
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Users table -->
        <section class="mt-6 rounded-3xl bg-white p-6 shadow">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[620px] text-left">
                    <thead>
                        <tr class="border-b text-sm text-gray-500">
                            <th class="pb-3">User</th>
                            <th class="pb-3">Email</th>
                            <th class="pb-3">Role</th>
                            <th class="pb-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$users): ?>
                        <tr>
                            <td colspan="4" class="py-10 text-center text-lg font-semibold text-gray-400">
                                No users found.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($users as $u): ?>
                        <?php
                            $isSelf  = ((int)$u['user_id'] === $adminId);
                            $label   = $staffRoles[$u['role']] ?? 'Customer';
                            $style   = $roleStyle[$u['role']] ?? 'bg-blue-100 text-blue-700';
                            $initial = strtoupper(mb_substr(trim($u['full_name']) ?: '?', 0, 1));
                            $payload = [
                                'id' => (int)$u['user_id'], 'name' => $u['full_name'],
                                'email' => $u['email'], 'role' => $u['role'], 'self' => $isSelf,
                            ];
                        ?>
                        <tr class="border-b last:border-0">
                            <td class="py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-orange-100 font-extrabold text-[#ff4d0a]">
                                        <?= htmlspecialchars($initial) ?>
                                    </div>
                                    <div>
                                        <p class="font-bold">
                                            <?= htmlspecialchars($u['full_name']) ?>
                                            <?php if ($isSelf): ?>
                                                <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-bold text-gray-500">You</span>
                                            <?php endif; ?>
                                        </p>
                                        <p class="text-xs text-gray-400">ID #<?= (int)$u['user_id'] ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-4 text-sm text-gray-600"><?= htmlspecialchars($u['email']) ?></td>
                            <td class="py-4">
                                <span class="rounded-full px-3 py-1 text-sm font-bold <?= $style ?>">
                                    <?= htmlspecialchars($label) ?>
                                </span>
                            </td>
                            <td class="py-4">
                                <div class="flex justify-end gap-2">
                                    <button type="button"
                                            onclick='openModal(<?= json_encode($payload, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'
                                            class="rounded-xl bg-orange-50 px-4 py-2 text-sm font-bold text-gray-800 hover:bg-orange-100">
                                        <i class="fa-solid fa-pen mr-1"></i>Edit
                                    </button>

                                    <?php if (!$isSelf): ?>
                                        <form method="POST" action="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>"
                                              onsubmit="return confirm('Delete this user? This cannot be undone.');">
                                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
                                            <button type="submit"
                                                    class="rounded-xl bg-red-50 px-4 py-2 text-sm font-bold text-red-600 hover:bg-red-100"
                                                    aria-label="Delete user">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
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
                        Showing page <?= $page ?> of <?= $pages ?> (<?= $total ?> users)
                    </p>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="<?= htmlspecialchars(pageUrl($page - 1, $q, $roleFilter), ENT_QUOTES, 'UTF-8') ?>"
                               class="rounded-xl bg-orange-50 px-4 py-2 font-bold hover:bg-orange-100">← Prev</a>
                        <?php endif; ?>
                        <?php if ($page < $pages): ?>
                            <a href="<?= htmlspecialchars(pageUrl($page + 1, $q, $roleFilter), ENT_QUOTES, 'UTF-8') ?>"
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
                Add <span class="text-[#ff4d0a]">User</span>
            </h3>
            <button type="button" onclick="closeModal()" class="text-2xl text-gray-500 hover:text-gray-800"
                    aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" action="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>" class="mt-6 space-y-4">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" id="f_action" value="add">
            <input type="hidden" name="user_id" id="f_id" value="">

            <input type="text" name="full_name" id="f_name" placeholder="Full name" required maxlength="100"
                   class="w-full rounded-2xl border border-gray-200 p-4 outline-none focus:border-orange-500">

            <input type="email" name="email" id="f_email" placeholder="Email ID" required maxlength="100"
                   class="w-full rounded-2xl border border-gray-200 p-4 outline-none focus:border-orange-500">

            <select name="role" id="f_role" required
                    class="w-full rounded-2xl border border-gray-200 p-4 outline-none focus:border-orange-500">
                <?php foreach ($staffRoles as $key => $label): ?>
                    <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>

            <div>
                <input type="password" name="password" id="f_password" minlength="8" autocomplete="new-password"
                       placeholder="Password (min 8 characters)"
                       class="w-full rounded-2xl border border-gray-200 p-4 outline-none focus:border-orange-500">
                <p id="pwHint" class="mt-1 hidden text-xs font-semibold text-gray-500">
                    Leave blank to keep the current password.
                </p>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeModal()"
                        class="w-1/3 rounded-2xl bg-gray-100 p-3 font-bold text-gray-700 hover:bg-gray-200">
                    Cancel
                </button>
                <button type="submit"
                        class="w-2/3 rounded-2xl bg-[#ff4d0a] p-3 text-lg font-bold text-white hover:bg-orange-500">
                    Save User
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
    const roleSelect = document.getElementById('f_role');
    const STAFF_ROLES = <?= json_encode(array_keys($staffRoles)) ?>;

    function openModal(user) {
        const editing = !!user;

        document.getElementById('modalTitle').innerHTML =
            (editing ? 'Edit' : 'Add') + ' <span class="text-[#ff4d0a]">User</span>';
        document.getElementById('f_action').value = editing ? 'edit' : 'add';
        document.getElementById('f_id').value     = editing ? user.id : '';
        document.getElementById('f_name').value   = editing ? user.name : '';
        document.getElementById('f_email').value  = editing ? user.email : '';

        // Remove any extra option added for a customer role earlier
        roleSelect.querySelectorAll('option[data-extra]').forEach(o => o.remove());

        if (editing && !STAFF_ROLES.includes(user.role)) {
            const opt = document.createElement('option');
            opt.value = user.role;
            opt.textContent = 'Customer (keep as is)';
            opt.dataset.extra = '1';
            roleSelect.appendChild(opt);
        }
        roleSelect.value = editing ? user.role : STAFF_ROLES[0];

        // A logged-in admin cannot change their own role
        roleSelect.disabled = !!(editing && user.self);
        if (roleSelect.disabled) {
            // disabled fields aren't submitted, so add a hidden copy
            let h = document.getElementById('f_role_hidden');
            if (!h) {
                h = document.createElement('input');
                h.type = 'hidden'; h.name = 'role'; h.id = 'f_role_hidden';
                roleSelect.parentNode.appendChild(h);
            }
            h.value = user.role;
        } else {
            const h = document.getElementById('f_role_hidden');
            if (h) h.remove();
        }

        const pw = document.getElementById('f_password');
        pw.value = '';
        pw.required = !editing;
        document.getElementById('pwHint').classList.toggle('hidden', !editing);

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.getElementById('f_name').focus();
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
