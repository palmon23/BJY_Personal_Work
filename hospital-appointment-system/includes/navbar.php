<?php
/**
 * =====================================================================
 * includes/navbar.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * Top bar: sidebar toggle, page title, notification bell (live from DB),
 * theme toggle switch, and user profile dropdown with logout.
 *
 * REQUIRES: $conn (config/db.php), includes/functions.php, includes/auth.php
 * already loaded, and the user to be logged in.
 * Optional: $pageTitle (string) set by the including page before header.php.
 * =====================================================================
 */

$__navUser = current_user();
$__unreadCount = isset($conn) ? unread_notification_count($conn, $__navUser['id']) : 0;

// Fetch the 5 most recent notifications for the bell dropdown.
$__recentNotifs = [];
if (isset($conn)) {
    $stmt = db_query(
        $conn,
        "SELECT id, title, message, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5",
        'i',
        [$__navUser['id']]
    );
    if ($stmt) {
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $__recentNotifs[] = $row;
        }
    }
}

$__roleLabels = [
    'admin' => 'Administrator',
    'doctor' => 'Doctor',
    'receptionist' => 'Receptionist',
    'patient' => 'Patient',
];
?>
<header class="hams-topbar">
    <div class="d-flex align-items-center gap-3">
        <button type="button" class="sidebar-toggle-btn" aria-label="Toggle sidebar">
            <i class="bi bi-list fs-5"></i>
        </button>
        <div class="d-none d-md-block">
            <h1 class="h5 mb-0 font-heading"><?php echo e($pageTitle ?? 'Dashboard'); ?></h1>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2">

        <!-- Theme toggle -->
        <button type="button" class="theme-toggle" aria-label="Toggle dark mode" title="Toggle light / dark mode">
            <span class="knob"><i class="bi bi-sun-fill"></i></span>
        </button>

        <!-- Notifications -->
        <div class="dropdown">
            <button class="icon-btn dropdown-toggle-no-caret" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                <i class="bi bi-bell fs-6"></i>
                <?php if ($__unreadCount > 0): ?>
                    <span class="notif-dot" id="notifCountBadge"></span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end notif-dropdown shadow-lg">
                <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                    <strong class="small">Notifications</strong>
                    <?php if ($__unreadCount > 0): ?>
                        <button type="button" id="markAllNotifsRead"
                                data-url="<?php echo BASE_URL . e($__navUser['role']); ?>/mark_notifications_read.php"
                                class="btn btn-link btn-sm p-0 text-decoration-none">Mark all read</button>
                    <?php endif; ?>
                </div>

                <?php if (empty($__recentNotifs)): ?>
                    <div class="empty-state py-4">
                        <i class="bi bi-bell-slash"></i>
                        <p class="mb-0 small">No notifications yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($__recentNotifs as $notif): ?>
                        <a href="<?php echo $notif['link'] ? BASE_URL . e($notif['link']) : '#'; ?>"
                           class="notif-item<?php echo !$notif['is_read'] ? ' unread' : ''; ?>">
                            <div class="d-flex justify-content-between">
                                <span class="fw-semibold small"><?php echo e($notif['title']); ?></span>
                                <span class="text-muted" style="font-size:.7rem;"><?php echo time_ago($notif['created_at']); ?></span>
                            </div>
                            <div class="text-muted small mt-1"><?php echo e($notif['message']); ?></div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- User menu -->
        <div class="dropdown">
            <button class="btn d-flex align-items-center gap-2 dropdown-toggle-no-caret" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="<?php echo UPLOADS_URL . e($__navUser['profile_photo']); ?>"
                     onerror="this.src='<?php echo ASSETS_URL; ?>images/default-avatar.png'"
                     alt="<?php echo e($__navUser['full_name']); ?>" class="avatar-sm">
                <span class="d-none d-lg-flex flex-column text-start lh-sm">
                    <span class="fw-semibold small"><?php echo e($__navUser['full_name']); ?></span>
                    <span class="text-muted" style="font-size:.72rem;"><?php echo e($__roleLabels[$__navUser['role']] ?? ''); ?></span>
                </span>
                <i class="bi bi-chevron-down small text-muted d-none d-md-inline"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-lg">
                <li><a class="dropdown-item" href="<?php echo BASE_URL . e($__navUser['role']); ?>/profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                <?php if ($__navUser['role'] === 'admin'): ?>
                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>admin/settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="#" onclick="event.preventDefault(); document.getElementById('logoutForm').submit();">
                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>

<form id="logoutForm" action="<?php echo BASE_URL; ?>logout.php" method="POST" class="d-none" data-no-spinner>
    <?php csrf_field(); ?>
</form>