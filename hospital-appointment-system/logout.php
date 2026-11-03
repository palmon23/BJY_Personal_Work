<?php
/**
 * =====================================================================
 * logout.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * Logs the current user out. Only accepts POST (submitted via the
 * hidden #logoutForm in includes/navbar.php) so logout can never be
 * triggered by a plain link, image tag, or link-prefetcher — all of
 * which could otherwise force an unwanted logout via CSRF/GET.
 * =====================================================================
 */

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('login.php');
}

require_csrf();

if (is_logged_in()) {
    log_activity($conn, current_user_id(), 'logout', 'User logged out.');
}

logout_user();

// Start a fresh session only long enough to carry the flash message
// through the redirect to the login page.
session_start();
set_flash('info', 'You have been logged out successfully.');

redirect('login.php');