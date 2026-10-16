
<?php
session_start();

require_once __DIR__ . '/../db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';

    } else {
        try {
            // Find the customer account
            $stmt = $pdo->prepare(
                "SELECT user_id, full_name, email, password_hash, role
                 FROM users
                 WHERE email = ? AND role = 'customer'
                 LIMIT 1"
            );

            $stmt->execute([$email]);
            $customer = $stmt->fetch();

            // Verify the stored password hash
            if (
                $customer &&
                password_verify($password, $customer['password_hash'])
            ) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = $customer['user_id'];
                $_SESSION['full_name'] = $customer['full_name'];
                $_SESSION['role'] = $customer['role'];

                // Redirect after successful login
                header('Location: ../Dashboard/customer.html');
                exit;

            } else {
                $error = 'Invalid email or password.';
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'Unable to log in right now. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Customer Login - FoodFlow</title>

    <link rel="icon" type="image/png" href="../favicon.png">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>

<body class="m-0 min-h-screen bg-[url('./img/cust_bg.png')]
             bg-cover bg-center bg-no-repeat">

    <!-- FoodFlow Logo -->
    <h1 class="ml-4 mt-4 text-3xl font-bold text-gray-900">
        Food<span class="text-[#ff4d0a]">Flow</span>
    </h1>

    <!-- Two Cards -->
    <div class="mx-auto my-6 grid min-h-[calc(100vh-100px)]
                w-[95%] max-w-7xl grid-cols-1 overflow-hidden
                rounded-[2rem] shadow-xl lg:grid-cols-2">

        <!-- Left Card -->
        <div class="min-h-[350px] bg-[url('./img/c.png')]
                    bg-cover bg-center bg-no-repeat p-8 md:p-10">

            <h1 class="mt-8 text-5xl font-extrabold leading-tight text-gray-900">
                Welcome Back,<br>
                <span class="text-[#ff4d0a]">Food Lover! 👋</span>
            </h1>

            <p class="mt-2 text-lg font-medium text-gray-700">
                Log in to order your favorite food, enjoy delicious meals
                at your doorstep.
            </p>

        </div>

        <!-- Right Card -->
        <div class="bg-white/95 p-6 md:p-10">

            <h2 class="text-center text-4xl font-extrabold text-gray-900">
                Customer <span class="text-[#ff4d0a]">Login</span>
            </h2>

            <p class="mt-3 text-center text-lg font-semibold text-gray-500">
                Welcome back! Log in to continue your food journey.
            </p>

            <!-- Error Message -->
            <?php if ($error !== ''): ?>
                <div class="mt-6 rounded-xl border border-red-300
                            bg-red-50 p-4 text-red-700"
                     role="alert">
                    <i class="fa-solid fa-circle-exclamation mr-2"></i>
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form action="customer.php" method="POST"
                  class="mt-10 space-y-5">

                <!-- Email -->
                <div class="flex items-center gap-4 rounded-2xl
                            border border-gray-200 p-5
                            focus-within:border-orange-500">

                    <i class="fa-regular fa-envelope text-xl text-gray-600"></i>

                    <input
                        type="email"
                        name="email"
                        placeholder="Email ID"
                        autocomplete="username"
                        maxlength="100"
                        required
                        class="w-full outline-none">
                </div>

                <!-- Password -->
                <div class="flex items-center gap-4 rounded-2xl
                            border border-gray-200 p-5
                            focus-within:border-orange-500">

                    <i class="fa-solid fa-lock text-xl text-gray-600"></i>

                    <input
                        type="password"
                        name="password"
                        placeholder="Password"
                        autocomplete="current-password"
                        required
                        class="w-full outline-none">
                </div>

                <!-- Login Button -->
                <button type="submit"
                        class="w-full rounded-2xl bg-[#ff4d0a] p-4 text-xl font-bold text-white hover:bg-orange-500">
                    Login →
                </button>

            </form>

            <!-- OR Divider -->
            <div class="my-7 flex items-center gap-4 text-gray-500">
                <hr class="flex-1">
                <span>OR</span>
                <hr class="flex-1">
            </div>

            <!-- Google Button -->
            <button type="button"
                    class="flex w-full items-center justify-center gap-3
                           rounded-2xl border-2 border-[#ff4d0a] p-5
                           font-semibold hover:bg-orange-50">

                <img
                    src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg"
                    alt="Google"
                    class="h-7 w-7">

                Continue with Google
            </button>

            <!-- Signup Link -->
            <p class="mt-10 text-center text-lg font-semibold text-gray-800">
                Don't have an account?

                <a href="../SignUp/customer.php"
                   class="font-bold text-[#ff4d0a] hover:underline">
                    Sign Up
                </a>
            </p>

        </div>
    </div>

</body>
</html>
