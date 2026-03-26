<?php
/**
 * Database Configuration
 * assignment_portal/src/config/database.php
 */

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

date_default_timezone_set(TIMEZONE);
