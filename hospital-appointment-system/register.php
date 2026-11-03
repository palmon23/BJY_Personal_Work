<?php
/**
 * =====================================================================
 * register.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * Public self-registration — PATIENTS ONLY. Admin, doctor and
 * receptionist accounts are created by an administrator (see
 * admin/doctors.php, admin/receptionists.php) — never through a public
 * form, to prevent privilege self-assignment.
 * =====================================================================
 */

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

redirect_if_logged_in();

$errors = [];
$old = ['full_name' => '', 'email' => '', 'phone' => '', 'gender' => '', 'date_of_birth' => '', 'address' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $fullName  = sanitize($_POST['full_name'] ?? '');
    $email     = sanitize($_POST['email'] ?? '');
    $phone     = sanitize($_POST['phone'] ?? '');
    $gender    = sanitize($_POST['gender'] ?? '');
    $dob       = sanitize($_POST['date_of_birth'] ?? '');
    $address   = sanitize($_POST['address'] ?? '');
    $password  = (string) ($_POST['password'] ?? '');
    $confirm   = (string) ($_POST['confirm_password'] ?? '');

    $old = compact('fullName', 'email', 'phone', 'gender', 'dob', 'address');
    $old = ['full_name' => $fullName, 'email' => $email, 'phone' => $phone, 'gender' => $gender, 'date_of_birth' => $dob, 'address' => $address];

    // ---- Validation ----
    if ($fullName === '' || mb_strlen($fullName) < 2) {
        $errors[] = 'Please enter your full name.';
    }
    if (!is_valid_email($email)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($phone !== '' && !is_valid_phone($phone)) {
        $errors[] = 'Please enter a valid phone number.';
    }
    if (!in_array($gender, ['male', 'female', 'other'], true)) {
        $errors[] = 'Please select a gender.';
    }
    if ($dob !== '' && strtotime($dob) > time()) {
        $errors[] = 'Date of birth cannot be in the future.';
    }
    if (!is_strong_password($password)) {
        $errors[] = 'Password must be at least 8 characters and include letters and numbers.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    // ---- Email uniqueness ----
    if (empty($errors)) {
        $stmt = db_query($conn, "SELECT id FROM users WHERE email = ?", 's', [$email]);
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) > 0) {
            $errors[] = 'An account with that email already exists. Try logging in instead.';
        }
    }

    // ---- Create account ----
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $dobValue = $dob !== '' ? $dob : null;

        $stmt = db_query(
            $conn,
            "INSERT INTO users (role, full_name, email, password, phone, gender, date_of_birth, address, status) VALUES ('patient', ?, ?, ?, ?, ?, ?, ?, 'active')",
            'sssssss',
            [$fullName, $email, $hash, $phone, $gender, $dobValue, $address]
        );

        if ($stmt === false) {
            $errors[] = 'Something went wrong while creating your account. Please try again.';
        } else {
            $userId = (int) mysqli_insert_id($conn);

            db_query($conn, "INSERT INTO patients (user_id) VALUES (?)", 'i', [$userId]);

            $stmt = db_query($conn, "SELECT * FROM users WHERE id = ?", 'i', [$userId]);
            $newUser = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            $stmt = db_query($conn, "SELECT id FROM patients WHERE user_id = ?", 'i', [$userId]);
            $patientRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            login_user($newUser, (int) $patientRow['id']);
            add_notification($conn, $userId, 'Welcome to HAMS!', 'Your patient account was created successfully. You can now book your first appointment.');
            log_activity($conn, $userId, 'register', 'New patient self-registered.');

            set_flash('success', 'Account created! Welcome to ' . APP_NAME . '.');
            redirect('patient/dashboard.php');
        }
    }
}

$pageTitle = 'Register';
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
            <h2 class="fw-bold text-white mb-3">Your care, on your schedule.</h2>
            <p style="color:rgba(255,255,255,.8);">Create a free patient account to book appointments, track your visit history, and get notified the moment a doctor confirms your slot.</p>
            <ul class="list-unstyled mt-4" style="color:rgba(255,255,255,.85);">
                <li class="mb-2"><i class="bi bi-check-circle-fill me-2" style="color:var(--hams-secondary);"></i>Takes less than a minute</li>
                <li class="mb-2"><i class="bi bi-check-circle-fill me-2" style="color:var(--hams-secondary);"></i>Reschedule or cancel anytime</li>
                <li class="mb-2"><i class="bi bi-check-circle-fill me-2" style="color:var(--hams-secondary);"></i>Your data is encrypted and private</li>
            </ul>
        </div>
    </div>

    <div class="auth-form-side">
        <div class="auth-form-card" style="max-width:480px;">
            <div class="d-lg-none text-center mb-4">
                <span style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,var(--hams-primary),var(--hams-secondary));display:inline-flex;align-items:center;justify-content:center;color:#06202E;font-size:1.2rem;">
                    <i class="bi bi-heart-pulse-fill"></i>
                </span>
            </div>

            <h3 class="fw-bold mb-1">Create your patient account</h3>
            <p class="text-muted mb-4">It only takes a minute.</p>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger py-2">
                    <?php foreach ($errors as $err): ?>
                        <div><i class="bi bi-exclamation-circle me-1"></i><?php echo e($err); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo BASE_URL; ?>register.php" class="needs-validation" novalidate>
                <?php csrf_field(); ?>

                <div class="mb-3">
                    <label class="form-label" for="full_name">Full name</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" required minlength="2"
                           value="<?php echo e($old['full_name']); ?>" placeholder="Jane Doe">
                    <div class="invalid-feedback">Please enter your full name.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="email">Email address</label>
                    <input type="email" class="form-control" id="email" name="email" required
                           value="<?php echo e($old['email']); ?>" placeholder="you@example.com">
                    <div class="invalid-feedback">Please enter a valid email address.</div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="phone">Phone number</label>
                        <input type="tel" class="form-control" id="phone" name="phone"
                               value="<?php echo e($old['phone']); ?>" placeholder="+1 234 567 890">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="gender">Gender</label>
                        <select class="form-select" id="gender" name="gender" required>
                            <option value="" disabled <?php echo $old['gender'] === '' ? 'selected' : ''; ?>>Select...</option>
                            <option value="male" <?php echo $old['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo $old['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo $old['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                        <div class="invalid-feedback">Please select a gender.</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="date_of_birth">Date of birth</label>
                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                           value="<?php echo e($old['date_of_birth']); ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="address">Address</label>
                    <input type="text" class="form-control" id="address" name="address"
                           value="<?php echo e($old['address']); ?>" placeholder="Street, City">
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required minlength="8"
                               placeholder="At least 8 characters">
                        <div class="form-text">Must include letters and numbers.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="confirm_password">Confirm password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required
                               data-match="#password" placeholder="Re-enter password">
                        <div class="invalid-feedback">Passwords do not match.</div>
                    </div>
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" id="terms" required>
                    <label class="form-check-label small" for="terms">I agree to the terms of service and privacy policy.</label>
                    <div class="invalid-feedback">You must agree before continuing.</div>
                </div>

                <button type="submit" class="btn btn-primary w-100 btn-lg mb-3">
                    <i class="bi bi-person-plus me-2"></i>Create Account
                </button>

                <p class="text-center text-muted small mb-0">
                    Already have an account? <a href="<?php echo BASE_URL; ?>login.php" class="fw-semibold">Log in</a>
                </p>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>