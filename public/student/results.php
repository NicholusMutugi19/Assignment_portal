<?php
require_once __DIR__ . '/../../src/config/database.php'; require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
Auth::requireRole('student','/auth/login.php');
if (!PORTAL_EXTENSIONS_ENABLED) { http_response_code(503); exit('Published results are disabled.'); }
$user=Auth::user();
$educationAudience = EDUCATION_COURSE_TARGETING_ENABLED
 ? " AND (c.audience='both' OR (" . "EXISTS (SELECT 1 FROM users level_student WHERE level_student.id=:level_student AND level_student.education_level='campus') AND c.audience='campus_only') OR (EXISTS (SELECT 1 FROM users level_student2 WHERE level_student2.id=:level_student2 AND level_student2.education_level='high_school') AND c.audience='high_school_only'))"
 : '';
$rows=Database::query("SELECT c.id AS course_id,c.code,c.title AS course_title,u.name AS lecturer_name,c.tutor_name,
 (SELECT SUM(s.score) FROM assignments a JOIN submissions s ON s.assignment_id=a.id WHERE a.course_id=c.id AND s.student_id=:sid2 AND s.score IS NOT NULL AND a.status='published') AS total_score,
 (SELECT SUM(a.max_score) FROM assignments a JOIN submissions s ON s.assignment_id=a.id WHERE a.course_id=c.id AND s.student_id=:sid3 AND s.score IS NOT NULL AND a.status='published') AS max_score,
 (SELECT COUNT(*) FROM assignments a JOIN submissions s ON s.assignment_id=a.id WHERE a.course_id=c.id AND s.student_id=:sid4 AND s.score IS NOT NULL AND a.status='published') AS graded_count
 FROM enrollments e JOIN courses c ON c.id=e.course_id JOIN users u ON u.id=c.lecturer_id
 WHERE e.student_id=:sid AND c.results_published=1 AND e.access_status='active'
 AND e.application_status='approved'
 AND (COALESCE(c.price,0)=0 OR EXISTS (SELECT 1 FROM payments paid WHERE paid.student_id=e.student_id AND paid.course_id=c.id AND paid.payment_status='success'))
 ".$educationAudience."
 ORDER BY c.title",
 EDUCATION_COURSE_TARGETING_ENABLED
 ? [':sid2'=>(int)$user['id'],':sid3'=>(int)$user['id'],':sid4'=>(int)$user['id'],':sid'=>(int)$user['id'],':level_student'=>(int)$user['id'],':level_student2'=>(int)$user['id']]
 : [':sid2'=>(int)$user['id'],':sid3'=>(int)$user['id'],':sid4'=>(int)$user['id'],':sid'=>(int)$user['id']])->fetchAll();
$pageTitle='My published results';
?>
<?php include __DIR__.'/../../views/shared/header.php'; ?>
<div class="page-header"><div><h1 class="page-title">My published results</h1><p class="page-subtitle">Only your own course results are shown here.</p></div></div>
<div class="card"><div class="table-wrap"><table><thead><tr><th>Course</th><th>Lecturer / Tutor</th><th>Score</th><th>Grade</th><th>Assignments graded</th></tr></thead><tbody><?php foreach($rows as $r): $percentage=(float)$r['max_score']>0?round(100*(float)$r['total_score']/(float)$r['max_score'],2):null; ?><tr><td><?= htmlspecialchars($r['code'].' — '.$r['course_title']) ?></td><td><?= htmlspecialchars($r['lecturer_name']) ?><?php if($r['tutor_name']): ?> | <?= htmlspecialchars($r['tutor_name']) ?><?php endif; ?></td><td><?= htmlspecialchars($r['total_score']??'0') ?> / <?= htmlspecialchars($r['max_score']??'0') ?></td><td><?= $percentage===null?'—':$percentage.'%' ?></td><td><?= (int)$r['graded_count'] ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php include __DIR__.'/../../views/shared/footer.php'; ?>
