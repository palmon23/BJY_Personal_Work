<?php
/**
 * =====================================================================
 * includes/header.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * Shared <head> + opening <body>/<wrapper> markup for every page.
 *
 * REQUIRES the including page to have already loaded, in this order:
 *   require_once __DIR__ . '/../config/constants.php';
 *   require_once __DIR__ . '/../config/db.php';
 *   require_once __DIR__ . '/../includes/functions.php';
 *   require_once __DIR__ . '/../includes/auth.php';
 *
 * OPTIONAL variables the including page may set before requiring this file:
 *   $pageTitle      (string) — shown in <title> and browser tab
 *   $bodyClass      (string) — extra class(es) on <body>, e.g. 'auth-page'
 *   $hideChrome     (bool)   — true to skip sidebar/topbar (auth pages, errors)
 * =====================================================================
 */

$pageTitle  = $pageTitle  ?? APP_NAME;
$bodyClass  = $bodyClass  ?? '';
$hideChrome = $hideChrome ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<meta name="description" content="<?php echo e(APP_FULL_NAME); ?> — book, manage and track hospital appointments online.">
<title><?php echo e($pageTitle); ?> | <?php echo e(APP_NAME); ?></title>

<link rel="icon" type="image/png" href="<?php echo ASSETS_URL; ?>images/favicon.png">

<!-- Prevent flash-of-wrong-theme: apply saved theme before CSS paints -->
<script>
    (function () {
        try {
            var saved = localStorage.getItem('hams_theme');
            var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-bs-theme', saved || (prefersDark ? 'dark' : 'light'));
        } catch (e) { /* localStorage unavailable — default to light */ }
    })();
</script>

<!-- Bootstrap 5.3 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<!-- Font Awesome -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<!-- SweetAlert2 -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

<!-- HAMS theme -->
<link href="<?php echo ASSETS_URL; ?>css/style.css" rel="stylesheet">

<?php if (!empty($extraHead)) { echo $extraHead; } ?>
</head>
<body class="<?php echo e($bodyClass); ?>">

<?php if (!$hideChrome): ?>
<div class="hams-wrapper">
<?php endif; ?>