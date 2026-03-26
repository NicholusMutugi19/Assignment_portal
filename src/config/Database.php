<?php
/**
 * Database Connection (Singleton PDO wrapper)
 * assignment_portal/src/config/Database.php
 */

require_once __DIR__ . '/database.php';

class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            // Add SSL configuration for Aiven MySQL
            if (getenv('DB_SSL_MODE') === 'REQUIRED') {
                $options[PDO::MYSQL_ATTR_SSL_CA] = '/usr/local/share/ca-certificates/aiven-ca.pem';
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
            }

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log('DB Connection failed: ' . $e->getMessage());
                die(json_encode(['error' => 'Database connection failed.']));
            }
        }
        return self::$instance;
    }

    /**
     * Quick helper: run a prepared statement, return PDOStatement
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
