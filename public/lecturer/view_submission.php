<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/Submission.php';
require_once __DIR__ . '/../../src/models/Assignment.php';

Auth::requireRole('lecturer', '/auth/login.php');
$user = Auth::user();

$id  = (int)($_GET['id'] ?? 0);
$sub = Submission::findById($id);
if (!$sub) { header('Location: /lecturer/submissions.php'); exit; }

// Handle grade update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) die('CSRF error');
    $score    = (float)$_POST['score'];
    $feedback = trim($_POST['feedback'] ?? '');
    Submission::grade($id, $score, $feedback, (int)$user['id']);
    $_SESSION['flash'] = ['type'=>'success','message'=>'Grade saved.'];
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$pageTitle = 'Submission Detail';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$ext = strtolower(pathinfo($sub['original_name'], PATHINFO_EXTENSION));
?>
<?php include __DIR__ . '/../../views/shared/header.php'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Submission Detail</h1>
    <p class="page-subtitle"><?= htmlspecialchars($sub['assignment_title']) ?></p>
  </div>
  <a href="/lecturer/submissions.php?assignment_id=<?= $sub['assignment_id'] ?>"
     class="btn btn-ghost"><i class="fa fa-arrow-left"></i> Back</a>
</div>

<div class="detail-grid">
  <!-- Left: info -->
  <div>
    <div class="card" style="margin-bottom:1.25rem">
      <div class="card-header">
        <h2 class="card-title"><i class="fa fa-user text-accent"></i> &nbsp;Student</h2>
      </div>
      <ul class="meta-list">
        <li><span class="meta-key">Name</span>   <span class="meta-val fw-700"><?= htmlspecialchars($sub['student_name']) ?></span></li>
        <li><span class="meta-key">Email</span>  <span class="meta-val"><?= htmlspecialchars($sub['student_email']) ?></span></li>
        <li><span class="meta-key">Course</span> <span class="meta-val"><?= htmlspecialchars($sub['course_code'].' — '.$sub['course_title']) ?></span></li>
      </ul>
    </div>

    <div class="card" style="margin-bottom:1.25rem">
      <div class="card-header">
        <h2 class="card-title"><i class="fa fa-file text-accent"></i> &nbsp;Submitted File</h2>
        <a href="<?= APP_URL . '/' . $sub['file_path'] ?>" download class="btn btn-ghost btn-sm">
          <i class="fa fa-download"></i> Download
        </a>
      </div>
      <ul class="meta-list">
        <li><span class="meta-key">Filename</span>     <span class="meta-val"><?= htmlspecialchars($sub['original_name']) ?></span></li>
        <li><span class="meta-key">Size</span>         <span class="meta-val"><?= round($sub['file_size']/1024,1) ?> KB</span></li>
        <li><span class="meta-key">MIME type</span>    <span class="meta-val"><?= htmlspecialchars($sub['mime_type']) ?></span></li>
        <li><span class="meta-key">Submitted at</span> <span class="meta-val"><?= date('M j, Y H:i', strtotime($sub['submitted_at'])) ?></span></li>
        <li><span class="meta-key">Late?</span>
            <span class="meta-val">
              <?= $sub['is_late'] ? '<span class="badge badge-warning">Late</span>' : '<span class="badge badge-success">On time</span>' ?>
            </span>
        </li>
      </ul>
      <?php if ($sub['comment']): ?>
        <div style="margin-top:1rem;padding:1rem;background:var(--bg);border-radius:var(--radius);font-size:.875rem">
          <div class="text-muted" style="font-size:.78rem;font-weight:600;margin-bottom:.4rem">STUDENT COMMENT</div>
          <?= nl2br(htmlspecialchars($sub['comment'])) ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Right: grading -->
  <div>
    <div class="card">
      <div class="card-header">
        <h2 class="card-title"><i class="fa fa-star text-accent"></i> &nbsp;Grade</h2>
        <?php if ($sub['status'] === 'graded'): ?>
          <span class="badge badge-graded">Graded</span>
        <?php endif; ?>
      </div>

      <?php if ($sub['score'] !== null): ?>
        <div style="text-align:center;padding:1.5rem 0">
          <div style="font-size:3rem;font-family:var(--font-head);font-weight:800;color:var(--green)">
            <?= $sub['score'] ?>
          </div>
          <div class="text-muted">out of <?= $sub['max_score'] ?></div>
          <div style="font-size:1.1rem;margin-top:.5rem;color:var(--accent)">
            <?= round(($sub['score']/$sub['max_score'])*100) ?>%
          </div>
        </div>
        <?php if ($sub['feedback']): ?>
          <div style="padding:.875rem;background:var(--bg);border-radius:var(--radius);font-size:.875rem">
            <div class="text-muted" style="font-size:.78rem;font-weight:600;margin-bottom:.4rem">FEEDBACK</div>
            <?= nl2br(htmlspecialchars($sub['feedback'])) ?>
          </div>
        <?php endif; ?>
        <hr class="divider">
        <p class="text-muted" style="font-size:.8rem">Graded on <?= date('M j, Y H:i', strtotime($sub['graded_at'])) ?></p>
      <?php endif; ?>

      <!-- Grade / re-grade form -->
      <form method="POST" style="margin-top:<?= $sub['score']!==null ? '1rem':'0' ?>">
        <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
        <div class="form-group">
          <label>Score (max <?= $sub['max_score'] ?>)</label>
          <input type="number" name="score" value="<?= $sub['score'] ?? '' ?>"
                 min="0" max="<?= $sub['max_score'] ?>" step="0.5" required>
        </div>
        <div class="form-group">
          <label>Feedback (optional)</label>
          <textarea name="feedback" rows="4" placeholder="Leave feedback for the student..."><?= htmlspecialchars($sub['feedback'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">
          <i class="fa fa-floppy-disk"></i>
          <?= $sub['score'] !== null ? 'Update Grade' : 'Save Grade' ?>
        </button>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../views/shared/footer.php'; ?>
