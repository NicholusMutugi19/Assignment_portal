<?php
/**
 * Student Submission Page
 * Handles: deadline enforcement, file validation, CSRF, duplicate prevention
 */

require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/Assignment.php';
require_once __DIR__ . '/../../src/models/Submission.php';
require_once __DIR__ . '/../../src/models/FileUploader.php';

Auth::requireRole('student', '/auth/login.php');
$user = Auth::user();

$assignmentId = (int)($_GET['assignment_id'] ?? 0);
$assignment   = $assignmentId ? Assignment::findById($assignmentId) : null;

if (!$assignment) {
    header('Location: /student/assignments.php');
    exit;
}

// ── Deadline & status enforcement ──────────────────────────────
$accepting  = Assignment::isAcceptingSubmissions($assignment);
$isPastDl   = Assignment::isPastDeadline($assignment);
$isLate     = $isPastDl && $accepting;  // past but late allowed
$alreadySub = Submission::existsByStudentAndAssignment((int)$user['id'], $assignmentId);

// Prevent duplicate submission
if ($alreadySub) {
    $_SESSION['flash'] = ['type'=>'warning','message'=>'You have already submitted this assignment.'];
    header('Location: /student/submissions.php');
    exit;
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token mismatch. Please refresh the page.';
    }

    // Re-check deadline at submit time (race-condition prevention)
    elseif (!Assignment::isAcceptingSubmissions($assignment)) {
        $errors[] = 'The submission deadline has passed and late submissions are not allowed.';
    }

    else {
        // File required
        if (empty($_FILES['submission_file']['name'])) {
            $errors[] = 'Please select a file to upload.';
        } else {
            $destDir  = __DIR__ . '/../' . UPLOAD_DIR_SUBMISSIONS;
            $uploader = new FileUploader($destDir);
            $result   = $uploader->handle($_FILES['submission_file']);

            if (!$result['ok']) {
                $errors = array_merge($errors, $result['errors']);
            } else {
                // Build relative path for DB (relative to public/)
                $relativePath = UPLOAD_DIR_SUBMISSIONS . $result['stored_name'];

                $subId = Submission::create([
                    'assignment_id' => $assignmentId,
                    'student_id'    => $user['id'],
                    'file_path'     => $relativePath,
                    'original_name' => $result['original_name'],
                    'file_size'     => $result['size'],
                    'mime_type'     => $result['mime'],
                    'comment'       => trim($_POST['comment'] ?? ''),
                    'is_late'       => $isLate ? 1 : 0,
                ]);

                $_SESSION['flash'] = [
                    'type'    => 'success',
                    'message' => 'Your work has been submitted successfully!' .
                                 ($isLate ? ' (Late — penalty may apply)' : ''),
                ];
                header('Location: /student/submissions.php');
                exit;
            }
        }
    }
}

$pageTitle   = 'Submit Assignment';
$timeLeft    = Assignment::timeRemaining($assignment);
$flash       = null;
?>
<?php include __DIR__ . '/../../views/shared/header.php'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Submit Assignment</h1>
    <p class="page-subtitle"><?= htmlspecialchars($assignment['course_code'].' — '.$assignment['title']) ?></p>
  </div>
  <a href="/student/assignments.php" class="btn btn-ghost">
    <i class="fa fa-arrow-left"></i> Back
  </a>
</div>

<!-- Assignment info banner -->
<div class="card" style="margin-bottom:1.5rem;border-color:<?= $isLate?'var(--yellow)':($accepting?'var(--accent)':'var(--red)') ?>">
  <div class="d-flex justify-between align-center flex-wrap gap-2">
    <div>
      <div class="fw-700" style="font-size:1.05rem"><?= htmlspecialchars($assignment['title']) ?></div>
      <div class="text-muted" style="font-size:.85rem;margin-top:.25rem">
        <?= nl2br(htmlspecialchars(mb_strimwidth($assignment['description'], 0, 200, '…'))) ?>
      </div>
      <?php if (!empty($assignment['attachment_path'])): ?>
        <div style="margin-top:.5rem">
          <a href="/student/download.php?assignment_id=<?= $assignment['id'] ?>" 
             class="btn btn-sm btn-outline" target="_blank">
            <i class="fa fa-download"></i> Download Assignment File
          </a>
        </div>
      <?php endif; ?>
    </div>
    <div style="text-align:right">
      <div class="deadline-pill <?= $isPastDl ? ($isLate?'urgent':'past') : 'soon' ?>">
        <i class="fa fa-clock"></i>
        <?= $isPastDl ? 'Deadline passed' : date('M j, Y H:i', strtotime($assignment['deadline'])) ?>
      </div>
      <?php if (!$isPastDl): ?>
        <div style="font-size:.8rem;color:var(--text-muted);margin-top:.35rem">
          <span data-deadline="<?= date('c', strtotime($assignment['deadline'])) ?>">
            <?= $timeLeft ?>
          </span>
        </div>
      <?php elseif ($isLate): ?>
        <div class="text-yellow" style="font-size:.8rem;margin-top:.25rem">
          <i class="fa fa-triangle-exclamation"></i>
          Late submission — <?= $assignment['late_penalty'] ?>% penalty applies
        </div>
      <?php endif; ?>
      <div class="text-muted" style="font-size:.78rem;margin-top:.25rem">
        Max score: <?= $assignment['max_score'] ?> pts
      </div>
    </div>
  </div>
