<?php
session_start();

// Remember who was logged in so we can send them to the right login page
$role = $_SESSION['role'] ?? '';

// Clear all session data
$_SESSION = [];

// Delete the session cookie
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $p['path'],
        $p['domain'],
        $p['secure'],
        $p['httponly']
    );
}

// Destroy the session on the server
session_destroy();

// Stop browsers from showing cached dashboard pages after logout
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Send each type of staff back to their own login page
switch ($role) {
    case 'restaurant_staff':
        $redirect = 'Login/restaurant.php';
        break;
    case 'delivery_person':
        $redirect = 'Login/delivery.php';
        break;
    case 'admin':
        $redirect = 'Login/admin.php';
        break;
    default:
        $redirect = 'Login/select_role.html';
}

header('Location: ' . $redirect);
exit;
