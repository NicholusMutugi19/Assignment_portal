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
  $currentUser = Auth::user();
  if ($currentUser['role'] === 'admin') {
    header('Location: /admin/');
    exit;
  }
  if ($currentUser['role'] === 'tutor') {
    header('Location: /lecturer/dashboard.php');
    exit;
  }
  if ($currentUser['role'] === 'lecturer' && PORTAL_EXTENSIONS_ENABLED
      && User::approvalStatus((int)$currentUser['id']) !== 'approved') {
    header('Location: /lecturer/pending_approval.php');
    exit;
  }
  if (PORTAL_EXTENSIONS_ENABLED && $currentUser['role'] === 'lecturer'
    && User::approvalStatus((int)$currentUser['id']) !== 'approved') {
    header('Location: /lecturer/pending_approval.php');
    exit;
  }
  if (EDUCATION_COURSE_TARGETING_ENABLED && $currentUser['role'] === 'student') {
    $profile = User::educationProfile((int)$currentUser['id']);
    if (!$profile || !$profile['education_level'] || !$profile['institution_name'] || !$profile['year_or_form']) {
      header('Location: /student/education.php');
      exit;
    }
  }
    if (!Auth::hasSelectedCourses()) {
        // Redirect to course selection
        $role = Auth::user()['role'];
        if ($role === 'student') {
            header('Location: /student/courses.php');
        } else {
            header('Location: /lecturer/select_courses.php');
        }
    } else {
        $role = Auth::user()['role'];
      header('Location: ' . ($role === 'admin' ? '/admin/' : '/' . $role . '/dashboard.php'));
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

            if ($user['role'] === 'admin') {
              header('Location: /admin/');
              exit;
            }
            if ($user['role'] === 'tutor') {
              header('Location: /lecturer/dashboard.php');
              exit;
            }

            if (PORTAL_EXTENSIONS_ENABLED && $user['role'] === 'lecturer'
              && User::approvalStatus((int)$user['id']) !== 'approved') {
              header('Location: /lecturer/pending_approval.php');
              exit;
            }

          if (EDUCATION_COURSE_TARGETING_ENABLED && $user['role'] === 'student') {
            $profile = User::educationProfile((int)$user['id']);
            if (!$profile || !$profile['education_level'] || !$profile['institution_name'] || !$profile['year_or_form']) {
              header('Location: /student/education.php');
              exit;
            }
          }

            // Check if course selection is complete
            if (!Auth::hasSelectedCourses()) {
                // Redirect to course selection
                if ($user['role'] === 'student') {
                    header('Location: /student/courses.php');
                } else {
                    header('Location: /lecturer/select_courses.php');
                }
            } else {
              header('Location: ' . ($user['role'] === 'admin' ? '/admin/' : '/' . $user['role'] . '/dashboard.php'));
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
<link rel="stylesheet" href="/css/app.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="auth-body">
<div class="auth-page">
  <div class="auth-card">
    <a class="auth-brand" href="/" aria-label="<?= htmlspecialchars(APP_NAME) ?> home">
      <span class="auth-logo-icon">⬡</span>
      <span><?= APP_NAME ?></span>
    </a>
    <div class="auth-heading">
      <span class="auth-eyebrow">Welcome back</span>
      <h1 class="auth-title">Sign in to your portal</h1>
      <p class="auth-subtitle">Your courses and assignments are waiting.</p>
    </div>

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
    <?php if (!empty($_GET['error']) && $_GET['error'] === 'suspended'): ?>
      <div class="alert alert-error"><i class="fa fa-ban"></i> This account is suspended. Contact an administrator.</div>
    <?php endif; ?>

    <form method="POST" action="" class="auth-form">
      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               placeholder="you@example.com" autocomplete="username" required autofocus>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               placeholder="Enter your password" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-lg auth-submit">
        <i class="fa fa-right-to-bracket"></i> Sign In
      </button>
    </form>

    <div class="auth-footer">
      <p>Don't have an account? <a href="register.php">Register here</a></p>
      <p class="auth-note"><i class="fa fa-shield-halved"></i> Your account information is protected.</p>
    </div>
  </div>
</div>
<script src="/js/app.js"></script>
</body>
</html>
