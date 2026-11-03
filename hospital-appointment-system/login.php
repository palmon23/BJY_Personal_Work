<?php
/**
 * =====================================================================
 * login.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * Single login form shared by all 4 roles (admin, doctor, receptionist,
 * patient). Role is determined from the matched user row, not chosen
 * by the visitor, so nobody can "select" a privileged role at login.
 * =====================================================================
 */

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

redirect_if_logged_in();

$errors = [];
$oldEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $email    = sanitize($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $oldEmail = $email;

    if ($email === '' || $password === '') {
        $errors[] = 'Please enter both email and password.';
    } elseif (!is_valid_email($email)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($errors)) {
        $stmt = db_query($conn, "SELECT * FROM users WHERE email = ? LIMIT 1", 's', [$email]);
        $result = $stmt ? mysqli_stmt_get_result($stmt) : false;
        $user = $result ? mysqli_fetch_assoc($result) : null;

        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Incorrect email or password.';
            log_activity($conn, null, 'login_failed', 'Failed login attempt for: ' . $email);
        } elseif ($user['status'] !== 'active') {
            $errors[] = 'Your account is currently ' . $user['status'] . '. Please contact the administrator.';
        } else {
            // Resolve the role-specific ID (doctors.id / patients.id) if applicable.
            $extraId = null;
            if ($user['role'] === 'doctor') {
                $r = mysqli_stmt_get_result(db_query($conn, "SELECT id FROM doctors WHERE user_id = ?", 'i', [$user['id']]));
                $extraId = $r && ($row = mysqli_fetch_assoc($r)) ? (int) $row['id'] : null;
            } elseif ($user['role'] === 'patient') {
                $r = mysqli_stmt_get_result(db_query($conn, "SELECT id FROM patients WHERE user_id = ?", 'i', [$user['id']]));
                $extraId = $r && ($row = mysqli_fetch_assoc($r)) ? (int) $row['id'] : null;
            }

            login_user($user, $extraId);

            // Update last_login timestamp
            db_query($conn, "UPDATE users SET last_login = NOW() WHERE id = ?", 'i', [$user['id']]);
            log_activity($conn, (int) $user['id'], 'login_success', 'User logged in.');

            redirect(dashboard_url_for($user['role']));
        }
    }
}

$pageTitle = 'Login';
$hideChrome = true;
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-shell">
    <div class="auth-visual">
        <div class="auth-visual-content">
            <div class="d-flex align-items-center gap-2 mb-4">
                <span style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,var(--hams-primary),var(--hams-secondary));display:flex;align-items:center;justify-content:center;color:#06202E;font-size:1.2rem;">
                    <i class="bi bi-heart-pulse-fill"></i>
                </span>
                <span class="fw-bold fs-4 text-white font-heading"><?php echo e(APP_NAME); ?></span>
            </div>
            <h2 class="fw-bold text-white mb-3">Welcome back.</h2>
            <p style="color:rgba(255,255,255,.8);">Log in to manage appointments, view your schedule, or check in patients — everything in one place.</p>
            <ul class="list-unstyled mt-4" style="color:rgba(255,255,255,.85);">
                <li class="mb-2"><i class="bi bi-check-circle-fill me-2" style="color:var(--hams-secondary);"></i>One account for patients, doctors, staff &amp; admins</li>
                <li class="mb-2"><i class="bi bi-check-circle-fill me-2" style="color:var(--hams-secondary);"></i>Real-time appointment status</li>
                <li class="mb-2"><i class="bi bi-check-circle-fill me-2" style="color:var(--hams-secondary);"></i>Secure, encrypted credentials</li>
            </ul>
        </div>
    </div>

    <div class="auth-form-side">
        <div class="auth-form-card">
            <div class="d-lg-none text-center mb-4">
                <span style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,var(--hams-primary),var(--hams-secondary));display:inline-flex;align-items:center;justify-content:center;color:#06202E;font-size:1.2rem;">
                    <i class="bi bi-heart-pulse-fill"></i>
                </span>
            </div>

            <h3 class="fw-bold mb-1">Sign in to your account</h3>
            <p class="text-muted mb-4">Enter your credentials to continue.</p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger py-2">
                    <?php foreach ($errors as $err): ?>
                        <div><i class="bi bi-exclamation-circle me-1"></i><?php echo e($err); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo BASE_URL; ?>login.php" class="needs-validation" novalidate>
                <?php csrf_field(); ?>

                <div class="mb-3">
                    <label class="form-label" for="email">Email address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" required
                               value="<?php echo e($oldEmail); ?>" placeholder="you@example.com" autofocus>
                    </div>
                    <div class="invalid-feedback">Please enter a valid email address.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required
                               placeholder="Enter your password" minlength="6">
                        <button class="btn btn-outline-secondary" type="button" data-toggle-password="#password" tabindex="-1">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback">Please enter your password.</div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="rememberMe" name="remember_me">
                        <label class="form-check-label small" for="rememberMe">Remember me</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 btn-lg mb-3">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Log In
                </button>

                <p class="text-center text-muted small mb-0">
                    Don't have an account? <a href="<?php echo BASE_URL; ?>register.php" class="fw-semibold">Register as a patient</a>
                </p>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>