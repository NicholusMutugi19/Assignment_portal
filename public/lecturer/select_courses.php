<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/User.php';

Auth::requireRole('lecturer', '/auth/login.php');
$user = Auth::user();
$errors = [];

if (!PORTAL_EXTENSIONS_ENABLED) {
    http_response_code(503);
    exit('Course selection is unavailable until the teaching-assignment migration is applied.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Refresh the page and try again.';
    } else {
        try {
            $selectedIds = $_POST['courses'] ?? [];
            if (!is_array($selectedIds)) $selectedIds = [];
            User::setTeachingCourseSelections((int)$user['id'], $selectedIds);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Your teaching-course selections have been saved. Course ownership was not changed.'];
            header('Location: /lecturer/courses.php');
            exit;
        } catch (Throwable $e) {
            error_log('Teaching-course selection failed: ' . $e->getMessage());
            $errors[] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Unable to save course selections.';
        }
    }
}

$availableCourses = User::availableTeachingCourses((int)$user['id']);
$selectedCourses = array_filter(User::taughtCourses((int)$user['id']), static fn(array $course): bool => (int)$course['lecturer_id'] !== (int)$user['id']);
$selectedIds = array_map('intval', array_column($selectedCourses, 'id'));
$pageTitle = 'Select Teaching Courses';
?>
<?php include __DIR__ . '/../../views/shared/header.php'; ?>

<div class="page-header">
  <div><h1 class="page-title">Choose Courses to Teach</h1><p class="page-subtitle">Select published courses already in the portal, or create a new course for your classes.</p></div>
  <div class="page-actions"><a class="btn btn-primary" href="/lecturer/create_course.php"><i class="fa fa-square-plus"></i> Create New Course</a><a class="btn btn-secondary" href="/lecturer/courses.php"><i class="fa fa-book"></i> My Courses</a></div>
</div>

<?php if ($errors): ?><div class="alert alert-error"><i class="fa fa-circle-exclamation"></i><ul><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="course-catalog-intro"><i class="fa fa-shield-halved"></i><span>Adding a course gives you teaching access; it does not transfer ownership or alter another lecturer’s course settings.</span></div>

<div class="card">
  <div class="card-header"><h2 class="card-title"><i class="fa fa-list-check text-accent"></i> Available published courses</h2></div>
  <?php if (!$availableCourses && !$selectedCourses): ?>
    <div class="empty-state"><div class="empty-state-icon"><i class="fa fa-book-open"></i></div><h3>No other published courses yet</h3><p>Create a custom course and it will appear in your teaching list.</p><a href="/lecturer/create_course.php" class="btn btn-primary mt-2"><i class="fa fa-plus"></i> Create a course</a></div>
  <?php else: ?>
    <?php if ($selectedCourses): ?><div class="selected-teaching-courses"><strong>Currently teaching from the shared catalog</strong><div class="course-chip-list"><?php foreach ($selectedCourses as $course): ?><span class="course-chip"><i class="fa fa-circle-check"></i><?= htmlspecialchars($course['code'] . ' — ' . $course['title']) ?></span><?php endforeach; ?></div></div><?php endif; ?>
    <?php if ($availableCourses): ?>
      <form method="POST" class="teaching-course-form">
        <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
        <div class="course-selection-grid teaching-course-grid">
          <?php foreach ($availableCourses as $course): $checked = in_array((int)$course['id'], $selectedIds, true); ?>
            <label class="course-selection-card teaching-course-option <?= $checked ? 'is-selected' : '' ?>">
              <span class="teaching-course-check"><input type="checkbox" name="courses[]" value="<?= (int)$course['id'] ?>" <?= $checked ? 'checked' : '' ?>><span class="course-code"><?= htmlspecialchars($course['code']) ?></span></span>
              <span class="course-title"><?= htmlspecialchars($course['title']) ?></span>
              <span class="course-lecturer">Course owner: <?= htmlspecialchars($course['owner_name']) ?></span>
              <?php if (!empty($course['description'])): ?><span class="teaching-course-description"><?= htmlspecialchars($course['description']) ?></span><?php endif; ?>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="teaching-course-actions"><span class="form-hint">Uncheck a course to remove it from your teaching list.</span><button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Course Selections</button></div>
      </form>
    <?php else: ?><div class="empty-state compact-empty-state"><p>You are already teaching every other published course currently available.</p></div><?php endif; ?>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../../views/shared/footer.php'; ?>