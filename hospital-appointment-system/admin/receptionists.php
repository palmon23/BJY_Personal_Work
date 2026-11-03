<?php
/**
 * =====================================================================
 * admin/receptionists.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * CRUD for receptionist (front-desk staff) accounts. Simpler than
 * doctors — no department/specialization/schedule fields.
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
    // ADD
    // -----------------------------------------------------------------
    if ($action === 'add') {
        $fullName = sanitize($_POST['full_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $gender = sanitize($_POST['gender'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        $errors = [];
        if ($fullName === '' || mb_strlen($fullName) < 2) $errors[] = 'Please enter the receptionist\'s full name.';
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
            $stmt = db_query(
                $conn,
                "INSERT INTO users (role, full_name, email, password, phone, gender, status) VALUES ('receptionist', ?, ?, ?, ?, ?, 'active')",
                'sssss',
                [$fullName, $email, $hash, $phone, $gender]
            );
            $userId = (int) mysqli_insert_id($conn);

            add_notification($conn, $userId, 'Welcome to HAMS', 'Your receptionist account has been created by the administrator.');
            log_activity($conn, current_user_id(), 'receptionist_added', "Added receptionist: $fullName ($email)");
            set_flash('success', 'Receptionist added successfully.');
        } else {
            set_flash('error', implode(' ', $errors));
        }
        redirect('admin/receptionists.php');
    }

    // -----------------------------------------------------------------
    // EDIT
    // -----------------------------------------------------------------
    if ($action === 'edit') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $fullName = sanitize($_POST['full_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $gender = sanitize($_POST['gender'] ?? '');
        $newPassword = (string) ($_POST['password'] ?? '');

        $errors = [];
        if ($userId <= 0) $errors[] = 'Invalid receptionist record.';
        if ($fullName === '' || mb_strlen($fullName) < 2) $errors[] = 'Please enter the receptionist\'s full name.';
        if (!is_valid_email($email)) $errors[] = 'Please enter a valid email address.';
        if ($newPassword !== '' && !is_strong_password($newPassword)) $errors[] = 'New password must be at least 8 characters with letters and numbers.';

        if (empty($errors)) {
            $stmt = db_query($conn, "SELECT id FROM users WHERE email = ? AND id != ?", 'si', [$email, $userId]);
            if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
                $errors[] = 'Another user already uses that email.';
            }
        }

        if (empty($errors)) {
            db_query($conn, "UPDATE users SET full_name=?, email=?, phone=?, gender=? WHERE id=?", 'ssssi', [$fullName, $email, $phone, $gender, $userId]);

            if ($newPassword !== '') {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                db_query($conn, "UPDATE users SET password=? WHERE id=?", 'si', [$hash, $userId]);
            }

            log_activity($conn, current_user_id(), 'receptionist_updated', "Updated receptionist #$userId");
            set_flash('success', 'Receptionist details updated.');
        } else {
            set_flash('error', implode(' ', $errors));
        }
        redirect('admin/receptionists.php');
    }

    // -----------------------------------------------------------------
    // TOGGLE STATUS
    // -----------------------------------------------------------------
    if ($action === 'toggle_status') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $newStatus = $_POST['new_status'] === 'active' ? 'active' : 'inactive';
        db_query($conn, "UPDATE users SET status=? WHERE id=?", 'si', [$newStatus, $userId]);
        log_activity($conn, current_user_id(), 'receptionist_status_changed', "Receptionist #$userId set to $newStatus");
        set_flash('success', 'Receptionist status updated.');
        redirect('admin/receptionists.php');
    }

    // -----------------------------------------------------------------
    // DELETE
    // -----------------------------------------------------------------
    if ($action === 'delete') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        db_query($conn, "DELETE FROM users WHERE id = ?", 'i', [$userId]);
        log_activity($conn, current_user_id(), 'receptionist_deleted', "Deleted receptionist user #$userId");
        set_flash('success', 'Receptionist removed from the system.');
        redirect('admin/receptionists.php');
    }
}

/* =====================================================================
   LIST + SEARCH + PAGINATION
   ===================================================================== */
$search = sanitize($_GET['search'] ?? '');

$where = "role = 'receptionist'";
$types = '';
$params = [];
if ($search !== '') {
    $where .= ' AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)';
    $like = '%' . $search . '%';
    $types = 'sss';
    $params = [$like, $like, $like];
}

$countSql = "SELECT COUNT(*) c FROM users WHERE $where";
$stmt = $types ? db_query($conn, $countSql, $types, $params) : db_query($conn, $countSql);
$totalRecords = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
$pagination = paginate($totalRecords);

$listSql = "SELECT id, full_name, email, phone, gender, profile_photo, status, created_at, last_login
            FROM users WHERE $where
            ORDER BY created_at DESC
            LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}";
$stmt = $types ? db_query($conn, $listSql, $types, $params) : db_query($conn, $listSql);
$receptionists = [];
if ($stmt) {
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $receptionists[] = $row;
    }
}

$pageTitle = 'Manage Receptionists';

