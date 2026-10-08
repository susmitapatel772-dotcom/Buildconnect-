<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$page_title = "Admin Executive Dashboard - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();

// Efficient DB COUNT Queries for real statistics
$total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_workers = $db->query("SELECT COUNT(*) FROM users WHERE role = 'worker'")->fetchColumn();
$total_contractors = $db->query("SELECT COUNT(*) FROM users WHERE role = 'contractor'")->fetchColumn();
$total_clients = $db->query("SELECT COUNT(*) FROM users WHERE role = 'client'")->fetchColumn();

$total_projects = $db->query("SELECT COUNT(*) FROM projects")->fetchColumn() ?: 0;
$active_jobs = $db->query("SELECT COUNT(*) FROM jobs WHERE status = 'open'")->fetchColumn() ?: 0;
$pending_applications = $db->query("SELECT COUNT(*) FROM job_applications WHERE status = 'pending'")->fetchColumn() ?: 0;
$pending_verifications = $db->query("SELECT COUNT(*) FROM workers WHERE verification_status = 'pending'")->fetchColumn() ?: 0;

// Recent Activity Logs
$activity_stmt = $db->query("
    SELECT a.*, u.name as user_name, u.email as user_email, u.role as user_role 
    FROM activity_logs a 
    LEFT JOIN users u ON a.user_id = u.id 
    ORDER BY a.id DESC LIMIT 8
");
$recent_activities = $activity_stmt->fetchAll();

// Recent Registrations
$recent_users = $db->query("SELECT id, name, email, role, status, created_at FROM users ORDER BY id DESC LIMIT 5")->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-gauge-high text-warning me-2"></i>Executive Admin Control Panel</h1>
                <p class="text-muted small mb-0">Platform overview, database statistics, and system activity logs.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/admin/verification.php" class="btn btn-amber btn-sm position-relative fw-bold">
                    <i class="fa-solid fa-id-card me-1"></i> Verification Queue
                    <?php if ($pending_verifications > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?= $pending_verifications ?>
                        </span>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <!-- Real Statistics Metric Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-sm-6">
                <div class="bc-card p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted extra-small uppercase fw-semibold">Total Users</span>
                        <div class="stat-icon amber p-2 rounded"><i class="fa-solid fa-users"></i></div>
                    </div>
                    <div class="fs-3 fw-bold text-white"><?= number_format($total_users) ?></div>
                    <div class="text-muted extra-small mt-1">Platform Accounts</div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="bc-card p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted extra-small uppercase fw-semibold">Workers</span>
                        <div class="stat-icon emerald p-2 rounded"><i class="fa-solid fa-helmet-safety"></i></div>
                    </div>
                    <div class="fs-3 fw-bold text-emerald"><?= number_format($total_workers) ?></div>
                    <div class="text-muted extra-small mt-1"><?= $pending_verifications ?> Pending Verifications</div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="bc-card p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted extra-small uppercase fw-semibold">Contractors</span>
                        <div class="stat-icon blue p-2 rounded"><i class="fa-solid fa-city"></i></div>
                    </div>
                    <div class="fs-3 fw-bold text-info"><?= number_format($total_contractors) ?></div>
                    <div class="text-muted extra-small mt-1">Registered Companies</div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="bc-card p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted extra-small uppercase fw-semibold">Clients</span>
                        <div class="stat-icon purple p-2 rounded"><i class="fa-solid fa-user-tie"></i></div>
                    </div>
                    <div class="fs-3 fw-bold text-warning"><?= number_format($total_clients) ?></div>
                    <div class="text-muted extra-small mt-1">Project Owners</div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="bc-card p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted extra-small uppercase fw-semibold">Total Projects</span>
                        <div class="stat-icon amber p-2 rounded"><i class="fa-solid fa-building"></i></div>
                    </div>
                    <div class="fs-3 fw-bold text-white"><?= number_format($total_projects) ?></div>
                    <div class="text-muted extra-small mt-1">Active Developments</div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="bc-card p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted extra-small uppercase fw-semibold">Active Jobs</span>
                        <div class="stat-icon blue p-2 rounded"><i class="fa-solid fa-briefcase"></i></div>
                    </div>
                    <div class="fs-3 fw-bold text-info"><?= number_format($active_jobs) ?></div>
                    <div class="text-muted extra-small mt-1">Open Positions</div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="bc-card p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted extra-small uppercase fw-semibold">Applications</span>
                        <div class="stat-icon purple p-2 rounded"><i class="fa-solid fa-file-signature"></i></div>
                    </div>
                    <div class="fs-3 fw-bold text-warning"><?= number_format($pending_applications) ?></div>
                    <div class="text-muted extra-small mt-1">Pending Review</div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="bc-card p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted extra-small uppercase fw-semibold">Verification Queue</span>
                        <div class="stat-icon emerald p-2 rounded"><i class="fa-solid fa-id-card"></i></div>
                    </div>
                    <div class="fs-3 fw-bold text-success"><?= number_format($pending_verifications) ?></div>
                    <div class="text-muted extra-small mt-1">Action Required</div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Distribution Chart -->
            <div class="col-lg-6">
                <div class="bc-card p-4 h-100">
                    <h3 class="h5 text-white fw-bold mb-3"><i class="fa-solid fa-chart-pie text-warning me-2"></i>User Roles Distribution</h3>
                    <div style="height: 240px; position: relative;">
                        <canvas id="roleDistributionChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Recent Registrations -->
            <div class="col-lg-6">
                <div class="bc-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h5 text-white fw-bold mb-0"><i class="fa-solid fa-user-plus text-info me-2"></i>Recent User Registrations</h3>
                        <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline-secondary btn-sm extra-small">View All Users</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_users as $ru): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-white small"><?= e($ru['name']) ?></div>
                                            <div class="text-muted extra-small"><?= e($ru['email']) ?></div>
                                        </td>
                                        <td><span class="badge bg-dark border border-secondary text-uppercase"><?= e($ru['role']) ?></span></td>
                                        <td><span class="badge <?= get_status_badge_class($ru['status']) ?>"><?= e($ru['status']) ?></span></td>
                                        <td class="text-muted extra-small"><?= format_date($ru['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Log Stream -->
        <div class="bc-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h5 text-white fw-bold mb-0"><i class="fa-solid fa-list-check text-success me-2"></i>Recent System Activity Stream</h3>
                <a href="<?= BASE_URL ?>/admin/activity-logs.php" class="btn btn-outline-secondary btn-sm extra-small">View All Activity Logs</a>
            </div>

            <?php if (empty($recent_activities)): ?>
                <div class="bc-empty-state py-4">
                    <i class="fa-solid fa-list-check"></i>
                    <p class="mb-0">No system activity logged yet.</p>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush bg-transparent">
                    <?php foreach ($recent_activities as $act): ?>
                        <div class="list-group-item bg-transparent text-light border-secondary px-0 py-2 d-flex justify-content-between align-items-center">
                            <div>
                                <span class="fw-bold text-warning small"><?= e($act['action']) ?></span>
                                <span class="text-muted extra-small ms-2"><?= e($act['details']) ?></span>
                                <?php if ($act['user_name']): ?>
                                    <span class="badge bg-dark border border-secondary extra-small ms-2"><?= e($act['user_name']) ?> (<?= e($act['user_role']) ?>)</span>
                                <?php endif; ?>
                            </div>
                            <span class="text-muted extra-small"><?= format_datetime($act['created_at']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Chart.js Dependency -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('roleDistributionChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Workers', 'Contractors', 'Clients', 'Admins'],
            datasets: [{
                data: [<?= (int)$total_workers ?>, <?= (int)$total_contractors ?>, <?= (int)$total_clients ?>, 1],
                backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: '#94a3b8', font: { family: 'Inter', size: 12 } }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
