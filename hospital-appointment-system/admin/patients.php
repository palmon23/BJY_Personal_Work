<?php
/**
 * =====================================================================
 * admin/patients.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * View, search, add, edit, and manage patient accounts. Includes a
 * quick "View" panel showing each patient's appointment history.
 * =====================================================================
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

/* =====================================================================
   POST HANDLERS
   ===================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['form_action'] ?? '';

    // -----------------------------------------------------------------
    // ADD PATIENT
    // -----------------------------------------------------------------
    if ($action === 'add') {
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

        $errors = [];
        if ($fullName === '' || mb_strlen($fullName) < 2) $errors[] = 'Please enter the patient\'s full name.';
        if (!is_valid_email($email)) $errors[] = 'Please enter a valid email address.';
        if (!is_strong_password($password)) $errors[] = 'Password must be at least 8 characters with letters and numbers.';
        if (!in_array($gender, ['male', 'female', 'other'], true)) $errors[] = 'Please select a gender.';

        if (empty($errors)) {
            $stmt = db_query($conn, "SELECT id FROM users WHERE email = ?", 's', [$email]);
            if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
                $errors[] = 'A user with that email already exists.';
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
            $userId = (int) mysqli_insert_id($conn);

            db_query(
                $conn,
                "INSERT INTO patients (user_id, blood_group, emergency_contact_name, emergency_contact_phone) VALUES (?, ?, ?, ?)",
                'isss',
                [$userId, $bloodGroup, $emergencyName, $emergencyPhone]
            );

            add_notification($conn, $userId, 'Welcome to HAMS', 'Your patient account has been created by the administrator.');
            log_activity($conn, current_user_id(), 'patient_added', "Added patient: $fullName ($email)");
            set_flash('success', 'Patient added successfully.');
        } else {
            set_flash('error', implode(' ', $errors));
        }
        redirect('admin/patients.php');
    }

    // -----------------------------------------------------------------
    // EDIT PATIENT
    // -----------------------------------------------------------------
    if ($action === 'edit') {
        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $userId = (int) ($_POST['user_id'] ?? 0);
        $fullName = sanitize($_POST['full_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $gender = sanitize($_POST['gender'] ?? '');
        $dob = sanitize($_POST['date_of_birth'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $bloodGroup = sanitize($_POST['blood_group'] ?? '');
        $emergencyName = sanitize($_POST['emergency_contact_name'] ?? '');
        $emergencyPhone = sanitize($_POST['emergency_contact_phone'] ?? '');
        $newPassword = (string) ($_POST['password'] ?? '');

        $errors = [];
        if ($patientId <= 0 || $userId <= 0) $errors[] = 'Invalid patient record.';
        if ($fullName === '' || mb_strlen($fullName) < 2) $errors[] = 'Please enter the patient\'s full name.';
        if (!is_valid_email($email)) $errors[] = 'Please enter a valid email address.';
        if ($newPassword !== '' && !is_strong_password($newPassword)) $errors[] = 'New password must be at least 8 characters with letters and numbers.';

        if (empty($errors)) {
            $stmt = db_query($conn, "SELECT id FROM users WHERE email = ? AND id != ?", 'si', [$email, $userId]);
            if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
                $errors[] = 'Another user already uses that email.';
            }
        }

        if (empty($errors)) {
            $dobValue = $dob !== '' ? $dob : null;
            db_query($conn, "UPDATE users SET full_name=?, email=?, phone=?, gender=?, date_of_birth=?, address=? WHERE id=?", 'ssssssi', [$fullName, $email, $phone, $gender, $dobValue, $address, $userId]);

            if ($newPassword !== '') {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                db_query($conn, "UPDATE users SET password=? WHERE id=?", 'si', [$hash, $userId]);
            }

            db_query($conn, "UPDATE patients SET blood_group=?, emergency_contact_name=?, emergency_contact_phone=? WHERE id=?", 'sssi', [$bloodGroup, $emergencyName, $emergencyPhone, $patientId]);

            log_activity($conn, current_user_id(), 'patient_updated', "Updated patient #$patientId");
            set_flash('success', 'Patient details updated.');
        } else {
            set_flash('error', implode(' ', $errors));
        }
        redirect('admin/patients.php');
    }

    // -----------------------------------------------------------------
    // TOGGLE STATUS
    // -----------------------------------------------------------------
    if ($action === 'toggle_status') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $newStatus = $_POST['new_status'] === 'active' ? 'active' : 'suspended';
        db_query($conn, "UPDATE users SET status=? WHERE id=?", 'si', [$newStatus, $userId]);
        log_activity($conn, current_user_id(), 'patient_status_changed', "Patient user #$userId set to $newStatus");
        set_flash('success', 'Patient status updated.');
        redirect('admin/patients.php');
    }

    // -----------------------------------------------------------------
    // DELETE
    // -----------------------------------------------------------------
    if ($action === 'delete') {
        $userId = (int) ($_POST['user_id'] ?? 0);

        $stmt = db_query($conn, "SELECT COUNT(*) c FROM appointments a JOIN patients p ON p.id = a.patient_id WHERE p.user_id = ? AND a.status IN ('pending','confirmed')", 'i', [$userId]);
        $activeCount = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];

        if ($activeCount > 0) {
            set_flash('error', "Cannot delete this patient — they have $activeCount active appointment(s). Cancel those first.");
        } else {
            db_query($conn, "DELETE FROM users WHERE id = ?", 'i', [$userId]); // cascades to patients, appointments history stays via FK... see note
            log_activity($conn, current_user_id(), 'patient_deleted', "Deleted patient user #$userId");
            set_flash('success', 'Patient removed from the system.');
        }
        redirect('admin/patients.php');
    }
}

/* =====================================================================
   LIST + SEARCH + FILTER + PAGINATION
   ===================================================================== */
