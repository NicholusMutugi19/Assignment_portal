<?php
/**
 * Login Page
 * assignment_portal/public/auth/login.php
 */

require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/User.php';

Auth::start();

// Already logged in
if (Auth::isLoggedIn()) {
    if (!Auth::hasSelectedCourses()) {
        // Redirect to course selection
        $role = Auth::user()['role'];
        if ($role === 'student') {
            header('Location: ' . APP_URL . '/student/courses.php');
        } else {
            header('Location: ' . APP_URL . '/lecturer/select_courses.php');
        }
    } else {
        $role = Auth::user()['role'];
        header('Location: ' . APP_URL . '/' . $role . '/dashboard.php');
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $error = 'Please enter both email and password.';
    } else {
        $user = User::findByEmail($email);
        if ($user && User::verifyPassword($password, $user['password'])) {
            Auth::login($user);

            // Check if course selection is complete
            if (!Auth::hasSelectedCourses()) {
                // Redirect to course selection
                if ($user['role'] === 'student') {
                    header('Location: ' . APP_URL . '/student/courses.php');
                } else {
                    header('Location: ' . APP_URL . '/lecturer/select_courses.php');
                }
            } else {
                header('Location: ' . APP_URL . '/' . $user['role'] . '/dashboard.php');
            }
            exit;
        } else {
            $error = 'Invalid credentials. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/css/app.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <div class="auth-logo-icon">⬡</div>
    </div>
    <h1 class="auth-title"><?= APP_NAME ?></h1>
    <p class="auth-subtitle">Sign in to access your dashboard</p>

    <?php if ($error): ?>
      <div class="alert alert-error">
        <i class="fa fa-circle-exclamation"></i>
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($_GET['error']) && $_GET['error'] === 'unauthorized'): ?>
      <div class="alert alert-warning">
        <i class="fa fa-triangle-exclamation"></i>
        You are not authorised to access that page.
      </div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               placeholder="Input Your Email" required autofocus>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-primary btn-lg" style="width:100%; margin-top:.5rem;">
        <i class="fa fa-right-to-bracket"></i> Sign In
      </button>
    </form>

    <div class="auth-footer">
      <p>Don't have an account? <a href="register.php">Register here</a></p>
      <hr class="divider" style="margin:1rem 0">
      <p style="font-size:.78rem; color:var(--text-dim);">
        Demo — Lecturer: <code>lecturer@portal.ac.ke</code> |
        Student: <code>alice@student.ac.ke</code><br>
        Password: <code>password123</code>
      </p>
    </div>
  </div>
</div>
<script src="<?= APP_URL ?>/js/app.js"></script>
</body>
</html>
