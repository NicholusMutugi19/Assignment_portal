<?php
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/config/Database.php';
require_once __DIR__ . '/../src/models/Payment.php';
require_once __DIR__ . '/../src/models/Admin.php';

header('Content-Type: application/json');
if (!MPESA_ENABLED || !PORTAL_EXTENSIONS_ENABLED || Admin::setting('payments_enabled', '0') !== '1') {
    http_response_code(503);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Payments disabled']);
    exit;
}
$expectedSecret = (string)getenv('MPESA_CALLBACK_SECRET');
$providedSecret = (string)($_GET['token'] ?? '');
if ($expectedSecret === '' || $providedSecret === '' || !hash_equals($expectedSecret, $providedSecret)) {
    http_response_code(403);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Forbidden']);
    exit;
}
$raw = file_get_contents('php://input');
if (!is_string($raw) || $raw === '' || strlen($raw) > 65535) {
    http_response_code(413);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid callback payload']);
    exit;
}
try {
    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    $callback = $decoded['Body']['stkCallback'] ?? [];
    $checkoutId = isset($callback['CheckoutRequestID']) ? (string)$callback['CheckoutRequestID'] : null;
    $inboxId = Payment::logCallback($raw, $checkoutId);
    // Acknowledge Safaricom once durably stored. A scheduled worker retries
    // processing failures; returning 500 could cause unbounded provider retries.
    try {
        Payment::processCallback($inboxId);
    } catch (Throwable $processingError) {
        error_log('Daraja callback queued for retry: inbox_id=' . $inboxId);
    }
    http_response_code(200);
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
} catch (Throwable $e) {
    error_log('Daraja callback processing failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Callback processing failed']);
}
