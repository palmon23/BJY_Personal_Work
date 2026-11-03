<?php
/**
 * =====================================================================
 * doctor/appointments.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * The doctor's own appointment queue: accept/reject pending requests,
 * and mark confirmed visits complete with consultation notes.
 * =====================================================================
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('doctor');

$doctorId = (int) current_user()['doctor_id'];

/* =====================================================================
   POST HANDLERS
   ===================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['form_action'] ?? '';
    $appointmentId = (int) ($_POST['appointment_id'] ?? 0);

    // Always scope by doctor_id too, so a doctor can never act on another
    // doctor's appointment even with a manipulated form submission.
    $stmt = db_query(
        $conn,
        "SELECT a.*, pu.id AS patient_user_id FROM appointments a
         JOIN patients p ON p.id = a.patient_id JOIN users pu ON pu.id = p.user_id
         WHERE a.id = ? AND a.doctor_id = ?",
        'ii',
        [$appointmentId, $doctorId]
    );
    $appt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$appt) {
        set_flash('error', 'Appointment not found.');
        redirect('doctor/appointments.php');
    }

    if ($action === 'accept') {
        db_query($conn, "UPDATE appointments SET status='confirmed' WHERE id=?", 'i', [$appointmentId]);
        add_notification($conn, (int) $appt['patient_user_id'], 'Appointment Accepted', 'Dr. ' . current_user()['full_name'] . ' has accepted your appointment on ' . format_date($appt['appointment_date']) . '.');
        log_activity($conn, current_user_id(), 'appointment_accepted', "Doctor accepted appointment #$appointmentId");
        set_flash('success', 'Appointment accepted.');
    } elseif ($action === 'reject') {
        $reason = sanitize($_POST['cancelled_reason'] ?? '');
        db_query($conn, "UPDATE appointments SET status='rejected', cancelled_reason=? WHERE id=?", 'si', [$reason, $appointmentId]);
        add_notification($conn, (int) $appt['patient_user_id'], 'Appointment Declined', 'Your appointment on ' . format_date($appt['appointment_date']) . ' could not be accepted. ' . ($reason ? "Reason: $reason" : ''));
        log_activity($conn, current_user_id(), 'appointment_rejected', "Doctor rejected appointment #$appointmentId");
        set_flash('success', 'Appointment declined.');
    } elseif ($action === 'complete') {
        $notes = sanitize($_POST['consultation_notes'] ?? '');
        $prescription = sanitize($_POST['prescription'] ?? '');
        db_query($conn, "UPDATE appointments SET status='completed', consultation_notes=?, prescription=? WHERE id=?", 'ssi', [$notes, $prescription, $appointmentId]);
        add_notification($conn, (int) $appt['patient_user_id'], 'Visit Completed', 'Your appointment on ' . format_date($appt['appointment_date']) . ' has been marked complete. Consultation notes are available in your appointment history.');
        log_activity($conn, current_user_id(), 'appointment_completed', "Doctor completed appointment #$appointmentId");
        set_flash('success', 'Appointment marked as completed.');
    }
    redirect('doctor/appointments.php' . (!empty($_POST['redirect_qs']) ? '?' . $_POST['redirect_qs'] : ''));
}

/* =====================================================================
   FILTERS + LIST
   ===================================================================== */
$statusFilter = sanitize($_GET['status'] ?? '');
$dateFilter = sanitize($_GET['date'] ?? '');
$search = sanitize($_GET['search'] ?? '');

$where = ['a.doctor_id = ?'];
$types = 'i';
$params = [$doctorId];

if (in_array($statusFilter, ['pending', 'confirmed', 'completed', 'cancelled', 'rejected'], true)) {
    $where[] = 'a.status = ?';
    $types .= 's';
    $params[] = $statusFilter;
}
if ($dateFilter !== '') {
    $where[] = 'a.appointment_date = ?';
    $types .= 's';
    $params[] = $dateFilter;
}
if ($search !== '') {
    $where[] = 'pu.full_name LIKE ?';
    $types .= 's';
    $params[] = '%' . $search . '%';
}
$whereSql = implode(' AND ', $where);

$countSql = "SELECT COUNT(*) c FROM appointments a JOIN patients p ON p.id = a.patient_id JOIN users pu ON pu.id = p.user_id WHERE $whereSql";
$stmt = db_query($conn, $countSql, $types, $params);
$totalRecords = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
$pagination = paginate($totalRecords);

$listSql = "SELECT a.*, pu.full_name AS patient_name, pu.phone AS patient_phone, pu.date_of_birth, pu.gender, pu.profile_photo
            FROM appointments a
            JOIN patients p ON p.id = a.patient_id JOIN users pu ON pu.id = p.user_id
            WHERE $whereSql
            ORDER BY a.appointment_date DESC, a.appointment_time ASC
            LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}";
$stmt = db_query($conn, $listSql, $types, $params);
$appointments = [];
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $appointments[] = $row;
}

$redirectQs = http_build_query($_GET);

$pageTitle = "Today's Appointments";

