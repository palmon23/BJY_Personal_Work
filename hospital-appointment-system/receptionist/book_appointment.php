<?php
/**
 * =====================================================================
 * patient/book_appointment.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * Patient self-service booking: department -> doctor -> date -> an
 * available time slot computed from the doctor's schedule, leave days,
 * and existing bookings. Same slot logic as the receptionist's booking
 * flow, scoped so a patient can only book for themselves.
 * =====================================================================
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('patient');

$patientId = (int) current_user()['patient_id'];

/* =====================================================================
   BOOK (POST)
   ===================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'book') {
    require_csrf();

    $departmentId = (int) ($_POST['department_id'] ?? 0);
    $doctorId = (int) ($_POST['doctor_id'] ?? 0);
    $date = sanitize($_POST['appointment_date'] ?? '');
    $time = sanitize($_POST['appointment_time'] ?? '');
    $reason = sanitize($_POST['reason'] ?? '');
    $rescheduleId = (int) ($_POST['reschedule_id'] ?? 0);

    $errors = [];
    if ($departmentId <= 0 || $doctorId <= 0 || $date === '' || $time === '') {
        $errors[] = 'Please complete every step before booking.';
    }

    // If this is a reschedule, confirm the old appointment actually
    // belongs to this patient and is still active before touching it.
    $oldAppt = null;
    if ($rescheduleId > 0) {
        $stmt = db_query($conn, "SELECT * FROM appointments WHERE id = ? AND patient_id = ? AND status IN ('pending','confirmed')", 'ii', [$rescheduleId, $patientId]);
        $oldAppt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if (!$oldAppt) {
            $rescheduleId = 0; // ignore a stale/invalid reschedule reference rather than blocking the new booking
        }
    }

    if (empty($errors)) {
        $stmt = db_query(
            $conn,
            "SELECT COUNT(*) c FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status IN ('pending','confirmed')",
            'iss',
            [$doctorId, $date, $time]
        );
        $taken = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
        if ($taken > 0) {
            $errors[] = 'Sorry, that time slot was just taken. Please choose another.';
        }
    }

    if (empty($errors)) {
        db_query(
            $conn,
            "INSERT INTO appointments (patient_id, doctor_id, department_id, appointment_date, appointment_time, reason, status, booked_by, booked_by_user_id) VALUES (?, ?, ?, ?, ?, ?, 'pending', 'patient', ?)",
            'iiisssi',
            [$patientId, $doctorId, $departmentId, $date, $time, $reason, current_user_id()]
        );
        $newApptId = mysqli_insert_id($conn);

        $stmt = db_query($conn, "SELECT u.id FROM doctors d JOIN users u ON u.id = d.user_id WHERE d.id = ?", 'i', [$doctorId]);
        $doctorUser = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($oldAppt) {
            db_query($conn, "UPDATE appointments SET status='cancelled', cancelled_reason='Rescheduled by patient' WHERE id=?", 'i', [$rescheduleId]);
            add_notification($conn, current_user_id(), 'Appointment Rescheduled', 'Your appointment has been moved to ' . format_date($date) . ' at ' . format_time($time) . '.');
            add_notification($conn, (int) $doctorUser['id'], 'Appointment Rescheduled', current_user()['full_name'] . ' rescheduled their appointment to ' . format_date($date) . '.');
            log_activity($conn, current_user_id(), 'appointment_rescheduled', "Patient rescheduled appointment #$rescheduleId to new appointment #$newApptId");
            set_flash('success', 'Your appointment has been rescheduled and is awaiting confirmation.');
        } else {
            add_notification($conn, current_user_id(), 'Appointment Requested', 'Your appointment on ' . format_date($date) . ' at ' . format_time($time) . ' has been booked and is awaiting confirmation.');
            add_notification($conn, (int) $doctorUser['id'], 'New Appointment Request', current_user()['full_name'] . ' requested an appointment on ' . format_date($date) . '.');
            log_activity($conn, current_user_id(), 'appointment_booked', "Patient booked appointment #$newApptId");
            set_flash('success', 'Your appointment has been booked and is awaiting confirmation.');
        }

        redirect('patient/appointments.php');
    } else {
        set_flash('error', implode(' ', $errors));
        redirect('patient/book_appointment.php?department_id=' . $departmentId . '&doctor_id=' . $doctorId . '&date=' . $date . ($rescheduleId ? '&reschedule_id=' . $rescheduleId : ''));
    }
}

/* =====================================================================
   STEP STATE (from GET)
   ===================================================================== */
