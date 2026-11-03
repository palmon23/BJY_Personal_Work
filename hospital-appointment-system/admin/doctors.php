<?php
/**
 * =====================================================================
 * admin/doctors.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * Full CRUD for doctors: list (search + filter + pagination), add,
 * edit, activate/deactivate, delete. Doctor accounts are ONLY created
 * here by an administrator — never through public self-registration.
 * =====================================================================
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

/* =====================================================================
   POST HANDLERS (add / edit / delete / toggle-status)
   ===================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['form_action'] ?? '';

    // -----------------------------------------------------------------
    // ADD DOCTOR
    // -----------------------------------------------------------------
    if ($action === 'add') {
        $fullName        = sanitize($_POST['full_name'] ?? '');
        $email           = sanitize($_POST['email'] ?? '');
        $phone           = sanitize($_POST['phone'] ?? '');
        $gender          = sanitize($_POST['gender'] ?? '');
        $password        = (string) ($_POST['password'] ?? '');
        $departmentId    = (int) ($_POST['department_id'] ?? 0);
        $specialization  = sanitize($_POST['specialization'] ?? '');
        $qualification   = sanitize($_POST['qualification'] ?? '');
        $experienceYears = (int) ($_POST['experience_years'] ?? 0);
        $consultationFee = (float) ($_POST['consultation_fee'] ?? 0);
        $licenseNumber   = sanitize($_POST['license_number'] ?? '');
        $bio             = sanitize($_POST['bio'] ?? '');

        $errors = [];
        if ($fullName === '' || mb_strlen($fullName) < 2) $errors[] = 'Please enter the doctor\'s full name.';
        if (!is_valid_email($email)) $errors[] = 'Please enter a valid email address.';
        if (!is_strong_password($password)) $errors[] = 'Password must be at least 8 characters with letters and numbers.';
        if ($departmentId <= 0) $errors[] = 'Please select a department.';
        if (!in_array($gender, ['male', 'female', 'other'], true)) $errors[] = 'Please select a gender.';

        if (empty($errors)) {
            $stmt = db_query($conn, "SELECT id FROM users WHERE email = ?", 's', [$email]);
            if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
                $errors[] = 'A user with that email already exists.';
            }
        }

        if (empty($errors)) {
            $uploadError = null;
            $photoPath = null;
            if (!empty($_FILES['profile_photo']['name'])) {
                $photoPath = upload_image($_FILES['profile_photo'], 'doctors', $uploadError);
                if ($uploadError) $errors[] = $uploadError;
            }
        }

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = db_query(
                $conn,
                "INSERT INTO users (role, full_name, email, password, phone, gender, profile_photo, status) VALUES ('doctor', ?, ?, ?, ?, ?, ?, 'active')",
                'ssssss',
                [$fullName, $email, $hash, $phone, $gender, $photoPath]
            );
            $userId = (int) mysqli_insert_id($conn);

            db_query(
                $conn,
                "INSERT INTO doctors (user_id, department_id, specialization, qualification, experience_years, consultation_fee, bio, license_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                'iissiiss',
                [$userId, $departmentId, $specialization, $qualification, $experienceYears, $consultationFee, $bio, $licenseNumber]
            );

            add_notification($conn, $userId, 'Welcome to HAMS', 'Your doctor account has been created by the administrator. You can now log in and set your schedule.');
            log_activity($conn, current_user_id(), 'doctor_added', "Added doctor: $fullName ($email)");
            set_flash('success', 'Doctor added successfully.');
        } else {
            set_flash('error', implode(' ', $errors));
        }
        redirect('admin/doctors.php');
    }

    // -----------------------------------------------------------------
    // EDIT DOCTOR
    // -----------------------------------------------------------------
    if ($action === 'edit') {
        $doctorId        = (int) ($_POST['doctor_id'] ?? 0);
        $userId          = (int) ($_POST['user_id'] ?? 0);
        $fullName        = sanitize($_POST['full_name'] ?? '');
        $email           = sanitize($_POST['email'] ?? '');
        $phone           = sanitize($_POST['phone'] ?? '');
        $gender          = sanitize($_POST['gender'] ?? '');
        $newPassword     = (string) ($_POST['password'] ?? '');
        $departmentId    = (int) ($_POST['department_id'] ?? 0);
        $specialization  = sanitize($_POST['specialization'] ?? '');
        $qualification   = sanitize($_POST['qualification'] ?? '');
        $experienceYears = (int) ($_POST['experience_years'] ?? 0);
        $consultationFee = (float) ($_POST['consultation_fee'] ?? 0);
        $licenseNumber   = sanitize($_POST['license_number'] ?? '');
        $bio             = sanitize($_POST['bio'] ?? '');

        $errors = [];
        if ($doctorId <= 0 || $userId <= 0) $errors[] = 'Invalid doctor record.';
        if ($fullName === '' || mb_strlen($fullName) < 2) $errors[] = 'Please enter the doctor\'s full name.';
        if (!is_valid_email($email)) $errors[] = 'Please enter a valid email address.';
        if ($newPassword !== '' && !is_strong_password($newPassword)) $errors[] = 'New password must be at least 8 characters with letters and numbers.';
        if ($departmentId <= 0) $errors[] = 'Please select a department.';

        if (empty($errors)) {
            $stmt = db_query($conn, "SELECT id FROM users WHERE email = ? AND id != ?", 'si', [$email, $userId]);
            if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
                $errors[] = 'Another user already uses that email.';
            }
        }

        if (empty($errors)) {
            $uploadError = null;
            $photoPath = null;
            if (!empty($_FILES['profile_photo']['name'])) {
                $photoPath = upload_image($_FILES['profile_photo'], 'doctors', $uploadError);
                if ($uploadError) $errors[] = $uploadError;
            }
        }

        if (empty($errors)) {
            if (!empty($photoPath)) {
                db_query($conn, "UPDATE users SET full_name=?, email=?, phone=?, gender=?, profile_photo=? WHERE id=?", 'sssssi', [$fullName, $email, $phone, $gender, $photoPath, $userId]);
            } else {
                db_query($conn, "UPDATE users SET full_name=?, email=?, phone=?, gender=? WHERE id=?", 'ssssi', [$fullName, $email, $phone, $gender, $userId]);
            }

            if ($newPassword !== '') {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                db_query($conn, "UPDATE users SET password=? WHERE id=?", 'si', [$hash, $userId]);
            }

            db_query(
                $conn,
                "UPDATE doctors SET department_id=?, specialization=?, qualification=?, experience_years=?, consultation_fee=?, bio=?, license_number=? WHERE id=?",
                'issiissi',
                [$departmentId, $specialization, $qualification, $experienceYears, $consultationFee, $bio, $licenseNumber, $doctorId]
            );

            log_activity($conn, current_user_id(), 'doctor_updated', "Updated doctor #$doctorId ($email)");
            set_flash('success', 'Doctor details updated.');
        } else {
            set_flash('error', implode(' ', $errors));
        }
        redirect('admin/doctors.php');
    }

    // -----------------------------------------------------------------
    // TOGGLE STATUS (activate / deactivate)
    // -----------------------------------------------------------------
    if ($action === 'toggle_status') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $doctorId = (int) ($_POST['doctor_id'] ?? 0);
        $newStatus = $_POST['new_status'] === 'active' ? 'active' : 'inactive';

        db_query($conn, "UPDATE users SET status=? WHERE id=?", 'si', [$newStatus, $userId]);
        db_query($conn, "UPDATE doctors SET status=? WHERE id=?", 'si', [$newStatus, $doctorId]);

        log_activity($conn, current_user_id(), 'doctor_status_changed', "Doctor #$doctorId set to $newStatus");
        set_flash('success', 'Doctor status updated.');
        redirect('admin/doctors.php');
    }

    // -----------------------------------------------------------------
    // DELETE DOCTOR
    // -----------------------------------------------------------------
    if ($action === 'delete') {
        $userId = (int) ($_POST['user_id'] ?? 0);

        $stmt = db_query($conn, "SELECT COUNT(*) c FROM appointments a JOIN doctors d ON d.id = a.doctor_id WHERE d.user_id = ? AND a.status IN ('pending','confirmed')", 'i', [$userId]);
        $activeCount = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];

        if ($activeCount > 0) {
            set_flash('error', 'Cannot delete this doctor — they have ' . $activeCount . ' active appointment(s). Cancel or reassign them first.');
        } else {
            db_query($conn, "DELETE FROM users WHERE id = ?", 'i', [$userId]); // cascades to doctors, doctor_schedules, doctor_leaves
            log_activity($conn, current_user_id(), 'doctor_deleted', "Deleted doctor user #$userId");
            set_flash('success', 'Doctor removed from the system.');
        }
        redirect('admin/doctors.php');
    }
}

/* =====================================================================
   LIST + SEARCH + FILTER + PAGINATION
   ===================================================================== */
