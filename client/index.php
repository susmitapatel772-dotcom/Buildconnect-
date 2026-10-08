<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('client');

$page_title = "Client Executive Dashboard - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$client_user_id = (int)$user['id'];
$flash = get_flash_message();

// DB Statistics isolated strictly to logged-in client
$stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE client_id = ?");
$stmt->execute([$client_user_id]);
$total_projects = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE client_id = ? AND status IN ('in_progress', 'active')");
$stmt->execute([$client_user_id]);
$active_projects = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE client_id = ? AND status = 'completed'");
$stmt->execute([$client_user_id]);
$completed_projects = (int)$stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE client_id = ? AND status = 'on_hold'");
$stmt->execute([$client_user_id]);
$on_hold_projects = (int)$stmt->fetchColumn();

$stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM milestones m 
    JOIN projects p ON m.project_id = p.id 
    WHERE p.client_id = ? AND m.status IN ('pending', 'in_progress')
");
$stmt->execute([$client_user_id]);
$upcoming_milestones = (int)$stmt->fetchColumn();

// Fetch Client Projects
$stmt_p = $db->prepare("
    SELECT p.*, u.name as contractor_name, c.company_name as contractor_company 
    FROM projects p 
    LEFT JOIN users u ON p.contractor_id = u.id 
    LEFT JOIN contractors c ON c.user_id = u.id 
    WHERE p.client_id = ? 
    ORDER BY p.id DESC LIMIT 5
");
$stmt_p->execute([$client_user_id]);
$recent_projects = $stmt_p->fetchAll();

// Fetch Upcoming Milestones
$stmt_m = $db->prepare("
    SELECT m.*, p.title as project_title 
    FROM milestones m 
    JOIN projects p ON m.project_id = p.id 
    WHERE p.client_id = ? 
    ORDER BY m.target_date ASC LIMIT 5
");
$stmt_m->execute([$client_user_id]);
$recent_milestones = $stmt_m->fetchAll();

// Greeting helper based on hour of day
$hour = date('H');
$greeting = ($hour < 12) ? 'Good Morning' : (($hour < 18) ? 'Good Afternoon' : 'Good Evening');
?>

<div class="dashboard-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="dashboard-content">
        <!-- Dashboard Top Greeting Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h1 class="h2 fw-extrabold text-white bc-page-title mb-1">
                    <?= $greeting ?>, <?= e(explode(' ', $user['name'])[0]) ?> 👋
                </h1>
                <p class="text-slate-400 small mb-0">Overview of ongoing developments, project progress, and milestone deliveries.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="bg-dark border border-secondary rounded-3 px-3 py-1.5 shadow-sm text-slate-300 small fw-semibold">
                    <i class="fa-regular fa-calendar text-primary me-2"></i><?= date('M d, Y', strtotime('-6 days')) ?> - <?= date('M d, Y') ?>
                </div>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show mb-4" role="alert">
                <i class="fa-solid fa-circle-info me-2"></i> <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- 5 Metric Cards Row -->
        <div class="row g-3 mb-4">
            <div class="col-xl col-md-4 col-sm-6">
                <div class="bc-metric-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bc-metric-badge-blue p-2.5 rounded-3"><i class="fa-solid fa-building fs-5"></i></div>
                        <span class="text-slate-400 extra-small fw-bold">Total Projects</span>
                    </div>
                    <div class="fs-2 fw-extrabold text-white mb-1"><?= number_format($total_projects) ?></div>
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="bc-trend-badge-up"><i class="fa-solid fa-arrow-trend-up me-1"></i>+15%</span>
                        <span class="text-slate-400 extra-small">vs. last month</span>
                    </div>
                    <svg class="mt-2 w-100" height="24" viewBox="0 0 100 25" fill="none">
                        <path d="M0 20 Q 20 15, 40 18 T 80 5 T 100 2" stroke="#2563eb" stroke-width="2.5" fill="none"/>
                    </svg>
                </div>
            </div>

            <div class="col-xl col-md-4 col-sm-6">
                <div class="bc-metric-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bc-metric-badge-green p-2.5 rounded-3"><i class="fa-solid fa-person-digging fs-5"></i></div>
                        <span class="text-slate-400 extra-small fw-bold">Active Construction</span>
                    </div>
                    <div class="fs-2 fw-extrabold text-white mb-1"><?= number_format($active_projects) ?></div>
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="bc-trend-badge-up"><i class="fa-solid fa-arrow-trend-up me-1"></i>+10%</span>
                        <span class="text-slate-400 extra-small">On Schedule</span>
                    </div>
                    <svg class="mt-2 w-100" height="24" viewBox="0 0 100 25" fill="none">
                        <path d="M0 22 Q 25 10, 50 16 T 80 8 T 100 3" stroke="#10b981" stroke-width="2.5" fill="none"/>
                    </svg>
                </div>
            </div>

            <div class="col-xl col-md-4 col-sm-6">
                <div class="bc-metric-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bc-metric-badge-purple p-2.5 rounded-3"><i class="fa-solid fa-circle-check fs-5"></i></div>
                        <span class="text-slate-400 extra-small fw-bold">Completed Projects</span>
                    </div>
                    <div class="fs-2 fw-extrabold text-white mb-1"><?= number_format($completed_projects) ?></div>
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="bc-trend-badge-up"><i class="fa-solid fa-arrow-trend-up me-1"></i>100%</span>
                        <span class="text-slate-400 extra-small">Delivered</span>
                    </div>
                    <svg class="mt-2 w-100" height="24" viewBox="0 0 100 25" fill="none">
                        <path d="M0 18 Q 30 22, 60 10 T 90 6 T 100 1" stroke="#8b5cf6" stroke-width="2.5" fill="none"/>
                    </svg>
                </div>
            </div>

            <div class="col-xl col-md-4 col-sm-6">
                <div class="bc-metric-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bc-metric-badge-amber p-2.5 rounded-3"><i class="fa-solid fa-flag-checkered fs-5"></i></div>
                        <span class="text-slate-400 extra-small fw-bold">Upcoming Milestones</span>
                    </div>
                    <div class="fs-2 fw-extrabold text-white mb-1"><?= number_format($upcoming_milestones) ?></div>
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="text-slate-400 extra-small"><?= number_format($on_hold_projects) ?> On Hold</span>
                        <span class="text-slate-400 extra-small">Target Approaching</span>
                    </div>
                    <svg class="mt-2 w-100" height="24" viewBox="0 0 100 25" fill="none">
                        <path d="M0 24 Q 20 18, 45 12 T 75 14 T 100 2" stroke="#f59e0b" stroke-width="2.5" fill="none"/>
                    </svg>
                </div>
            </div>

            <div class="col-xl col-md-4 col-sm-6">
                <div class="bc-metric-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bc-metric-badge-rose p-2.5 rounded-3"><i class="fa-solid fa-indian-rupee-sign fs-5"></i></div>
                        <span class="text-slate-400 extra-small fw-bold">Budget Track</span>
                    </div>
                    <div class="fs-2 fw-extrabold text-white mb-1">94%</div>
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="bc-trend-badge-up"><i class="fa-solid fa-check me-1"></i>Healthy</span>
                        <span class="text-slate-400 extra-small">Disbursement</span>
                    </div>
                    <svg class="mt-2 w-100" height="24" viewBox="0 0 100 25" fill="none">
                        <path d="M0 20 Q 30 15, 50 18 T 85 8 T 100 4" stroke="#ef4444" stroke-width="2.5" fill="none"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Main Construction Projects Card Stream -->
        <div class="row g-4 mb-4">
            <div class="col-xl-9 col-lg-8">
                <div class="bc-surface-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h6 text-dark fw-bold mb-0"><i class="fa-solid fa-building text-primary me-2"></i>My Construction Developments</h2>
                        <a href="<?= BASE_URL ?>/client/projects.php" class="text-primary text-decoration-none extra-small fw-bold">View All Projects <i class="fa-solid fa-arrow-right ms-1"></i></a>
                    </div>

                    <?php if (empty($recent_projects)): ?>
                        <div class="text-center py-4 text-muted extra-small">
                            No active construction projects linked to your client account.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 extra-small">
                                <thead class="table-light">
                                    <tr>
                                        <th>Project Name</th>
                                        <th>Contractor Firm</th>
                                        <th>Location</th>
                                        <th>Budget</th>
                                        <th>Status</th>
                                        <th>Progress</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_projects as $p): ?>
                                        <tr>
                                            <td>
                                                <a href="<?= BASE_URL ?>/client/project-details.php?id=<?= $p['id'] ?>" class="fw-bold text-dark text-decoration-none">
                                                    <?= e($p['title']) ?>
                                                </a>
                                            </td>
                                            <td><span class="text-secondary fw-semibold"><?= e($p['contractor_company'] ?? $p['contractor_name'] ?? 'Contractor') ?></span></td>
                                            <td class="text-muted"><i class="fa-solid fa-location-dot text-danger me-1"></i><?= e($p['location']) ?></td>
                                            <td class="font-monospace fw-bold text-dark"><?= format_currency($p['budget']) ?></td>
                                            <td><span class="badge bg-primary bg-opacity-10 text-primary fw-semibold"><?= e($p['status']) ?></span></td>
                                            <td style="min-width: 120px;">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress flex-grow-1 bg-slate-100" style="height: 6px;">
                                                        <div class="progress-bar bg-primary" style="width: <?= (int)$p['progress_percent'] ?>%;"></div>
                                                    </div>
                                                    <span class="fw-bold text-dark font-monospace"><?= (int)$p['progress_percent'] ?>%</span>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <a href="<?= BASE_URL ?>/client/project-details.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary extra-small">Monitor</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Client Quick Actions -->
            <div class="col-xl-3 col-lg-4">
                <div class="bc-surface-card p-4 h-100">
                    <h3 class="h6 fw-bold text-dark mb-3"><i class="fa-solid fa-bolt text-warning me-2"></i>Quick Actions</h3>

                    <a href="<?= BASE_URL ?>/client/projects.php" class="bc-quick-action-item">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon bc-metric-badge-blue p-2 rounded-3"><i class="fa-solid fa-building fs-6"></i></div>
                            <div>
                                <div class="fw-bold text-dark small">My Projects</div>
                                <div class="text-muted extra-small">View developments</div>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-muted small"></i>
                    </a>

                    <a href="<?= BASE_URL ?>/client/progress.php" class="bc-quick-action-item">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon bc-metric-badge-green p-2 rounded-3"><i class="fa-solid fa-chart-column fs-6"></i></div>
                            <div>
                                <div class="fw-bold text-dark small">Track Progress</div>
                                <div class="text-muted extra-small">Site milestones</div>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-muted small"></i>
                    </a>

                    <a href="<?= BASE_URL ?>/client/documents.php" class="bc-quick-action-item">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon bc-metric-badge-purple p-2 rounded-3"><i class="fa-solid fa-file-contract fs-6"></i></div>
                            <div>
                                <div class="fw-bold text-dark small">Documents</div>
                                <div class="text-muted extra-small">Contracts & blueprints</div>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-muted small"></i>
                    </a>

                    <a href="<?= BASE_URL ?>/client/reports.php" class="bc-quick-action-item mb-0">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon bc-metric-badge-amber p-2 rounded-3"><i class="fa-solid fa-chart-pie fs-6"></i></div>
                            <div>
                                <div class="fw-bold text-dark small">Reports</div>
                                <div class="text-muted extra-small">Export analytics</div>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-muted small"></i>
                    </a>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
