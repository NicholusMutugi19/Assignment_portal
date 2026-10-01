<?php
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/config/Database.php';
require_once __DIR__ . '/../src/models/Payment.php';
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (!MPESA_ENABLED) {
    fwrite(STDERR, "M-Pesa processing is disabled.\n");
    exit(1);
}
try {
    $count = Payment::processDueCallbacks(100);
    $stale = Payment::failStalePending(30);
    fwrite(STDOUT, "Processed {$count} callback inbox record(s); closed {$stale} stale payment(s).\n");
} catch (Throwable $e) {
    error_log('Payment callback retry job failed: ' . $e->getMessage());
    fwrite(STDERR, "Callback retry job failed. Check server logs.\n");
    exit(1);
}
