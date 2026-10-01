<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/User.php';
Auth::requireLogin('/auth/login.php');
if (!PORTAL_EXTENSIONS_ENABLED) { http_response_code(503); exit('Course applications are disabled.'); }
$user = Auth::user();
if (!in_array($user['role'], ['lecturer', 'tutor'], true)) { http_response_code(403); exit('Forbidden.'); }
if ($user['role'] === 'lecturer' && User::approvalStatus((int)$user['id']) !== 'approved') { header('Location: /lecturer/pending_approval.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        try {
            User::decideCourseApplication((int)$user['id'], (int)($_POST['student_id'] ?? 0), (int)($_POST['course_id'] ?? 0), $_POST['decision'] ?? '', trim($_POST['note'] ?? ''));
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Course application decision saved.'];
            header('Location: /lecturer/applications.php'); exit;
        } catch (Throwable $e) {
            error_log('Course application decision failed: ' . $e->getMessage());
            $error = $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'Unable to save the application decision.';
        }
    }
}
$applications = User::pendingCourseApplications((int)$user['id']);
$pageTitle = 'Course applications';
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
?>
<?php include __DIR__ . '/../../views/shared/header.php'; ?>
<div class="page-header"><div><h1 class="page-title">Course applications</h1><p class="page-subtitle">Approve or reject eligible students. Paid course materials remain locked until payment also succeeds.</p></div></div>
<?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (!$applications): ?><div class="empty-state"><h3>No pending applications</h3><p>New student applications for your assigned courses will appear here.</p></div><?php else: ?>
<div class="card"><div class="table-wrap"><table><thead><tr><th>Student</th><th>Education</th><th>Course</th><th>Applied</th><th>Decision</th></tr></thead><tbody>
<?php foreach ($applications as $application): ?><tr><td><?= htmlspecialchars($application['student_name']) ?><br><small><?= htmlspecialchars($application['email']) ?></small></td><td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $application['education_level'] ?? 'unknown'))) ?><br><small><?= htmlspecialchars($application['institution_name'] ?? '') ?> · <?= htmlspecialchars($application['year_or_form'] ?? '') ?></small></td><td><?= htmlspecialchars($application['code'] . ' — ' . $application['course_title']) ?></td><td><?= htmlspecialchars($application['enrolled_at']) ?></td><td><form method="POST" style="display:flex;gap:.4rem;flex-wrap:wrap"><input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>"><input type="hidden" name="student_id" value="<?= (int)$application['student_id'] ?>"><input type="hidden" name="course_id" value="<?= (int)$application['course_id'] ?>"><input name="note" maxlength="500" placeholder="Optional note"><button class="btn btn-success btn-sm" name="decision" value="approved">Approve</button><button class="btn btn-danger btn-sm" name="decision" value="rejected">Reject</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></div><?php endif; ?>
<?php include __DIR__ . '/../../views/shared/footer.php'; ?>
