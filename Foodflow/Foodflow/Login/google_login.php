<?php
session_start();

require_once __DIR__ . '/../vendor/autoload.php';

$client = new Google\Client();

$client->setAuthConfig(__DIR__ . '/../google-oauth.json');

$client->setRedirectUri(
    'http://localhost/Foodflow/Foodflow/Login/google_callback.php'
);

$client->addScope('email');
$client->addScope('profile');

$client->setPrompt('select_account');

$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;
$client->setState($state);

header('Location: ' . $client->createAuthUrl());
exit;
?>