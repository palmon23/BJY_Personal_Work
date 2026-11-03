<?php
/**
 * =====================================================================
 * receptionist/doctor_availability.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * Read-only lookup of doctor weekly schedules and leave days, so the
 * front desk can check availability before calling a patient back —
 * without going through the full booking flow.
 * =====================================================================
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('receptionist');

$daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$dayAbbrev = ['Monday' => 'Mon', 'Tuesday' => 'Tue', 'Wednesday' => 'Wed', 'Thursday' => 'Thu', 'Friday' => 'Fri', 'Saturday' => 'Sat', 'Sunday' => 'Sun'];

$departmentFilter = (int) ($_GET['department_id'] ?? 0);
$doctorId = (int) ($_GET['doctor_id'] ?? 0);
$checkDate = sanitize($_GET['check_date'] ?? '');

// Departments for the filter
$departments = [];
$res = mysqli_query($conn, "SELECT id, name FROM departments WHERE status = 'active' ORDER BY name");
if ($res) while ($row = mysqli_fetch_assoc($res)) $departments[] = $row;

// Doctor list (optionally filtered by department)
if ($departmentFilter > 0) {
    $stmt = db_query($conn, "SELECT d.id, u.full_name, u.profile_photo, dept.name AS department_name FROM doctors d JOIN users u ON u.id = d.user_id JOIN departments dept ON dept.id = d.department_id WHERE d.status = 'active' AND d.department_id = ? ORDER BY u.full_name", 'i', [$departmentFilter]);
} else {
    $stmt = db_query($conn, "SELECT d.id, u.full_name, u.profile_photo, dept.name AS department_name FROM doctors d JOIN users u ON u.id = d.user_id JOIN departments dept ON dept.id = d.department_id WHERE d.status = 'active' ORDER BY u.full_name");
}
$doctorsList = [];
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) $doctorsList[] = $row;

// Pre-fetch each listed doctor's weekly schedule (for the compact day badges)
$scheduleByDoctor = [];
foreach ($doctorsList as $doc) {
    $stmt = db_query($conn, "SELECT day_of_week FROM doctor_schedules WHERE doctor_id = ?", 'i', [$doc['id']]);
    $res = mysqli_stmt_get_result($stmt);
    $days = [];
    while ($row = mysqli_fetch_assoc($res)) $days[] = $row['day_of_week'];
    $scheduleByDoctor[$doc['id']] = $days;
}

// Detail view for a single selected doctor
$selectedDoctor = null;
$fullSchedule = [];
$leaveDays = [];
$availableSlots = null;
$slotMessage = '';

if ($doctorId > 0) {
    $stmt = db_query($conn, "SELECT d.*, u.full_name, u.profile_photo, dept.name AS department_name FROM doctors d JOIN users u ON u.id = d.user_id JOIN departments dept ON dept.id = d.department_id WHERE d.id = ?", 'i', [$doctorId]);
    $selectedDoctor = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($selectedDoctor) {
        $stmt = db_query($conn, "SELECT * FROM doctor_schedules WHERE doctor_id = ?", 'i', [$doctorId]);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) $fullSchedule[$row['day_of_week']] = $row;

        $stmt = db_query($conn, "SELECT * FROM doctor_leaves WHERE doctor_id = ? AND leave_date >= CURDATE() ORDER BY leave_date ASC LIMIT 10", 'i', [$doctorId]);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) $leaveDays[] = $row;

        if ($checkDate !== '') {
            $dayName = date('l', strtotime($checkDate));
            $isPast = strtotime($checkDate) < strtotime(date('Y-m-d'));

            $stmt = db_query($conn, "SELECT id, reason FROM doctor_leaves WHERE doctor_id = ? AND leave_date = ?", 'is', [$doctorId, $checkDate]);
            $onLeave = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            $scheduleRow = $fullSchedule[$dayName] ?? null;

            if ($isPast) {
                $slotMessage = 'That date is in the past.';
            } elseif ($onLeave) {
                $slotMessage = 'On leave that day' . ($onLeave['reason'] ? ' — ' . $onLeave['reason'] : '') . '.';
            } elseif (!$scheduleRow) {
                $slotMessage = 'Dr. ' . $selectedDoctor['full_name'] . ' does not work on ' . $dayName . 's.';
            } else {
                $bookedTimes = [];
                $stmt = db_query($conn, "SELECT appointment_time FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status IN ('pending','confirmed')", 'is', [$doctorId, $checkDate]);
                $res = mysqli_stmt_get_result($stmt);
                while ($row = mysqli_fetch_assoc($res)) $bookedTimes[] = substr($row['appointment_time'], 0, 5);

                $start = new DateTime($checkDate . ' ' . $scheduleRow['start_time']);
                $end = new DateTime($checkDate . ' ' . $scheduleRow['end_time']);
                $interval = new DateInterval('PT' . (int) $scheduleRow['slot_duration_minutes'] . 'M');
                $now = new DateTime();
                $isToday = $checkDate === date('Y-m-d');
                $availableSlots = [];

                while ($start < $end) {
                    $label = $start->format('H:i');
                    if (!in_array($label, $bookedTimes, true) && !($isToday && $start <= $now)) {
                        $availableSlots[] = $label;
                    }
                    $start->add($interval);
                }
            }
        }
    }
}

$pageTitle = 'Doctor Availability';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="hams-main">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="hams-content">

        <div class="mb-4">
            <h2 class="h4 fw-bold mb-1">Doctor Availability</h2>
            <p class="text-muted mb-0">Check working hours and open slots before calling a patient back.</p>
        </div>

        <!-- Filters -->
        <form method="GET" class="hams-card p-3 mb-4">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label mb-1">Department</label>
                    <select class="form-select" name="department_id" onchange="this.form.submit()">
                        <option value="0">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" <?php echo $departmentFilter === (int)$dept['id'] ? 'selected' : ''; ?>><?php echo e($dept['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($doctorId > 0): ?>
                <div class="col-md-5">
                    <a href="<?php echo BASE_URL; ?>receptionist/doctor_availability.php?department_id=<?php echo $departmentFilter; ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Doctor List</a>
                </div>
                <?php endif; ?>
            </div>
        </form>

        <?php if (!$selectedDoctor): ?>
            <!-- ============ DOCTOR LIST ============ -->
            <div class="row g-3">
                <?php if (empty($doctorsList)): ?>
                    <div class="col-12"><div class="empty-state"><i class="bi bi-person-badge"></i><p class="mb-0">No doctors found for this department.</p></div></div>
                <?php else: ?>
                    <?php foreach ($doctorsList as $doc): ?>
                    <div class="col-md-6 col-lg-4">
                        <a href="?doctor_id=<?php echo $doc['id']; ?>&department_id=<?php echo $departmentFilter; ?>" class="text-decoration-none">
                            <div class="hams-card p-4 h-100">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <img src="<?php echo UPLOADS_URL . e($doc['profile_photo'] ?: 'default-avatar.png'); ?>" onerror="this.src='<?php echo ASSETS_URL; ?>images/default-avatar.png'" class="avatar-md" alt="">
                                    <div>
                                        <div class="fw-semibold text-body">Dr. <?php echo e($doc['full_name']); ?></div>
                                        <div class="text-muted small"><?php echo e($doc['department_name']); ?></div>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($daysOfWeek as $day):
                                        $working = in_array($day, $scheduleByDoctor[$doc['id']] ?? [], true);
                                    ?>
                                        <span class="badge <?php echo $working ? 'bg-secondary-soft text-secondary-accent' : 'bg-primary-soft text-muted'; ?>" style="opacity:<?php echo $working ? '1' : '.45'; ?>;"><?php echo $dayAbbrev[$day]; ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- ============ DOCTOR DETAIL ============ -->
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="hams-card p-4 text-center mb-3">
                        <img src="<?php echo UPLOADS_URL . e($selectedDoctor['profile_photo'] ?: 'default-avatar.png'); ?>" onerror="this.src='<?php echo ASSETS_URL; ?>images/default-avatar.png'" class="avatar-lg mb-3" alt="">
                        <h5 class="fw-bold mb-0">Dr. <?php echo e($selectedDoctor['full_name']); ?></h5>
                        <p class="text-muted small mb-2"><?php echo e($selectedDoctor['specialization'] ?: $selectedDoctor['department_name']); ?></p>
                        <span class="badge bg-primary-soft text-primary"><?php echo e($selectedDoctor['department_name']); ?></span>
                    </div>

                    <div class="hams-card p-4">
                        <h6 class="fw-bold mb-3">Upcoming Leave Days</h6>
                        <?php if (empty($leaveDays)): ?>
                            <p class="text-muted small mb-0">No upcoming leave days.</p>
                        <?php else: ?>
                            <?php foreach ($leaveDays as $leave): ?>
                            <div class="d-flex justify-content-between py-1 small border-bottom">
                                <span><?php echo format_date($leave['leave_date']); ?></span>
                                <span class="text-muted"><?php echo e($leave['reason'] ?: '—'); ?></span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="hams-card p-4 mb-3">
                        <h6 class="fw-bold mb-3">Weekly Hours</h6>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>Day</th><th>Hours</th><th>Slot Length</th></tr></thead>
                                <tbody>
                                    <?php foreach ($daysOfWeek as $day): $sched = $fullSchedule[$day] ?? null; ?>
                                    <tr class="<?php echo $sched ? '' : 'text-muted'; ?>">
                                        <td class="fw-semibold"><?php echo $day; ?></td>
                                        <td><?php echo $sched ? format_time($sched['start_time']) . ' – ' . format_time($sched['end_time']) : 'Not working'; ?></td>
                                        <td><?php echo $sched ? (int) $sched['slot_duration_minutes'] . ' min' : '—'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="hams-card p-4">
                        <h6 class="fw-bold mb-3">Check a Specific Date</h6>
                        <form method="GET" class="row g-2 align-items-end mb-3">
                            <input type="hidden" name="doctor_id" value="<?php echo $doctorId; ?>">
                            <input type="hidden" name="department_id" value="<?php echo $departmentFilter; ?>">
                            <div class="col-md-5">
                                <input type="date" class="form-control" name="check_date" value="<?php echo e($checkDate); ?>" min="<?php echo date('Y-m-d'); ?>" onchange="this.form.submit()">
                            </div>
                        </form>

                        <?php if ($checkDate !== ''): ?>
                            <?php if ($slotMessage !== ''): ?>
                                <div class="empty-state py-3"><i class="bi bi-calendar-x"></i><p class="mb-0"><?php echo e($slotMessage); ?></p></div>
                            <?php elseif (empty($availableSlots)): ?>
                                <div class="empty-state py-3"><i class="bi bi-calendar-x"></i><p class="mb-0">Fully booked that day.</p></div>
                            <?php else: ?>
                                <p class="small text-muted mb-2"><?php echo count($availableSlots); ?> open slot<?php echo count($availableSlots) === 1 ? '' : 's'; ?>:</p>
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <?php foreach ($availableSlots as $slot): ?>
                                        <span class="badge bg-secondary-soft text-secondary-accent"><?php echo date('h:i A', strtotime($slot)); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <a href="<?php echo BASE_URL; ?>receptionist/book_appointment.php?department_id=<?php echo $departmentFilter ?: $selectedDoctor['department_id']; ?>&doctor_id=<?php echo $doctorId; ?>&date=<?php echo e($checkDate); ?>" class="btn btn-primary btn-sm">
                                    <i class="bi bi-calendar-plus me-1"></i>Book This Doctor
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>