<?php
/**
 * One-time CLI bootstrap for the first administrator.
 * Required env vars: BOOTSTRAP_ADMIN_NAME, BOOTSTRAP_ADMIN_EMAIL,
 * BOOTSTRAP_ADMIN_PASSWORD. Never place the password in this file or commit it.
 */
require_once __DIR__ . '/../src/config/database.php';
require_once __DIR__ . '/../src/config/Database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (!PORTAL_EXTENSIONS_ENABLED) {
    fwrite(STDERR, "Set PORTAL_EXTENSIONS_ENABLED=true after applying the role migration.\n");
    exit(1);
}
$name = trim((string)getenv('BOOTSTRAP_ADMIN_NAME'));
$email = strtolower(trim((string)getenv('BOOTSTRAP_ADMIN_EMAIL')));
$password = (string)getenv('BOOTSTRAP_ADMIN_PASSWORD');
if ($name === '' || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 16) {
    fwrite(STDERR, "Provide a name, valid email, and a unique password of at least 16 characters via environment variables.\n");
    exit(1);
}
$pdo = Database::getInstance();
$pdo->beginTransaction();
try {
    $adminCount = (int)Database::query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($adminCount !== 0) throw new RuntimeException('An administrator already exists; bootstrap is one-time only.');
    $existing = Database::query('SELECT id FROM users WHERE email = :email', [':email' => $email])->fetch();
    if ($existing) throw new RuntimeException('That email already belongs to an account; no existing account was changed.');
    Database::query(
        "INSERT INTO users (name, email, password, role, account_status, lecturer_approval_status)
         VALUES (:name, :email, :password, 'admin', 'active', NULL)",
        [':name' => $name, ':email' => $email, ':password' => password_hash($password, PASSWORD_DEFAULT)]
    );
    $adminId = (int)$pdo->lastInsertId();
    Database::query(
        "INSERT INTO audit_logs (admin_id, action, target_type, target_id, after_data)
         VALUES (:admin_id, 'admin.bootstrap', 'user', :target_id, :data)",
        [':admin_id' => $adminId, ':target_id' => (string)$adminId, ':data' => json_encode(['email' => $email], JSON_THROW_ON_ERROR)]
    );
    $pdo->commit();
    fwrite(STDOUT, "Created first administrator (id {$adminId}). Remove BOOTSTRAP_ADMIN_PASSWORD from the process environment now.\n");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('First-admin bootstrap failed: ' . $e->getMessage());
    fwrite(STDERR, "Bootstrap failed: {$e->getMessage()}\n");
    exit(1);
}
