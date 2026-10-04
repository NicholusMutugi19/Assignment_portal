<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/User.php';
require_once __DIR__ . '/../../src/models/RevisionPaper.php';
require_once __DIR__ . '/../../src/models/FileUploader.php';

Auth::requireLogin('/auth/login.php');
$user = Auth::user();
if (!in_array($user['role'], ['lecturer', 'tutor'], true)) { http_response_code(403); exit('Forbidden.'); }
if (!PORTAL_EXTENSIONS_ENABLED) { http_response_code(503); exit('Revision papers are disabled by deployment configuration. Enable PORTAL_EXTENSIONS_ENABLED after verifying migrations.'); }
if ($user['role'] === 'lecturer' && User::approvalStatus((int)$user['id']) !== 'approved') { header('Location: /lecturer/pending_approval.php'); exit; }
$courses = User::manageableCourseAssignments((int)$user['id']);
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Refresh the page and try again.';
    } else {
        try {
            $action = $_POST['action'] ?? 'upload';
            if ($action === 'set_status') {
                $status = $_POST['status'] ?? '';
                if (!RevisionPaper::setStatus((int)$user['id'], (int)($_POST['paper_id'] ?? 0), $status)) {
                    throw new RuntimeException('Revision paper not found or you do not manage its course.');
                }
                $_SESSION['flash'] = ['type' => 'success', 'message' => $status === 'published' ? 'Revision paper is visible to eligible students.' : 'Revision paper archived.'];
                header('Location: /lecturer/revision_papers.php');
                exit;
            }
            RevisionPaper::create((int)$user['id'], (int)($_POST['course_id'] ?? 0), (string)($_POST['title'] ?? ''), (string)($_POST['description'] ?? ''), $_FILES['paper'] ?? []);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Revision paper shared with eligible students in the selected course.'];
            header('Location: /lecturer/revision_papers.php');
            exit;
        } catch (Throwable $e) {
            error_log('Revision-paper action failed: ' . $e->getMessage());
            $errors[] = $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'Unable to save this revision paper.';
        }
    }
}
$papers = RevisionPaper::forManager((int)$user['id']);
$pageTitle = 'Revision Papers';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<?php include __DIR__ . '/../../views/shared/header.php'; ?>
<div class="page-header">
  <div><h1 class="page-title">Revision Papers</h1><p class="page-subtitle">Share course revision materials with students who have access to each course.</p></div>
  <a class="btn btn-secondary" href="/lecturer/dashboard.php"><i class="fa fa-arrow-left"></i> Dashboard</a>
</div>
<?php if ($flash): ?><div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><i class="fa fa-circle-check"></i><?= htmlspecialchars($flash['message']) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-error"><i class="fa fa-circle-exclamation"></i><ul><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="revision-page-grid">
  <section class="form-card revision-upload-card">
    <div class="revision-section-heading"><span class="revision-section-icon"><i class="fa fa-cloud-arrow-up"></i></span><div><h2>Share a revision paper</h2><p>PDF, DOC, DOCX, or ZIP · maximum <?= (int)(UPLOAD_MAX_SIZE / 1048576) ?> MB</p></div></div>
    <?php if (!$courses): ?><div class="empty-state compact-empty-state"><p>You need a course before sharing revision papers.</p><a class="btn btn-primary" href="/lecturer/create_course.php">Create a course</a></div><?php else: ?>
    <form method="POST" enctype="multipart/form-data" class="revision-upload-form">
      <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
      <div class="form-group"><label for="revision-course">Course</label><select id="revision-course" name="course_id" required><option value="">Choose a course</option><?php foreach ($courses as $course): ?><option value="<?= (int)$course['id'] ?>" <?= (int)($_POST['course_id'] ?? 0) === (int)$course['id'] ? 'selected' : '' ?>><?= htmlspecialchars($course['code'] . ' — ' . $course['title']) ?></option><?php endforeach; ?></select></div>
      <div class="form-group"><label for="revision-title">Paper title</label><input id="revision-title" name="title" maxlength="200" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" placeholder="e.g. 2025 end-of-semester revision"></div>
      <div class="form-group"><label for="revision-description">Short description <span class="text-muted">(optional)</span></label><textarea id="revision-description" name="description" rows="3" placeholder="Topics, year, or guidance for students"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea></div>
      <div class="form-group"><label for="revision-file">Revision file</label><input id="revision-file" name="paper" type="file" accept=".pdf,.doc,.docx,.zip" required><p class="form-hint">Files are stored privately and delivered through course-access checks.</p></div>
      <button class="btn btn-primary" type="submit"><i class="fa fa-share-from-square"></i> Publish for students</button>
    </form><?php endif; ?>
  </section>
  <section class="card revision-library-card">
    <div class="card-header"><div><h2 class="card-title"><i class="fa fa-folder-open text-accent"></i> Your shared papers</h2><p class="form-hint"><?= count($papers) ?> resource<?= count($papers) === 1 ? '' : 's' ?></p></div></div>
    <?php if (!$papers): ?><div class="empty-state compact-empty-state"><div class="empty-state-icon"><i class="fa fa-file-circle-question"></i></div><h3>No revision papers yet</h3><p>Your course revision library will appear here after your first upload.</p></div><?php else: ?>
      <div class="revision-resource-list">
      <?php foreach ($papers as $paper): $size = (int)$paper['file_size']; $sizeLabel = $size >= 1048576 ? number_format($size / 1048576, 1) . ' MB' : number_format($size / 1024) . ' KB'; ?>
        <article class="revision-resource-row">
          <span class="revision-file-icon"><i class="fa fa-file-lines"></i></span>
          <div class="revision-resource-main"><div class="revision-resource-title"><?= htmlspecialchars($paper['title']) ?></div><div class="revision-resource-meta"><span><?= htmlspecialchars($paper['course_code'] . ' · ' . $paper['course_title']) ?></span><span><?= htmlspecialchars($paper['original_filename']) ?> · <?= $sizeLabel ?></span><span><?= htmlspecialchars(date('M j, Y', strtotime($paper['created_at']))) ?></span></div></div>
          <span class="badge badge-<?= $paper['status'] === 'published' ? 'success' : 'pending' ?>"><?= htmlspecialchars(ucfirst($paper['status'])) ?></span>
          <form method="POST" class="revision-status-form"><input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>"><input type="hidden" name="action" value="set_status"><input type="hidden" name="paper_id" value="<?= (int)$paper['id'] ?>"><input type="hidden" name="status" value="<?= $paper['status'] === 'published' ? 'archived' : 'published' ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= $paper['status'] === 'published' ? 'Archive' : 'Publish' ?></button></form>
        </article>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
<?php include __DIR__ . '/../../views/shared/footer.php'; ?>
