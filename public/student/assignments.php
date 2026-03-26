<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/Assignment.php';

Auth::requireRole('student', APP_URL . '/auth/login.php');
$user        = Auth::user();
$assignments = Assignment::forStudent((int)$user['id']);

// Filter assignments based on submission status
$filter = $_GET['filter'] ?? 'all';
$filtered = match($filter) {
    'pending'   => array_filter($assignments, fn($a) => !$a['submission_id'] && $a['display_status'] === 'pending'),
    'submitted' => array_filter($assignments, fn($a) => $a['submission_id'] && $a['submission_status'] !== 'graded'),
    'graded'    => array_filter($assignments, fn($a) => $a['submission_status'] === 'graded'),
    'late'      => array_filter($assignments, fn($a) => $a['display_status'] === 'late'),
    default     => $assignments,
};

$pageTitle = 'My Assignments';
$flash = null;
?>
<?php include __DIR__ . '/../../views/shared/header.php'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Assignments</h1>
    <p class="page-subtitle"><?= count($assignments) ?> total across all enrolled courses</p>
  </div>
</div>

<!-- Filter tabs -->
<div class="d-flex gap-1 mb-2 flex-wrap">
  <?php foreach (['all','pending','submitted','graded','late'] as $f): ?>
    <a href="?filter=<?= $f ?>"
       class="btn btn-sm <?= $filter === $f ? 'btn-primary' : 'btn-ghost' ?>">
      <?= ucfirst($f) ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if (empty($filtered)): ?>
  <div class="empty-state">
    <div class="empty-state-icon"><i class="fa fa-filter"></i></div>
    <h3>No assignments in this filter</h3>
    <p><a href="?filter=all">View all assignments</a></p>
  </div>
<?php else: ?>
  <div class="assignment-grid">
    <?php foreach ($filtered as $a):
      $diff    = strtotime($a['deadline']) - time();
      $isPast  = $diff <= 0;
      $pillCls = $isPast ? 'past' : ($diff < 3600*6 ? 'urgent' : ($diff < 86400*3 ? 'soon' : 'plenty'));
    ?>
    <div class="assignment-card">
      <div class="d-flex justify-between align-center">
        <span class="assignment-course"><?= htmlspecialchars($a['course_code']) ?></span>
        <?php
          $ds = $a['submission_id'] ? ($a['submission_status'] ?? 'submitted') : $a['display_status'];
          $bc = match($ds) {
            'graded'  => 'success', 'submitted' => 'info',
            'pending' => 'pending', 'late'       => 'warning',
            'closed'  => 'danger',  default      => 'pending'
          };
        ?>
        <span class="badge badge-<?= $bc ?>"><?= ucfirst($ds) ?></span>
      </div>

      <h3 class="assignment-title"><?= htmlspecialchars($a['title']) ?></h3>
      <p class="assignment-desc"><?= htmlspecialchars($a['description']) ?></p>

      <?php if (!empty($a['attachment_path'])): ?>
        <div class="assignment-attachment">
          <a href="<?= APP_URL ?>/student/download.php?assignment_id=<?= $a['id'] ?>" 
             class="btn btn-sm btn-outline" target="_blank">
            <i class="fa fa-download"></i> Download Assignment File
          </a>
          <span class="text-muted" style="font-size:.8rem;margin-left:.5rem">
            <?= htmlspecialchars($a['attachment_name']) ?>
          </span>
        </div>
      <?php endif; ?>

      <div class="deadline-pill <?= $pillCls ?>">
        <i class="fa fa-clock"></i>
        <?= date('M j, Y H:i', strtotime($a['deadline'])) ?>
      </div>

      <div class="assignment-meta">
        <span><i class="fa fa-user-tie"></i> <?= htmlspecialchars($a['lecturer_name']) ?></span>
        <span><i class="fa fa-trophy"></i> <?= $a['max_score'] ?> pts</span>
      </div>

      <?php if ($a['submission_status'] === 'graded'): ?>
        <div style="background:var(--green-bg);border-radius:var(--radius);padding:.75rem;text-align:center">
          <div style="font-size:1.5rem;font-family:var(--font-head);font-weight:800;color:var(--green)">
            <?= $a['submission_score'] ?> <span style="font-size:.9rem;color:var(--text-muted)">/ <?= $a['max_score'] ?></span>
          </div>
          <div class="text-muted" style="font-size:.78rem">Your Grade</div>
        </div>
      <?php elseif ($a['submission_id']): ?>
        <div class="alert alert-info" style="margin:0">
          <i class="fa fa-circle-check"></i> Submitted <?= date('M j, Y', strtotime($a['submitted_at'])) ?>
        </div>
      <?php elseif (in_array($a['display_status'], ['pending','late'])): ?>
        <a href="<?= APP_URL ?>/student/submit.php?assignment_id=<?= $a['id'] ?>"
           class="btn btn-primary" style="width:100%;justify-content:center">
          <i class="fa fa-file-arrow-up"></i>
          <?= $a['display_status']==='late' ? 'Submit Late' : 'Submit Work' ?>
        </a>
      <?php else: ?>
        <div class="btn btn-ghost" style="width:100%;justify-content:center;opacity:.5;cursor:default">
          <i class="fa fa-lock"></i> Closed
        </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../../views/shared/footer.php'; ?>
