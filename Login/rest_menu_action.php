<?php
// Restaurant staff: menu actions for rest_menu.php and dashboard.php
//   action=toggle (AJAX)  id, available
//   action=delete (AJAX)  id
//   action=save   (form)  item_id, name, category, price, description, is_veg
session_start();
require_once __DIR__ . '/../db.php';

$isForm = ($_POST['action'] ?? '') === 'save';

function fail(string $msg, bool $isForm, int $code = 400): void
{
    if ($isForm) {
        http_response_code($code);
        echo '<p style="font-family:sans-serif;padding:2rem">' . htmlspecialchars($msg)
           . ' <a href="rest_menu.php">Back to menu</a></p>';
    } else {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode(['ok' => false, 'error' => $msg]);
    }
    exit;
}

if (($_SESSION['role'] ?? '') !== 'restaurant_staff') fail('Not allowed.', $isForm, 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST')            fail('POST only.', $isForm, 405);

$sess = $_SESSION['csrf'] ?? '';
if ($sess === '' || !hash_equals($sess, $_POST['csrf'] ?? '')) fail('Session expired. Reload the page.', $isForm, 403);

$action = $_POST['action'] ?? '';

try {
    if ($action === 'toggle') {
        $pdo->prepare('UPDATE food SET available = ? WHERE id = ?')
            ->execute([empty($_POST['available']) ? 0 : 1, (int)($_POST['id'] ?? 0)]);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM food WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'save') {
        $id    = (int)($_POST['item_id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $cat   = trim($_POST['category'] ?? '');
        $price = $_POST['price'] ?? '';
        $desc  = trim($_POST['description'] ?? '');
        $veg   = isset($_POST['is_veg']) ? 1 : 0;

        if ($name === '' || mb_strlen($name) > 100)        fail('Enter a dish name (up to 100 characters).', true);
        if (!is_numeric($price) || $price < 0 || $price > 100000) fail('Enter a valid price.', true);
        if (mb_strlen($desc) > 255)                        fail('Description must be within 255 characters.', true);

        $c = $pdo->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
        $c->execute([$cat]);
        $catId = $c->fetchColumn();
        if (!$catId) fail('Please choose an existing category.', true);

        if ($id > 0) {
            $pdo->prepare('UPDATE food SET category_id = ?, name = ?, description = ?, price = ?, is_veg = ? WHERE id = ?')
                ->execute([$catId, $name, $desc, $price, $veg, $id]);
        } else {
            $pdo->prepare('INSERT INTO food (category_id, name, description, price, is_veg, available) VALUES (?, ?, ?, ?, ?, 1)')
                ->execute([$catId, $name, $desc, $price, $veg]);
        }
        header('Location: rest_menu.php');
        exit;
    }

    fail('Unknown action.', $isForm);
} catch (PDOException $e) {
    error_log($e->getMessage());
    fail('Database error.', $isForm, 500);
}
