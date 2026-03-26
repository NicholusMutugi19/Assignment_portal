<?php
/**
 * Student Assignment File Download
 * Secure file serving for assignment attachments
 */

require_once __DIR__ . '/../../src/config/database.php';
require_once __DIR__ . '/../../src/config/Database.php';
require_once __DIR__ . '/../../src/middleware/Auth.php';
require_once __DIR__ . '/../../src/models/Assignment.php';

Auth::requireRole('student', APP_URL . '/auth/login.php');
$user = Auth::user();

$assignmentId = (int)($_GET['assignment_id'] ?? 0);
if (!$assignmentId) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid assignment ID');
}

// Get assignment details
$assignment = Assignment::findById($assignmentId);
if (!$assignment) {
    header('HTTP/1.1 404 Not Found');
    exit('Assignment not found');
}

// Check if student is enrolled in the course
$enrolled = Database::query(
    'SELECT 1 FROM enrollments WHERE student_id = :sid AND course_id = :cid',
    [':sid' => $user['id'], ':cid' => $assignment['course_id']]
)->fetch();

if (!$enrolled) {
    header('HTTP/1.1 403 Forbidden');
    exit('You are not enrolled in this course');
}

// Check if assignment has an attachment
if (empty($assignment['attachment_path']) || empty($assignment['attachment_name'])) {
    header('HTTP/1.1 404 Not Found');
    exit('No attachment available for this assignment');
}

// Build full file path
$filePath = __DIR__ . '/../' . $assignment['attachment_path'];

// Check if file exists
if (!file_exists($filePath)) {
    header('HTTP/1.1 404 Not Found');
    exit('File not found on server');
}

// Get file info
$fileSize = filesize($filePath);
$mimeType = mime_content_type($filePath) ?: 'application/octet-stream';

// Set headers for download
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);
header('Content-Disposition: attachment; filename="' . $assignment['attachment_name'] . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Clear output buffer
if (ob_get_level()) {
    ob_clean();
}

// Read and output file
readfile($filePath);
exit;
?>