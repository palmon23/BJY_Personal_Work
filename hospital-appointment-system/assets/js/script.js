/**
 * =====================================================================
 * assets/js/script.js
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * Global front-end behavior: dark/light theme toggle (persisted),
 * responsive sidebar, SweetAlert2 helpers, toast/flash display,
 * Bootstrap form validation, loading spinner, small UX utilities.
 *
 * Depends on (loaded via CDN in includes/footer.php):
 *   - Bootstrap 5 JS bundle
 *   - SweetAlert2
 * =====================================================================
 */

(function () {
    'use strict';

    /* =================================================================
       1. THEME (LIGHT / DARK MODE) — persisted in localStorage
       ================================================================= */
    const THEME_KEY = 'hams_theme';
    const htmlEl = document.documentElement;

    function applyTheme(theme) {
        htmlEl.setAttribute('data-bs-theme', theme);
        document.querySelectorAll('.theme-toggle .knob i').forEach((icon) => {
            icon.className = theme === 'dark' ? 'bi bi-moon-stars-fill' : 'bi bi-sun-fill';
        });
    }

    function initTheme() {
        const saved = localStorage.getItem(THEME_KEY);
        const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        applyTheme(saved || (prefersDark ? 'dark' : 'light'));
    }

    function toggleTheme() {
        const current = htmlEl.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
        const next = current === 'dark' ? 'light' : 'dark';
        applyTheme(next);
        localStorage.setItem(THEME_KEY, next);
    }

    // Apply theme immediately (before DOMContentLoaded) to avoid a flash of
    // the wrong theme. The toggle button listener is wired once DOM is ready.
    initTheme();

    /* =================================================================
       2. SIDEBAR (mobile off-canvas + desktop collapse)
       ================================================================= */
    function initSidebar() {
        const sidebar = document.querySelector('.hams-sidebar');
        const backdrop = document.querySelector('.sidebar-backdrop');
        const toggleBtns = document.querySelectorAll('.sidebar-toggle-btn');

        if (!sidebar) return;

        function openSidebar() {
            sidebar.classList.add('show');
            if (backdrop) backdrop.classList.add('show');
        }

        function closeSidebar() {
            sidebar.classList.remove('show');
            if (backdrop) backdrop.classList.remove('show');
        }

        toggleBtns.forEach((btn) => {
            btn.addEventListener('click', () => {
                if (window.innerWidth < 992) {
                    sidebar.classList.contains('show') ? closeSidebar() : openSidebar();
                } else {
                    document.body.classList.toggle('sidebar-collapsed');
                }
            });
        });

        if (backdrop) {
            backdrop.addEventListener('click', closeSidebar);
        }

        // Close the mobile drawer automatically after navigating
        sidebar.querySelectorAll('.nav-link').forEach((link) => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 992) closeSidebar();
            });
        });

        // Highlight the current page's nav link based on the URL
        const currentPath = window.location.pathname.split('/').pop();
        sidebar.querySelectorAll('.nav-link').forEach((link) => {
            const href = (link.getAttribute('href') || '').split('/').pop();
            if (href && href === currentPath) {
                link.classList.add('active');
            }
        });
    }

    /* =================================================================
       3. SWEETALERT2 HELPERS
       ================================================================= */

    /**
     * Show a themed SweetAlert2 toast (top-right, auto-dismiss).
     * type: 'success' | 'error' | 'warning' | 'info'
     */
    window.hamsToast = function (type, message) {
        if (typeof Swal === 'undefined') {
            console.warn('SweetAlert2 not loaded:', message);
            return;
        }
        Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true,
            didOpen: (el) => {
                el.addEventListener('mouseenter', Swal.stopTimer);
                el.addEventListener('mouseleave', Swal.resumeTimer);
            },
        }).fire({ icon: type, title: message });
    };

    /**
     * Confirmation dialog used before destructive actions (delete, cancel).
     * Returns a Promise<boolean>.
     */
    window.hamsConfirm = function (options) {
        const opts = Object.assign({
            title: 'Are you sure?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            confirmButtonText: 'Yes, proceed',
            cancelButtonText: 'Cancel',
        }, options || {});

        return Swal.fire({
            title: opts.title,
            text: opts.text,
            icon: opts.icon,
            showCancelButton: true,
            confirmButtonColor: '#EF4444',
            cancelButtonColor: '#64748B',
            confirmButtonText: opts.confirmButtonText,
            cancelButtonText: opts.cancelButtonText,
            reverseButtons: true,
        }).then((result) => result.isConfirmed);
    };

    /**
     * Wire up any element with [data-confirm-delete] to show a confirm
     * dialog before submitting its parent <form>.
     * Usage: <form data-confirm-delete data-confirm-message="Delete this doctor?">
     */
    function initConfirmDeleteForms() {
        document.querySelectorAll('form[data-confirm-delete]').forEach((form) => {
            form.addEventListener('submit', function (e) {
                if (form.dataset.confirmed === 'true') return; // already confirmed, let it submit
                e.preventDefault();
                const message = form.dataset.confirmMessage || 'This action cannot be undone.';
                window.hamsConfirm({ title: 'Are you sure?', text: message }).then((ok) => {
                    if (ok) {
                        form.dataset.confirmed = 'true';
                        form.submit();
                    }
                });
            });
        });
    }

    /**
     * Auto-display a PHP flash message passed via a hidden JSON script tag:
     * <script type="application/json" id="hams-flash">{"type":"success","message":"Saved!"}</script>
     */
    function initFlashDisplay() {
        const el = document.getElementById('hams-flash');
        if (!el) return;
        try {
            const flash = JSON.parse(el.textContent);
            if (flash && flash.message) {
                const iconMap = { error: 'error', success: 'success', warning: 'warning', info: 'info' };
                window.hamsToast(iconMap[flash.type] || 'info', flash.message);
            }
        } catch (err) {
            console.warn('Could not parse flash message', err);
        }
    }

    /* =================================================================
       4. BOOTSTRAP CLIENT-SIDE FORM VALIDATION
       ================================================================= */
    function initFormValidation() {
        const forms = document.querySelectorAll('.needs-validation');
        forms.forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }

    /**
     * Password confirmation matcher: any field with
     * [data-match="#otherFieldId"] validates equality live.
     */
    function initPasswordMatch() {
        document.querySelectorAll('[data-match]').forEach((field) => {
            const target = document.querySelector(field.dataset.match);
            if (!target) return;
            function check() {
                field.setCustomValidity(field.value === target.value ? '' : 'Passwords do not match');
            }
            field.addEventListener('input', check);
            target.addEventListener('input', check);
        });
    }

    /* =================================================================
       5. LOADING SPINNER
       ================================================================= */
    window.hamsShowSpinner = function () {
        if (document.querySelector('.hams-spinner-overlay')) return;
        const overlay = document.createElement('div');
        overlay.className = 'hams-spinner-overlay';
        overlay.innerHTML = '<div class="hams-spinner"></div>';
        document.body.appendChild(overlay);
    };

    window.hamsHideSpinner = function () {
        const overlay = document.querySelector('.hams-spinner-overlay');
        if (overlay) overlay.remove();
    };

    // Show spinner automatically on full page form submissions (not AJAX),
    // skipping forms explicitly marked [data-no-spinner].
    function initAutoSpinner() {
        document.querySelectorAll('form:not([data-no-spinner])').forEach((form) => {
            form.addEventListener('submit', () => {
                if (form.checkValidity && !form.checkValidity()) return;
                window.hamsShowSpinner();
            });
        });
    }

    /* =================================================================
       6. PASSWORD VISIBILITY TOGGLE
       Usage: <button type="button" data-toggle-password="#passwordFieldId">
       ================================================================= */
    function initPasswordToggle() {
        document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const input = document.querySelector(btn.dataset.togglePassword);
                if (!input) return;
                const showing = input.getAttribute('type') === 'text';
                input.setAttribute('type', showing ? 'password' : 'text');
                const icon = btn.querySelector('i');
                if (icon) icon.className = showing ? 'bi bi-eye' : 'bi bi-eye-slash';
            });
        });
    }

    /* =================================================================
       7. NOTIFICATION BELL — mark all as read (AJAX)
       ================================================================= */
    function initNotifications() {
        const markAllBtn = document.getElementById('markAllNotifsRead');
        if (!markAllBtn) return;

        markAllBtn.addEventListener('click', function () {
            fetch(markAllBtn.dataset.url, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then((res) => res.json())
                .then((data) => {
                    if (data.success) {
                        document.querySelectorAll('.notif-item.unread').forEach((item) => item.classList.remove('unread'));
                        const badge = document.getElementById('notifCountBadge');
                        if (badge) badge.remove();
                    }
                })
                .catch(() => window.hamsToast('error', 'Could not update notifications.'));
        });
    }

    /* =================================================================
       INIT — on DOM ready
       ================================================================= */
    document.addEventListener('DOMContentLoaded', function () {
        initSidebar();
        initConfirmDeleteForms();
        initFlashDisplay();
        initFormValidation();
        initPasswordMatch();
        initAutoSpinner();
        initPasswordToggle();
        initNotifications();

        const themeToggleBtn = document.querySelector('.theme-toggle');
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', toggleTheme);
        }

        // Re-sync icon state now that the DOM (and .knob i) definitely exists
        applyTheme(htmlEl.getAttribute('data-bs-theme') || 'light');
    });
})();