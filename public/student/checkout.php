<?php
require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/User.php';
require_once __DIR__ . '/../../src/models/Payment.php';
require_once __DIR__ . '/../../src/models/Admin.php';
require_once __DIR__ . '/../../src/services/DarajaClient.php';

Auth::requireRole('student', '/auth/login.php');
if (!PORTAL_EXTENSIONS_ENABLED || !MPESA_ENABLED || Admin::setting('payments_enabled', '0') !== '1') {
    http_response_code(503);
    exit('Course payments are not enabled.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Auth::verifyCsrf($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    exit('Invalid request.');
}
$courseId = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT) ?: 0;
$phoneInput = preg_replace('/\D+/', '', $_POST['phone_number'] ?? '');
if (preg_match('/^0[17]\d{8}$/', $phoneInput)) $phoneInput = '254' . substr($phoneInput, 1);
if (!preg_match('/^254[17]\d{8}$/', $phoneInput)) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Enter a valid Kenyan Safaricom number, e.g. 0712345678.'];
    header('Location: /student/courses.php');
    exit;
}
$paymentId = 0;
try {
    $existingEnrollment = Database::query('SELECT course_id FROM enrollments WHERE student_id = :sid AND course_id = :cid', [':sid' => (int)Auth::user()['id'], ':cid' => $courseId])->fetch();
    if (!$existingEnrollment) User::saveStudentCourseSelections((int)Auth::user()['id'], [$courseId]);
    // The fee is read from the course row in Payment::start. No client amount accepted.
    $paymentId = Payment::start((int)Auth::user()['id'], $courseId, $phoneInput);
    $response = DarajaClient::initiateStkPush($phoneInput, (int)round((float)Database::query(
        'SELECT amount_paid FROM payments WHERE id = :id', [':id' => $paymentId]
    )->fetchColumn()), $paymentId);
    if (empty($response['CheckoutRequestID']) || empty($response['MerchantRequestID'])) {
        throw new RuntimeException('M-Pesa did not return checkout identifiers.');
    }
    Payment::attachCheckout($paymentId, (string)$response['MerchantRequestID'], (string)$response['CheckoutRequestID']);
    error_log('STK push initiated: payment_id=' . $paymentId . ' student_id=' . (int)Auth::user()['id']);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Payment prompt sent. Approve it on your phone; course access opens after payment confirmation.'];
} catch (Throwable $e) {
    if ($paymentId) Payment::failInitiation($paymentId);
    error_log('STK push initiation failed: ' . $e->getMessage());
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Could not start the M-Pesa payment. Please check the number and try again.'];
}
header('Location: /student/payments.php');
exit;
