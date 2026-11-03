<?php
/**
 * =====================================================================
 * includes/footer.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * Closes the layout markup opened in header.php/sidebar.php/navbar.php,
 * renders the footer bar, bridges any PHP flash message into a
 * SweetAlert2 toast, and loads all JS assets (Bootstrap, SweetAlert2,
 * our own script.js) at the very end of <body> for fast page loads.
 *
 * REQUIRES: includes/functions.php already loaded (for get_flash()).
 * Optional: $hideChrome (bool) — must match the value passed to header.php.
 * =====================================================================
 */

$hideChrome = $hideChrome ?? false;
$__flash = get_flash();
?>

<?php if (!$hideChrome): ?>
        <footer class="hams-footer">
            &copy; <?php echo date('Y'); ?> <?php echo e(APP_FULL_NAME); ?>. All rights reserved.
            <span class="text-muted">&middot; v<?php echo e(APP_VERSION); ?></span>
        </footer>
    </div><!-- /.hams-main -->
</div><!-- /.hams-wrapper -->
<?php endif; ?>

<?php if ($__flash): ?>
<script type="application/json" id="hams-flash"><?php echo json_encode($__flash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<?php endif; ?>

<!-- Bootstrap 5.3 JS Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<!-- HAMS global script -->
<script src="<?php echo ASSETS_URL; ?>js/script.js"></script>

<?php if (!empty($extraScripts)) { echo $extraScripts; } ?>
</body>
</html>