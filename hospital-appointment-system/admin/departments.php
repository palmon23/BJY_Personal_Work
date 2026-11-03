<?php
/**
 * =====================================================================
 * admin/departments.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * CRUD for hospital departments. Departments feed the doctor form's
 * dropdown, the public landing page, and the appointment booking flow.
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
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $icon = sanitize($_POST['icon'] ?? '') ?: 'bi-hospital';

        $errors = [];
        if ($name === '' || mb_strlen($name) < 2) {
            $errors[] = 'Please enter a department name.';
        } else {
            $stmt = db_query($conn, "SELECT id FROM departments WHERE name = ?", 's', [$name]);
            if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
                $errors[] = 'A department with that name already exists.';
            }
        }

        if (empty($errors)) {
            db_query($conn, "INSERT INTO departments (name, description, icon) VALUES (?, ?, ?)", 'sss', [$name, $description, $icon]);
            log_activity($conn, current_user_id(), 'department_added', "Added department: $name");
            set_flash('success', 'Department added successfully.');
        } else {
            set_flash('error', implode(' ', $errors));
        }
        redirect('admin/departments.php');
    }

    // -----------------------------------------------------------------
    // EDIT
    // -----------------------------------------------------------------
    if ($action === 'edit') {
        $id = (int) ($_POST['department_id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $icon = sanitize($_POST['icon'] ?? '') ?: 'bi-hospital';

        $errors = [];
        if ($id <= 0) $errors[] = 'Invalid department.';
        if ($name === '' || mb_strlen($name) < 2) $errors[] = 'Please enter a department name.';

        if (empty($errors)) {
            $stmt = db_query($conn, "SELECT id FROM departments WHERE name = ? AND id != ?", 'si', [$name, $id]);
            if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
                $errors[] = 'Another department already uses that name.';
            }
        }

        if (empty($errors)) {
            db_query($conn, "UPDATE departments SET name=?, description=?, icon=? WHERE id=?", 'sssi', [$name, $description, $icon, $id]);
            log_activity($conn, current_user_id(), 'department_updated', "Updated department #$id");
            set_flash('success', 'Department updated.');
        } else {
            set_flash('error', implode(' ', $errors));
        }
        redirect('admin/departments.php');
    }

    // -----------------------------------------------------------------
    // TOGGLE STATUS
    // -----------------------------------------------------------------
    if ($action === 'toggle_status') {
        $id = (int) ($_POST['department_id'] ?? 0);
        $newStatus = $_POST['new_status'] === 'active' ? 'active' : 'inactive';
        db_query($conn, "UPDATE departments SET status=? WHERE id=?", 'si', [$newStatus, $id]);
        log_activity($conn, current_user_id(), 'department_status_changed', "Department #$id set to $newStatus");
        set_flash('success', 'Department status updated.');
        redirect('admin/departments.php');
    }

    // -----------------------------------------------------------------
    // DELETE
    // -----------------------------------------------------------------
    if ($action === 'delete') {
        $id = (int) ($_POST['department_id'] ?? 0);

        $stmt = db_query($conn, "SELECT COUNT(*) c FROM doctors WHERE department_id = ?", 'i', [$id]);
        $doctorCount = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];

        $stmt = db_query($conn, "SELECT COUNT(*) c FROM appointments WHERE department_id = ?", 'i', [$id]);
        $apptCount = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];

        if ($doctorCount > 0 || $apptCount > 0) {
            set_flash('error', "Cannot delete this department — it has $doctorCount doctor(s) and $apptCount appointment(s) linked to it. Deactivate it instead.");
        } else {
            db_query($conn, "DELETE FROM departments WHERE id = ?", 'i', [$id]);
            log_activity($conn, current_user_id(), 'department_deleted', "Deleted department #$id");
            set_flash('success', 'Department deleted.');
        }
        redirect('admin/departments.php');
    }
}

/* =====================================================================
   LIST + SEARCH
   ===================================================================== */
$search = sanitize($_GET['search'] ?? '');

$where = '1=1';
$types = '';
$params = [];
if ($search !== '') {
    $where = 'name LIKE ? OR description LIKE ?';
    $like = '%' . $search . '%';
    $types = 'ss';
    $params = [$like, $like];
}

$countSql = "SELECT COUNT(*) c FROM departments WHERE $where";
$stmt = $types ? db_query($conn, $countSql, $types, $params) : db_query($conn, $countSql);
$totalRecords = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
$pagination = paginate($totalRecords);

$listSql = "SELECT d.*, 
                   (SELECT COUNT(*) FROM doctors doc WHERE doc.department_id = d.id) AS doctor_count,
                   (SELECT COUNT(*) FROM appointments a WHERE a.department_id = d.id) AS appointment_count
            FROM departments d
            WHERE $where
            ORDER BY d.name ASC
            LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}";
$stmt = $types ? db_query($conn, $listSql, $types, $params) : db_query($conn, $listSql);
$departments = [];
if ($stmt) {
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $departments[] = $row;
    }
}

// A curated list of sensible Bootstrap Icons for the icon picker
$iconChoices = [
    'bi-hospital', 'bi-heart-pulse', 'bi-activity', 'bi-person-wheelchair', 'bi-emoji-smile',
    'bi-bandaid', 'bi-eye', 'bi-lungs', 'bi-thermometer-half', 'bi-capsule', 'bi-clipboard2-pulse',
    'bi-droplet-half', 'bi-brightness-high', 'bi-gender-ambiguous', 'bi-shield-plus',
];

