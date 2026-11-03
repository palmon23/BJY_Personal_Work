<?php
/**
 * =====================================================================
 * doctor/profile.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * The logged-in doctor's own profile: personal details + photo,
 * doctor-specific fields (specialization, bio, fee — department is
 * admin-managed, not self-editable), and password change.
 * =====================================================================
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('doctor');

$userId = current_user_id();
$doctorId = (int) current_user()['doctor_id'];

/* =====================================================================
   POST HANDLERS
   ===================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['form_action'] ?? '';

    // -----------------------------------------------------------------
    // UPDATE PROFILE
    // -----------------------------------------------------------------
    if ($action === 'update_profile') {
        $fullName = sanitize($_POST['full_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $gender = sanitize($_POST['gender'] ?? '');
        $specialization = sanitize($_POST['specialization'] ?? '');
        $qualification = sanitize($_POST['qualification'] ?? '');
        $experienceYears = (int) ($_POST['experience_years'] ?? 0);
        $consultationFee = (float) ($_POST['consultation_fee'] ?? 0);
        $bio = sanitize($_POST['bio'] ?? '');

        $errors = [];
        if ($fullName === '' || mb_strlen($fullName) < 2) $errors[] = 'Please enter your full name.';
        if (!is_valid_email($email)) $errors[] = 'Please enter a valid email address.';
        if ($phone !== '' && !is_valid_phone($phone)) $errors[] = 'Please enter a valid phone number.';

        if (empty($errors)) {
            $stmt = db_query($conn, "SELECT id FROM users WHERE email = ? AND id != ?", 'si', [$email, $userId]);
            if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
                $errors[] = 'Another account already uses that email.';
            }
        }

        $photoPath = null;
        if (empty($errors) && !empty($_FILES['profile_photo']['name'])) {
            $uploadError = null;
            $photoPath = upload_image($_FILES['profile_photo'], 'doctors', $uploadError);
            if ($uploadError) $errors[] = $uploadError;
        }

        if (empty($errors)) {
            if ($photoPath) {
                db_query($conn, "UPDATE users SET full_name=?, email=?, phone=?, gender=?, profile_photo=? WHERE id=?", 'sssssi', [$fullName, $email, $phone, $gender, $photoPath, $userId]);
                $_SESSION['profile_photo'] = $photoPath;
            } else {
                db_query($conn, "UPDATE users SET full_name=?, email=?, phone=?, gender=? WHERE id=?", 'ssssi', [$fullName, $email, $phone, $gender, $userId]);
            }
            $_SESSION['full_name'] = $fullName;
            $_SESSION['email'] = $email;

            db_query(
                $conn,
                "UPDATE doctors SET specialization=?, qualification=?, experience_years=?, consultation_fee=?, bio=? WHERE id=?",
                'ssidsi',
                [$specialization, $qualification, $experienceYears, $consultationFee, $bio, $doctorId]
            );

            log_activity($conn, $userId, 'profile_updated', 'Doctor updated their own profile.');
            set_flash('success', 'Profile updated successfully.');
        } else {
            set_flash('error', implode(' ', $errors));
        }
        redirect('doctor/profile.php');
    }

    // -----------------------------------------------------------------
    // CHANGE PASSWORD
    // -----------------------------------------------------------------
    if ($action === 'change_password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        $stmt = db_query($conn, "SELECT password FROM users WHERE id = ?", 'i', [$userId]);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        $errors = [];
        if (!$row || !password_verify($currentPassword, $row['password'])) $errors[] = 'Your current password is incorrect.';
        if (!is_strong_password($newPassword)) $errors[] = 'New password must be at least 8 characters with letters and numbers.';
        if ($newPassword !== $confirmPassword) $errors[] = 'New password and confirmation do not match.';

        if (empty($errors)) {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            db_query($conn, "UPDATE users SET password=? WHERE id=?", 'si', [$hash, $userId]);
            log_activity($conn, $userId, 'password_changed', 'Doctor changed their own password.');
            set_flash('success', 'Password changed successfully.');
        } else {
            set_flash('error', implode(' ', $errors));
        }
        redirect('doctor/profile.php');
    }
}

/* =====================================================================
   FETCH CURRENT DATA
   ===================================================================== */
