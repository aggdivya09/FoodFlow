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

$categories = $pdo->query("SELECT name FROM categories ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
$items = $pdo->query(
    "SELECT f.id, f.name, COALESCE(c.name, 'Uncategorised') AS category, f.price,
            f.is_veg AS veg, f.available, COALESCE(f.image, '') AS image,
            COALESCE(f.description, '') AS `desc`
     FROM food f LEFT JOIN categories c ON c.id = f.category_id
     ORDER BY f.id"
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($items as &$it) {
    $it['price']     = (float)$it['price'];
    $it['veg']       = (bool)$it['veg'];
    $it['available'] = (bool)$it['available'];
}
unset($it);
$totalItems     = count($items);
$availableCount = count(array_filter($items, fn($i) => $i['available']));
$soldOutCount   = $totalItems - $availableCount;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu Management - FoodFlow</title>
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
            <a href="rest_orders.php"
               class="flex items-center gap-3 rounded-xl px-4 py-3 text-gray-700 hover:bg-orange-50">
                <i class="fa-solid fa-receipt w-5"></i> Orders
            </a>
            <a href="#"
               class="flex items-center gap-3 rounded-xl bg-[#ff4d0a] px-4 py-3 text-white">
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
                    Menu <span class="text-[#ff4d0a]">Management</span> 🍕
                </h2>
                <p class="mt-1 font-medium text-gray-500">
                    Add dishes, update prices and mark items sold out.
                </p>
            </div>

            <button type="button" onclick="openModal()"
                    class="rounded-2xl bg-[#ff4d0a] px-6 py-3 text-lg font-bold text-white
                           shadow hover:bg-orange-500">
                <i class="fa-solid fa-plus mr-2"></i>Add Item
            </button>
        </header>

        <!-- Summary -->
        <section class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div class="flex items-center gap-4 rounded-3xl bg-white p-6 shadow">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-orange-100 text-2xl text-[#ff4d0a]">
                    <i class="fa-solid fa-utensils"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-500">Total Items</p>
                    <p class="text-3xl font-extrabold"><?= $totalItems ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4 rounded-3xl bg-white p-6 shadow">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-green-100 text-2xl text-green-600">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-500">Available</p>
                    <p id="availableCount" class="text-3xl font-extrabold"><?= $availableCount ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4 rounded-3xl bg-white p-6 shadow">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-red-100 text-2xl text-red-600">
                    <i class="fa-solid fa-ban"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-500">Sold Out</p>
                    <p id="soldOutCount" class="text-3xl font-extrabold"><?= $soldOutCount ?></p>
                </div>
            </div>
        </section>

        <!-- Search + category filters -->
        <section class="mt-8 rounded-3xl bg-white p-5 shadow">
            <div class="flex flex-wrap items-center gap-4">

                <div class="flex min-w-[220px] flex-1 items-center gap-3 rounded-2xl
                            border border-gray-200 px-4 py-3 focus-within:border-orange-500">
                    <i class="fa-solid fa-magnifying-glass text-gray-500"></i>
                    <input id="searchInput" type="text" placeholder="Search dishes..."
                           class="w-full outline-none">
                </div>

                <div id="filters" class="flex flex-wrap gap-2">
                    <button type="button" data-cat="All"
                            class="filter-btn rounded-full bg-[#ff4d0a] px-4 py-2 text-sm font-bold text-white">
                        All
                    </button>
                    <?php foreach ($categories as $c): ?>
                        <button type="button" data-cat="<?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?>"
                                class="filter-btn rounded-full bg-orange-50 px-4 py-2 text-sm font-bold text-gray-700 hover:bg-orange-100">
                            <?= htmlspecialchars($c) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Items grid -->
        <section id="grid" class="mt-6 grid grid-cols-1 gap-5 md:grid-cols-2 2xl:grid-cols-3">
            <?php foreach ($items as $it): ?>
                <article class="item-card flex flex-col rounded-3xl bg-white p-6 shadow"
                         data-name="<?= htmlspecialchars(strtolower($it['name']), ENT_QUOTES, 'UTF-8') ?>"
                         data-cat="<?= htmlspecialchars($it['category'], ENT_QUOTES, 'UTF-8') ?>"
                         data-available="<?= $it['available'] ? '1' : '0' ?>">

                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="flex h-5 w-5 items-center justify-center rounded border-2
                                             <?= $it['veg'] ? 'border-green-600' : 'border-red-600' ?>"
                                      title="<?= $it['veg'] ? 'Veg' : 'Non-veg' ?>">
                                    <span class="h-2.5 w-2.5 rounded-full <?= $it['veg'] ? 'bg-green-600' : 'bg-red-600' ?>"></span>
                                </span>
                                <h3 class="text-xl font-extrabold"><?= htmlspecialchars($it['name']) ?></h3>
                            </div>
                            <span class="mt-2 inline-block rounded-full bg-orange-100 px-3 py-1 text-xs font-bold text-[#ff4d0a]">
                                <?= htmlspecialchars($it['category']) ?>
                            </span>
                        </div>
                        <p class="text-2xl font-extrabold text-[#ff4d0a]">₹<?= (int)$it['price'] ?></p>
                    </div>

                    <p class="mt-3 flex-1 text-sm text-gray-600"><?= htmlspecialchars($it['desc']) ?></p>

                    <div class="mt-5 flex items-center justify-between border-t pt-4">
                        <label class="flex cursor-pointer items-center gap-3">
                            <span class="relative inline-flex items-center">
                                <input type="checkbox" class="avail-toggle peer sr-only"
                                       data-id="<?= (int)$it['id'] ?>"
                                       <?= $it['available'] ? 'checked' : '' ?>>
                                <span class="h-6 w-11 rounded-full bg-gray-300 transition peer-checked:bg-green-500
                                             after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5
                                             after:rounded-full after:bg-white after:transition
                                             peer-checked:after:translate-x-5"></span>
                            </span>
                            <span class="status-text text-sm font-bold <?= $it['available'] ? 'text-green-600' : 'text-red-600' ?>">
                                <?= $it['available'] ? 'Available' : 'Sold out' ?>
                            </span>
                        </label>

                        <div class="flex gap-2">
                            <button type="button"
                                    onclick='openModal(<?= json_encode($it, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'
                                    class="rounded-xl bg-orange-50 px-4 py-2 text-sm font-bold text-gray-800 hover:bg-orange-100">
                                <i class="fa-solid fa-pen mr-1"></i>Edit
                            </button>
                            <button type="button" data-id="<?= (int)$it['id'] ?>"
        class="delete-btn rounded-xl bg-red-50 px-4 py-2 text-sm font-bold text-red-600 hover:bg-red-100">
    <i class="fa-solid fa-trash"></i>
</button>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <p id="emptyMsg" class="mt-10 hidden text-center text-lg font-semibold text-gray-500">
            No dishes match your search.
        </p>
    </main>
</div>

<!-- Add / Edit modal -->
<div id="modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4">
    <div class="w-full max-w-lg rounded-3xl bg-white p-8 shadow-xl">

        <div class="flex items-center justify-between">
            <h3 id="modalTitle" class="text-2xl font-extrabold">
                Add <span class="text-[#ff4d0a]">Item</span>
            </h3>
            <button type="button" onclick="closeModal()" class="text-2xl text-gray-500 hover:text-gray-800"
                    aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="rest_menu_action.php" method="POST" class="mt-6 space-y-4">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="item_id" id="f_id">

            <input type="text" name="name" id="f_name" placeholder="Dish name" required maxlength="100"
                   class="w-full rounded-2xl border border-gray-200 p-4 outline-none focus:border-orange-500">

            <div class="grid grid-cols-2 gap-4">
                <select name="category" id="f_cat" required
                        class="w-full rounded-2xl border border-gray-200 p-4 outline-none focus:border-orange-500">
                    <?php foreach ($categories as $c): ?>
                        <option><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>

                <input type="number" name="price" id="f_price" placeholder="Price (₹)" min="1" required
                       class="w-full rounded-2xl border border-gray-200 p-4 outline-none focus:border-orange-500">
            </div>

            <textarea name="description" id="f_desc" rows="3" placeholder="Short description" maxlength="250"
                      class="w-full rounded-2xl border border-gray-200 p-4 outline-none focus:border-orange-500"></textarea>

            <label class="flex items-center gap-2 font-semibold text-gray-700">
    <input type="checkbox" name="is_veg" id="f_veg" checked class="h-4 w-4"> Vegetarian
</label>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeModal()"
                        class="w-1/3 rounded-2xl bg-gray-100 p-3 font-bold text-gray-700 hover:bg-gray-200">
                    Cancel
                </button>
                <button type="submit"
                        class="w-2/3 rounded-2xl bg-[#ff4d0a] p-3 text-lg font-bold text-white hover:bg-orange-500">
                    Save Item
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const CSRF = <?= json_encode($_SESSION['csrf']) ?>;
    const cards     = Array.from(document.querySelectorAll('.item-card'));
    const searchEl  = document.getElementById('searchInput');
    const emptyMsg  = document.getElementById('emptyMsg');
    let activeCat   = 'All';

    function applyFilters() {
        const q = searchEl.value.trim().toLowerCase();
        let shown = 0;
        cards.forEach(card => {
            const matchCat  = activeCat === 'All' || card.dataset.cat === activeCat;
            const matchText = card.dataset.name.includes(q);
            const visible   = matchCat && matchText;
            card.classList.toggle('hidden', !visible);
            if (visible) shown++;
        });
        emptyMsg.classList.toggle('hidden', shown !== 0);
    }

    searchEl.addEventListener('input', applyFilters);

    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            activeCat = btn.dataset.cat;
            document.querySelectorAll('.filter-btn').forEach(b => {
                const on = b === btn;
                b.classList.toggle('bg-[#ff4d0a]', on);
                b.classList.toggle('text-white', on);
                b.classList.toggle('bg-orange-50', !on);
                b.classList.toggle('text-gray-700', !on);
            });
            applyFilters();
        });
    });

    // Availability toggle (UI only - add a fetch() call to save to your database)
    document.querySelectorAll('.avail-toggle').forEach(toggle => {
        toggle.addEventListener('change', () => {
            const card = toggle.closest('.item-card');
            const text = card.querySelector('.status-text');
            card.dataset.available = toggle.checked ? '1' : '0';
            text.textContent = toggle.checked ? 'Available' : 'Sold out';


            const avail = cards.filter(c => c.dataset.available === '1').length;
            document.getElementById('availableCount').textContent = avail;
            document.getElementById('soldOutCount').textContent   = cards.length - avail;

            fetch('rest_menu_action.php', {
                method: 'POST',
                body: new URLSearchParams({ action: 'toggle', id: toggle.dataset.id, available: toggle.checked ? 1 : 0, csrf: CSRF })
            }).then(r => r.json()).then(o => { if (!o.ok) throw new Error(o.error); })
              .catch(err => { alert(err.message); location.reload(); });        });
    });

    // Modal
    const modal = document.getElementById('modal');

    function openModal(item) {
        const editing = !!item;
        document.getElementById('modalTitle').innerHTML =
            (editing ? 'Edit' : 'Add') + ' <span class="text-[#ff4d0a]">Item</span>';
        document.getElementById('f_id').value    = editing ? item.id : '';
        document.getElementById('f_name').value  = editing ? item.name : '';
        document.getElementById('f_cat').value   = editing ? item.category : document.getElementById('f_cat').options[0].value;
        document.getElementById('f_price').value = editing ? item.price : '';
        document.getElementById('f_desc').value  = editing ? item.desc : '';
        document.getElementById('f_veg').checked = editing ? !!item.veg : true;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
        document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', async () => {
        if (!confirm('Delete this item?')) return;
        try {
            const res = await fetch('rest_menu_action.php', {
                method: 'POST',
                body: new URLSearchParams({ action: 'delete', id: btn.dataset.id, csrf: CSRF })
            });
            const out = await res.json();
            if (!out.ok) throw new Error(out.error);
            location.reload();
        } catch (err) { alert(err.message); }
    }));
</script>

</body>
</html>
