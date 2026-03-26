<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/User.php';

// Predefined list of available courses (same as in create_assignment.php)
$availableCourses = [
    ['id' => 1, 'code' => 'CS101', 'title' => 'Introduction to Computer Science'],
    ['id' => 2, 'code' => 'CS201', 'title' => 'Data Structures and Algorithms'],
    ['id' => 3, 'code' => 'CS301', 'title' => 'Software Engineering'],
    ['id' => 4, 'code' => 'CS302', 'title' => 'Database Systems'],
    ['id' => 5, 'code' => 'CS303', 'title' => 'Web Development'],
    ['id' => 6, 'code' => 'CS304', 'title' => 'Mobile Application Development'],
    ['id' => 7, 'code' => 'CS401', 'title' => 'Artificial Intelligence'],
    ['id' => 8, 'code' => 'CS402', 'title' => 'Machine Learning'],
    ['id' => 9, 'code' => 'CS403', 'title' => 'Computer Networks'],
    ['id' => 10, 'code' => 'CS404', 'title' => 'Cybersecurity'],
    ['id' => 11, 'code' => 'CS501', 'title' => 'Advanced Algorithms'],
    ['id' => 12, 'code' => 'CS502', 'title' => 'Distributed Systems'],
    ['id' => 13, 'code' => 'CS503', 'title' => 'Cloud Computing'],
    ['id' => 14, 'code' => 'CS504', 'title' => 'DevOps and CI/CD'],
    ['id' => 15, 'code' => 'CS601', 'title' => 'Computer Vision'],
    ['id' => 16, 'code' => 'CS602', 'title' => 'Natural Language Processing'],
    ['id' => 17, 'code' => 'CS603', 'title' => 'Blockchain Technology'],
    ['id' => 18, 'code' => 'CS604', 'title' => 'Internet of Things (IoT)'],
    ['id' => 19, 'code' => 'CS701', 'title' => 'Research Methods in Computing'],
    ['id' => 20, 'code' => 'CS702', 'title' => 'Capstone Project']
];

Auth::requireRole('lecturer', '/auth/login.php');
$user = Auth::user();

// Get lecturer's currently taught courses
$taughtCourses = User::taughtCourses((int)$user['id']);
$taughtCourseIds = array_column($taughtCourses, 'id');

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedCourses = $_POST['courses'] ?? [];

    if (empty($selectedCourses)) {
        $errors[] = 'Please select at least one course to teach.';
    } else {
        // Default lecturer fallback is used for non-selected courses to keep catalog entries available for students
        $defaultLecturer = Database::query('SELECT id FROM users WHERE role = \'' . 'lecturer' . '\' ORDER BY id ASC LIMIT 1')->fetch();
        $defaultLecturerId = $defaultLecturer ? (int)$defaultLecturer['id'] : (int)$user['id'];

        // Normalize selected codes from index values
        $selectedCodes = [];
        foreach ($selectedCourses as $courseIndex) {
            $idx = (int)$courseIndex - 1;
            if (isset($availableCourses[$idx])) {
                $selectedCodes[] = $availableCourses[$idx]['code'];
            }
        }
        $selectedCodes = array_unique($selectedCodes);

        foreach ($availableCourses as $course) {
            $courseCode = $course['code'];
            $courseTitle = $course['title'];
            $existing = Database::query(
                'SELECT id, lecturer_id FROM courses WHERE code = :code',
                [':code' => $courseCode]
            )->fetch();

            if (in_array($courseCode, $selectedCodes, true)) {
                if ($existing) {
                    // Assign selected course to this lecturer
                    Database::query(
                        'UPDATE courses SET lecturer_id = :lid WHERE id = :id',
                        [':lid' => $user['id'], ':id' => $existing['id']]
                    );
                } else {
                    Database::query(
                        'INSERT INTO courses (code, title, lecturer_id) VALUES (:code, :title, :lid)',
                        [':code' => $courseCode, ':title' => $courseTitle, ':lid' => $user['id']]
                    );
                }
            } else {
                if ($existing && (int)$existing['lecturer_id'] === (int)$user['id']) {
                    // Release course to default lecturer when unselected
                    Database::query(
                        'UPDATE courses SET lecturer_id = :lid WHERE id = :id',
                        [':lid' => $defaultLecturerId, ':id' => $existing['id']]
                    );
                }
            }
        }

        $success = true;
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Teaching courses updated successfully!'];
        header('Location: /lecturer/dashboard.php');
        exit;
    }
}

$pageTitle = 'Select Teaching Courses';
?>
<?php include __DIR__ . '/../../views/shared/header.php'; ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Select Courses to Teach</h1>
    <p class="page-subtitle">Choose the courses you'll be teaching this semester</p>
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
    <h2 class="card-title"><i class="fa fa-chalkboard-teacher text-accent"></i> &nbsp;Available Courses</h2>
  </div>

  <form method="POST" action="">
    <div class="course-selection-grid">
      <?php foreach ($availableCourses as $index => $course): ?>
        <div class="course-selection-card">
          <label class="course-checkbox">
            <input type="checkbox"
                   name="courses[]"
                   value="<?= $index + 1 ?>"
                   <?= in_array($course['code'], array_column($taughtCourses, 'code')) ? 'checked' : '' ?>>
            <div class="course-info">
              <div class="course-code"><?= htmlspecialchars($course['code']) ?></div>
              <div class="course-title"><?= htmlspecialchars($course['title']) ?></div>
            </div>
          </label>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">
        <i class="fa fa-save"></i> Save Teaching Selections
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

.form-actions {
  padding: 1rem;
  border-top: 1px solid var(--border);
  background: var(--bg-light);
}
</style>

<?php include __DIR__ . '/../../views/shared/footer.php'; ?>