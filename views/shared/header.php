<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/app.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="role-<?= htmlspecialchars($user['role'] ?? 'guest') ?>">

<nav class="navbar">
  <div class="nav-brand">
    <span class="nav-logo">⬡</span>
    <span class="nav-name"><?= APP_NAME ?></span>
  </div>

  <div class="nav-links">
    <?php if (!empty($user['id'])): ?>
      <span class="nav-user">
        <i class="fa fa-circle-user"></i>
        <?= htmlspecialchars($user['name']) ?>
        <span class="badge badge-<?= $user['role'] ?>"><?= ucfirst($user['role']) ?></span>
      </span>
      <a href="/auth/logout.php" class="btn btn-ghost btn-sm">
        <i class="fa fa-right-from-bracket"></i> Logout
      </a>
    <?php endif; ?>
  </div>

  <!-- Hamburger Menu Button -->
  <button class="hamburger-menu" id="hamburger-menu" aria-label="Toggle navigation menu">
    <span class="hamburger-line"></span>
    <span class="hamburger-line"></span>
    <span class="hamburger-line"></span>
  </button>
</nav>

<div class="layout">
  <?php if (!empty($user['id'])): ?>
  <!-- Mobile Menu Overlay -->
  <div class="mobile-menu-overlay" id="mobile-menu-overlay"></div>

  <aside class="sidebar" id="sidebar">
    <ul class="sidebar-nav">
      <?php if ($user['role'] === 'lecturer'): ?>
        <li><a href="/lecturer/dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a></li>
        <li><a href="/lecturer/select_courses.php"><i class="fa fa-book"></i> My Courses</a></li>
        <li><a href="/lecturer/create_assignment.php"><i class="fa fa-plus-circle"></i> New Assignment</a></li>
        <li><a href="/lecturer/assignments.php"><i class="fa fa-list-check"></i> Assignments</a></li>
        <li><a href="/lecturer/submissions.php"><i class="fa fa-inbox"></i> All Submissions</a></li>
      <?php else: ?>
        <li><a href="/student/dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a></li>
        <li><a href="/student/courses.php"><i class="fa fa-book"></i> My Courses</a></li>
        <li><a href="/student/assignments.php"><i class="fa fa-book-open"></i> Assignments</a></li>
        <li><a href="/student/submissions.php"><i class="fa fa-file-arrow-up"></i> My Submissions</a></li>
      <?php endif; ?>
    </ul>
  </aside>
  <?php endif; ?>

  <main class="main-content">
    <?php if (!empty($flash)): ?>
      <div class="alert alert-<?= $flash['type'] ?>">
        <i class="fa fa-<?= $flash['type'] === 'success' ? 'circle-check' : 'circle-exclamation' ?>"></i>
        <?= htmlspecialchars($flash['message']) ?>
      </div>
    <?php endif; ?>
