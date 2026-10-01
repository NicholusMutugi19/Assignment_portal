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
        <li><a href="/lecturer/courses.php"><i class="fa fa-book"></i> My Courses</a></li>
        <li><a href="/lecturer/select_courses.php"><i class="fa fa-list"></i> Teaching Catalog</a></li>
        <li><a href="/lecturer/create_assignment.php"><i class="fa fa-plus-circle"></i> New Assignment</a></li>
        <li><a href="/lecturer/assignments.php"><i class="fa fa-list-check"></i> Assignments</a></li>
        <li><a href="/lecturer/submissions.php"><i class="fa fa-inbox"></i> All Submissions</a></li>
        <?php if (PORTAL_EXTENSIONS_ENABLED): ?>
        <li><a href="/lecturer/create_online_assignment.php"><i class="fa fa-list-check"></i> New Online Assignment</a></li>
        <li><a href="/lecturer/class_sessions.php"><i class="fa fa-video"></i> Class Sessions</a></li>
        <li><a href="/lecturer/results.php"><i class="fa fa-ranking-star"></i> Results</a></li>
        <li><a href="/lecturer/applications.php"><i class="fa fa-user-check"></i> Course Applications</a></li>
        <?php endif; ?>
      <?php elseif ($user['role'] === 'tutor' && PORTAL_EXTENSIONS_ENABLED): ?>
        <li><a href="/lecturer/dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a></li>
        <li><a href="/lecturer/courses.php"><i class="fa fa-book"></i> Assigned Courses</a></li>
        <li><a href="/lecturer/assignments.php"><i class="fa fa-list-check"></i> Assignments</a></li>
        <li><a href="/lecturer/submissions.php"><i class="fa fa-inbox"></i> Submissions</a></li>
        <li><a href="/lecturer/create_assignment.php"><i class="fa fa-file-arrow-up"></i> New Upload Assignment</a></li>
        <li><a href="/lecturer/create_online_assignment.php"><i class="fa fa-list-check"></i> New Online Assignment</a></li>
        <li><a href="/lecturer/class_sessions.php"><i class="fa fa-video"></i> Class Sessions</a></li>
        <li><a href="/lecturer/results.php"><i class="fa fa-ranking-star"></i> Results</a></li>
        <li><a href="/lecturer/applications.php"><i class="fa fa-user-check"></i> Course Applications</a></li>
      <?php elseif ($user['role'] === 'admin'): ?>
        <li><a href="/admin/"><i class="fa fa-shield-halved"></i> Admin Dashboard</a></li>
        <?php if (PORTAL_EXTENSIONS_ENABLED): ?>
        <li><a href="/admin/#lecturer-approvals"><i class="fa fa-user-check"></i> Lecturer Approvals</a></li>
        <li><a href="/admin/#users"><i class="fa fa-users"></i> Users</a></li>
        <li><a href="/admin/#courses"><i class="fa fa-book"></i> Courses &amp; Fees</a></li>
        <li><a href="/admin/#payments"><i class="fa fa-money-bill-wave"></i> Payments</a></li>
        <li><a href="/admin/#site-controls"><i class="fa fa-sliders"></i> Site Controls</a></li>
        <li><a href="/admin/#audit-log"><i class="fa fa-shield-halved"></i> Audit Log</a></li>
        <?php endif; ?>
      <?php else: ?>
        <li><a href="/student/dashboard.php"><i class="fa fa-gauge"></i> Dashboard</a></li>
        <li><a href="/student/courses.php"><i class="fa fa-book"></i> My Courses</a></li>
        <li><a href="/student/assignments.php"><i class="fa fa-book-open"></i> Assignments</a></li>
        <li><a href="/student/submissions.php"><i class="fa fa-file-arrow-up"></i> My Submissions</a></li>
        <?php if (PORTAL_EXTENSIONS_ENABLED): ?>
        <?php if (EDUCATION_COURSE_TARGETING_ENABLED): ?><li><a href="/student/education.php"><i class="fa fa-graduation-cap"></i> Education Profile</a></li><?php endif; ?>
        <li><a href="/student/results.php"><i class="fa fa-ranking-star"></i> My Results</a></li>
        <li><a href="/student/payments.php"><i class="fa fa-money-bill-wave"></i> Payments</a></li>
        <?php endif; ?>
      <?php endif; ?>
      <li class="sidebar-divider"></li>
      <li><a href="/auth/logout.php" class="logout-link"><i class="fa fa-right-from-bracket"></i> Logout</a></li>
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
