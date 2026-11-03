<?php
/**
 * =====================================================================
 * config/db.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * MySQLi database connection (procedural style).
 * Include this file at the top of any page that needs DB access:
 *      require_once __DIR__ . '/../config/db.php';
 * =====================================================================
 */

// Prevent direct script access errors from being displayed to end users
// while still surfacing them in the server log during development.
error_reporting(E_ALL);
ini_set('display_errors', 1); // Set to 0 in production

// -----------------------------------------------------------------
// Database credentials (default XAMPP settings — adjust as needed)
// -----------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hams_db');
define('DB_PORT', 3306);

// -----------------------------------------------------------------
// Create MySQLi connection
// -----------------------------------------------------------------
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if (!$conn) {
    // Never leak raw DB credentials/errors to the browser in production.
    error_log('HAMS DB Connection Error: ' . mysqli_connect_error());
    die('Database connection failed. Please try again later or contact the system administrator.');
}

// Force UTF-8 for full emoji / special character support
mysqli_set_charset($conn, 'utf8mb4');

/**
 * Helper: run a prepared statement safely and return the mysqli_stmt.
 * Usage:
 *   $stmt = db_query($conn, "SELECT * FROM users WHERE email = ?", "s", [$email]);
 *   $result = mysqli_stmt_get_result($stmt);
 *
 * @param mysqli $conn
 * @param string $sql
 * @param string $types   e.g. "ssi" for two strings and one integer
 * @param array  $params  values to bind, in the same order as $types
 * @return mysqli_stmt|false
 */
function db_query(mysqli $conn, string $sql, string $types = '', array $params = [])
{
    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt === false) {
        error_log('HAMS Prepare Error: ' . mysqli_error($conn) . ' | SQL: ' . $sql);
        return false;
    }

    if ($types !== '' && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    mysqli_stmt_execute($stmt);

    return $stmt;
}