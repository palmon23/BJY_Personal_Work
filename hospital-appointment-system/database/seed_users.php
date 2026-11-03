<?php
/**
 * =====================================================================
 * database/seed_users.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * ONE-TIME script to create demo login accounts (admin, doctor,
 * receptionist, patient) with correctly hashed passwords.
 *
 * HOW TO RUN:
 *   1. Import database/hams_db.sql first (via phpMyAdmin or CLI).
 *   2. Open this file in your browser, e.g.:
 *          http://localhost/hospital-appointment-system/database/seed_users.php
 *      (or run it via CLI: php seed_users.php)
 *   3. Note the printed demo credentials.
 *   4. DELETE this file afterwards — it should never remain on a
 *      production server.
 *
 * This script is SAFE TO RUN MULTIPLE TIMES: it checks for existing
 * demo accounts by email before inserting anything.
 * =====================================================================
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: text/html; charset=utf-8');

const DEMO_PASSWORD = 'Password@123';

/**
 * Insert a user row if it doesn't already exist (matched by email).
 * Returns the users.id (existing or newly created).
 */
function seed_user(mysqli $conn, string $role, string $fullName, string $email, string $phone, ?string $gender = null): int
{
    $stmt = db_query($conn, "SELECT id FROM users WHERE email = ?", 's', [$email]);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        return (int) $row['id'];
    }

    $hash = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);

    $stmt = db_query(
        $conn,
        "INSERT INTO users (role, full_name, email, password, phone, gender, status) VALUES (?, ?, ?, ?, ?, ?, 'active')",
        'ssssss',
        [$role, $fullName, $email, $hash, $phone, $gender]
    );

    return (int) mysqli_insert_id($conn);
}

$report = [];

// -----------------------------------------------------------------
// 1. Admin
// -----------------------------------------------------------------
$adminId = seed_user($conn, 'admin', 'System Administrator', 'admin@hams.test', '+10000000000', null);
$report[] = ['Administrator', 'admin@hams.test', DEMO_PASSWORD];

// -----------------------------------------------------------------
// 2. Receptionist
// -----------------------------------------------------------------
$receptionistId = seed_user($conn, 'receptionist', 'Front Desk Officer', 'reception@hams.test', '+10000000001', null);
$report[] = ['Receptionist', 'reception@hams.test', DEMO_PASSWORD];

// -----------------------------------------------------------------
// 3. Doctor (+ doctor profile + weekly schedule)
// -----------------------------------------------------------------
$doctorUserId = seed_user($conn, 'doctor', 'Dr. Sarah Johnson', 'doctor@hams.test', '+10000000002', 'female');
$report[] = ['Doctor', 'doctor@hams.test', DEMO_PASSWORD];

$stmt = db_query($conn, "SELECT id FROM doctors WHERE user_id = ?", 'i', [$doctorUserId]);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    // Department 1 = Cardiology (from hams_db.sql seed data)
    db_query(
        $conn,
        "INSERT INTO doctors (user_id, department_id, specialization, qualification, experience_years, consultation_fee, bio, license_number)
         VALUES (?, 1, 'Cardiologist', 'MBBS, MD (Cardiology)', 12, 50.00, 'Experienced cardiologist specializing in preventive heart care.', 'LIC-CARD-1001')",
        'i',
        [$doctorUserId]
    );
    $doctorId = (int) mysqli_insert_id($conn);

    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    foreach ($days as $day) {
        db_query(
            $conn,
            "INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration_minutes) VALUES (?, ?, '09:00:00', '17:00:00', 30)",
            'is',
            [$doctorId, $day]
        );
    }
} else {
    $row = mysqli_fetch_assoc($result);
    $doctorId = (int) $row['id'];
}

// -----------------------------------------------------------------
// 4. Patient (+ patient profile)
// -----------------------------------------------------------------
$patientUserId = seed_user($conn, 'patient', 'John Doe', 'patient@hams.test', '+10000000003', 'male');
$report[] = ['Patient', 'patient@hams.test', DEMO_PASSWORD];

$stmt = db_query($conn, "SELECT id FROM patients WHERE user_id = ?", 'i', [$patientUserId]);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    db_query(
        $conn,
        "INSERT INTO patients (user_id, blood_group, emergency_contact_name, emergency_contact_phone) VALUES (?, 'O+', 'Jane Doe', '+10000000004')",
        'i',
        [$patientUserId]
    );
}

// -----------------------------------------------------------------
// Report
// -----------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>HAMS &mdash; Demo Accounts Seeded</title>
<style>
    body { font-family: Segoe UI, Arial, sans-serif; background:#f1f5f9; padding:40px; color:#0f172a; }
    .card { max-width:640px; margin:0 auto; background:#fff; border-radius:12px; padding:32px; box-shadow:0 4px 16px rgba(0,0,0,.08); }
    h1 { color:#0284c7; font-size:22px; }
    table { width:100%; border-collapse:collapse; margin-top:16px; }
    th, td { text-align:left; padding:10px 12px; border-bottom:1px solid #e2e8f0; font-size:14px; }
    th { background:#f0f9ff; color:#0369a1; }
    .warn { margin-top:24px; padding:14px 16px; background:#fef3c7; border-left:4px solid #f59e0b; border-radius:6px; font-size:14px; }
    code { background:#f1f5f9; padding:2px 6px; border-radius:4px; }
</style>
</head>
<body>
<div class="card">
    <h1>&#10003; Demo accounts are ready</h1>
    <p>The following accounts now exist in <code>hams_db</code>. All demo accounts share the same password.</p>
    <table>
        <tr><th>Role</th><th>Email</th><th>Password</th></tr>
        <?php foreach ($report as $r): ?>
        <tr><td><?php echo htmlspecialchars($r[0]); ?></td><td><?php echo htmlspecialchars($r[1]); ?></td><td><?php echo htmlspecialchars($r[2]); ?></td></tr>
        <?php endforeach; ?>
    </table>
    <div class="warn">
        <strong>Security reminder:</strong> Delete this file (<code>database/seed_users.php</code>) now that the accounts are created,
        and change these passwords before using the system with real patient data.
    </div>
</div>
</body>
</html>