<?php
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/middleware/Auth.php';

Auth::start();

if (Auth::isLoggedIn()) {
    $role = Auth::user()['role'];
    header('Location: /' . $role . '/dashboard.php');
} else {
    header('Location: /auth/login.php');
}
exit;
