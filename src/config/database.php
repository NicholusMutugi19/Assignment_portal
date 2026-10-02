<?php
/**
 * Database Configuration
 * assignment_portal/src/config/database.php
 */

if (!function_exists('load_env_file')) {
    function load_env_file(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $trimmed, 2), 2, '');
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            $value = preg_replace('/^(?:"|\')(.*)(?:"|\')$/', '$1', $value) ?? $value;
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

load_env_file(dirname(__DIR__, 2) . '/.env');

define('DB_HOST',     getenv('DB_HOST')     ?: '');
define('DB_PORT',     getenv('DB_PORT')     ?: 3306);
define('DB_NAME',     getenv('DB_NAME')     ?: '');
define('DB_USER',     getenv('DB_USER')     ?: '');
define('DB_PASS',     getenv('DB_PASS')     ?: '');
define('DB_CHARSET',  'utf8mb4');

/**
 * PHP Upload Settings (mirror php.ini overrides done in .htaccess)
 */
define('UPLOAD_MAX_SIZE',       10 * 1024 * 1024); // 10 MB in bytes
define('UPLOAD_ALLOWED_TYPES',  ['application/pdf', 'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/zip', 'application/x-zip-compressed', 'application/octet-stream']);
define('UPLOAD_ALLOWED_EXT',    ['pdf', 'doc', 'docx', 'zip']);
define('UPLOAD_DIR_SUBMISSIONS','uploads/submissions/');
define('UPLOAD_DIR_ASSIGNMENTS','uploads/assignments/');

/**
 * Application settings
 */
define('APP_NAME',    'Assignment Portal');
define('APP_URL',     getenv('APP_URL') ?: '');
define('SESSION_NAME','ap_session');
define('TIMEZONE',    'Africa/Nairobi');
// Enable only after the additive education/course-audience migration is verified.
define('EDUCATION_COURSE_TARGETING_ENABLED', filter_var(
    getenv('EDUCATION_COURSE_TARGETING_ENABLED') ?: 'false',
    FILTER_VALIDATE_BOOLEAN
));
define('PORTAL_EXTENSIONS_ENABLED', filter_var(getenv('PORTAL_EXTENSIONS_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN));
define('MPESA_ENABLED', PORTAL_EXTENSIONS_ENABLED && filter_var(getenv('MPESA_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN));
define('MAINTENANCE_MODE', filter_var(getenv('MAINTENANCE_MODE') ?: 'false', FILTER_VALIDATE_BOOLEAN));

date_default_timezone_set(TIMEZONE);
