<?php
/**
 * =====================================================================
 * includes/sidebar.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * Role-aware left navigation. Renders a different menu depending on
 * current_role() (admin / doctor / receptionist / patient).
 *
 * REQUIRES: config/constants.php, includes/functions.php, includes/auth.php
 * already loaded, and the user to be logged in (require_role() already
 * called by the including page).
 * =====================================================================
 */

$__role = current_role();
$__user = current_user();
$__currentPage = basename($_SERVER['PHP_SELF'] ?? '');

/**
 * Build the nav-item list for the logged-in role.
 * Each item: ['icon' => 'bi-...', 'label' => '...', 'href' => '...', 'file' => 'dashboard.php']
 * A 'section' entry renders a section label instead of a link.
 */
function hams_nav_items(string $role): array
{
    switch ($role) {
        case 'admin':
            return [
                ['section' => 'Overview'],
                ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => 'admin/dashboard.php', 'file' => 'dashboard.php'],
                ['section' => 'Management'],
                ['icon' => 'bi-calendar2-check', 'label' => 'Appointments', 'href' => 'admin/appointments.php', 'file' => 'appointments.php'],
                ['icon' => 'bi-person-badge', 'label' => 'Doctors', 'href' => 'admin/doctors.php', 'file' => 'doctors.php'],
                ['icon' => 'bi-people', 'label' => 'Patients', 'href' => 'admin/patients.php', 'file' => 'patients.php'],
                ['icon' => 'bi-diagram-3', 'label' => 'Departments', 'href' => 'admin/departments.php', 'file' => 'departments.php'],
                ['icon' => 'bi-person-lines-fill', 'label' => 'Receptionists', 'href' => 'admin/receptionists.php', 'file' => 'receptionists.php'],
                ['icon' => 'bi-calendar-week', 'label' => 'Schedules', 'href' => 'admin/schedules.php', 'file' => 'schedules.php'],
                ['section' => 'Insights'],
                ['icon' => 'bi-bar-chart-line', 'label' => 'Reports', 'href' => 'admin/reports.php', 'file' => 'reports.php'],
                ['icon' => 'bi-clock-history', 'label' => 'Activity Log', 'href' => 'admin/activity_log.php', 'file' => 'activity_log.php'],
                ['section' => 'Account'],
                ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => 'admin/profile.php', 'file' => 'profile.php'],
                ['icon' => 'bi-gear', 'label' => 'Settings', 'href' => 'admin/settings.php', 'file' => 'settings.php'],
            ];

        case 'doctor':
            return [
                ['section' => 'Overview'],
                ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => 'doctor/dashboard.php', 'file' => 'dashboard.php'],
                ['section' => 'Appointments'],
                ['icon' => 'bi-calendar2-check', 'label' => "Today's Appointments", 'href' => 'doctor/appointments.php', 'file' => 'appointments.php'],
                ['icon' => 'bi-clock-history', 'label' => 'Appointment History', 'href' => 'doctor/history.php', 'file' => 'history.php'],
                ['icon' => 'bi-calendar-week', 'label' => 'My Schedule', 'href' => 'doctor/schedule.php', 'file' => 'schedule.php'],
                ['section' => 'Account'],
                ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => 'doctor/profile.php', 'file' => 'profile.php'],
            ];

        case 'receptionist':
            return [
                ['section' => 'Overview'],
                ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => 'receptionist/dashboard.php', 'file' => 'dashboard.php'],
                ['section' => 'Front Desk'],
                ['icon' => 'bi-person-plus', 'label' => 'Register Patient', 'href' => 'receptionist/register_patient.php', 'file' => 'register_patient.php'],
                ['icon' => 'bi-calendar-plus', 'label' => 'Book Appointment', 'href' => 'receptionist/book_appointment.php', 'file' => 'book_appointment.php'],
                ['icon' => 'bi-calendar2-check', 'label' => 'Manage Appointments', 'href' => 'receptionist/appointments.php', 'file' => 'appointments.php'],
                ['icon' => 'bi-calendar-week', 'label' => 'Doctor Availability', 'href' => 'receptionist/doctor_availability.php', 'file' => 'doctor_availability.php'],
                ['icon' => 'bi-search', 'label' => 'Search Patients', 'href' => 'receptionist/patients.php', 'file' => 'patients.php'],
                ['section' => 'Account'],
                ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => 'receptionist/profile.php', 'file' => 'profile.php'],
            ];

        case 'patient':
        default:
            return [
                ['section' => 'Overview'],
                ['icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'href' => 'patient/dashboard.php', 'file' => 'dashboard.php'],
                ['section' => 'Appointments'],
                ['icon' => 'bi-calendar-plus', 'label' => 'Book Appointment', 'href' => 'patient/book_appointment.php', 'file' => 'book_appointment.php'],
                ['icon' => 'bi-calendar2-check', 'label' => 'My Appointments', 'href' => 'patient/appointments.php', 'file' => 'appointments.php'],
                ['section' => 'Account'],
                ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => 'patient/notifications.php', 'file' => 'notifications.php'],
                ['icon' => 'bi-person-circle', 'label' => 'Profile', 'href' => 'patient/profile.php', 'file' => 'profile.php'],
            ];
    }
}

$__roleLabels = [
    'admin' => 'Administrator',
    'doctor' => 'Doctor',
    'receptionist' => 'Receptionist',
    'patient' => 'Patient',
];
?>
<aside class="hams-sidebar" id="hamsSidebar">
    <div class="brand">
        <div class="brand-mark"><i class="bi bi-heart-pulse-fill"></i></div>
        <div class="brand-text">
            <?php echo e(APP_NAME); ?>
            <small><?php echo e($__roleLabels[$__role] ?? 'User'); ?> Panel</small>
        </div>
    </div>

    <nav class="hams-nav">
        <?php foreach (hams_nav_items((string) $__role) as $item): ?>
            <?php if (isset($item['section'])): ?>
                <div class="nav-section-label"><?php echo e($item['section']); ?></div>
            <?php else: ?>
                <?php $isActive = ($__currentPage === $item['file']); ?>
                <a href="<?php echo BASE_URL . e($item['href']); ?>"
                   class="nav-link<?php echo $isActive ? ' active' : ''; ?>">
                    <i class="bi <?php echo e($item['icon']); ?>"></i>
                    <span><?php echo e($item['label']); ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <i class="bi bi-shield-check"></i> &copy; <?php echo date('Y'); ?> <?php echo e(APP_NAME); ?>
    </div>
</aside>

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>