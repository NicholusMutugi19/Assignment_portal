<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/User.php';

Auth::requireRole('lecturer', '/auth/login.php');
if (!EDUCATION_COURSE_TARGETING_ENABLED) {
    http_response_code(503);
    exit('Course creation is not enabled until the education and course-audience migration has been applied.');
}
$user = Auth::user();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $title = trim($_POST['title'] ?? '');
    $audience = $_POST['audience'] ?? '';
    if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{1,19}$/', $code)) $errors[] = 'Use a unique course code with 2–20 letters, numbers, hyphens, or underscores.';
    if ($title === '' || mb_strlen($title) > 200) $errors[] = 'Course title is required and must be 200 characters or fewer.';
    if (!in_array($audience, ['campus_only', 'high_school_only', 'both'], true)) $errors[] = 'Choose a valid student audience.';
    foreach (['category' => 120, 'duration' => 120, 'tutor_name' => 120] as $field => $limit) {
        if (mb_strlen(trim($_POST[$field] ?? '')) > $limit) $errors[] = ucfirst(str_replace('_', ' ', $field)) . " must be {$limit} characters or fewer.";
    }
    if (!$errors) {
        try {
            User::createLecturerCourse((int)$user['id'], [
                'code' => $code,
                'title' => $title,
                'description' => $_POST['description'] ?? '',
                'audience' => $audience,
                'category' => $_POST['category'] ?? '',
                'duration' => $_POST['duration'] ?? '',
                'prerequisites' => $_POST['prerequisites'] ?? '',
                'syllabus' => $_POST['syllabus'] ?? '',
                'tutor_name' => $_POST['tutor_name'] ?? '',
            ]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Course saved as a draft. Review it in My Courses and publish when ready.'];
            header('Location: /lecturer/courses.php');
            exit;
        } catch (Throwable $e) {
            error_log('Lecturer course creation failed: ' . $e->getMessage());
            $errors[] = 'Could not create the course. Check that the course code is unique and try again.';
        }
    }
}
$pageTitle = 'Create Course';
?>
<?php include __DIR__ . '/../../views/shared/header.php'; ?>
<div class="page-header">
  <div><h1 class="page-title">Create a Course</h1><p class="page-subtitle">New courses start as drafts. Select which students can see the course.</p></div>
  <a href="/lecturer/courses.php" class="btn btn-ghost"><i class="fa fa-arrow-left"></i> My Courses</a>
</div>
<?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="form-card">
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
    <div class="form-row">
      <div class="form-group"><label for="code">Course code</label><input id="code" name="code" maxlength="20" required value="<?= htmlspecialchars($_POST['code'] ?? '') ?>"></div>
      <div class="form-group"><label for="title">Course title</label><input id="title" name="title" maxlength="200" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"></div>
    </div>
    <div class="form-group"><label for="description">Description</label><textarea id="description" name="description" rows="4"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea></div>
    <div class="form-row">
      <div class="form-group"><label for="audience">Available to</label><select id="audience" name="audience" required>
        <option value="">Choose student level</option>
        <option value="campus_only" <?= ($_POST['audience'] ?? '') === 'campus_only' ? 'selected' : '' ?>>Campus students only</option>
        <option value="high_school_only" <?= ($_POST['audience'] ?? '') === 'high_school_only' ? 'selected' : '' ?>>High School students only</option>
        <option value="both" <?= ($_POST['audience'] ?? '') === 'both' ? 'selected' : '' ?>>Both levels</option>
      </select></div>
      <div class="form-group"><label for="category">Category</label><input id="category" name="category" maxlength="120" value="<?= htmlspecialchars($_POST['category'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label for="duration">Duration</label><input id="duration" name="duration" maxlength="120" placeholder="e.g. 8 weeks" value="<?= htmlspecialchars($_POST['duration'] ?? '') ?>"></div>
      <div class="form-group"><label for="tutor-name">Tutor name (optional)</label><input id="tutor-name" name="tutor_name" maxlength="120" value="<?= htmlspecialchars($_POST['tutor_name'] ?? '') ?>"></div>
    </div>
    <div class="form-group"><label for="prerequisites">Prerequisites</label><textarea id="prerequisites" name="prerequisites" rows="3"><?= htmlspecialchars($_POST['prerequisites'] ?? '') ?></textarea></div>
    <div class="form-group"><label for="syllabus">Syllabus</label><textarea id="syllabus" name="syllabus" rows="5"><?= htmlspecialchars($_POST['syllabus'] ?? '') ?></textarea></div>
    <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> Save as draft</button>
  </form>
</div>
<?php include __DIR__ . '/../../views/shared/footer.php'; ?>
