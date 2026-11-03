<?php
/**
 * =====================================================================
 * includes/auth.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * Session-based authentication helpers and role guards.
 * Requires: config/constants.php, config/db.php, includes/functions.php
 * to already be loaded (functions.php starts the session).
 *
 * Typical usage at the top of a protected page, e.g. admin/dashboard.php:
 *
 *      require_once __DIR__ . '/../config/constants.php';
 *      require_once __DIR__ . '/../config/db.php';
 *      require_once __DIR__ . '/../includes/functions.php';
 *      require_once __DIR__ . '/../includes/auth.php';
 *      require_role('admin');
 * =====================================================================
 */

/**
 * Whether a user is currently logged in.
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']) && !empty($_SESSION['role']);
}

/**
 * Return the logged-in user's role, or null if not logged in.
 */
function current_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

/**
 * Return the logged-in user's ID, or null if not logged in.
 */
function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

/**
 * Return a small array of commonly-needed session user fields.
 */
function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }

    return [
        'id'            => (int) $_SESSION['user_id'],
        'role'          => $_SESSION['role'],
        'full_name'     => $_SESSION['full_name'] ?? '',
        'email'         => $_SESSION['email'] ?? '',
        'profile_photo' => $_SESSION['profile_photo'] ?? 'assets/images/default-avatar.png',
        // Set only for doctors — the row ID in the `doctors` table (not users.id)
        'doctor_id'     => $_SESSION['doctor_id'] ?? null,
        // Set only for patients — the row ID in the `patients` table (not users.id)
        'patient_id'    => $_SESSION['patient_id'] ?? null,
    ];
}

/**
 * Populate the session after a successful login.
 * $userRow is expected to be an associative array from the `users` table.
 * $extraId is the doctors.id or patients.id, when applicable.
 */
function login_user(array $userRow, ?int $extraId = null): void
{
    // Regenerate session ID on privilege change to prevent session fixation.
    session_regenerate_id(true);

    $_SESSION['user_id']       = (int) $userRow['id'];
    $_SESSION['role']          = $userRow['role'];
    $_SESSION['full_name']     = $userRow['full_name'];
    $_SESSION['email']         = $userRow['email'];
    $_SESSION['profile_photo'] = $userRow['profile_photo'] ?? 'assets/images/default-avatar.png';

    if ($userRow['role'] === 'doctor') {
        $_SESSION['doctor_id'] = $extraId;
    } elseif ($userRow['role'] === 'patient') {
        $_SESSION['patient_id'] = $extraId;
    }
}

/**
 * Destroy the session completely (used by logout.php).
 */
function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Return the correct dashboard URL for a given role.
 */
function dashboard_url_for(string $role): string
{
    return match ($role) {
        'admin'        => BASE_URL . 'admin/dashboard.php',
        'doctor'       => BASE_URL . 'doctor/dashboard.php',
        'receptionist' => BASE_URL . 'receptionist/dashboard.php',
        'patient'      => BASE_URL . 'patient/dashboard.php',
        default        => BASE_URL . 'login.php',
    };
}

/**
 * Guard: require the user to be logged in at all (any role).
 * Redirects to login.php with a flash message if not.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('warning', 'Please log in to continue.');
        redirect('login.php');
    }
}

/**
 * Guard: require the user to be logged in AND hold one specific role
 * (or one of several roles). Redirects to their own dashboard if their
 * role doesn't match, or to login.php if not logged in at all.
 *
 * Usage:
 *      require_role('admin');
 *      require_role(['admin', 'receptionist']);
 */
function require_role($roles): void
{
    require_login();

    $roles = is_array($roles) ? $roles : [$roles];

    if (!in_array(current_role(), $roles, true)) {
        set_flash('error', 'You do not have permission to access that page.');
        redirect(dashboard_url_for((string) current_role()));
    }
}

/**
 * Guard: if the user is ALREADY logged in, bounce them away from
 * guest-only pages (login.php, register.php) straight to their dashboard.
 */
function redirect_if_logged_in(): void
{
    if (is_logged_in()) {
        redirect(dashboard_url_for((string) current_role()));
    }
}