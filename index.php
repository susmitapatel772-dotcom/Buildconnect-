<?php
$page_title = "BuildConnect | Smart Construction Management Platform";
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

<!-- Exact Screenshot Hero & Platform Section -->
<header class="bc-hero-exact">
    <div class="container">
        <!-- Hero Upper Row -->
        <div class="row align-items-center mb-5 pb-lg-4">
            <div class="col-lg-7 text-start position-relative" style="z-index: 3;">
                <span class="badge border border-warning text-warning bg-dark bg-opacity-75 rounded-pill px-3 py-2 fw-semibold small d-inline-flex align-items-center gap-2 mb-3.5">
                    <i class="fa-solid fa-microchip text-warning"></i> AI Powered Construction Management
                </span>
                <h1 class="display-3 fw-bold text-white mb-3 brand-font lh-sm">
                    Connect People. Build Projects.<br>
                    <span class="text-warning">Grow Together.</span>
                </h1>
                <p class="lead text-slate-300 mb-4 me-lg-4" style="max-width: 580px; font-size: 1.05rem; line-height: 1.6;">
                    BuildConnect brings contractors, clients and workers together on one smart platform. Manage your projects, hire skilled workers, find better opportunities and build a stronger future.
                </p>

                <div class="d-flex flex-wrap align-items-center gap-3 pt-2">
                    <?php if ($user): ?>
                        <a href="<?= BASE_URL ?>/<?= get_role_redirect_url($user['role']) ?>" class="btn btn-amber px-4 py-2.5 fw-bold rounded-3">
                            <i class="fa-solid fa-user-plus me-2"></i> Go to Dashboard <i class="fa-solid fa-arrow-right ms-2"></i>
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/register.php" class="btn btn-amber px-4 py-2.5 fw-bold rounded-3">
                            <i class="fa-solid fa-user-plus me-2"></i> Get Started <i class="fa-solid fa-arrow-right ms-2"></i>
                        </a>
                    <?php endif; ?>
                    <a href="#platform" class="btn btn-outline-light px-4 py-2.5 fw-semibold rounded-3">
                        <i class="fa-solid fa-circle-play me-2 text-warning"></i> Explore BuildConnect
                    </a>
                </div>
            </div>

            <!-- Sunset Handwritten Graphic Overlay -->
            <div class="bc-sunset-accent d-none d-xl-block">
                <span class="bc-handwriting">Smarter Construction Together</span>
                <div class="bc-swoosh"></div>
            </div>
        </div>

        <!-- Platform & Role Cards Side-by-Side Lower Row -->
        <div id="platform" class="pt-4 border-top border-secondary border-opacity-25">
            <div class="row align-items-center g-4">
                <!-- Left Title Column -->
                <div class="col-lg-4 text-start">
                    <span class="badge border border-secondary text-slate-300 bg-dark rounded-pill px-3 py-1.5 extra-small fw-semibold mb-2">OUR PLATFORM</span>
                    <h2 class="h1 fw-bold text-white mb-2 brand-font">One Central Hub for Every Construction Need</h2>
                    <p class="text-slate-400 small mb-0" style="max-width: 360px; line-height: 1.6;">
                        From workforce management to project tracking — everything you need, in one place.
                    </p>
                </div>

                <!-- Right 3 Role Cards Row -->
                <div class="col-lg-8">
                    <div class="row g-3 text-start">
                        <!-- CONTRACTORS CARD -->
                        <div class="col-md-4">
                            <div class="bc-platform-card">
                                <div>
                                    <div class="stat-icon amber mb-3">
                                        <i class="fa-solid fa-users text-warning fs-5"></i>
                                    </div>
                                    <h3 class="h6 text-white fw-bold mb-1.5 brand-font">Contractors</h3>
                                    <p class="text-slate-400 extra-small mb-3" style="line-height: 1.5;">
                                        Hire verified workers, manage projects and grow your business.
                                    </p>
                                </div>
                                <div class="text-end">
                                    <a href="<?= BASE_URL ?>/<?= $user ? get_role_redirect_url($user['role']) : 'register.php?role=contractor' ?>" class="text-warning text-decoration-none fw-bold fs-5 hover-warning">
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- WORKERS CARD -->
                        <div class="col-md-4">
                            <div class="bc-platform-card">
                                <div>
                                    <div class="stat-icon blue mb-3">
                                        <i class="fa-solid fa-helmet-safety text-info fs-5"></i>
                                    </div>
                                    <h3 class="h6 text-white fw-bold mb-1.5 brand-font">Workers</h3>
                                    <p class="text-slate-400 extra-small mb-3" style="line-height: 1.5;">
                                        Find new job opportunities, showcase your skills and build your career.
                                    </p>
                                </div>
                                <div class="text-end">
                                    <a href="<?= BASE_URL ?>/<?= $user ? get_role_redirect_url($user['role']) : 'register.php?role=worker' ?>" class="text-warning text-decoration-none fw-bold fs-5 hover-warning">
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- CLIENTS CARD -->
                        <div class="col-md-4">
                            <div class="bc-platform-card">
                                <div>
                                    <div class="stat-icon purple mb-3">
                                        <i class="fa-solid fa-building text-purple fs-5"></i>
                                    </div>
                                    <h3 class="h6 text-white fw-bold mb-1.5 brand-font">Clients</h3>
                                    <p class="text-slate-400 extra-small mb-3" style="line-height: 1.5;">
                                        Track progress, communicate with your team and ensure quality work.
                                    </p>
                                </div>
                                <div class="text-end">
                                    <a href="<?= BASE_URL ?>/<?= $user ? get_role_redirect_url($user['role']) : 'register.php?role=client' ?>" class="text-warning text-decoration-none fw-bold fs-5 hover-warning">
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- How It Works Section -->
<section id="how-it-works" class="py-5 border-top border-secondary border-opacity-25" style="background: rgba(15, 23, 42, 0.4);">
    <div class="container py-4">
        <div class="text-center mb-5">
            <span class="bc-section-title-badge">HOW IT WORKS</span>
            <h2 class="h1 fw-bold text-white mb-2 brand-font">From Connection to Completion</h2>
            <p class="text-slate-400 small mx-auto" style="max-width: 560px;">
                A simple 4-step workflow connecting workforce hiring with project execution.
            </p>
        </div>

        <div class="row g-4 text-start">
            <div class="col-lg-3 col-sm-6">
                <div class="bc-step-card">
                    <div class="bc-step-number">01</div>
                    <h3 class="h5 text-white fw-bold mb-2 brand-font">Create Your Profile</h3>
                    <p class="text-slate-400 small mb-0" style="line-height: 1.5;">Register as a Worker, Contractor, or Client with role-specific verification details.</p>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6">
                <div class="bc-step-card">
                    <div class="bc-step-number">02</div>
                    <h3 class="h5 text-white fw-bold mb-2 brand-font">Connect With People</h3>
                    <p class="text-slate-400 small mb-0" style="line-height: 1.5;">Contractors publish trade openings while skilled workers apply with proven credentials.</p>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6">
                <div class="bc-step-card">
                    <div class="bc-step-number">03</div>
                    <h3 class="h5 text-white fw-bold mb-2 brand-font">Manage Projects & Work</h3>
                    <p class="text-slate-400 small mb-0" style="line-height: 1.5;">Formalize agreements using digital contracts, set milestones, and log daily QR attendance.</p>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6">
                <div class="bc-step-card">
                    <div class="bc-step-number">04</div>
                    <h3 class="h5 text-white fw-bold mb-2 brand-font">Track Progress & Grow</h3>
                    <p class="text-slate-400 small mb-0" style="line-height: 1.5;">Monitor progress bars, review platform performance ratings, and expand operations.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Key Features Section -->
