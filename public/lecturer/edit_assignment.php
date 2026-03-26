<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/Assignment.php';

Auth::requireRole('lecturer', APP_URL . '/auth/login.php');
$user = Auth::user();

$id         = (int)($_GET['id'] ?? 0);
$assignment = Assignment::findById($id);
if (!$assignment || $assignment['lecturer_id'] != $user['id']) {
    header('Location: ' . APP_URL . '/lecturer/assignments.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form token.';
    } else {
        $title        = trim($_POST['title']       ?? '');
        $description  = trim($_POST['description'] ?? '');
        $deadline     = trim($_POST['deadline']    ?? '');
        $max_score    = (float)($_POST['max_score'] ?? 100);
        $allow_late   = isset($_POST['allow_late']) ? 1 : 0;
        $late_penalty = (float)($_POST['late_penalty'] ?? 0);
        $status       = in_array($_POST['status']??'', ['draft','published','closed']) ? $_POST['status'] : 'published';

        if (!$title)      $errors[] = 'Title is required.';
        if (!$description)$errors[] = 'Description is required.';
        if (!$deadline)   $errors[] = 'Deadline is required.';

        if (empty($errors)) {
            Assignment::update($id, [
                'title'        => $title,
                'description'  => $description,
                'max_score'    => $max_score,
                'deadline'     => date('Y-m-d H:i:s', strtotime($deadline)),
                'allow_late'   => $allow_late,
                'late_penalty' => $late_penalty,
                'status'       => $status,
            ]);
            $_SESSION['flash'] = ['type'=>'success','message'=>'Assignment updated successfully.'];
            header('Location: ' . APP_URL . '/lecturer/assignments.php');
            exit;
        }
    }
}

// Pre-fill from POST or DB
$vals = $_SERVER['REQUEST_METHOD']==='POST' ? $_POST : [
    'title'        => $assignment['title'],
    'description'  => $assignment['description'],
    'max_score'    => $assignment['max_score'],
    'deadline'     => date('Y-m-d\TH:i', strtotime($assignment['deadline'])),
    'allow_late'   => $assignment['allow_late'],
    'late_penalty' => $assignment['late_penalty'],
    'status'       => $assignment['status'],
];

$pageTitle = 'Edit Assignment';
$flash = null;
?>
<?php include __DIR__ . '/../../views/shared/header.php'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Edit Assignment</h1>
    <p class="page-subtitle"><?= htmlspecialchars($assignment['course_code'].' — '.$assignment['course_title']) ?></p>
  </div>
  <a href="<?= APP_URL ?>/lecturer/assignments.php" class="btn btn-ghost">
    <i class="fa fa-arrow-left"></i> Back
  </a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-error">
    <i class="fa fa-circle-exclamation"></i>
    <ul style="margin:.25rem 0 0 1rem">
      <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="form-card">
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">

    <div class="form-group">
      <label>Title</label>
      <input type="text" name="title" value="<?= htmlspecialchars($vals['title']) ?>" required>
    </div>
    <div class="form-group">
      <label>Description</label>
      <textarea name="description" rows="6" required><?= htmlspecialchars($vals['description']) ?></textarea>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Deadline</label>
        <input type="datetime-local" name="deadline" value="<?= htmlspecialchars($vals['deadline']) ?>" required>
      </div>
      <div class="form-group">
        <label>Max Score</label>
        <input type="number" name="max_score" value="<?= $vals['max_score'] ?>" min="1" max="1000" step="0.5">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:.5rem;text-transform:none;font-size:.9rem;font-weight:600;">
          <input type="checkbox" name="allow_late" style="width:auto"
                 <?= $vals['allow_late'] ? 'checked':'' ?>>
          Allow Late Submissions
        </label>
      </div>
      <div class="form-group">
        <label>Late Penalty (%)</label>
        <input type="number" name="late_penalty" value="<?= $vals['late_penalty'] ?>" min="0" max="100" step="5">
      </div>
    </div>
    <div class="form-group">
      <label>Status</label>
      <select name="status">
        <option value="published" <?= $vals['status']==='published'?'selected':'' ?>>Published</option>
        <option value="draft"     <?= $vals['status']==='draft'    ?'selected':'' ?>>Draft</option>
        <option value="closed"    <?= $vals['status']==='closed'   ?'selected':'' ?>>Closed</option>
      </select>
    </div>
    <hr class="divider">
    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary btn-lg">
        <i class="fa fa-floppy-disk"></i> Save Changes
      </button>
      <a href="<?= APP_URL ?>/lecturer/assignments.php" class="btn btn-ghost btn-lg">Cancel</a>
    </div>
  </form>
</div>

<?php include __DIR__ . '/../../views/shared/footer.php'; ?>