$departmentId = (int) ($_GET['department_id'] ?? 0);
$doctorId = (int) ($_GET['doctor_id'] ?? 0);
$date = sanitize($_GET['date'] ?? '');
$rescheduleId = (int) ($_GET['reschedule_id'] ?? 0);

$departments = [];
$res = mysqli_query($conn, "SELECT id, name, description, icon FROM departments WHERE status = 'active' ORDER BY name");
if ($res) while ($row = mysqli_fetch_assoc($res)) $departments[] = $row;

$doctorsList = [];
$selectedDoctor = null;
if ($departmentId > 0) {
    $stmt = db_query($conn, "SELECT d.*, u.full_name, u.profile_photo FROM doctors d JOIN users u ON u.id = d.user_id WHERE d.department_id = ? AND d.status = 'active' ORDER BY u.full_name", 'i', [$departmentId]);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $doctorsList[] = $row;
        if ((int) $row['id'] === $doctorId) $selectedDoctor = $row;
    }
}

$availableSlots = [];
$slotMessage = '';
if ($doctorId > 0 && $date !== '') {
    $dayName = date('l', strtotime($date));
    $isPast = strtotime($date) < strtotime(date('Y-m-d'));

    $stmt = db_query($conn, "SELECT id FROM doctor_leaves WHERE doctor_id = ? AND leave_date = ?", 'is', [$doctorId, $date]);
    $onLeave = mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0;

    $stmt = db_query($conn, "SELECT * FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ?", 'is', [$doctorId, $dayName]);
    $scheduleRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($isPast) {
        $slotMessage = 'That date is in the past.';
    } elseif ($onLeave) {
        $slotMessage = 'The doctor is on leave that day.';
    } elseif (!$scheduleRow) {
        $slotMessage = 'The doctor does not work on ' . $dayName . 's.';
    } else {
        $bookedTimes = [];
        $stmt = db_query($conn, "SELECT appointment_time FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status IN ('pending','confirmed')", 'is', [$doctorId, $date]);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) $bookedTimes[] = substr($row['appointment_time'], 0, 5);

        $start = new DateTime($date . ' ' . $scheduleRow['start_time']);
        $end = new DateTime($date . ' ' . $scheduleRow['end_time']);
        $interval = new DateInterval('PT' . (int) $scheduleRow['slot_duration_minutes'] . 'M');
        $now = new DateTime();
        $isToday = $date === date('Y-m-d');

        while ($start < $end) {
            $slotLabel = $start->format('H:i');
            if (!in_array($slotLabel, $bookedTimes, true) && !($isToday && $start <= $now)) {
                $availableSlots[] = $slotLabel;
            }
            $start->add($interval);
        }

        if (empty($availableSlots)) {
            $slotMessage = 'No open slots left for this day — try another date.';
        }
    }
}