<section id="services" class="py-5 border-top border-secondary border-opacity-25 bg-dark">
    <div class="container py-4">
        <div class="text-center mb-5">
            <span class="bc-section-title-badge">WHY BUILDCONNECT</span>
            <h2 class="h1 fw-bold text-white mb-2 brand-font">Everything You Need to Build Smarter</h2>
            <p class="text-slate-400 small mx-auto" style="max-width: 600px;">
                Designed specifically to solve real-world construction operational challenges.
            </p>
        </div>

        <div class="row g-4 text-start">
            <div class="col-md-6 col-lg-3">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-diagram-project text-warning fs-3 mb-3"></i>
                    <h3 class="h6 text-white fw-bold mb-2 brand-font">Project Management</h3>
                    <p class="text-slate-400 extra-small mb-0">Define milestones, track task completion timelines, and monitor site progress.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-robot text-warning fs-3 mb-3"></i>
                    <h3 class="h6 text-white fw-bold mb-2 brand-font">Job & Worker Matching</h3>
                    <p class="text-slate-400 extra-small mb-0">AI-assisted trade matching connecting qualified masons and electricians with jobs.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-qrcode text-warning fs-3 mb-3"></i>
                    <h3 class="h6 text-white fw-bold mb-2 brand-font">QR Attendance</h3>
                    <p class="text-slate-400 extra-small mb-0">On-site QR code check-in and check-out logs with automated working hours calculation.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-file-contract text-warning fs-3 mb-3"></i>
                    <h3 class="h6 text-white fw-bold mb-2 brand-font">Digital Contracts</h3>
                    <p class="text-slate-400 extra-small mb-0">Standardized digital agreements, payment rates, and legally enforceable terms.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-bell text-warning fs-3 mb-3"></i>
                    <h3 class="h6 text-white fw-bold mb-2 brand-font">Real-Time Notifications</h3>
                    <p class="text-slate-400 extra-small mb-0">Instant alerts for application updates, milestone approvals, and contract reviews.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-chart-pie text-warning fs-3 mb-3"></i>
                    <h3 class="h6 text-white fw-bold mb-2 brand-font">Project Analytics</h3>
                    <p class="text-slate-400 extra-small mb-0">Live graphical dashboards tracking project completion percentages and metrics.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-map-location-dot text-warning fs-3 mb-3"></i>
                    <h3 class="h6 text-white fw-bold mb-2 brand-font">Google Maps</h3>
                    <p class="text-slate-400 extra-small mb-0">Interactive map mapping active project sites across Ahmedabad and Surat.</p>
                </div>
            </div>

            <div class="col-md-6 col-lg-3">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-brain text-warning fs-3 mb-3"></i>
                    <h3 class="h6 text-white fw-bold mb-2 brand-font">AI-Powered Insights</h3>
                    <p class="text-slate-400 extra-small mb-0">Centralized AI engine evaluating task deadline risks and project health diagnostics.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Trust / Platform Section -->
