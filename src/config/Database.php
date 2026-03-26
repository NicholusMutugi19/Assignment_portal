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
            // Validate critical environment variables
            if (empty(DB_HOST) || empty(DB_NAME) || empty(DB_USER)) {
                $missing = [];
                if (empty(DB_HOST)) { $missing[] = 'DB_HOST'; }
                if (empty(DB_NAME)) { $missing[] = 'DB_NAME'; }
                if (empty(DB_USER)) { $missing[] = 'DB_USER'; }
                $message = 'DB connection config invalid, missing: ' . implode(', ', $missing);
                error_log($message);
                throw new PDOException($message);
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
                $caPath = getenv('DB_SSL_CA') ?: '/usr/local/share/ca-certificates/aiven-ca.pem';
                if (!file_exists($caPath)) {
                    // Fallback to system CA certificates if specific CA file not found
                    $options[PDO::MYSQL_ATTR_SSL_CA] = null;
                    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                } else {
                    $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
                    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                }
                $options[PDO::MYSQL_ATTR_SSL_KEY] = null;
                $options[PDO::MYSQL_ATTR_SSL_CERT] = null;
            }

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                $msg = 'DB Connection failed: ' . $e->getMessage();
                error_log($msg);

                // Detailed message during debug mode (set APP_ENV=development in Render for diagnostics)
                if (getenv('APP_ENV') === 'development') {
                    die(json_encode(['error' => $msg]));
                }

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
