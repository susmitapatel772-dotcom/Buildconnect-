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

// DB Statistics isolated strictly to logged-in client (WHERE client_id = ?)
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

// Fetch Upcoming Milestones for Client's Projects
$stmt_m = $db->prepare("
    SELECT m.*, p.title as project_title 
    FROM milestones m 
    JOIN projects p ON m.project_id = p.id 
    WHERE p.client_id = ? 
    ORDER BY m.target_date ASC LIMIT 5
");
$stmt_m->execute([$client_user_id]);
$recent_milestones = $stmt_m->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-gauge-high text-warning me-2"></i>Client Monitoring Dashboard
                </h1>
                <p class="text-slate-400 small mb-0">
                    Welcome back, <strong class="text-white"><?= e($user['name']) ?></strong>. Overview of ongoing developments, progress, and milestone deliveries.
                </p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/client/projects.php" class="btn btn-amber btn-sm fw-bold">
                    <i class="fa-solid fa-building me-1"></i> My Projects
                </a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show mb-4" role="alert">
                <i class="fa-solid fa-circle-info me-2"></i> <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards Grid -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-sm-6 col-12">
                <div class="bc-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-slate-300 extra-small text-uppercase fw-semibold">Total Projects</span>
                        <div class="stat-icon amber"><i class="fa-solid fa-building text-warning fs-5"></i></div>
                    </div>
                    <div class="fs-2 fw-bold text-white"><?= number_format($total_projects) ?></div>
                    <div class="text-slate-400 extra-small mt-1">Associated with your account</div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6 col-12">
                <div class="bc-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-slate-300 extra-small text-uppercase fw-semibold">Active Projects</span>
                        <div class="stat-icon blue"><i class="fa-solid fa-person-digging text-info fs-5"></i></div>
                    </div>
                    <div class="fs-2 fw-bold text-info"><?= number_format($active_projects) ?></div>
                    <div class="text-slate-400 extra-small mt-1">In active construction</div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6 col-12">
                <div class="bc-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-slate-300 extra-small text-uppercase fw-semibold">Completed Projects</span>
                        <div class="stat-icon emerald"><i class="fa-solid fa-circle-check text-success fs-5"></i></div>
                    </div>
                    <div class="fs-2 fw-bold text-success"><?= number_format($completed_projects) ?></div>
                    <div class="text-slate-400 extra-small mt-1">Delivered developments</div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6 col-12">
                <div class="bc-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-slate-300 extra-small text-uppercase fw-semibold">Upcoming Milestones</span>
                        <div class="stat-icon purple"><i class="fa-solid fa-flag-checkered text-warning fs-5"></i></div>
                    </div>
                    <div class="fs-2 fw-bold text-warning"><?= number_format($upcoming_milestones) ?></div>
                    <div class="text-slate-400 extra-small mt-1"><?= number_format($on_hold_projects) ?> Projects On Hold</div>
                </div>
            </div>
        </div>

        <!-- My Projects Stream -->
        <div class="bc-card p-4 mb-4">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
                <h2 class="h5 text-white fw-bold mb-0">
                    <i class="fa-solid fa-building text-warning me-2"></i>My Construction Projects
                </h2>
                <a href="<?= BASE_URL ?>/client/projects.php" class="btn btn-outline-amber btn-sm extra-small">
                    View All Projects <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
            </div>

            <?php if (empty($recent_projects)): ?>
                <div class="bc-empty-state py-4">
                    <i class="fa-solid fa-building-circle-exclamation text-warning mb-2"></i>
                    <h5 class="text-white fw-bold mb-1">No Construction Projects Found</h5>
                    <p class="text-slate-400 small mb-3">No construction projects are currently linked to your client account.</p>
                    <a href="<?= BASE_URL ?>/client/projects.php" class="btn btn-amber btn-sm extra-small fw-bold">
                        <i class="fa-solid fa-building me-1"></i> Explore Client Projects
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
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
                                        <a href="<?= BASE_URL ?>/client/project-details.php?id=<?= $p['id'] ?>" class="fw-bold text-white text-decoration-none hover-warning">
                                            <?= e($p['title']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="text-warning small"><i class="fa-solid fa-building me-1"></i><?= e($p['contractor_company'] ?? $p['contractor_name'] ?? 'Contractor') ?></span>
                                    </td>
                                    <td>
                                        <span class="text-slate-400 small"><i class="fa-solid fa-location-dot text-danger me-1"></i><?= e($p['location']) ?></span>
                                    </td>
                                    <td>
                                        <span class="font-monospace text-warning fw-bold"><?= format_currency($p['budget']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge <?= get_status_badge_class($p['status']) ?>"><?= e($p['status']) ?></span>
                                    </td>
                                    <td style="min-width: 130px;">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1 bg-secondary" style="height: 6px;">
                                                <div class="progress-bar bg-warning" style="width: <?= (int)$p['progress_percent'] ?>%;"></div>
                                            </div>
                                            <span class="extra-small fw-bold text-white font-monospace"><?= (int)$p['progress_percent'] ?>%</span>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>/client/project-details.php?id=<?= $p['id'] ?>" class="btn btn-outline-amber btn-sm extra-small">
                                            Monitor Project
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Milestones Overview Stream -->
        <div class="bc-card p-4">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
                <h2 class="h5 text-white fw-bold mb-0">
                    <i class="fa-solid fa-flag-checkered text-success me-2"></i>Upcoming Milestones & Deliverables
                </h2>
                <a href="<?= BASE_URL ?>/client/milestones.php" class="btn btn-outline-amber btn-sm extra-small">
                    View All Milestones <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
            </div>

            <?php if (empty($recent_milestones)): ?>
                <div class="bc-empty-state py-4">
                    <i class="fa-solid fa-flag-checkered text-success mb-2"></i>
                    <h5 class="text-white fw-bold mb-1">No Milestones Scheduled</h5>
                    <p class="text-slate-400 small mb-0">No project milestones are currently scheduled for your developments.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Milestone Title</th>
                                <th>Project Name</th>
                                <th>Target Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_milestones as $m): ?>
                                <tr>
                                    <td><strong class="text-white"><?= e($m['title']) ?></strong></td>
                                    <td><span class="text-slate-400 small"><?= e($m['project_title']) ?></span></td>
                                    <td><span class="text-info extra-small"><i class="fa-solid fa-calendar me-1"></i><?= format_date($m['target_date']) ?></span></td>
                                    <td><span class="text-warning font-monospace fw-bold"><?= format_currency($m['amount']) ?></span></td>
                                    <td><span class="badge <?= get_status_badge_class($m['status']) ?>"><?= e($m['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
