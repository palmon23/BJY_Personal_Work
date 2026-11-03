<?php
/**
 * =====================================================================
 * patient/appointments.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * The patient's own appointments: upcoming and history in one filtered
 * list, with cancel and reschedule actions on active appointments and
 * a details view (including consultation notes once completed).
 * =====================================================================
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('patient');

$patientId = (int) current_user()['patient_id'];

/* =====================================================================
   POST HANDLER — cancel (scoped to this patient's own appointments only)
   ===================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['form_action'] ?? '';
    $appointmentId = (int) ($_POST['appointment_id'] ?? 0);

    $stmt = db_query(
        $conn,
        "SELECT a.*, du.id AS doctor_user_id, du.full_name AS doctor_name
         FROM appointments a JOIN doctors d ON d.id = a.doctor_id JOIN users du ON du.id = d.user_id
         WHERE a.id = ? AND a.patient_id = ?",
        'ii',
        [$appointmentId, $patientId]
    );
    $appt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$appt) {
        set_flash('error', 'Appointment not found.');
        redirect('patient/appointments.php');
    }

    if ($action === 'cancel' && in_array($appt['status'], ['pending', 'confirmed'], true)) {
        $reason = sanitize($_POST['cancelled_reason'] ?? '');
        db_query($conn, "UPDATE appointments SET status='cancelled', cancelled_reason=? WHERE id=?", 'si', [$reason, $appointmentId]);
        add_notification($conn, (int) $appt['doctor_user_id'], 'Appointment Cancelled', current_user()['full_name'] . ' cancelled their appointment on ' . format_date($appt['appointment_date']) . '.');
        log_activity($conn, current_user_id(), 'appointment_cancelled', "Patient cancelled appointment #$appointmentId");
        set_flash('success', 'Your appointment has been cancelled.');
    }
    redirect('patient/appointments.php');
}

/* =====================================================================
   FILTERS + LIST
   ===================================================================== */
$statusFilter = sanitize($_GET['status'] ?? '');

$where = ['a.patient_id = ?'];
$types = 'i';
$params = [$patientId];

if (in_array($statusFilter, ['pending', 'confirmed', 'completed', 'cancelled', 'rejected'], true)) {
    $where[] = 'a.status = ?';
    $types .= 's';
    $params[] = $statusFilter;
}
$whereSql = implode(' AND ', $where);

$baseFrom = "FROM appointments a JOIN doctors d ON d.id = a.doctor_id JOIN users du ON du.id = d.user_id JOIN departments dept ON dept.id = a.department_id";

$countSql = "SELECT COUNT(*) c $baseFrom WHERE $whereSql";
$stmt = db_query($conn, $countSql, $types, $params);
$totalRecords = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
$pagination = paginate($totalRecords);

$listSql = "SELECT a.*, du.full_name AS doctor_name, du.profile_photo AS doctor_photo, dept.name AS department_name
            $baseFrom
            WHERE $whereSql
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
            LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}";
$stmt = db_query($conn, $listSql, $types, $params);
$appointments = [];
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $appointments[] = $row;
}

