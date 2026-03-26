<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/User.php';

Auth::requireRole('student', '/auth/login.php');
$user = Auth::user();

// Global course catalog fallback to keep 20 units always available for students
$courseCatalog = [
    ['code' => 'CS101', 'title' => 'Introduction to Computer Science'],
    ['code' => 'CS201', 'title' => 'Data Structures and Algorithms'],
    ['code' => 'CS301', 'title' => 'Software Engineering'],
    ['code' => 'CS302', 'title' => 'Database Systems'],
    ['code' => 'CS303', 'title' => 'Web Development'],
    ['code' => 'CS304', 'title' => 'Mobile Application Development'],
    ['code' => 'CS401', 'title' => 'Artificial Intelligence'],
    ['code' => 'CS402', 'title' => 'Machine Learning'],
    ['code' => 'CS403', 'title' => 'Computer Networks'],
    ['code' => 'CS404', 'title' => 'Cybersecurity'],
    ['code' => 'CS501', 'title' => 'Advanced Algorithms'],
    ['code' => 'CS502', 'title' => 'Distributed Systems'],
    ['code' => 'CS503', 'title' => 'Cloud Computing'],
    ['code' => 'CS504', 'title' => 'DevOps and CI/CD'],
    ['code' => 'CS601', 'title' => 'Computer Vision'],
    ['code' => 'CS602', 'title' => 'Natural Language Processing'],
    ['code' => 'CS603', 'title' => 'Blockchain Technology'],
    ['code' => 'CS604', 'title' => 'Internet of Things (IoT)'],
    ['code' => 'CS701', 'title' => 'Research Methods in Computing'],
    ['code' => 'CS702', 'title' => 'Capstone Project']
];

// Ensure all catalog courses exist in database (with a default lecturer for unassigned cases)
$existingCodes = array_column(Database::query('SELECT code FROM courses')->fetchAll(), 'code');
$defaultLecturer = Database::query('SELECT id FROM users WHERE role = "lecturer" ORDER BY id ASC LIMIT 1')->fetch();

foreach ($courseCatalog as $courseDef) {
    if (!in_array($courseDef['code'], $existingCodes, true) && $defaultLecturer) {
        Database::query(
            'INSERT INTO courses (code, title, lecturer_id) VALUES (:code, :title, :lecturer_id)',
            [':code' => $courseDef['code'], ':title' => $courseDef['title'], ':lecturer_id' => $defaultLecturer['id']]
        );
    }
}

// Use directory list from DB for full course availability
$allCourses = Database::query(
    'SELECT c.*, u.name AS lecturer_name FROM courses c JOIN users u ON c.lecturer_id = u.id ORDER BY c.code'
)->fetchAll();

$enrolledCourses = User::enrolledCourses((int)$user['id']);
$enrolledCourseIds = array_column($enrolledCourses, 'id');

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedCourseIds = $_POST['course_ids'] ?? [];

    if (empty($selectedCourseIds)) {
        $errors[] = 'Please select at least one course.';
    } else {
        // Remove existing enrollments
        Database::query('DELETE FROM enrollments WHERE student_id = :sid', [':sid' => $user['id']]);

        // Insert new enrollments
        foreach ($selectedCourseIds as $courseId) {
            Database::query('INSERT INTO enrollments (student_id, course_id) VALUES (:sid, :cid)', [':sid' => $user['id'], ':cid' => (int)$courseId]);
        }

        $success = true;
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Course selections have been saved.'];
        header('Location: /student/dashboard.php');
        exit;
    }
}

$pageTitle = 'Select Courses';
?>
<?php include __DIR__ . '/../../views/shared/header.php'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Select Your Courses</h1>
    <p class="page-subtitle">Choose the courses you're taking this semester</p>
  </div>
</div>

<?php if ($errors): ?>
  <div class="alert alert-error">
    <i class="fa fa-circle-exclamation"></i>
    <ul>
      <?php foreach ($errors as $error): ?>
        <li><?= htmlspecialchars($error) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <h2 class="card-title"><i class="fa fa-book text-accent"></i> &nbsp;Available Courses</h2>
  </div>

  <form method="POST" action="">
    <div class="course-selection-grid">
      <?php foreach ($allCourses as $course): ?>
        <div class="course-selection-card">
          <label class="course-checkbox">
            <input type="checkbox"
                   name="course_ids[]"
                   value="<?= $course['id'] ?>"
                   <?= in_array($course['id'], $enrolledCourseIds) ? 'checked' : '' ?> >
            <div class="course-info">
              <div class="course-code"><?= htmlspecialchars($course['code']) ?></div>
              <div class="course-title"><?= htmlspecialchars($course['title']) ?></div>
              <div class="course-lecturer">Lecturer: <?= htmlspecialchars($course['lecturer_name']) ?></div>
            </div>
          </label>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">
        <i class="fa fa-save"></i> Save Course Selections
      </button>
    </div>
  </form>
</div>

<style>
.course-selection-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
}

.course-selection-card {
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--bg);
}

.course-checkbox {
  display: block;
  padding: 1rem;
  cursor: pointer;
  transition: all 0.2s;
}

.course-checkbox:hover {
  background: var(--bg-hover);
}

.course-checkbox input[type="checkbox"] {
  margin-right: 0.75rem;
  transform: scale(1.2);
}

.course-info {
  display: inline-block;
}

.course-code {
  font-weight: 600;
  color: var(--accent);
  margin-bottom: 0.25rem;
}

.course-title {
  font-weight: 500;
  margin-bottom: 0.25rem;
}

.course-lecturer {
  font-size: 0.875rem;
  color: var(--text-muted);
}

.form-actions {
  padding: 1rem;
  border-top: 1px solid var(--border);
  background: var(--bg-light);
}
</style>

<?php include __DIR__ . '/../../views/shared/footer.php'; ?>