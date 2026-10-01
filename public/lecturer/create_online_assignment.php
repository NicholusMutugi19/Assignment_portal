<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/User.php';
require_once __DIR__ . '/../../src/models/OnlineAssignment.php';
require_once __DIR__ . '/../../src/models/Assignment.php';
if (!PORTAL_EXTENSIONS_ENABLED) { http_response_code(503); exit('Online assignments are disabled until the extension migration is applied.'); }
$user = Auth::user();
if (!$user['id'] || !in_array($user['role'], ['lecturer','tutor'], true)) { header('Location: /auth/login.php?error=unauthorized'); exit; }
if ($user['role'] === 'lecturer' && User::approvalStatus((int)$user['id']) !== 'approved') { header('Location: /lecturer/pending_approval.php'); exit; }
$courses = User::manageableCourseAssignments((int)$user['id']);
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) $errors[] = 'Invalid security token.';
    $courseId = (int)($_POST['course_id'] ?? 0);
    if (!User::canManageCourse((int)$user['id'], $courseId)) $errors[] = 'Choose a course assigned to your account.';
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $deadline = trim($_POST['deadline'] ?? '');
    if ($title === '' || mb_strlen($title) > 255) $errors[] = 'Assignment title is required (maximum 255 characters).';
    if ($description === '') $errors[] = 'Instructions are required.';
    if (!$deadline || strtotime($deadline) <= time()) $errors[] = 'Choose a future deadline.';
    $texts = $_POST['question_text'] ?? [];
    $types = $_POST['question_type'] ?? [];
    $points = $_POST['question_points'] ?? [];
    $optionsInput = $_POST['options'] ?? [];
    $correctInput = $_POST['correct'] ?? [];
    $questions = [];
    foreach ($texts as $i => $text) {
        $text = trim((string)$text);
        if ($text === '') continue;
        $type = $types[$i] ?? 'text';
        $questionPoints = filter_var($points[$i] ?? 1, FILTER_VALIDATE_FLOAT);
        if (!in_array($type, ['text','single_choice','multiple_choice'], true) || $questionPoints === false || $questionPoints <= 0 || $questionPoints > 1000) {
            $errors[] = 'Each question needs a valid type and positive points.';
            continue;
        }
        $rawOptions = $optionsInput[$i] ?? [];
        $rawCorrect = array_map('intval', $correctInput[$i] ?? []);
        $options = [];
        $correct = [];
        foreach ($rawOptions as $optionIndex => $optionText) {
            $optionText = trim((string)$optionText);
            if ($optionText !== '') {
                $newIndex = count($options);
                $options[] = $optionText;
                if (in_array((int)$optionIndex, $rawCorrect, true)) $correct[] = $newIndex;
            }
        }
        if ($type === 'text' && $options) $errors[] = 'Short-answer questions do not use choices.';
        if ($type !== 'text' && (count($options) < 2 || !$correct)) $errors[] = 'Choice questions require at least two options and one correct option.';
        if ($type === 'single_choice' && count($correct) !== 1) $errors[] = 'Select exactly one correct answer for each single-choice question.';
        $questions[] = ['text' => $text, 'type' => $type, 'points' => (float)$questionPoints, 'options' => $options, 'correct' => $correct];
    }
    if (!$questions) $errors[] = 'Add at least one question.';
    if (!$errors) {
        try {
            $courseOwner = Database::query('SELECT lecturer_id FROM courses WHERE id = :id', [':id' => $courseId])->fetchColumn();
            if (!$courseOwner) throw new RuntimeException('Course owner was not found.');
            $id = OnlineAssignment::create($courseId, (int)$courseOwner, (int)$user['id'], [
                'title' => $title, 'description' => $description,
                'deadline' => date('Y-m-d H:i:s', strtotime($deadline)),
                'allow_late' => !empty($_POST['allow_late']) ? 1 : 0,
                'late_penalty' => (float)($_POST['late_penalty'] ?? 0),
                'status' => in_array($_POST['status'] ?? '', ['draft','published'], true) ? $_POST['status'] : 'draft',
                'tutor_name' => trim($_POST['tutor_name'] ?? ''),
            ], $questions);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Online assignment created.'];
            header('Location: /lecturer/assignments.php'); exit;
        } catch (Throwable $e) {
            error_log('Online assignment creation failed: ' . $e->getMessage());
            $errors[] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Could not create assignment. Please try again.';
        }
    }
}
$pageTitle = 'Create Online Assignment';
?>
<?php include __DIR__ . '/../../views/shared/header.php'; ?>
<div class="page-header"><div><h1 class="page-title">Create online assignment</h1><p class="page-subtitle">No document upload is required. Add text or automatically marked choice questions.</p></div></div>
<?php if ($errors): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="form-card"><form method="POST"><input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
<div class="form-group"><label>Course</label><select name="course_id" required><option value="">Select course</option><?php foreach ($courses as $course): ?><option value="<?= (int)$course['id'] ?>" <?= (($_POST['course_id'] ?? '') == $course['id']) ? 'selected' : '' ?>><?= htmlspecialchars($course['code'].' — '.$course['title']) ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label>Assignment title</label><input name="title" maxlength="255" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"></div><div class="form-group"><label>Instructions</label><textarea name="description" rows="3" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea></div>
<div class="form-row"><div class="form-group"><label>Deadline</label><input name="deadline" type="datetime-local" required value="<?= htmlspecialchars($_POST['deadline'] ?? '') ?>"></div><div class="form-group"><label>Tutor name (optional)</label><input name="tutor_name" maxlength="120" value="<?= htmlspecialchars($_POST['tutor_name'] ?? '') ?>"></div></div>
<div id="question-list"></div><button type="button" class="btn btn-secondary" id="add-question"><i class="fa fa-plus"></i> Add question</button>
<div class="form-group"><label>Visibility</label><select name="status"><option value="draft">Draft</option><option value="published">Publish immediately</option></select></div>
<div class="form-group"><label><input type="checkbox" name="allow_late" value="1"> Allow late submissions</label></div><div class="form-group"><label>Late penalty (%)</label><input type="number" name="late_penalty" min="0" max="100" step="1" value="0"></div>
<button class="btn btn-primary" type="submit">Save online assignment</button></form></div>
<script>
let questionIndex = 0;
function addQuestion() {
  const index = questionIndex++;
  const wrapper = document.createElement('section');
  wrapper.className = 'card'; wrapper.style.marginTop = '1rem';
  wrapper.innerHTML = `<div class="form-group"><label>Question</label><textarea name="question_text[${index}]" required></textarea></div><div class="form-row"><div class="form-group"><label>Question type</label><select name="question_type[${index}]" class="question-type"><option value="text">Short answer (manual grading)</option><option value="single_choice">Single choice</option><option value="multiple_choice">Multiple choice</option></select></div><div class="form-group"><label>Points</label><input type="number" name="question_points[${index}]" value="1" min="0.01" step="0.01" required></div></div><div class="options"></div><button type="button" class="btn btn-ghost btn-sm add-option">Add option</button>`;
  const options = wrapper.querySelector('.options');
  function addOption() { const n = options.children.length; const row = document.createElement('div'); row.className = 'form-row'; row.innerHTML = `<div class="form-group"><label>Option</label><input name="options[${index}][${n}]" maxlength="1000"></div><div class="form-group correct-wrap"><label>Correct?</label><input type="checkbox" name="correct[${index}][]" value="${n}"></div>`; options.append(row); }
  wrapper.querySelector('.add-option').addEventListener('click', addOption);
  wrapper.querySelector('.question-type').addEventListener('change', (event) => { options.hidden = event.target.value === 'text'; wrapper.querySelector('.add-option').hidden = event.target.value === 'text'; });
  document.getElementById('question-list').append(wrapper); addOption(); addOption();
}
document.getElementById('add-question').addEventListener('click', addQuestion); addQuestion();
</script>
<?php include __DIR__ . '/../../views/shared/footer.php'; ?>
