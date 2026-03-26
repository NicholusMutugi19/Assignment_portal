<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/User.php';

Auth::start();
if (Auth::isLoggedIn()) {
    header('Location: /' . Auth::user()['role'] . '/dashboard.php');
    exit;
}

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = in_array($_POST['role'] ?? '', ['student','lecturer']) ? $_POST['role'] : 'student';

    if (!$name || !$email || !$password) {
        $error = 'All fields are required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (User::findByEmail($email)) {
        $error = 'An account with that email already exists.';
    } else {
        $userId = User::create(compact('name','email','password','role'));

        // Log the user in automatically
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = $role;

        // Redirect to course selection
        if ($role === 'student') {
            header('Location: /student/courses.php');
        } else {
            header('Location: /lecturer/select_courses.php');
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/app.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo"><div class="auth-logo-icon">⬡</div></div>
    <h1 class="auth-title">Create Account</h1>
    <p class="auth-subtitle"><?= APP_NAME ?></p>

    <?php if ($error): ?>
      <div class="alert alert-error"><i class="fa fa-circle-exclamation"></i> <?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><i class="fa fa-circle-check"></i> <?= $success ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="Enter your full name" required>
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="Enter your email address" required>
      </div>
      <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="Min 6 characters" required>
      </div>
      <div class="form-group">
        <label>Role</label>
        <select name="role">
          <option value="student"  <?= ($_POST['role']??'student')==='student'  ? 'selected' : '' ?>>Student</option>
          <option value="lecturer" <?= ($_POST['role']??'')==='lecturer' ? 'selected' : '' ?>>Lecturer</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary btn-lg" style="width:100%">
        <i class="fa fa-user-plus"></i> Create Account
      </button>
    </form>

    <div class="auth-footer">Already have an account? <a href="login.php">Sign in</a></div>
  </div>
</div>
</body>
</html>
