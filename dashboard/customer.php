<?php
session_start();

// Check whether the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Login/customer.php");
    exit;
}

$fullName = $_SESSION['full_name'];
$role = ucfirst($_SESSION['role']);

// Generate initials from first and last name
$nameParts = preg_split('/\s+/', trim($fullName));

if (count($nameParts) >= 2) {
    $initials = strtoupper(
        mb_substr($nameParts[0], 0, 1) .
        mb_substr($nameParts[count($nameParts) - 1], 0, 1)
    );
} else {
    $initials = strtoupper(mb_substr($nameParts[0], 0, 2));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard (Customer)</title>
    <link rel="icon" type="image/png" href="../favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />

    <!-- Per-user key so carts/favourites don't mix between accounts on one browser -->
    <script>
        window.FF_USER = <?= json_encode((string)$_SESSION['user_id'], JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        // window.FF_CHECKOUT_URL = "../Cart/checkout.php";  // set when your checkout page exists
    </script>
    <script src="./foodflow.js"></script>
</head>
<body>

<!-- Top Navbar -->
<header class="relative z-30 flex h-16 w-full items-center justify-between bg-white px-6 shadow-sm">

    <!-- FoodFlow Logo -->
    <a href="../index.html" class="flex shrink-0 items-center gap-2 no-underline">
        <i class="fa-solid fa-kitchen-set text-3xl text-orange-500"></i>
        <span class="text-3xl font-extrabold tracking-tight text-slate-900">
            Food<span class="text-orange-600">Flow</span>
        </span>
    </a>

    <!-- Search Bar -->
    <div class="relative mx-10 max-w-2xl flex-1">
        <div class="flex items-center gap-3 rounded-full bg-gray-100 px-5 py-3">
            <i class="fa-solid fa-magnifying-glass text-lg text-gray-500"></i>
            <input
                id="searchInput"
                type="search"
                autocomplete="off"
                aria-label="Search dishes"
                placeholder="Search for your favorite dishes..."
                class="w-full bg-transparent text-sm outline-none placeholder:text-gray-500"
            >
        </div>
        <!-- Live results -->
        <div id="searchPanel"
             class="absolute left-0 right-0 top-full mt-2 hidden overflow-hidden rounded-2xl border border-gray-200 bg-white py-1 shadow-xl"></div>
    </div>

    <!-- Favourites, Cart, Profile -->
    <div class="flex shrink-0 items-center gap-3">

        <a href="profile.php#favourites" aria-label="Favourites"
           class="relative flex h-11 w-11 items-center justify-center rounded-full text-slate-700 transition hover:bg-orange-50 hover:text-red-500">
            <i class="fa-regular fa-heart text-xl"></i>
            <span data-fav-count class="absolute -right-0.5 -top-0.5 hidden h-5 min-w-5 rounded-full bg-red-500 px-1 text-center text-xs font-bold leading-5 text-white">0</span>
        </a>

        <button type="button" data-open-cart aria-label="Open cart"
                class="relative flex h-11 w-11 items-center justify-center rounded-full text-slate-700 transition hover:bg-orange-50 hover:text-orange-600">
            <i class="fa-solid fa-cart-shopping text-xl"></i>
            <span data-cart-count class="absolute -right-0.5 -top-0.5 hidden h-5 min-w-5 rounded-full bg-orange-600 px-1 text-center text-xs font-bold leading-5 text-white">0</span>
        </button>

        <a href="profile.php" class="flex items-center gap-3 no-underline">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-orange-100 text-lg font-bold text-orange-600">
                <?= htmlspecialchars($initials) ?>
            </div>
            <div>
                <p class="text-base font-bold text-slate-900"><?= htmlspecialchars($fullName) ?></p>
                <p class="text-sm text-gray-500"><?= htmlspecialchars($role) ?></p>
            </div>
        </a>

    </div>
</header>

<!-- Customer Sidebar -->
<div class="flex min-h-screen bg-[#fffaf5]">
<aside class="w-64 shrink-0 bg-[#fff3e6] px-4 py-3">
    <nav class="flex flex-col gap-2">

        <a href="customer.php" class="flex items-center gap-4 rounded-xl bg-[#ffe3d2] px-4 py-3 font-bold text-orange-600">
            <i class="fa-solid fa-house text-xl"></i>
            <span>Dashboard</span>
        </a>

        <a href="../Menu/menuafterlogin.html" class="flex items-center gap-4 rounded-xl px-4 py-3 text-slate-900 transition hover:bg-[#ffe3d2] hover:text-orange-600">
            <i class="fa-solid fa-utensils text-xl"></i>
            <span>Menu</span>
        </a>

        <a href="../My_orders/orders.php" class="flex items-center gap-4 rounded-xl px-4 py-3 text-slate-900 transition hover:bg-[#ffe3d2] hover:text-orange-600">
            <i class="fa-solid fa-clipboard-list text-xl"></i>
            <span>My Orders</span>
        </a>

        <a href="	../My_orders/track_order.php" class="flex items-center gap-4 rounded-xl px-4 py-3 text-slate-900 transition hover:bg-[#ffe3d2] hover:text-orange-600">
            <i class="fa-solid fa-location-dot text-xl"></i>
            <span>Track Order</span>
        </a>

        <a href="profile.php" class="flex items-center gap-4 rounded-xl px-4 py-3 text-slate-900 transition hover:bg-[#ffe3d2] hover:text-orange-600">
            <i class="fa-regular fa-user text-xl"></i>
            <span>Profile</span>
        </a>

        <a href="../Help&Support/help.html" class="flex items-center gap-4 rounded-xl px-4 py-3 text-slate-900 transition hover:bg-[#ffe3d2] hover:text-orange-600">
            <i class="fa-regular fa-circle-question text-xl"></i>
            <span>Help &amp; Support</span>
        </a>

        <hr class="my-2 border-gray-200">

        <a href="../Login/logout.php"
           class="flex items-center gap-4 rounded-xl px-4 py-5 text-xl text-red-500 transition hover:bg-[#ffcaca]">
            <i class="fa-solid fa-arrow-right-from-bracket text-xl"></i>
            <span>Logout</span>
        </a>

    </nav>
</aside>

<main class="min-w-0 flex-1 p-4">

<!-- Hero Banner -->
<section class="relative mt-1 h-[290px] w-full overflow-hidden rounded-2xl bg-[#fff0df]">

    <figure class="hover-gallery absolute inset-0 h-full w-full">
        <img src="./img/p1.png" alt="Pizza banner 1" class="h-full w-full object-cover" />
        <img src="./img/p3.png" alt="Pizza banner 3" class="h-full w-full object-cover" />
    </figure>

    <div class="relative z-10 flex h-full max-w-[65%] flex-col justify-center px-8 md:px-10">
        <p class="mb-2 text-xs font-bold uppercase tracking-[0.35em] text-slate-700">Good Food, Good Mood</p>
        <h1 class="text-3xl font-extrabold leading-tight text-slate-900 md:text-4xl">Delicious Food</h1>
        <h2 class="text-3xl font-extrabold leading-tight text-orange-600 md:text-4xl">Right at Your Doorstep!</h2>
        <p class="mt-3 text-sm leading-6 text-slate-700 md:text-base">
            Freshly prepared meals, made with love.<br>
            Explore our menu and order your favorites today!
        </p>
    </div>

</section>

<!-- Categories Section -->
<section class="mt-6">
    <h2 class="mb-3 text-xl font-extrabold text-slate-900">Categories</h2>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7">

        <a href="../categories/pizzaafterlogin.html"
           class="group flex h-[140px] flex-col items-center justify-center rounded-xl bg-[#fff3e6] p-3 transition duration-300 hover:-translate-y-1 hover:shadow-md">
            <img src="./img/c1.png" alt="Pizza" class="h-[85px] w-full object-contain transition duration-300 group-hover:scale-110">
            <span class="mt-1 font-bold text-slate-800">Pizza</span>
        </a>

        <a href="../categories/burgerafterlogin.html"
           class="group flex h-[140px] flex-col items-center justify-center rounded-xl bg-[#fff3e6] p-3 transition duration-300 hover:-translate-y-1 hover:shadow-md">
            <img src="./img/c2.png" alt="Burgers" class="h-[85px] w-full object-contain transition duration-300 group-hover:scale-110">
            <span class="mt-1 font-bold text-slate-800">Burgers</span>
        </a>

        <a href="../categories/pastafterlogin.html"
           class="group flex h-[140px] flex-col items-center justify-center rounded-xl bg-[#fff3e6] p-3 transition duration-300 hover:-translate-y-1 hover:shadow-md">
            <img src="./img/c3.png" alt="Pasta" class="h-[85px] w-full object-contain transition duration-300 group-hover:scale-110">
            <span class="mt-1 font-bold text-slate-800">Pasta</span>
        </a>

        <a href="../categories/tacosafterlogin.html"
           class="group flex h-[140px] flex-col items-center justify-center rounded-xl bg-[#fff3e6] p-3 transition duration-300 hover:-translate-y-1 hover:shadow-md">
            <img src="./img/c6.png" alt="Taco" class="h-[85px] w-full object-contain transition duration-300 group-hover:scale-110">
            <span class="mt-1 font-bold text-slate-800">Tacos</span>
        </a>

        <a href="../categories/beveragesafterlogin.html"
           class="group flex h-[140px] flex-col items-center justify-center rounded-xl bg-[#fff3e6] p-3 transition duration-300 hover:-translate-y-1 hover:shadow-md">
            <img src="./img/c4.png" alt="Beverages" class="h-[85px] w-full object-contain transition duration-300 group-hover:scale-110">
            <span class="mt-1 font-bold text-slate-800">Beverages</span>
        </a>

        <a href="../categories/dessertafterlogin.html"
           class="group flex h-[140px] flex-col items-center justify-center rounded-xl bg-[#fff3e6] p-3 transition duration-300 hover:-translate-y-1 hover:shadow-md">
            <img src="./img/c5.png" alt="Desserts" class="h-[85px] w-full object-contain transition duration-300 group-hover:scale-110">
            <span class="mt-1 font-bold text-slate-800">Desserts</span>
        </a>

        <a href="../categories/combosafterlogin.html"
           class="group flex h-[140px] flex-col items-center justify-center rounded-xl bg-[#fff3e6] p-3 transition duration-300 hover:-translate-y-1 hover:shadow-md">
            <img src="./img/c7.png" alt="Combos" class="h-[85px] w-full object-contain transition duration-300 group-hover:scale-110">
            <span class="mt-1 font-bold text-slate-800">Combos</span>
        </a>

    </div>
</section>

<!-- Popular Dishes Section -->
<section class="mt-7">

    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-xl font-extrabold text-slate-900">Popular Dishes</h2>
        <a href="../Menu/menuafterlogin.html"
           class="flex items-center gap-2 font-semibold text-orange-600 transition hover:text-orange-700">
            View All
            <i class="fa-solid fa-arrow-right"></i>
        </a>
    </div>

    <p id="noResults" class="mb-4 hidden rounded-xl bg-white p-4 text-gray-500">
        No popular dishes match your search. Check the dropdown under the search bar for the rest of the menu.
    </p>

    <div id="dishGrid" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">

        <!-- Margherita Pizza -->
        <div data-name="Margherita Pizza" data-cat="Pizza"
             class="flex h-full flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-sm transition hover:shadow-md">
            <div class="relative">
                <img src="./img/d1.png" alt="Margherita Pizza" class="h-24 w-[78%] rounded-xl object-cover">
                <button type="button" data-fav="p1"
                        class="absolute right-0 top-0 flex h-9 w-9 items-center justify-center rounded-full bg-white shadow">
                    <i class="fa-regular fa-heart text-gray-400"></i>
                </button>
            </div>
            <h3 class="mt-3 font-bold text-slate-900">Margherita Pizza</h3>
            <p class="mt-1 text-sm leading-5 text-gray-500">Classic delight with fresh tomatoes, mozzarella and basil.</p>
            <div class="mt-auto flex items-center justify-between pt-3">
                <span class="text-xl font-extrabold text-orange-600">₹149</span>
                <button type="button" data-add="p1"
                        class="rounded-xl bg-orange-600 px-6 py-3 font-bold text-white transition hover:bg-orange-700">Add</button>
            </div>
        </div>

        <!-- Classic Pizza Burger -->
        <div data-name="Classic Pizza Burger" data-cat="Burgers"
             class="flex h-full flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-sm transition hover:shadow-md">
            <div class="relative">
                <img src="./img/d2.png" alt="Classic Pizza Burger" class="h-24 w-[78%] rounded-xl object-cover">
                <button type="button" data-fav="p2"
                        class="absolute right-0 top-0 flex h-9 w-9 items-center justify-center rounded-full bg-white shadow">
                    <i class="fa-regular fa-heart text-gray-400"></i>
                </button>
            </div>
            <h3 class="mt-3 font-bold text-slate-900">Classic Pizza Burger</h3>
            <p class="mt-1 text-sm leading-5 text-gray-500">Juicy patty with cheese, fresh veggies and special sauce.</p>
            <div class="mt-auto flex items-center justify-between pt-3">
                <span class="text-xl font-extrabold text-orange-600">₹189</span>
                <button type="button" data-add="p2"
                        class="rounded-xl bg-orange-600 px-6 py-3 font-bold text-white transition hover:bg-orange-700">Add</button>
            </div>
        </div>

        <!-- Mac and Cheese -->
        <div data-name="Mac and Cheese" data-cat="Pasta"
             class="flex h-full flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-sm transition hover:shadow-md">
            <div class="relative">
                <img src="./img/d3.png" alt="Mac and Cheese" class="h-24 w-[78%] rounded-xl object-cover">
                <button type="button" data-fav="p3"
                        class="absolute right-0 top-0 flex h-9 w-9 items-center justify-center rounded-full bg-white shadow">
                    <i class="fa-regular fa-heart text-gray-400"></i>
                </button>
            </div>
            <h3 class="mt-3 font-bold text-slate-900">Mac and Cheese</h3>
            <p class="mt-1 text-sm leading-5 text-gray-500">Rich and creamy pasta with herbs and cheese.</p>
            <div class="mt-auto flex items-center justify-between pt-3">
                <span class="text-xl font-extrabold text-orange-600">₹179</span>
                <button type="button" data-add="p3"
                        class="rounded-xl bg-orange-600 px-6 py-3 font-bold text-white transition hover:bg-orange-700">Add</button>
            </div>
        </div>

        <!-- Veg Tacos -->
        <div data-name="Veg Tacos" data-cat="Tacos"
             class="flex h-full flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-sm transition hover:shadow-md">
            <div class="relative">
                <img src="./img/d4.png" alt="Veg Tacos" class="h-24 w-[78%] rounded-xl object-cover">
                <button type="button" data-fav="p4"
                        class="absolute right-0 top-0 flex h-9 w-9 items-center justify-center rounded-full bg-white shadow">
                    <i class="fa-regular fa-heart text-gray-400"></i>
                </button>
            </div>
            <h3 class="mt-3 font-bold text-slate-900">Veg Tacos</h3>
            <p class="mt-1 text-sm leading-5 text-gray-500">Crispy veggie tacos filled with fresh vegetables, beans and flavorful spices.</p>
            <div class="mt-auto flex items-center justify-between pt-3">
                <span class="text-xl font-extrabold text-orange-600">₹219</span>
                <button type="button" data-add="p4"
                        class="rounded-xl bg-orange-600 px-6 py-3 font-bold text-white transition hover:bg-orange-700">Add</button>
            </div>
        </div>

    </div>
</section>

</main>
</div>
<script>
window.FF_USER = <?= json_encode((string)$_SESSION['user_id'], JSON_HEX_TAG | JSON_HEX_AMP) ?>;
window.FF_PROFILE = <?= json_encode(['name' => $fullName, 'role' => $role], JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<script>
    FF.initSearch({
        input: "#searchInput",
        panel: "#searchPanel",
        grid: "#dishGrid",
        empty: "#noResults"
    });
</script>
<script src="../foodflow.js"></script>   <!-- Menu/, categories/, About/, Celebration/, dashboard/ pages -->
<script src="./foodflow.js"></script>    <!-- indexafterlogin.html (root) -->
</body>
</html>
