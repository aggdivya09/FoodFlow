
<?php
session_start();

require_once __DIR__ . '/../db.php';

$message = "";
$messageType = "";
$validToken = false;
$token = "";

if (isset($_GET['token'])) {

    $token = $_GET['token'];

    // Check token format (64 hexadecimal characters)
    if (preg_match('/^[a-f0-9]{64}$/', $token)) {

        $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (token_hash)
        ) ENGINE=InnoDB");

        $tokenHash = hash('sha256', $token);

        // Find token and check its expiry
        $stmt = $pdo->prepare(
            "SELECT id, user_id
             FROM password_resets
             WHERE token_hash = ?
             AND expires_at > NOW()
             LIMIT 1"
        );

        $stmt->execute([$tokenHash]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($reset) {
            $validToken = true;
        } else {
            $message = "This password reset link is invalid or has expired. Please request a new one.";
            $messageType = "error";
        }

    } else {
        $message = "Invalid password reset link.";
        $messageType = "error";
    }

} else {
    $message = "No password reset token was provided.";
    $messageType = "error";
}


// Handle new password submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['reset_password'])) {

    $token = $_POST['token'] ?? "";
    $password = $_POST['password'] ?? "";
    $confirmPassword = $_POST['confirm_password'] ?? "";

    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {

        $message = "Invalid reset token.";
        $messageType = "error";

    } elseif (strlen($password) < 8) {

        $message = "Password must contain at least 8 characters.";
        $messageType = "error";

    } elseif ($password !== $confirmPassword) {

        $message = "Passwords do not match.";
        $messageType = "error";

    } else {

        try {

            $tokenHash = hash('sha256', $token);

            // Verify token again before updating password
            $stmt = $pdo->prepare(
                "SELECT id, user_id
                 FROM password_resets
                 WHERE token_hash = ?
                 AND expires_at > NOW()
                 LIMIT 1"
            );

            $stmt->execute([$tokenHash]);
            $reset = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reset) {

                $message = "This link has expired or has already been used. Please request a new one.";
                $messageType = "error";

            } else {

                // Hash the new password
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                // Update password and delete token atomically
                $pdo->beginTransaction();

                $update = $pdo->prepare(
                    "UPDATE users
                     SET password_hash = ?
                     WHERE user_id = ?"
                );

                $update->execute([
                    $passwordHash,
                    $reset['user_id']
                ]);

                // Invalidate the used reset token
                $delete = $pdo->prepare(
                    "DELETE FROM password_resets WHERE user_id = ?"
                );

                $delete->execute([$reset['user_id']]);

                $pdo->commit();

                $message = "Your password has been reset successfully. You can now log in.";
                $messageType = "success";
                $validToken = false;
            }

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log("FoodFlow password reset error: " . $e->getMessage());

            $message = "Something went wrong. Please try again.";
            $messageType = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reset Password | FoodFlow</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen flex items-center justify-center bg-[#fffaf5] p-4">

    <div class="w-full max-w-md bg-white rounded-2xl shadow-lg p-8">

        <h1 class="text-3xl font-bold text-gray-800 text-center mb-3">
            Reset Password
        </h1>

        <p class="text-gray-500 text-center mb-6">
            Create a new password for your FoodFlow account.
        </p>

        <?php if ($message !== ""): ?>

            <div class="mb-5 p-3 rounded-lg text-sm
                <?php echo $messageType === 'success'
                    ? 'bg-green-100 text-green-700'
                    : 'bg-red-100 text-red-700'; ?>">

                <?php echo htmlspecialchars($message); ?>

            </div>

        <?php endif; ?>


        <?php if ($validToken): ?>

            <form method="POST" action="">

                <input
                    type="hidden"
                    name="token"
                    value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>"
                >

                <label class="block text-gray-700 font-medium mb-2">
                    New Password
                </label>

                <input
                    type="password"
                    name="password"
                    placeholder="Enter new password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                    class="w-full border border-gray-300 rounded-lg px-4 py-3 mb-5
                           focus:outline-none focus:ring-2 focus:ring-orange-400"
                >

                <label class="block text-gray-700 font-medium mb-2">
                    Confirm New Password
                </label>

                <input
                    type="password"
                    name="confirm_password"
                    placeholder="Confirm new password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                    class="w-full border border-gray-300 rounded-lg px-4 py-3 mb-5
                           focus:outline-none focus:ring-2 focus:ring-orange-400"
                >

                <button
                    type="submit"
                    name="reset_password"
                    class="w-full bg-orange-500 hover:bg-orange-600
                           text-white font-semibold py-3 rounded-lg transition">

                    Reset Password

                </button>

            </form>

        <?php endif; ?>


        <div class="text-center mt-6">

            <a href="customer.php"
               class="text-orange-500 hover:underline font-medium">

                Back to Login

            </a>

        </div>

    </div>

</body>
</html>
