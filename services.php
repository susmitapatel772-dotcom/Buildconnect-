<?php
$page_title = "BuildConnect Services | Construction Management";
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
                <span class="bc-section-title-badge">OUR SERVICES</span>
                <h1 class="display-3 fw-bold text-white mb-3 brand-font lh-sm">
                    Everything You Need to<br>
                    <span class="text-warning">Manage Construction Better</span>
                </h1>
                <p class="lead text-slate-300 mb-4 me-lg-4" style="max-width: 650px; font-size: 1.125rem; line-height: 1.65;">
                    From workforce management to project tracking, BuildConnect gives every role the tools they need to work smarter.
                </p>

                <div class="d-flex flex-wrap align-items-center gap-3 pt-2">
                    <?php if ($user): ?>
                        <a href="<?= BASE_URL ?>/<?= get_role_redirect_url($user['role']) ?>" class="btn btn-amber-glow px-4.5 py-3 fw-bold fs-6">
                            Go to Dashboard <i class="fa-solid fa-arrow-right ms-2"></i>
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/register.php" class="btn btn-amber-glow px-4.5 py-3 fw-bold fs-6">
                            Get Started <i class="fa-solid fa-arrow-right ms-2"></i>
                        </a>
                    <?php endif; ?>
                    <a href="#role-services" class="btn btn-outline-amber px-4.5 py-3 fw-semibold fs-6">
                        Role Portals <i class="fa-solid fa-chevron-down ms-1.5"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- 12 Core Services Grid -->
