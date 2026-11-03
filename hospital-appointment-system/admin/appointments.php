<?php
/**
 * =====================================================================
 * admin/appointments.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * System-wide appointment management: filter by status/date/department
 * /doctor/patient, and confirm, reject, cancel, or mark appointments
 * completed with full audit logging.
 * =====================================================================
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

/* =====================================================================
   POST HANDLERS — status transitions
   ===================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['form_action'] ?? '';
    $appointmentId = (int) ($_POST['appointment_id'] ?? 0);

    if ($appointmentId > 0) {
        // Fetch the appointment + owning patient/doctor user IDs so we can notify them.
        $stmt = db_query(
            $conn,
            "SELECT a.*, pu.id AS patient_user_id, du.id AS doctor_user_id
             FROM appointments a
             JOIN patients p ON p.id = a.patient_id JOIN users pu ON pu.id = p.user_id
             JOIN doctors d ON d.id = a.doctor_id JOIN users du ON du.id = d.user_id
             WHERE a.id = ?",
            'i',
            [$appointmentId]
        );
        $appt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$appt) {
            set_flash('error', 'Appointment not found.');
            redirect('admin/appointments.php');
        }

        if ($action === 'confirm') {
            db_query($conn, "UPDATE appointments SET status='confirmed' WHERE id=?", 'i', [$appointmentId]);
            add_notification($conn, (int) $appt['patient_user_id'], 'Appointment Confirmed', 'Your appointment on ' . format_date($appt['appointment_date']) . ' at ' . format_time($appt['appointment_time']) . ' has been confirmed.');
            add_notification($conn, (int) $appt['doctor_user_id'], 'Appointment Confirmed', 'An appointment on ' . format_date($appt['appointment_date']) . ' has been confirmed by the front desk.');
            log_activity($conn, current_user_id(), 'appointment_confirmed', "Confirmed appointment #$appointmentId");
            set_flash('success', 'Appointment confirmed.');
        } elseif ($action === 'reject') {
            db_query($conn, "UPDATE appointments SET status='rejected' WHERE id=?", 'i', [$appointmentId]);
            add_notification($conn, (int) $appt['patient_user_id'], 'Appointment Rejected', 'Unfortunately your appointment request on ' . format_date($appt['appointment_date']) . ' could not be accepted.');
            log_activity($conn, current_user_id(), 'appointment_rejected', "Rejected appointment #$appointmentId");
            set_flash('success', 'Appointment rejected.');
        } elseif ($action === 'complete') {
            db_query($conn, "UPDATE appointments SET status='completed' WHERE id=?", 'i', [$appointmentId]);
            add_notification($conn, (int) $appt['patient_user_id'], 'Appointment Completed', 'Your visit on ' . format_date($appt['appointment_date']) . ' has been marked as completed.');
            log_activity($conn, current_user_id(), 'appointment_completed', "Marked appointment #$appointmentId completed");
            set_flash('success', 'Appointment marked as completed.');
        } elseif ($action === 'cancel') {
            $reason = sanitize($_POST['cancelled_reason'] ?? '');
            db_query($conn, "UPDATE appointments SET status='cancelled', cancelled_reason=? WHERE id=?", 'si', [$reason, $appointmentId]);
            add_notification($conn, (int) $appt['patient_user_id'], 'Appointment Cancelled', 'Your appointment on ' . format_date($appt['appointment_date']) . ' was cancelled. ' . ($reason ? "Reason: $reason" : ''));
            add_notification($conn, (int) $appt['doctor_user_id'], 'Appointment Cancelled', 'An appointment on ' . format_date($appt['appointment_date']) . ' was cancelled.');
            log_activity($conn, current_user_id(), 'appointment_cancelled', "Cancelled appointment #$appointmentId. Reason: $reason");
            set_flash('success', 'Appointment cancelled.');
        }
    }
    redirect('admin/appointments.php' . (!empty($_POST['redirect_qs']) ? '?' . $_POST['redirect_qs'] : ''));
}

/* =====================================================================
   FILTERS + PAGINATION
   ===================================================================== */
