<?php
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/middleware/Auth.php';

Auth::start();

if (Auth::isLoggedIn()) {
    $role = Auth::user()['role'];
    $destination = match ($role) {
        'admin' => '/admin/',
        'tutor' => '/lecturer/courses.php',
        default => '/' . $role . '/dashboard.php',
    };
    header('Location: ' . $destination);
} else {
    header('Location: /auth/login.php');
}
exit;