</div>

<?php if (!$accepting): ?>
  <!-- Closed -->
  <div class="alert alert-error">
    <i class="fa fa-lock"></i>
    <div>
      <strong>Submissions are closed.</strong>
      The deadline for this assignment has passed and late submissions are not allowed.
    </div>
  </div>
<?php else: ?>

  <?php if ($errors): ?>
    <div class="alert alert-error">
      <i class="fa fa-circle-exclamation"></i>
      <div>
        <strong>Upload failed. Please fix the following:</strong>
        <ul style="margin:.5rem 0 0 1rem">
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($isLate): ?>
    <div class="alert alert-warning">
      <i class="fa fa-triangle-exclamation"></i>
      <strong>You are submitting past the deadline.</strong>
      A penalty of <strong><?= $assignment['late_penalty'] ?>%</strong> will be deducted from your score.
    </div>
  <?php endif; ?>

  <div class="form-card">
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token"     value="<?= Auth::csrfToken() ?>">
      <input type="hidden" name="MAX_FILE_SIZE"  value="<?= UPLOAD_MAX_SIZE ?>">

      <!-- File upload -->
      <div class="form-group">
        <label>Your Submission File <span style="color:var(--red)">*</span></label>
        <div class="upload-zone">
          <input type="file" name="submission_file" accept=".pdf,.doc,.docx,.zip" required>
          <div class="upload-icon"><i class="fa fa-cloud-arrow-up"></i></div>
          <div class="upload-text">
            <strong>Click to browse</strong> or drag your file here
          </div>
          <div class="upload-requirements">
            <span class="upload-tag"><i class="fa fa-file-pdf" style="color:var(--red)"></i> PDF</span>
            <span class="upload-tag"><i class="fa fa-file-word" style="color:#3b82f6"></i> DOC / DOCX</span>
            <span class="upload-tag"><i class="fa fa-file-zipper" style="color:var(--yellow)"></i> ZIP</span>
            <span class="upload-tag">Max 10 MB</span>
          </div>
        </div>

        <!-- Preview -->
        <div class="upload-preview">
          <i class="file-icon fa fa-file"></i>
          <span class="upload-preview-name"></span>
          <span class="upload-preview-size"></span>
          <i class="fa fa-circle-check" style="color:var(--green);margin-left:auto"></i>
        </div>

        <!-- Upload progress -->
        <div class="progress-wrap">
          <div class="progress-bar"></div>
        </div>
        <p class="form-hint">
          Only PDF, DOC, DOCX, and ZIP files are accepted. Files are scanned for MIME type
          on the server — the extension alone is not sufficient.
        </p>
      </div>

      <!-- Optional comment -->
      <div class="form-group">
        <label for="comment">Note to Lecturer <span class="text-muted">(optional)</span></label>
        <textarea id="comment" name="comment" rows="3"
                  placeholder="Any notes or comments about your submission..."
                  ><?= htmlspecialchars($_POST['comment'] ?? '') ?></textarea>
      </div>

      <hr class="divider">

      <!-- Submission checklist -->
      <div style="background:var(--bg-card2);border-radius:var(--radius);padding:1rem;margin-bottom:1.5rem">
        <div style="font-size:.8rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text-muted);margin-bottom:.75rem">
          Before You Submit
        </div>
        <ul style="list-style:none;display:flex;flex-direction:column;gap:.5rem">
          <li style="font-size:.875rem"><i class="fa fa-circle-check text-green"></i> &nbsp;My file is in PDF, DOC, DOCX, or ZIP format</li>
          <li style="font-size:.875rem"><i class="fa fa-circle-check text-green"></i> &nbsp;My file is smaller than 10 MB</li>
          <li style="font-size:.875rem"><i class="fa fa-circle-check text-green"></i> &nbsp;I have reviewed my work before submitting</li>
          <li style="font-size:.875rem;color:var(--yellow)"><i class="fa fa-triangle-exclamation"></i> &nbsp;Once submitted, you cannot resubmit</li>
        </ul>
      </div>

      <button type="submit" class="btn btn-primary btn-lg" data-submit style="width:100%;justify-content:center">
        <i class="fa fa-paper-plane"></i>
        <?= $isLate ? 'Submit Late' : 'Submit Assignment' ?>
      </button>
    </form>
  </div>

<?php endif; // $accepting ?>

<?php include __DIR__ . '/../../views/shared/footer.php'; ?>
