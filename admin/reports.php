<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$page_title = "Platform Reports - Admin - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$current_user = currentUser();
$db = getDB();

// Aggregated Real Database Summaries
$users_by_role = $db->query("SELECT role, COUNT(*) as count FROM users GROUP BY role")->fetchAll();
$users_by_status = $db->query("SELECT status, COUNT(*) as count FROM users GROUP BY status")->fetchAll();
$workers_by_verification = $db->query("SELECT verification_status, COUNT(*) as count FROM workers GROUP BY verification_status")->fetchAll();
$projects_by_status = $db->query("SELECT status, COUNT(*) as count FROM projects GROUP BY status")->fetchAll();
$jobs_by_status = $db->query("SELECT status, COUNT(*) as count FROM jobs GROUP BY status")->fetchAll();
$apps_by_status = $db->query("SELECT status, COUNT(*) as count FROM job_applications GROUP BY status")->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-chart-pie text-success me-2"></i>Platform Summary Reports</h1>
                <p class="text-muted small mb-0">Real database metrics grouped by user roles, verification statuses, project states, and job applications.</p>
            </div>
            <button onclick="window.print()" class="btn btn-amber btn-sm"><i class="fa-solid fa-print me-1"></i> Print / Export Report</button>
        </div>

        <div class="row g-4 mb-4">
            <!-- Users by Role -->
            <div class="col-md-6 col-xl-4">
                <div class="bc-card p-4 h-100">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-users text-warning me-2"></i>Users by Role</h3>
                    <ul class="list-group list-group-flush bg-transparent">
                        <?php foreach ($users_by_role as $r): ?>
                            <li class="list-group-item bg-transparent text-light border-secondary px-0 py-2 d-flex justify-content-between align-items-center">
                                <span class="text-capitalize"><?= e($r['role']) ?></span>
                                <span class="badge bg-warning text-dark font-monospace fw-bold"><?= number_format($r['count']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Users by Status -->
            <div class="col-md-6 col-xl-4">
                <div class="bc-card p-4 h-100">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-user-check text-info me-2"></i>Users by Account Status</h3>
                    <ul class="list-group list-group-flush bg-transparent">
                        <?php foreach ($users_by_status as $s): ?>
                            <li class="list-group-item bg-transparent text-light border-secondary px-0 py-2 d-flex justify-content-between align-items-center">
                                <span class="text-capitalize"><?= e($s['status']) ?></span>
                                <span class="badge <?= get_status_badge_class($s['status']) ?> font-monospace fw-bold"><?= number_format($s['count']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Workers by Verification Status -->
            <div class="col-md-6 col-xl-4">
                <div class="bc-card p-4 h-100">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-id-card text-success me-2"></i>Workers by Verification</h3>
                    <ul class="list-group list-group-flush bg-transparent">
                        <?php foreach ($workers_by_verification as $wv): ?>
                            <li class="list-group-item bg-transparent text-light border-secondary px-0 py-2 d-flex justify-content-between align-items-center">
                                <span class="text-capitalize"><?= e($wv['verification_status']) ?></span>
                                <span class="badge <?= get_status_badge_class($wv['verification_status']) ?> font-monospace fw-bold"><?= number_format($wv['count']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Projects by Status -->
            <div class="col-md-6 col-xl-4">
                <div class="bc-card p-4 h-100">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-building text-warning me-2"></i>Projects by Status</h3>
                    <ul class="list-group list-group-flush bg-transparent">
                        <?php foreach ($projects_by_status as $ps): ?>
                            <li class="list-group-item bg-transparent text-light border-secondary px-0 py-2 d-flex justify-content-between align-items-center">
                                <span class="text-capitalize"><?= e(str_replace('_', ' ', $ps['status'])) ?></span>
                                <span class="badge <?= get_status_badge_class($ps['status']) ?> font-monospace fw-bold"><?= number_format($ps['count']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Jobs by Status -->
            <div class="col-md-6 col-xl-4">
                <div class="bc-card p-4 h-100">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-briefcase text-info me-2"></i>Jobs by Availability</h3>
                    <ul class="list-group list-group-flush bg-transparent">
                        <?php foreach ($jobs_by_status as $js): ?>
                            <li class="list-group-item bg-transparent text-light border-secondary px-0 py-2 d-flex justify-content-between align-items-center">
                                <span class="text-capitalize"><?= e($js['status']) ?></span>
                                <span class="badge <?= get_status_badge_class($js['status']) ?> font-monospace fw-bold"><?= number_format($js['count']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Applications by Status -->
            <div class="col-md-6 col-xl-4">
                <div class="bc-card p-4 h-100">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-file-signature text-purple me-2"></i>Applications by Status</h3>
                    <ul class="list-group list-group-flush bg-transparent">
                        <?php foreach ($apps_by_status as $as): ?>
                            <li class="list-group-item bg-transparent text-light border-secondary px-0 py-2 d-flex justify-content-between align-items-center">
                                <span class="text-capitalize"><?= e($as['status']) ?></span>
                                <span class="badge <?= get_status_badge_class($as['status']) ?> font-monospace fw-bold"><?= number_format($as['count']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
