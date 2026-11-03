<?php
/**
 * =====================================================================
 * admin/schedules.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * Manage a doctor's weekly recurring availability (doctor_schedules)
 * and specific leave/unavailable dates (doctor_leaves).
 * =====================================================================
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

$daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

/* =====================================================================
   POST HANDLERS
   ===================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['form_action'] ?? '';
    $doctorId = (int) ($_POST['doctor_id'] ?? 0);

    // -----------------------------------------------------------------
    // SAVE WEEKLY SCHEDULE (bulk — all 7 days submitted together)
    // -----------------------------------------------------------------
    if ($action === 'save_schedule' && $doctorId > 0) {
        // Replace this doctor's schedule wholesale — simpler and safer
        // than trying to diff/update 7 rows individually.
        db_query($conn, "DELETE FROM doctor_schedules WHERE doctor_id = ?", 'i', [$doctorId]);

        $savedDays = 0;
        foreach ($daysOfWeek as $day) {
            $enabled = isset($_POST['enabled'][$day]);
            if (!$enabled) continue;

            $start = sanitize($_POST['start_time'][$day] ?? '09:00');
            $end   = sanitize($_POST['end_time'][$day] ?? '17:00');
            $slot  = max(5, (int) ($_POST['slot_duration'][$day] ?? 30));

            if (strtotime($start) >= strtotime($end)) continue; // skip invalid ranges

            db_query(
                $conn,
                "INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration_minutes) VALUES (?, ?, ?, ?, ?)",
                'isssi',
                [$doctorId, $day, $start, $end, $slot]
            );
            $savedDays++;
        }

        log_activity($conn, current_user_id(), 'schedule_updated', "Updated weekly schedule for doctor #$doctorId ($savedDays day(s) active)");
        set_flash('success', 'Weekly schedule saved.');
        redirect('admin/schedules.php?doctor_id=' . $doctorId);
    }

    // -----------------------------------------------------------------
    // ADD LEAVE DAY
    // -----------------------------------------------------------------
    if ($action === 'add_leave' && $doctorId > 0) {
        $leaveDate = sanitize($_POST['leave_date'] ?? '');
        $reason = sanitize($_POST['reason'] ?? '');

        if ($leaveDate === '' || strtotime($leaveDate) < strtotime(date('Y-m-d'))) {
            set_flash('error', 'Please choose a valid, future leave date.');
        } else {
            $stmt = db_query($conn, "SELECT id FROM doctor_leaves WHERE doctor_id = ? AND leave_date = ?", 'is', [$doctorId, $leaveDate]);
            if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
                set_flash('error', 'That date is already marked as leave.');
            } else {
                db_query($conn, "INSERT INTO doctor_leaves (doctor_id, leave_date, reason) VALUES (?, ?, ?)", 'iss', [$doctorId, $leaveDate, $reason]);
                log_activity($conn, current_user_id(), 'leave_added', "Added leave day $leaveDate for doctor #$doctorId");
                set_flash('success', 'Leave day added.');
            }
        }
        redirect('admin/schedules.php?doctor_id=' . $doctorId);
    }

    // -----------------------------------------------------------------
    // DELETE LEAVE DAY
    // -----------------------------------------------------------------
    if ($action === 'delete_leave') {
        $leaveId = (int) ($_POST['leave_id'] ?? 0);
        db_query($conn, "DELETE FROM doctor_leaves WHERE id = ?", 'i', [$leaveId]);
        log_activity($conn, current_user_id(), 'leave_deleted', "Deleted leave #$leaveId");
        set_flash('success', 'Leave day removed.');
        redirect('admin/schedules.php?doctor_id=' . $doctorId);
    }
}

/* =====================================================================
   DATA FOR THE SELECTED DOCTOR
   ===================================================================== */
$doctorsList = [];
$res = mysqli_query($conn, "SELECT d.id, u.full_name, dept.name AS department_name FROM doctors d JOIN users u ON u.id = d.user_id JOIN departments dept ON dept.id = d.department_id WHERE d.status = 'active' ORDER BY u.full_name");
if ($res) while ($row = mysqli_fetch_assoc($res)) $doctorsList[] = $row;

$selectedDoctorId = (int) ($_GET['doctor_id'] ?? ($doctorsList[0]['id'] ?? 0));

$currentSchedule = []; // keyed by day name
if ($selectedDoctorId > 0) {
    $stmt = db_query($conn, "SELECT * FROM doctor_schedules WHERE doctor_id = ?", 'i', [$selectedDoctorId]);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $currentSchedule[$row['day_of_week']] = $row;
    }
}

$leaveDays = [];
if ($selectedDoctorId > 0) {
    $stmt = db_query($conn, "SELECT * FROM doctor_leaves WHERE doctor_id = ? AND leave_date >= CURDATE() ORDER BY leave_date ASC", 'i', [$selectedDoctorId]);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $leaveDays[] = $row;
    }
}