$search = sanitize($_GET['search'] ?? '');
$departmentFilter = (int) ($_GET['department'] ?? 0);
$statusFilter = sanitize($_GET['status'] ?? '');

$where = ['1=1'];
$types = '';
$params = [];

if ($search !== '') {
    $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR d.specialization LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'sss';
    $params = array_merge($params, [$like, $like, $like]);
}
if ($departmentFilter > 0) {
    $where[] = 'd.department_id = ?';
    $types .= 'i';
    $params[] = $departmentFilter;
}
if (in_array($statusFilter, ['active', 'inactive'], true)) {
    $where[] = 'd.status = ?';
    $types .= 's';
    $params[] = $statusFilter;
}
$whereSql = implode(' AND ', $where);

$countSql = "SELECT COUNT(*) c FROM doctors d JOIN users u ON u.id = d.user_id WHERE $whereSql";
$stmt = $types ? db_query($conn, $countSql, $types, $params) : db_query($conn, $countSql);
$totalRecords = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];

$pagination = paginate($totalRecords);

$listSql = "SELECT d.id AS doctor_id, d.department_id, d.specialization, d.qualification, d.experience_years,
                   d.consultation_fee, d.license_number, d.bio, d.status AS doctor_status,
                   u.id AS user_id, u.full_name, u.email, u.phone, u.gender, u.profile_photo, u.status AS user_status,
                   dept.name AS department_name
            FROM doctors d
            JOIN users u ON u.id = d.user_id
            JOIN departments dept ON dept.id = d.department_id
            WHERE $whereSql
            ORDER BY u.full_name ASC
            LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}";
