<?php
/**
 * =====================================================================
 * admin/dashboard.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * Administrator dashboard: system-wide stats, a 7-day appointments
 * trend chart, and a recent-appointments table.
 * =====================================================================
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

// ---------------------------------------------------------------
// Stat cards
// ---------------------------------------------------------------
function count_query(mysqli $conn, string $sql): int
{
    $res = mysqli_query($conn, $sql);
    return (int) (($res ? mysqli_fetch_assoc($res) : null)['c'] ?? 0);
}

$totalDoctors      = count_query($conn, "SELECT COUNT(*) c FROM doctors WHERE status = 'active'");
$totalPatients     = count_query($conn, "SELECT COUNT(*) c FROM patients");
$totalDepartments  = count_query($conn, "SELECT COUNT(*) c FROM departments WHERE status = 'active'");
$appointmentsToday = count_query($conn, "SELECT COUNT(*) c FROM appointments WHERE appointment_date = CURDATE()");
$pendingCount      = count_query($conn, "SELECT COUNT(*) c FROM appointments WHERE status = 'pending'");
$completedCount    = count_query($conn, "SELECT COUNT(*) c FROM appointments WHERE status = 'completed'");

// ---------------------------------------------------------------
// 7-day appointment trend (for the chart)
// ---------------------------------------------------------------
$chartLabels = [];
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('D', strtotime($date));
    $chartData[] = count_query($conn, "SELECT COUNT(*) c FROM appointments WHERE appointment_date = '$date'");
}

// ---------------------------------------------------------------
// Appointment status breakdown (for the doughnut chart)
// ---------------------------------------------------------------
$statusCounts = [
    'Pending'   => $pendingCount,
    'Confirmed' => count_query($conn, "SELECT COUNT(*) c FROM appointments WHERE status = 'confirmed'"),
    'Completed' => $completedCount,
    'Cancelled' => count_query($conn, "SELECT COUNT(*) c FROM appointments WHERE status IN ('cancelled','rejected')"),
];

// ---------------------------------------------------------------
// Recent appointments (last 8)
// ---------------------------------------------------------------
$recentSql = "SELECT a.id, a.appointment_date, a.appointment_time, a.status,
                     pu.full_name AS patient_name, du.full_name AS doctor_name, dept.name AS department_name
              FROM appointments a
              JOIN patients p ON p.id = a.patient_id
              JOIN users pu ON pu.id = p.user_id
              JOIN doctors d ON d.id = a.doctor_id
              JOIN users du ON du.id = d.user_id
              JOIN departments dept ON dept.id = a.department_id
              ORDER BY a.created_at DESC
              LIMIT 8";
$recentAppointments = [];
$res = mysqli_query($conn, $recentSql);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $recentAppointments[] = $row;
    }
}

$chartLabelsJson = json_encode($chartLabels);
$chartDataJson = json_encode($chartData);
$statusLabelsJson = json_encode(array_keys($statusCounts));
$statusDataJson = json_encode(array_values($statusCounts));

$pageTitle = 'Dashboard';

$extraScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>' . "\n" . <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {
    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const gridColor = isDark ? 'rgba(255,255,255,.08)' : 'rgba(15,23,42,.06)';
    const textColor = isDark ? '#93A4C3' : '#64748B';

    new Chart(document.getElementById('trendChart'), {
        type: 'bar',
        data: {
            labels: {$chartLabelsJson},
            datasets: [{
                label: 'Appointments',
                data: {$chartDataJson},
                backgroundColor: '#38BDF8',
                borderRadius: 6,
                maxBarThickness: 36,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { color: textColor } },
                y: { beginAtZero: true, ticks: { stepSize: 1, color: textColor }, grid: { color: gridColor } }
            }
        }
    });

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: {$statusLabelsJson},
            datasets: [{
                data: {$statusDataJson},
                backgroundColor: ['#F59E0B', '#38BDF8', '#22C55E', '#EF4444'],
                borderWidth: 0,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom', labels: { color: textColor, boxWidth: 12, padding: 14 } } },
            cutout: '68%'
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

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="h4 fw-bold mb-1">Welcome back, <?php echo e(explode(' ', current_user()['full_name'])[0]); ?> 👋</h2>
                <p class="text-muted mb-0">Here's what's happening across the hospital today.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?php echo BASE_URL; ?>admin/doctors.php?action=add" class="btn btn-outline-primary"><i class="bi bi-person-plus me-1"></i>Add Doctor</a>
                <a href="<?php echo BASE_URL; ?>admin/appointments.php" class="btn btn-primary"><i class="bi bi-calendar2-check me-1"></i>View Appointments</a>
            </div>
        </div>

        <!-- Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon icon-primary"><i class="bi bi-person-badge"></i></div>
                    <div>
                        <div class="stat-value"><?php echo $totalDoctors; ?></div>
                        <div class="stat-label">Active Doctors</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon icon-secondary"><i class="bi bi-people"></i></div>
                    <div>
                        <div class="stat-value"><?php echo $totalPatients; ?></div>
                        <div class="stat-label">Registered Patients</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon icon-primary"><i class="bi bi-calendar2-check"></i></div>
                    <div>
                        <div class="stat-value"><?php echo $appointmentsToday; ?></div>
                        <div class="stat-label">Appointments Today</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon icon-warning"><i class="bi bi-hourglass-split"></i></div>
                    <div>
                        <div class="stat-value"><?php echo $pendingCount; ?></div>
                        <div class="stat-label">Pending Approval</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <!-- Trend chart -->
            <div class="col-lg-8">
                <div class="hams-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0">Appointments — Last 7 Days</h6>
                        <span class="badge bg-primary-soft text-primary"><?php echo array_sum($chartData); ?> total</span>
                    </div>
                    <canvas id="trendChart" height="110"></canvas>
                </div>
            </div>
            <!-- Status breakdown -->
            <div class="col-lg-4">
                <div class="hams-card p-4 h-100">
                    <h6 class="fw-bold mb-3">Status Breakdown</h6>
                    <canvas id="statusChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent appointments -->
        <div class="table-responsive-card">
            <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                <h6 class="fw-bold mb-0">Recent Appointments</h6>
                <a href="<?php echo BASE_URL; ?>admin/appointments.php" class="small fw-semibold">View all <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Department</th>
                            <th>Date &amp; Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentAppointments)): ?>
                            <tr><td colspan="5">
                                <div class="empty-state"><i class="bi bi-calendar-x"></i><p class="mb-0">No appointments booked yet.</p></div>
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($recentAppointments as $appt): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo e($appt['patient_name']); ?></td>
                                <td>Dr. <?php echo e($appt['doctor_name']); ?></td>
                                <td><?php echo e($appt['department_name']); ?></td>
                                <td><?php echo format_date($appt['appointment_date']); ?> &middot; <?php echo format_time($appt['appointment_time']); ?></td>
                                <td><span class="badge <?php echo status_badge_class($appt['status']); ?>"><?php echo e(ucfirst($appt['status'])); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>