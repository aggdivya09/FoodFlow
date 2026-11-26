<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../Login/customer.php");
    exit;
}

$fullName = $_SESSION['full_name'];
$role     = ucfirst($_SESSION['role']);
$userId   = $_SESSION['user_id'];

// Optional fields: shown only if your login script stores them in the session
$email   = $_SESSION['email']   ?? 'aggdivya091@gmailcom';
$phone   = $_SESSION['phone']   ?? '';
$address = $_SESSION['address'] ?? '';

$nameParts = preg_split('/\s+/', trim($fullName));
if (count($nameParts) >= 2) {
    $initials = strtoupper(
        mb_substr($nameParts[0], 0, 1) .
        mb_substr($nameParts[count($nameParts) - 1], 0, 1)
    );
} else {
    $initials = strtoupper(mb_substr($nameParts[0], 0, 2));
}

function field(string $label, string $value, string $icon): void { ?>
    <div class="flex items-start gap-3 py-3">
        <i class="<?= $icon ?> mt-1 w-5 text-center text-orange-500"></i>
        <div class="min-w-0">
            <p class="text-xs font-semibold text-gray-500"><?= htmlspecialchars($label) ?></p>
            <p class="break-words font-semibold text-slate-900">
                <?= $value !== '' ? htmlspecialchars($value) : '<span class="font-normal text-gray-400">Not added yet</span>' ?>
            </p>
        </div>
    </div>
<?php }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile (Customer)</title>
    <link rel="icon" type="image/png" href="../favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <script>
        window.FF_USER = <?= json_encode((string)$userId, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    </script>
    <script src="./foodflow.js"></script>
</head>
<body>

<!-- Top Navbar -->
<header class="relative z-30 flex h-16 w-full items-center justify-between bg-white px-6 shadow-sm">
    <a href="../index.html" class="flex shrink-0 items-center gap-2 no-underline">
        <i class="fa-solid fa-kitchen-set text-3xl text-orange-500"></i>
        <span class="text-3xl font-extrabold tracking-tight text-slate-900">
            Food<span class="text-orange-600">Flow</span>
        </span>
    </a>

    <div class="flex shrink-0 items-center gap-3">
        <a href="#favourites" aria-label="Favourites"
           class="relative flex h-11 w-11 items-center justify-center rounded-full text-slate-700 transition hover:bg-orange-50 hover:text-red-500">
            <i class="fa-regular fa-heart text-xl"></i>
            <span data-fav-count class="absolute -right-0.5 -top-0.5 hidden h-5 min-w-5 rounded-full bg-red-500 px-1 text-center text-xs font-bold leading-5 text-white">0</span>
        </a>
        <button type="button" data-open-cart aria-label="Open cart"
                class="relative flex h-11 w-11 items-center justify-center rounded-full text-slate-700 transition hover:bg-orange-50 hover:text-orange-600">
            <i class="fa-solid fa-cart-shopping text-xl"></i>
            <span data-cart-count class="absolute -right-0.5 -top-0.5 hidden h-5 min-w-5 rounded-full bg-orange-600 px-1 text-center text-xs font-bold leading-5 text-white">0</span>
        </button>
        <div class="flex items-center gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-orange-100 text-lg font-bold text-orange-600">
                <?= htmlspecialchars($initials) ?>
            </div>
            <div>
                <p class="text-base font-bold text-slate-900"><?= htmlspecialchars($fullName) ?></p>
                <p class="text-sm text-gray-500"><?= htmlspecialchars($role) ?></p>
            </div>
        </div>
    </div>
</header>

<div class="flex min-h-screen bg-[#fffaf5]">
<aside class="w-64 shrink-0 bg-[#fff3e6] px-4 py-3">
    <nav class="flex flex-col gap-2">

        <a href="customer.php" class="flex items-center gap-4 rounded-xl px-4 py-3 text-slate-900 transition hover:bg-[#ffe3d2] hover:text-orange-600">
            <i class="fa-solid fa-house text-xl"></i><span>Dashboard</span>
        </a>
        <a href="../Menu/menuafterlogin.html" class="flex items-center gap-4 rounded-xl px-4 py-3 text-slate-900 transition hover:bg-[#ffe3d2] hover:text-orange-600">
            <i class="fa-solid fa-utensils text-xl"></i><span>Menu</span>
        </a>
        <a href="../My_orders/orders.php" class="flex items-center gap-4 rounded-xl px-4 py-3 text-slate-900 transition hover:bg-[#ffe3d2] hover:text-orange-600">
            <i class="fa-solid fa-clipboard-list text-xl"></i><span>My Orders</span>
        </a>
        <a href="../My_orders/track_order.php" class="flex items-center gap-4 rounded-xl px-4 py-3 text-slate-900 transition hover:bg-[#ffe3d2] hover:text-orange-600">
            <i class="fa-solid fa-location-dot text-xl"></i><span>Track Order</span>
        </a>
        <a href="profile.php" class="flex items-center gap-4 rounded-xl bg-[#ffe3d2] px-4 py-3 font-bold text-orange-600">
            <i class="fa-regular fa-user text-xl"></i><span>Profile</span>
        </a>
        <a href="../Help&Support/help.html" class="flex items-center gap-4 rounded-xl px-4 py-3 text-slate-900 transition hover:bg-[#ffe3d2] hover:text-orange-600">
            <i class="fa-regular fa-circle-question text-xl"></i><span>Help &amp; Support</span>
        </a>

        <hr class="my-2 border-gray-200">

        <a href="../Login/logout.php"
           class="flex items-center gap-4 rounded-xl px-4 py-5 text-xl text-red-500 transition hover:bg-[#ffcaca]">
            <i class="fa-solid fa-arrow-right-from-bracket text-xl"></i><span>Logout</span>
        </a>
    </nav>
</aside>

<main class="min-w-0 flex-1 p-4">

    <h1 class="mb-4 text-2xl font-extrabold text-slate-900">My Profile</h1>

    <div class="grid gap-4 lg:grid-cols-3">

        <!-- Account card -->
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col items-center text-center">
                <div class="flex h-24 w-24 items-center justify-center rounded-full bg-orange-100 text-3xl font-extrabold text-orange-600">
                    <?= htmlspecialchars($initials) ?>
                </div>
                <h2 class="mt-3 text-xl font-extrabold text-slate-900"><?= htmlspecialchars($fullName) ?></h2>
                <span class="mt-1 rounded-full bg-[#fff3e6] px-3 py-1 text-sm font-semibold text-orange-600">
                    <?= htmlspecialchars($role) ?>
                </span>
            </div>

            <div class="mt-4 divide-y divide-gray-100 border-t border-gray-100">
                <?php field('Email', $email, 'fa-regular fa-envelope'); ?>
                <?php field('Phone', $phone, 'fa-solid fa-phone'); ?>
                <?php field('Delivery address', $address, 'fa-solid fa-location-dot'); ?>
            </div>
        </section>

        <!-- Activity -->
        <div class="lg:col-span-2">
            <div class="grid grid-cols-2 gap-4">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-semibold text-gray-500">Items in cart</p>
                    <p id="statCart" class="mt-1 text-3xl font-extrabold text-orange-600">0</p>
                    <button type="button" data-open-cart class="mt-2 text-sm font-semibold text-orange-600 hover:underline">View cart</button>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-semibold text-gray-500">Favourite dishes</p>
                    <p id="statFavs" class="mt-1 text-3xl font-extrabold text-red-500">0</p>
                    <a href="#favourites" class="mt-2 inline-block text-sm font-semibold text-orange-600 hover:underline">See favourites</a>
                </div>
            </div>

            <section id="favourites" class="mt-6 scroll-mt-4">
                <h2 class="mb-3 text-xl font-extrabold text-slate-900">Favourites</h2>
                <div id="favList" class="grid gap-3 sm:grid-cols-2"></div>
            </section>
        </div>

    </div>
</main>
</div>
<script>
window.FF_USER = <?= json_encode((string)$_SESSION['user_id'], JSON_HEX_TAG | JSON_HEX_AMP) ?>;
window.FF_PROFILE = <?= json_encode(['name' => $fullName, 'role' => $role], JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<script>
    FF.mountFavourites("#favList");

    function updateStats() {
        document.getElementById("statCart").textContent =
            document.querySelector("[data-cart-count]").textContent;
        document.getElementById("statFavs").textContent =
            document.querySelector("[data-fav-count]").textContent;
    }
    document.addEventListener("ff:change", updateStats);
    document.addEventListener("DOMContentLoaded", () => setTimeout(updateStats, 0));
</script>
<script src="../foodflow.js"></script>   <!-- Menu/, categories/, About/, Celebration/, dashboard/ pages -->
<script src="./foodflow.js"></script>    <!-- indexafterlogin.html (root) -->
</body>
</html>
