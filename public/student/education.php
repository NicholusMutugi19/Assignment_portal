<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/User.php';

Auth::requireRole('student', '/auth/login.php');
if (!EDUCATION_COURSE_TARGETING_ENABLED) {
    header('Location: /student/dashboard.php');
    exit;
}
$user = Auth::user();
$profile = User::educationProfile((int)$user['id']) ?? [];
$originalLevel = $profile['education_level'] ?? null;
$error = '';
$returnTo = ($_GET['return'] ?? $_POST['return'] ?? '') === 'courses' ? '/student/courses.php' : '/student/dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Your session security token expired. Please refresh and try again.';
    } else {
        $level = $_POST['education_level'] ?? '';
        $institution = trim($_POST['institution_name'] ?? '');
        $yearOrForm = trim($_POST['year_or_form'] ?? '');
        if (!in_array($level, ['campus', 'high_school'], true) || $institution === '' || $yearOrForm === '') {
            $error = 'Select your education level and provide your institution and year or form.';
        } elseif (strlen($institution) > 180 || strlen($yearOrForm) > 60) {
            $error = 'Institution or year/form exceeds the allowed length.';
        } else {
            try {
                $pdo = Database::getInstance();
                $pdo->beginTransaction();
                $existingCourseIds = array_map('intval', array_column(Database::query('SELECT course_id FROM enrollments WHERE student_id = :id FOR UPDATE', [':id' => (int)$user['id']])->fetchAll(), 'course_id'));
                User::updateEducationProfile((int)$user['id'], $level, $institution, $yearOrForm);
                if (PORTAL_EXTENSIONS_ENABLED && $existingCourseIds) {
                    $placeholders = implode(',', array_fill(0, count($existingCourseIds), '?'));
                    $courseAudience = $pdo->prepare('SELECT id, audience FROM courses WHERE id IN (' . $placeholders . ')');
                    $courseAudience->execute($existingCourseIds);
                    $allowed = [];
                    foreach ($courseAudience->fetchAll(PDO::FETCH_ASSOC) as $courseRow) {
                        if ($courseRow['audience'] === 'both'
                            || ($level === 'campus' && $courseRow['audience'] === 'campus_only')
                            || ($level === 'high_school' && $courseRow['audience'] === 'high_school_only')) {
                            $allowed[] = (int)$courseRow['id'];
                        }
                    }
                    $levelChanged = $originalLevel !== null && $originalLevel !== $level;
                    if ($allowed && $levelChanged) {
                        $allowedPlaceholders = implode(',', array_fill(0, count($allowed), '?'));
                        $update = $pdo->prepare('UPDATE enrollments SET application_status = \'pending\', application_reviewed_by = NULL, application_reviewed_at = NULL, application_note = NULL WHERE student_id = ? AND course_id IN (' . $allowedPlaceholders . ')');
                        $update->execute(array_merge([(int)$user['id']], $allowed));
                    }
                    $disallowed = array_values(array_diff($existingCourseIds, $allowed));
                    if ($disallowed && $levelChanged) {
                        $lockedIds = implode(',', array_fill(0, count($disallowed), '?'));
                      $lock = $pdo->prepare('UPDATE enrollments SET application_status = \'rejected\', application_reviewed_by = NULL, application_reviewed_at = NULL, application_note = ? WHERE student_id = ? AND course_id IN (' . $lockedIds . ')');
                      $lock->execute(array_merge(['Course is not eligible at the selected education level'], [(int)$user['id']], $disallowed));
                    }
                }
                $pdo->commit();
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Your education profile has been saved.'];
                header('Location: ' . $returnTo);
                exit;
            } catch (Throwable $e) {
              if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
                error_log('Education profile update failed: ' . $e->getMessage());
                $error = 'Unable to save your education profile. Please try again.';
            }
        }
        $profile = [
            'education_level' => $level,
            'institution_name' => $institution,
            'year_or_form' => $yearOrForm,
        ];
    }
}

$pageTitle = 'Education Profile';
?>
<?php include __DIR__ . '/../../views/shared/header.php'; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Your education profile</h1>
    <p class="page-subtitle">This helps us show courses intended for your level of study.</p>
  </div>
</div>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="form-card">
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
    <input type="hidden" name="return" value="<?= htmlspecialchars($_POST['return'] ?? $_GET['return'] ?? '') ?>">
    <div class="form-group">
      <label for="education-level">Education level</label>
      <select id="education-level" name="education_level" required>
        <option value="">Choose one</option>
        <option value="campus" <?= ($profile['education_level'] ?? '') === 'campus' ? 'selected' : '' ?>>Campus / University / College</option>
        <option value="high_school" <?= ($profile['education_level'] ?? '') === 'high_school' ? 'selected' : '' ?>>High School</option>
      </select>
    </div>
    <div class="form-group">
      <label for="institution-name">Institution or school name</label>
      <input id="institution-name" name="institution_name" maxlength="180" required value="<?= htmlspecialchars($profile['institution_name'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label for="year-or-form">Year of study or form</label>
      <input id="year-or-form" name="year_or_form" maxlength="60" required value="<?= htmlspecialchars($profile['year_or_form'] ?? '') ?>">
    </div>
    <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> Save profile</button>
  </form>
</div>
<?php include __DIR__ . '/../../views/shared/footer.php'; ?>
