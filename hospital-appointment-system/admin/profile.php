<?php
/**
 * =====================================================================
 * admin/profile.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * The logged-in administrator's own profile: edit personal details
 * (with photo upload) and change password. Session values are
 * refreshed immediately after a successful update so the navbar/
 * sidebar reflect changes without requiring re-login.
 * =====================================================================
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$userId = current_user_id();

/* =====================================================================
   POST HANDLERS
   ===================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['form_action'] ?? '';

    // -----------------------------------------------------------------
    // UPDATE PROFILE DETAILS
    // -----------------------------------------------------------------
    if ($action === 'update_profile') {
        $fullName = sanitize($_POST['full_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $gender = sanitize($_POST['gender'] ?? '');

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
            $photoPath = upload_image($_FILES['profile_photo'], 'admins', $uploadError);
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

            log_activity($conn, $userId, 'profile_updated', 'Administrator updated their own profile.');
            set_flash('success', 'Profile updated successfully.');
        } else {
            set_flash('error', implode(' ', $errors));
        }
        redirect('admin/profile.php');
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
        if (!$row || !password_verify($currentPassword, $row['password'])) {
            $errors[] = 'Your current password is incorrect.';
        }
        if (!is_strong_password($newPassword)) {
            $errors[] = 'New password must be at least 8 characters with letters and numbers.';
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'New password and confirmation do not match.';
        }

        if (empty($errors)) {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            db_query($conn, "UPDATE users SET password=? WHERE id=?", 'si', [$hash, $userId]);
            log_activity($conn, $userId, 'password_changed', 'Administrator changed their own password.');
            set_flash('success', 'Password changed successfully.');
        } else {
            set_flash('error', implode(' ', $errors));
        }
        redirect('admin/profile.php');
    }
}

/* =====================================================================
   FETCH CURRENT DATA
   ===================================================================== */
$stmt = db_query($conn, "SELECT * FROM users WHERE id = ?", 'i', [$userId]);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="hams-main">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="hams-content">

        <div class="mb-4">
            <h2 class="h4 fw-bold mb-1">My Profile</h2>
            <p class="text-muted mb-0">Manage your personal details and account security.</p>
        </div>

        <div class="row g-4">
            <!-- Profile summary card -->
            <div class="col-lg-4">
                <div class="hams-card p-4 text-center">
                    <img src="<?php echo UPLOADS_URL . e($user['profile_photo'] ?: 'default-avatar.png'); ?>"
                         onerror="this.src='<?php echo ASSETS_URL; ?>images/default-avatar.png'"
                         class="avatar-lg mb-3" alt="">
                    <h5 class="fw-bold mb-0"><?php echo e($user['full_name']); ?></h5>
                    <p class="text-muted small mb-2"><?php echo e($user['email']); ?></p>
                    <span class="badge bg-primary-soft text-primary">Administrator</span>
                    <hr>
                    <div class="text-start small">
                        <div class="d-flex justify-content-between py-1"><span class="text-muted">Phone</span><span><?php echo e($user['phone'] ?: '—'); ?></span></div>
                        <div class="d-flex justify-content-between py-1"><span class="text-muted">Member since</span><span><?php echo format_date($user['created_at']); ?></span></div>
                        <div class="d-flex justify-content-between py-1"><span class="text-muted">Last login</span><span><?php echo $user['last_login'] ? time_ago($user['last_login']) : 'This session'; ?></span></div>
                    </div>
                </div>
            </div>

            <!-- Edit forms -->
            <div class="col-lg-8">
                <div class="hams-card p-4 mb-4">
                    <h6 class="fw-bold mb-3">Personal Details</h6>
                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <?php csrf_field(); ?>
                        <input type="hidden" name="form_action" value="update_profile">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="full_name" required minlength="2" value="<?php echo e($user['full_name']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" required value="<?php echo e($user['email']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone" value="<?php echo e($user['phone']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender">
                                    <option value="" <?php echo empty($user['gender']) ? 'selected' : ''; ?>>Prefer not to say</option>
                                    <option value="male" <?php echo $user['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo $user['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo $user['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Profile Photo</label>
                                <input type="file" class="form-control" name="profile_photo" accept="image/png, image/jpeg, image/webp">
                            </div>
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
                            <div class="col-12">
                                <label class="form-label">Current Password</label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="new_password" id="new_password" required minlength="8">
                                <div class="form-text">At least 8 characters, with letters and numbers.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password" required data-match="#new_password">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-secondary mt-3"><i class="bi bi-shield-lock me-1"></i>Change Password</button>
                    </form>
                </div>
            </div>
        </div>

    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>