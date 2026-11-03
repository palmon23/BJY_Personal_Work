<?php
/**
 * =====================================================================
 * includes/functions.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * Core reusable helper functions: sanitization, CSRF protection,
 * redirects, flash messages, formatting, notifications, pagination.
 *
 * Requires: config/constants.php, config/db.php (for notification /
 * activity-log helpers that touch the database)
 * =====================================================================
 */

// -----------------------------------------------------------------
// Start session (safe to call multiple times)
// -----------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =====================================================================
   INPUT SANITIZATION & VALIDATION
   ===================================================================== */

/**
 * Trim, strip tags and HTML-escape a raw input value.
 */
function sanitize(string $value): string
{
    $value = trim($value);
    $value = strip_tags($value);
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize an entire array (e.g. $_POST) in one call.
 */
function sanitize_array(array $data): array
{
    $clean = [];
    foreach ($data as $key => $value) {
        $clean[$key] = is_array($value) ? sanitize_array($value) : sanitize((string) $value);
    }
    return $clean;
}

function is_valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Basic international-friendly phone validation (digits, spaces, +, -, parentheses).
 */
function is_valid_phone(string $phone): bool
{
    return (bool) preg_match('/^[0-9+\-\s()]{7,20}$/', $phone);
}

/**
 * Enforce a reasonably strong password: min 8 chars, at least one letter and one number.
 */
function is_strong_password(string $password): bool
{
    return (bool) preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/', $password);
}

/* =====================================================================
   CSRF PROTECTION
   ===================================================================== */

/**
 * Generate (or reuse) a CSRF token for the current session and return it.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output a hidden <input> field carrying the CSRF token. Echo this
 * directly inside every <form> that submits via POST:
 *      <?php csrf_field(); ?>
 */
function csrf_field(): void
{
    echo '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * Verify a submitted CSRF token against the session token.
 * Call this at the top of every POST handler.
 */
function verify_csrf(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Verify CSRF and die with a 403 if it fails. Convenience wrapper for
 * POST handlers that want a one-line guard.
 */
function require_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        http_response_code(403);
        die('Invalid or expired security token. Please refresh the page and try again.');
    }
}

/* =====================================================================
   REDIRECTS & FLASH MESSAGES
   ===================================================================== */

/**
 * Redirect to a URL (relative to BASE_URL or absolute) and stop execution.
 */
function redirect(string $url): void
{
    if (!preg_match('#^https?://#i', $url)) {
        $url = rtrim(BASE_URL, '/') . '/' . ltrim($url, '/');
    }
    header('Location: ' . $url);
    exit;
}

/**
 * Store a one-time flash message in the session.
 * $type: 'success' | 'error' | 'warning' | 'info'
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieve and clear the flash message, if any. Returns null if none set.
 */
function get_flash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/* =====================================================================
   FORMATTING HELPERS
   ===================================================================== */

function format_date(?string $date, string $format = 'M d, Y'): string
{
    if (empty($date) || $date === '0000-00-00') {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '—';
}

function format_time(?string $time, string $format = 'h:i A'): string
{
    if (empty($time)) {
        return '—';
    }
    $ts = strtotime($time);
    return $ts ? date($format, $ts) : '—';
}

function format_datetime(?string $datetime, string $format = 'M d, Y h:i A'): string
{
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return '—';
    }
    $ts = strtotime($datetime);
    return $ts ? date($format, $ts) : '—';
}

/**
 * Return a Bootstrap badge CSS class for a given appointment/user status.
 */
function status_badge_class(string $status): string
{
    return match (strtolower($status)) {
        'confirmed', 'active', 'completed' => 'bg-success',
        'pending' => 'bg-warning text-dark',
        'cancelled', 'rejected', 'inactive', 'suspended' => 'bg-danger',
        default => 'bg-secondary',
    };
}

/**
 * Human-friendly "time ago" string for notifications.
 */
function time_ago(string $datetime): string
{
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
    return format_date($datetime);
}

/* =====================================================================
   FILE UPLOAD HELPER
   ===================================================================== */

/**
 * Validate and move an uploaded image into UPLOADS_PATH.
 * Returns the relative filename on success, or false on failure
 * (with an error message written into $error by reference).
 */
function upload_image(array $file, string $subfolder, ?string &$error = null)
{
    if (empty($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // No file selected — not necessarily an error
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload failed. Please try again.';
        return false;
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        $error = 'File is too large. Maximum size is 2MB.';
        return false;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) {
        $error = 'Only JPG, PNG or WEBP images are allowed.';
        return false;
    }

    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => 'jpg',
    };

    $filename = uniqid('img_', true) . '.' . $ext;
    $targetDir = UPLOADS_PATH . trim($subfolder, '/\\') . DIRECTORY_SEPARATOR;

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $targetDir . $filename)) {
        $error = 'Could not save the uploaded file.';
        return false;
    }

    return trim($subfolder, '/\\') . '/' . $filename;
}

