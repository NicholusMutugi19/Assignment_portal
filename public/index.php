<?php
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/middleware/Auth.php';

Auth::start();

if (Auth::isLoggedIn()) {
    $role = Auth::user()['role'];
    header('Location: ' . APP_URL . '/' . $role . '/dashboard.php');
} else {
    header('Location: ' . APP_URL . '/auth/login.php');
}
exit;
