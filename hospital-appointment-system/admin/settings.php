<?php
/**
 * =====================================================================
 * admin/settings.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * Site-wide configuration backed by the `settings` key/value table:
 * site identity, contact info, and appointment booking defaults.
 * =====================================================================
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

// The set of keys this page manages, with sane fallback defaults.
$settingKeys = [
    'site_name'                => APP_FULL_NAME,
    'site_email'                => 'info@hams.test',
    'site_phone'                => '',
    'site_address'              => '',
    'appointment_slot_minutes'  => '30',
    'booking_lead_days'         => '30',
    'theme_default'             => 'light',
];

/* =====================================================================
   POST HANDLER
   ===================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $siteName   = sanitize($_POST['site_name'] ?? '');
    $siteEmail  = sanitize($_POST['site_email'] ?? '');
    $sitePhone  = sanitize($_POST['site_phone'] ?? '');
    $siteAddress = sanitize($_POST['site_address'] ?? '');
    $slotMinutes = max(5, (int) ($_POST['appointment_slot_minutes'] ?? 30));
    $leadDays    = max(1, (int) ($_POST['booking_lead_days'] ?? 30));
    $themeDefault = in_array($_POST['theme_default'] ?? 'light', ['light', 'dark'], true) ? $_POST['theme_default'] : 'light';

    $errors = [];
    if ($siteName === '') $errors[] = 'Please enter a site name.';
    if (!is_valid_email($siteEmail)) $errors[] = 'Please enter a valid contact email.';

    if (empty($errors)) {
        $updates = [
            'site_name' => $siteName,
            'site_email' => $siteEmail,
            'site_phone' => $sitePhone,
            'site_address' => $siteAddress,
            'appointment_slot_minutes' => (string) $slotMinutes,
            'booking_lead_days' => (string) $leadDays,
            'theme_default' => $themeDefault,
        ];

        foreach ($updates as $key => $value) {
            // Upsert: update if the key exists, insert otherwise.
            $stmt = db_query($conn, "SELECT id FROM settings WHERE setting_key = ?", 's', [$key]);
            if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) {
                db_query($conn, "UPDATE settings SET setting_value = ? WHERE setting_key = ?", 'ss', [$value, $key]);
            } else {
                db_query($conn, "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)", 'ss', [$key, $value]);
            }
        }

        log_activity($conn, current_user_id(), 'settings_updated', 'Administrator updated site settings.');
        set_flash('success', 'Settings saved successfully.');
    } else {
        set_flash('error', implode(' ', $errors));
    }
    redirect('admin/settings.php');
}

/* =====================================================================
   LOAD CURRENT SETTINGS (fall back to defaults for any missing key)
   ===================================================================== */
$current = $settingKeys;
$res = mysqli_query($conn, "SELECT setting_key, setting_value FROM settings");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        if (array_key_exists($row['setting_key'], $current)) {
            $current[$row['setting_key']] = $row['setting_value'];
        }
    }
}

$pageTitle = 'Settings';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="hams-main">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="hams-content">

        <div class="mb-4">
            <h2 class="h4 fw-bold mb-1">Settings</h2>
            <p class="text-muted mb-0">Configure site-wide details used across the patient portal and notifications.</p>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <form method="POST" class="needs-validation" novalidate>
                    <?php csrf_field(); ?>

                    <div class="hams-card p-4 mb-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-building me-2"></i>Site Identity</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Site / Hospital Name</label>
                                <input type="text" class="form-control" name="site_name" required value="<?php echo e($current['site_name']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact Email</label>
                                <input type="email" class="form-control" name="site_email" required value="<?php echo e($current['site_email']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact Phone</label>
                                <input type="tel" class="form-control" name="site_phone" value="<?php echo e($current['site_phone']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control" name="site_address" value="<?php echo e($current['site_address']); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="hams-card p-4 mb-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-calendar2-week me-2"></i>Booking Defaults</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Default Appointment Slot Length</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="appointment_slot_minutes" min="5" max="180" step="5" value="<?php echo e($current['appointment_slot_minutes']); ?>">
                                    <span class="input-group-text">minutes</span>
                                </div>
                                <div class="form-text">Used as the default when a doctor's schedule doesn't specify its own slot length.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Booking Lead Time</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="booking_lead_days" min="1" max="365" value="<?php echo e($current['booking_lead_days']); ?>">
                                    <span class="input-group-text">days ahead</span>
                                </div>
                                <div class="form-text">How far in advance patients are allowed to book.</div>
                            </div>
                        </div>
                    </div>

                    <div class="hams-card p-4 mb-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-palette me-2"></i>Appearance</h6>
                        <label class="form-label">Default Theme for New Visitors</label>
                        <select class="form-select" name="theme_default" style="max-width:260px;">
                            <option value="light" <?php echo $current['theme_default'] === 'light' ? 'selected' : ''; ?>>Light Mode</option>
                            <option value="dark" <?php echo $current['theme_default'] === 'dark' ? 'selected' : ''; ?>>Dark Mode</option>
                        </select>
                        <div class="form-text">Individual users can still switch modes themselves — this only sets the first-visit default.</div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-lg me-1"></i>Save Settings</button>
                </form>
            </div>

            <div class="col-lg-4">
                <div class="hams-card p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>System Info</h6>
                    <ul class="list-unstyled small mb-0">
                        <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">App Version</span><span class="fw-semibold"><?php echo e(APP_VERSION); ?></span></li>
                        <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">PHP Version</span><span class="fw-semibold"><?php echo e(PHP_VERSION); ?></span></li>
                        <li class="d-flex justify-content-between py-2"><span class="text-muted">Server Time</span><span class="fw-semibold"><?php echo date('M j, Y g:i A'); ?></span></li>
                    </ul>
                </div>
            </div>
        </div>

    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>