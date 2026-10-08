<?php
$page_title = "How BuildConnect Works | Construction Management Platform";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

$flash = get_flash_message();
$user = get_logged_user();
?>

<?php if ($flash): ?>
    <div class="container mt-3">
        <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check text-success' : 'fa-circle-info text-warning' ?> me-2"></i>
            <?= sanitize($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
<?php endif; ?>

<!-- Hero Section -->
<header class="bc-hero-redesign">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8 text-start">
                <span class="bc-section-title-badge">HOW BUILDCONNECT WORKS</span>
                <h1 class="display-3 fw-bold text-white mb-3 brand-font lh-sm">
                    From First Connection to<br>
                    <span class="text-warning">Project Completion</span>
                </h1>
                <p class="lead text-slate-300 mb-4 me-lg-4" style="max-width: 650px; font-size: 1.125rem; line-height: 1.65;">
                    BuildConnect connects people, projects and workflows through one simple construction management platform.
                </p>

                <div class="d-flex flex-wrap align-items-center gap-3 pt-2">
                    <?php if ($user): ?>
                        <a href="<?= BASE_URL ?>/<?= get_role_redirect_url($user['role']) ?>" class="btn btn-amber-glow px-4.5 py-3 fw-bold fs-6">
                            Go to Dashboard <i class="fa-solid fa-arrow-right ms-2"></i>
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/register.php" class="btn btn-amber-glow px-4.5 py-3 fw-bold fs-6">
                            Create Your Account <i class="fa-solid fa-arrow-right ms-2"></i>
                        </a>
                    <?php endif; ?>
                    <a href="#workflow-steps" class="btn btn-outline-amber px-4.5 py-3 fw-semibold fs-6">
                        Explore Steps <i class="fa-solid fa-chevron-down ms-1.5"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- 6 Detailed Workflow Steps -->
<section id="workflow-steps" class="py-5 border-top border-secondary border-opacity-25 bg-dark">
    <div class="container py-4">
        <div class="text-center mb-5">
            <span class="bc-section-title-badge">STEP-BY-STEP WORKFLOW</span>
            <h2 class="h1 fw-bold text-white mb-2 brand-font">6 Simple Steps to Site Success</h2>
            <p class="text-slate-400 small mx-auto" style="max-width: 580px;">
                A clear, structured path from registration to final build sign-off.
            </p>
        </div>

        <div class="row g-4 text-start mb-5">
            <!-- STEP 1 -->
            <div class="col-md-6 col-lg-4">
                <div class="bc-step-card">
                    <div class="bc-step-number">01</div>
                    <h3 class="h5 text-white fw-bold mb-2 brand-font">Create Your Account</h3>
                    <p class="text-slate-400 small mb-0" style="line-height: 1.6;">
                        Register as a Contractor, Worker, or Client. Each role receives a personalized dashboard interface and tailored operations toolset.
                    </p>
                </div>
            </div>

            <!-- STEP 2 -->
            <div class="col-md-6 col-lg-4">
                <div class="bc-step-card">
                    <div class="bc-step-number">02</div>
                    <h3 class="h5 text-white fw-bold mb-2 brand-font">Complete Your Profile</h3>
                    <p class="text-slate-400 small mb-0" style="line-height: 1.6;">
                        Contractors add company & project details; workers showcase skills, experience, availability & verification; clients add project specs.
                    </p>
                </div>
            </div>

            <!-- STEP 3 -->
            <div class="col-md-6 col-lg-4">
                <div class="bc-step-card">
                    <div class="bc-step-number">03</div>
                    <h3 class="h5 text-white fw-bold mb-2 brand-font">Connect People & Projects</h3>
                    <p class="text-slate-400 small mb-0" style="line-height: 1.6;">
                        Contractors create projects and publish job vacancies. Workers discover relevant trade openings and apply. Clients link to project teams.
                    </p>
                </div>
            </div>

            <!-- STEP 4 -->
            <div class="col-md-6 col-lg-4">
                <div class="bc-step-card">
                    <div class="bc-step-number">04</div>
                    <h3 class="h5 text-white fw-bold mb-2 brand-font">Manage Work</h3>
                    <p class="text-slate-400 small mb-0" style="line-height: 1.6;">
                        Contractors assign workers, create tasks, set milestones, track QR attendance, and issue digital contracts. Workers execute assigned site work.
                    </p>
                </div>
            </div>

            <!-- STEP 5 -->
            <div class="col-md-6 col-lg-4">
                <div class="bc-step-card">
                    <div class="bc-step-number">05</div>
                    <h3 class="h5 text-white fw-bold mb-2 brand-font">Track Progress</h3>
                    <p class="text-slate-400 small mb-0" style="line-height: 1.6;">
                        Clients and contractors monitor progress bars, completed milestones, task statuses, on-site attendance logs, documents, and notifications.
                    </p>
                </div>
            </div>

            <!-- STEP 6 -->
            <div class="col-md-6 col-lg-4">
                <div class="bc-step-card">
                    <div class="bc-step-number">06</div>
                    <h3 class="h5 text-white fw-bold mb-2 brand-font">Make Better Decisions</h3>
                    <p class="text-slate-400 small mb-0" style="line-height: 1.6;">
                        Utilize analytics, reports, AI-assisted insights, project data, and workforce metrics. AI provides recommendations while users remain in control.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Visual Workflow Pipeline Section -->
<section class="py-5 border-top border-secondary border-opacity-25" style="background: rgba(15, 23, 42, 0.4);">
    <div class="container py-4">
        <div class="text-center mb-5">
            <span class="bc-section-title-badge">PIPELINE ARCHITECTURE</span>
            <h2 class="h1 fw-bold text-white mb-2 brand-font">End-to-End Visual Workflow</h2>
            <p class="text-slate-400 small mx-auto" style="max-width: 580px;">
                How data and actions flow through BuildConnect from registration to project completion.
            </p>
        </div>

        <div class="bc-card p-4 p-md-5 text-center">
            <div class="d-flex flex-wrap align-items-center justify-content-center gap-2.5">
                <span class="badge bg-dark border border-warning text-warning px-3 py-2 fs-6 rounded-3"><i class="fa-solid fa-user-plus me-1.5"></i> REGISTER</span>
                <i class="fa-solid fa-arrow-right text-slate-400"></i>
                <span class="badge bg-dark border border-secondary text-light px-3 py-2 fs-6 rounded-3"><i class="fa-solid fa-id-card me-1.5"></i> PROFILE</span>
                <i class="fa-solid fa-arrow-right text-slate-400"></i>
                <span class="badge bg-dark border border-warning text-warning px-3 py-2 fs-6 rounded-3"><i class="fa-solid fa-handshake me-1.5"></i> CONNECT</span>
                <i class="fa-solid fa-arrow-right text-slate-400"></i>
                <span class="badge bg-dark border border-secondary text-light px-3 py-2 fs-6 rounded-3"><i class="fa-solid fa-city me-1.5"></i> PROJECT</span>
                <i class="fa-solid fa-arrow-right text-slate-400"></i>
                <span class="badge bg-dark border border-warning text-warning px-3 py-2 fs-6 rounded-3"><i class="fa-solid fa-briefcase me-1.5"></i> JOBS & WORKERS</span>
                <i class="fa-solid fa-arrow-right text-slate-400"></i>
                <span class="badge bg-dark border border-secondary text-light px-3 py-2 fs-6 rounded-3"><i class="fa-solid fa-list-check me-1.5"></i> TASKS</span>
                <i class="fa-solid fa-arrow-right text-slate-400"></i>
                <span class="badge bg-dark border border-warning text-warning px-3 py-2 fs-6 rounded-3"><i class="fa-solid fa-qrcode me-1.5"></i> ATTENDANCE</span>
                <i class="fa-solid fa-arrow-right text-slate-400"></i>
                <span class="badge bg-dark border border-secondary text-light px-3 py-2 fs-6 rounded-3"><i class="fa-solid fa-chart-line me-1.5"></i> PROGRESS</span>
                <i class="fa-solid fa-arrow-right text-slate-400"></i>
                <span class="badge bg-dark border border-warning text-warning px-3 py-2 fs-6 rounded-3"><i class="fa-solid fa-chart-pie me-1.5"></i> REPORTS</span>
                <i class="fa-solid fa-arrow-right text-slate-400"></i>
                <span class="badge bg-success text-dark px-3 py-2 fs-6 rounded-3"><i class="fa-solid fa-circle-check me-1.5"></i> COMPLETION</span>
            </div>
        </div>
    </div>
</section>

<!-- Parallel Role Journeys Section -->
<section class="py-5 border-top border-secondary border-opacity-25 bg-dark">
    <div class="container py-4">
        <div class="text-center mb-5">
            <span class="bc-section-title-badge">PARALLEL JOURNEYS</span>
            <h2 class="h1 fw-bold text-white mb-2 brand-font">User Journeys by Role</h2>
            <p class="text-slate-400 small mx-auto" style="max-width: 580px;">
                Three parallel paths operating simultaneously on the BuildConnect platform.
            </p>
        </div>

        <div class="row g-4 text-start">
            <!-- CONTRACTOR JOURNEY -->
            <div class="col-lg-4">
                <div class="bc-card p-4.5 h-100">
                    <div class="d-flex align-items-center gap-2.5 mb-3 text-warning">
                        <i class="fa-solid fa-building fs-4"></i>
                        <h3 class="h5 text-white fw-bold mb-0 brand-font">CONTRACTOR JOURNEY</h3>
                    </div>
                    <hr class="border-secondary opacity-25">
                    <ol class="list-unstyled text-slate-300 small mb-0 d-flex flex-column gap-2.5">
                        <li><strong class="text-warning">1.</strong> Register Contractor Account</li>
                        <li><strong class="text-warning">2.</strong> Create Construction Project</li>
                        <li><strong class="text-warning">3.</strong> Post Job Vacancies</li>
                        <li><strong class="text-warning">4.</strong> Review & Hire Workers</li>
                        <li><strong class="text-warning">5.</strong> Assign Site Tasks & Milestones</li>
                        <li><strong class="text-warning">6.</strong> Track QR Attendance & Progress</li>
                        <li><strong class="text-warning">7.</strong> Successfully Complete Project</li>
                    </ol>
                </div>
            </div>

            <!-- WORKER JOURNEY -->
            <div class="col-lg-4">
                <div class="bc-card p-4.5 h-100">
                    <div class="d-flex align-items-center gap-2.5 mb-3 text-warning">
                        <i class="fa-solid fa-helmet-safety fs-4"></i>
                        <h3 class="h5 text-white fw-bold mb-0 brand-font">WORKER JOURNEY</h3>
                    </div>
                    <hr class="border-secondary opacity-25">
                    <ol class="list-unstyled text-slate-300 small mb-0 d-flex flex-column gap-2.5">
                        <li><strong class="text-warning">1.</strong> Register Worker Account</li>
                        <li><strong class="text-warning">2.</strong> Build Trade Profile & Skills</li>
                        <li><strong class="text-warning">3.</strong> Discover Open Trade Jobs</li>
                        <li><strong class="text-warning">4.</strong> Apply for Matching Positions</li>
                        <li><strong class="text-warning">5.</strong> Accept Contract & Get Hired</li>
                        <li><strong class="text-warning">6.</strong> Log Attendance & Finish Tasks</li>
                        <li><strong class="text-warning">7.</strong> Build Verified Platform Reputation</li>
                    </ol>
                </div>
            </div>

            <!-- CLIENT JOURNEY -->
            <div class="col-lg-4">
                <div class="bc-card p-4.5 h-100">
                    <div class="d-flex align-items-center gap-2.5 mb-3 text-warning">
                        <i class="fa-solid fa-user-tie fs-4"></i>
                        <h3 class="h5 text-white fw-bold mb-0 brand-font">CLIENT JOURNEY</h3>
                    </div>
                    <hr class="border-secondary opacity-25">
                    <ol class="list-unstyled text-slate-300 small mb-0 d-flex flex-column gap-2.5">
                        <li><strong class="text-warning">1.</strong> Register Client Account</li>
                        <li><strong class="text-warning">2.</strong> View Linked Construction Projects</li>
                        <li><strong class="text-warning">3.</strong> Monitor Real-Time Progress Meters</li>
                        <li><strong class="text-warning">4.</strong> Track Completion Milestones</li>
                        <li><strong class="text-warning">5.</strong> Inspect Digital Documents</li>
                        <li><strong class="text-warning">6.</strong> Receive Real-Time Notifications</li>
                        <li><strong class="text-warning">7.</strong> Verify Completed Build Quality</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Final CTA Section -->
<section class="py-5 bg-dark">
    <div class="container py-3">
        <div class="bc-cta-banner text-center">
            <h2 class="display-5 fw-bold text-white mb-3 brand-font">Ready to Build Smarter?</h2>
            <p class="lead text-slate-300 mx-auto mb-4" style="max-width: 600px; font-size: 1.1rem;">
                Bring your people, projects and construction workflows together with BuildConnect.
            </p>
            <div class="d-flex justify-content-center flex-wrap gap-3">
                <?php if ($user): ?>
                    <a href="<?= BASE_URL ?>/<?= get_role_redirect_url($user['role']) ?>" class="btn btn-amber-glow px-4.5 py-3 fw-bold fs-6">
                        Go to Dashboard <i class="fa-solid fa-arrow-right ms-2"></i>
                    </a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/register.php" class="btn btn-amber-glow px-4.5 py-3 fw-bold fs-6">
                        Create Your Account <i class="fa-solid fa-arrow-right ms-2"></i>
                    </a>
                    <a href="<?= BASE_URL ?>/services.php" class="btn btn-outline-amber px-4.5 py-3 fw-semibold fs-6">
                        Explore Services
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer id="contact" class="py-5 border-top border-secondary border-opacity-25" style="background-color: #060910;">
    <div class="container">
        <div class="row g-4 mb-4 text-start">
            <div class="col-lg-4">
                <a href="<?= BASE_URL ?>/index.php" class="bc-brand-logo mb-3">
                    <i class="fa-solid fa-helmet-safety"></i>
                    <span>Build<span class="text-warning">Connect</span></span>
                </a>
                <p class="text-slate-400 small mt-2" style="max-width: 320px; line-height: 1.6;">
                    Connecting People. Building Projects. Growing Together.
                </p>
            </div>
            <div class="col-6 col-lg-2">
                <h4 class="text-white small fw-bold text-uppercase mb-3 brand-font">Quick Links</h4>
                <ul class="list-unstyled text-slate-400 small mb-0 d-flex flex-column gap-2">
                    <li><a href="<?= BASE_URL ?>/index.php" class="text-slate-400 text-decoration-none hover-warning">Home</a></li>
                    <li><a href="<?= BASE_URL ?>/about.php" class="text-slate-400 text-decoration-none hover-warning">About</a></li>
                    <li><a href="<?= BASE_URL ?>/services.php" class="text-slate-400 text-decoration-none hover-warning">Services</a></li>
                    <li><a href="<?= BASE_URL ?>/how-it-works.php" class="text-slate-400 text-decoration-none hover-warning">How It Works</a></li>
                    <li><a href="<?= BASE_URL ?>/index.php#contact" class="text-slate-400 text-decoration-none hover-warning">Contact</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-3">
                <h4 class="text-white small fw-bold text-uppercase mb-3 brand-font">For Users</h4>
                <ul class="list-unstyled text-slate-400 small mb-0 d-flex flex-column gap-2">
                    <li><a href="<?= BASE_URL ?>/register.php?role=contractor" class="text-slate-400 text-decoration-none hover-warning">Contractors</a></li>
                    <li><a href="<?= BASE_URL ?>/register.php?role=worker" class="text-slate-400 text-decoration-none hover-warning">Workers</a></li>
                    <li><a href="<?= BASE_URL ?>/register.php?role=client" class="text-slate-400 text-decoration-none hover-warning">Clients</a></li>
                </ul>
            </div>
            <div class="col-lg-3">
                <h4 class="text-white small fw-bold text-uppercase mb-3 brand-font">Account</h4>
                <ul class="list-unstyled text-slate-400 small mb-0 d-flex flex-column gap-2 mb-3">
                    <li><a href="<?= BASE_URL ?>/login.php" class="text-slate-400 text-decoration-none hover-warning">Login</a></li>
                    <li><a href="<?= BASE_URL ?>/register.php" class="text-slate-400 text-decoration-none hover-warning">Register</a></li>
                </ul>
            </div>
        </div>

        <div class="pt-4 border-top border-secondary border-opacity-25 text-center text-slate-400 small">
            &copy; <?= date('Y') ?> BuildConnect. All rights reserved.
        </div>
    </div>
</footer>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
