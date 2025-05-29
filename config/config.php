<?php
// Application configuration
define('APP_NAME', 'MBIMS - Multi-Business Income Management System');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost:3000/utb/mbims');

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 6);

// File upload settings
define('MAX_FILE_SIZE', 5242880); // 5MB
define('UPLOAD_PATH', 'uploads/');

// Pagination settings
define('RECORDS_PER_PAGE', 10);

// Date format
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');

// Error reporting (for development)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>