$extraScripts = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-reject-appt').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('reject_appointment_id').value = btn.dataset.id;
            document.getElementById('reject_reason').value = '';
            new bootstrap.Modal(document.getElementById('rejectApptModal')).show();
        });
    });
    document.querySelectorAll('.btn-complete-appt').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('complete_appointment_id').value = btn.dataset.id;
            document.getElementById('complete_patient_name').textContent = btn.dataset.patient;
            document.getElementById('consultation_notes').value = '';
            document.getElementById('prescription').value = '';
            new bootstrap.Modal(document.getElementById('completeApptModal')).show();
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
            <h2 class="h4 fw-bold mb-1">My Appointments</h2>
            <p class="text-muted mb-0"><?php echo $totalRecords; ?> appointment<?php echo $totalRecords === 1 ? '' : 's'; ?> matching your filters.</p>
        </div>

        <!-- Filters -->
        <form method="GET" class="hams-card p-3 mb-4">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label mb-1">Search Patient</label>
                    <input type="text" class="form-control" name="search" value="<?php echo e($search); ?>" placeholder="Patient name...">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All</option>
                        <?php foreach (['pending', 'confirmed', 'completed', 'cancelled', 'rejected'] as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo $statusFilter === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">Date</label>
                    <input type="date" class="form-control" name="date" value="<?php echo e($dateFilter); ?>">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search"></i></button>
                    <a href="<?php echo BASE_URL; ?>doctor/appointments.php" class="btn btn-outline-secondary flex-fill"><i class="bi bi-x-lg"></i></a>
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
                            <th>Date &amp; Time</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($appointments)): ?>
                            <tr><td colspan="5"><div class="empty-state"><i class="bi bi-calendar-x"></i><p class="mb-0">No appointments match your filters.</p></div></td></tr>
                        <?php else: ?>
                            <?php foreach ($appointments as $appt): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?php echo UPLOADS_URL . e($appt['profile_photo'] ?: 'default-avatar.png'); ?>" onerror="this.src='<?php echo ASSETS_URL; ?>images/default-avatar.png'" class="avatar-sm" alt="">
                                        <div>
                                            <div class="fw-semibold"><?php echo e($appt['patient_name']); ?></div>
                                            <div class="text-muted small"><?php echo e($appt['patient_phone'] ?: '—'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo format_date($appt['appointment_date']); ?> &middot; <?php echo format_time($appt['appointment_time']); ?></td>
                                <td class="text-muted small"><?php echo e($appt['reason'] ?: '—'); ?></td>
                                <td><span class="badge <?php echo status_badge_class($appt['status']); ?>"><?php echo e(ucfirst($appt['status'])); ?></span></td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        <?php if ($appt['status'] === 'pending'): ?>
                                            <form method="POST" class="d-inline">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="form_action" value="accept">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appt['id']; ?>">
                                                <input type="hidden" name="redirect_qs" value="<?php echo e($redirectQs); ?>">
                                                <button type="submit" class="icon-btn text-success" title="Accept"><i class="bi bi-check-lg"></i></button>
                                            </form>
                                            <button type="button" class="icon-btn text-danger btn-reject-appt" data-id="<?php echo $appt['id']; ?>" title="Reject"><i class="bi bi-x-lg"></i></button>
                                        <?php elseif ($appt['status'] === 'confirmed'): ?>
                                            <button type="button" class="icon-btn text-success btn-complete-appt"
                                                data-id="<?php echo $appt['id']; ?>"
                                                data-patient="<?php echo e($appt['patient_name']); ?>"
                                                title="Mark completed"><i class="bi bi-check2-all"></i></button>
                                        <?php elseif ($appt['status'] === 'completed'): ?>
                                            <span class="text-muted small"><i class="bi bi-clipboard2-check me-1"></i>Notes saved</span>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
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

        <!-- ===================== REJECT MODAL ===================== -->
        <div class="modal fade" id="rejectApptModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="form_action" value="reject">
                        <input type="hidden" name="appointment_id" id="reject_appointment_id">
                        <input type="hidden" name="redirect_qs" value="<?php echo e($redirectQs); ?>">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold">Decline Appointment</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label">Reason (shown to the patient)</label>
                            <textarea class="form-control" name="cancelled_reason" id="reject_reason" rows="3" placeholder="e.g. Fully booked that day, please choose another slot..."></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Back</button>
                            <button type="submit" class="btn btn-danger"><i class="bi bi-x-lg me-1"></i>Decline</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ===================== COMPLETE MODAL ===================== -->
        <div class="modal fade" id="completeApptModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="form_action" value="complete">
                        <input type="hidden" name="appointment_id" id="complete_appointment_id">
                        <input type="hidden" name="redirect_qs" value="<?php echo e($redirectQs); ?>">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold">Complete Visit — <span id="complete_patient_name"></span></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Consultation Notes</label>
                                <textarea class="form-control" name="consultation_notes" id="consultation_notes" rows="4" placeholder="Diagnosis, observations, follow-up recommendations..."></textarea>
                            </div>
                            <div>
                                <label class="form-label">Prescription <span class="text-muted small">(optional)</span></label>
                                <textarea class="form-control" name="prescription" id="prescription" rows="3" placeholder="Medications, dosage, instructions..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check2-all me-1"></i>Mark Completed</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>