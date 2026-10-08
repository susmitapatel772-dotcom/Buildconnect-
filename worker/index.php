<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('worker');

$page_title = "Worker Executive Dashboard - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$user_id = (int)$user['id'];
$flash = get_flash_message();

// Fetch Worker Profile
$stmt = $db->prepare("SELECT * FROM workers WHERE user_id = ?");
$stmt->execute([$user_id]);
$worker = $stmt->fetch();
$worker_profile_id = $worker ? (int)$worker['id'] : 0;

// Fetch Metrics
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

// Profile Completion % Calculation
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
                <h1 class="h2 fw-bold text-dark mb-1">
                    <?= $greeting ?>, <?= e(explode(' ', $user['name'])[0]) ?> 👋
                </h1>
                <p class="text-muted small mb-0">Monitor your trade skills, identity documents, and active site assignments.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="<?= BASE_URL ?>/worker/profile.php" class="btn btn-primary btn-sm fw-bold rounded-3">
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

        <!-- Profile Completion Alert Card -->
        <div class="bc-surface-card p-4 mb-4 border-start border-4 border-primary">
            <div class="row align-items-center g-3">
                <div class="col-md-8">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <h2 class="h6 text-dark fw-bold mb-0">Profile Completion Progress: <span class="text-primary font-monospace"><?= $completion ?>%</span></h2>
                        <span class="badge bg-success bg-opacity-10 text-success text-uppercase font-monospace">
                            Status: <?= e($worker['verification_status'] ?? 'pending') ?>
                        </span>
                    </div>
                    <div class="progress bg-slate-100 mb-2" style="height: 8px;">
                        <div class="progress-bar bg-primary" style="width: <?= $completion ?>%;"></div>
                    </div>
                    <p class="text-muted extra-small mb-0">
                        <?php if ($completion < 100): ?>
                            Complete your trade skills, work history, and identity documents to get recruited faster by contractors.
                        <?php else: ?>
                            Your profile is 100% complete and verified by administrators!
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="<?= BASE_URL ?>/worker/profile.php" class="btn btn-outline-primary btn-sm fw-semibold">
                        <i class="fa-solid fa-pen-to-square me-1"></i> Complete Profile
                    </a>
                </div>
            </div>
        </div>

        <!-- 5 Metric Cards Row -->
        <div class="row g-3 mb-4">
            <div class="col-xl col-md-4 col-sm-6">
                <div class="bc-metric-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bc-metric-badge-blue p-2.5 rounded-3"><i class="fa-solid fa-shield-halved fs-5"></i></div>
                        <span class="text-slate-500 extra-small fw-bold">Admin Verification</span>
                    </div>
                    <div class="fs-4 fw-bold text-dark text-capitalize mb-1"><?= e($worker['verification_status'] ?? 'Pending') ?></div>
                    <div class="text-muted extra-small">Badge Status</div>
                    <svg class="mt-2 w-100" height="24" viewBox="0 0 100 25" fill="none">
                        <path d="M0 20 Q 20 15, 40 18 T 80 5 T 100 2" stroke="#2563eb" stroke-width="2.5" fill="none"/>
                    </svg>
                </div>
            </div>

            <div class="col-xl col-md-4 col-sm-6">
                <div class="bc-metric-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bc-metric-badge-green p-2.5 rounded-3"><i class="fa-solid fa-building-flag fs-5"></i></div>
                        <span class="text-slate-500 extra-small fw-bold">Assigned Projects</span>
                    </div>
                    <div class="fs-2 fw-bold text-dark mb-1"><?= number_format($assigned_projects) ?></div>
                    <div class="text-muted extra-small"><?= number_format($active_projects) ?> Active Sites</div>
                    <svg class="mt-2 w-100" height="24" viewBox="0 0 100 25" fill="none">
                        <path d="M0 22 Q 25 10, 50 16 T 80 8 T 100 3" stroke="#10b981" stroke-width="2.5" fill="none"/>
                    </svg>
                </div>
            </div>

            <div class="col-xl col-md-4 col-sm-6">
                <div class="bc-metric-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bc-metric-badge-purple p-2.5 rounded-3"><i class="fa-solid fa-screwdriver-wrench fs-5"></i></div>
                        <span class="text-slate-500 extra-small fw-bold">Verified Skills</span>
                    </div>
                    <div class="fs-2 fw-bold text-dark mb-1"><?= number_format($skills_count) ?></div>
                    <div class="text-muted extra-small">Trade Specializations</div>
                    <svg class="mt-2 w-100" height="24" viewBox="0 0 100 25" fill="none">
                        <path d="M0 18 Q 30 22, 60 10 T 90 6 T 100 1" stroke="#8b5cf6" stroke-width="2.5" fill="none"/>
                    </svg>
                </div>
            </div>

            <div class="col-xl col-md-4 col-sm-6">
                <div class="bc-metric-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bc-metric-badge-amber p-2.5 rounded-3"><i class="fa-solid fa-id-card fs-5"></i></div>
                        <span class="text-slate-500 extra-small fw-bold">Identity Documents</span>
                    </div>
                    <div class="fs-2 fw-bold text-dark mb-1"><?= number_format($total_documents) ?></div>
                    <div class="text-muted extra-small"><?= number_format($pending_documents) ?> Pending Review</div>
                    <svg class="mt-2 w-100" height="24" viewBox="0 0 100 25" fill="none">
                        <path d="M0 24 Q 20 18, 45 12 T 75 14 T 100 2" stroke="#f59e0b" stroke-width="2.5" fill="none"/>
                    </svg>
                </div>
            </div>

            <div class="col-xl col-md-4 col-sm-6">
                <div class="bc-metric-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bc-metric-badge-rose p-2.5 rounded-3"><i class="fa-solid fa-calendar-check fs-5"></i></div>
                        <span class="text-slate-500 extra-small fw-bold">Attendance Rate</span>
                    </div>
                    <div class="fs-2 fw-bold text-dark mb-1">98%</div>
                    <div class="text-muted extra-small">QR Site Verification</div>
                    <svg class="mt-2 w-100" height="24" viewBox="0 0 100 25" fill="none">
                        <path d="M0 20 Q 30 15, 50 18 T 85 8 T 100 4" stroke="#ef4444" stroke-width="2.5" fill="none"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Main Site Assignments Card Stream & Quick Actions -->
        <div class="row g-4 mb-4">
            <div class="col-xl-9 col-lg-8">
                <div class="bc-surface-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h6 text-dark fw-bold mb-0"><i class="fa-solid fa-building-flag text-primary me-2"></i>My Active Site Assignments</h2>
                        <a href="<?= BASE_URL ?>/worker/projects.php" class="text-primary text-decoration-none extra-small fw-bold">View All Projects <i class="fa-solid fa-arrow-right ms-1"></i></a>
                    </div>

                    <?php if (empty($recent_projects)): ?>
                        <div class="text-center py-4 text-muted extra-small">
                            No site assignments linked to your worker profile yet.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 extra-small">
                                <thead class="table-light">
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
                                            <td><strong class="text-dark"><?= e($p['title']) ?></strong></td>
                                            <td class="text-muted"><i class="fa-solid fa-location-dot text-danger me-1"></i><?= e($p['location']) ?></td>
                                            <td><span class="badge bg-secondary bg-opacity-10 text-secondary fw-semibold"><?= e($p['role_in_project']) ?></span></td>
                                            <td><span class="badge bg-success bg-opacity-10 text-success fw-semibold"><?= e($p['status']) ?></span></td>
                                            <td class="text-muted"><?= format_date($p['joined_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Worker Quick Actions -->
            <div class="col-xl-3 col-lg-4">
                <div class="bc-surface-card p-4 h-100">
                    <h3 class="h6 fw-bold text-dark mb-3"><i class="fa-solid fa-bolt text-warning me-2"></i>Quick Actions</h3>

                    <a href="<?= BASE_URL ?>/worker/jobs.php" class="bc-quick-action-item">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon bc-metric-badge-blue p-2 rounded-3"><i class="fa-solid fa-magnifying-glass fs-6"></i></div>
                            <div>
                                <div class="fw-bold text-dark small">Browse Jobs</div>
                                <div class="text-muted extra-small">Apply for open work</div>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-muted small"></i>
                    </a>

                    <a href="<?= BASE_URL ?>/worker/skills.php" class="bc-quick-action-item">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon bc-metric-badge-green p-2 rounded-3"><i class="fa-solid fa-screwdriver-wrench fs-6"></i></div>
                            <div>
                                <div class="fw-bold text-dark small">Trade Skills</div>
                                <div class="text-muted extra-small">Update specializations</div>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-muted small"></i>
                    </a>

                    <a href="<?= BASE_URL ?>/worker/documents.php" class="bc-quick-action-item">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon bc-metric-badge-purple p-2 rounded-3"><i class="fa-solid fa-id-card fs-6"></i></div>
                            <div>
                                <div class="fw-bold text-dark small">Identity Docs</div>
                                <div class="text-muted extra-small">Aadhaar / PAN / ID</div>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-right text-muted small"></i>
                    </a>

                    <a href="<?= BASE_URL ?>/worker/contracts.php" class="bc-quick-action-item mb-0">
                        <div class="d-flex align-items-center gap-3">
                            <div class="stat-icon bc-metric-badge-amber p-2 rounded-3"><i class="fa-solid fa-file-contract fs-6"></i></div>
                            <div>
                                <div class="fw-bold text-dark small">Digital Contracts</div>
                                <div class="text-muted extra-small">View signed agreements</div>
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
