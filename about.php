<?php
$page_title = "BuildConnect | Construction Management Platform";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

$flash = get_flash_message();
$user = get_logged_user();

// Fetch live database statistics securely
$contractor_count = 0;
$worker_count = 0;
$project_count = 0;
$contract_count = 0;

try {
    $db = getDB();
    $contractor_count = (int)$db->query("SELECT COUNT(*) FROM contractors")->fetchColumn();
    $worker_count = (int)$db->query("SELECT COUNT(*) FROM workers")->fetchColumn();
    $project_count = (int)$db->query("SELECT COUNT(*) FROM projects")->fetchColumn();
    $contract_count = (int)$db->query("SELECT COUNT(*) FROM contracts")->fetchColumn();
} catch (PDOException $e) {
    // Graceful fallback if database is loading
}
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
        <div class="row align-items-center g-4">
            <div class="col-lg-7 text-start">
                <span class="bc-section-title-badge">ABOUT BUILDCONNECT</span>
                <h1 class="display-3 fw-bold text-white mb-3 brand-font lh-sm">
                    Building the Future of<br>
                    <span class="text-warning">Construction Management</span>
                </h1>
                <p class="lead text-slate-300 mb-4 me-lg-4" style="max-width: 620px; font-size: 1.125rem; line-height: 1.65;">
                    BuildConnect brings contractors, workers, and clients together on one intelligent platform — making construction projects easier to manage, track, and grow.
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
                    <a href="<?= BASE_URL ?>/services.php" class="btn btn-outline-amber px-4.5 py-3 fw-semibold fs-6">
                        Explore Services <i class="fa-solid fa-arrow-right ms-1.5"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-5 d-none d-lg-block text-center">
                <div class="bc-card p-2 border-warning border-opacity-25 shadow-lg overflow-hidden" style="border-radius: 24px;">
                    <img src="<?= BASE_URL ?>/assets/images/construction-bg.jpg" alt="BuildConnect Construction Platform" class="img-fluid rounded-4" style="max-height: 380px; object-fit: cover; width: 100%;">
                </div>
            </div>
        </div>
    </div>
</header>

<!-- About Introduction -->
<section class="py-5 border-top border-secondary border-opacity-25 bg-dark">
    <div class="container py-4">
        <div class="text-center mb-5">
            <span class="bc-section-title-badge">INTEGRATED ECOSYSTEM</span>
            <h2 class="h1 fw-bold text-white mb-2 brand-font">One Platform. Every Construction Connection.</h2>
            <p class="text-slate-400 small mx-auto" style="max-width: 600px;">
                Unifying the entire construction value chain with real-time verification and workflow transparency.
            </p>
        </div>

        <div class="row g-4">
            <!-- CONTRACTORS FEATURE BLOCK -->
            <div class="col-md-4">
                <div class="bc-role-card h-100 d-flex flex-column text-start">
                    <div class="bc-login-header-icon mb-3" style="width: 56px; height: 56px; font-size: 1.5rem;">
                        <i class="fa-solid fa-city text-warning"></i>
                    </div>
                    <h3 class="h4 text-white fw-bold brand-font mb-2">Contractors</h3>
                    <p class="text-slate-400 small mb-0" style="line-height: 1.65;">
                        Manage projects, teams, jobs, tasks and workforce from one place with centralized control and AI worker matching.
                    </p>
                </div>
            </div>

            <!-- WORKERS FEATURE BLOCK -->
            <div class="col-md-4">
                <div class="bc-role-card h-100 d-flex flex-column text-start">
                    <div class="bc-login-header-icon mb-3" style="width: 56px; height: 56px; font-size: 1.5rem;">
                        <i class="fa-solid fa-helmet-safety text-warning"></i>
                    </div>
                    <h3 class="h4 text-white fw-bold brand-font mb-2">Workers</h3>
                    <p class="text-slate-400 small mb-0" style="line-height: 1.65;">
                        Discover opportunities, manage your professional profile, track work and build your reputation with verified trade ratings.
                    </p>
                </div>
            </div>

            <!-- CLIENTS FEATURE BLOCK -->
            <div class="col-md-4">
                <div class="bc-role-card h-100 d-flex flex-column text-start">
                    <div class="bc-login-header-icon mb-3" style="width: 56px; height: 56px; font-size: 1.5rem;">
                        <i class="fa-solid fa-user-tie text-warning"></i>
                    </div>
                    <h3 class="h4 text-white fw-bold brand-font mb-2">Clients</h3>
                    <p class="text-slate-400 small mb-0" style="line-height: 1.65;">
                        Monitor project progress, milestones, workforce activity and project updates through visual reporting dashboards.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Our Mission Section -->
