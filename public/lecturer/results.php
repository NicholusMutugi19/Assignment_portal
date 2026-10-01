<?php
require_once __DIR__ . '/../../src/config/database.php'; require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php'; require_once __DIR__ . '/../../src/models/User.php';
if (!PORTAL_EXTENSIONS_ENABLED) { http_response_code(503); exit('Results are disabled until the required migration is applied.'); }
$user=Auth::user(); if (!$user['id'] || !in_array($user['role'],['lecturer','tutor'],true)) { header('Location: /auth/login.php?error=unauthorized'); exit; }
if ($user['role']==='lecturer' && User::approvalStatus((int)$user['id'])!=='approved') { header('Location: /lecturer/pending_approval.php'); exit; }
$courses=User::manageableCourseAssignments((int)$user['id']); $courseId=(int)($_GET['course_id']??($courses[0]['id']??0));
if (!$courseId || !User::canManageCourse((int)$user['id'],$courseId)) { http_response_code(403); exit('Course access denied.'); }
$course=Database::query(
  PORTAL_EXTENSIONS_ENABLED
    ? 'SELECT c.*,u.name AS lecturer_name FROM courses c JOIN users u ON u.id=c.lecturer_id WHERE c.id=:id'
    : 'SELECT c.*,u.name AS lecturer_name, NULL AS tutor_name, 0 AS results_published FROM courses c JOIN users u ON u.id=c.lecturer_id WHERE c.id=:id',
  [':id'=>$courseId]
)->fetch();
if (!$course) { http_response_code(404); exit('Course not found.'); }
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!Auth::verifyCsrf($_POST['csrf_token']??'')) { http_response_code(400); exit('Invalid security token.'); }
  $published=isset($_POST['publish']);
  $publicationSql = PORTAL_EXTENSIONS_ENABLED
    ? 'UPDATE courses SET results_published=:published WHERE id=:id AND (lecturer_id=:owner OR tutor_id=:tutor)'
    : 'UPDATE courses SET results_published=:published WHERE id=:id AND lecturer_id=:owner';
  $publicationParams = PORTAL_EXTENSIONS_ENABLED
    ? [':published'=>$published?1:0,':id'=>$courseId,':owner'=>(int)$user['id'],':tutor'=>(int)$user['id']]
    : [':published'=>$published?1:0,':id'=>$courseId,':owner'=>(int)$user['id']];
  $updated = Database::query($publicationSql, $publicationParams)->rowCount();
  if ($updated === 0 && !User::canManageCourse((int)$user['id'], $courseId)) { http_response_code(403); exit('Course access denied.'); }
  $_SESSION['flash']=['type'=>'success','message'=>$published?'Results are now visible to students in this course.':'Results are hidden from students.']; header('Location: /lecturer/results.php?course_id='.$courseId); exit;
}
$eligibility=EDUCATION_COURSE_TARGETING_ENABLED ? " AND (u.education_level IS NULL OR c.audience='both' OR (u.education_level='campus' AND c.audience='campus_only') OR (u.education_level='high_school' AND c.audience='high_school_only'))" : '';
$courseFilter = EDUCATION_COURSE_TARGETING_ENABLED ? ' AND c.id = :course_id2' : '';
$scoreSubquery = PORTAL_EXTENSIONS_ENABLED
 ? 'JOIN submissions s ON s.assignment_id=a.id AND s.student_id=u.id AND s.score IS NOT NULL'
 : "JOIN submissions s ON s.assignment_id=a.id AND s.student_id=u.id AND s.score IS NOT NULL AND s.status = 'graded'";
$applicationFilter = PORTAL_EXTENSIONS_ENABLED ? " AND e.application_status='approved'" : '';
$paymentFilter = PORTAL_EXTENSIONS_ENABLED ? " AND (COALESCE(c.price,0)=0 OR EXISTS (SELECT 1 FROM payments paid WHERE paid.student_id=u.id AND paid.course_id=c.id AND paid.payment_status='success'))" : '';
$courseWhere = PORTAL_EXTENSIONS_ENABLED
 ? ' WHERE c.id=:course_id'
 : ' WHERE c.id=:course_id';
$educationColumn = EDUCATION_COURSE_TARGETING_ENABLED ? 'u.education_level' : 'NULL AS education_level';
$groupColumns = EDUCATION_COURSE_TARGETING_ENABLED ? 'u.id,u.name,u.email,u.education_level' : 'u.id,u.name,u.email';
$rows=Database::query("SELECT u.id AS student_id,u.name AS student_name,u.email," . $educationColumn . ",
 SUM(s.score) AS total_score,SUM(a.max_score) AS max_score,
 CASE WHEN SUM(a.max_score)>0 THEN ROUND(100*SUM(s.score)/SUM(a.max_score),2) ELSE NULL END AS percentage,
 COUNT(s.id) AS graded_assignments
 FROM users u JOIN enrollments e ON e.student_id=u.id
 JOIN courses c ON c.id=e.course_id
 JOIN assignments a ON a.course_id=c.id AND a.status='published'
 ".$scoreSubquery."
".$courseWhere.$applicationFilter.$paymentFilter.$eligibility.$courseFilter.
 " GROUP BY " . $groupColumns . " ORDER BY percentage DESC,u.name ASC",EDUCATION_COURSE_TARGETING_ENABLED ? [':course_id'=>$courseId,':course_id2'=>$courseId] : [':course_id'=>$courseId])->fetchAll();
