<?php
/**
 * =====================================================================
 * receptionist/patients.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * Front-desk patient search: quickly look someone up by name, email,
 * or phone, see their basic info, and jump straight into booking an
 * appointment for them. Full CRUD stays in admin/patients.php — this
 * page is intentionally read-only plus one quick action.
 * =====================================================================
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('receptionist');

$search = sanitize($_GET['search'] ?? '');

$where = '1=1';
$types = '';
$params = [];
if ($search !== '') {
    $where = '(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $like = '%' . $search . '%';
    $types = 'sss';
    $params = [$like, $like, $like];
}

$countSql = "SELECT COUNT(*) c FROM patients p JOIN users u ON u.id = p.user_id WHERE $where";
$stmt = $types ? db_query($conn, $countSql, $types, $params) : db_query($conn, $countSql);
$totalRecords = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
$pagination = paginate($totalRecords);

$listSql = "SELECT p.id AS patient_id, p.blood_group, p.emergency_contact_name, p.emergency_contact_phone,
                   u.full_name, u.email, u.phone, u.gender, u.date_of_birth, u.address, u.profile_photo,
                   (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.id) AS appointment_count,
                   (SELECT MAX(a.appointment_date) FROM appointments a WHERE a.patient_id = p.id) AS last_visit
            FROM patients p JOIN users u ON u.id = p.user_id
            WHERE $where
            ORDER BY u.full_name ASC
            LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}";
$stmt = $types ? db_query($conn, $listSql, $types, $params) : db_query($conn, $listSql);
$patients = [];
if ($stmt) {
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $patients[] = $row;
    }
}

$pageTitle = 'Search Patients';

$extraScripts = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-view-patient').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('vp_name').textContent = btn.dataset.name;
            document.getElementById('vp_email').textContent = btn.dataset.email;
            document.getElementById('vp_phone').textContent = btn.dataset.phone || '—';
            document.getElementById('vp_gender').textContent = btn.dataset.gender || '—';
            document.getElementById('vp_dob').textContent = btn.dataset.dob || '—';
            document.getElementById('vp_address').textContent = btn.dataset.address || '—';
            document.getElementById('vp_blood').textContent = btn.dataset.blood || '—';
            document.getElementById('vp_emergency_name').textContent = btn.dataset.emergencyName || '—';
            document.getElementById('vp_emergency_phone').textContent = btn.dataset.emergencyPhone || '—';
            document.getElementById('vp_book_link').href = btn.dataset.bookUrl;
            new bootstrap.Modal(document.getElementById('viewPatientModal')).show();
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
                <h2 class="h4 fw-bold mb-1">Search Patients</h2>
                <p class="text-muted mb-0"><?php echo $totalRecords; ?> patient<?php echo $totalRecords === 1 ? '' : 's'; ?> registered.</p>
            </div>
            <a href="<?php echo BASE_URL; ?>receptionist/register_patient.php" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Register New Patient</a>
        </div>

        <form method="GET" class="hams-card p-3 mb-4">
            <div class="row g-2 align-items-end">
                <div class="col-md-9">
                    <label class="form-label mb-1">Search</label>
                    <input type="text" class="form-control" name="search" value="<?php echo e($search); ?>" placeholder="Name, email or phone..." autofocus>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search"></i> Search</button>
                    <a href="<?php echo BASE_URL; ?>receptionist/patients.php" class="btn btn-outline-secondary flex-fill"><i class="bi bi-x-lg"></i></a>
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
                            <th>Visits</th>
                            <th>Last Visit</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($patients)): ?>
                            <tr><td colspan="5">
                                <div class="empty-state"><i class="bi bi-people"></i><p class="mb-1">No patients found.</p>
                                    <?php if ($search !== ''): ?><a href="<?php echo BASE_URL; ?>receptionist/register_patient.php" class="small">Register "<?php echo e($search); ?>" as a new patient <i class="bi bi-arrow-right"></i></a><?php endif; ?>
                                </div>
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($patients as $pat):
                                $bookUrl = BASE_URL . 'receptionist/book_appointment.php?patient_id=' . $pat['patient_id'];
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?php echo UPLOADS_URL . e($pat['profile_photo'] ?: 'default-avatar.png'); ?>" onerror="this.src='<?php echo ASSETS_URL; ?>images/default-avatar.png'" class="avatar-sm" alt="">
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
                                <td><span class="badge bg-primary-soft text-primary"><?php echo (int) $pat['appointment_count']; ?></span></td>
                                <td class="text-muted small"><?php echo $pat['last_visit'] ? format_date($pat['last_visit']) : 'Never'; ?></td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <button type="button" class="icon-btn btn-view-patient" title="View details"
                                            data-name="<?php echo e($pat['full_name']); ?>"
                                            data-email="<?php echo e($pat['email']); ?>"
                                            data-phone="<?php echo e($pat['phone']); ?>"
                                            data-gender="<?php echo e(ucfirst($pat['gender'])); ?>"
                                            data-dob="<?php echo e(format_date($pat['date_of_birth'])); ?>"
                                            data-address="<?php echo e($pat['address']); ?>"
                                            data-blood="<?php echo e($pat['blood_group']); ?>"
                                            data-emergency-name="<?php echo e($pat['emergency_contact_name']); ?>"
                                            data-emergency-phone="<?php echo e($pat['emergency_contact_phone']); ?>"
                                            data-book-url="<?php echo e($bookUrl); ?>">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <a href="<?php echo e($bookUrl); ?>" class="icon-btn text-primary" title="Book appointment"><i class="bi bi-calendar-plus"></i></a>
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

        <!-- ===================== VIEW PATIENT MODAL ===================== -->
        <div class="modal fade" id="viewPatientModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold"><span id="vp_name"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <dl class="row small mb-0">
                            <dt class="col-4 text-muted">Email</dt><dd class="col-8" id="vp_email"></dd>
                            <dt class="col-4 text-muted">Phone</dt><dd class="col-8" id="vp_phone"></dd>
                            <dt class="col-4 text-muted">Gender</dt><dd class="col-8" id="vp_gender"></dd>
                            <dt class="col-4 text-muted">Date of Birth</dt><dd class="col-8" id="vp_dob"></dd>
                            <dt class="col-4 text-muted">Address</dt><dd class="col-8" id="vp_address"></dd>
                            <dt class="col-4 text-muted">Blood Group</dt><dd class="col-8" id="vp_blood"></dd>
                            <dt class="col-4 text-muted">Emergency Contact</dt><dd class="col-8" id="vp_emergency_name"></dd>
                            <dt class="col-4 text-muted">Emergency Phone</dt><dd class="col-8" id="vp_emergency_phone"></dd>
                        </dl>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        <a href="#" id="vp_book_link" class="btn btn-primary"><i class="bi bi-calendar-plus me-1"></i>Book Appointment</a>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>