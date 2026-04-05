<?php
/**
 * Database Connection (Singleton PDO wrapper)
 * assignment_portal/src/config/Database.php
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../middleware/ErrorHandler.php';

class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            // Validate critical environment variables - log details, show generic message
            if (empty(DB_HOST) || empty(DB_NAME) || empty(DB_USER)) {
                $missing = [];
                if (empty(DB_HOST)) { $missing[] = 'DB_HOST'; }
                if (empty(DB_NAME)) { $missing[] = 'DB_NAME'; }
                if (empty(DB_USER)) { $missing[] = 'DB_USER'; }
                $message = 'DB connection config invalid, missing: ' . implode(', ', $missing);
                error_log($message);
                throw new PDOException('Database configuration error');
            }

            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            // Add SSL configuration for Aiven MySQL
            $sslMode = strtoupper(getenv('DB_SSL_MODE') ?: '');
            if ($sslMode === 'REQUIRED' || $sslMode === 'PREFERRED') {
                // Enable SSL with minimal verification (matches working MySQL CLI config)
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Log full error details server-side
                error_log('DB Connection failed: ' . $e->getMessage());

                // Return user-friendly error via ErrorHandler
                ErrorHandler::renderErrorPage(
                    $e,
                    'Database Connection Error',
                    'Unable to connect to the database. Please try again later or contact support if the problem persists.'
                );
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
