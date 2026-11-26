
<?php
session_start();

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageType = "error";
    } else {

        try {
            // Find customer using their registered email
            $stmt = $pdo->prepare(
                "SELECT user_id, full_name, email
                 FROM users
                 WHERE email = ? AND role = 'customer'
                 LIMIT 1"
            );

            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {

                // Generate a secure random token
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);

                

                // Remove previous reset requests for this user
                $delete = $pdo->prepare(
                    "DELETE FROM password_resets WHERE user_id = ?"
                );
                $delete->execute([$user['user_id']]);

                $insert = $pdo->prepare(
                "INSERT INTO password_resets
                (user_id, token_hash, expires_at)
                 VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");

            $insert->execute([$user['user_id'],$tokenHash]);

                // Create reset link
                $resetLink = "http://localhost/FoodFlow/Login/reset_pass.php?token="
                           . urlencode($token);

                // Configure PHPMailer
                $mail = new PHPMailer(true);

                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;

                // Replace with the Gmail account used for the App Password
                $mail->Username   = 'aggdivya091@gmail.com';

                // Replace with your Google 16-character App Password
                $mail->Password   = 'wjlm otly jrtc nmpd';

                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // Email details
                $mail->setFrom(
                    'aggdivya091@gmail.com',
                    'FoodFlow'
                );

                $mail->addAddress($user['email'], $user['full_name']);

                $mail->isHTML(true);
                $mail->Subject = 'FoodFlow - Password Reset';

                $mail->Body = "
                    <h2>Hello " . htmlspecialchars($user['full_name']) . ",</h2>

                    <p>We received a request to reset your FoodFlow password.</p>

                    <p>Click the button below to create a new password:</p>

                    <p>
                        <a href='$resetLink'
                           style='background:#f97316;
                                  color:white;
                                  padding:12px 20px;
                                  text-decoration:none;
                                  border-radius:6px;'>
                            Reset Password
                        </a>
                    </p>

                    <p>This link expires in <b>15 minutes</b>.</p>

                    <p>If you did not request this, you can ignore this email.</p>

                    <p>Regards,<br>FoodFlow Team</p>
                ";

                $mail->AltBody =
                    "Reset your FoodFlow password using this link: $resetLink
                     This link expires in 15 minutes.";

                $mail->send();
            }

            // Same message whether the email exists or not
            $message = "If an account exists with this email, a password reset link has been sent.";
            $messageType = "success";

        } catch (Exception $e) {

            // Remove the reset token if email sending fails
            if (isset($user) && $user) {
                $delete = $pdo->prepare(
                    "DELETE FROM password_resets WHERE user_id = ?"
                );
                $delete->execute([$user['user_id']]);
            }

            error_log("FoodFlow email error: " . $e->getMessage());

            $message = "Unable to send the reset email right now. Please try again later.";
            $messageType = "error";

        } catch (PDOException $e) {

            error_log("FoodFlow database error: " . $e->getMessage());

            $message = "Something went wrong. Please try again later.";
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
    <title>Forgot Password | FoodFlow</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen flex items-center justify-center bg-[#fffaf5] p-4">

    <div class="w-full max-w-md bg-white rounded-2xl shadow-lg p-8">

        <h1 class="text-3xl font-bold text-gray-800 text-center mb-3">
            Forgot Password?
        </h1>

        <p class="text-gray-500 text-center mb-6">
            Enter your registered email address and we'll send you a password reset link.
        </p>

        <?php if ($message !== ""): ?>
            <div class="mb-5 p-3 rounded-lg text-sm
                <?php echo $messageType === 'success'
                    ? 'bg-green-100 text-green-700'
                    : 'bg-red-100 text-red-700'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">

            <label class="block text-gray-700 font-medium mb-2">
                Email Address
            </label>

            <input
                type="email"
                name="email"
                placeholder="Enter your registered email"
                required
                class="w-full border border-gray-300 rounded-lg px-4 py-3
                       focus:outline-none focus:ring-2 focus:ring-orange-400 mb-5"
            >

            <button
                type="submit"
                class="w-full bg-orange-500 hover:bg-orange-600
                       text-white font-semibold py-3 rounded-lg transition">
                Send Reset Link
            </button>

        </form>

        <div class="text-center mt-6">
            <a href="customer.php"
               class="text-orange-500 hover:underline font-medium">
                Back to Login
            </a>
        </div>

    </div>

</body>
</html>