$extraScripts = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-edit-receptionist').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('edit_user_id').value = btn.dataset.userId;
            document.getElementById('edit_full_name').value = btn.dataset.fullName;
            document.getElementById('edit_email').value = btn.dataset.email;
            document.getElementById('edit_phone').value = btn.dataset.phone;
            document.getElementById('edit_gender').value = btn.dataset.gender;
            new bootstrap.Modal(document.getElementById('editReceptionistModal')).show();
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
                <h2 class="h4 fw-bold mb-1">Receptionists</h2>
                <p class="text-muted mb-0"><?php echo $totalRecords; ?> front-desk account<?php echo $totalRecords === 1 ? '' : 's'; ?>.</p>
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addReceptionistModal">
                <i class="bi bi-person-plus me-1"></i>Add Receptionist
            </button>
        </div>

        <form method="GET" class="hams-card p-3 mb-4">
            <div class="row g-2 align-items-end">
                <div class="col-md-9">
                    <label class="form-label mb-1">Search</label>
                    <input type="text" class="form-control" name="search" value="<?php echo e($search); ?>" placeholder="Name, email or phone...">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search"></i> Search</button>
                    <a href="<?php echo BASE_URL; ?>admin/receptionists.php" class="btn btn-outline-secondary flex-fill"><i class="bi bi-x-lg"></i></a>
                </div>
            </div>
        </form>

        <div class="table-responsive-card mb-3">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Receptionist</th>
                            <th>Phone</th>
                            <th>Last Login</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($receptionists)): ?>
                            <tr><td colspan="5">
                                <div class="empty-state"><i class="bi bi-person-lines-fill"></i><p class="mb-0">No receptionists found.</p></div>
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($receptionists as $r): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?php echo UPLOADS_URL . e($r['profile_photo'] ?: 'default-avatar.png'); ?>"
                                             onerror="this.src='<?php echo ASSETS_URL; ?>images/default-avatar.png'" class="avatar-sm" alt="">
                                        <div>
                                            <div class="fw-semibold"><?php echo e($r['full_name']); ?></div>
                                            <div class="text-muted small"><?php echo e($r['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo e($r['phone'] ?: '—'); ?></td>
                                <td><?php echo $r['last_login'] ? time_ago($r['last_login']) : 'Never'; ?></td>
                                <td><span class="badge <?php echo status_badge_class($r['status']); ?>"><?php echo e(ucfirst($r['status'])); ?></span></td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <button type="button" class="icon-btn btn-edit-receptionist"
                                            data-user-id="<?php echo $r['id']; ?>"
                                            data-full-name="<?php echo e($r['full_name']); ?>"
                                            data-email="<?php echo e($r['email']); ?>"
                                            data-phone="<?php echo e($r['phone']); ?>"
                                            data-gender="<?php echo e($r['gender']); ?>"
                                            title="Edit"><i class="bi bi-pencil"></i></button>

                                        <form method="POST" class="d-inline">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="form_action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo $r['id']; ?>">
                                            <input type="hidden" name="new_status" value="<?php echo $r['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                            <button type="submit" class="icon-btn" title="<?php echo $r['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="bi <?php echo $r['status'] === 'active' ? 'bi-toggle-on text-success' : 'bi-toggle-off'; ?>"></i>
                                            </button>
                                        </form>

                                        <form method="POST" data-confirm-delete data-confirm-message="Delete <?php echo e($r['full_name']); ?>? This cannot be undone.">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="form_action" value="delete">
                                            <input type="hidden" name="user_id" value="<?php echo $r['id']; ?>">
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

        <!-- ===================== ADD RECEPTIONIST MODAL ===================== -->
        <div class="modal fade" id="addReceptionistModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" class="needs-validation" novalidate>
                        <?php csrf_field(); ?>
                        <input type="hidden" name="form_action" value="add">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold">Add Receptionist</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-12"><label class="form-label">Full Name</label><input type="text" class="form-control" name="full_name" required minlength="2"></div>
                                <div class="col-12"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required></div>
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
                                <div class="col-12"><label class="form-label">Initial Password</label><input type="password" class="form-control" name="password" required minlength="8"></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Add Receptionist</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ===================== EDIT RECEPTIONIST MODAL ===================== -->
        <div class="modal fade" id="editReceptionistModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" class="needs-validation" novalidate>
                        <?php csrf_field(); ?>
                        <input type="hidden" name="form_action" value="edit">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold">Edit Receptionist</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-12"><label class="form-label">Full Name</label><input type="text" class="form-control" name="full_name" id="edit_full_name" required minlength="2"></div>
                                <div class="col-12"><label class="form-label">Email</label><input type="email" class="form-control" name="email" id="edit_email" required></div>
                                <div class="col-md-6"><label class="form-label">Phone</label><input type="tel" class="form-control" name="phone" id="edit_phone"></div>
                                <div class="col-md-6">
                                    <label class="form-label">Gender</label>
                                    <select class="form-select" name="gender" id="edit_gender" required>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
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