<section class="py-5 border-top border-secondary border-opacity-25" style="background: rgba(15, 23, 42, 0.4);">
    <div class="container py-4">
        <div class="row align-items-center g-5">
            <div class="col-lg-6 text-start">
                <span class="bc-section-title-badge">OUR PURPOSE</span>
                <h2 class="h1 fw-bold text-white mb-3 brand-font">Our Mission</h2>
                <p class="lead text-slate-300 mb-4" style="line-height: 1.7; font-size: 1.1rem;">
                    "To simplify construction management by connecting the people, projects and opportunities that make every build possible."
                </p>
                <p class="text-slate-400 small mb-0" style="line-height: 1.65;">
                    Traditional construction project operations often suffer from fragmented communication, unverified skill credentials, and manual paper-based attendance tracking. BuildConnect standardizes this process by providing structured verification, transparent progress meters, digital contracts, and automated logs for all stakeholders.
                </p>
            </div>
            <div class="col-lg-6">
                <div class="bc-card p-4 text-start">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <i class="fa-solid fa-shield-halved text-warning fs-2"></i>
                        <div>
                            <h4 class="h6 text-white fw-bold mb-0">Verified Integrity</h4>
                            <span class="text-slate-400 extra-small">Ensuring credential accuracy across every site</span>
                        </div>
                    </div>
                    <hr class="border-secondary opacity-25">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <i class="fa-solid fa-chart-line text-warning fs-2"></i>
                        <div>
                            <h4 class="h6 text-white fw-bold mb-0">Transparent Operations</h4>
                            <span class="text-slate-400 extra-small">Real-time milestone tracking for developers</span>
                        </div>
                    </div>
                    <hr class="border-secondary opacity-25">
                    <div class="d-flex align-items-center gap-3">
                        <i class="fa-solid fa-network-wired text-warning fs-2"></i>
                        <div>
                            <h4 class="h6 text-white fw-bold mb-0">Connected Workforce</h4>
                            <span class="text-slate-400 extra-small">Connecting masons, electricians, & contractors</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Why BuildConnect Grid -->
<section class="py-5 border-top border-secondary border-opacity-25 bg-dark">
    <div class="container py-4">
        <div class="text-center mb-5">
            <span class="bc-section-title-badge">PLATFORM ADVANTAGES</span>
            <h2 class="h1 fw-bold text-white mb-2 brand-font">Why BuildConnect?</h2>
            <p class="text-slate-400 small mx-auto" style="max-width: 580px;">
                Key structural features engineered to deliver reliability across every job site.
            </p>
        </div>

        <div class="row g-4 text-start">
            <div class="col-md-6 col-lg-3">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-check-circle text-warning fs-4 mb-2.5"></i>
                    <h3 class="h6 text-white fw-bold mb-1.5 brand-font">One Connected Platform</h3>
                    <p class="text-slate-400 extra-small mb-0">Unifies workers, contractors, and clients in a single system.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-check-circle text-warning fs-4 mb-2.5"></i>
                    <h3 class="h6 text-white fw-bold mb-1.5 brand-font">Smarter Project Management</h3>
                    <p class="text-slate-400 extra-small mb-0">Structured milestone tracking, task boards, and schedules.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-check-circle text-warning fs-4 mb-2.5"></i>
                    <h3 class="h6 text-white fw-bold mb-1.5 brand-font">Verified Workforce</h3>
                    <p class="text-slate-400 extra-small mb-0">Admin-approved trade skills, experience, and documentation.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-check-circle text-warning fs-4 mb-2.5"></i>
                    <h3 class="h6 text-white fw-bold mb-1.5 brand-font">Real-Time Project Visibility</h3>
                    <p class="text-slate-400 extra-small mb-0">Live progress bars, milestone timelines, and project status updates.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-check-circle text-warning fs-4 mb-2.5"></i>
                    <h3 class="h6 text-white fw-bold mb-1.5 brand-font">Better Connections</h3>
                    <p class="text-slate-400 extra-small mb-0">Seamless job applications and trade requirement publishing.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-check-circle text-warning fs-4 mb-2.5"></i>
                    <h3 class="h6 text-white fw-bold mb-1.5 brand-font">Data-Driven Insights</h3>
                    <p class="text-slate-400 extra-small mb-0">Analytical dashboards tracking progress trends and shift metrics.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-check-circle text-warning fs-4 mb-2.5"></i>
                    <h3 class="h6 text-white fw-bold mb-1.5 brand-font">AI-Powered Assistance</h3>
                    <p class="text-slate-400 extra-small mb-0">Centralized AI evaluating worker match scores & project health.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="bc-feature-card">
                    <i class="fa-solid fa-check-circle text-warning fs-4 mb-2.5"></i>
                    <h3 class="h6 text-white fw-bold mb-1.5 brand-font">Transparent Communication</h3>
                    <p class="text-slate-400 extra-small mb-0">Standardized digital contracts and real-time alert notifications.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Trust / Stats Section -->