<section class="py-5 border-top border-secondary border-opacity-25 bg-dark">
    <div class="container py-4">
        <div class="text-center mb-5">
            <span class="bc-section-title-badge">FULL FEATURE MATRIX</span>
            <h2 class="h1 fw-bold text-white mb-2 brand-font">End-to-End Construction Capabilities</h2>
            <p class="text-slate-400 small mx-auto" style="max-width: 600px;">
                Comprehensive capabilities supporting every phase of project management and hiring.
            </p>
        </div>

        <div class="row g-4 text-start">
            <!-- 1. Project Management -->
            <div class="col-md-6 col-lg-4">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-diagram-project text-warning fs-3 mb-3"></i>
                    <h3 class="h5 text-white fw-bold mb-2 brand-font">1. Project Management</h3>
                    <p class="text-slate-400 small mb-0" style="line-height: 1.6;">
                        Create and manage construction projects, track progress, milestones, tasks and project information.
                    </p>
                </div>
            </div>

            <!-- 2. Workforce Management -->
            <div class="col-md-6 col-lg-4">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-users-gear text-warning fs-3 mb-3"></i>
                    <h3 class="h5 text-white fw-bold mb-2 brand-font">2. Workforce Management</h3>
                    <p class="text-slate-400 small mb-0" style="line-height: 1.6;">
                        Find, connect and manage skilled construction workers for your active site projects.
                    </p>
                </div>
            </div>

            <!-- 3. Job & Hiring Management -->
            <div class="col-md-6 col-lg-4">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-briefcase text-warning fs-3 mb-3"></i>
                    <h3 class="h5 text-white fw-bold mb-2 brand-font">3. Job & Hiring Management</h3>
                    <p class="text-slate-400 small mb-0" style="line-height: 1.6;">
                        Publish construction jobs, receive applications and manage hiring from one place.
                    </p>
                </div>
            </div>

            <!-- 4. Worker Profiles -->
            <div class="col-md-6 col-lg-4">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-id-card text-warning fs-3 mb-3"></i>
                    <h3 class="h5 text-white fw-bold mb-2 brand-font">4. Worker Profiles</h3>
                    <p class="text-slate-400 small mb-0" style="line-height: 1.6;">
                        Workers can showcase skills, experience, availability and verified professional information.
                    </p>
                </div>
            </div>

            <!-- 5. QR Attendance -->
            <div class="col-md-6 col-lg-4">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-qrcode text-warning fs-3 mb-3"></i>
                    <h3 class="h5 text-white fw-bold mb-2 brand-font">5. QR Attendance</h3>
                    <p class="text-slate-400 small mb-0" style="line-height: 1.6;">
                        Track workforce attendance securely through project-based QR attendance sessions.
                    </p>
                </div>
            </div>

            <!-- 6. Digital Contracts -->
            <div class="col-md-6 col-lg-4">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-file-contract text-warning fs-3 mb-3"></i>
                    <h3 class="h5 text-white fw-bold mb-2 brand-font">6. Digital Contracts</h3>
                    <p class="text-slate-400 small mb-0" style="line-height: 1.6;">
                        Manage digital work contracts between contractors and workers with agreed daily rates.
                    </p>
                </div>
            </div>

            <!-- 7. Payments & Reviews -->
            <div class="col-md-6 col-lg-4">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-star text-warning fs-3 mb-3"></i>
                    <h3 class="h5 text-white fw-bold mb-2 brand-font">7. Payments & Reviews</h3>
                    <p class="text-slate-400 small mb-0" style="line-height: 1.6;">
                        Support transparent work relationships through payment records, ratings and reviews.
                    </p>
                </div>
            </div>

            <!-- 8. Project Monitoring -->
            <div class="col-md-6 col-lg-4">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-eye text-warning fs-3 mb-3"></i>
                    <h3 class="h5 text-white fw-bold mb-2 brand-font">8. Project Monitoring</h3>
                    <p class="text-slate-400 small mb-0" style="line-height: 1.6;">
                        Clients can monitor project progress, milestones, documents and visual progress reports.
                    </p>
                </div>
            </div>

            <!-- 9. Maps & Locations -->
            <div class="col-md-6 col-lg-4">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-map-location-dot text-warning fs-3 mb-3"></i>
                    <h3 class="h5 text-white fw-bold mb-2 brand-font">9. Maps & Locations</h3>
                    <p class="text-slate-400 small mb-0" style="line-height: 1.6;">
                        Visualize project locations and construction sites using integrated interactive maps.
                    </p>
                </div>
            </div>

            <!-- 10. Notifications -->
            <div class="col-md-6 col-lg-4">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-bell text-warning fs-3 mb-3"></i>
                    <h3 class="h5 text-white fw-bold mb-2 brand-font">10. Notifications</h3>
                    <p class="text-slate-400 small mb-0" style="line-height: 1.6;">
                        Keep contractors, workers and clients informed with relevant project and status updates.
                    </p>
                </div>
            </div>

            <!-- 11. Analytics & Reports -->
            <div class="col-md-6 col-lg-4">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-chart-pie text-warning fs-3 mb-3"></i>
                    <h3 class="h5 text-white fw-bold mb-2 brand-font">11. Analytics & Reports</h3>
                    <p class="text-slate-400 small mb-0" style="line-height: 1.6;">
                        Turn project and workforce data into useful performance insights and completion metrics.
                    </p>
                </div>
            </div>

            <!-- 12. AI-Powered Assistance -->
            <div class="col-md-6 col-lg-4">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-brain text-warning fs-3 mb-3"></i>
                    <h3 class="h5 text-white fw-bold mb-2 brand-font">12. AI-Powered Assistance</h3>
                    <p class="text-slate-400 small mb-0" style="line-height: 1.6;">
                        Use AI to assist with worker-job matching, project insights and construction workflows. *(Assistance only; users maintain full control).*
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Role-Based Services Section -->
<section id="role-services" class="py-5 border-top border-secondary border-opacity-25" style="background: rgba(15, 23, 42, 0.4);">
    <div class="container py-4">
        <div class="text-center mb-5">
            <span class="bc-section-title-badge">ROLE-SPECIFIC SUITE</span>
            <h2 class="h1 fw-bold text-white mb-2 brand-font">Tailored Operations by Stakeholder</h2>
            <p class="text-slate-400 small mx-auto" style="max-width: 580px;">
                Customized workflow toolsets built specifically for each role in the construction ecosystem.
            </p>
        </div>

        <div class="row g-4 text-start">
            <!-- CONTRACTORS SUITE -->
            <div class="col-lg-4">
                <div class="bc-card p-4.5 h-100 border-warning border-opacity-25">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="bc-login-header-icon mb-0" style="width: 48px; height: 48px; font-size: 1.25rem;">
                            <i class="fa-solid fa-city text-warning"></i>
                        </div>
                        <div>
                            <h3 class="h5 text-white fw-bold mb-0 brand-font">FOR CONTRACTORS</h3>
                            <span class="text-warning extra-small fw-semibold">Operations & Workforce Control</span>
                        </div>
                    </div>
                    <hr class="border-secondary opacity-25">
                    <ul class="list-unstyled text-slate-300 small mb-0 d-flex flex-column gap-2.5">
                        <li><i class="fa-solid fa-check text-warning me-2"></i> Create & manage projects</li>
                        <li><i class="fa-solid fa-check text-warning me-2"></i> Post trade job openings</li>
                        <li><i class="fa-solid fa-check text-warning me-2"></i> Manage worker hiring</li>
                        <li><i class="fa-solid fa-check text-warning me-2"></i> Assign site tasks</li>
                        <li><i class="fa-solid fa-check text-warning me-2"></i> Track QR attendance</li>
                        <li><i class="fa-solid fa-check text-warning me-2"></i> Issue & sign contracts</li>
                        <li><i class="fa-solid fa-check text-warning me-2"></i> Monitor project performance</li>
                        <li><i class="fa-solid fa-check text-warning me-2"></i> Access platform analytics</li>
                    </ul>
                    <div class="mt-4 pt-2">
                        <a href="<?= BASE_URL ?>/register.php?role=contractor" class="btn btn-amber w-100 py-2.5 fw-bold">Register as Contractor</a>
                    </div>
                </div>
            </div>

            <!-- WORKERS SUITE -->
            <div class="col-lg-4">
                <div class="bc-card p-4.5 h-100 border-warning border-opacity-25">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="bc-login-header-icon mb-0" style="width: 48px; height: 48px; font-size: 1.25rem;">
                            <i class="fa-solid fa-helmet-safety text-warning"></i>
                        </div>
                        <div>
                            <h3 class="h5 text-white fw-bold mb-0 brand-font">FOR WORKERS</h3>
                            <span class="text-warning extra-small fw-semibold">Career & Job Portal</span>
                        </div>
                    </div>
                    <hr class="border-secondary opacity-25">
                    <ul class="list-unstyled text-slate-300 small mb-0 d-flex flex-column gap-2.5">
                        <li><i class="fa-solid fa-check text-warning me-2"></i> Create professional profile</li>
                        <li><i class="fa-solid fa-check text-warning me-2"></i> Add verified trade skills</li>
                        <li><i class="fa-solid fa-check text-warning me-2"></i> Discover trade jobs</li>
                        <li><i class="fa-solid fa-check text-warning me-2"></i> Apply for open vacancies</li>
                        <li><i class="fa-solid fa-check text-warning me-2"></i> Track application status</li>
                        <li><i class="fa-solid fa-check text-warning me-2"></i> View assigned projects & tasks</li>
                        <li><i class="fa-solid fa-check text-warning me-2"></i> Clock-in with QR attendance</li>
                        <li><i class="fa-solid fa-check text-warning me-2"></i> Build platform reputation</li>
                    </ul>
                    <div class="mt-4 pt-2">
                        <a href="<?= BASE_URL ?>/register.php?role=worker" class="btn btn-amber w-100 py-2.5 fw-bold">Register as Worker</a>
                    </div>
                </div>
            </div>

            <!-- CLIENTS SUITE -->
            <div class="col-lg-4">
                <div class="bc-card p-4.5 h-100 border-warning border-opacity-25">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="bc-login-header-icon mb-0" style="width: 48px; height: 48px; font-size: 1.25rem;">
                            <i class="fa-solid fa-user-tie text-warning"></i>
                        </div>
                        <div>
                            <h3 class="h5 text-white fw-bold mb-0 brand-font">FOR CLIENTS</h3>
                            <span class="text-warning extra-small fw-semibold">Project Oversight & Monitoring</span>
                        </div>
                    </div>
                    <hr class="border-secondary opacity-25">
                    <ul class="list-unstyled text-slate-300 small mb-0 d-flex flex-column gap-2.5">
                        <li><i class="fa-solid fa-check text-warning me-2"></i> View construction projects</li>
                        <li><i class="fa-solid fa-check text-warning me-2"></i> Monitor real-time progress</li>
                        <li><i class="fa-solid fa-check text-warning me-2"></i> Track completion milestones</li>
                        <li><i class="fa-solid fa-check text-warning me-2"></i> Inspect project documents</li>
                        <li><i class="fa-solid fa-check text-warning me-2"></i> View analytical reports</li>
                        <li><i class="fa-solid fa-check text-warning me-2"></i> Receive instant notifications</li>
                        <li><i class="fa-solid fa-check text-warning me-2"></i> Monitor overall performance</li>
                    </ul>
                    <div class="mt-4 pt-2">
                        <a href="<?= BASE_URL ?>/register.php?role=client" class="btn btn-amber w-100 py-2.5 fw-bold">Register as Client</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Final Service CTA -->
<section class="py-5 bg-dark">
    <div class="container py-3">
        <div class="bc-cta-banner text-center">
            <h2 class="display-5 fw-bold text-white mb-3 brand-font">Everything Connected. One Powerful Platform.</h2>
            <p class="lead text-slate-300 mx-auto mb-4" style="max-width: 600px; font-size: 1.1rem;">
                Streamline workforce discovery, project tracking, attendance, and contracts today.
            </p>
            <div class="d-flex justify-content-center">
                <a href="<?= BASE_URL ?>/register.php" class="btn btn-amber-glow px-4.5 py-3 fw-bold fs-6">
                    Get Started with BuildConnect <i class="fa-solid fa-arrow-right ms-2"></i>
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
