<?php
/**
 * =====================================================================
 * doctor/dashboard.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * Doctor dashboard: today's appointments, quick stats, and a 7-day
 * appointment trend for this doctor specifically.
 * =====================================================================
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('doctor');

$doctorId = (int) current_user()['doctor_id'];

/* =====================================================================
   STATS
   ===================================================================== */
function doc_count(mysqli $conn, int $doctorId, string $extraWhere = '', string $types = '', array $params = []): int
{
    $sql = "SELECT COUNT(*) c FROM appointments WHERE doctor_id = ? $extraWhere";
    $stmt = db_query($conn, $sql, 'i' . $types, array_merge([$doctorId], $params));
    return (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
}

$todayCount = doc_count($conn, $doctorId, "AND appointment_date = CURDATE()");
$pendingCount = doc_count($conn, $doctorId, "AND status = 'pending'");
$weekStart = date('Y-m-d', strtotime('monday this week'));
$completedThisWeek = doc_count($conn, $doctorId, "AND status = 'completed' AND appointment_date >= ?", 's', [$weekStart]);

$stmt = db_query($conn, "SELECT COUNT(DISTINCT patient_id) c FROM appointments WHERE doctor_id = ?", 'i', [$doctorId]);
$totalPatients = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];

/* =====================================================================
   TODAY'S APPOINTMENTS
   ===================================================================== */
$stmt = db_query(
    $conn,
    "SELECT a.*, pu.full_name AS patient_name, pu.phone AS patient_phone, pu.profile_photo
     FROM appointments a
     JOIN patients p ON p.id = a.patient_id JOIN users pu ON pu.id = p.user_id
     WHERE a.doctor_id = ? AND a.appointment_date = CURDATE()
     ORDER BY a.appointment_time ASC",
    'i',
    [$doctorId]
);
$todaysAppointments = [];
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $todaysAppointments[] = $row;
}

/* =====================================================================
   7-DAY TREND
   ===================================================================== */
$chartLabels = [];
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('D', strtotime($date));
    $chartData[] = doc_count($conn, $doctorId, "AND appointment_date = ?", 's', [$date]);
}

$nameParts = explode(' ', current_user()['full_name']);
$doctorLastName = end($nameParts);

$pageTitle = 'Dashboard';
$chartLabelsJson = json_encode($chartLabels);
$chartDataJson = json_encode($chartData);

$extraScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>' . "\n" . <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {
    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const gridColor = isDark ? 'rgba(255,255,255,.08)' : 'rgba(15,23,42,.06)';
    const textColor = isDark ? '#93A4C3' : '#64748B';
    new Chart(document.getElementById('trendChart'), {
        type: 'bar',
        data: { labels: {$chartLabelsJson}, datasets: [{ label: 'Appointments', data: {$chartDataJson}, backgroundColor: '#38BDF8', borderRadius: 6, maxBarThickness: 36 }] },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { color: textColor } },
                y: { beginAtZero: true, ticks: { stepSize: 1, color: textColor }, grid: { color: gridColor } }
            }
        }
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
            <h2 class="h4 fw-bold mb-1">Good day, Dr. <?php echo e($doctorLastName); ?> 👋</h2>
            <p class="text-muted mb-0">Here's your schedule for <?php echo date('l, F j, Y'); ?>.</p>
        </div>

        <!-- Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="stat-card"><div class="stat-icon icon-primary"><i class="bi bi-calendar2-check"></i></div>
                    <div><div class="stat-value"><?php echo $todayCount; ?></div><div class="stat-label">Today's Appointments</div></div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card"><div class="stat-icon icon-warning"><i class="bi bi-hourglass-split"></i></div>
                    <div><div class="stat-value"><?php echo $pendingCount; ?></div><div class="stat-label">Pending Requests</div></div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card"><div class="stat-icon icon-secondary"><i class="bi bi-check2-circle"></i></div>
                    <div><div class="stat-value"><?php echo $completedThisWeek; ?></div><div class="stat-label">Completed This Week</div></div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card"><div class="stat-icon icon-primary"><i class="bi bi-people"></i></div>
                    <div><div class="stat-value"><?php echo $totalPatients; ?></div><div class="stat-label">Total Patients Seen</div></div></div>
            </div>
        </div>

        <div class="row g-3">
            <!-- Today's appointments -->
            <div class="col-lg-7">
                <div class="table-responsive-card h-100">
                    <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                        <h6 class="fw-bold mb-0">Today's Appointments</h6>
                        <a href="<?php echo BASE_URL; ?>doctor/appointments.php" class="small fw-semibold">View all <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>Time</th><th>Patient</th><th>Reason</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php if (empty($todaysAppointments)): ?>
                                    <tr><td colspan="4"><div class="empty-state"><i class="bi bi-calendar-check"></i><p class="mb-0">No appointments scheduled for today.</p></div></td></tr>
                                <?php else: ?>
                                    <?php foreach ($todaysAppointments as $appt): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo format_time($appt['appointment_time']); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <img src="<?php echo UPLOADS_URL . e($appt['profile_photo'] ?: 'default-avatar.png'); ?>" onerror="this.src='<?php echo ASSETS_URL; ?>images/default-avatar.png'" class="avatar-sm" alt="">
                                                <?php echo e($appt['patient_name']); ?>
                                            </div>
                                        </td>
                                        <td class="text-muted small"><?php echo e($appt['reason'] ?: '—'); ?></td>
                                        <td><span class="badge <?php echo status_badge_class($appt['status']); ?>"><?php echo e(ucfirst($appt['status'])); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Trend chart -->
            <div class="col-lg-5">
                <div class="hams-card p-4 h-100">
                    <h6 class="fw-bold mb-3">Appointments — Last 7 Days</h6>
                    <canvas id="trendChart" height="180"></canvas>
                </div>
            </div>
        </div>

    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>