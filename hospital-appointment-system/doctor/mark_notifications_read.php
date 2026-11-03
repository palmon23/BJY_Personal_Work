<?php
/**
 * =====================================================================
 * doctor/mark_notifications_read.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * AJAX endpoint called by the "Mark all read" button in
 * includes/navbar.php (see script.js: initNotifications()) when the
 * logged-in user is a doctor. Identical logic to the admin version,
 * duplicated per-role because navbar.php builds the URL as
 * "{role}/mark_notifications_read.php" and each role folder is guarded
 * by its own require_role() call.
 * =====================================================================
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('doctor');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

if (isset($_POST['csrf_token']) && !verify_csrf($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit;
}

$userId = current_user_id();
$stmt = db_query($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0", 'i', [$userId]);

echo json_encode([
    'success' => $stmt !== false,
    'updated' => $stmt ? mysqli_stmt_affected_rows($stmt) : 0,
]);