$search = sanitize($_GET['search'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');

$where = ['1=1'];
$types = '';
$params = [];

if ($search !== '') {
    $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'sss';
    $params = array_merge($params, [$like, $like, $like]);
}
if (in_array($statusFilter, ['active', 'inactive', 'suspended'], true)) {
    $where[] = 'u.status = ?';
    $types .= 's';
    $params[] = $statusFilter;
}
$whereSql = implode(' AND ', $where);

$countSql = "SELECT COUNT(*) c FROM patients p JOIN users u ON u.id = p.user_id WHERE $whereSql";
$stmt = $types ? db_query($conn, $countSql, $types, $params) : db_query($conn, $countSql);
$totalRecords = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
$pagination = paginate($totalRecords);

$listSql = "SELECT p.id AS patient_id, p.blood_group, p.emergency_contact_name, p.emergency_contact_phone,
                   u.id AS user_id, u.full_name, u.email, u.phone, u.gender, u.date_of_birth, u.address, u.profile_photo, u.status,
                   (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.id) AS appointment_count
            FROM patients p
            JOIN users u ON u.id = p.user_id
            WHERE $whereSql
            ORDER BY u.created_at DESC
            LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}";
$stmt = $types ? db_query($conn, $listSql, $types, $params) : db_query($conn, $listSql);
$patients = [];
if ($stmt) {
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $patients[] = $row;
    }
}

$pageTitle = 'Manage Patients';