<section id="about" class="py-5 border-top border-secondary border-opacity-25" style="background: rgba(15, 23, 42, 0.4);">
    <div class="container py-4">
        <div class="text-center mb-5">
            <span class="bc-section-title-badge">BUILT FOR EVERYONE</span>
            <h2 class="h1 fw-bold text-white mb-2 brand-font">Built for Everyone in Construction</h2>
            <p class="text-slate-400 small mx-auto" style="max-width: 580px;">
                Empowering all stakeholders with modern digital tools tailored to their unique role.
            </p>
        </div>

        <div class="row g-4 text-center">
            <div class="col-md-4">
                <div class="bc-card p-4 h-100">
                    <i class="fa-solid fa-building-user text-warning fs-2 mb-3"></i>
                    <h3 class="h5 text-white fw-bold mb-2 brand-font">For Contractors</h3>
                    <p class="text-slate-400 small mb-0">Manage your workforce, publish job vacancies, track site tasks, and formalize digital agreements effortlessly.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="bc-card p-4 h-100">
                    <i class="fa-solid fa-user-shield text-warning fs-2 mb-3"></i>
                    <h3 class="h5 text-white fw-bold mb-2 brand-font">For Workers</h3>
                    <p class="text-slate-400 small mb-0">Find reliable job opportunities, showcase your experience, record QR attendance, and build your trade reputation.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="bc-card p-4 h-100">
                    <i class="fa-solid fa-chart-line text-warning fs-2 mb-3"></i>
                    <h3 class="h5 text-white fw-bold mb-2 brand-font">For Clients</h3>
                    <p class="text-slate-400 small mb-0">Monitor active project timelines, review completion milestones, inspect digital contracts, and verify quality work.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Final Call to Action Section -->