$selectedDoctorName = '';
foreach ($doctorsList as $d) {
    if ((int) $d['id'] === $selectedDoctorId) { $selectedDoctorName = $d['full_name']; break; }
}

$pageTitle = 'Doctor Schedules';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="hams-main">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="hams-content">

        <div class="mb-4">
            <h2 class="h4 fw-bold mb-1">Doctor Schedules</h2>
            <p class="text-muted mb-0">Set weekly working hours and mark specific leave days.</p>
        </div>

        <!-- Doctor selector -->
        <form method="GET" class="hams-card p-3 mb-4">
            <div class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="form-label mb-1">Select Doctor</label>
                    <select class="form-select" name="doctor_id" onchange="this.form.submit()">
                        <?php foreach ($doctorsList as $doc): ?>
                            <option value="<?php echo $doc['id']; ?>" <?php echo $selectedDoctorId === (int)$doc['id'] ? 'selected' : ''; ?>>
                                Dr. <?php echo e($doc['full_name']); ?> — <?php echo e($doc['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <noscript><button type="submit" class="btn btn-primary w-100">Load Schedule</button></noscript>
                </div>
            </div>
        </form>

        <?php if (empty($doctorsList)): ?>
            <div class="empty-state"><i class="bi bi-person-badge"></i><p>No active doctors found. Add a doctor first.</p></div>
        <?php else: ?>

        <div class="row g-4">
            <!-- Weekly schedule -->
            <div class="col-lg-8">
                <div class="hams-card p-4">
                    <h6 class="fw-bold mb-3">Weekly Hours — Dr. <?php echo e($selectedDoctorName); ?></h6>
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="form_action" value="save_schedule">
                        <input type="hidden" name="doctor_id" value="<?php echo $selectedDoctorId; ?>">

                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th style="width:110px;">Day</th>
                                        <th>Working</th>
                                        <th>Start</th>
                                        <th>End</th>
                                        <th>Slot (min)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($daysOfWeek as $day): $sched = $currentSchedule[$day] ?? null; ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo $day; ?></td>
                                        <td>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="enabled[<?php echo $day; ?>]" <?php echo $sched ? 'checked' : ''; ?>>
                                            </div>
                                        </td>
                                        <td><input type="time" class="form-control form-control-sm" name="start_time[<?php echo $day; ?>]" value="<?php echo e($sched['start_time'] ?? '09:00'); ?>"></td>
                                        <td><input type="time" class="form-control form-control-sm" name="end_time[<?php echo $day; ?>]" value="<?php echo e($sched['end_time'] ?? '17:00'); ?>"></td>
                                        <td style="width:110px;"><input type="number" class="form-control form-control-sm" name="slot_duration[<?php echo $day; ?>]" min="5" max="180" step="5" value="<?php echo (int) ($sched['slot_duration_minutes'] ?? 30); ?>"></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Weekly Schedule</button>
                    </form>
                </div>
            </div>

            <!-- Leave days -->
            <div class="col-lg-4">
                <div class="hams-card p-4 mb-3">
                    <h6 class="fw-bold mb-3">Add Leave Day</h6>
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="form_action" value="add_leave">
                        <input type="hidden" name="doctor_id" value="<?php echo $selectedDoctorId; ?>">
                        <div class="mb-2">
                            <label class="form-label mb-1">Date</label>
                            <input type="date" class="form-control" name="leave_date" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label mb-1">Reason <span class="text-muted small">(optional)</span></label>
                            <input type="text" class="form-control" name="reason" placeholder="e.g. Conference, personal leave">
                        </div>
                        <button type="submit" class="btn btn-secondary w-100"><i class="bi bi-calendar-plus me-1"></i>Add Leave Day</button>
                    </form>
                </div>

                <div class="hams-card p-4">
                    <h6 class="fw-bold mb-3">Upcoming Leave Days</h6>
                    <?php if (empty($leaveDays)): ?>
                        <p class="text-muted small mb-0">No upcoming leave days scheduled.</p>
                    <?php else: ?>
                        <?php foreach ($leaveDays as $leave): ?>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <div>
                                <div class="fw-semibold small"><?php echo format_date($leave['leave_date']); ?></div>
                                <div class="text-muted small"><?php echo e($leave['reason'] ?: 'No reason given'); ?></div>
                            </div>
                            <form method="POST">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="form_action" value="delete_leave">
                                <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                                <input type="hidden" name="doctor_id" value="<?php echo $selectedDoctorId; ?>">
                                <button type="submit" class="icon-btn text-danger" title="Remove"><i class="bi bi-x-lg"></i></button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php endif; ?>

    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>