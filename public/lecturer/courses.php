<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/User.php';
require_once __DIR__ . '/../../src/models/Assignment.php';

Auth::requireRole('lecturer', APP_URL . '/auth/login.php');
$user    = Auth::user();
$courses = User::taughtCourses((int)$user['id']);

$pageTitle = 'My Courses';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<?php include __DIR__ . '/../../views/shared/header.php'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">My Courses</h1>
    <p class="page-subtitle">Courses you're teaching</p>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/lecturer/select_courses.php" class="btn btn-secondary">
      <i class="fa fa-edit"></i> Manage Courses
    </a>
    <a href="<?= APP_URL ?>/lecturer/create_assignment.php" class="btn btn-primary">
      <i class="fa fa-plus"></i> Create Assignment
    </a>
  </div>
</div>

<?php if ($flash): ?>
  <div class="alert alert-<?= $flash['type'] ?>">
    <i class="fa fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'circle-exclamation' ?>"></i>
    <?= htmlspecialchars($flash['message']) ?>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <h2 class="card-title"><i class="fa fa-book text-accent"></i> &nbsp;Your Courses</h2>
  </div>

  <?php if (empty($courses)): ?>
    <div class="empty-state">
      <div class="empty-state-icon"><i class="fa fa-book-open"></i></div>
      <h3>No courses yet</h3>
      <p>Create your first assignment to get started with a course.</p>
      <a href="<?= APP_URL ?>/lecturer/create_assignment.php" class="btn btn-primary mt-2">
        <i class="fa fa-plus"></i> Create Assignment
      </a>
    </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Course Code</th>
          <th>Course Title</th>
          <th>Students</th>
          <th>Assignments</th>
          <th>Created</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($courses as $course): ?>
        <?php
          $assignmentCount = count(Assignment::forCourse((int)$course['id']));
        ?>
        <tr>
          <td>
            <span class="badge badge-info"><?= htmlspecialchars($course['code']) ?></span>
          </td>
          <td>
            <div class="fw-700"><?= htmlspecialchars($course['title']) ?></div>
          </td>
          <td>
            <span class="fw-700"><?= $course['student_count'] ?? 0 ?></span>
            <span class="text-muted"> enrolled</span>
          </td>
          <td>
            <span class="fw-700"><?= $assignmentCount ?></span>
            <span class="text-muted"> assignments</span>
          </td>
          <td>
            <?= date('M j, Y', strtotime($course['created_at'])) ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../../views/shared/footer.php'; ?>