<section class="py-5 bg-dark">
    <div class="container py-3">
        <div class="bc-cta-banner text-center">
            <h2 class="display-5 fw-bold text-white mb-3 brand-font">Ready to Build Smarter?</h2>
            <p class="lead text-slate-300 mx-auto mb-4" style="max-width: 600px; font-size: 1.1rem;">
                Join BuildConnect and bring your construction work, workforce and projects together.
            </p>
            <div class="d-flex justify-content-center flex-wrap gap-3">
                <?php if ($user): ?>
                    <a href="<?= BASE_URL ?>/<?= get_role_redirect_url($user['role']) ?>" class="btn btn-amber px-4.5 py-3 fw-bold fs-6 rounded-3">
                        <i class="fa-solid fa-user-plus me-2"></i> Go to Dashboard <i class="fa-solid fa-arrow-right ms-2"></i>
                    </a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/register.php" class="btn btn-amber px-4.5 py-3 fw-bold fs-6 rounded-3">
                        <i class="fa-solid fa-user-plus me-2"></i> Get Started <i class="fa-solid fa-arrow-right ms-2"></i>
                    </a>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/services.php" class="btn btn-outline-light px-4.5 py-3 fw-semibold fs-6 rounded-3">
                    Explore BuildConnect
                </a>
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
                    Smart construction workforce and project management platform connecting contractors, workers, and clients.
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
                <h4 class="text-white small fw-bold text-uppercase mb-3 brand-font">Roles & Portals</h4>
                <ul class="list-unstyled text-slate-400 small mb-0 d-flex flex-column gap-2">
                    <li><a href="<?= BASE_URL ?>/register.php?role=contractor" class="text-slate-400 text-decoration-none hover-warning">Contractors Portal</a></li>
                    <li><a href="<?= BASE_URL ?>/register.php?role=worker" class="text-slate-400 text-decoration-none hover-warning">Workers Portal</a></li>
                    <li><a href="<?= BASE_URL ?>/register.php?role=client" class="text-slate-400 text-decoration-none hover-warning">Clients Portal</a></li>
                    <li><a href="<?= BASE_URL ?>/login.php" class="text-slate-400 text-decoration-none hover-warning">Admin Access</a></li>
                </ul>
            </div>
            <div class="col-lg-3">
                <h4 class="text-white small fw-bold text-uppercase mb-3 brand-font">Contact Info</h4>
                <p class="text-slate-400 small mb-2"><i class="fa-solid fa-location-dot me-2 text-warning"></i>Ahmedabad, Gujarat, India</p>
                <p class="text-slate-400 small mb-2"><i class="fa-solid fa-envelope me-2 text-warning"></i>contact@buildconnect.demo</p>
                <p class="text-slate-400 small mb-0"><i class="fa-solid fa-phone me-2 text-warning"></i>+91 98765 43210</p>
            </div>
        </div>

        <div class="pt-4 border-top border-secondary border-opacity-25 text-center text-slate-400 small">
            &copy; <?= date('Y') ?> BuildConnect. All rights reserved.
        </div>
    </div>
</footer>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