$search = sanitize($_GET['search'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$departmentFilter = (int) ($_GET['department'] ?? 0);
$doctorFilter = (int) ($_GET['doctor'] ?? 0);
$dateFrom = sanitize($_GET['date_from'] ?? '');
$dateTo = sanitize($_GET['date_to'] ?? '');

$where = ['1=1'];
$types = '';
$params = [];

if ($search !== '') {
    $where[] = '(pu.full_name LIKE ? OR du.full_name LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'ss';
    $params = array_merge($params, [$like, $like]);
}
if (in_array($statusFilter, ['pending', 'confirmed', 'completed', 'cancelled', 'rejected'], true)) {
    $where[] = 'a.status = ?';
    $types .= 's';
    $params[] = $statusFilter;
}
if ($departmentFilter > 0) {
    $where[] = 'a.department_id = ?';
    $types .= 'i';
    $params[] = $departmentFilter;
}
if ($doctorFilter > 0) {
    $where[] = 'a.doctor_id = ?';
    $types .= 'i';
    $params[] = $doctorFilter;
}
if ($dateFrom !== '') {
    $where[] = 'a.appointment_date >= ?';
    $types .= 's';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = 'a.appointment_date <= ?';
    $types .= 's';
    $params[] = $dateTo;
}
$whereSql = implode(' AND ', $where);

$baseFrom = "FROM appointments a
             JOIN patients p ON p.id = a.patient_id JOIN users pu ON pu.id = p.user_id
             JOIN doctors d ON d.id = a.doctor_id JOIN users du ON du.id = d.user_id
             JOIN departments dept ON dept.id = a.department_id";

$countSql = "SELECT COUNT(*) c $baseFrom WHERE $whereSql";
$stmt = $types ? db_query($conn, $countSql, $types, $params) : db_query($conn, $countSql);
$totalRecords = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
$pagination = paginate($totalRecords);

$listSql = "SELECT a.*, pu.full_name AS patient_name, pu.phone AS patient_phone,
                   du.full_name AS doctor_name, dept.name AS department_name
            $baseFrom
            WHERE $whereSql
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
            LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}";
$stmt = $types ? db_query($conn, $listSql, $types, $params) : db_query($conn, $listSql);
$appointments = [];
if ($stmt) {
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $appointments[] = $row;
    }
}

// Dropdown data
$departments = [];
$res = mysqli_query($conn, "SELECT id, name FROM departments WHERE status = 'active' ORDER BY name");
if ($res) while ($row = mysqli_fetch_assoc($res)) $departments[] = $row;

$doctorsList = [];
$res = mysqli_query($conn, "SELECT d.id, u.full_name FROM doctors d JOIN users u ON u.id = d.user_id WHERE d.status = 'active' ORDER BY u.full_name");
if ($res) while ($row = mysqli_fetch_assoc($res)) $doctorsList[] = $row;

$redirectQs = http_build_query($_GET);

$pageTitle = 'Manage Appointments';

