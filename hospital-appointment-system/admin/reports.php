<?php
/**
 * =====================================================================
 * admin/reports.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * Analytics: appointments by department/status, doctor performance
 * table, date-range filtering, and CSV export of the doctor summary.
 * =====================================================================
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$dateFrom = sanitize($_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days')));
$dateTo   = sanitize($_GET['date_to'] ?? date('Y-m-d'));

// Guard against an inverted range
if (strtotime($dateFrom) > strtotime($dateTo)) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

/* =====================================================================
   DOCTOR PERFORMANCE (shared by both the on-page table and CSV export)
   ===================================================================== */
$doctorPerfSql = "SELECT u.full_name AS doctor_name, dept.name AS department_name,
                         COUNT(a.id) AS total_appts,
                         SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) AS completed_appts,
                         SUM(CASE WHEN a.status IN ('cancelled','rejected') THEN 1 ELSE 0 END) AS cancelled_appts,
                         SUM(CASE WHEN a.status = 'completed' THEN d.consultation_fee ELSE 0 END) AS revenue
                  FROM doctors d
                  JOIN users u ON u.id = d.user_id
                  JOIN departments dept ON dept.id = d.department_id
                  LEFT JOIN appointments a ON a.doctor_id = d.id AND a.appointment_date BETWEEN ? AND ?
                  GROUP BY d.id
                  ORDER BY total_appts DESC";
$stmt = db_query($conn, $doctorPerfSql, 'ss', [$dateFrom, $dateTo]);
$doctorPerf = [];
if ($stmt) {
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $doctorPerf[] = $row;
    }
}

/* =====================================================================
   CSV EXPORT — must run before any HTML output
   ===================================================================== */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="hams_doctor_report_' . $dateFrom . '_to_' . $dateTo . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Doctor', 'Department', 'Total Appointments', 'Completed', 'Cancelled/Rejected', 'Revenue ($)']);
    foreach ($doctorPerf as $row) {
        fputcsv($out, [
            $row['doctor_name'],
            $row['department_name'],
            $row['total_appts'],
            $row['completed_appts'],
            $row['cancelled_appts'],
            number_format((float) $row['revenue'], 2),
        ]);
    }
    fclose($out);
    exit;
}

/* =====================================================================
   SUMMARY STATS
   ===================================================================== */
function report_count(mysqli $conn, string $sql, string $types, array $params): int
{
    $stmt = db_query($conn, $sql, $types, $params);
    return (int) (($stmt ? mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) : null)['c'] ?? 0);
}

