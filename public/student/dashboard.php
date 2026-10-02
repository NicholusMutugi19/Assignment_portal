<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/Assignment.php';
require_once __DIR__ . '/../../src/models/Submission.php';
require_once __DIR__ . '/../../src/models/User.php';
require_once __DIR__ . '/../../src/models/ClassSession.php';
require_once __DIR__ . '/../../src/models/Admin.php';

Auth::requireRole('student', '/auth/login.php');
$user        = Auth::user();
$assignments = Assignment::forStudent((int)$user['id']);
$submissions = Submission::forStudent((int)$user['id']);
$courses     = User::enrolledCourses((int)$user['id']);
$upcomingSessions = PORTAL_EXTENSIONS_ENABLED ? ClassSession::forStudent((int)$user['id']) : [];
$applications = PORTAL_EXTENSIONS_ENABLED ? User::courseApplicationStatuses((int)$user['id']) : [];
$pendingApplications = count(array_filter($applications, fn($application) => $application['application_status'] === 'pending'));
$paidCourseIds = PORTAL_EXTENSIONS_ENABLED ? array_map('intval', array_column(Database::query(
  "SELECT DISTINCT course_id FROM payments WHERE student_id = :student_id AND payment_status = 'success'",
  [':student_id' => (int)$user['id']]
)->fetchAll(), 'course_id')) : [];
$courseFeesDue = count(array_filter($applications, fn($application) => $application['application_status'] === 'approved'
  && (float)($application['price'] ?? 0) > 0
  && !in_array((int)$application['course_id'], $paidCourseIds, true)));

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
  <div class="page-actions">
    <a href="/student/courses.php" class="btn btn-secondary"><i class="fa fa-book"></i> Course Catalog</a>
    <?php if (EDUCATION_COURSE_TARGETING_ENABLED): ?><a href="/student/education.php" class="btn btn-ghost"><i class="fa fa-graduation-cap"></i> Education Profile</a><?php endif; ?>
    <?php if (PORTAL_EXTENSIONS_ENABLED): ?><a href="/student/results.php" class="btn btn-ghost"><i class="fa fa-ranking-star"></i> My Results</a><a href="/student/payments.php" class="btn btn-ghost"><i class="fa fa-money-bill-wave"></i> Payments</a><?php endif; ?>
  </div>
</div>

<?php if ($pendingApplications > 0): ?><div class="alert alert-info"><i class="fa fa-hourglass-half"></i> <?= $pendingApplications ?> course application<?= $pendingApplications === 1 ? '' : 's' ?> awaiting lecturer/tutor review.<?php if (PORTAL_EXTENSIONS_ENABLED): ?> Paid course materials remain unavailable until approval and payment confirmation.<?php endif; ?></div><?php endif; ?>
<?php if ($courseFeesDue > 0): ?><div class="alert alert-warning"><i class="fa fa-money-bill-wave"></i> <?= $courseFeesDue ?> approved paid course application<?= $courseFeesDue === 1 ? ' needs' : 's need' ?> payment before resources unlock. <a href="/student/courses.php">Go to courses</a></div><?php endif; ?>

<div class="card" id="upcoming-classes">
  <div class="card-header"><h2 class="card-title"><i class="fa fa-video text-accent"></i> Upcoming classes</h2></div>
  <?php if ($upcomingSessions): ?><div class="table-wrap"><table><thead><tr><th>Course</th><th>Session</th><th>Time</th><th>Join</th></tr></thead><tbody><?php foreach ($upcomingSessions as $session): ?><tr><td><?= htmlspecialchars($session['course_code'].' — '.$session['course_title']) ?></td><td><?= htmlspecialchars($session['title']) ?><?php if (!empty($session['tutor_name'])): ?><br><small>Tutor: <?= htmlspecialchars($session['tutor_name']) ?></small><?php endif; ?></td><td><?= htmlspecialchars(date('M j, Y H:i', strtotime($session['scheduled_at']))) ?></td><td><a class="btn btn-primary btn-sm" href="<?= htmlspecialchars($session['meet_link'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Join Meet</a></td></tr><?php endforeach; ?></tbody></table></div>
  <?php else: ?><div class="empty-state compact-empty-state"><p>No upcoming classes are scheduled for your courses.</p><a class="btn btn-ghost btn-sm" href="/student/courses.php">Browse courses</a></div><?php endif; ?>
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
<div class="card" id="active-assignments">
  <div class="card-header">
    <h2 class="card-title"><i class="fa fa-book-open text-accent"></i> &nbsp;Active Assignments</h2>
    <a href="/student/assignments.php" class="btn btn-ghost btn-sm">View All</a>
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
        <a href="<?= ($a['assignment_type'] ?? 'upload') === 'online' ? '/student/take_assignment.php' : '/student/submit.php' ?>?assignment_id=<?= $a['id'] ?>"
           class="btn btn-primary" style="width:100%;justify-content:center">
          <i class="fa fa-<?= ($a['assignment_type'] ?? 'upload') === 'online' ? 'list-check' : 'file-arrow-up' ?>"></i>
          <?= ($a['assignment_type'] ?? 'upload') === 'online' ? 'Start Online Assignment' : 'Submit Work' ?>
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
