<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/RevisionPaper.php';

Auth::requireRole('student', '/auth/login.php');
$user = Auth::user();
$papers = PORTAL_EXTENSIONS_ENABLED ? RevisionPaper::forStudent((int)$user['id']) : [];
$byCourse = [];
foreach ($papers as $paper) $byCourse[$paper['course_code'] . ' — ' . $paper['course_title']][] = $paper;
$pageTitle = 'Revision Papers';
?>
<?php include __DIR__ . '/../../views/shared/header.php'; ?>
<div class="page-header"><div><h1 class="page-title">Revision Papers</h1><p class="page-subtitle">Past papers and revision resources shared for courses you can access.</p></div><a href="/student/dashboard.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Dashboard</a></div>
<div class="course-catalog-intro"><i class="fa fa-lock"></i><span>Only published papers for your approved, accessible courses appear here. Paid-course papers unlock after confirmed payment.</span></div>
<?php if (!$papers): ?><div class="card empty-state"><div class="empty-state-icon"><i class="fa fa-file-circle-question"></i></div><h2>No revision papers available yet</h2><p>When your lecturer or tutor shares revision material for an accessible course, it will appear here.</p><a href="/student/courses.php" class="btn btn-secondary mt-2">Browse courses</a></div><?php else: ?>
<div class="revision-course-list">
  <?php foreach ($byCourse as $courseName => $coursePapers): ?>
  <section class="card revision-student-course"><div class="card-header"><div><span class="revision-kicker">COURSE LIBRARY</span><h2 class="card-title"><?= htmlspecialchars($courseName) ?></h2></div><span class="badge badge-info"><?= count($coursePapers) ?> paper<?= count($coursePapers) === 1 ? '' : 's' ?></span></div>
    <div class="revision-resource-list">
      <?php foreach ($coursePapers as $paper): $size = (int)$paper['file_size']; $sizeLabel = $size >= 1048576 ? number_format($size / 1048576, 1) . ' MB' : number_format($size / 1024) . ' KB'; ?>
      <article class="revision-resource-row"><span class="revision-file-icon"><i class="fa fa-file-pdf"></i></span><div class="revision-resource-main"><h3 class="revision-resource-title"><?= htmlspecialchars($paper['title']) ?></h3><?php if (!empty($paper['description'])): ?><p><?= nl2br(htmlspecialchars($paper['description'])) ?></p><?php endif; ?><div class="revision-resource-meta"><span>Shared by <?= htmlspecialchars($paper['lecturer_name']) ?></span><span><?= htmlspecialchars($paper['original_filename']) ?> · <?= $sizeLabel ?></span><span><?= htmlspecialchars(date('M j, Y', strtotime($paper['created_at']))) ?></span></div></div><a class="btn btn-primary btn-sm" href="/student/revision_download.php?id=<?= (int)$paper['id'] ?>"><i class="fa fa-download"></i> Download</a></article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>
</div><?php endif; ?>
<?php include __DIR__ . '/../../views/shared/footer.php'; ?>