$totalInRange     = report_count($conn, "SELECT COUNT(*) c FROM appointments WHERE appointment_date BETWEEN ? AND ?", 'ss', [$dateFrom, $dateTo]);
$completedInRange = report_count($conn, "SELECT COUNT(*) c FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status='completed'", 'ss', [$dateFrom, $dateTo]);
$cancelledInRange = report_count($conn, "SELECT COUNT(*) c FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status IN ('cancelled','rejected')", 'ss', [$dateFrom, $dateTo]);
$newPatientsInRange = report_count($conn, "SELECT COUNT(*) c FROM users WHERE role='patient' AND created_at BETWEEN ? AND ?", 'ss', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

$stmt = db_query($conn, "SELECT COALESCE(SUM(d.consultation_fee),0) c FROM appointments a JOIN doctors d ON d.id = a.doctor_id WHERE a.appointment_date BETWEEN ? AND ? AND a.status = 'completed'", 'ss', [$dateFrom, $dateTo]);
$totalRevenue = (float) (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'] ?? 0);

/* =====================================================================
   APPOINTMENTS BY DEPARTMENT (bar chart)
   ===================================================================== */
$deptSql = "SELECT dept.name, COUNT(a.id) AS c
            FROM departments dept
            LEFT JOIN appointments a ON a.department_id = dept.id AND a.appointment_date BETWEEN ? AND ?
            WHERE dept.status = 'active'
            GROUP BY dept.id ORDER BY c DESC";
$stmt = db_query($conn, $deptSql, 'ss', [$dateFrom, $dateTo]);
$deptLabels = [];
$deptData = [];
if ($stmt) {
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $deptLabels[] = $row['name'];
        $deptData[] = (int) $row['c'];
    }
}

/* =====================================================================
   STATUS BREAKDOWN (doughnut)
   ===================================================================== */
$statusCounts = [
    'Pending'   => report_count($conn, "SELECT COUNT(*) c FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status='pending'", 'ss', [$dateFrom, $dateTo]),
    'Confirmed' => report_count($conn, "SELECT COUNT(*) c FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status='confirmed'", 'ss', [$dateFrom, $dateTo]),
    'Completed' => $completedInRange,
    'Cancelled' => $cancelledInRange,
];

$pageTitle = 'Reports';

$chartDeptLabelsJson = json_encode($deptLabels);
$chartDeptDataJson = json_encode($deptData);
$chartStatusLabelsJson = json_encode(array_keys($statusCounts));
$chartStatusDataJson = json_encode(array_values($statusCounts));

$extraScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>' . "\n" . <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {
    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const textColor = isDark ? '#93A4C3' : '#64748B';
    const gridColor = isDark ? 'rgba(255,255,255,.08)' : 'rgba(15,23,42,.06)';

    new Chart(document.getElementById('deptChart'), {
        type: 'bar',
        data: {
            labels: {$chartDeptLabelsJson},
            datasets: [{ label: 'Appointments', data: {$chartDeptDataJson}, backgroundColor: '#84CC16', borderRadius: 6, maxBarThickness: 40 }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, ticks: { stepSize: 1, color: textColor }, grid: { color: gridColor } },
                y: { ticks: { color: textColor }, grid: { display: false } }
            }
        }
    });

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: {$chartStatusLabelsJson},
            datasets: [{ data: {$chartStatusDataJson}, backgroundColor: ['#F59E0B', '#38BDF8', '#22C55E', '#EF4444'], borderWidth: 0 }]
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
                <h2 class="h4 fw-bold mb-1">Reports</h2>
                <p class="text-muted mb-0">Insights for <?php echo format_date($dateFrom); ?> &ndash; <?php echo format_date($dateTo); ?>.</p>
            </div>
            <a href="?date_from=<?php echo e($dateFrom); ?>&date_to=<?php echo e($dateTo); ?>&export=csv" class="btn btn-outline-primary">
                <i class="bi bi-download me-1"></i>Export Doctor Report (CSV)
            </a>
        </div>

        <!-- Date range filter -->
        <form method="GET" class="hams-card p-3 mb-4">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label mb-1">From</label>
                    <input type="date" class="form-control" name="date_from" value="<?php echo e($dateFrom); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1">To</label>
                    <input type="date" class="form-control" name="date_to" value="<?php echo e($dateTo); ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-filter me-1"></i>Apply</button>
                    <a href="<?php echo BASE_URL; ?>admin/reports.php" class="btn btn-outline-secondary flex-fill">Reset</a>
                </div>
            </div>
        </form>

        <!-- Summary stats -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon icon-primary"><i class="bi bi-calendar2-check"></i></div>
                    <div><div class="stat-value"><?php echo $totalInRange; ?></div><div class="stat-label">Total Appointments</div></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon icon-secondary"><i class="bi bi-check2-circle"></i></div>
                    <div><div class="stat-value"><?php echo $completedInRange; ?></div><div class="stat-label">Completed</div></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon icon-danger"><i class="bi bi-x-circle"></i></div>
                    <div><div class="stat-value"><?php echo $cancelledInRange; ?></div><div class="stat-label">Cancelled / Rejected</div></div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon icon-warning"><i class="bi bi-cash-coin"></i></div>
                    <div><div class="stat-value">$<?php echo number_format($totalRevenue, 0); ?></div><div class="stat-label">Revenue (completed)</div></div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row g-3 mb-4">
            <div class="col-lg-7">
                <div class="hams-card p-4 h-100">
                    <h6 class="fw-bold mb-3">Appointments by Department</h6>
                    <canvas id="deptChart" height="220"></canvas>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="hams-card p-4 h-100">
                    <h6 class="fw-bold mb-3">Status Breakdown</h6>
                    <canvas id="statusChart" height="220"></canvas>
                </div>
            </div>
        </div>

        <!-- Doctor performance table -->
        <div class="table-responsive-card">
            <div class="p-3 border-bottom"><h6 class="fw-bold mb-0">Doctor Performance</h6></div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Doctor</th>
                            <th>Department</th>
                            <th>Total</th>
                            <th>Completed</th>
                            <th>Cancelled</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($doctorPerf)): ?>
                            <tr><td colspan="6"><div class="empty-state"><i class="bi bi-bar-chart"></i><p class="mb-0">No data for this range.</p></div></td></tr>
                        <?php else: ?>
                            <?php foreach ($doctorPerf as $row): ?>
                            <tr>
                                <td class="fw-semibold">Dr. <?php echo e($row['doctor_name']); ?></td>
                                <td><?php echo e($row['department_name']); ?></td>
                                <td><?php echo (int) $row['total_appts']; ?></td>
                                <td><span class="badge bg-success"><?php echo (int) $row['completed_appts']; ?></span></td>
                                <td><span class="badge bg-danger"><?php echo (int) $row['cancelled_appts']; ?></span></td>
                                <td>$<?php echo number_format((float) $row['revenue'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>