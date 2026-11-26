<?php
require_once 'db.php';

$email = 'staff@foodflow.com';
$newPassword = 'FoodFlow@123';

$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

$sql = "UPDATE users
        SET password_hash = ?
        WHERE email = ?
        AND role = 'restaurant_staff'";

$stmt = $pdo->prepare($sql);
$stmt->execute([$newHash, $email]);

if ($stmt->rowCount() > 0) {
    echo "Restaurant staff password updated successfully!";
} else {
    echo "No account updated. Check the email and role.";
}
?>