/* =====================================================================
   NOTIFICATIONS (requires $conn from config/db.php)
   ===================================================================== */

/**
 * Insert an in-app notification for a user.
 */
function add_notification(mysqli $conn, int $userId, string $title, string $message, ?string $link = null): void
{
    $sql = "INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)";
    db_query($conn, $sql, 'isss', [$userId, $title, $message, $link]);
}

/**
 * Count unread notifications for a user (used in navbar bell icon).
 */
function unread_notification_count(mysqli $conn, int $userId): int
{
    $stmt = db_query($conn, "SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0", 'i', [$userId]);
    if (!$stmt) {
        return 0;
    }
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    return (int) ($row['cnt'] ?? 0);
}

/* =====================================================================
   ACTIVITY LOG (requires $conn from config/db.php)
   ===================================================================== */

function log_activity(mysqli $conn, ?int $userId, string $action, string $description = ''): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $sql = "INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)";
    db_query($conn, $sql, 'isss', [$userId, $action, $description, $ip]);
}

/* =====================================================================
   PAGINATION HELPER
   ===================================================================== */

/**
 * Build pagination metadata from the current page and total record count.
 * Returns an array: [currentPage, totalPages, offset, limit]
 */
function paginate(int $totalRecords, int $perPage = RECORDS_PER_PAGE): array
{
    $currentPage = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $totalPages = max(1, (int) ceil($totalRecords / $perPage));
    $currentPage = min($currentPage, $totalPages);
    $offset = ($currentPage - 1) * $perPage;

    return [
        'currentPage' => $currentPage,
        'totalPages'  => $totalPages,
        'offset'      => $offset,
        'limit'       => $perPage,
    ];
}

/**
 * Render Bootstrap 5 pagination links. Preserves existing query string
 * (e.g. search/filter params) while changing only the "page" param.
 */
function render_pagination(int $currentPage, int $totalPages): void
{
    if ($totalPages <= 1) {
        return;
    }

    $params = $_GET;
    echo '<nav aria-label="Page navigation"><ul class="pagination justify-content-center flex-wrap">';

    $params['page'] = max(1, $currentPage - 1);
    $prevDisabled = $currentPage <= 1 ? ' disabled' : '';
    echo '<li class="page-item' . $prevDisabled . '"><a class="page-link" href="?' . http_build_query($params) . '">Previous</a></li>';

    for ($i = 1; $i <= $totalPages; $i++) {
        $params['page'] = $i;
        $active = $i === $currentPage ? ' active' : '';
        echo '<li class="page-item' . $active . '"><a class="page-link" href="?' . http_build_query($params) . '">' . $i . '</a></li>';
    }

    $params['page'] = min($totalPages, $currentPage + 1);
    $nextDisabled = $currentPage >= $totalPages ? ' disabled' : '';
    echo '<li class="page-item' . $nextDisabled . '"><a class="page-link" href="?' . http_build_query($params) . '">Next</a></li>';

    echo '</ul></nav>';
}

/* =====================================================================
   MISC HELPERS
   ===================================================================== */

/**
 * Generate a simple unique reference code, e.g. for appointment receipts.
 */
function generate_reference(string $prefix = 'HAMS'): string
{
    return strtoupper($prefix) . '-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

/**
 * Escape output for safe HTML display (alias of htmlspecialchars for readability in views).
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}