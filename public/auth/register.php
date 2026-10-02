<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/User.php';
require_once __DIR__ . '/../../src/models/Admin.php';

Auth::start();
if (MAINTENANCE_MODE) {
  http_response_code(503);
  header('Retry-After: 300');
  exit('The portal is temporarily unavailable for maintenance.');
}
if (PORTAL_EXTENSIONS_ENABLED && Admin::setting('new_registrations_enabled', '1') !== '1') {
  http_response_code(503);
  exit('New registrations are temporarily disabled.');
}
if (Auth::isLoggedIn()) {
    header('Location: /' . Auth::user()['role'] . '/dashboard.php');
    exit;
}

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = in_array($_POST['role'] ?? '', ['student','lecturer']) ? $_POST['role'] : 'student';
    $educationLevel = $_POST['education_level'] ?? '';
    $institutionName = trim($_POST['institution_name'] ?? '');
    $yearOrForm = trim($_POST['year_or_form'] ?? '');

    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
      $error = 'Invalid security token. Please refresh the page and try again.';
    } elseif (!$name || !$email || !$password) {
        $error = 'All fields are required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (User::findByEmail($email)) {
        $error = 'An account with that email already exists.';
    } elseif (EDUCATION_COURSE_TARGETING_ENABLED && $role === 'student' &&
      (!in_array($educationLevel, ['campus', 'high_school'], true) || $institutionName === '' || $yearOrForm === '')) {
      $error = 'Please provide your education level, institution, and year or form.';
    } else {
        try {
        $userId = User::create([
          'name' => $name,
          'email' => $email,
          'password' => $password,
          'role' => $role,
          'education_level' => $educationLevel,
          'institution_name' => $institutionName,
          'year_or_form' => $yearOrForm,
        ]);

        if (PORTAL_EXTENSIONS_ENABLED && $role === 'lecturer') {
          $_SESSION['user_id'] = $userId;
          $_SESSION['user_role'] = $role;
          header('Location: /lecturer/pending_approval.php');
          exit;
        }

            // Log the user in automatically
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_role'] = $role;

            // Redirect to course selection
            if ($role === 'student') {
                header('Location: /student/courses.php');
            } else {
                header('Location: /lecturer/select_courses.php');
            }
            exit;
        } catch (Exception $e) {
            $error = 'Failed to create account. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/app.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="auth-body">
<div class="auth-page">
  <div class="auth-card">
    <a class="auth-brand" href="/" aria-label="<?= htmlspecialchars(APP_NAME) ?> home">
      <span class="auth-logo-icon">⬡</span>
      <span><?= APP_NAME ?></span>
    </a>
    <div class="auth-heading">
      <span class="auth-eyebrow">Get started</span>
      <h1 class="auth-title">Create your account</h1>
      <p class="auth-subtitle">Join your learning community in a few steps.</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><i class="fa fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><i class="fa fa-circle-check"></i> <?= $success ?></div>
    <?php endif; ?>

    <form method="POST" class="auth-form">
      <input type="hidden" name="csrf_token" value="<?= Auth::csrfToken() ?>">
      <div class="form-group">
        <label for="full-name">Full Name</label>
        <input id="full-name" type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="Enter your full name" autocomplete="name" maxlength="120" required>
      </div>
      <div class="form-group">
        <label for="register-email">Email</label>
        <input id="register-email" type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="you@example.com" autocomplete="email" required>
      </div>
      <div class="form-group">
        <label for="register-password">Password</label>
        <input id="register-password" type="password" name="password" placeholder="At least 6 characters" autocomplete="new-password" minlength="6" required>
        <p class="form-hint">Use at least 6 characters. Choose a unique password.</p>
      </div>
      <div class="form-group">
        <label for="account-role">I am joining as</label>
        <select name="role" id="account-role">
          <option value="student"  <?= ($_POST['role']??'student')==='student'  ? 'selected' : '' ?>>Student</option>
          <option value="lecturer" <?= ($_POST['role']??'')==='lecturer' ? 'selected' : '' ?>>Lecturer</option>
        </select>
      </div>
      <?php if (EDUCATION_COURSE_TARGETING_ENABLED): ?>
      <fieldset id="student-education-fields" class="auth-education-fields">
        <legend>Education level</legend>
        <div class="form-group">
          <label for="education-level">Where are you in your studies?</label>
          <select name="education_level" id="education-level">
            <option value="">Select a level</option>
            <option value="campus" <?= ($_POST['education_level'] ?? '') === 'campus' ? 'selected' : '' ?>>Campus / University / College</option>
            <option value="high_school" <?= ($_POST['education_level'] ?? '') === 'high_school' ? 'selected' : '' ?>>High School</option>
          </select>
        </div>
        <div class="form-group">
          <label for="institution-name">Institution or school name</label>
          <input id="institution-name" name="institution_name" value="<?= htmlspecialchars($_POST['institution_name'] ?? '') ?>" maxlength="180">
        </div>
        <div class="form-group">
          <label for="year-or-form">Year of study or form</label>
          <input id="year-or-form" name="year_or_form" value="<?= htmlspecialchars($_POST['year_or_form'] ?? '') ?>" maxlength="60">
        </div>
      </fieldset>
      <?php endif; ?>
      <button type="submit" class="btn btn-primary btn-lg auth-submit">
        <i class="fa fa-user-plus"></i> Create Account
      </button>
    </form>

    <div class="auth-footer">Already have an account? <a href="login.php">Sign in</a></div>
  </div>
</div>
<?php if (EDUCATION_COURSE_TARGETING_ENABLED): ?>
<script>
const roleSelect = document.getElementById('account-role');
const educationFields = document.getElementById('student-education-fields');
const educationInputs = educationFields.querySelectorAll('select, input');
function updateEducationFields() {
  const isStudent = roleSelect.value === 'student';
  educationFields.hidden = !isStudent;
  educationInputs.forEach((input) => { input.required = isStudent; });
}
roleSelect.addEventListener('change', updateEducationFields);
updateEducationFields();
</script>
<?php endif; ?>
</body>
</html>
