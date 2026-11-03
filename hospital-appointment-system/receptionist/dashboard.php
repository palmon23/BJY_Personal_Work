<?php
/**
 * =====================================================================
 * patient/dashboard.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * Patient dashboard: upcoming appointments, quick stats, and a
 * shortcut to book a new appointment.
 * =====================================================================
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('patient');

$patientId = (int) current_user()['patient_id'];

/* =====================================================================
   STATS
   ===================================================================== */
function pat_count(mysqli $conn, int $patientId, string $extraWhere = ''): int
{
    $stmt = db_query($conn, "SELECT COUNT(*) c FROM appointments WHERE patient_id = ? $extraWhere", 'i', [$patientId]);
    return (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
}

$upcomingCount = pat_count($conn, $patientId, "AND status IN ('pending','confirmed') AND appointment_date >= CURDATE()");
$completedCount = pat_count($conn, $patientId, "AND status = 'completed'");
$totalCount = pat_count($conn, $patientId);
$cancelledCount = pat_count($conn, $patientId, "AND status IN ('cancelled','rejected')");

/* =====================================================================
   UPCOMING APPOINTMENTS (next 5)
   ===================================================================== */
$stmt = db_query(
    $conn,
    "SELECT a.*, du.full_name AS doctor_name, du.profile_photo AS doctor_photo, dept.name AS department_name
     FROM appointments a
     JOIN doctors d ON d.id = a.doctor_id JOIN users du ON du.id = d.user_id
     JOIN departments dept ON dept.id = a.department_id
     WHERE a.patient_id = ? AND a.status IN ('pending','confirmed') AND a.appointment_date >= CURDATE()
     ORDER BY a.appointment_date ASC, a.appointment_time ASC
     LIMIT 5",
    'i',
    [$patientId]
);
$upcomingAppointments = [];
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $upcomingAppointments[] = $row;
}

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="hams-main">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="hams-content">

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="h4 fw-bold mb-1">Hi, <?php echo e(explode(' ', current_user()['full_name'])[0]); ?> 👋</h2>
                <p class="text-muted mb-0">Here's an overview of your care.</p>
            </div>
            <a href="<?php echo BASE_URL; ?>patient/book_appointment.php" class="btn btn-primary"><i class="bi bi-calendar-plus me-1"></i>Book Appointment</a>
        </div>

        <!-- Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="stat-card"><div class="stat-icon icon-primary"><i class="bi bi-calendar2-check"></i></div>
                    <div><div class="stat-value"><?php echo $upcomingCount; ?></div><div class="stat-label">Upcoming</div></div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card"><div class="stat-icon icon-secondary"><i class="bi bi-check2-circle"></i></div>
                    <div><div class="stat-value"><?php echo $completedCount; ?></div><div class="stat-label">Completed Visits</div></div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card"><div class="stat-icon icon-primary"><i class="bi bi-calendar3"></i></div>
                    <div><div class="stat-value"><?php echo $totalCount; ?></div><div class="stat-label">Total Appointments</div></div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card"><div class="stat-icon icon-danger"><i class="bi bi-x-circle"></i></div>
                    <div><div class="stat-value"><?php echo $cancelledCount; ?></div><div class="stat-label">Cancelled</div></div></div>
            </div>
        </div>

        <!-- Upcoming appointments -->
        <div class="table-responsive-card mb-4">
            <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                <h6 class="fw-bold mb-0">Upcoming Appointments</h6>
                <a href="<?php echo BASE_URL; ?>patient/appointments.php" class="small fw-semibold">View all <i class="bi bi-arrow-right"></i></a>
            </div>

            <?php if (empty($upcomingAppointments)): ?>
                <div class="empty-state"><i class="bi bi-calendar-plus"></i><p class="mb-2">You don't have any upcoming appointments.</p>
                    <a href="<?php echo BASE_URL; ?>patient/book_appointment.php" class="btn btn-primary btn-sm">Book Your First Appointment</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Doctor</th><th>Department</th><th>Date &amp; Time</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($upcomingAppointments as $appt): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?php echo UPLOADS_URL . e($appt['doctor_photo'] ?: 'default-avatar.png'); ?>" onerror="this.src='<?php echo ASSETS_URL; ?>images/default-avatar.png'" class="avatar-sm" alt="">
                                        Dr. <?php echo e($appt['doctor_name']); ?>
                                    </div>
                                </td>
                                <td><?php echo e($appt['department_name']); ?></td>
                                <td><?php echo format_date($appt['appointment_date']); ?> &middot; <?php echo format_time($appt['appointment_time']); ?></td>
                                <td><span class="badge <?php echo status_badge_class($appt['status']); ?>"><?php echo e(ucfirst($appt['status'])); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>