$pageTitle = 'Book Appointment';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="hams-main">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="hams-content">

        <div class="mb-4">
            <h2 class="h4 fw-bold mb-1">Book an Appointment</h2>
            <p class="text-muted mb-0">Choose a department, doctor, and time that works for you.</p>
        </div>

        <?php if ($rescheduleId > 0): ?>
            <div class="alert py-2 mb-3" style="background:var(--hams-primary-light); border:1px solid var(--hams-primary);">
                <i class="bi bi-arrow-repeat me-1"></i>Rescheduling your existing appointment — picking a new time will cancel the old one automatically.
            </div>
        <?php endif; ?>

        <!-- ============ STEP 1: DEPARTMENT ============ -->
        <div class="hams-card p-4 mb-3">
            <h6 class="fw-bold mb-3"><span class="badge bg-primary-soft text-primary me-2">1</span>Choose a Department</h6>
            <div class="row g-2">
                <?php foreach ($departments as $dept): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <a href="?department_id=<?php echo $dept['id']; ?><?php echo $rescheduleId ? '&reschedule_id=' . $rescheduleId : ''; ?>" class="text-decoration-none">
                        <div class="p-3 rounded-3 text-center h-100 <?php echo $departmentId === (int)$dept['id'] ? 'border border-2' : 'border'; ?>"
                             style="<?php echo $departmentId === (int)$dept['id'] ? 'border-color:var(--hams-primary)!important; background:var(--hams-primary-light);' : 'border-color:var(--hams-border);'; ?>">
                            <div class="mb-2" style="font-size:1.4rem; color:var(--hams-primary-darker);"><i class="bi <?php echo e($dept['icon'] ?: 'bi-hospital'); ?>"></i></div>
                            <div class="fw-semibold small text-body"><?php echo e($dept['name']); ?></div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($departmentId > 0): ?>
        <!-- ============ STEP 2: DOCTOR ============ -->
        <div class="hams-card p-4 mb-3">
            <h6 class="fw-bold mb-3"><span class="badge bg-primary-soft text-primary me-2">2</span>Choose a Doctor</h6>
            <?php if (empty($doctorsList)): ?>
                <p class="text-muted small mb-0">No doctors currently available in this department.</p>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($doctorsList as $doc): ?>
                    <div class="col-md-6 col-lg-4">
                        <a href="?department_id=<?php echo $departmentId; ?>&doctor_id=<?php echo $doc['id']; ?><?php echo $rescheduleId ? '&reschedule_id=' . $rescheduleId : ''; ?>" class="text-decoration-none">
                            <div class="p-3 rounded-3 h-100 <?php echo $doctorId === (int)$doc['id'] ? 'border border-2' : 'border'; ?>"
                                 style="<?php echo $doctorId === (int)$doc['id'] ? 'border-color:var(--hams-primary)!important; background:var(--hams-primary-light);' : 'border-color:var(--hams-border);'; ?>">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <img src="<?php echo UPLOADS_URL . e($doc['profile_photo'] ?: 'default-avatar.png'); ?>" onerror="this.src='<?php echo ASSETS_URL; ?>images/default-avatar.png'" class="avatar-sm" alt="">
                                    <div class="text-body">
                                        <div class="fw-semibold small">Dr. <?php echo e($doc['full_name']); ?></div>
                                        <div class="text-muted" style="font-size:.76rem;"><?php echo e($doc['specialization'] ?: '—'); ?></div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between text-muted" style="font-size:.76rem;">
                                    <span><?php echo (int) $doc['experience_years']; ?> yrs experience</span>
                                    <span class="fw-semibold text-body">$<?php echo number_format((float) $doc['consultation_fee'], 2); ?></span>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($selectedDoctor): ?>
        <!-- ============ STEP 3: DATE & TIME ============ -->
        <div class="hams-card p-4 mb-3">
            <h6 class="fw-bold mb-3"><span class="badge bg-primary-soft text-primary me-2">3</span>Choose Date &amp; Time</h6>
            <form method="GET" class="mb-3">
                <input type="hidden" name="department_id" value="<?php echo $departmentId; ?>">
                <input type="hidden" name="doctor_id" value="<?php echo $doctorId; ?>">
                <?php if ($rescheduleId): ?><input type="hidden" name="reschedule_id" value="<?php echo $rescheduleId; ?>"><?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <input type="date" class="form-control" name="date" value="<?php echo e($date); ?>" min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+60 days')); ?>" onchange="this.form.submit()">
                    </div>
                </div>
            </form>

            <?php if ($date !== ''): ?>
                <?php if (!empty($slotMessage)): ?>
                    <div class="empty-state py-3"><i class="bi bi-calendar-x"></i><p class="mb-0"><?php echo e($slotMessage); ?></p></div>
                <?php else: ?>
                    <form method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="form_action" value="book">
                        <input type="hidden" name="department_id" value="<?php echo $departmentId; ?>">
                        <input type="hidden" name="doctor_id" value="<?php echo $doctorId; ?>">
                        <input type="hidden" name="appointment_date" value="<?php echo e($date); ?>">
                        <?php if ($rescheduleId): ?><input type="hidden" name="reschedule_id" value="<?php echo $rescheduleId; ?>"><?php endif; ?>

                        <label class="form-label">Available Time Slots</label>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <?php foreach ($availableSlots as $slot): ?>
                                <input type="radio" class="btn-check" name="appointment_time" value="<?php echo $slot; ?>" id="slot_<?php echo $slot; ?>" required>
                                <label class="btn btn-outline-primary btn-sm" for="slot_<?php echo $slot; ?>"><?php echo date('h:i A', strtotime($slot)); ?></label>
                            <?php endforeach; ?>
                        </div>

                        <label class="form-label">Reason for Visit <span class="text-muted small">(optional)</span></label>
                        <textarea class="form-control mb-3" name="reason" rows="2" placeholder="Briefly describe your symptoms or reason for the visit..."></textarea>

                        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-calendar-check me-1"></i>Confirm Booking</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>