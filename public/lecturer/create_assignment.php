<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/Assignment.php';
require_once __DIR__ . '/../../src/models/User.php';
require_once __DIR__ . '/../../src/models/FileUploader.php';

Auth::requireRole('lecturer', '/auth/login.php');
$user    = Auth::user();

// Get courses taught by this lecturer
$availableCourses = User::taughtCourses((int)$user['id']);

// Get courses taught by this lecturer (from database)
$courses = User::taughtCourses((int)$user['id']);

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate CSRF
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please refresh and try again.';
    } else {
        $title       = trim($_POST['title']       ?? '');
        $description = trim($_POST['description'] ?? '');
        $course_id   = (int)($_POST['course_id'] ?? 0);

        // Validate that the selected course is taught by this lecturer
        $courseValid = false;
        foreach ($availableCourses as $course) {
            if ($course['id'] == $course_id) {
                $courseValid = true;
                break;
            }
        }

        if (!$courseValid) {
            $errors[] = 'Please select a valid course you are teaching.';
        }

        if (!$title)       $errors[] = 'Assignment title is required.';
        if (!$description) $errors[] = 'Description is required.';
        if (!$course_id)   $errors[] = 'Please select a course.';
        if (!$deadline)    $errors[] = 'Deadline is required.';
        elseif (strtotime($deadline) <= time()) $errors[] = 'Deadline must be in the future.';

        $attachmentPath = null;
        $attachmentName = null;

        // Optional file attachment
        if (!empty($_FILES['attachment']['name'])) {
            $uploader = new FileUploader(
                __DIR__ . '/../' . UPLOAD_DIR_ASSIGNMENTS
            );
            $result = $uploader->handle($_FILES['attachment']);
            if ($result['ok']) {
                $attachmentPath = UPLOAD_DIR_ASSIGNMENTS . $result['stored_name'];
                $attachmentName = $result['original_name'];
            } else {
                $errors = array_merge($errors, $result['errors']);
            }
        }

        if (empty($errors)) {
            $id = Assignment::create([
                'course_id'       => $course_id,
                'lecturer_id'     => $user['id'],
                'title'           => $title,
                'description'     => $description,
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'max_score'       => $max_score,
                'deadline'        => date('Y-m-d H:i:s', strtotime($deadline)),
                'allow_late'      => $allow_late,
                'late_penalty'    => $late_penalty,
                'status'          => $status,
            ]);
            $_SESSION['flash'] = ['type'=>'success','message'=>'Assignment created successfully!'];
            header('Location: /lecturer/submissions.php?assignment_id=' . $id);
            exit;
        }
    }
}

$pageTitle = 'Create Assignment';
$flash     = null;
?>
<?php include __DIR__ . '/../../views/shared/header.php'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Create Assignment</h1>
    <p class="page-subtitle">Post a new assignment for your students</p>
  </div>
  <a href="/lecturer/dashboard.php" class="btn btn-ghost">
    <i class="fa fa-arrow-left"></i> Back
  </a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-error">
    <i class="fa fa-circle-exclamation"></i>
    <div>
      <strong>Please fix the following:</strong>
      <ul style="margin:.5rem 0 0 1rem">
        <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php endif; ?>

<div class="form-card">
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= UPLOAD_MAX_SIZE ?>">

    <div class="form-group">
      <label for="course_id">Course <span style="color:var(--red)">*</span></label>
      <select id="course_id" name="course_id" required>
        <option value="">— Select a course —</option>
        <?php foreach ($availableCourses as $c):
          $selected = (($_POST['course_id'] ?? '') == $c['id']) ? 'selected' : '';
        ?>
          <option value="<?= $c['id'] ?>" <?= $selected ?>>
            <?= htmlspecialchars($c['code'] . ' — ' . $c['title']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="form-hint">Choose a course you're teaching to create an assignment for</p>
    </div>

    <div class="form-group">
      <label for="title">Assignment Title <span style="color:var(--red)">*</span></label>
      <input type="text" id="title" name="title"
             value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
             placeholder="e.g. PHP File Upload System" required>
    </div>

    <div class="form-group">
      <label for="description">Description / Instructions <span style="color:var(--red)">*</span></label>
      <textarea id="description" name="description" rows="6"
                placeholder="Describe what students need to do, requirements, and deliverables..."
                required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="deadline">Deadline <span style="color:var(--red)">*</span></label>
        <input type="datetime-local" id="deadline" name="deadline"
               value="<?= htmlspecialchars($_POST['deadline'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label for="max_score">Maximum Score</label>
        <input type="number" id="max_score" name="max_score"
               value="<?= htmlspecialchars($_POST['max_score'] ?? '100') ?>"
               min="1" max="1000" step="0.5">
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:.5rem;text-transform:none;font-size:.9rem;font-weight:600;">
          <input type="checkbox" name="allow_late"
                 <?= !empty($_POST['allow_late']) ? 'checked' : '' ?>
                 style="width:auto;">
          Allow Late Submissions
        </label>
        <p class="form-hint">Students can still submit after the deadline</p>
      </div>
      <div class="form-group">
        <label for="late_penalty">Late Penalty (%)</label>
        <input type="number" id="late_penalty" name="late_penalty"
               value="<?= htmlspecialchars($_POST['late_penalty'] ?? '0') ?>"
               min="0" max="100" step="5">
        <p class="form-hint">Deducted from score for late submissions</p>
      </div>
    </div>

    <!-- File attachment for the assignment brief -->
    <div class="form-group">
      <label>Attachment (Optional)</label>
      <div class="upload-zone">
        <input type="file" name="attachment" accept=".pdf,.doc,.docx,.zip">
        <div class="upload-icon"><i class="fa fa-cloud-arrow-up"></i></div>
        <div class="upload-text">
          <strong>Click to upload</strong> or drag and drop an assignment brief
        </div>
        <div class="upload-requirements">
          <span class="upload-tag">PDF</span>
          <span class="upload-tag">DOC</span>
          <span class="upload-tag">DOCX</span>
          <span class="upload-tag">ZIP</span>
          <span class="upload-tag">Max 10 MB</span>
        </div>
      </div>
      <div class="upload-preview">
        <i class="file-icon fa fa-file"></i>
        <span class="upload-preview-name"></span>
        <span class="upload-preview-size"></span>
      </div>
      <div class="progress-wrap">
        <div class="progress-bar"></div>
      </div>
    </div>

    <div class="form-group">
      <label for="status">Visibility</label>
      <select id="status" name="status">
        <option value="published" <?= ($_POST['status']??'published')==='published' ? 'selected':'' ?>>
          Published — visible to students immediately
        </option>
        <option value="draft" <?= ($_POST['status']??'')==='draft' ? 'selected':'' ?>>
          Draft — save without publishing
        </option>
      </select>
    </div>

    <hr class="divider">
    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary btn-lg" data-submit>
        <i class="fa fa-paper-plane"></i> Post Assignment
      </button>
      <a href="/lecturer/dashboard.php" class="btn btn-ghost btn-lg">Cancel</a>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../../views/shared/footer.php'; ?>
