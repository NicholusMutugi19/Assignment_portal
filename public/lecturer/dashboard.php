<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/Assignment.php';
require_once __DIR__ . '/../../src/models/User.php';
require_once __DIR__ . '/../../src/models/Submission.php';

Auth::requireRole('lecturer', APP_URL . '/auth/login.php');
$user        = Auth::user();
$assignments = Assignment::forLecturer((int)$user['id']);
$courses     = User::taughtCourses((int)$user['id']);

// Aggregate stats
$totalAssignments  = count($assignments);
$totalSubmissions  = array_sum(array_column($assignments, 'total_submissions'));
$pendingGrading    = array_sum(array_column($assignments, 'total_submissions')) - array_sum(array_column($assignments, 'graded_count'));
$openAssignments   = count(array_filter($assignments, fn($a) => $a['status'] === 'published' && strtotime($a['deadline']) > time()));

$pageTitle = 'Lecturer Dashboard';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<?php include __DIR__ . '/../../views/shared/header.php'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Welcome back, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?> 👋</h1>
    <p class="page-subtitle">You are teaching <?= count($courses) ?> course<?= count($courses) !== 1 ? 's' : '' ?></p>
  </div>
  <a href="<?= APP_URL ?>/lecturer/create_assignment.php" class="btn btn-primary">
    <i class="fa fa-plus"></i> New Assignment
  </a>
</div>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-number text-accent"><?= $totalAssignments ?></div>
    <div class="stat-label">Total Assignments</div>
    <i class="fa fa-list-check stat-icon"></i>
  </div>
  <div class="stat-card">
    <div class="stat-number text-green"><?= $totalSubmissions ?></div>
    <div class="stat-label">Total Submissions</div>
    <i class="fa fa-inbox stat-icon"></i>
  </div>
  <div class="stat-card">
    <div class="stat-number text-yellow"><?= $pendingGrading ?></div>
    <div class="stat-label">Awaiting Grade</div>
    <i class="fa fa-hourglass-half stat-icon"></i>
  </div>
  <div class="stat-card">
    <div class="stat-number"><?= count($courses) ?></div>
    <div class="stat-label">Courses</div>
    <i class="fa fa-book stat-icon"></i>
  </div>
</div>

<!-- Recent Assignments -->
<div class="card">
  <div class="card-header">
    <h2 class="card-title"><i class="fa fa-list-check text-accent"></i> &nbsp;Assignments</h2>
    <a href="<?= APP_URL ?>/lecturer/assignments.php" class="btn btn-ghost btn-sm">View All</a>
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
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Assignment</th>
          <th>Course</th>
          <th>Deadline</th>
          <th>Status</th>
          <th>Submissions</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach (array_slice($assignments, 0, 8) as $a): ?>
        <?php
          $isPast = strtotime($a['deadline']) < time();
          $diff   = strtotime($a['deadline']) - time();
          $pillCls= $isPast ? 'past' : ($diff < 86400*2 ? 'urgent' : ($diff < 86400*7 ? 'soon' : 'plenty'));
        ?>
        <tr>
          <td>
            <div class="fw-700"><?= htmlspecialchars($a['title']) ?></div>
            <div class="text-muted" style="font-size:.78rem">Max: <?= $a['max_score'] ?> pts</div>
          </td>
          <td>
            <span class="badge badge-info"><?= htmlspecialchars($a['course_code']) ?></span>
          </td>
          <td>
            <span class="deadline-pill <?= $pillCls ?>">
              <i class="fa fa-clock"></i>
              <?= date('M j, Y H:i', strtotime($a['deadline'])) ?>
            </span>
          </td>
          <td><span class="badge badge-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
          <td>
            <span class="fw-700"><?= $a['total_submissions'] ?></span>
            <span class="text-muted"> / <?= $a['graded_count'] ?> graded</span>
          </td>
          <td>
            <a href="<?= APP_URL ?>/lecturer/submissions.php?assignment_id=<?= $a['id'] ?>" class="btn btn-ghost btn-sm">
              <i class="fa fa-eye"></i> View
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../../views/shared/footer.php'; ?>