<section class="py-5 border-top border-secondary border-opacity-25" style="background: rgba(15, 23, 42, 0.4);">
    <div class="container py-4">
        <div class="text-center mb-5">
            <span class="bc-section-title-badge">PLATFORM METRICS</span>
            <h2 class="h1 fw-bold text-white mb-2 brand-font">Built for the Modern Construction Industry</h2>
            <p class="text-slate-400 small mx-auto" style="max-width: 580px;">
                Live database performance indicators across our active platform network.
            </p>
        </div>

        <div class="row g-4 text-center">
            <div class="col-6 col-md-3">
                <div class="bc-card p-4">
                    <div class="display-5 fw-bold text-warning mb-1 brand-font"><?= number_format($contractor_count) ?></div>
                    <div class="text-slate-300 small fw-semibold">Contractors</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="bc-card p-4">
                    <div class="display-5 fw-bold text-warning mb-1 brand-font"><?= number_format($worker_count) ?></div>
                    <div class="text-slate-300 small fw-semibold">Skilled Workers</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="bc-card p-4">
                    <div class="display-5 fw-bold text-warning mb-1 brand-font"><?= number_format($project_count) ?></div>
                    <div class="text-slate-300 small fw-semibold">Active Projects</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="bc-card p-4">
                    <div class="display-5 fw-bold text-warning mb-1 brand-font"><?= number_format($contract_count) ?></div>
                    <div class="text-slate-300 small fw-semibold">Contracts Executed</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Final Call to Action -->
<section class="py-5 bg-dark">
    <div class="container py-3">
        <div class="bc-cta-banner text-center">
            <h2 class="display-5 fw-bold text-white mb-3 brand-font">Ready to Build Smarter?</h2>
            <p class="lead text-slate-300 mx-auto mb-4" style="max-width: 600px; font-size: 1.1rem;">
                Join BuildConnect and bring your construction projects, workforce and clients together.
            </p>
            <div class="d-flex justify-content-center flex-wrap gap-3">
                <?php if ($user): ?>
                    <a href="<?= BASE_URL ?>/<?= get_role_redirect_url($user['role']) ?>" class="btn btn-amber-glow px-4.5 py-3 fw-bold fs-6">
                        Go to Dashboard <i class="fa-solid fa-arrow-right ms-2"></i>
                    </a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/register.php" class="btn btn-amber-glow px-4.5 py-3 fw-bold fs-6">
                        Create Account <i class="fa-solid fa-arrow-right ms-2"></i>
                    </a>
                    <a href="<?= BASE_URL ?>/login.php" class="btn btn-outline-amber px-4.5 py-3 fw-semibold fs-6">
                        Login
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
