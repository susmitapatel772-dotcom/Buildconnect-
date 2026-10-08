<?php
$user = get_logged_user();
$current_page = $_SERVER['PHP_SELF'];

if (!function_exists('is_active')) {
    function is_active($path, $current_page) {
        return (strpos($current_page, $path) !== false) ? 'active' : '';
    }
}

$is_worker = ($user && isset($user['role']) && $user['role'] === ROLE_WORKER);
$is_admin  = ($user && isset($user['role']) && $user['role'] === ROLE_ADMIN);
$is_client = ($user && isset($user['role']) && $user['role'] === ROLE_CLIENT);
$is_contractor = ($user && isset($user['role']) && $user['role'] === ROLE_CONTRACTOR);

$sidebar_class = 'bc-sidebar';
$sidebar_id = '';

if ($is_worker) {
    $sidebar_class .= ' bc-sidebar-worker';
    $sidebar_id = 'id="bc-worker-sidebar"';
} elseif ($is_admin) {
    $sidebar_class .= ' bc-sidebar-admin';
    $sidebar_id = 'id="bc-admin-sidebar"';
} elseif ($is_client) {
    $sidebar_class .= ' bc-sidebar-client';
    $sidebar_id = 'id="bc-client-sidebar"';
} elseif ($is_contractor) {
    $sidebar_class .= ' bc-sidebar-contractor';
    $sidebar_id = 'id="bc-contractor-sidebar"';
}
?>
<aside class="<?= $sidebar_class ?>" <?= $sidebar_id ?>>

    <div class="px-2 mb-3">
        <div class="text-uppercase text-muted extra-small fw-bold tracking-wider" style="font-size: 0.68rem; color: var(--bc-text-secondary) !important;">
            <?= $user ? sanitize(strtoupper($user['role'])) . ' CONTROL CENTER' : 'NAVIGATION' ?>
        </div>
    </div>
    
    <ul class="bc-sidebar-nav">
        <?php if (!$user): ?>
            <li class="bc-nav-item <?= is_active('/index.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/index.php"><i class="fa-solid fa-house"></i> Home Overview</a>
            </li>
            <li class="bc-nav-item <?= is_active('/login.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/login.php"><i class="fa-solid fa-right-to-bracket"></i> Login</a>
            </li>
            <li class="bc-nav-item <?= is_active('/register.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/register.php"><i class="fa-solid fa-user-plus"></i> Register</a>
            </li>

        <?php elseif ($user['role'] === ROLE_ADMIN): ?>
            <!-- MAIN -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-2 mb-1" style="font-size: 0.65rem;">MAIN</li>
            <li class="bc-nav-item <?= is_active('/admin/index.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/index.php"><i class="fa-solid fa-gauge-high text-warning"></i> Dashboard</a>
            </li>
            <li class="bc-nav-item <?= is_active('/admin/users.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/users.php"><i class="fa-solid fa-users text-info"></i> Users</a>
            </li>
            <li class="bc-nav-item <?= is_active('/admin/contractors.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/contractors.php"><i class="fa-solid fa-building text-warning"></i> Companies & Contractors</a>
            </li>
            <li class="bc-nav-item <?= is_active('/admin/clients.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/clients.php"><i class="fa-solid fa-user-tie text-info"></i> Clients</a>
            </li>
            <li class="bc-nav-item <?= is_active('/admin/verification.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/verification.php" class="d-flex justify-content-between align-items-center">
                    <span><i class="fa-solid fa-id-card text-success me-2"></i>Verification Queue</span>
                    <span class="badge rounded-circle bg-warning text-dark px-1.5 py-0.5 extra-small">8</span>
                </a>
            </li>

            <!-- PROJECTS & WORK -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-3 mb-1" style="font-size: 0.65rem;">PROJECTS & WORK</li>
            <li class="bc-nav-item <?= is_active('/admin/projects.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/projects.php"><i class="fa-solid fa-building text-info"></i> Projects</a>
            </li>
            <li class="bc-nav-item <?= is_active('/admin/jobs.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/jobs.php"><i class="fa-solid fa-briefcase text-warning"></i> Jobs</a>
            </li>
            <li class="bc-nav-item <?= is_active('/admin/applications.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/applications.php"><i class="fa-solid fa-file-signature text-success"></i> Applications</a>
            </li>
            <li class="bc-nav-item <?= is_active('/admin/reviews.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/reviews.php" class="d-flex justify-content-between align-items-center">
                    <span><i class="fa-solid fa-star text-warning me-2"></i>Review Moderation</span>
                    <span class="badge rounded-circle bg-danger text-white px-1.5 py-0.5 extra-small">2</span>
                </a>
            </li>

            <!-- ANALYTICS -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-3 mb-1" style="font-size: 0.65rem;">ANALYTICS</li>
            <li class="bc-nav-item <?= is_active('/admin/analytics.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/analytics.php"><i class="fa-solid fa-chart-line text-warning"></i> Platform Analytics</a>
            </li>
            <li class="bc-nav-item <?= is_active('/admin/ai-usage.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/ai-usage.php"><i class="fa-solid fa-brain text-info"></i> AI Usage Monitor</a>
            </li>

            <!-- SYSTEM -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-3 mb-1" style="font-size: 0.65rem;">SYSTEM</li>
            <li class="bc-nav-item <?= is_active('/admin/audit-logs.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/audit-logs.php"><i class="fa-solid fa-shield-halved text-success"></i> Audit Logs</a>
            </li>
            <li class="bc-nav-item <?= is_active('/notifications.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/notifications.php"><i class="fa-solid fa-bell text-warning"></i> Notifications</a>
            </li>
            <li class="bc-nav-item <?= is_active('/admin/settings.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/settings.php"><i class="fa-solid fa-sliders text-info"></i> Settings</a>
            </li>

            <!-- ACCOUNT -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-3 mb-1" style="font-size: 0.65rem;">ACCOUNT</li>
            <li class="bc-nav-item">
                <a href="<?= BASE_URL ?>/logout.php" class="text-danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </li>

        <?php elseif ($user['role'] === ROLE_WORKER): ?>
            <!-- MAIN -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-2 mb-1" style="font-size: 0.65rem;">MAIN</li>
            <li class="bc-nav-item <?= is_active('/worker/index.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/worker/index.php"><i class="fa-solid fa-gauge-high text-warning"></i> Dashboard</a>
            </li>
            <li class="bc-nav-item <?= is_active('/worker/analytics.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/worker/analytics.php"><i class="fa-solid fa-chart-line text-success"></i> My Analytics</a>
            </li>

            <!-- MY PROFILE -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-3 mb-1" style="font-size: 0.65rem;">MY PROFILE</li>
            <li class="bc-nav-item <?= is_active('/worker/profile.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/worker/profile.php"><i class="fa-solid fa-id-card text-info"></i> Profile</a>
            </li>
            <li class="bc-nav-item <?= is_active('/worker/skills.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/worker/skills.php"><i class="fa-solid fa-screwdriver-wrench text-warning"></i> Skills</a>
            </li>
            <li class="bc-nav-item <?= is_active('/worker/experience.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/worker/experience.php"><i class="fa-solid fa-briefcase text-info"></i> Experience</a>
            </li>
            <li class="bc-nav-item <?= is_active('/worker/availability.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/worker/availability.php"><i class="fa-solid fa-calendar-check text-success"></i> Availability</a>
            </li>
            <li class="bc-nav-item <?= is_active('/worker/documents.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/worker/documents.php"><i class="fa-solid fa-file-contract text-primary"></i> Documents</a>
            </li>

            <!-- WORK & CONTRACTS -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-3 mb-1" style="font-size: 0.65rem;">WORK & CONTRACTS</li>
            <li class="bc-nav-item <?= is_active('/worker/projects.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/worker/projects.php"><i class="fa-solid fa-building text-warning"></i> My Projects</a>
            </li>
            <li class="bc-nav-item <?= is_active('/worker/jobs.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/worker/jobs.php"><i class="fa-solid fa-magnifying-glass text-info"></i> Jobs</a>
            </li>
            <li class="bc-nav-item <?= is_active('/worker/applications.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/worker/applications.php"><i class="fa-solid fa-file-signature text-success"></i> Applications</a>
            </li>
            <li class="bc-nav-item <?= is_active('/worker/contracts.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/worker/contracts.php"><i class="fa-solid fa-file-contract text-success"></i> Digital Contracts</a>
            </li>
            <li class="bc-nav-item <?= is_active('/worker/attendance.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/worker/attendance.php"><i class="fa-solid fa-qrcode text-warning"></i> Attendance</a>
            </li>

            <!-- ACCOUNT -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-3 mb-1" style="font-size: 0.65rem;">ACCOUNT</li>
            <li class="bc-nav-item <?= is_active('/notifications.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/notifications.php"><i class="fa-solid fa-bell text-warning"></i> Notifications</a>
            </li>
            <li class="bc-nav-item <?= is_active('/worker/settings.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/worker/settings.php"><i class="fa-solid fa-sliders text-info"></i> Settings</a>
            </li>
            <li class="bc-nav-item mt-1">
                <a href="<?= BASE_URL ?>/logout.php" class="text-danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </li>

        <?php elseif ($user['role'] === ROLE_CLIENT): ?>
            <!-- MAIN -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-2 mb-1" style="font-size: 0.65rem;">MAIN</li>
            <li class="bc-nav-item <?= is_active('/client/index.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/client/index.php"><i class="fa-solid fa-gauge-high text-warning"></i> Dashboard</a>
            </li>
            <li class="bc-nav-item <?= is_active('/client/projects.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/client/projects.php"><i class="fa-solid fa-building text-info"></i> My Projects</a>
            </li>

            <!-- PROJECT MANAGEMENT -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-3 mb-1" style="font-size: 0.65rem;">PROJECT MANAGEMENT</li>
            <li class="bc-nav-item <?= is_active('/client/milestones.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/client/milestones.php"><i class="fa-solid fa-flag-checkered text-warning"></i> Milestones</a>
            </li>
            <li class="bc-nav-item <?= is_active('/client/tasks.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/client/tasks.php"><i class="fa-solid fa-list-check text-info"></i> Tasks</a>
            </li>

            <!-- FINANCE -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-3 mb-1" style="font-size: 0.65rem;">FINANCE</li>
            <li class="bc-nav-item <?= is_active('/client/payments.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/client/payments.php"><i class="fa-solid fa-indian-rupee-sign text-success"></i> Payments</a>
            </li>
            <li class="bc-nav-item <?= is_active('/client/contracts.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/client/contracts.php"><i class="fa-solid fa-file-contract text-warning"></i> Contracts</a>
            </li>

            <!-- MONITORING -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-3 mb-1" style="font-size: 0.65rem;">MONITORING</li>
            <li class="bc-nav-item <?= is_active('/client/progress.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/client/progress.php"><i class="fa-solid fa-chart-column text-success"></i> Progress Reports</a>
            </li>
            <li class="bc-nav-item <?= is_active('/client/reviews.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/client/reviews.php"><i class="fa-solid fa-star text-warning"></i> Reviews</a>
            </li>

            <!-- ACCOUNT -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-3 mb-1" style="font-size: 0.65rem;">ACCOUNT</li>
            <li class="bc-nav-item <?= is_active('/client/profile.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/client/profile.php"><i class="fa-solid fa-id-card text-info"></i> Profile</a>
            </li>
            <li class="bc-nav-item <?= is_active('/notifications.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/notifications.php"><i class="fa-solid fa-bell text-warning"></i> Notifications</a>
            </li>
            <li class="bc-nav-item <?= is_active('/client/settings.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/client/settings.php"><i class="fa-solid fa-sliders text-info"></i> Settings</a>
            </li>
            <li class="bc-nav-item mt-1">
                <a href="<?= BASE_URL ?>/logout.php" class="text-danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </li>

        <?php elseif ($user['role'] === ROLE_CONTRACTOR): ?>
            <!-- DASHBOARD -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-2 mb-1" style="font-size: 0.65rem;">MAIN</li>
            <li class="bc-nav-item <?= is_active('/contractor/index.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/contractor/index.php"><i class="fa-solid fa-gauge-high text-warning"></i> Dashboard</a>
            </li>

            <!-- AI ASSISTANCE -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-3 mb-1" style="font-size: 0.65rem;">AI Intelligence</li>
            <li class="bc-nav-item <?= is_active('/contractor/job-matches.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/contractor/job-matches.php"><i class="fa-solid fa-wand-magic-sparkles text-warning"></i> AI Worker Matching</a>
            </li>
            <li class="bc-nav-item <?= is_active('/contractor/project-insights.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/contractor/project-insights.php"><i class="fa-solid fa-brain text-info"></i> Project Health & Risk</a>
            </li>

            <!-- PROJECTS & ANALYTICS -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-3 mb-1" style="font-size: 0.65rem;">Projects & Analytics</li>
            <li class="bc-nav-item <?= is_active('/contractor/analytics.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/contractor/analytics.php"><i class="fa-solid fa-chart-line text-warning"></i> Analytics Engine</a>
            </li>
            <li class="bc-nav-item <?= is_active('/contractor/projects.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/contractor/projects.php"><i class="fa-solid fa-building text-info"></i> All Projects</a>
            </li>
            <li class="bc-nav-item <?= is_active('/contractor/create-project.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/contractor/create-project.php"><i class="fa-solid fa-plus-circle text-success"></i> Create Project</a>
            </li>

            <!-- WORKFORCE -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-3 mb-1" style="font-size: 0.65rem;">Workforce & Contracts</li>
            <li class="bc-nav-item <?= is_active('/contractor/workers.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/contractor/workers.php"><i class="fa-solid fa-helmet-safety text-info"></i> Workers</a>
            </li>
            <li class="bc-nav-item <?= is_active('/contractor/contracts.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/contractor/contracts.php"><i class="fa-solid fa-file-contract text-warning"></i> Digital Contracts</a>
            </li>
            <li class="bc-nav-item <?= is_active('/contractor/jobs.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/contractor/jobs.php"><i class="fa-solid fa-briefcase text-warning"></i> Jobs</a>
            </li>

            <!-- ACCOUNT -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-3 mb-1" style="font-size: 0.65rem;">ACCOUNT</li>
            <li class="bc-nav-item <?= is_active('/notifications.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/notifications.php"><i class="fa-solid fa-bell text-warning"></i> Notifications</a>
            </li>
            <li class="bc-nav-item <?= is_active('/contractor/profile.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/contractor/profile.php"><i class="fa-solid fa-user-gear text-info"></i> Profile</a>
            </li>
            <li class="bc-nav-item <?= is_active('/contractor/settings.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/contractor/settings.php"><i class="fa-solid fa-sliders text-info"></i> Settings</a>
            </li>
            <li class="bc-nav-item mt-1">
                <a href="<?= BASE_URL ?>/logout.php" class="text-danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </li>
        <?php endif; ?>
    </ul>

    <!-- Bottom Weather & Location Widget -->
    <div class="bc-sidebar-weather-widget mt-4">
        <div class="d-flex align-items-center justify-content-between text-white">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-cloud-sun text-warning fs-5"></i>
                <div>
                    <div class="fw-bold small">32°C</div>
                    <div class="text-muted extra-small" style="font-size: 0.65rem; color: var(--bc-text-secondary) !important;">Partly cloudy</div>
                </div>
            </div>
            <div class="text-muted extra-small text-end" style="font-size: 0.65rem; color: var(--bc-text-secondary) !important;">
                <i class="fa-solid fa-location-dot me-1 text-danger"></i>New Delhi, India
            </div>
        </div>
    </div>
</aside>