$extraScripts = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-cancel-appt').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('cancel_appointment_id').value = btn.dataset.id;
            document.getElementById('cancel_reason').value = '';
            new bootstrap.Modal(document.getElementById('cancelApptModal')).show();
        });
    });
    document.querySelectorAll('.btn-view-appt').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('view_patient').textContent = btn.dataset.patient;
            document.getElementById('view_doctor').textContent = 'Dr. ' + btn.dataset.doctor;
            document.getElementById('view_department').textContent = btn.dataset.department;
            document.getElementById('view_datetime').textContent = btn.dataset.datetime;
            document.getElementById('view_phone').textContent = btn.dataset.phone || '—';
            document.getElementById('view_reason').textContent = btn.dataset.reason || 'No reason provided.';
            document.getElementById('view_notes').textContent = btn.dataset.notes || 'No consultation notes yet.';
            document.getElementById('view_status').innerHTML = btn.dataset.statusBadge;
            new bootstrap.Modal(document.getElementById('viewApptModal')).show();
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

        <div class="mb-4">
            <h2 class="h4 fw-bold mb-1">Appointments</h2>
            <p class="text-muted mb-0"><?php echo $totalRecords; ?> appointment<?php echo $totalRecords === 1 ? '' : 's'; ?> match your filters.</p>
        </div>

        <!-- Filters -->
        <form method="GET" class="hams-card p-3 mb-4">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label mb-1">Search</label>
                    <input type="text" class="form-control" name="search" value="<?php echo e($search); ?>" placeholder="Patient or doctor name...">
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All</option>
                        <?php foreach (['pending', 'confirmed', 'completed', 'cancelled', 'rejected'] as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo $statusFilter === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Department</label>
                    <select class="form-select" name="department">
                        <option value="0">All</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" <?php echo $departmentFilter === (int)$dept['id'] ? 'selected' : ''; ?>><?php echo e($dept['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Doctor</label>
                    <select class="form-select" name="doctor">
                        <option value="0">All</option>
                        <?php foreach ($doctorsList as $doc): ?>
                            <option value="<?php echo $doc['id']; ?>" <?php echo $doctorFilter === (int)$doc['id'] ? 'selected' : ''; ?>>Dr. <?php echo e($doc['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">From</label>
                    <input type="date" class="form-control" name="date_from" value="<?php echo e($dateFrom); ?>">
                </div>
                <div class="col-md-1 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search"></i></button>
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-md-2">
                    <label class="form-label mb-1">To</label>
                    <input type="date" class="form-control" name="date_to" value="<?php echo e($dateTo); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="<?php echo BASE_URL; ?>admin/appointments.php" class="btn btn-outline-secondary w-100"><i class="bi bi-x-lg me-1"></i>Reset</a>
                </div>
            </div>
        </form>

        <!-- Appointments table -->
        <div class="table-responsive-card mb-3">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Department</th>
                            <th>Date &amp; Time</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($appointments)): ?>
                            <tr><td colspan="6">
                                <div class="empty-state"><i class="bi bi-calendar-x"></i><p class="mb-0">No appointments match your filters.</p></div>
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($appointments as $appt):
                                $dateTimeLabel = format_date($appt['appointment_date']) . ' · ' . format_time($appt['appointment_time']);
                                $statusBadgeHtml = '<span class="badge ' . status_badge_class($appt['status']) . '">' . ucfirst($appt['status']) . '</span>';
                            ?>
                            <tr>
                                <td class="fw-semibold"><?php echo e($appt['patient_name']); ?></td>
                                <td>Dr. <?php echo e($appt['doctor_name']); ?></td>
                                <td><?php echo e($appt['department_name']); ?></td>
                                <td><?php echo $dateTimeLabel; ?></td>
                                <td><span class="badge <?php echo status_badge_class($appt['status']); ?>"><?php echo e(ucfirst($appt['status'])); ?></span></td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <button type="button" class="icon-btn btn-view-appt" title="View details"
                                            data-patient="<?php echo e($appt['patient_name']); ?>"
                                            data-doctor="<?php echo e($appt['doctor_name']); ?>"
                                            data-department="<?php echo e($appt['department_name']); ?>"
                                            data-datetime="<?php echo e($dateTimeLabel); ?>"
                                            data-phone="<?php echo e($appt['patient_phone']); ?>"
                                            data-reason="<?php echo e($appt['reason']); ?>"
                                            data-notes="<?php echo e($appt['consultation_notes']); ?>"
                                            data-status-badge="<?php echo e($statusBadgeHtml); ?>">
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        <?php if ($appt['status'] === 'pending'): ?>
                                            <form method="POST" class="d-inline">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="form_action" value="confirm">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
                                                <input type="hidden" name="redirect_qs" value="<?php echo e($redirectQs); ?>">
                                                <button type="submit" class="icon-btn text-success" title="Confirm"><i class="bi bi-check-lg"></i></button>
                                            </form>
                                            <form method="POST" class="d-inline">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="form_action" value="reject">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
                                                <input type="hidden" name="redirect_qs" value="<?php echo e($redirectQs); ?>">
                                                <button type="submit" class="icon-btn text-danger" title="Reject"><i class="bi bi-x-lg"></i></button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($appt['status'] === 'confirmed'): ?>
                                            <form method="POST" class="d-inline">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="form_action" value="complete">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
                                                <input type="hidden" name="redirect_qs" value="<?php echo e($redirectQs); ?>">
                                                <button type="submit" class="icon-btn text-success" title="Mark completed"><i class="bi bi-check2-all"></i></button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if (in_array($appt['status'], ['pending', 'confirmed'], true)): ?>
                                            <button type="button" class="icon-btn text-danger btn-cancel-appt" data-id="<?php echo $appt['id']; ?>" title="Cancel"><i class="bi bi-calendar-x"></i></button>
                                        <?php endif; ?>
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

        <!-- ===================== VIEW APPOINTMENT MODAL ===================== -->
        <div class="modal fade" id="viewApptModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Appointment Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <dl class="row mb-0 small">
                            <dt class="col-4 text-muted">Patient</dt><dd class="col-8" id="view_patient"></dd>
                            <dt class="col-4 text-muted">Doctor</dt><dd class="col-8" id="view_doctor"></dd>
                            <dt class="col-4 text-muted">Department</dt><dd class="col-8" id="view_department"></dd>
                            <dt class="col-4 text-muted">Date &amp; Time</dt><dd class="col-8" id="view_datetime"></dd>
                            <dt class="col-4 text-muted">Patient Phone</dt><dd class="col-8" id="view_phone"></dd>
                            <dt class="col-4 text-muted">Status</dt><dd class="col-8" id="view_status"></dd>
                            <dt class="col-4 text-muted">Reason</dt><dd class="col-8" id="view_reason"></dd>
                            <dt class="col-4 text-muted">Notes</dt><dd class="col-8" id="view_notes"></dd>
                        </dl>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================== CANCEL APPOINTMENT MODAL ===================== -->
        <div class="modal fade" id="cancelApptModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="form_action" value="cancel">
                        <input type="hidden" name="appointment_id" id="cancel_appointment_id">
                        <input type="hidden" name="redirect_qs" value="<?php echo e($redirectQs); ?>">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold">Cancel Appointment</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label">Reason for cancellation</label>
                            <textarea class="form-control" name="cancelled_reason" id="cancel_reason" rows="3" placeholder="Let the patient know why (optional)..."></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Back</button>
                            <button type="submit" class="btn btn-danger"><i class="bi bi-calendar-x me-1"></i>Cancel Appointment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>