$stmt = $types ? db_query($conn, $listSql, $types, $params) : db_query($conn, $listSql);
$doctors = [];
if ($stmt) {
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $doctors[] = $row;
    }
}

// Departments for the dropdowns
$departments = [];
$res = mysqli_query($conn, "SELECT id, name FROM departments WHERE status = 'active' ORDER BY name");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $departments[] = $row;
    }
}

$openAddModal = isset($_GET['action']) && $_GET['action'] === 'add';

$pageTitle = 'Manage Doctors';

$extraScripts = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Auto-open "Add Doctor" modal when linked via ?action=add
    var addModalEl = document.getElementById('addDoctorModal');
    if (addModalEl && addModalEl.dataset.autoshow === 'true') {
        new bootstrap.Modal(addModalEl).show();
    }

    // Populate Edit modal from the clicked row's data-* attributes
    document.querySelectorAll('.btn-edit-doctor').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('edit_doctor_id').value = btn.dataset.doctorId;
            document.getElementById('edit_user_id').value = btn.dataset.userId;
            document.getElementById('edit_full_name').value = btn.dataset.fullName;
            document.getElementById('edit_email').value = btn.dataset.email;
            document.getElementById('edit_phone').value = btn.dataset.phone;
            document.getElementById('edit_gender').value = btn.dataset.gender;
            document.getElementById('edit_department_id').value = btn.dataset.departmentId;
            document.getElementById('edit_specialization').value = btn.dataset.specialization;
            document.getElementById('edit_qualification').value = btn.dataset.qualification;
            document.getElementById('edit_experience').value = btn.dataset.experience;
            document.getElementById('edit_fee').value = btn.dataset.fee;
            document.getElementById('edit_license').value = btn.dataset.license;
            document.getElementById('edit_bio').value = btn.dataset.bio;
            new bootstrap.Modal(document.getElementById('editDoctorModal')).show();
        });
    });
});
</script>
HTML;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="hams-main">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="hams-content">

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="h4 fw-bold mb-1">Doctors</h2>
                <p class="text-muted mb-0"><?php echo $totalRecords; ?> doctor<?php echo $totalRecords === 1 ? '' : 's'; ?> in the system.</p>
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDoctorModal">
                <i class="bi bi-person-plus me-1"></i>Add Doctor
            </button>
        </div>

        <!-- Filters -->
        <form method="GET" class="hams-card p-3 mb-4">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label mb-1">Search</label>
                    <input type="text" class="form-control" name="search" value="<?php echo e($search); ?>" placeholder="Name, email or specialization...">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">Department</label>
                    <select class="form-select" name="department">
                        <option value="0">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" <?php echo $departmentFilter === (int)$dept['id'] ? 'selected' : ''; ?>><?php echo e($dept['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All</option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search"></i></button>
                    <a href="<?php echo BASE_URL; ?>admin/doctors.php" class="btn btn-outline-secondary flex-fill"><i class="bi bi-x-lg"></i></a>
                </div>
            </div>
        </form>

        <!-- Doctors table -->
        <div class="table-responsive-card mb-3">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Doctor</th>
                            <th>Department</th>
                            <th>Specialization</th>
                            <th>Experience</th>
                            <th>Fee</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($doctors)): ?>
                            <tr><td colspan="7">
                                <div class="empty-state"><i class="bi bi-person-badge"></i><p class="mb-0">No doctors found. Try adjusting your search or filters.</p></div>
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($doctors as $doc): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?php echo UPLOADS_URL . e($doc['profile_photo'] ?: 'default-avatar.png'); ?>"
                                             onerror="this.src='<?php echo ASSETS_URL; ?>images/default-avatar.png'" class="avatar-sm" alt="">
                                        <div>
                                            <div class="fw-semibold">Dr. <?php echo e($doc['full_name']); ?></div>
                                            <div class="text-muted small"><?php echo e($doc['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo e($doc['department_name']); ?></td>
                                <td><?php echo e($doc['specialization'] ?: '—'); ?></td>
                                <td><?php echo (int) $doc['experience_years']; ?> yrs</td>
                                <td>$<?php echo number_format((float) $doc['consultation_fee'], 2); ?></td>
                                <td><span class="badge <?php echo status_badge_class($doc['doctor_status']); ?>"><?php echo e(ucfirst($doc['doctor_status'])); ?></span></td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <button type="button" class="icon-btn btn-edit-doctor"
                                            data-doctor-id="<?php echo $doc['doctor_id']; ?>"
                                            data-user-id="<?php echo $doc['user_id']; ?>"
                                            data-full-name="<?php echo e($doc['full_name']); ?>"
                                            data-email="<?php echo e($doc['email']); ?>"
                                            data-phone="<?php echo e($doc['phone']); ?>"
                                            data-gender="<?php echo e($doc['gender']); ?>"
                                            data-department-id="<?php echo $doc['department_id']; ?>"
                                            data-specialization="<?php echo e($doc['specialization']); ?>"
                                            data-qualification="<?php echo e($doc['qualification']); ?>"
                                            data-experience="<?php echo (int) $doc['experience_years']; ?>"
                                            data-fee="<?php echo (float) $doc['consultation_fee']; ?>"
                                            data-license="<?php echo e($doc['license_number']); ?>"
                                            data-bio="<?php echo e($doc['bio']); ?>"
                                            title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <form method="POST" class="d-inline">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="form_action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo $doc['user_id']; ?>">
                                            <input type="hidden" name="doctor_id" value="<?php echo $doc['doctor_id']; ?>">
                                            <input type="hidden" name="new_status" value="<?php echo $doc['doctor_status'] === 'active' ? 'inactive' : 'active'; ?>">
                                            <button type="submit" class="icon-btn" title="<?php echo $doc['doctor_status'] === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="bi <?php echo $doc['doctor_status'] === 'active' ? 'bi-toggle-on text-success' : 'bi-toggle-off'; ?>"></i>
                                            </button>
                                        </form>

                                        <form method="POST" data-confirm-delete data-confirm-message="Delete Dr. <?php echo e($doc['full_name']); ?>? This cannot be undone.">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="form_action" value="delete">
                                            <input type="hidden" name="user_id" value="<?php echo $doc['user_id']; ?>">
                                            <button type="submit" class="icon-btn text-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php render_pagination($pagination['currentPage'], $pagination['totalPages']); ?>

    </main>

    <!-- ===================== ADD DOCTOR MODAL ===================== -->
<div class="modal fade" id="addDoctorModal" tabindex="-1" aria-hidden="true"<?php echo $openAddModal ? ' data-autoshow="true"' : ''; ?>>
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                <?php csrf_field(); ?>
                <input type="hidden" name="form_action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Add New Doctor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" required minlength="2" placeholder="Dr. John Smith">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required placeholder="doctor@hospital.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" placeholder="+1 234 567 890">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="gender" required>
                                <option value="" selected disabled>Select...</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Initial Password</label>
                            <input type="password" class="form-control" name="password" required minlength="8" placeholder="At least 8 characters">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department_id" required>
                                <option value="" selected disabled>Select department...</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>"><?php echo e($dept['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Specialization</label>
                            <input type="text" class="form-control" name="specialization" placeholder="e.g. Cardiologist">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Qualification</label>
                            <input type="text" class="form-control" name="qualification" placeholder="e.g. MBBS, MD">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Experience (years)</label>
                            <input type="number" class="form-control" name="experience_years" min="0" max="70" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Consultation Fee</label>
                            <input type="number" class="form-control" name="consultation_fee" min="0" step="0.01" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">License Number</label>
                            <input type="text" class="form-control" name="license_number" placeholder="LIC-0000">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Profile Photo</label>
                            <input type="file" class="form-control" name="profile_photo" accept="image/png, image/jpeg, image/webp">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Short Bio</label>
                            <textarea class="form-control" name="bio" rows="3" placeholder="Brief professional background..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Add Doctor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===================== EDIT DOCTOR MODAL ===================== -->
<div class="modal fade" id="editDoctorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data" id="editDoctorForm" class="needs-validation" novalidate>
                <?php csrf_field(); ?>
                <input type="hidden" name="form_action" value="edit">
                <input type="hidden" name="doctor_id" id="edit_doctor_id">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Edit Doctor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" id="edit_full_name" required minlength="2">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="edit_email" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" id="edit_phone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="gender" id="edit_gender" required>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">New Password <span class="text-muted small">(optional)</span></label>
                            <input type="password" class="form-control" name="password" minlength="8" placeholder="Leave blank to keep current">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department_id" id="edit_department_id" required>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>"><?php echo e($dept['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Specialization</label>
                            <input type="text" class="form-control" name="specialization" id="edit_specialization">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Qualification</label>
                            <input type="text" class="form-control" name="qualification" id="edit_qualification">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Experience (years)</label>
                            <input type="number" class="form-control" name="experience_years" id="edit_experience" min="0" max="70">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Consultation Fee</label>
                            <input type="number" class="form-control" name="consultation_fee" id="edit_fee" min="0" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">License Number</label>
                            <input type="text" class="form-control" name="license_number" id="edit_license">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Profile Photo <span class="text-muted small">(optional)</span></label>
                            <input type="file" class="form-control" name="profile_photo" accept="image/png, image/jpeg, image/webp">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Short Bio</label>
                            <textarea class="form-control" name="bio" id="edit_bio" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>