<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/Assignment.php';
require_once __DIR__ . '/../../src/models/Submission.php';

Auth::requireRole('lecturer', APP_URL . '/auth/login.php');
$user = Auth::user();

// Handle grade update (CRUD: Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_submission'])) {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        die('CSRF validation failed');
    }
    $subId    = (int)$_POST['submission_id'];
    $score    = (float)$_POST['score'];
    $feedback = trim($_POST['feedback'] ?? '');
    Submission::grade($subId, $score, $feedback, (int)$user['id']);
    $_SESSION['flash'] = ['type'=>'success','message'=>'Grade saved successfully.'];
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$assignmentId  = (int)($_GET['assignment_id'] ?? 0);
$assignment    = $assignmentId ? Assignment::findById($assignmentId) : null;
$submissions   = $assignmentId ? Submission::forAssignment($assignmentId) : Submission::forLecturer((int)$user['id']);
$stats         = $assignmentId ? Submission::statsForAssignment($assignmentId) : Submission::statsForLecturer((int)$user['id']);
$allAssignments= Assignment::forLecturer((int)$user['id']);

$pageTitle = 'Submissions';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<?php include __DIR__ . '/../../views/shared/header.php'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">
      <?= $assignment ? htmlspecialchars($assignment['title']) : 'All Submissions' ?>
    </h1>
    <?php if ($assignment): ?>
      <p class="page-subtitle">
        <?= htmlspecialchars($assignment['course_code']) ?> &mdash;
        Deadline: <?= date('M j, Y H:i', strtotime($assignment['deadline'])) ?>
      </p>
    <?php endif; ?>
  </div>
  <a href="<?= APP_URL ?>/lecturer/dashboard.php" class="btn btn-ghost">
    <i class="fa fa-arrow-left"></i> Dashboard
  </a>
</div>

<!-- Assignment filter -->
<div class="card mb-2">
  <form method="GET" style="display:flex;gap:1rem;align-items:flex-end">
    <div class="form-group" style="margin:0;flex:1">
      <label>Filter by Assignment</label>
      <select name="assignment_id" onchange="this.form.submit()">
        <option value="">— All Assignments —</option>
        <?php foreach ($allAssignments as $a): ?>
          <option value="<?= $a['id'] ?>" <?= $assignmentId == $a['id'] ? 'selected':'' ?>>
            [<?= htmlspecialchars($a['course_code']) ?>] <?= htmlspecialchars($a['title']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>
</div>

<!-- Stats row -->
<?php if ($stats && $assignmentId): ?>
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr)">
  <div class="stat-card">
    <div class="stat-number"><?= $stats['total'] ?></div>
    <div class="stat-label">Submitted</div>
  </div>
  <div class="stat-card">
    <div class="stat-number text-green"><?= $stats['graded'] ?></div>
    <div class="stat-label">Graded</div>
  </div>
  <div class="stat-card">
    <div class="stat-number text-yellow"><?= $stats['late_count'] ?></div>
    <div class="stat-label">Late</div>
  </div>
  <div class="stat-card">
    <div class="stat-number text-accent"><?= $stats['avg_score'] ? round($stats['avg_score'],1) : '—' ?></div>
    <div class="stat-label">Avg Score</div>
  </div>
</div>
<?php endif; ?>

<!-- Submissions table -->
<div class="card">
  <div class="card-header">
    <h2 class="card-title"><i class="fa fa-inbox text-accent"></i> &nbsp;Submissions</h2>
  </div>

  <?php if (empty($submissions)): ?>
    <div class="empty-state">
      <div class="empty-state-icon"><i class="fa fa-inbox"></i></div>
      <h3>No submissions yet</h3>
      <p>Students haven't submitted their work for this assignment.</p>
    </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Student</th>
          <th>File</th>
          <th>Submitted</th>
          <th>Status</th>
          <th>Score / <?= $assignment ? $assignment['max_score'] : '100' ?></th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($submissions as $sub): ?>
        <tr>
          <td>
            <div class="fw-700"><?= htmlspecialchars($sub['student_name']) ?></div>
            <div class="text-muted" style="font-size:.78rem"><?= htmlspecialchars($sub['student_email']) ?></div>
          </td>
          <td>
            <?php
              $ext = strtolower(pathinfo($sub['original_name'], PATHINFO_EXTENSION));
              $iconClass = match($ext) { 'pdf'=>'file-pdf pdf', 'doc','docx'=>'file-word docx', default=>'file-zipper zip' };
            ?>
            <div style="display:flex;align-items:center;gap:.5rem">
              <i class="fa fa-<?= $iconClass ?> file-icon"></i>
              <div>
                <div style="font-size:.83rem"><?= htmlspecialchars($sub['original_name']) ?></div>
                <div class="text-muted" style="font-size:.75rem">
                  <?= round($sub['file_size']/1024) ?> KB
                </div>
              </div>
            </div>
          </td>
          <td>
            <div><?= date('M j, Y', strtotime($sub['submitted_at'])) ?></div>
            <div class="text-muted" style="font-size:.78rem"><?= date('H:i', strtotime($sub['submitted_at'])) ?></div>
            <?php if ($sub['is_late']): ?>
              <span class="badge badge-warning" style="margin-top:.25rem">Late</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge badge-<?= $sub['status'] ?>">
              <?= ucfirst($sub['status']) ?>
            </span>
          </td>
          <td>
            <?php if ($sub['score'] !== null): ?>
              <span class="score-display text-green"><?= $sub['score'] ?></span>
              <span class="score-max"> / <?= $assignment ? $assignment['max_score'] : 100 ?></span>
            <?php else: ?>
              <!-- Inline grading form -->
              <form method="POST" class="grade-form">
                <input type="hidden" name="csrf_token"      value="<?= Auth::csrfToken() ?>">
                <input type="hidden" name="grade_submission" value="1">
                <input type="hidden" name="submission_id"   value="<?= $sub['id'] ?>">
                <input type="number" name="score" class="grade-input"
                       data-max="<?= $assignment ? $assignment['max_score'] : 100 ?>"
                       min="0" max="<?= $assignment ? $assignment['max_score'] : 100 ?>"
                       step="0.5" placeholder="0" required>
                <button type="submit" class="btn btn-success btn-sm">
                  <i class="fa fa-check"></i>
                </button>
              </form>
            <?php endif; ?>
          </td>
          <td>
            <a href="<?= APP_URL ?>/lecturer/view_submission.php?id=<?= $sub['id'] ?>"
               class="btn btn-ghost btn-sm">
              <i class="fa fa-eye"></i> Detail
            </a>
            <a href="<?= APP_URL . '/' . $sub['file_path'] ?>" download
               class="btn btn-ghost btn-sm">
              <i class="fa fa-download"></i>
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../../views/shared/footer.php'; ?>
