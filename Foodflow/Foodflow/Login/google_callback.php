<?php
session_start();

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../vendor/autoload.php';

if (
    !isset($_GET['state'], $_SESSION['google_oauth_state']) ||
    !hash_equals($_SESSION['google_oauth_state'], $_GET['state'])
) {
    exit('Invalid Google login request.');
}

unset($_SESSION['google_oauth_state']);

if (isset($_GET['error'])) {
    exit('Google login was cancelled or failed.');
}

if (!isset($_GET['code'])) {
    exit('Google authorization code not received.');
}

$client = new Google\Client();

$client->setAuthConfig(__DIR__ . '/../google-oauth.json');

$client->setRedirectUri(
    'http://localhost/Foodflow/Foodflow/Login/google_callback.php'
);

$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

if (isset($token['error'])) {
    exit('Unable to authenticate with Google.');
}

$client->setAccessToken($token);

$googleService = new Google\Service\Oauth2($client);
$googleUser = $googleService->userinfo->get();

$email = $googleUser->email;
$name = $googleUser->name;

// Only accept verified Google email addresses.
if (!$googleUser->verifiedEmail) {
    exit('Please use a verified Google email address.');
}

try {
    $stmt = $pdo->prepare(
        "SELECT user_id, full_name, email, role
         FROM users
         WHERE email = ? AND role = 'customer'
         LIMIT 1"
    );

    $stmt->execute([$email]);
    $customer = $stmt->fetch();

    if ($customer) {

        session_regenerate_id(true);

        $_SESSION['user_id'] = $customer['user_id'];
        $_SESSION['full_name'] = $customer['full_name'];
        $_SESSION['email'] = $customer['email'];
        $_SESSION['role'] = $customer['role'];

        header('Location: ../dashboard/customer.php');
        exit;

    } else {
        // Auto-register new Google user as customer
        $pw = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $ins = $pdo->prepare(
            "INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, 'customer')"
        );
        $ins->execute([$name, $email, $pw]);
        $customer = [
            'user_id'   => (int)$pdo->lastInsertId(),
            'full_name' => $name,
            'email'     => $email,
            'role'      => 'customer',
        ];

        session_regenerate_id(true);

        $_SESSION['user_id']   = $customer['user_id'];
        $_SESSION['full_name'] = $customer['full_name'];
        $_SESSION['email']     = $customer['email'];
        $_SESSION['role']      = $customer['role'];

        header('Location: ../dashboard/customer.php');
        exit;
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    exit("No FoodFlow customer account found with this Google email. Please sign up first");
}
?>