<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/User.php';
require_once __DIR__ . '/../../src/models/Assignment.php';

Auth::requireLogin('/auth/login.php');
$user    = Auth::user();
if (!in_array($user['role'], ['lecturer', 'tutor'], true)) {
  http_response_code(403);
  exit('Forbidden.');
}
if ($user['role'] === 'lecturer' && PORTAL_EXTENSIONS_ENABLED && User::approvalStatus((int)$user['id']) !== 'approved') {
  header('Location: /lecturer/pending_approval.php');
  exit;
}
if (PORTAL_EXTENSIONS_ENABLED && !empty($_GET['assign_tutor']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid security token.'];
  } else {
    $courseId = (int)($_POST['course_id'] ?? 0);
    $tutorId = (int)($_POST['tutor_id'] ?? 0);
    $owned = Database::query('SELECT id FROM courses WHERE id = :course AND lecturer_id = :lecturer', [':course' => $courseId, ':lecturer' => (int)$user['id']])->fetch();
    $tutor = Database::query("SELECT id FROM users WHERE id = :id AND role = 'tutor' AND account_status = 'active'", [':id' => $tutorId])->fetch();
    if ($owned && ($tutor || $tutorId === 0)) {
      Database::query('UPDATE courses SET tutor_id = :tutor WHERE id = :course AND lecturer_id = :lecturer', [':tutor' => $tutorId ?: null, ':course' => $courseId, ':lecturer' => (int)$user['id']]);
      $_SESSION['flash'] = ['type' => 'success', 'message' => 'Tutor assigned to course.'];
    } else {
      $_SESSION['flash'] = ['type' => 'error', 'message' => 'Select one of your courses and an active tutor.'];
    }
  }
  header('Location: /lecturer/courses.php'); exit;
}
$courses = PORTAL_EXTENSIONS_ENABLED && in_array($user['role'], ['lecturer', 'tutor'], true)
  ? User::manageableCourseAssignments((int)$user['id'])
  : User::taughtCourses((int)$user['id']);

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
    <?php if ($user['role'] === 'tutor'): ?>
    <?php if (PORTAL_EXTENSIONS_ENABLED): ?>
    <a href="/lecturer/create_online_assignment.php" class="btn btn-primary"><i class="fa fa-list-check"></i> Create Online Assignment</a>
    <a href="/lecturer/class_sessions.php" class="btn btn-secondary"><i class="fa fa-video"></i> Class Sessions</a>
    <?php endif; ?>
    <?php else: ?>
    <?php if (PORTAL_EXTENSIONS_ENABLED): ?>
    <a href="/lecturer/create_course.php" class="btn btn-primary">
      <i class="fa fa-plus"></i> Create Course
    </a>
    <?php endif; ?>
    <?php if (!PORTAL_EXTENSIONS_ENABLED): ?><a href="/lecturer/select_courses.php" class="btn btn-secondary"><i class="fa fa-edit"></i> Manage Teaching Catalog</a><?php endif; ?>
    <a href="/lecturer/create_assignment.php" class="btn btn-primary">
      <i class="fa fa-plus"></i> Create Assignment
    </a>
    <?php endif; ?>
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
      <a href="<?= $user['role'] === 'tutor' ? '/lecturer/create_online_assignment.php' : '/lecturer/create_assignment.php' ?>" class="btn btn-primary mt-2">
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
          <?php if (PORTAL_EXTENSIONS_ENABLED && $user['role'] === 'lecturer'): ?><th><?= EDUCATION_COURSE_TARGETING_ENABLED ? 'Audience / Status / Fee' : 'Status / Fee' ?></th><?php endif; ?>
          <?php if (PORTAL_EXTENSIONS_ENABLED && $user['role'] === 'lecturer'): ?><th>Assigned tutor</th><?php endif; ?>
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
          <?php if (PORTAL_EXTENSIONS_ENABLED && $user['role'] === 'lecturer'): ?>
          <td>
            <form method="POST" action="/lecturer/manage_course.php" style="display:flex;gap:.4rem;align-items:center">
              <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
              <input type="hidden" name="course_id" value="<?= (int)$course['id'] ?>">
              <?php if (EDUCATION_COURSE_TARGETING_ENABLED): ?><select name="audience" aria-label="Course audience">
                <?php foreach (['campus_only' => 'Campus only', 'high_school_only' => 'High School only', 'both' => 'Both levels'] as $value => $label): ?>
                  <option value="<?= $value ?>" <?= ($course['audience'] ?? 'both') === $value ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select><?php else: ?><input type="hidden" name="audience" value="both"><?php endif; ?>
              <select name="status" aria-label="Course status">
                <?php foreach (['draft' => 'Draft', 'published' => 'Published'] as $value => $label): ?>
                  <option value="<?= $value ?>" <?= ($course['status'] ?? 'published') === $value ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
              <input type="number" name="price" min="0" max="1000000" step="0.01" value="<?= htmlspecialchars((string)($course['price'] ?? '')) ?>" placeholder="Fee (KES)" aria-label="Course fee in KES" style="width:8rem">
              <?php if ($course['price'] !== null): ?><span class="text-muted">KES <?= number_format((float)$course['price'], 2) ?></span><?php endif; ?>
              <button class="btn btn-ghost btn-sm" type="submit" title="Save course settings"><i class="fa fa-save"></i></button>
            </form>
          </td>
          <?php endif; ?>
          <?php if (PORTAL_EXTENSIONS_ENABLED && $user['role'] === 'lecturer'): ?>
          <td><form method="POST" action="/lecturer/courses.php?assign_tutor=1" style="display:flex;gap:.4rem"><input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>"><input type="hidden" name="course_id" value="<?= (int)$course['id'] ?>"><select name="tutor_id"><option value="0">No tutor</option><?php foreach (Database::query("SELECT id,name FROM users WHERE role='tutor' AND account_status='active' ORDER BY name")->fetchAll() as $tutor): ?><option value="<?= (int)$tutor['id'] ?>" <?= (int)($course['tutor_id']??0)===(int)$tutor['id']?'selected':'' ?>><?= htmlspecialchars($tutor['name']) ?></option><?php endforeach; ?></select><button class="btn btn-ghost btn-sm" type="submit"><i class="fa fa-save"></i></button></form></td>
          <?php endif; ?>
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