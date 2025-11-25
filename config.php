<?php
// config.php - MySQL Configuration (Clean Version)

// Database Configuration
define('DB_HOST', '');    // เปลี่ยนเป็นของคุณ
define('DB_NAME', '');     // เปลี่ยนเป็นของคุณ
define('DB_USER', '');               // เปลี่ยนเป็นของคุณ
define('DB_PASS', '');         // เปลี่ยนเป็นของคุณ

// Application Settings
define('APP_NAME', 'นัดเพื่อน');
define('APP_URL', 'fujifrodo.com');

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // Set to 1 if using HTTPS

// Error Reporting (ปิดในเว็บจริง)
error_reporting(E_ALL);
ini_set('display_errors', 0); // เปลี่ยนเป็น 0 เมื่อใช้งานจริง
ini_set('log_errors', 1);

// Timezone
date_default_timezone_set('Asia/Bangkok');

// Legacy compatibility (not used in MySQL version)
$dataFile = __DIR__ . '/data.json';
?>