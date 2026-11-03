<?php
/**
 * =====================================================================
 * index.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * Public landing page. Introduces the hospital/system, highlights
 * departments pulled live from the database, and routes visitors to
 * register/login to book an appointment.
 * =====================================================================
 */

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, send straight to the right dashboard.
redirect_if_logged_in();

// Pull active departments for the "Our Departments" section.
$departments = [];
$result = mysqli_query($conn, "SELECT name, description, icon FROM departments WHERE status = 'active' ORDER BY name LIMIT 6");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $departments[] = $row;
    }
}

// Live counters for the trust bar.
$doctorCount = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM doctors WHERE status='active'"))['c'] ?? 0);
$departmentCount = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM departments WHERE status='active'"))['c'] ?? 0);
$patientCount = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM patients"))['c'] ?? 0);
$completedCount = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM appointments WHERE status='completed'"))['c'] ?? 0);

$pageTitle = 'Welcome';
$hideChrome = true; // Landing page has its own public navbar, not the dashboard chrome.
$extraHead = <<<HTML
<style>
    .public-nav { position: sticky; top: 0; z-index: 1030; background: var(--hams-surface); border-bottom: 1px solid var(--hams-border); }
    .hero-section {
        background: linear-gradient(160deg, var(--hams-primary-darker) 0%, #0C4A6E 55%, #082F49 100%);
        color: #fff; position: relative; overflow: hidden; padding: 90px 0 70px;
    }
    .hero-section::after {
        content: ''; position: absolute; left: -5%; right: -5%; bottom: 8%; height: 100px;
        background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 600 90'%3E%3Cpolyline points='0,45 120,45 145,10 170,80 195,45 260,45 285,25 305,65 325,45 600,45' fill='none' stroke='%2384CC16' stroke-width='2' opacity='0.3'/%3E%3C/svg%3E") repeat-x;
        background-size: 600px 90px; opacity: 0.5;
    }
    .hero-badge { background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.2); border-radius: 30px; padding: 6px 16px; font-size: .8rem; display: inline-flex; align-items: center; gap: 8px; }
    .trust-bar { background: var(--hams-surface); border-bottom: 1px solid var(--hams-border); }
    .trust-stat { text-align: center; padding: 22px 10px; }
    .trust-stat .num { font-family: var(--font-heading); font-weight: 800; font-size: 1.8rem; color: var(--hams-primary-darker); }
    .trust-stat .lbl { font-size: .82rem; color: var(--hams-text-muted); }
    .feature-tile { border-radius: var(--hams-radius); border: 1px solid var(--hams-border); background: var(--hams-surface); padding: 28px; height: 100%; transition: var(--hams-transition); }
    .feature-tile:hover { transform: translateY(-4px); box-shadow: var(--hams-shadow-lg); border-color: var(--hams-primary); }
    .feature-tile .fi { width: 52px; height: 52px; border-radius: 14px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; margin-bottom:16px; }
    .dept-card { border-radius: var(--hams-radius); border: 1px solid var(--hams-border); background: var(--hams-surface); padding: 24px; height: 100%; transition: var(--hams-transition); }
    .dept-card:hover { border-color: var(--hams-secondary); box-shadow: var(--hams-shadow-lg); transform: translateY(-3px); }
    .step-num { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--hams-primary), var(--hams-secondary)); color: #06202E; font-weight: 800; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .cta-band { background: linear-gradient(135deg, var(--hams-primary), var(--hams-secondary-dark)); border-radius: var(--hams-radius); color: #06202E; }
    .public-footer { background: var(--hams-sidebar-bg); color: rgba(186,230,253,.7); }
    .public-footer a { color: #fff; }
</style>
HTML;

require_once __DIR__ . '/includes/header.php';
?>

<!-- ================= PUBLIC NAVBAR ================= -->
<nav class="navbar navbar-expand-lg public-nav py-3">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="<?php echo BASE_URL; ?>">
            <span class="brand-mark d-inline-flex" style="width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,var(--hams-primary),var(--hams-secondary));align-items:center;justify-content:center;color:#06202E;">
                <i class="bi bi-heart-pulse-fill"></i>
            </span>
            <span class="font-heading"><?php echo e(APP_NAME); ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="publicNavMenu">
            <ul class="navbar-nav mx-auto gap-lg-2 mt-3 mt-lg-0">
                <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="#departments">Departments</a></li>
                <li class="nav-item"><a class="nav-link" href="#how-it-works">How It Works</a></li>
                <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
            </ul>
            <div class="d-flex gap-2 mt-3 mt-lg-0">
                <button type="button" class="theme-toggle" aria-label="Toggle dark mode"><span class="knob"><i class="bi bi-sun-fill"></i></span></button>
                <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-outline-primary">Login</a>
                <a href="<?php echo BASE_URL; ?>register.php" class="btn btn-primary">Book Appointment</a>
            </div>
        </div>
    </div>
</nav>

<!-- ================= HERO ================= -->
<section class="hero-section" id="home">
    <div class="container position-relative" style="z-index:2;">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <span class="hero-badge"><i class="bi bi-shield-check"></i> Trusted care, simplified scheduling</span>
                <h1 class="display-5 fw-bold mt-3 mb-3" style="font-family:var(--font-heading);">Book your hospital appointment in minutes, not phone calls.</h1>
                <p class="lead" style="color:rgba(255,255,255,.85);">HAMS connects patients, doctors, receptionists and administrators on one simple platform — so you can find the right department, pick a time that works, and get confirmed without waiting on hold.</p>
                <div class="d-flex flex-wrap gap-3 mt-4">
                    <a href="<?php echo BASE_URL; ?>register.php" class="btn btn-secondary btn-lg px-4"><i class="bi bi-calendar-plus me-2"></i>Book an Appointment</a>
                    <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-outline-light btn-lg px-4">Staff / Doctor Login</a>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="p-4 rounded-4" style="background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); backdrop-filter: blur(6px);">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="fi bg-white bg-opacity-25 rounded-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;"><i class="bi bi-calendar2-check fs-4"></i></div>
                        <div>
                            <div class="fw-semibold">Next available slot</div>
                            <div style="color:rgba(255,255,255,.7); font-size:.85rem;">Cardiology &middot; Dr. Sarah Johnson</div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center p-3 rounded-3 mb-2" style="background:rgba(255,255,255,.08);">
                        <span><i class="bi bi-clock me-2"></i>Today, 3:30 PM</span>
                        <span class="badge bg-secondary-accent" style="background:var(--hams-secondary)!important;color:#06202E;">Open</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center p-3 rounded-3" style="background:rgba(255,255,255,.08);">
                        <span><i class="bi bi-clock me-2"></i>Tomorrow, 9:00 AM</span>
                        <span class="badge bg-secondary-accent" style="background:var(--hams-secondary)!important;color:#06202E;">Open</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ================= TRUST BAR ================= -->
