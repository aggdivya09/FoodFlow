<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../db.php';

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    // Validate required fields
    if (
        $full_name === "" ||
        $email === "" ||
        $phone === "" ||
        $password === "" ||
        $confirm_password === ""
    ) {
        $error = "Please fill in all the fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";

    } elseif (!preg_match('/^[0-9+\-\s()]{7,15}$/', $phone)) {
        $error = "Please enter a valid phone number.";

    } elseif (strlen($password) < 8) {
        $error = "Password must contain at least 8 characters.";

    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";

    } else {
        try {
            // Check whether email already exists
            $check = $pdo->prepare(
                "SELECT user_id FROM users WHERE email = ?"
            );

            $check->execute([$email]);

            if ($check->fetch()) {
                $error = "This email is already registered.";

            } else {
                // Securely hash the password
                $password_hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // Register the customer
                $sql = "INSERT INTO users
                        (full_name, email, phone, password_hash, role)
                        VALUES (?, ?, ?, ?, 'customer')";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([
                    $full_name,
                    $email,
                    $phone,
                    $password_hash
                ]);

                $success = "Account created successfully! You can now log in.";
            }

        } catch (PDOException $e) {
            // Log technical details privately on the server
            error_log($e->getMessage());

            if ($e->getCode() === '23000') {
                $error = "This email may already be registered.";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - FoodFlow</title>

    <link rel="icon" type="image/png" href="../favicon.png">

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>

<body class="m-0 min-h-screen w-full bg-[#fffaf5]
             bg-[url('./img/bg.png')] bg-cover bg-center
             bg-fixed bg-no-repeat">

    <!-- Navbar -->
    <nav class="flex items-center justify-between bg-white px-12 py-4 shadow-sm">

        <div>
            <h1 class="text-3xl font-bold text-slate-900">
                Food<span class="text-orange-600">Flow</span>
            </h1>
        </div>

        <div class="flex items-center gap-14 text-lg font-medium tracking-wider">
            <a href="../index.html" class="hover:text-orange-600">Home</a>
            <a href="../Menu/menu.html" class="hover:text-orange-600">Menu</a>
            <a href="../Celebration/celebration.html"
               class="hover:text-orange-600">Celebrations</a>
            <a href="../My_orders/orders.html"
               class="hover:text-orange-600">My Orders</a>
            <a href="../About/about.html" class="hover:text-orange-600">About Us</a>
        </div>

        <div class="flex items-center gap-5">
            <a href="../Login/select_role.html"
               class="rounded-full border border-orange-500 px-6 py-2
                      text-orange-600 hover:bg-orange-50">
                Login
            </a>

            <a href="../SignUp/index.php"
               class="rounded-full bg-[#ff4d0a] px-6 py-2
                      font-semibold text-white hover:bg-[#e94305]">
                Sign Up
            </a>
        </div>
    </nav>

    <!-- Main Cards -->
    <div class="mx-auto mt-5 grid w-[80%] grid-cols-1
                overflow-hidden rounded-3xl shadow-lg lg:grid-cols-2">

        <!-- Left Card -->
        <div class="min-h-[650px] rounded-l-3xl bg-[#ffeedb]
                    bg-cover bg-center p-10 lg:rounded-r-3xl"
             style="background-image: url('./img/pizza.png')">

            <p class="mb-5 font-bold uppercase tracking-widest text-orange-600">
                — Join FoodFlow
            </p>

            <h1 class="mb-4 text-5xl font-extrabold text-slate-900">
                Create Your<br>Account
            </h1>

            <p class="max-w-sm text-lg font-semibold text-gray-700">
                Sign up to explore delicious food, place orders
                and enjoy a seamless delivery experience.
            </p>

            <div class="mt-8 space-y-6">

                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center
                                rounded-full bg-[#ffddc7]">
                        <i class="fa-solid fa-cart-shopping
                                  text-2xl text-[#ff4d0a]"></i>
                    </div>

                    <div>
                        <h3 class="font-bold text-slate-900">Easy Ordering</h3>
                        <p class="text-sm font-semibold text-gray-600">
                            Browse our menu and place orders<br>
                            in just a few clicks.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center
                                rounded-full bg-[#ffddc7]">
                        <i class="fa-regular fa-clock
                                  text-2xl text-[#ff4d0a]"></i>
                    </div>

                    <div>
                        <h3 class="font-bold text-slate-900">
                            Track Your Orders
                        </h3>
                        <p class="text-sm font-semibold text-gray-600">
                            Get updates on your<br>
                            food delivery.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center
                                rounded-full bg-[#ffddc7]">
                        <i class="fa-regular fa-heart
                                  text-2xl text-[#ff4d0a]"></i>
                    </div>

                    <div>
                        <h3 class="font-bold text-slate-900">
                            Exclusive Offers
                        </h3>
                        <p class="text-sm text-gray-600">
                            Be the first to know about special<br>
                            deals and discounts.
                        </p>
                    </div>
                </div>

            </div>
        </div>

        <!-- Signup Form Card -->
        <div class="rounded-3xl bg-white/95 p-8 md:p-10">

            <h2 class="text-center text-4xl font-extrabold text-slate-900">
                Sign <span class="text-[#ff4d0a]">Up</span>
            </h2>

            <p class="mb-8 mt-3 text-center text-gray-600">
                Create your FoodFlow account as a Customer
            </p>

            <!-- Success Message -->
            <?php if ($success !== ""): ?>
                <div class="mb-5 rounded-lg border border-green-300
                            bg-green-50 p-3 text-sm text-green-700"
                     role="status">
                    <i class="fa-solid fa-circle-check mr-2"></i>
                    <?= htmlspecialchars($success) ?>
                    <a href="../Login/customer.php"
                       class="ml-1 font-bold underline">
                        Login here
                    </a>
                </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if ($error !== ""): ?>
                <div class="mb-5 rounded-lg border border-red-300
                            bg-red-50 p-3 text-sm text-red-700"
                     role="alert">
                    <i class="fa-solid fa-circle-exclamation mr-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form action="index.php" method="POST" class="space-y-4">

                <!-- Full Name -->
                <div>
                    <label for="full_name" class="mb-2 block font-medium">
                        Full Name
                    </label>

                    <div class="flex items-center gap-3 rounded-lg border p-3">
                        <i class="fa-regular fa-user text-gray-500"></i>

                        <input id="full_name" name="full_name"
                               type="text"
                               placeholder="Enter your full name"
                               value="<?= htmlspecialchars($full_name ?? '') ?>"
                               maxlength="100" autocomplete="name"
                               class="w-full outline-none"
                               required>
                    </div>
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="mb-2 block font-medium">
                        Email Address
                    </label>

                    <div class="flex items-center gap-3 rounded-lg border p-3">
                        <i class="fa-regular fa-envelope text-gray-500"></i>

                        <input id="email" name="email"
                               type="email"
                               placeholder="Enter your email address"
                               value="<?= htmlspecialchars($email ?? '') ?>"
                               maxlength="100" autocomplete="email"
                               class="w-full outline-none"
                               required>
                    </div>
                </div>

                <!-- Phone -->
                <div>
                    <label for="phone" class="mb-2 block font-medium">
                        Phone Number
                    </label>

                    <div class="flex items-center gap-3 rounded-lg border p-3">
                        <i class="fa-solid fa-phone text-gray-500"></i>

                        <input id="phone" name="phone"
                               type="tel"
                               placeholder="Enter your phone number"
                               value="<?= htmlspecialchars($phone ?? '') ?>"
                               maxlength="15" autocomplete="tel"
                               class="w-full outline-none"
                               required>
                    </div>
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="mb-2 block font-medium">
                        Password
                    </label>

                    <div class="flex items-center gap-3 rounded-lg border p-3">
                        <i class="fa-solid fa-lock text-gray-500"></i>

                        <input id="password" name="password"
                               type="password"
                               placeholder="Create a password"
                               minlength="8" maxlength="72"
                               autocomplete="new-password"
                               class="w-full outline-none"
                               required>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div>
                    <label for="confirm_password"
                           class="mb-2 block font-medium">
                        Confirm Password
                    </label>

                    <div class="flex items-center gap-3 rounded-lg border p-3">
                        <i class="fa-solid fa-lock text-gray-500"></i>

                        <input id="confirm_password"
                               name="confirm_password"
                               type="password"
                               placeholder="Confirm your password"
                               minlength="8" maxlength="72"
                               autocomplete="new-password"
                               class="w-full outline-none"
                               required>
                    </div>
                </div>

                <!-- Terms -->
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="terms" value="accepted"
                           required class="mt-1 accent-orange-600">

                    <span>
                        I agree to the
                        <a href="#" class="text-[#ff4d0a]">Terms & Conditions</a>
                        and
                        <a href="#" class="text-[#ff4d0a]">Privacy Policy</a>
                    </span>
                </label>

                <!-- Create Account -->
                <button type="submit"
                        class="w-full rounded-xl bg-[#ff4d0a] py-3
                               font-semibold text-white transition
                               hover:bg-[#e94305]">
                    Create Account
                    <i class="fa-solid fa-arrow-right ml-2"></i>
                </button>

            </form>

            <!-- Divider -->
            <div class="my-5 flex items-center gap-3 text-gray-500">
                <hr class="flex-1">
                <span>OR</span>
                <hr class="flex-1">
            </div>

            <!-- Login Link -->
            <p class="text-center text-sm text-gray-700">
                Already have an account?
                <a href="../Login/select_role.html"
                   class="font-semibold text-[#ff4d0a] hover:underline">
                    Login here
                </a>
            </p>

        </div>
    </div>

</body>
</html>
