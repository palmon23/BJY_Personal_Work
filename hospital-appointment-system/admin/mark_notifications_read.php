<?php
/**
 * =====================================================================
 * admin/mark_notifications_read.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * AJAX endpoint called by the "Mark all read" button in
 * includes/navbar.php (see script.js: initNotifications()).
 * Marks every unread notification belonging to the logged-in admin
 * as read and returns a small JSON result.
 *
 * NOTE: This same file pattern is duplicated per-role
 * (doctor/, receptionist/, patient/) because navbar.php builds the
 * URL as "{role}/mark_notifications_read.php" and each role's folder
 * is guarded by its own require_role() call.
 * =====================================================================
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// This is an AJAX-only, same-origin action with no state-changing side
// effects beyond the user's own notifications, but we still verify a
// CSRF token when one is supplied for defense in depth.
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