$stmt = db_query(
    $conn,
    "SELECT u.*, d.id AS doctor_id, d.specialization, d.qualification, d.experience_years, d.consultation_fee, d.bio, d.license_number, dept.name AS department_name
     FROM users u JOIN doctors d ON d.user_id = u.id JOIN departments dept ON dept.id = d.department_id
     WHERE u.id = ?",
    'i',
    [$userId]
);
$doctor = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="hams-main">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="hams-content">

        <div class="mb-4">
            <h2 class="h4 fw-bold mb-1">My Profile</h2>
            <p class="text-muted mb-0">Manage your professional details and account security.</p>
        </div>

        <div class="row g-4">
            <!-- Profile summary -->
            <div class="col-lg-4">
                <div class="hams-card p-4 text-center">
                    <img src="<?php echo UPLOADS_URL . e($doctor['profile_photo'] ?: 'default-avatar.png'); ?>"
                         onerror="this.src='<?php echo ASSETS_URL; ?>images/default-avatar.png'"
                         class="avatar-lg mb-3" alt="">
                    <h5 class="fw-bold mb-0">Dr. <?php echo e($doctor['full_name']); ?></h5>
                    <p class="text-muted small mb-2"><?php echo e($doctor['specialization'] ?: $doctor['department_name']); ?></p>
                    <span class="badge bg-primary-soft text-primary"><?php echo e($doctor['department_name']); ?></span>
                    <hr>
                    <div class="text-start small">
                        <div class="d-flex justify-content-between py-1"><span class="text-muted">Email</span><span><?php echo e($doctor['email']); ?></span></div>
                        <div class="d-flex justify-content-between py-1"><span class="text-muted">Phone</span><span><?php echo e($doctor['phone'] ?: '—'); ?></span></div>
                        <div class="d-flex justify-content-between py-1"><span class="text-muted">License No.</span><span><?php echo e($doctor['license_number'] ?: '—'); ?></span></div>
                        <div class="d-flex justify-content-between py-1"><span class="text-muted">Experience</span><span><?php echo (int) $doctor['experience_years']; ?> yrs</span></div>
                        <div class="d-flex justify-content-between py-1"><span class="text-muted">Fee</span><span>$<?php echo number_format((float) $doctor['consultation_fee'], 2); ?></span></div>
                    </div>
                    <div class="alert alert-primary-soft small mt-3 mb-0" style="background:var(--hams-primary-light);">
                        <i class="bi bi-info-circle me-1"></i>Department assignment is managed by the administrator.
                    </div>
                </div>
            </div>

            <!-- Edit forms -->
            <div class="col-lg-8">
                <div class="hams-card p-4 mb-4">
                    <h6 class="fw-bold mb-3">Personal &amp; Professional Details</h6>
                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <?php csrf_field(); ?>
                        <input type="hidden" name="form_action" value="update_profile">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Full Name</label><input type="text" class="form-control" name="full_name" required minlength="2" value="<?php echo e($doctor['full_name']); ?>"></div>
                            <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required value="<?php echo e($doctor['email']); ?>"></div>
                            <div class="col-md-6"><label class="form-label">Phone</label><input type="tel" class="form-control" name="phone" value="<?php echo e($doctor['phone']); ?>"></div>
                            <div class="col-md-6">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender">
                                    <option value="male" <?php echo $doctor['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo $doctor['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo $doctor['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-6"><label class="form-label">Specialization</label><input type="text" class="form-control" name="specialization" value="<?php echo e($doctor['specialization']); ?>"></div>
                            <div class="col-md-6"><label class="form-label">Qualification</label><input type="text" class="form-control" name="qualification" value="<?php echo e($doctor['qualification']); ?>"></div>
                            <div class="col-md-6"><label class="form-label">Experience (years)</label><input type="number" class="form-control" name="experience_years" min="0" max="70" value="<?php echo (int) $doctor['experience_years']; ?>"></div>
                            <div class="col-md-6"><label class="form-label">Consultation Fee</label><input type="number" class="form-control" name="consultation_fee" min="0" step="0.01" value="<?php echo (float) $doctor['consultation_fee']; ?>"></div>
                            <div class="col-12"><label class="form-label">Profile Photo</label><input type="file" class="form-control" name="profile_photo" accept="image/png, image/jpeg, image/webp"></div>
                            <div class="col-12"><label class="form-label">Short Bio</label><textarea class="form-control" name="bio" rows="3"><?php echo e($doctor['bio']); ?></textarea></div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                    </form>
                </div>

                <div class="hams-card p-4">
                    <h6 class="fw-bold mb-3">Change Password</h6>
                    <form method="POST" class="needs-validation" novalidate>
                        <?php csrf_field(); ?>
                        <input type="hidden" name="form_action" value="change_password">
                        <div class="row g-3">
                            <div class="col-12"><label class="form-label">Current Password</label><input type="password" class="form-control" name="current_password" required></div>
                            <div class="col-md-6"><label class="form-label">New Password</label><input type="password" class="form-control" name="new_password" id="new_password" required minlength="8"></div>
                            <div class="col-md-6"><label class="form-label">Confirm New Password</label><input type="password" class="form-control" name="confirm_password" required data-match="#new_password"></div>
                        </div>
                        <button type="submit" class="btn btn-secondary mt-3"><i class="bi bi-shield-lock me-1"></i>Change Password</button>
                    </form>
                </div>
            </div>
        </div>

    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>