$pageTitle = 'My Appointments';

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
            document.getElementById('view_doctor').textContent = 'Dr. ' + btn.dataset.doctor;
            document.getElementById('view_department').textContent = btn.dataset.department;
            document.getElementById('view_datetime').textContent = btn.dataset.datetime;
            document.getElementById('view_status').innerHTML = btn.dataset.statusBadge;
            document.getElementById('view_reason').textContent = btn.dataset.reason || 'No reason provided.';
            document.getElementById('view_notes').textContent = btn.dataset.notes || 'Not available yet.';
            document.getElementById('view_prescription').textContent = btn.dataset.prescription || 'Not available yet.';
            document.getElementById('view_cancelled_wrap').style.display = btn.dataset.cancelledReason ? '' : 'none';
            document.getElementById('view_cancelled_reason').textContent = btn.dataset.cancelledReason || '';
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

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="h4 fw-bold mb-1">My Appointments</h2>
                <p class="text-muted mb-0"><?php echo $totalRecords; ?> appointment<?php echo $totalRecords === 1 ? '' : 's'; ?> on record.</p>
            </div>
            <a href="<?php echo BASE_URL; ?>patient/book_appointment.php" class="btn btn-primary"><i class="bi bi-calendar-plus me-1"></i>Book New Appointment</a>
        </div>

        <!-- Status filter tabs -->
        <ul class="nav nav-pills mb-4 flex-wrap gap-1">
            <?php $tabs = ['' => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'rejected' => 'Rejected']; ?>
            <?php foreach ($tabs as $val => $label): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $statusFilter === $val ? 'active' : ''; ?>" href="?status=<?php echo $val; ?>"
                       style="<?php echo $statusFilter === $val ? 'background:var(--hams-primary); color:#fff;' : 'background:var(--hams-surface-alt); color:var(--hams-text);'; ?>">
                        <?php echo $label; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <!-- Appointments -->
        <div class="row g-3">
            <?php if (empty($appointments)): ?>
                <div class="col-12">
                    <div class="empty-state"><i class="bi bi-calendar-x"></i><p class="mb-2">No appointments found for this filter.</p>
                        <a href="<?php echo BASE_URL; ?>patient/book_appointment.php" class="btn btn-primary btn-sm">Book an Appointment</a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($appointments as $appt):
                    $dateTimeLabel = format_date($appt['appointment_date']) . ' · ' . format_time($appt['appointment_time']);
                    $statusBadgeHtml = '<span class="badge ' . status_badge_class($appt['status']) . '">' . ucfirst($appt['status']) . '</span>';
                ?>
                <div class="col-md-6">
                    <div class="hams-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <img src="<?php echo UPLOADS_URL . e($appt['doctor_photo'] ?: 'default-avatar.png'); ?>" onerror="this.src='<?php echo ASSETS_URL; ?>images/default-avatar.png'" class="avatar-md" alt="">
                                <div>
                                    <div class="fw-semibold">Dr. <?php echo e($appt['doctor_name']); ?></div>
                                    <div class="text-muted small"><?php echo e($appt['department_name']); ?></div>
                                </div>
                            </div>
                            <span class="badge <?php echo status_badge_class($appt['status']); ?>"><?php echo e(ucfirst($appt['status'])); ?></span>
                        </div>
                        <div class="d-flex align-items-center gap-2 text-muted small mb-3">
                            <i class="bi bi-calendar3"></i><?php echo $dateTimeLabel; ?>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm flex-fill btn-view-appt"
                                data-doctor="<?php echo e($appt['doctor_name']); ?>"
                                data-department="<?php echo e($appt['department_name']); ?>"
                                data-datetime="<?php echo e($dateTimeLabel); ?>"
                                data-status-badge="<?php echo e($statusBadgeHtml); ?>"
                                data-reason="<?php echo e($appt['reason']); ?>"
                                data-notes="<?php echo e($appt['consultation_notes']); ?>"
                                data-prescription="<?php echo e($appt['prescription']); ?>"
                                data-cancelled-reason="<?php echo e($appt['cancelled_reason']); ?>">
                                <i class="bi bi-eye me-1"></i>Details
                            </button>
                            <?php if (in_array($appt['status'], ['pending', 'confirmed'], true)): ?>
                                <a href="<?php echo BASE_URL; ?>patient/book_appointment.php?department_id=<?php echo $appt['department_id']; ?>&doctor_id=<?php echo $appt['doctor_id']; ?>&reschedule_id=<?php echo $appt['id']; ?>" class="btn btn-outline-primary btn-sm flex-fill">
                                    <i class="bi bi-arrow-repeat me-1"></i>Reschedule
                                </a>
                                <button type="button" class="btn btn-outline-danger btn-sm flex-fill btn-cancel-appt" data-id="<?php echo $appt['id']; ?>">
                                    <i class="bi bi-x-lg me-1"></i>Cancel
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="mt-4">
            <?php render_pagination($pagination['currentPage'], $pagination['totalPages']); ?>
        </div>

        <!-- ===================== VIEW MODAL ===================== -->
        <div class="modal fade" id="viewApptModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Appointment Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <dl class="row small mb-3">
                            <dt class="col-3 text-muted">Doctor</dt><dd class="col-9" id="view_doctor"></dd>
                            <dt class="col-3 text-muted">Department</dt><dd class="col-9" id="view_department"></dd>
                            <dt class="col-3 text-muted">Date &amp; Time</dt><dd class="col-9" id="view_datetime"></dd>
                            <dt class="col-3 text-muted">Status</dt><dd class="col-9" id="view_status"></dd>
                            <dt class="col-3 text-muted">Reason</dt><dd class="col-9" id="view_reason"></dd>
                        </dl>
                        <div id="view_cancelled_wrap" class="alert alert-danger py-2 small">
                            <strong>Cancellation reason:</strong> <span id="view_cancelled_reason"></span>
                        </div>
                        <div class="mb-3">
                            <h6 class="fw-bold small text-muted text-uppercase">Consultation Notes</h6>
                            <p id="view_notes" class="mb-0"></p>
                        </div>
                        <div>
                            <h6 class="fw-bold small text-muted text-uppercase">Prescription</h6>
                            <p id="view_prescription" class="mb-0"></p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================== CANCEL MODAL ===================== -->
        <div class="modal fade" id="cancelApptModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="form_action" value="cancel">
                        <input type="hidden" name="appointment_id" id="cancel_appointment_id">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold">Cancel Appointment</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label">Reason <span class="text-muted small">(optional)</span></label>
                            <textarea class="form-control" name="cancelled_reason" id="cancel_reason" rows="3" placeholder="Let us know why you're cancelling..."></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Back</button>
                            <button type="submit" class="btn btn-danger"><i class="bi bi-x-lg me-1"></i>Cancel Appointment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>