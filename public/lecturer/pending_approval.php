<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
Auth::requireLogin('/auth/login.php');
$user = Auth::user();
if ($user['role'] !== 'lecturer') {
    header('Location: /auth/login.php?error=unauthorized');
    exit;
}
$pageTitle = 'Lecturer application status';
?>
<?php include __DIR__ . '/../../views/shared/header.php'; ?>
<div class="empty-state"><div class="empty-state-icon"><i class="fa fa-user-clock"></i></div><h1>Lecturer access pending</h1><p>Your lecturer application must be approved by an administrator before you can use lecturer tools. You can still sign in to check this page later.</p><a class="btn btn-ghost" href="/auth/logout.php">Sign out</a></div>
<?php include __DIR__ . '/../../views/shared/footer.php'; ?>
