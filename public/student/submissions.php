<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/Submission.php';

Auth::requireRole('student', '/auth/login.php');
$user        = Auth::user();
$submissions = Submission::forStudent((int)$user['id']);

$pageTitle = 'My Submissions';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<?php include __DIR__ . '/../../views/shared/header.php'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">My Submissions</h1>
    <p class="page-subtitle"><?= count($submissions) ?> submission<?= count($submissions)!==1?'s':'' ?></p>
  </div>
</div>

<?php if (empty($submissions)): ?>
  <div class="empty-state">
    <div class="empty-state-icon"><i class="fa fa-file-arrow-up"></i></div>
    <h3>No submissions yet</h3>
    <p>Go to <a href="/student/assignments.php">Assignments</a> to submit your work.</p>
  </div>
<?php else: ?>
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Assignment</th>
          <th>Course</th>
          <th>File</th>
          <th>Submitted</th>
          <th>Status</th>
          <th>Score</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($submissions as $s):
        $ext = strtolower(pathinfo($s['original_name'], PATHINFO_EXTENSION));
        $iconCls = match($ext) {
          'pdf'          => 'file-pdf pdf',
          'doc', 'docx'  => 'file-word docx',
          default        => 'file-zipper zip',
        };
      ?>
        <tr>
          <td class="fw-700"><?= htmlspecialchars($s['assignment_title']) ?></td>
          <td><span class="badge badge-info"><?= htmlspecialchars($s['course_code']) ?></span></td>
          <td>
            <div style="display:flex;align-items:center;gap:.5rem">
              <i class="fa fa-<?= $iconCls ?> file-icon"></i>
              <div>
                <div style="font-size:.83rem"><?= htmlspecialchars($s['original_name']) ?></div>
                <div class="text-muted" style="font-size:.75rem"><?= round($s['file_size']/1024) ?> KB</div>
              </div>
            </div>
          </td>
          <td>
            <?= date('M j, Y H:i', strtotime($s['submitted_at'])) ?>
            <?php if ($s['is_late']): ?>
              <span class="badge badge-warning" style="margin-left:.5rem">Late</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge badge-<?= $s['status'] ?>">
              <?= ucfirst($s['status']) ?>
            </span>
          </td>
          <td>
            <?php if ($s['score'] !== null): ?>
              <span class="score-display text-green"><?= $s['score'] ?></span>
              <span class="score-max"> / <?= $s['max_score'] ?>
                (<?= round(($s['score']/$s['max_score'])*100) ?>%)
              </span>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <!-- Feedback row -->
        <?php if ($s['status'] === 'graded' && $s['feedback']): ?>
        <tr style="background:var(--bg-card2)">
          <td colspan="6" style="padding:.5rem 1rem .875rem">
            <div class="text-muted" style="font-size:.75rem;font-weight:600;margin-bottom:.25rem">LECTURER FEEDBACK</div>
            <div style="font-size:.85rem"><?= nl2br(htmlspecialchars($s['feedback'])) ?></div>
          </td>
        </tr>
        <?php endif; ?>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../views/shared/footer.php'; ?>
