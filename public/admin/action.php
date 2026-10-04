<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/Admin.php';
Auth::requireRole('admin', '/auth/login.php');
if (!PORTAL_EXTENSIONS_ENABLED || $_SERVER['REQUEST_METHOD'] !== 'POST' || !Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
    http_response_code(400); exit('Invalid request.');
}
$adminId = (int)Auth::user()['id'];
try {
    switch ($_POST['action'] ?? '') {
        case 'health_snapshot':
            Admin::audit($adminId, 'health.snapshot', 'system', null, null, ['status' => 'viewed']);
            break;
        case 'approve':
        case 'reject':
            Admin::decideLecturer($adminId, (int)($_POST['lecturer_id'] ?? 0), $_POST['action'] === 'approve' ? 'approved' : 'rejected', trim($_POST['note'] ?? ''));
            break;
        case 'update_user':
            Admin::updateUser($adminId, (int)($_POST['user_id'] ?? 0), $_POST['role'] ?? '', $_POST['account_status'] ?? '');
            break;
        case 'suspend_course':
            Admin::courseAudit($adminId, 'course.suspend.request', (int)($_POST['course_id'] ?? 0));
            Admin::suspendCourse($adminId, (int)($_POST['course_id'] ?? 0));
            break;
        case 'restore_course':
            Admin::courseAudit($adminId, 'course.restore.request', (int)($_POST['course_id'] ?? 0));
            Admin::setCourseStatus($adminId, (int)($_POST['course_id'] ?? 0), 'published');
            break;
        case 'delete_course':
            Admin::deleteCourse($adminId, (int)($_POST['course_id'] ?? 0));
            break;
        case 'update_course_fee':
            $rawPrice = trim($_POST['price'] ?? '');
            $price = $rawPrice === '' ? null : filter_var($rawPrice, FILTER_VALIDATE_FLOAT);
            if ($rawPrice !== '' && $price === false) throw new InvalidArgumentException('Invalid fee.');
            Admin::setCoursePrice($adminId, (int)($_POST['course_id'] ?? 0), $price === false ? null : $price);
            break;
        case 'update_setting':
            Admin::updateSetting($adminId, (string)($_POST['setting_key'] ?? ''), (string)($_POST['setting_value'] ?? ''));
            break;
        case 'delete_user':
            Admin::deleteUser($adminId, (int)($_POST['user_id'] ?? 0));
            break;
        default: throw new InvalidArgumentException('Unknown admin action.');
    }
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Administrative action completed and audited.'];
} catch (Throwable $e) {
    error_log('Admin action failed: ' . $e->getMessage());
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Action could not be completed.'];
}
header('Location: /admin/');
exit;