foreach($rows as $i=>&$row) $row['rank']=$i+1; unset($row);
if (PORTAL_EXTENSIONS_ENABLED) {
  $rowCount = count($rows);
  foreach ($rows as $index => &$rankedRow) {
    $rankedRow['position'] = $rowCount ? round(100 * ($rowCount - $index) / $rowCount, 2) : null;
  }
  unset($rankedRow);
}
if (($_GET['export']??'')==='csv') {
 header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="course-results-'.$courseId.'.csv"');
 $out=fopen('php://output','w'); fputcsv($out,['Rank','Student','Student ID','Education level','Score','Max score','Percent','Graded assignments']); foreach($rows as $r) fputcsv($out,[$r['rank'],$r['student_name'],$r['student_id'],$r['education_level'],$r['total_score'],$r['max_score'],$r['percentage'],$r['graded_assignments']]); fclose($out); exit;
}
$print=($_GET['print']??'')==='1';
if($print): ?><!doctype html><html><head><meta charset="utf-8"><title><?= htmlspecialchars($course['title']) ?> results</title><style>body{font:14px Arial;margin:30px;color:#111}h1,h2{text-align:center}table{border-collapse:collapse;width:100%;margin-top:24px}th,td{border:1px solid #555;padding:8px;text-align:left}@media print{button{display:none}}</style></head><body><h1>Assignment Portal</h1><h2><?= htmlspecialchars($course['title']) ?> — Ranked Results</h2><p>Lecturer: <?= htmlspecialchars($course['lecturer_name']) ?><?php if($course['tutor_name']): ?> | Tutor: <?= htmlspecialchars($course['tutor_name']) ?><?php endif; ?><br>Generated: <?= htmlspecialchars(date('Y-m-d H:i')) ?></p><table><thead><tr><th>Rank</th><th>Student</th><th>Student ID</th><th>Score</th><th>Grade (%)</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= $r['rank'] ?></td><td><?= htmlspecialchars($r['student_name']) ?></td><td><?= $r['student_id'] ?></td><td><?= $r['total_score'] ?> / <?= $r['max_score'] ?></td><td><?= $r['percentage'] ?></td></tr><?php endforeach; ?></tbody></table><script>window.print()</script></body></html><?php exit; endif;
$pageTitle='Course results'; $flash=$_SESSION['flash']??null;unset($_SESSION['flash']);
?>
<?php include __DIR__.'/../../views/shared/header.php'; ?>
<div class="page-header"><div><h1 class="page-title">Ranked results</h1><p class="page-subtitle">Ranking is the weighted aggregate of existing graded assignment marks: total earned / total available.</p></div></div>
<form method="GET" class="card"><label>Course</label><select name="course_id" onchange="this.form.submit()"><?php foreach($courses as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $c['id']==$courseId?'selected':'' ?>><?= htmlspecialchars($c['code'].' — '.$c['title']) ?></option><?php endforeach; ?></select></form>
<div class="card"><div class="card-header"><h2 class="card-title"><?= htmlspecialchars($course['title']) ?></h2><div><a class="btn btn-secondary btn-sm" href="?course_id=<?= $courseId ?>&export=csv">CSV</a> <a class="btn btn-secondary btn-sm" target="_blank" href="?course_id=<?= $courseId ?>&print=1">Print / PDF</a></div></div><form method="POST"><input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>"><label><input type="checkbox" name="publish" value="1" <?= $course['results_published']?'checked':'' ?>> Publish results to students (each student sees only their own result)</label> <button class="btn btn-primary btn-sm">Save visibility</button></form><div class="table-wrap"><table><thead><tr><th>Rank</th><th>Student</th><th>Education</th><th>Score</th><th>Grade</th><th>Position</th><th>Assignments graded</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= $r['rank'] ?></td><td><?= htmlspecialchars($r['student_name']) ?></td><td><?= htmlspecialchars($r['education_level']??'—') ?></td><td><?= $r['total_score'] ?> / <?= $r['max_score'] ?></td><td><?= $r['percentage'] ?>%</td><td><?= htmlspecialchars((string)($r['position'] ?? '—')) ?>%</td><td><?= $r['graded_assignments'] ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php include __DIR__.'/../../views/shared/footer.php'; ?>
