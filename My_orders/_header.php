<?php
// Usage: $active = 'orders'|'track'|'checkout'; include '_header.php';
$fullName = $_SESSION['full_name'] ?? 'Customer';
$parts    = preg_split('/\s+/', trim($fullName));
$initials = strtoupper(count($parts) > 1 ? mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1) : mb_substr($parts[0], 0, 2));
$active   = $active ?? '';
$link = fn($k) => $active === $k ? 'font-bold text-orange-600' : 'hover:text-orange-600';
?>
<header class="sticky top-0 z-30 flex h-16 w-full items-center justify-between bg-white px-4 shadow-sm sm:px-6">
    <a href="../index.html" class="flex shrink-0 items-center gap-2 no-underline">
        <i class="fa-solid fa-kitchen-set text-3xl text-orange-500"></i>
        <span class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">Food<span class="text-orange-600">Flow</span></span>
    </a>
    <nav class="hidden items-center gap-6 text-base font-semibold text-slate-700 md:flex">
        <a href="../dashboard/customer.php" class="hover:text-orange-600">Dashboard</a>
        <a href="../Menu/menuafterlogin.html" class="hover:text-orange-600">Menu</a>
        <a href="orders.php" class="<?= $link('orders') ?>">My Orders</a>
        <a href="track_order.php" class="<?= $link('track') ?>">Track Order</a>
    </nav>
    <div class="flex items-center gap-3">
        <a href="../dashboard/profile.php" class="flex items-center gap-2 no-underline">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-orange-100 font-bold text-orange-600"><?= e($initials) ?></div>
            <span class="hidden text-sm font-bold text-slate-900 sm:block"><?= e($fullName) ?></span>
        </a>
        <a href="../logout.php" class="rounded-xl px-3 py-2 text-sm font-bold text-red-500 hover:bg-red-50">Logout</a>
    </div>
</header>
