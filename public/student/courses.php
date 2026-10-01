<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/User.php';
require_once __DIR__ . '/../../src/models/Admin.php';

Auth::requireRole('student', '/auth/login.php');
$user = Auth::user();
if (PORTAL_EXTENSIONS_ENABLED && Admin::setting('maintenance_mode', '0') === '1') {
  http_response_code(503); header('Retry-After: 300'); exit('Course applications are paused during maintenance.');
}
if (PORTAL_EXTENSIONS_ENABLED && Admin::setting('student_course_applications_enabled', '1') !== '1') {
  http_response_code(503);
  exit('New course applications are temporarily paused.');
}
if (EDUCATION_COURSE_TARGETING_ENABLED) {
    $profile = User::educationProfile((int)$user['id']);
    if (!$profile || !$profile['education_level'] || !$profile['institution_name'] || !$profile['year_or_form']) {
        header('Location: /student/education.php?return=courses');
        exit;
    }
}
$courses = User::availableCourses((int)$user['id']);
$enrolled = array_map('intval', array_column(User::enrolledCourses((int)$user['id']), 'id'));
$applications = [];
foreach (User::courseApplicationStatuses((int)$user['id']) as $applicationRow) {
  $applications[(int)$applicationRow['course_id']] = $applicationRow;
}
$paid = PORTAL_EXTENSIONS_ENABLED ? array_map('intval', array_column(Database::query(
    "SELECT DISTINCT course_id FROM payments WHERE student_id = :sid AND payment_status = 'success'",
    [':sid' => (int)$user['id']]
)->fetchAll(), 'course_id')) : [];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['free_course_id'])) {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Refresh and try again.';
  } else {
    try {
      $courseId = (int)$_POST['free_course_id'];
      $targetSql = PORTAL_EXTENSIONS_ENABLED ? 'SELECT id, price FROM courses WHERE id = :course_id' : 'SELECT id, NULL AS price FROM courses WHERE id = :course_id';
      $target = Database::query($targetSql, [':course_id' => $courseId])->fetch();
      if (!$target) throw new InvalidArgumentException('Selected course was not found.');
      if (PORTAL_EXTENSIONS_ENABLED && (float)($target['price'] ?? 0) > 0) {
        throw new InvalidArgumentException('This course requires a payment request; use its Pay action.');
      }
      User::saveStudentCourseSelections((int)$user['id'], [$courseId]);
      $_SESSION['flash'] = ['type' => 'success', 'message' => PORTAL_EXTENSIONS_ENABLED ? 'Your application has been submitted; course staff will review it.' : 'You are enrolled.'];
      header('Location: /student/courses.php');
      exit;
    } catch (Throwable $e) {
      error_log('Free course enrollment failed: ' . $e->getMessage());
      $errors[] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Could not enroll in this course.';
    }
    }
}
$pageTitle = 'Available Courses';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<?php include __DIR__ . '/../../views/shared/header.php'; ?>
<div class="page-header"><div><h1 class="page-title">Available Courses</h1><p class="page-subtitle">Choose courses for your level. Paid course resources unlock only after M-Pesa confirms payment.</p></div></div>
<?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="course-selection-grid">
<?php foreach ($courses as $course):
    $id = (int)$course['id'];
    $isPaid = PORTAL_EXTENSIONS_ENABLED && $course['price'] !== null && (float)$course['price'] > 0;
    $isEnrolled = in_array($id, $enrolled, true);
    $isPaidByStudent = in_array($id, $paid, true);
?>
<div class="course-selection-card"><div class="course-checkbox">
  <div class="course-code"><?= htmlspecialchars($course['code']) ?></div>
  <h2 class="course-title"><?= htmlspecialchars($course['title']) ?></h2>
  <?php if (!empty($course['description'])): ?><p><?= nl2br(htmlspecialchars($course['description'])) ?></p><?php endif; ?>
  <div class="course-lecturer">Lecturer: <?= htmlspecialchars($course['lecturer_name']) ?><?php if (!empty($course['tutor_name'])): ?> | Tutor: <?= htmlspecialchars($course['tutor_name']) ?><?php endif; ?></div>
  <?php if (EDUCATION_COURSE_TARGETING_ENABLED): ?><div class="course-lecturer">Audience: <?= htmlspecialchars(str_replace('_', ' ', $course['audience'])) ?></div><?php endif; ?>
  <?php if ($isPaid): ?><p class="fw-700">Fee: KES <?= number_format((float)$course['price'], 2) ?></p><?php endif; ?>
  <?php if (PORTAL_EXTENSIONS_ENABLED && ($applications[$id]['application_status'] ?? '') === 'pending'): ?>
    <span class="badge badge-warning">Application pending approval</span>
  <?php elseif (PORTAL_EXTENSIONS_ENABLED && ($applications[$id]['application_status'] ?? '') === 'rejected'): ?>
    <span class="badge badge-danger">Application rejected<?= !empty($applications[$id]['application_note']) ? ': ' . htmlspecialchars($applications[$id]['application_note']) : '' ?></span>
    <form method="POST" action="/student/courses.php"><input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>"><input type="hidden" name="free_course_id" value="<?= $id ?>"><button class="btn btn-secondary" type="submit">Reapply</button></form>
  <?php elseif ($isPaidByStudent): ?>
    <span class="badge badge-success">Payment confirmed · resources unlocked</span>
  <?php elseif ($isPaid && MPESA_ENABLED && (!PORTAL_EXTENSIONS_ENABLED || ($applications[$id]['application_status'] ?? '') === 'approved')): ?>
    <form method="POST" action="/student/checkout.php" style="margin-top:.75rem">
      <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
      <input type="hidden" name="course_id" value="<?= $id ?>">
      <div class="form-group"><label for="phone-<?= $id ?>">M-Pesa phone number</label><input id="phone-<?= $id ?>" name="phone_number" type="tel" inputmode="numeric" pattern="(0[17][0-9]{8}|254[17][0-9]{8})" placeholder="0712345678" required></div>
      <button class="btn btn-primary" type="submit"><i class="fa fa-mobile-screen"></i> <?= $isEnrolled ? 'Pay' : 'Enroll & pay' ?> KES <?= number_format((float)$course['price'], 0) ?></button>
      <p class="form-hint">Course resources remain locked until payment confirmation.</p>
    </form>
  <?php elseif ($isPaid && PORTAL_EXTENSIONS_ENABLED && empty($applications[$id])): ?>
    <form method="POST" action="/student/courses.php"><input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>"><input type="hidden" name="free_course_id" value="<?= $id ?>"><button class="btn btn-primary" type="submit">Apply for course</button></form>
    <p class="form-hint">Payment becomes available after the lecturer or tutor approves your application.</p>
  <?php elseif ($isPaid): ?>
    <span class="badge badge-warning">Payment unavailable at this time</span>
  <?php elseif ($isEnrolled): ?>
    <span class="badge badge-info">Enrolled</span>
  <?php else: ?>
    <form method="POST" action="/student/courses.php"><input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>"><input type="hidden" name="free_course_id" value="<?= $id ?>"><button class="btn btn-primary" type="submit">Enroll</button></form>
  <?php endif; ?>
</div></div>
<?php endforeach; ?>
</div>
<?php include __DIR__ . '/../../views/shared/footer.php'; ?>
