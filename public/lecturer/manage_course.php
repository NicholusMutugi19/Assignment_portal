<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/User.php';

Auth::requireRole('lecturer', '/auth/login.php');
if (!EDUCATION_COURSE_TARGETING_ENABLED) {
    http_response_code(503);
    exit('Course audience management is not enabled.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    exit('Invalid request.');
}
$courseId = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT) ?: 0;
$audience = $_POST['audience'] ?? '';
$status = $_POST['status'] ?? '';
$priceRaw = trim($_POST['price'] ?? '');
$price = $priceRaw === '' ? null : filter_var($priceRaw, FILTER_VALIDATE_FLOAT);
if (!$courseId || !in_array($audience, ['campus_only', 'high_school_only', 'both'], true)
    || !in_array($status, ['draft', 'published'], true) || ($priceRaw !== '' && $price === false)) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid course settings.'];
    header('Location: /lecturer/courses.php');
    exit;
}
try {
    $saved = User::updateCourseSettings((int)Auth::user()['id'], $courseId, $audience, $status, $price === false ? null : $price);
    $_SESSION['flash'] = [
        'type' => $saved ? 'success' : 'error',
        'message' => $saved ? 'Course settings saved.' : 'Course not found or you do not own it.',
    ];
} catch (Throwable $e) {
    error_log('Course settings update failed: ' . $e->getMessage());
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Unable to update course settings.'];
}
header('Location: /lecturer/courses.php');
exit;