<section class="trust-bar">
    <div class="container">
        <div class="row g-0 text-center">
            <div class="col-6 col-md-3 trust-stat"><div class="num"><?php echo $doctorCount; ?>+</div><div class="lbl">Active Doctors</div></div>
            <div class="col-6 col-md-3 trust-stat"><div class="num"><?php echo $departmentCount; ?></div><div class="lbl">Departments</div></div>
            <div class="col-6 col-md-3 trust-stat"><div class="num"><?php echo $patientCount; ?>+</div><div class="lbl">Registered Patients</div></div>
            <div class="col-6 col-md-3 trust-stat"><div class="num"><?php echo $completedCount; ?>+</div><div class="lbl">Consultations Completed</div></div>
        </div>
    </div>
</section>

<!-- ================= FEATURES ================= -->
<section class="py-5 py-lg-6" style="padding-top:70px; padding-bottom:70px;">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Everything a modern hospital front desk needs</h2>
            <p class="text-muted">One platform for patients, receptionists, doctors and administrators.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="feature-tile">
                    <div class="fi bg-primary-soft text-primary"><i class="bi bi-calendar2-check"></i></div>
                    <h5 class="fw-bold">Online Booking</h5>
                    <p class="text-muted small mb-0">Patients pick a department, doctor and time slot in a few clicks — no phone queue.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="feature-tile">
                    <div class="fi bg-secondary-soft text-secondary-accent"><i class="bi bi-person-badge"></i></div>
                    <h5 class="fw-bold">Verified Doctors</h5>
                    <p class="text-muted small mb-0">Every doctor profile shows specialization, qualifications and real-time availability.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="feature-tile">
                    <div class="fi bg-primary-soft text-primary"><i class="bi bi-bell"></i></div>
                    <h5 class="fw-bold">Instant Notifications</h5>
                    <p class="text-muted small mb-0">Confirmations, reschedules and cancellations reach patients and doctors instantly.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="feature-tile">
                    <div class="fi bg-secondary-soft text-secondary-accent"><i class="bi bi-shield-lock"></i></div>
                    <h5 class="fw-bold">Secure &amp; Private</h5>
                    <p class="text-muted small mb-0">Hashed credentials, CSRF-protected forms and role-based access protect every record.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ================= DEPARTMENTS ================= -->
