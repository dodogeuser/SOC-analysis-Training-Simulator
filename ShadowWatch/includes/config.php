<?php
// ShadowWatch Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'shadowwatch');

define('APP_NAME', 'ShadowWatch');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/shadowwatch');

define('SESSION_LIFETIME', 3600 * 8); // 8 hours
define('SESSION_NAME', 'sw_session');
define('CSRF_TOKEN_NAME', 'sw_csrf_token');

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB

define('ALERT_AUTO_GENERATE', true);
define('ALERT_GENERATE_INTERVAL', 30); // seconds

define('TIMEZONE', 'UTC');
date_default_timezone_set(TIMEZONE);

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
