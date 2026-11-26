<?php
/**
 * FoodFlow Database Connection
 * Automatically detects standard MySQL port (3306 or 3307)
 */

$host     = getenv('DB_HOST') ?: '127.0.0.1';
$dbname   = getenv('DB_NAME') ?: 'foodflow';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

// Try 3306 first, fallback to 3307 (or use explicit env DB_PORT)
$preferredPort = getenv('DB_PORT') ? (int)getenv('DB_PORT') : 3306;
$portsToTry = $preferredPort === 3307 ? [3307, 3306] : [3306, 3307];

$pdo = null;
$lastException = null;

foreach ($portsToTry as $port) {
    try {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        break; // Connected successfully
    } catch (PDOException $e) {
        $lastException = $e;
    }
}

if (!$pdo) {
    // If database does not exist yet, attempt connecting without dbname to create it
    foreach ($portsToTry as $port) {
        try {
            $rawPdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $rawPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $username, $password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            break;
        } catch (PDOException $ex) {
            $lastException = $ex;
        }
    }
}

if (!$pdo) {
    error_log("Database connection failed: " . ($lastException ? $lastException->getMessage() : 'Unknown error'));
    die("Database connection failed: " . ($lastException ? htmlspecialchars($lastException->getMessage(), ENT_QUOTES, 'UTF-8') : 'Please ensure MySQL is running in XAMPP.'));
}
?>
