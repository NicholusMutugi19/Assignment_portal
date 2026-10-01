<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/User.php';

Auth::requireRole('student', '/auth/login.php');
$user = Auth::user();
if (EDUCATION_COURSE_TARGETING_ENABLED) {
    $profile = User::educationProfile((int)$user['id']);
    if (!$profile || !$profile['education_level'] || !$profile['institution_name'] || !$profile['year_or_form']) {
        header('Location: /student/education.php?return=courses');
        exit;
    }
}
$courses = User::availableCourses((int)$user['id']);
$enrolled = array_map('intval', array_column(User::enrolledCourses((int)$user['id']), 'id'));
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
      User::saveStudentCourseSelections((int)$user['id'], [(int)$_POST['free_course_id']]);
      $_SESSION['flash'] = ['type' => 'success', 'message' => 'You are enrolled.'];
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
  <?php if ($isPaidByStudent): ?>
    <span class="badge badge-success">Payment confirmed · resources unlocked</span>
  <?php elseif ($isPaid && MPESA_ENABLED): ?>
    <form method="POST" action="/student/checkout.php" style="margin-top:.75rem">
      <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
      <input type="hidden" name="course_id" value="<?= $id ?>">
      <div class="form-group"><label for="phone-<?= $id ?>">M-Pesa phone number</label><input id="phone-<?= $id ?>" name="phone_number" type="tel" inputmode="numeric" pattern="(0[17][0-9]{8}|254[17][0-9]{8})" placeholder="0712345678" required></div>
      <button class="btn btn-primary" type="submit"><i class="fa fa-mobile-screen"></i> <?= $isEnrolled ? 'Pay' : 'Enroll & pay' ?> KES <?= number_format((float)$course['price'], 0) ?></button>
      <p class="form-hint">Course resources remain locked until payment confirmation.</p>
    </form>
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
