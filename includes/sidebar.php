<?php
$user = get_logged_user();
$current_page = $_SERVER['PHP_SELF'];

function is_active($path, $current_page) {
    return (strpos($current_page, $path) !== false) ? 'active' : '';
}
?>
<aside class="bc-sidebar">
    <div class="px-2 mb-3">
        <div class="text-uppercase text-muted extra-small fw-bold tracking-wider" style="font-size: 0.7rem;">
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
            <!-- Dashboard -->
            <li class="bc-nav-item <?= is_active('/admin/index.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/index.php"><i class="fa-solid fa-gauge-high text-warning"></i> Dashboard</a>
            </li>

            <!-- USER MANAGEMENT -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-3 mb-1" style="font-size: 0.65rem;">User Management</li>
            <li class="bc-nav-item <?= is_active('/admin/users.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/users.php"><i class="fa-solid fa-users"></i> All Users</a>
            </li>
            <li class="bc-nav-item <?= is_active('/admin/workers.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/workers.php"><i class="fa-solid fa-helmet-safety text-warning"></i> Workers</a>
            </li>
            <li class="bc-nav-item <?= is_active('/admin/contractors.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/contractors.php"><i class="fa-solid fa-city text-info"></i> Contractors</a>
            </li>
            <li class="bc-nav-item <?= is_active('/admin/clients.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/clients.php"><i class="fa-solid fa-user-tie"></i> Clients</a>
            </li>
            <li class="bc-nav-item <?= is_active('/admin/verification.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/verification.php"><i class="fa-solid fa-id-card text-success"></i> Verification Queue</a>
            </li>

            <!-- PLATFORM -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-3 mb-1" style="font-size: 0.65rem;">Platform</li>
            <li class="bc-nav-item <?= is_active('/admin/projects.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/projects.php"><i class="fa-solid fa-building"></i> Projects</a>
            </li>
            <li class="bc-nav-item <?= is_active('/admin/project-map.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/project-map.php"><i class="fa-solid fa-map-location-dot text-warning"></i> Platform Map</a>
            </li>
            <li class="bc-nav-item <?= is_active('/admin/jobs.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/jobs.php"><i class="fa-solid fa-briefcase"></i> Jobs</a>
            </li>
            <li class="bc-nav-item <?= is_active('/admin/applications.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/applications.php"><i class="fa-solid fa-file-signature"></i> Applications</a>
            </li>
            <li class="bc-nav-item <?= is_active('/admin/reviews.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/reviews.php"><i class="fa-solid fa-star text-warning"></i> Review Moderation</a>
            </li>

            <!-- REPORTS & AI MONITORING -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-3 mb-1" style="font-size: 0.65rem;">Analytics & AI</li>
            <li class="bc-nav-item <?= is_active('/admin/analytics.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/analytics.php"><i class="fa-solid fa-chart-line text-warning"></i> Platform Analytics</a>
            </li>
            <li class="bc-nav-item <?= is_active('/admin/ai-usage.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/ai-usage.php"><i class="fa-solid fa-brain text-info"></i> AI Usage Monitor</a>
            </li>
            <li class="bc-nav-item <?= is_active('/admin/reports.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/reports.php"><i class="fa-solid fa-chart-pie text-success"></i> Reports</a>
            </li>
            <li class="bc-nav-item <?= is_active('/admin/activity-logs.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/activity-logs.php"><i class="fa-solid fa-list-check"></i> Activity Logs</a>
            </li>

            <!-- SYSTEM -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-3 mb-1" style="font-size: 0.65rem;">System</li>
            <li class="bc-nav-item <?= is_active('/notifications.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/notifications.php"><i class="fa-solid fa-bell text-warning"></i> Notifications</a>
            </li>
            <li class="bc-nav-item <?= is_active('/admin/settings.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/admin/settings.php"><i class="fa-solid fa-sliders"></i> Settings</a>
            </li>
            <li class="bc-nav-item">
                <a href="<?= BASE_URL ?>/logout.php" class="text-danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </li>

        <?php elseif ($user['role'] === ROLE_CONTRACTOR): ?>
            <!-- DASHBOARD -->
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
                <a href="<?= BASE_URL ?>/contractor/projects.php"><i class="fa-solid fa-building"></i> All Projects</a>
            </li>
            <li class="bc-nav-item <?= is_active('/contractor/project-map.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/contractor/project-map.php"><i class="fa-solid fa-map-location-dot text-info"></i> Projects Map</a>
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
            <li class="bc-nav-item <?= is_active('/contractor/reviews.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/contractor/reviews.php"><i class="fa-solid fa-star text-warning"></i> Worker Reviews</a>
            </li>

            <!-- JOBS -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-3 mb-1" style="font-size: 0.65rem;">Jobs</li>
            <li class="bc-nav-item <?= is_active('/contractor/jobs.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/contractor/jobs.php"><i class="fa-solid fa-briefcase text-warning"></i> Jobs</a>
            </li>

            <!-- ACCOUNT -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-3 mb-1" style="font-size: 0.65rem;">Account</li>
            <li class="bc-nav-item <?= is_active('/notifications.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/notifications.php"><i class="fa-solid fa-bell text-warning"></i> Notifications</a>
            </li>
            <li class="bc-nav-item <?= is_active('/contractor/profile.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/contractor/profile.php"><i class="fa-solid fa-user-gear"></i> Profile</a>
            </li>
            <li class="bc-nav-item <?= is_active('/contractor/settings.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/contractor/settings.php"><i class="fa-solid fa-sliders"></i> Settings</a>
            </li>
            <li class="bc-nav-item">
                <a href="<?= BASE_URL ?>/logout.php" class="text-danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </li>

        <?php elseif ($user['role'] === ROLE_WORKER): ?>
            <!-- DASHBOARD -->
            <li class="bc-nav-item <?= is_active('/worker/index.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/worker/index.php"><i class="fa-solid fa-gauge-high text-warning"></i> Dashboard</a>
            </li>
            <li class="bc-nav-item <?= is_active('/worker/analytics.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/worker/analytics.php"><i class="fa-solid fa-chart-line text-success"></i> My Analytics</a>
            </li>

            <!-- MY PROFILE -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-3 mb-1" style="font-size: 0.65rem;">My Profile</li>
            <li class="bc-nav-item <?= is_active('/worker/profile.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/worker/profile.php"><i class="fa-solid fa-id-card"></i> Profile</a>
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

            <!-- WORK -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-3 mb-1" style="font-size: 0.65rem;">Work & Contracts</li>
            <li class="bc-nav-item <?= is_active('/worker/projects.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/worker/projects.php"><i class="fa-solid fa-building text-warning"></i> My Projects</a>
            </li>
            <li class="bc-nav-item <?= is_active('/worker/jobs.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/worker/jobs.php"><i class="fa-solid fa-magnifying-glass text-info"></i> Jobs</a>
            </li>
            <li class="bc-nav-item <?= is_active('/worker/applications.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/worker/applications.php"><i class="fa-solid fa-file-signature"></i> Applications</a>
            </li>
            <li class="bc-nav-item <?= is_active('/worker/contracts.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/worker/contracts.php"><i class="fa-solid fa-file-contract text-success"></i> Digital Contracts</a>
            </li>
            <li class="bc-nav-item <?= is_active('/worker/reviews.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/worker/reviews.php"><i class="fa-solid fa-star text-warning"></i> My Reviews</a>
            </li>

            <!-- ACCOUNT -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-3 mb-1" style="font-size: 0.65rem;">Account</li>
            <li class="bc-nav-item <?= is_active('/notifications.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/notifications.php"><i class="fa-solid fa-bell text-warning"></i> Notifications</a>
            </li>
            <li class="bc-nav-item <?= is_active('/worker/settings.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/worker/settings.php"><i class="fa-solid fa-sliders"></i> Settings</a>
            </li>
            <li class="bc-nav-item">
                <a href="<?= BASE_URL ?>/logout.php" class="text-danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </li>

        <?php elseif ($user['role'] === ROLE_CLIENT): ?>
            <!-- DASHBOARD -->
            <li class="bc-nav-item <?= is_active('/client/index.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/client/index.php"><i class="fa-solid fa-gauge-high text-warning"></i> Dashboard</a>
            </li>

            <!-- PROJECTS & ANALYTICS -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-3 mb-1" style="font-size: 0.65rem;">Projects & Analytics</li>
            <li class="bc-nav-item <?= is_active('/client/analytics.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/client/analytics.php"><i class="fa-solid fa-chart-line text-warning"></i> Analytics Engine</a>
            </li>
            <li class="bc-nav-item <?= is_active('/client/projects.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/client/projects.php"><i class="fa-solid fa-building text-info"></i> My Projects</a>
            </li>
            <li class="bc-nav-item <?= is_active('/client/progress.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/client/progress.php"><i class="fa-solid fa-chart-column text-success"></i> Project Progress</a>
            </li>
            <li class="bc-nav-item <?= is_active('/client/milestones.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/client/milestones.php"><i class="fa-solid fa-flag-checkered text-warning"></i> Milestones</a>
            </li>
            <li class="bc-nav-item <?= is_active('/client/documents.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/client/documents.php"><i class="fa-solid fa-file-contract text-primary"></i> Documents</a>
            </li>
            <li class="bc-nav-item <?= is_active('/client/reports.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/client/reports.php"><i class="fa-solid fa-chart-pie text-success"></i> Reports</a>
            </li>

            <!-- ACCOUNT -->
            <li class="bc-nav-header text-uppercase text-muted extra-small font-semibold px-3 mt-3 mb-1" style="font-size: 0.65rem;">Account</li>
            <li class="bc-nav-item <?= is_active('/client/profile.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/client/profile.php"><i class="fa-solid fa-id-card"></i> Profile</a>
            </li>
            <li class="bc-nav-item <?= is_active('/notifications.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/notifications.php"><i class="fa-solid fa-bell text-warning"></i> Notifications</a>
            </li>
            <li class="bc-nav-item <?= is_active('/client/settings.php', $current_page) ?>">
                <a href="<?= BASE_URL ?>/client/settings.php"><i class="fa-solid fa-sliders"></i> Settings</a>
            </li>
            <li class="bc-nav-item">
                <a href="<?= BASE_URL ?>/logout.php" class="text-danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </li>
        <?php endif; ?>
    </ul>
</aside>