$pageTitle = 'Departments';

$extraScripts = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-edit-department').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('edit_department_id').value = btn.dataset.id;
            document.getElementById('edit_name').value = btn.dataset.name;
            document.getElementById('edit_description').value = btn.dataset.description;
            var iconSelect = document.getElementById('edit_icon');
            if (iconSelect) iconSelect.value = btn.dataset.icon;
            new bootstrap.Modal(document.getElementById('editDepartmentModal')).show();
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
                <h2 class="h4 fw-bold mb-1">Departments</h2>
                <p class="text-muted mb-0"><?php echo $totalRecords; ?> department<?php echo $totalRecords === 1 ? '' : 's'; ?> configured.</p>
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                <i class="bi bi-plus-lg me-1"></i>Add Department
            </button>
        </div>

        <form method="GET" class="hams-card p-3 mb-4">
            <div class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="form-label mb-1">Search</label>
                    <input type="text" class="form-control" name="search" value="<?php echo e($search); ?>" placeholder="Search by name or description...">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search"></i> Search</button>
                    <a href="<?php echo BASE_URL; ?>admin/departments.php" class="btn btn-outline-secondary flex-fill"><i class="bi bi-x-lg"></i></a>
                </div>
            </div>
        </form>

        <div class="row g-3">
            <?php if (empty($departments)): ?>
                <div class="col-12">
                    <div class="empty-state"><i class="bi bi-diagram-3"></i><p class="mb-0">No departments found. Add your first one to get started.</p></div>
                </div>
            <?php else: ?>
                <?php foreach ($departments as $dept): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="hams-card p-4 h-100 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="stat-icon icon-primary"><i class="bi <?php echo e($dept['icon']); ?>"></i></div>
                            <span class="badge <?php echo status_badge_class($dept['status']); ?>"><?php echo e(ucfirst($dept['status'])); ?></span>
                        </div>
                        <h5 class="fw-bold mb-1"><?php echo e($dept['name']); ?></h5>
                        <p class="text-muted small flex-grow-1"><?php echo e($dept['description'] ?: 'No description provided.'); ?></p>
                        <div class="d-flex gap-3 small text-muted mb-3">
                            <span><i class="bi bi-person-badge me-1"></i><?php echo (int) $dept['doctor_count']; ?> doctors</span>
                            <span><i class="bi bi-calendar2-check me-1"></i><?php echo (int) $dept['appointment_count']; ?> appointments</span>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-primary btn-sm flex-fill btn-edit-department"
                                data-id="<?php echo $dept['id']; ?>"
                                data-name="<?php echo e($dept['name']); ?>"
                                data-description="<?php echo e($dept['description']); ?>"
                                data-icon="<?php echo e($dept['icon']); ?>">
                                <i class="bi bi-pencil me-1"></i>Edit
                            </button>
                            <form method="POST" class="flex-fill">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="form_action" value="toggle_status">
                                <input type="hidden" name="department_id" value="<?php echo $dept['id']; ?>">
                                <input type="hidden" name="new_status" value="<?php echo $dept['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                <button type="submit" class="btn btn-outline-secondary btn-sm w-100">
                                    <i class="bi <?php echo $dept['status'] === 'active' ? 'bi-eye-slash' : 'bi-eye'; ?> me-1"></i><?php echo $dept['status'] === 'active' ? 'Hide' : 'Show'; ?>
                                </button>
                            </form>
                            <form method="POST" data-confirm-delete data-confirm-message="Delete the <?php echo e($dept['name']); ?> department?">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="form_action" value="delete">
                                <input type="hidden" name="department_id" value="<?php echo $dept['id']; ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="mt-4">
            <?php render_pagination($pagination['currentPage'], $pagination['totalPages']); ?>
        </div>

        <!-- ===================== ADD DEPARTMENT MODAL ===================== -->
        <div class="modal fade" id="addDepartmentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" class="needs-validation" novalidate>
                        <?php csrf_field(); ?>
                        <input type="hidden" name="form_action" value="add">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold">Add Department</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Department Name</label>
                                <input type="text" class="form-control" name="name" required minlength="2" placeholder="e.g. Cardiology">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3" placeholder="Brief description of this department..."></textarea>
                            </div>
                            <div class="mb-1">
                                <label class="form-label">Icon</label>
                                <select class="form-select" name="icon">
                                    <?php foreach ($iconChoices as $ic): ?>
                                        <option value="<?php echo e($ic); ?>"><?php echo e($ic); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Bootstrap Icons name — shown on department cards and the public site.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Add Department</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ===================== EDIT DEPARTMENT MODAL ===================== -->
        <div class="modal fade" id="editDepartmentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" class="needs-validation" novalidate>
                        <?php csrf_field(); ?>
                        <input type="hidden" name="form_action" value="edit">
                        <input type="hidden" name="department_id" id="edit_department_id">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold">Edit Department</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Department Name</label>
                                <input type="text" class="form-control" name="name" id="edit_name" required minlength="2">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                            </div>
                            <div class="mb-1">
                                <label class="form-label">Icon</label>
                                <select class="form-select" name="icon" id="edit_icon">
                                    <?php foreach ($iconChoices as $ic): ?>
                                        <option value="<?php echo e($ic); ?>"><?php echo e($ic); ?></option>
                                    <?php endforeach; ?>
                                </select>
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