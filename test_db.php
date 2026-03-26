<?php
/**
 * Database Connection Test
 * Test SSL connection to Aiven MySQL
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/Database.php';

try {
    $pdo = Database::getInstance();
    echo "Database connection successful!\n";

    // Test a simple query
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    echo "Query test successful: " . $result['test'] . "\n";

} catch (Exception $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
}
?>