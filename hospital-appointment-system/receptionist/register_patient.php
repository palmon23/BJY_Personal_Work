<?php
/**
 * =====================================================================
 * receptionist/register_patient.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * Front-desk patient registration. A full page rather than a modal —
 * receptionists will use this repeatedly throughout the day, often for
 * walk-ins, so it gets its own dedicated screen with a clear next step
 * (book an appointment immediately after registering).
 * =====================================================================
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('receptionist');

$errors = [];
$old = ['full_name' => '', 'email' => '', 'phone' => '', 'gender' => '', 'date_of_birth' => '', 'address' => '', 'blood_group' => '', 'emergency_contact_name' => '', 'emergency_contact_phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $fullName = sanitize($_POST['full_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $gender = sanitize($_POST['gender'] ?? '');
    $dob = sanitize($_POST['date_of_birth'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $bloodGroup = sanitize($_POST['blood_group'] ?? '');
    $emergencyName = sanitize($_POST['emergency_contact_name'] ?? '');
    $emergencyPhone = sanitize($_POST['emergency_contact_phone'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    $old = compact('fullName', 'email', 'phone', 'gender', 'dob', 'address', 'bloodGroup', 'emergencyName', 'emergencyPhone');
    $old = ['full_name' => $fullName, 'email' => $email, 'phone' => $phone, 'gender' => $gender, 'date_of_birth' => $dob, 'address' => $address, 'blood_group' => $bloodGroup, 'emergency_contact_name' => $emergencyName, 'emergency_contact_phone' => $emergencyPhone];

    if ($fullName === '' || mb_strlen($fullName) < 2) $errors[] = 'Please enter the patient\'s full name.';
    if (!is_valid_email($email)) $errors[] = 'Please enter a valid email address.';
    if ($phone !== '' && !is_valid_phone($phone)) $errors[] = 'Please enter a valid phone number.';
    if (!in_array($gender, ['male', 'female', 'other'], true)) $errors[] = 'Please select a gender.';
    if (!is_strong_password($password)) $errors[] = 'Password must be at least 8 characters with letters and numbers.';

    if (empty($errors)) {
        $stmt = db_query($conn, "SELECT id FROM users WHERE email = ?", 's', [$email]);
        if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
            $errors[] = 'A patient with that email is already registered. Try searching for them instead.';
        }
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $dobValue = $dob !== '' ? $dob : null;

        db_query(
            $conn,
            "INSERT INTO users (role, full_name, email, password, phone, gender, date_of_birth, address, status) VALUES ('patient', ?, ?, ?, ?, ?, ?, ?, 'active')",
            'sssssss',
            [$fullName, $email, $hash, $phone, $gender, $dobValue, $address]
        );
        $newUserId = (int) mysqli_insert_id($conn);

        db_query(
            $conn,
            "INSERT INTO patients (user_id, blood_group, emergency_contact_name, emergency_contact_phone) VALUES (?, ?, ?, ?)",
            'isss',
            [$newUserId, $bloodGroup, $emergencyName, $emergencyPhone]
        );
        $newPatientId = (int) mysqli_insert_id($conn);

        add_notification($conn, $newUserId, 'Welcome to HAMS', 'Your patient account was created at the front desk. A staff member can now book your appointment.');
        log_activity($conn, current_user_id(), 'patient_registered', "Receptionist registered patient: $fullName ($email)");

        set_flash('success', $fullName . ' has been registered successfully.');
        redirect('receptionist/book_appointment.php?patient_id=' . $newPatientId);
    }
}

$pageTitle = 'Register Patient';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="hams-main">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="hams-content">

        <div class="mb-4">
            <h2 class="h4 fw-bold mb-1">Register New Patient</h2>
            <p class="text-muted mb-0">Create a patient account for a walk-in or phone registration.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $err): ?>
                    <div><i class="bi bi-exclamation-circle me-1"></i><?php echo e($err); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="hams-card p-4">
                    <form method="POST" class="needs-validation" novalidate>
                        <?php csrf_field(); ?>

                        <h6 class="fw-bold mb-3">Personal Information</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="full_name" required minlength="2" value="<?php echo e($old['full_name']); ?>" autofocus>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" required value="<?php echo e($old['email']); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone" value="<?php echo e($old['phone']); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender" required>
                                    <option value="" disabled <?php echo $old['gender'] === '' ? 'selected' : ''; ?>>Select...</option>
                                    <option value="male" <?php echo $old['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo $old['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo $old['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="date_of_birth" value="<?php echo e($old['date_of_birth']); ?>" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control" name="address" value="<?php echo e($old['address']); ?>">
                            </div>
                        </div>

                        <h6 class="fw-bold mb-3">Medical &amp; Emergency Info <span class="text-muted small fw-normal">(optional)</span></h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Blood Group</label>
                                <input type="text" class="form-control" name="blood_group" placeholder="e.g. O+" value="<?php echo e($old['blood_group']); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Emergency Contact Name</label>
                                <input type="text" class="form-control" name="emergency_contact_name" value="<?php echo e($old['emergency_contact_name']); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Emergency Contact Phone</label>
                                <input type="tel" class="form-control" name="emergency_contact_phone" value="<?php echo e($old['emergency_contact_phone']); ?>">
                            </div>
                        </div>

                        <h6 class="fw-bold mb-3">Account Password</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Initial Password</label>
                                <input type="password" class="form-control" name="password" required minlength="8" placeholder="At least 8 characters">
                                <div class="form-text">Share this with the patient so they can log in later, or they can reset it themselves.</div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg mt-4"><i class="bi bi-person-plus me-1"></i>Register &amp; Continue to Booking</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="hams-card p-4">
                    <h6 class="fw-bold mb-2"><i class="bi bi-lightbulb me-2"></i>Tip</h6>
                    <p class="text-muted small mb-0">After registering, you'll be taken straight to the booking screen with this patient pre-selected — no need to search for them again.</p>
                </div>
            </div>
        </div>

    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>