<?php
// ============================================================
// ShadowWatch — Configuration
// ============================================================

define('DB_HOST',    'localhost');
define('DB_PORT',    3306);
define('DB_NAME',    'shadowwatch');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME',    'ShadowWatch');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'http://localhost/shadowwatch');
define('APP_ROOT',    dirname(__DIR__));

define('SESSION_NAME',     'shadowwatch_session');
define('SESSION_LIFETIME', 28800); // 8 hours
define('SESSION_SECURE',   false); // true if HTTPS

define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION',   900); // 15 min in seconds

define('UPLOAD_DIR', APP_ROOT . '/uploads/');
define('LOG_DIR',    APP_ROOT . '/logs/');
define('HASH_COST',  12);
define('ENV',        'development'); // 'development' or 'production'

if (ENV === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

date_default_timezone_set('UTC');