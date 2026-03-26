<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/Assignment.php';

Auth::requireRole('lecturer', APP_URL . '/auth/login.php');
$user        = Auth::user();
$assignments = Assignment::forLecturer((int)$user['id']);

$pageTitle = 'My Assignments';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<?php include __DIR__ . '/../../views/shared/header.php'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">My Assignments</h1>
    <p class="page-subtitle"><?= count($assignments) ?> assignment<?= count($assignments) !== 1 ? 's':'' ?> total</p>
  </div>
  <a href="<?= APP_URL ?>/lecturer/create_assignment.php" class="btn btn-primary">
    <i class="fa fa-plus"></i> New Assignment
  </a>
</div>

<?php if (empty($assignments)): ?>
  <div class="empty-state">
    <div class="empty-state-icon"><i class="fa fa-folder-open"></i></div>
    <h3>No assignments yet</h3>
    <p>Create your first assignment to get started.</p>
    <a href="<?= APP_URL ?>/lecturer/create_assignment.php" class="btn btn-primary mt-2">
      <i class="fa fa-plus"></i> Create Assignment
    </a>
  </div>
<?php else: ?>
  <div class="assignment-grid">
    <?php foreach ($assignments as $a):
      $isPast  = strtotime($a['deadline']) < time();
      $diff    = strtotime($a['deadline']) - time();
      $pillCls = $isPast ? 'past' : ($diff < 86400*2 ? 'urgent' : ($diff < 86400*7 ? 'soon' : 'plenty'));
    ?>
    <div class="assignment-card">
      <div class="d-flex justify-between align-center">
        <span class="assignment-course"><?= htmlspecialchars($a['course_code']) ?></span>
        <span class="badge badge-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span>
      </div>
      <h3 class="assignment-title"><?= htmlspecialchars($a['title']) ?></h3>
      <div class="deadline-pill <?= $pillCls ?>">
        <i class="fa fa-clock"></i>
        <?= $isPast ? 'Closed ' : '' ?><?= date('M j, Y H:i', strtotime($a['deadline'])) ?>
      </div>
      <div class="assignment-meta">
        <span><i class="fa fa-inbox"></i> <?= $a['total_submissions'] ?> submitted</span>
        <span><i class="fa fa-star"></i>  <?= $a['graded_count'] ?> graded</span>
        <span><i class="fa fa-trophy"></i> Max <?= $a['max_score'] ?></span>
      </div>
      <div class="d-flex gap-1 mt-1">
        <a href="<?= APP_URL ?>/lecturer/submissions.php?assignment_id=<?= $a['id'] ?>"
           class="btn btn-ghost btn-sm" style="flex:1;justify-content:center">
          <i class="fa fa-eye"></i> View Submissions
        </a>
        <a href="<?= APP_URL ?>/lecturer/edit_assignment.php?id=<?= $a['id'] ?>"
           class="btn btn-ghost btn-sm">
          <i class="fa fa-pen"></i>
        </a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../../views/shared/footer.php'; ?>
