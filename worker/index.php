<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('worker');

$page_title = "Worker Dashboard - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$user_id = (int)$user['id'];
$flash = get_flash_message();

// 1. Fetch Worker Profile
$stmt = $db->prepare("SELECT * FROM workers WHERE user_id = ?");
$stmt->execute([$user_id]);
$worker = $stmt->fetch();
$worker_profile_id = $worker ? (int)$worker['id'] : 0;

// 2. Fetch Metrics
$stmt_p = $db->prepare("SELECT COUNT(*) FROM project_members WHERE user_id = ?");
$stmt_p->execute([$user_id]);
$assigned_projects = (int)$stmt_p->fetchColumn();

$stmt_ap = $db->prepare("
    SELECT COUNT(*) 
    FROM project_members pm 
    JOIN projects p ON pm.project_id = p.id 
    WHERE pm.user_id = ? AND p.status = 'in_progress'
");
$stmt_ap->execute([$user_id]);
$active_projects = (int)$stmt_ap->fetchColumn();

$stmt_s = $db->prepare("SELECT COUNT(*) FROM worker_skills WHERE worker_id = ?");
$stmt_s->execute([$worker_profile_id]);
$skills_count = (int)$stmt_s->fetchColumn();

$stmt_exp = $db->prepare("SELECT COUNT(*) FROM worker_experience WHERE worker_id = ?");
$stmt_exp->execute([$worker_profile_id]);
$experience_count = (int)$stmt_exp->fetchColumn();

$stmt_d = $db->prepare("SELECT COUNT(*) FROM worker_documents WHERE worker_id = ? AND status = 'pending'");
$stmt_d->execute([$worker_profile_id]);
$pending_documents = (int)$stmt_d->fetchColumn();

$stmt_total_d = $db->prepare("SELECT COUNT(*) FROM worker_documents WHERE worker_id = ?");
$stmt_total_d->execute([$worker_profile_id]);
$total_documents = (int)$stmt_total_d->fetchColumn();

// 3. Dynamic Profile Completion % Calculation
$completion = 0;
if (!empty($user['name'])) $completion += 10;
if (!empty($user['email'])) $completion += 10;
if (!empty($user['phone'])) $completion += 10;
if ($worker && !empty($worker['bio'])) $completion += 10;
if ($worker && !empty($worker['trade_title'])) $completion += 10;
if ($worker && !empty($worker['city'])) $completion += 10;
if ($skills_count > 0) $completion += 15;
if ($experience_count > 0) $completion += 15;
if ($total_documents > 0) $completion += 10;

// Fetch Recent Assigned Projects
$stmt_rp = $db->prepare("
    SELECT p.*, pm.role_in_project, pm.joined_at 
    FROM project_members pm 
    JOIN projects p ON pm.project_id = p.id 
    WHERE pm.user_id = ? 
    ORDER BY pm.id DESC LIMIT 5
");
$stmt_rp->execute([$user_id]);
$recent_projects = $stmt_rp->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-gauge-high text-warning me-2"></i>Worker Dashboard
                </h1>
                <p class="text-muted small mb-0">
                    Welcome back, <strong class="text-white"><?= e($user['name']) ?></strong>. Monitor your skills, documents, and assigned construction sites.
                </p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/worker/profile.php" class="btn btn-amber btn-sm fw-bold">
                    <i class="fa-solid fa-user me-1"></i> View Profile
                </a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show mb-4" role="alert">
                <i class="fa-solid fa-circle-info me-2"></i> <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Profile Completion & Verification Alert Banner -->
        <div class="bc-card p-4 mb-4 border-warning">
            <div class="row align-items-center g-3">
                <div class="col-md-8">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <h2 class="h5 text-white fw-bold mb-0">Profile Completion: <span class="text-warning font-monospace"><?= $completion ?>%</span></h2>
                        <span class="badge <?= get_status_badge_class($worker['verification_status'] ?? 'pending') ?> font-monospace text-uppercase">
                            Verification: <?= e($worker['verification_status'] ?? 'pending') ?>
                        </span>
                    </div>
                    <div class="progress bg-secondary mb-2" style="height: 10px;">
                        <div class="progress-bar bg-warning" style="width: <?= $completion ?>%;"></div>
                    </div>
                    <p class="text-muted extra-small mb-0">
                        <?php if ($completion < 100): ?>
                            Complete your trade skills, work history, and upload identity credentials to reach 100% completion.
                        <?php else: ?>
                            Your profile information is 100% complete and ready for contractor recruitment!
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="<?= BASE_URL ?>/worker/edit-profile.php" class="btn btn-outline-amber btn-sm font-semibold">
                        <i class="fa-solid fa-pen-to-square me-1"></i> Complete Profile
                    </a>
                </div>
            </div>
        </div>

        <!-- Metric Statistics Cards Grid -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-sm-6">
                <div class="bc-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted extra-small text-uppercase fw-semibold">Verification Status</span>
                        <div class="stat-icon emerald p-2 rounded"><i class="fa-solid fa-shield-halved text-success fs-5"></i></div>
                    </div>
                    <div class="fs-4 fw-bold text-white text-capitalize"><?= e($worker['verification_status'] ?? 'Pending') ?></div>
                    <div class="text-muted extra-small mt-1">Admin Badge Status</div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="bc-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted extra-small text-uppercase fw-semibold">Assigned Projects</span>
                        <div class="stat-icon amber p-2 rounded"><i class="fa-solid fa-building text-warning fs-5"></i></div>
                    </div>
                    <div class="fs-3 fw-bold text-white"><?= number_format($assigned_projects) ?></div>
                    <div class="text-muted extra-small mt-1"><?= number_format($active_projects) ?> Currently Active</div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="bc-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted extra-small text-uppercase fw-semibold">Trade Skills</span>
                        <div class="stat-icon blue p-2 rounded"><i class="fa-solid fa-screwdriver-wrench text-info fs-5"></i></div>
                    </div>
                    <div class="fs-3 fw-bold text-info"><?= number_format($skills_count) ?></div>
                    <div class="text-muted extra-small mt-1">Verified Specializations</div>
                </div>
            </div>

            <div class="col-xl-3 col-sm-6">
                <div class="bc-card p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted extra-small text-uppercase fw-semibold">Pending Docs</span>
                        <div class="stat-icon purple p-2 rounded"><i class="fa-solid fa-file-contract text-primary fs-5"></i></div>
                    </div>
                    <div class="fs-3 fw-bold text-primary"><?= number_format($pending_documents) ?></div>
                    <div class="text-muted extra-small mt-1">Under Admin Review</div>
                </div>
            </div>
        </div>

        <!-- Assigned Projects Stream -->
        <div class="bc-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 text-white fw-bold mb-0">
                    <i class="fa-solid fa-building-flag text-warning me-2"></i>My Active Site Assignments
                </h2>
                <a href="<?= BASE_URL ?>/worker/projects.php" class="btn btn-outline-secondary btn-sm extra-small">
                    View All Projects <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
            </div>

            <?php if (empty($recent_projects)): ?>
                <div class="bc-empty-state py-4">
                    <i class="fa-solid fa-helmet-safety"></i>
                    <p class="mb-0">No construction project assignments linked to your account yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Project Name</th>
                                <th>Location</th>
                                <th>Role in Site</th>
                                <th>Project Status</th>
                                <th>Joined Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_projects as $p): ?>
                                <tr>
                                    <td>
                                        <strong class="text-white"><?= e($p['title']) ?></strong>
                                    </td>
                                    <td>
                                        <span class="text-muted small"><i class="fa-solid fa-location-dot text-danger me-1"></i><?= e($p['location']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-dark border border-secondary text-warning font-monospace"><?= e($p['role_in_project']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge <?= get_status_badge_class($p['status']) ?>"><?= e($p['status']) ?></span>
                                    </td>
                                    <td class="text-muted extra-small">
                                        <?= format_date($p['joined_at']) ?>
                                    </td>
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
