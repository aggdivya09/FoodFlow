
<?php
session_start();

require_once __DIR__ . '/../db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';

    } else {
        try {
            // Find the admin account
            $stmt = $pdo->prepare(
                "SELECT user_id, full_name, email, password_hash, role
                 FROM users
                 WHERE email = ? AND role = 'admin'
                 LIMIT 1"
            );

            $stmt->execute([$email]);
            $admin = $stmt->fetch();

            // Verify the password
            if (
                $admin &&
                password_verify($password, $admin['password_hash'])
            ) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = $admin['user_id'];
                $_SESSION['full_name'] = $admin['full_name'];
                $_SESSION['role'] = $admin['role'];

                // Change this path if your dashboard has another location
                header('Location: ../Admin/dashboard.php');
                exit;

            } else {
                $error = 'Invalid admin email or password.';
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

    <title>Admin Login - FoodFlow</title>

    <link rel="icon" type="image/png" href="../favicon.png">

    <script src="https://cdn.tailwindcss.com"></script>

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
        <div class="min-h-[350px] bg-[url('./img/a.png')]
                    bg-cover bg-center bg-no-repeat p-8 md:p-10">

            <h1 class="mt-8 text-5xl font-extrabold leading-tight text-gray-900">
                Welcome Back,<br>
                <span class="text-[#ff4d0a]">Admin!</span>
            </h1>

            <p class="mt-2 text-lg font-medium text-gray-700">
                Manage users, orders, menu and reports
                to keep FoodFlow running smoothly.
            </p>

        </div>

        <!-- Right Card -->
        <div class="bg-white/95 p-6 md:p-10">

            <h2 class="text-center text-4xl font-extrabold text-gray-900">
                Admin <span class="text-[#ff4d0a]">Login</span>
            </h2>

            <p class="mt-3 text-center text-lg font-semibold text-gray-500">
                Securely access your dashboard and oversee daily operations.
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
            <form action="admin.php" method="POST"
                  class="mt-10 space-y-5">

                <!-- Email -->
                <div class="flex items-center gap-4 rounded-2xl
                            border border-gray-200 p-5
                            focus-within:border-orange-500">

                    <i class="fa-regular fa-envelope text-xl text-gray-600"></i>

                    <input
                        type="email"
                        name="email"
                        placeholder="Admin Email ID"
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
                        class="w-full rounded-2xl bg-[#ff4d0a]
                               p-4 text-xl font-bold text-white
                               hover:bg-orange-500">
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
                    class="flex w-full items-center justify-center
                           gap-3 rounded-2xl border-2
                           border-[#ff4d0a] p-5 font-semibold
                           hover:bg-orange-50">

                <img
                    src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg"
                    alt="Google"
                    class="h-7 w-7">

                Continue with Google
            </button>

        </div>
    </div>

</body>
</html>
