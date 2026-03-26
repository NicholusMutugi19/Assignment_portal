<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
Auth::logout();
header('Location: /auth/login.php');
exit;
