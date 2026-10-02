<?php
require_once __DIR__ . '/../../src/config/database.php'; require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php'; require_once __DIR__ . '/../../src/models/User.php'; require_once __DIR__ . '/../../src/models/ClassSession.php';
if (!PORTAL_EXTENSIONS_ENABLED) { http_response_code(503); exit('Class sessions are disabled.'); }
$user = Auth::user();
if (!$user['id'] || !in_array($user['role'], ['lecturer','tutor'], true)) { header('Location: /auth/login.php?error=unauthorized'); exit; }
if ($user['role'] === 'lecturer' && User::approvalStatus((int)$user['id']) !== 'approved') { header('Location: /lecturer/pending_approval.php'); exit; }
$courses = User::manageableCourseAssignments((int)$user['id']); $errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) $errors[] = 'Invalid security token.';
  $courseId=(int)($_POST['course_id']??0); $title=trim($_POST['title']??''); $scheduled=trim($_POST['scheduled_at']??'');
  if (!User::canManageCourse((int)$user['id'],$courseId)) $errors[]='Course access denied.';
  if ($title==='' || strlen($title)>200) $errors[]='Session title is required (maximum 200 characters).';
  if (!$scheduled || strtotime($scheduled)<=time()) $errors[]='Choose a future session time.';
  if (!$errors) try { ClassSession::create($courseId,(int)$user['id'],['title'=>$title,'meet_link'=>trim($_POST['meet_link']??''),'scheduled_at'=>date('Y-m-d H:i:s',strtotime($scheduled)),'description'=>$_POST['description']??'','tutor_name'=>$_POST['tutor_name']??'']); $_SESSION['flash']=['type'=>'success','message'=>'Class session published for enrolled, paid students.']; header('Location: /lecturer/class_sessions.php'); exit; } catch(Throwable $e) { error_log('Session creation failed: '.$e->getMessage()); $errors[]=$e instanceof InvalidArgumentException?$e->getMessage():'Unable to create session.'; }
}
$sessions=ClassSession::forManager((int)$user['id']); $pageTitle='Class sessions';
?>
<?php include __DIR__.'/../../views/shared/header.php'; ?>
<div class="page-header"><div><h1 class="page-title">Class sessions</h1><p class="page-subtitle">Meet links are shown only to eligible enrolled students.</p></div></div>
<?php if($errors): ?><div class="alert alert-error"><ul><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="form-card"><form method="POST"><input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>"><div class="form-group"><label>Course</label><select name="course_id" required><?php foreach($courses as $c): ?><option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['code'].' — '.$c['title']) ?></option><?php endforeach; ?></select></div><div class="form-row"><div class="form-group"><label>Session title</label><input name="title" maxlength="200" required></div><div class="form-group"><label>Scheduled at</label><input name="scheduled_at" type="datetime-local" required></div></div><div class="form-row"><div class="form-group"><label>Google Meet URL</label><input name="meet_link" type="url" placeholder="https://meet.google.com/..." required></div><div class="form-group"><label>Tutor name (optional)</label><input name="tutor_name" maxlength="120"></div></div><div class="form-group"><label>Description</label><textarea name="description" rows="3"></textarea></div><button class="btn btn-primary">Publish session</button></form></div>
<div class="card"><div class="card-header"><h2 class="card-title">Your sessions</h2></div><div class="table-wrap"><table><thead><tr><th>Course</th><th>Session</th><th>Scheduled</th><th>Status</th></tr></thead><tbody><?php foreach($sessions as $s): ?><tr><td><?= htmlspecialchars($s['code'].' — '.$s['course_title']) ?></td><td><?= htmlspecialchars($s['title']) ?></td><td><?= htmlspecialchars($s['scheduled_at']) ?></td><td><?= htmlspecialchars($s['status']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php include __DIR__.'/../../views/shared/footer.php'; ?>
