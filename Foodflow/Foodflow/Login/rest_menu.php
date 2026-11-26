<?php
session_start();

require_once __DIR__ . '/../db.php';

// Only logged-in restaurant staff can see this page
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'restaurant_staff') {
    header('Location: ../Login/restaurant.php');
    exit;
}

$staffName = $_SESSION['full_name'] ?? 'Staff';

// Restaurant details
$restaurantName = 'FoodFlow Kitchen';
try {
    $stmt = $pdo->query("SELECT * FROM restaurants WHERE is_active = 1 LIMIT 1");
    if ($stmt && ($restaurant = $stmt->fetch(PDO::FETCH_ASSOC))) {
        $restaurantName = $restaurant['restaurant_name'] ?? 'FoodFlow Kitchen';
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
}

// Handle Add / Edit / Toggle / Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $avail = !empty($_POST['available']) ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE food SET available = ? WHERE id = ?");
            $stmt->execute([$avail, $id]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM food WHERE id = ?");
            $stmt->execute([$id]);
            header('Location: rest_menu.php');
            exit;
        }
    }

    // Save (Add or Edit)
    $id = (int)($_POST['item_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $catName = trim($_POST['category'] ?? 'Pizza');
    $price = (float)($_POST['price'] ?? 0);
    $desc = trim($_POST['description'] ?? '');

    if ($name !== '' && $price >= 0) {
        // Resolve category_id
        $catStmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? LIMIT 1");
        $catStmt->execute([$catName]);
        $catId = $catStmt->fetchColumn();
        if (!$catId) {
            $catIns = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $catIns->execute([$catName]);
            $catId = $pdo->lastInsertId();
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE food SET name = ?, category_id = ?, price = ?, description = ? WHERE id = ?");
            $stmt->execute([$name, $catId, $price, $desc, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO food (name, category_id, restaurant_id, price, description, available) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$name, $catId, $_SESSION['user_id'] ?? 2, $price, $desc]);
        }
    }
    header('Location: rest_menu.php');
    exit;
}

/*
 * ---------------------------------------------------------------
 * SAMPLE DATA - replace with queries on your own tables.
 * Match the column names to the ones your customer menu
 * (categories / celebration pages) already uses.
 * Example:
 *   $categories = $pdo->query("SELECT category_id, category_name FROM categories")->fetchAll();
 *   $items = $pdo->prepare("SELECT * FROM menu_items WHERE restaurant_id = ?");
 * ---------------------------------------------------------------
 */
$categories = ['Pizza', 'Pasta', 'Burgers', 'Tacos', 'Beverages', 'Desserts', 'Combos'];

$items = [
    ['id' => 1, 'name' => 'Margherita Pizza', 'category' => 'Pizza', 'price' => 149, 'veg' => true, 'available' => true, 'image' => 'margherita.png', 'desc' => 'Fresh tomatoes, mozzarella and basil.'],
    ['id' => 2, 'name' => 'Farmhouse Pizza', 'category' => 'Pizza', 'price' => 249, 'veg' => true, 'available' => true, 'image' => 'farmhouse.png', 'desc' => 'Capsicum, onion, mushrooms and cheese.'],
    ['id' => 3, 'name' => 'Cheese Burst Pizza', 'category' => 'Pizza', 'price' => 299, 'veg' => true, 'available' => true, 'image' => 'cheeseburst.png', 'desc' => 'Extra cheese with a creamy, gooey center.'],
    ['id' => 4, 'name' => 'Pepperoni Pizza', 'category' => 'Pizza', 'price' => 329, 'veg' => false, 'available' => true, 'image' => 'pepperoni.png', 'desc' => 'Savory pepperoni and melted mozzarella.'],
    ['id' => 5, 'name' => 'Veg Extravaganza', 'category' => 'Pizza', 'price' => 279, 'veg' => true, 'available' => true, 'image' => 'extravaganza.png', 'desc' => 'Colorful vegetables, olives and extra cheese.'],
    ['id' => 6, 'name' => 'BBQ Chicken Pizza', 'category' => 'Pizza', 'price' => 349, 'veg' => false, 'available' => true, 'image' => 'chicken.png', 'desc' => 'Smoky barbecue chicken with herbs and cheese.'],
    ['id' => 7, 'name' => 'Tandoori Paneer Pizza', 'category' => 'Pizza', 'price' => 299, 'veg' => true, 'available' => true, 'image' => 'paneertandoor.png', 'desc' => 'Spiced paneer, onion and creamy cheese.'],
    ['id' => 8, 'name' => 'Double Cheese Pizza', 'category' => 'Pizza', 'price' => 269, 'veg' => true, 'available' => true, 'image' => 'doublecheese.png', 'desc' => 'A cheesy delight with extra mozzarella.'],
    ['id' => 9, 'name' => 'Peppy Paneer Pizza', 'category' => 'Pizza', 'price' => 279, 'veg' => true, 'available' => true, 'image' => 'peppypanerr.png', 'desc' => 'Paneer cubes, capsicum and zesty seasoning.'],
    ['id' => 10, 'name' => 'Mexican Green Wave', 'category' => 'Pizza', 'price' => 299, 'veg' => true, 'available' => true, 'image' => 'mexican.png', 'desc' => 'Jalapeños, capsicum, onion and Mexican herbs.'],
    ['id' => 11, 'name' => 'Golden Corn Pizza', 'category' => 'Pizza', 'price' => 199, 'veg' => true, 'available' => true, 'image' => 'golden.png', 'desc' => 'Sweet corn, mozzarella and creamy sauce.'],
    ['id' => 12, 'name' => 'Paneer Makhani Pizza', 'category' => 'Pizza', 'price' => 319, 'veg' => true, 'available' => true, 'image' => 'makhnipaneer.png', 'desc' => 'Indian-style paneer with rich makhani sauce.'],
    ['id' => 13, 'name' => 'Country Feast Pizza', 'category' => 'Pizza', 'price' => 289, 'veg' => true, 'available' => true, 'image' => 'countryfeast.png', 'desc' => 'A hearty mix of vegetables, herbs and cheese.'],
    ['id' => 14, 'name' => 'Achari Do Pyaza', 'category' => 'Pizza', 'price' => 269, 'veg' => true, 'available' => true, 'image' => 'acharidopyaz.png', 'desc' => 'Tangy achari flavor with onion and capsicum.'],
    ['id' => 15, 'name' => 'Chicken Dominator', 'category' => 'Pizza', 'price' => 399, 'veg' => false, 'available' => true, 'image' => 'chicken2.png', 'desc' => 'Seasoned chicken and a generous cheesy topping.'],
    ['id' => 16, 'name' => 'Chicken Fiesta', 'category' => 'Pizza', 'price' => 359, 'veg' => false, 'available' => true, 'image' => 'chicken3.png', 'desc' => 'Chicken chunks, peppers and flavorful herbs.'],
    ['id' => 17, 'name' => 'Peri Peri Chicken', 'category' => 'Pizza', 'price' => 379, 'veg' => false, 'available' => true, 'image' => 'chicken4.png', 'desc' => 'Spicy peri-peri chicken with melted cheese.'],
    ['id' => 18, 'name' => 'Cheese Volcano', 'category' => 'Pizza', 'price' => 349, 'veg' => true, 'available' => true, 'image' => 'cheesevolcano.png', 'desc' => 'A rich cheese-filled center for cheese lovers.'],
    ['id' => 19, 'name' => 'Four Cheese Pizza', 'category' => 'Pizza', 'price' => 329, 'veg' => true, 'available' => true, 'image' => 'fourcheese.png', 'desc' => 'A creamy blend of four delicious cheeses.'],
    ['id' => 20, 'name' => 'Nacho Crunch Pizza', 'category' => 'Pizza', 'price' => 309, 'veg' => true, 'available' => true, 'image' => 'nacho.png', 'desc' => 'Crunchy tortilla chips, jalapeños, salsa and cheddar cheese.'],
    ['id' => 21, 'name' => 'Classic Pizza Burger', 'category' => 'Burgers', 'price' => 189, 'veg' => true, 'available' => true, 'image' => 'b1.png', 'desc' => 'Classic pizza sauce, melted mozzarella, fresh tomato, onion and Italian herbs in every bite.'],
    ['id' => 22, 'name' => 'Paneer Tikka Pizza Burger', 'category' => 'Burgers', 'price' => 219, 'veg' => true, 'available' => true, 'image' => 'b2.png', 'desc' => 'Tandoori paneer, melted mozzarella, capsicum, onion and spicy tikka sauce.'],
    ['id' => 23, 'name' => 'Golden Corn Pizza Burger', 'category' => 'Burgers', 'price' => 129, 'veg' => true, 'available' => true, 'image' => 'b3.png', 'desc' => 'Sweet corn, melted mozzarella, creamy cheese and zesty Italian seasoning.'],
    ['id' => 24, 'name' => 'Double Cheese Pizza Burger', 'category' => 'Burgers', 'price' => 219, 'veg' => true, 'available' => true, 'image' => 'b4.png', 'desc' => 'Crispy veg patty loaded with double mozzarella, cheddar and pizza sauce.'],
    ['id' => 25, 'name' => 'Peri Peri Pizza Burger', 'category' => 'Burgers', 'price' => 149, 'veg' => true, 'available' => true, 'image' => 'b5.png', 'desc' => 'Spicy peri-peri patty, melted mozzarella, jalapeños and tangy red sauce.'],
    ['id' => 26, 'name' => 'Pepperoni Pizza Burger', 'category' => 'Burgers', 'price' => 249, 'veg' => false, 'available' => true, 'image' => 'b6.png', 'desc' => 'Chicken pepperoni, gooey mozzarella, crispy patty and rich Italian tomato sauce.'],
    ['id' => 27, 'name' => 'Tandoori Chicken Pizza Burger', 'category' => 'Burgers', 'price' => 289, 'veg' => false, 'available' => true, 'image' => 'b7.png', 'desc' => 'Smoky tandoori chicken, melted mozzarella, crunchy capsicum and spicy tikka sauce.'],
    ['id' => 28, 'name' => 'Onion Ring Crunch Burger', 'category' => 'Burgers', 'price' => 119, 'veg' => true, 'available' => true, 'image' => 'b8.png', 'desc' => 'Crispy onion rings, gooey melted cheese and smoky mayo in every bite.'],
    ['id' => 29, 'name' => 'Classic Alfredo Pasta', 'category' => 'Pasta', 'price' => 219, 'veg' => true, 'available' => true, 'image' => 'p1.png', 'desc' => 'Penne pasta coated in rich, creamy Alfredo sauce, topped with parmesan and fresh herbs.'],
    ['id' => 30, 'name' => 'Pink Sauce Pasta', 'category' => 'Pasta', 'price' => 249, 'veg' => true, 'available' => true, 'image' => 'p2.png', 'desc' => 'Penne pasta tossed in creamy tomato sauce, topped with parmesan, fresh basil and Italian herbs.'],
    ['id' => 31, 'name' => 'Cheese Baked Pasta', 'category' => 'Pasta', 'price' => 299, 'veg' => true, 'available' => true, 'image' => 'p3.png', 'desc' => 'Penne pasta baked in rich tomato sauce, topped with golden melted mozzarella and Italian herbs.'],
    ['id' => 32, 'name' => 'Mac And Cheese', 'category' => 'Pasta', 'price' => 179, 'veg' => true, 'available' => true, 'image' => 'p4.png', 'desc' => 'Creamy, cheesy macaroni loaded with rich cheddar sauce and irresistible stretchy goodness.'],
    ['id' => 33, 'name' => 'Classic Veg Taco', 'category' => 'Tacos', 'price' => 219, 'veg' => true, 'available' => true, 'image' => 't1.png', 'desc' => 'Crispy shell filled with fresh veggies, lettuce, salsa and cheese.'],
    ['id' => 34, 'name' => 'Double Cheese Taco', 'category' => 'Tacos', 'price' => 249, 'veg' => true, 'available' => true, 'image' => 't2.png', 'desc' => 'Crispy tacos loaded with double mozzarella, cheddar, fresh veggies and tangy salsa.'],
    ['id' => 35, 'name' => 'Crispy Chicken Taco', 'category' => 'Tacos', 'price' => 299, 'veg' => false, 'available' => true, 'image' => 't3.png', 'desc' => 'Crispy chicken bites, fresh lettuce and juicy veggies topped with smoky creamy mayo.'],
    ['id' => 36, 'name' => 'Mexican Bean Taco', 'category' => 'Tacos', 'price' => 179, 'veg' => true, 'available' => true, 'image' => 't4.png', 'desc' => 'Seasoned beans, fresh avocado, juicy tomatoes and zesty Mexican salsa in a crispy shell.'],
    ['id' => 37, 'name' => 'Thums Up', 'category' => 'Beverages', 'price' => 49, 'veg' => true, 'available' => true, 'image' => 'bb1.png', 'desc' => 'Bold, refreshing cola with intense fizz, sparkling ice and a rich, chilled taste.'],
    ['id' => 38, 'name' => 'Sprite', 'category' => 'Beverages', 'price' => 49, 'veg' => true, 'available' => true, 'image' => 'bb2.png', 'desc' => 'Crisp lemon-lime soda with refreshing bubbles, chilled ice and a zesty mint twist.'],
    ['id' => 39, 'name' => 'Classic Iced Tea', 'category' => 'Beverages', 'price' => 79, 'veg' => true, 'available' => true, 'image' => 'bb3.png', 'desc' => 'Refreshing chilled iced tea infused with zesty lemon, sparkling ice and fresh mint.'],
    ['id' => 40, 'name' => 'Blue Lagoon', 'category' => 'Beverages', 'price' => 99, 'veg' => true, 'available' => true, 'image' => 'bb4.png', 'desc' => 'A refreshing blend of blue citrus syrup, zesty lemonade and sparkling soda served over chilled ice.'],
    ['id' => 41, 'name' => 'Classic Cold Coffee', 'category' => 'Beverages', 'price' => 119, 'veg' => true, 'available' => true, 'image' => 'bb5.png', 'desc' => 'Creamy chilled coffee blended with smooth milk and ice cream, topped with rich chocolate drizzle.'],
    ['id' => 42, 'name' => 'Blueberry Soda', 'category' => 'Beverages', 'price' => 99, 'veg' => true, 'available' => true, 'image' => 'bb6.png', 'desc' => 'Refreshing blueberry-flavoured soda with sparkling fizz, crushed ice and juicy blueberries.'],
    ['id' => 43, 'name' => 'Strawberry Mojito', 'category' => 'Beverages', 'price' => 119, 'veg' => true, 'available' => true, 'image' => 'bb7.png', 'desc' => 'Sweet strawberries, zesty lime, fresh mint and sparkling soda for a fruity, refreshing twist.'],
    ['id' => 44, 'name' => 'Oreo Coffee Frappe', 'category' => 'Beverages', 'price' => 149, 'veg' => true, 'available' => true, 'image' => 'bb8.png', 'desc' => 'Creamy Oreo coffee topped with whipped cream and chocolate drizzle.'],
    ['id' => 45, 'name' => 'Chocolate Brownie', 'category' => 'Desserts', 'price' => 249, 'veg' => true, 'available' => true, 'image' => 'd1.png', 'desc' => 'Rich, fudgy chocolate brownie drizzled with warm chocolate sauce and creamy vanilla ice cream.'],
    ['id' => 46, 'name' => 'Chocolate Mousse', 'category' => 'Desserts', 'price' => 149, 'veg' => true, 'available' => true, 'image' => 'd2.png', 'desc' => 'Light, fluffy chocolate mousse topped with rich chocolate curls and smooth chocolate cream.'],
    ['id' => 47, 'name' => 'Chocolate Lava Cake', 'category' => 'Desserts', 'price' => 99, 'veg' => true, 'available' => true, 'image' => 'd3.png', 'desc' => 'Warm chocolate cake with a gooey molten centre, served with creamy vanilla ice cream.'],
    ['id' => 48, 'name' => 'Oreo Ice Cream', 'category' => 'Desserts', 'price' => 99, 'veg' => true, 'available' => true, 'image' => 'd4.png', 'desc' => 'Creamy vanilla ice cream loaded with crunchy Oreo chunks and rich chocolate drizzle.'],
    ['id' => 49, 'name' => 'Red Velvet Pastry', 'category' => 'Desserts', 'price' => 119, 'veg' => true, 'available' => true, 'image' => 'd5.png', 'desc' => 'Moist red velvet cake layered with smooth, creamy frosting.'],
    ['id' => 50, 'name' => 'Choco Churros', 'category' => 'Desserts', 'price' => 199, 'veg' => true, 'available' => true, 'image' => 'd6.png', 'desc' => 'Crispy cinnamon churros served with rich, melted chocolate dip.'],
    ['id' => 51, 'name' => 'Black Forest pastry', 'category' => 'Desserts', 'price' => 79, 'veg' => true, 'available' => true, 'image' => 'd7.png', 'desc' => 'Chocolate sponge layered with whipped cream and juicy cherries.'],
    ['id' => 52, 'name' => 'Brownie Sundae', 'category' => 'Desserts', 'price' => 129, 'veg' => true, 'available' => true, 'image' => 'd8.png', 'desc' => 'Fudgy brownie chunks topped with creamy vanilla ice cream and rich chocolate sauce.'],
    ['id' => 53, 'name' => 'Pizza Combo', 'category' => 'Combos', 'price' => 299, 'veg' => true, 'available' => true, 'image' => 'c1.png', 'desc' => 'Pizza + garlic bread + Thums Up'],
    ['id' => 54, 'name' => 'Double Pizza Combo', 'category' => 'Combos', 'price' => 349, 'veg' => true, 'available' => true, 'image' => 'c2.png', 'desc' => 'two mini pizzas + cheesy dip + garlic bread.'],
    ['id' => 55, 'name' => 'Family Pizza Feast', 'category' => 'Combos', 'price' => 699, 'veg' => true, 'available' => true, 'image' => 'c3.png', 'desc' => 'two large pizzas + garlic bread + two beverages'],
    ['id' => 56, 'name' => 'Pasta Delight Combo', 'category' => 'Combos', 'price' => 319, 'veg' => true, 'available' => true, 'image' => 'c4.png', 'desc' => 'Creamy pasta + garlic bread + iced tea'],
    ['id' => 57, 'name' => 'Sweet Treat Combo', 'category' => 'Combos', 'price' => 299, 'veg' => true, 'available' => true, 'image' => 'c5.png', 'desc' => 'Chocolate brownie + vanilla ice cream + cold coffee'],
    ['id' => 58, 'name' => 'Chocolate Lovers Combo', 'category' => 'Combos', 'price' => 179, 'veg' => true, 'available' => true, 'image' => 'c6.png', 'desc' => 'Lava cake + Oreo ice cream + chocolate sauce'],
    ['id' => 59, 'name' => 'Mega Family Combo', 'category' => 'Combos', 'price' => 749, 'veg' => true, 'available' => true, 'image' => 'c7.png', 'desc' => 'Large pizza + pasta + garlic bread + two beverages.'],
    ['id' => 60, 'name' => 'Grand Feast Combo', 'category' => 'Combos', 'price' => 1499, 'veg' => true, 'available' => true, 'image' => 'c8.png', 'desc' => '2 Large Pizzas + Pink Sauce Pasta + 4 Beverages + 2 Pastries + Classic Veg Taco + 2 Golden Corn Pizza Burgers.'],
];

try {
    $catRows = $pdo->query("SELECT name FROM categories ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($catRows)) {
        $categories = $catRows;
    }

    $dbItems = $pdo->query("SELECT f.id, f.name, COALESCE(c.name, 'Pizza') AS category,
                                   f.price, f.is_veg AS veg, f.available,
                                   COALESCE(f.image, 'margherita.png') AS image,
                                   COALESCE(f.description, '') AS `desc`
                            FROM food f
                            LEFT JOIN categories c ON f.category_id = c.id
                            ORDER BY f.id DESC")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($dbItems)) {
        $items = $dbItems;
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
}

$totalItems     = count($items);
$availableCount = count(array_filter($items, fn($i) => !empty($i['available'])));
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
                            <button type="button"
                                    onclick="if(confirm('Delete this item?')){ /* TODO: delete from DB */ this.closest('article').remove(); }"
                                    class="rounded-xl bg-red-50 px-4 py-2 text-sm font-bold text-red-600 hover:bg-red-100">
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

        <form action="rest_menu.php" method="POST" class="mt-6 space-y-4">
            <input type="hidden" name="action" value="save">
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

            fetch('rest_menu.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'toggle', id: toggle.dataset.id, available: toggle.checked ? 1 : 0 })
            }).catch(err => console.error(err));
        });
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
        modal.classList.remove('hidden');
        modal.classList.add('flex');
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
