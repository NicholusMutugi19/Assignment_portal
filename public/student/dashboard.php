<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/Assignment.php';
require_once __DIR__ . '/../../src/models/Submission.php';
require_once __DIR__ . '/../../src/models/User.php';

Auth::requireRole('student', APP_URL . '/auth/login.php');
$user        = Auth::user();
$assignments = Assignment::forStudent((int)$user['id']);
$submissions = Submission::forStudent((int)$user['id']);
$courses     = User::enrolledCourses((int)$user['id']);

$pending   = count(array_filter($assignments, fn($a) => !$a['submission_id'] && $a['display_status'] === 'pending'));
$submitted = count(array_filter($assignments, fn($a) => $a['submission_id'] && $a['submission_status'] !== 'graded'));
$graded    = count(array_filter($assignments, fn($a) => $a['submission_status'] === 'graded'));
$late      = count(array_filter($assignments, fn($a) => $a['display_status'] === 'late'));

$pageTitle = 'Student Dashboard';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<?php include __DIR__ . '/../../views/shared/header.php'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      Welcome, <?= htmlspecialchars(explode(' ',$user['name'])[0]) ?> 👋
    </h1>
    <p class="page-subtitle">You are enrolled in <?= count($courses) ?> course<?= count($courses)!==1?'s':'' ?></p>
  </div>
  <a href="<?= APP_URL ?>/student/courses.php" class="btn btn-secondary">
    <i class="fa fa-edit"></i> Manage Courses
  </a>
</div>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-number text-yellow"><?= $pending ?></div>
    <div class="stat-label">Pending</div>
    <i class="fa fa-hourglass stat-icon"></i>
  </div>
  <div class="stat-card">
    <div class="stat-number text-blue"><?= $submitted ?></div>
    <div class="stat-label">Submitted</div>
    <i class="fa fa-paper-plane stat-icon"></i>
  </div>
  <div class="stat-card">
    <div class="stat-number text-green"><?= $graded ?></div>
    <div class="stat-label">Graded</div>
    <i class="fa fa-star stat-icon"></i>
  </div>
  <div class="stat-card">
    <div class="stat-number text-red"><?= $late ?></div>
    <div class="stat-label">Late (open)</div>
    <i class="fa fa-triangle-exclamation stat-icon"></i>
  </div>
</div>

<!-- Upcoming / active assignments -->
<div class="card">
  <div class="card-header">
    <h2 class="card-title"><i class="fa fa-book-open text-accent"></i> &nbsp;Active Assignments</h2>
    <a href="<?= APP_URL ?>/student/assignments.php" class="btn btn-ghost btn-sm">View All</a>
  </div>

  <?php
  $active = array_filter($assignments, fn($a) =>
      !in_array($a['display_status'], ['submitted']) &&
      $a['submission_status'] !== 'graded'
  );
  ?>

  <?php if (empty($active)): ?>
    <div class="empty-state">
      <div class="empty-state-icon"><i class="fa fa-party-horn"></i></div>
      <h3>All caught up!</h3>
      <p>No pending assignments right now.</p>
    </div>
  <?php else: ?>
  <div class="assignment-grid">
    <?php foreach ($active as $a):
      $diff    = strtotime($a['deadline']) - time();
      $isPast  = $diff <= 0;
      $pillCls = $isPast ? 'past' : ($diff < 3600*6 ? 'urgent' : ($diff < 86400*3 ? 'soon' : 'plenty'));
    ?>
    <div class="assignment-card">
      <div class="d-flex justify-between align-center">
        <span class="assignment-course"><?= htmlspecialchars($a['course_code']) ?></span>
        <?php
          $badgeCls = match($a['display_status']) {
            'pending' => 'pending', 'late' => 'warning', 'closed' => 'danger', default => 'info'
          };
        ?>
        <span class="badge badge-<?= $badgeCls ?>">
          <?= ucfirst($a['display_status']) ?>
        </span>
      </div>
      <h3 class="assignment-title"><?= htmlspecialchars($a['title']) ?></h3>
      <p class="assignment-desc"><?= htmlspecialchars($a['description']) ?></p>
      <div class="deadline-pill <?= $pillCls ?>">
        <i class="fa fa-clock"></i>
        <span data-deadline="<?= date('c', strtotime($a['deadline'])) ?>">
          <?= Assignment::timeRemaining($a) ?>
        </span>
      </div>
      <div class="assignment-meta">
        <span><i class="fa fa-user-tie"></i> <?= htmlspecialchars($a['lecturer_name']) ?></span>
        <span><i class="fa fa-trophy"></i> <?= $a['max_score'] ?> pts</span>
        <?php if ($a['allow_late']): ?>
          <span class="text-yellow"><i class="fa fa-clock-rotate-left"></i> Late allowed (−<?= $a['late_penalty'] ?>%)</span>
        <?php endif; ?>
      </div>
      <?php if (in_array($a['display_status'], ['pending','late'])): ?>
        <a href="<?= APP_URL ?>/student/submit.php?assignment_id=<?= $a['id'] ?>"
           class="btn btn-primary" style="width:100%;justify-content:center">
          <i class="fa fa-file-arrow-up"></i> Submit Work
        </a>
      <?php elseif ($a['display_status'] === 'closed'): ?>
        <div class="btn btn-ghost" style="width:100%;justify-content:center;opacity:.5;cursor:default">
          <i class="fa fa-lock"></i> Submissions Closed
        </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Recent grades -->
<?php
$graded_subs = array_filter($submissions, fn($s) => $s['status'] === 'graded');
if (!empty($graded_subs)):
?>
<div class="card" style="margin-top:1.5rem">
  <div class="card-header">
    <h2 class="card-title"><i class="fa fa-star text-accent"></i> &nbsp;Recent Grades</h2>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Assignment</th><th>Course</th><th>Score</th><th>Submitted</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach (array_slice(array_values($graded_subs), 0, 5) as $s): ?>
        <tr>
          <td class="fw-700"><?= htmlspecialchars($s['assignment_title']) ?></td>
          <td><span class="badge badge-info"><?= htmlspecialchars($s['course_code']) ?></span></td>
          <td>
            <span class="score-display text-green"><?= $s['score'] ?></span>
            <span class="score-max"> / <?= $s['max_score'] ?></span>
            <span class="text-muted"> (<?= round(($s['score']/$s['max_score'])*100) ?>%)</span>
          </td>
          <td class="text-muted"><?= date('M j, Y', strtotime($s['submitted_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../views/shared/footer.php'; ?>
