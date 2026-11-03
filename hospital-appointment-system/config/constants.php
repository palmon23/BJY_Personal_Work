<?php
/**
 * =====================================================================
 * config/constants.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * Global application constants: base URL, paths, app info, session
 * settings. Include this BEFORE any output and BEFORE session_start()
 * related includes:
 *      require_once __DIR__ . '/../config/constants.php';
 * =====================================================================
 */

// -----------------------------------------------------------------
// App Info
// -----------------------------------------------------------------
define('APP_NAME', 'HAMS');
define('APP_FULL_NAME', 'Hospital Appointment Management System');
define('APP_VERSION', '1.0.0');

// -----------------------------------------------------------------
// Base URL — auto-detected so the project works from any folder name
// e.g. http://localhost/hospital-appointment-system/
// -----------------------------------------------------------------
$protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host       = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir  = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

// Strip trailing role sub-folders (admin/doctor/receptionist/patient) so
// BASE_URL always points to the project ROOT regardless of which page
// includes this file.
$rootDir = preg_replace('#/(admin|doctor|receptionist|patient|config|includes)(/.*)?$#', '', $scriptDir);
$rootDir = ($rootDir === '' || $rootDir === '/') ? '' : $rootDir;

define('BASE_URL', rtrim($protocol . $host . $rootDir, '/') . '/');

// -----------------------------------------------------------------
// Filesystem paths
// -----------------------------------------------------------------
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('UPLOADS_PATH', ROOT_PATH . 'uploads' . DIRECTORY_SEPARATOR);
define('UPLOADS_URL', BASE_URL . 'uploads/');
define('ASSETS_URL', BASE_URL . 'assets/');

// -----------------------------------------------------------------
// Upload rules
// -----------------------------------------------------------------
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// -----------------------------------------------------------------
// Pagination default
// -----------------------------------------------------------------
define('RECORDS_PER_PAGE', 10);

// -----------------------------------------------------------------
// Session security settings — must be set BEFORE session_start()
// -----------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    if ($protocol === 'https://') {
        ini_set('session.cookie_secure', 1);
    }
}