<section class="py-5" style="background: var(--hams-surface-alt);" id="departments">
    <div class="container py-4">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Our Departments</h2>
            <p class="text-muted">Choose the right specialty for your visit.</p>
        </div>
        <div class="row g-4">
            <?php if (empty($departments)): ?>
                <div class="col-12">
                    <div class="empty-state"><i class="bi bi-diagram-3"></i><p>Departments will appear here once added by the administrator.</p></div>
                </div>
            <?php else: ?>
                <?php foreach ($departments as $dept): ?>
                <div class="col-sm-6 col-lg-4">
                    <div class="dept-card">
                        <div class="fi bg-primary-soft text-primary mb-3" style="width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;">
                            <i class="bi <?php echo e($dept['icon'] ?: 'bi-hospital'); ?>"></i>
                        </div>
                        <h5 class="fw-bold mb-1"><?php echo e($dept['name']); ?></h5>
                        <p class="text-muted small mb-0"><?php echo e($dept['description'] ?: 'Comprehensive, patient-centered care from our specialist team.'); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ================= HOW IT WORKS ================= -->
<section class="py-5" id="how-it-works">
    <div class="container py-4">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Book an appointment in 3 steps</h2>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="d-flex gap-3">
                    <div class="step-num">1</div>
                    <div><h6 class="fw-bold mb-1">Create your account</h6><p class="text-muted small">Register as a patient with your basic details in under a minute.</p></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex gap-3">
                    <div class="step-num">2</div>
                    <div><h6 class="fw-bold mb-1">Choose doctor &amp; time</h6><p class="text-muted small">Browse departments, pick an available doctor and a convenient slot.</p></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex gap-3">
                    <div class="step-num">3</div>
                    <div><h6 class="fw-bold mb-1">Get confirmed</h6><p class="text-muted small">Receive an instant notification once the front desk confirms your visit.</p></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ================= CTA ================= -->
<section class="container pb-5">
    <div class="cta-band p-5 text-center">
        <h3 class="fw-bold mb-2">Ready to book your visit?</h3>
        <p class="mb-4" style="color:#06202E; opacity:.8;">Join HAMS today and manage your hospital visits online, anytime.</p>
        <a href="<?php echo BASE_URL; ?>register.php" class="btn btn-dark btn-lg px-4">Create your free account</a>
    </div>
</section>

<!-- ================= FOOTER ================= -->
<footer class="public-footer pt-5 pb-4" id="contact">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <h5 class="text-white fw-bold"><?php echo e(APP_NAME); ?></h5>
                <p class="small">Hospital Appointment Management System — connecting patients and care teams on one secure platform.</p>
            </div>
            <div class="col-md-4">
                <h6 class="text-white fw-bold">Quick Links</h6>
                <ul class="list-unstyled small">
                    <li><a href="#departments">Departments</a></li>
                    <li><a href="<?php echo BASE_URL; ?>login.php">Login</a></li>
                    <li><a href="<?php echo BASE_URL; ?>register.php">Register</a></li>
                </ul>
            </div>
            <div class="col-md-4">
                <h6 class="text-white fw-bold">Contact</h6>
                <ul class="list-unstyled small">
                    <li><i class="bi bi-geo-alt me-2"></i>123 Wellness Avenue, Health City</li>
                    <li><i class="bi bi-telephone me-2"></i>+1 234 567 890</li>
                    <li><i class="bi bi-envelope me-2"></i>info@hams.test</li>
                </ul>
            </div>
        </div>
        <hr class="border-secondary my-4">
        <p class="small text-center mb-0">&copy; <?php echo date('Y'); ?> <?php echo e(APP_FULL_NAME); ?>. All rights reserved.</p>
    </div>
</footer>

<?php require_once __DIR__ . '/includes/footer.php'; ?>