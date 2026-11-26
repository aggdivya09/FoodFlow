<?php
session_start();
if (($_SESSION['role'] ?? '') === 'restaurant_staff') {
    header('Location: rest_reports.php');
} else {
    header('Location: admin_reports.php');
}
exit;