$extraScripts = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-edit-patient').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('edit_patient_id').value = btn.dataset.patientId;
            document.getElementById('edit_user_id').value = btn.dataset.userId;
            document.getElementById('edit_full_name').value = btn.dataset.fullName;
            document.getElementById('edit_email').value = btn.dataset.email;
            document.getElementById('edit_phone').value = btn.dataset.phone;
            document.getElementById('edit_gender').value = btn.dataset.gender;
            document.getElementById('edit_dob').value = btn.dataset.dob;
            document.getElementById('edit_address').value = btn.dataset.address;
            document.getElementById('edit_blood_group').value = btn.dataset.bloodGroup;
            document.getElementById('edit_emergency_name').value = btn.dataset.emergencyName;
            document.getElementById('edit_emergency_phone').value = btn.dataset.emergencyPhone;
            new bootstrap.Modal(document.getElementById('editPatientModal')).show();
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
                <h2 class="h4 fw-bold mb-1">Patients</h2>
                <p class="text-muted mb-0"><?php echo $totalRecords; ?> patient<?php echo $totalRecords === 1 ? '' : 's'; ?> registered.</p>
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPatientModal">
                <i class="bi bi-person-plus me-1"></i>Add Patient
            </button>
        </div>

        <form method="GET" class="hams-card p-3 mb-4">
            <div class="row g-2 align-items-end">
                <div class="col-md-7">
                    <label class="form-label mb-1">Search</label>
                    <input type="text" class="form-control" name="search" value="<?php echo e($search); ?>" placeholder="Name, email or phone...">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All</option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="suspended" <?php echo $statusFilter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search"></i></button>
                    <a href="<?php echo BASE_URL; ?>admin/patients.php" class="btn btn-outline-secondary flex-fill"><i class="bi bi-x-lg"></i></a>
                </div>
            </div>
        </form>

        <div class="table-responsive-card mb-3">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Contact</th>
                            <th>Gender / DOB</th>
                            <th>Appointments</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($patients)): ?>
                            <tr><td colspan="6">
                                <div class="empty-state"><i class="bi bi-people"></i><p class="mb-0">No patients found. Try adjusting your search or filters.</p></div>
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($patients as $pat): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?php echo UPLOADS_URL . e($pat['profile_photo'] ?: 'default-avatar.png'); ?>"
                                             onerror="this.src='<?php echo ASSETS_URL; ?>images/default-avatar.png'" class="avatar-sm" alt="">
                                        <div>
                                            <div class="fw-semibold"><?php echo e($pat['full_name']); ?></div>
                                            <div class="text-muted small">Blood: <?php echo e($pat['blood_group'] ?: '—'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo e($pat['email']); ?></div>
                                    <div class="text-muted small"><?php echo e($pat['phone'] ?: '—'); ?></div>
                                </td>
                                <td>
                                    <div class="text-capitalize"><?php echo e($pat['gender'] ?: '—'); ?></div>
                                    <div class="text-muted small"><?php echo format_date($pat['date_of_birth']); ?></div>
                                </td>
                                <td><span class="badge bg-primary-soft text-primary"><?php echo (int) $pat['appointment_count']; ?></span></td>
                                <td><span class="badge <?php echo status_badge_class($pat['status']); ?>"><?php echo e(ucfirst($pat['status'])); ?></span></td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <button type="button" class="icon-btn btn-edit-patient"
                                            data-patient-id="<?php echo $pat['patient_id']; ?>"
                                            data-user-id="<?php echo $pat['user_id']; ?>"
                                            data-full-name="<?php echo e($pat['full_name']); ?>"
                                            data-email="<?php echo e($pat['email']); ?>"
                                            data-phone="<?php echo e($pat['phone']); ?>"
                                            data-gender="<?php echo e($pat['gender']); ?>"
                                            data-dob="<?php echo e($pat['date_of_birth']); ?>"
                                            data-address="<?php echo e($pat['address']); ?>"
                                            data-blood-group="<?php echo e($pat['blood_group']); ?>"
                                            data-emergency-name="<?php echo e($pat['emergency_contact_name']); ?>"
                                            data-emergency-phone="<?php echo e($pat['emergency_contact_phone']); ?>"
                                            title="Edit"><i class="bi bi-pencil"></i></button>

                                        <a href="<?php echo BASE_URL; ?>admin/patient_view.php?id=<?php echo $pat['patient_id']; ?>" class="icon-btn" title="View history"><i class="bi bi-eye"></i></a>

                                        <form method="POST" class="d-inline">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="form_action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo $pat['user_id']; ?>">
                                            <input type="hidden" name="new_status" value="<?php echo $pat['status'] === 'active' ? 'suspended' : 'active'; ?>">
                                            <button type="submit" class="icon-btn" title="<?php echo $pat['status'] === 'active' ? 'Suspend' : 'Activate'; ?>">
                                                <i class="bi <?php echo $pat['status'] === 'active' ? 'bi-toggle-on text-success' : 'bi-toggle-off'; ?>"></i>
                                            </button>
                                        </form>

                                        <form method="POST" data-confirm-delete data-confirm-message="Delete <?php echo e($pat['full_name']); ?>? This cannot be undone.">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="form_action" value="delete">
                                            <input type="hidden" name="user_id" value="<?php echo $pat['user_id']; ?>">
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

        <!-- ===================== ADD PATIENT MODAL ===================== -->
        <div class="modal fade" id="addPatientModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <form method="POST" class="needs-validation" novalidate>
                        <?php csrf_field(); ?>
                        <input type="hidden" name="form_action" value="add">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold">Add Patient</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Full Name</label><input type="text" class="form-control" name="full_name" required minlength="2"></div>
                                <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required></div>
                                <div class="col-md-6"><label class="form-label">Phone</label><input type="tel" class="form-control" name="phone"></div>
                                <div class="col-md-6">
                                    <label class="form-label">Gender</label>
                                    <select class="form-select" name="gender" required>
                                        <option value="" selected disabled>Select...</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6"><label class="form-label">Date of Birth</label><input type="date" class="form-control" name="date_of_birth" max="<?php echo date('Y-m-d'); ?>"></div>
                                <div class="col-md-6"><label class="form-label">Blood Group</label><input type="text" class="form-control" name="blood_group" placeholder="e.g. O+"></div>
                                <div class="col-12"><label class="form-label">Address</label><input type="text" class="form-control" name="address"></div>
                                <div class="col-md-6"><label class="form-label">Emergency Contact Name</label><input type="text" class="form-control" name="emergency_contact_name"></div>
                                <div class="col-md-6"><label class="form-label">Emergency Contact Phone</label><input type="tel" class="form-control" name="emergency_contact_phone"></div>
                                <div class="col-12"><label class="form-label">Initial Password</label><input type="password" class="form-control" name="password" required minlength="8"></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Add Patient</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ===================== EDIT PATIENT MODAL ===================== -->
        <div class="modal fade" id="editPatientModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <form method="POST" class="needs-validation" novalidate>
                        <?php csrf_field(); ?>
                        <input type="hidden" name="form_action" value="edit">
                        <input type="hidden" name="patient_id" id="edit_patient_id">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold">Edit Patient</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Full Name</label><input type="text" class="form-control" name="full_name" id="edit_full_name" required minlength="2"></div>
                                <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" id="edit_email" required></div>
                                <div class="col-md-6"><label class="form-label">Phone</label><input type="tel" class="form-control" name="phone" id="edit_phone"></div>
                                <div class="col-md-6">
                                    <label class="form-label">Gender</label>
                                    <select class="form-select" name="gender" id="edit_gender" required>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6"><label class="form-label">Date of Birth</label><input type="date" class="form-control" name="date_of_birth" id="edit_dob" max="<?php echo date('Y-m-d'); ?>"></div>
                                <div class="col-md-6"><label class="form-label">Blood Group</label><input type="text" class="form-control" name="blood_group" id="edit_blood_group"></div>
                                <div class="col-12"><label class="form-label">Address</label><input type="text" class="form-control" name="address" id="edit_address"></div>
                                <div class="col-md-6"><label class="form-label">Emergency Contact Name</label><input type="text" class="form-control" name="emergency_contact_name" id="edit_emergency_name"></div>
                                <div class="col-md-6"><label class="form-label">Emergency Contact Phone</label><input type="tel" class="form-control" name="emergency_contact_phone" id="edit_emergency_phone"></div>
                                <div class="col-12"><label class="form-label">New Password <span class="text-muted small">(optional)</span></label><input type="password" class="form-control" name="password" minlength="8" placeholder="Leave blank to keep current"></div>
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

    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>