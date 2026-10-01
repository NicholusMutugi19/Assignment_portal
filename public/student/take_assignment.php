<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/Assignment.php';
require_once __DIR__ . '/../../src/models/OnlineAssignment.php';

Auth::requireRole('student', '/auth/login.php');
if (!PORTAL_EXTENSIONS_ENABLED) { http_response_code(503); exit('Online assignments are disabled.'); }
$user = Auth::user();
$id = filter_input(INPUT_GET, 'assignment_id', FILTER_VALIDATE_INT) ?: 0;
$assignment = $id ? Assignment::findById($id) : null;
if (!$assignment || $assignment['assignment_type'] !== 'online' || !Assignment::isAvailableToStudent($id, (int)$user['id'])) { http_response_code(404); exit('Assignment not found.'); }
$questions = OnlineAssignment::questionsForStudent($id);
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) $errors[] = 'Invalid security token.';
    else {
        try {
            OnlineAssignment::submit($id, (int)$user['id'], $_POST['answers'] ?? [], strtotime($assignment['deadline']) < time());
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Your online assignment was submitted. Choice questions are marked automatically; text answers may need lecturer grading.'];
            header('Location: /student/submissions.php'); exit;
        } catch (Throwable $e) {
            error_log('Online assignment submission failed: ' . $e->getMessage());
            $errors[] = $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'Unable to submit. Please try again.';
        }
    }
}
$pageTitle = $assignment['title'];
?>
<?php include __DIR__ . '/../../views/shared/header.php'; ?>
<div class="page-header"><div><h1 class="page-title"><?= htmlspecialchars($assignment['title']) ?></h1><p class="page-subtitle"><?= htmlspecialchars($assignment['course_code'].' — Due '.date('M j, Y H:i', strtotime($assignment['deadline']))) ?></p></div></div>
<div class="card"><p><?= nl2br(htmlspecialchars($assignment['description'])) ?></p></div>
<?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="POST"><input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
<?php foreach ($questions as $index => $question): ?><section class="card"><h2><?= $index + 1 ?>. <?= nl2br(htmlspecialchars($question['question_text'])) ?></h2><p class="form-hint"><?= htmlspecialchars($question['points']) ?> points<?php if ($question['question_type'] === 'multiple_choice'): ?> · Select all that apply<?php endif; ?></p>
<?php if ($question['question_type'] === 'text'): ?><textarea name="answers[<?= (int)$question['id'] ?>]" rows="5" required></textarea>
<?php else: foreach ($question['options'] as $option): ?><label style="display:flex;gap:.6rem;align-items:flex-start;margin:.75rem 0"><input type="<?= $question['question_type'] === 'single_choice' ? 'radio' : 'checkbox' ?>" name="answers[<?= (int)$question['id'] ?>]<?= $question['question_type'] === 'multiple_choice' ? '[]' : '' ?>" value="<?= (int)$option['id'] ?>"><span><?= nl2br(htmlspecialchars($option['option_text'])) ?></span></label><?php endforeach; endif; ?>
</section><?php endforeach; ?>
<button class="btn btn-primary" type="submit" onclick="return confirm('Submit this assignment? You cannot submit it a second time.')">Submit answers</button></form>
<?php include __DIR__ . '/../../views/shared/footer.php'; ?>
