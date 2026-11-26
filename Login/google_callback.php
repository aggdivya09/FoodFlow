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
    'http://localhost/FoodFlow/Login/google_callback.php'
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
        $_SESSION['role'] = $customer['role'];

        header('Location: ../Dashboard/customer.php');
        exit;

    } else {
        exit(
            'No FoodFlow customer account found with this Google email. '
            . 'Please sign up first.'
        );
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    exit("No FoodFlow customer account found with this Google email. Please sign up first");
}
?>