<?php
/**
 * =====================================================================
 * admin/patient_view.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * Read-only detail view for a single patient: profile info plus their
 * full appointment history. Linked from the "View" icon in
 * admin/patients.php.
 * =====================================================================
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$patientId = (int) ($_GET['id'] ?? 0);
if ($patientId <= 0) {
    set_flash('error', 'Invalid patient.');
    redirect('admin/patients.php');
}

$stmt = db_query(
    $conn,
    "SELECT p.*, u.full_name, u.email, u.phone, u.gender, u.date_of_birth, u.address, u.profile_photo, u.status, u.created_at
     FROM patients p JOIN users u ON u.id = p.user_id
     WHERE p.id = ?",
    'i',
    [$patientId]
);
$patient = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$patient) {
    set_flash('error', 'Patient not found.');
    redirect('admin/patients.php');
}

// Appointment history (most recent first)
$stmt = db_query(
    $conn,
    "SELECT a.*, du.full_name AS doctor_name, dept.name AS department_name
     FROM appointments a
     JOIN doctors d ON d.id = a.doctor_id JOIN users du ON du.id = d.user_id
     JOIN departments dept ON dept.id = a.department_id
     WHERE a.patient_id = ?
     ORDER BY a.appointment_date DESC, a.appointment_time DESC",
    'i',
    [$patientId]
);
$appointments = [];
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $appointments[] = $row;
}

// Quick stats
$totalAppts = count($appointments);
$completedAppts = count(array_filter($appointments, fn($a) => $a['status'] === 'completed'));
$cancelledAppts = count(array_filter($appointments, fn($a) => in_array($a['status'], ['cancelled', 'rejected'], true)));
$upcomingAppts = count(array_filter($appointments, fn($a) => in_array($a['status'], ['pending', 'confirmed'], true) && strtotime($a['appointment_date']) >= strtotime(date('Y-m-d'))));

$pageTitle = 'Patient Profile';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="hams-main">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="hams-content">

        <div class="d-flex align-items-center gap-2 mb-4">
            <a href="<?php echo BASE_URL; ?>admin/patients.php" class="icon-btn"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h2 class="h4 fw-bold mb-1">Patient Profile</h2>
                <p class="text-muted mb-0">Read-only view — use the Patients list to edit details.</p>
            </div>
        </div>

        <div class="row g-4">
            <!-- Profile summary -->
            <div class="col-lg-4">
                <div class="hams-card p-4 text-center mb-3">
                    <img src="<?php echo UPLOADS_URL . e($patient['profile_photo'] ?: 'default-avatar.png'); ?>"
                         onerror="this.src='<?php echo ASSETS_URL; ?>images/default-avatar.png'"
                         class="avatar-lg mb-3" alt="">
                    <h5 class="fw-bold mb-0"><?php echo e($patient['full_name']); ?></h5>
                    <p class="text-muted small mb-2"><?php echo e($patient['email']); ?></p>
                    <span class="badge <?php echo status_badge_class($patient['status']); ?>"><?php echo e(ucfirst($patient['status'])); ?></span>

                    <hr>
                    <div class="text-start small">
                        <div class="d-flex justify-content-between py-1"><span class="text-muted">Phone</span><span><?php echo e($patient['phone'] ?: '—'); ?></span></div>
                        <div class="d-flex justify-content-between py-1"><span class="text-muted">Gender</span><span class="text-capitalize"><?php echo e($patient['gender'] ?: '—'); ?></span></div>
                        <div class="d-flex justify-content-between py-1"><span class="text-muted">Date of Birth</span><span><?php echo format_date($patient['date_of_birth']); ?></span></div>
                        <div class="d-flex justify-content-between py-1"><span class="text-muted">Blood Group</span><span><?php echo e($patient['blood_group'] ?: '—'); ?></span></div>
                        <div class="d-flex justify-content-between py-1"><span class="text-muted">Address</span><span class="text-end"><?php echo e($patient['address'] ?: '—'); ?></span></div>
                        <div class="d-flex justify-content-between py-1"><span class="text-muted">Registered</span><span><?php echo format_date($patient['created_at']); ?></span></div>
                    </div>
                </div>

                <div class="hams-card p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-person-lines-fill me-2"></i>Emergency Contact</h6>
                    <div class="small">
                        <div class="d-flex justify-content-between py-1"><span class="text-muted">Name</span><span><?php echo e($patient['emergency_contact_name'] ?: '—'); ?></span></div>
                        <div class="d-flex justify-content-between py-1"><span class="text-muted">Phone</span><span><?php echo e($patient['emergency_contact_phone'] ?: '—'); ?></span></div>
                    </div>
                </div>
            </div>

            <!-- Appointment history -->
            <div class="col-lg-8">
                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-3">
                        <div class="stat-card"><div class="stat-icon icon-primary"><i class="bi bi-calendar2-check"></i></div>
                            <div><div class="stat-value"><?php echo $totalAppts; ?></div><div class="stat-label">Total Visits</div></div></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card"><div class="stat-icon icon-secondary"><i class="bi bi-check2-circle"></i></div>
                            <div><div class="stat-value"><?php echo $completedAppts; ?></div><div class="stat-label">Completed</div></div></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card"><div class="stat-icon icon-warning"><i class="bi bi-hourglass-split"></i></div>
                            <div><div class="stat-value"><?php echo $upcomingAppts; ?></div><div class="stat-label">Upcoming</div></div></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card"><div class="stat-icon icon-danger"><i class="bi bi-x-circle"></i></div>
                            <div><div class="stat-value"><?php echo $cancelledAppts; ?></div><div class="stat-label">Cancelled</div></div></div>
                    </div>
                </div>

                <div class="table-responsive-card">
                    <div class="p-3 border-bottom"><h6 class="fw-bold mb-0">Appointment History</h6></div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Doctor</th>
                                    <th>Department</th>
                                    <th>Date &amp; Time</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($appointments)): ?>
                                    <tr><td colspan="5"><div class="empty-state"><i class="bi bi-calendar-x"></i><p class="mb-0">This patient has no appointments yet.</p></div></td></tr>
                                <?php else: ?>
                                    <?php foreach ($appointments as $appt): ?>
                                    <tr>
                                        <td class="fw-semibold">Dr. <?php echo e($appt['doctor_name']); ?></td>
                                        <td><?php echo e($appt['department_name']); ?></td>
                                        <td><?php echo format_date($appt['appointment_date']); ?> &middot; <?php echo format_time($appt['appointment_time']); ?></td>
                                        <td><span class="badge <?php echo status_badge_class($appt['status']); ?>"><?php echo e(ucfirst($appt['status'])); ?></span></td>
                                        <td class="text-muted small"><?php echo e($appt['consultation_notes'] ?: '—'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>