<?php
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/config/Database.php';
require_once __DIR__ . '/../src/models/Admin.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (!PORTAL_EXTENSIONS_ENABLED) {
    fwrite(STDERR, "Enable the role-extension migration and PORTAL_EXTENSIONS_ENABLED before first-admin provisioning.\n");
    exit(1);
}
try {
    $id = Admin::provisionFirstAdminFromEnvironment();
    fwrite(STDOUT, "First administrator provisioned. User ID: {$id}\n");
    fwrite(STDOUT, "Unset BOOTSTRAP_ADMIN_PASSWORD from the deployment environment after this one-time operation.\n");
} catch (Throwable $e) {
    error_log('Administrator bootstrap failed: ' . $e->getMessage());
    fwrite(STDERR, "Administrator bootstrap failed: {$e->getMessage()}\n");
    exit(1);
}
