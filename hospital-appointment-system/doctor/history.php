<?php
/**
 * =====================================================================
 * doctor/history.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * Read-only history of the doctor's past appointments (completed,
 * cancelled, rejected) with consultation notes lookup. Active/pending
 * appointments live in doctor/appointments.php, not here.
 * =====================================================================
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('doctor');

$doctorId = (int) current_user()['doctor_id'];

/* =====================================================================
   FILTERS + LIST
   ===================================================================== */
$search = sanitize($_GET['search'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$dateFrom = sanitize($_GET['date_from'] ?? '');
$dateTo = sanitize($_GET['date_to'] ?? '');

$where = ['a.doctor_id = ?', "a.status IN ('completed','cancelled','rejected')"];
$types = 'i';
$params = [$doctorId];

if ($search !== '') {
    $where[] = 'pu.full_name LIKE ?';
    $types .= 's';
    $params[] = '%' . $search . '%';
}
if (in_array($statusFilter, ['completed', 'cancelled', 'rejected'], true)) {
    $where[] = 'a.status = ?';
    $types .= 's';
    $params[] = $statusFilter;
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

$baseFrom = "FROM appointments a JOIN patients p ON p.id = a.patient_id JOIN users pu ON pu.id = p.user_id";

$countSql = "SELECT COUNT(*) c $baseFrom WHERE $whereSql";
$stmt = db_query($conn, $countSql, $types, $params);
$totalRecords = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
$pagination = paginate($totalRecords);

$listSql = "SELECT a.*, pu.full_name AS patient_name, pu.phone AS patient_phone, pu.profile_photo
            $baseFrom
            WHERE $whereSql
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
            LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}";
$stmt = db_query($conn, $listSql, $types, $params);
$history = [];
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $history[] = $row;
}

$pageTitle = 'Appointment History';

$extraScripts = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-view-history').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('hist_patient').textContent = btn.dataset.patient;
            document.getElementById('hist_datetime').textContent = btn.dataset.datetime;
            document.getElementById('hist_status').innerHTML = btn.dataset.statusBadge;
            document.getElementById('hist_reason').textContent = btn.dataset.reason || 'No reason provided.';
            document.getElementById('hist_notes').textContent = btn.dataset.notes || 'No consultation notes recorded.';
            document.getElementById('hist_prescription').textContent = btn.dataset.prescription || 'No prescription recorded.';
            document.getElementById('hist_cancelled_reason_wrap').style.display = btn.dataset.cancelledReason ? '' : 'none';
            document.getElementById('hist_cancelled_reason').textContent = btn.dataset.cancelledReason || '';
            new bootstrap.Modal(document.getElementById('viewHistoryModal')).show();
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
            <h2 class="h4 fw-bold mb-1">Appointment History</h2>
            <p class="text-muted mb-0"><?php echo $totalRecords; ?> past appointment<?php echo $totalRecords === 1 ? '' : 's'; ?>.</p>
        </div>

        <!-- Filters -->
        <form method="GET" class="hams-card p-3 mb-4">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label mb-1">Search Patient</label>
                    <input type="text" class="form-control" name="search" value="<?php echo e($search); ?>" placeholder="Patient name...">
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All</option>
                        <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">From</label>
                    <input type="date" class="form-control" name="date_from" value="<?php echo e($dateFrom); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">To</label>
                    <input type="date" class="form-control" name="date_to" value="<?php echo e($dateTo); ?>">
                </div>
                <div class="col-md-1 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search"></i></button>
                </div>
            </div>
        </form>

        <!-- History table -->
        <div class="table-responsive-card mb-3">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Date &amp; Time</th>
                            <th>Status</th>
                            <th class="text-end">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history)): ?>
                            <tr><td colspan="4"><div class="empty-state"><i class="bi bi-clock-history"></i><p class="mb-0">No past appointments found for these filters.</p></div></td></tr>
                        <?php else: ?>
                            <?php foreach ($history as $appt):
                                $dateTimeLabel = format_date($appt['appointment_date']) . ' · ' . format_time($appt['appointment_time']);
                                $statusBadgeHtml = '<span class="badge ' . status_badge_class($appt['status']) . '">' . ucfirst($appt['status']) . '</span>';
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?php echo UPLOADS_URL . e($appt['profile_photo'] ?: 'default-avatar.png'); ?>" onerror="this.src='<?php echo ASSETS_URL; ?>images/default-avatar.png'" class="avatar-sm" alt="">
                                        <?php echo e($appt['patient_name']); ?>
                                    </div>
                                </td>
                                <td><?php echo $dateTimeLabel; ?></td>
                                <td><span class="badge <?php echo status_badge_class($appt['status']); ?>"><?php echo e(ucfirst($appt['status'])); ?></span></td>
                                <td class="text-end">
                                    <button type="button" class="icon-btn btn-view-history"
                                        data-patient="<?php echo e($appt['patient_name']); ?>"
                                        data-datetime="<?php echo e($dateTimeLabel); ?>"
                                        data-status-badge="<?php echo e($statusBadgeHtml); ?>"
                                        data-reason="<?php echo e($appt['reason']); ?>"
                                        data-notes="<?php echo e($appt['consultation_notes']); ?>"
                                        data-prescription="<?php echo e($appt['prescription']); ?>"
                                        data-cancelled-reason="<?php echo e($appt['cancelled_reason']); ?>"
                                        title="View details"><i class="bi bi-eye"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php render_pagination($pagination['currentPage'], $pagination['totalPages']); ?>

        <!-- ===================== VIEW HISTORY MODAL ===================== -->
        <div class="modal fade" id="viewHistoryModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Visit Details — <span id="hist_patient"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <dl class="row small mb-3">
                            <dt class="col-3 text-muted">Date &amp; Time</dt><dd class="col-9" id="hist_datetime"></dd>
                            <dt class="col-3 text-muted">Status</dt><dd class="col-9" id="hist_status"></dd>
                            <dt class="col-3 text-muted">Reason for Visit</dt><dd class="col-9" id="hist_reason"></dd>
                        </dl>
                        <div id="hist_cancelled_reason_wrap" class="alert alert-danger py-2 small">
                            <strong>Cancellation reason:</strong> <span id="hist_cancelled_reason"></span>
                        </div>
                        <div class="mb-3">
                            <h6 class="fw-bold small text-muted text-uppercase">Consultation Notes</h6>
                            <p id="hist_notes" class="mb-0"></p>
                        </div>
                        <div>
                            <h6 class="fw-bold small text-muted text-uppercase">Prescription</h6>
                            <p id="hist_prescription" class="mb-0"></p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>