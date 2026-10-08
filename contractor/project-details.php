<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('contractor');

$page_title = "Project Details - Contractor - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$contractor_id = (int)$user['id'];
$proj_id = (int)($_GET['id'] ?? 0);

$flash = get_flash_message();

// Fetch project with strict contractor ownership check
$stmt = $db->prepare("
    SELECT p.*, u_c.name as client_name, c_prof.company_name as client_company, u_c.email as client_email
    FROM projects p
    LEFT JOIN users u_c ON p.client_id = u_c.id
    LEFT JOIN clients c_prof ON u_c.id = c_prof.user_id
    WHERE p.id = ? AND p.contractor_id = ?
");
$stmt->execute([$proj_id, $contractor_id]);
$project = $stmt->fetch();

if (!$project) {
    ?>
    <div class="bc-layout">
        <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="bc-main-content">
            <div class="bc-card p-5 text-center my-5 border-danger">
                <i class="fa-solid fa-lock text-danger fs-1 mb-3"></i>
                <h2 class="h4 text-white fw-bold">Access Denied</h2>
                <p class="text-muted small mb-4">The requested project was not found or does not belong to your contractor account.</p>
                <a href="<?= BASE_URL ?>/contractor/projects.php" class="btn btn-amber btn-sm fw-bold">
                    <i class="fa-solid fa-arrow-left me-1"></i> Return to Projects
                </a>
            </div>
        </main>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Calculate real progress
$project['progress_percent'] = calculateProjectProgress($proj_id);

// Fetch assigned workforce (project_members)
$members_stmt = $db->prepare("
    SELECT pm.*, u.name as worker_name, u.email as worker_email, u.phone as worker_phone,
           w.trade_title, w.experience_years, w.verification_status,
           (SELECT COUNT(*) FROM tasks t WHERE t.project_id = pm.project_id AND t.assigned_to_worker_id = pm.user_id AND t.status NOT IN ('completed', 'done', 'cancelled')) as active_tasks
    FROM project_members pm
    JOIN users u ON pm.user_id = u.id
    LEFT JOIN workers w ON u.id = w.user_id
    WHERE pm.project_id = ?
    ORDER BY pm.joined_at DESC
");
$members_stmt->execute([$proj_id]);
$members = $members_stmt->fetchAll();

// Fetch milestones
$milestones_stmt = $db->prepare("
    SELECT m.*, 
           (SELECT COUNT(*) FROM tasks t WHERE t.milestone_id = m.id) as total_tasks,
           (SELECT COUNT(*) FROM tasks t WHERE t.milestone_id = m.id AND t.status IN ('completed', 'done')) as completed_tasks
    FROM milestones m
    WHERE m.project_id = ?
    ORDER BY m.target_date ASC, m.id ASC
");
$milestones_stmt->execute([$proj_id]);
$milestones = $milestones_stmt->fetchAll();

// Synchronize milestone progress
foreach ($milestones as &$ms) {
    $ms['progress_percent'] = calculateMilestoneProgress($ms['id']);
}
unset($ms);

// Fetch project tasks
$tasks_stmt = $db->prepare("
    SELECT t.*, u.name as assigned_worker_name, m.title as milestone_title
    FROM tasks t
    LEFT JOIN users u ON t.assigned_to_worker_id = u.id
    LEFT JOIN milestones m ON t.milestone_id = m.id
    WHERE t.project_id = ?
    ORDER BY t.due_date ASC, t.id DESC
");
$tasks_stmt->execute([$proj_id]);
$tasks = $tasks_stmt->fetchAll();

// Fetch project documents
$docs_stmt = $db->prepare("
    SELECT pd.*, u.name as uploader_name 
    FROM project_documents pd
    JOIN users u ON pd.uploaded_by_user_id = u.id
    WHERE pd.project_id = ?
    ORDER BY pd.id DESC
");
$docs_stmt->execute([$proj_id]);
$documents = $docs_stmt->fetchAll();

// Fetch project activity logs
$act_stmt = $db->prepare("
    SELECT al.*, u.name as actor_name
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE al.entity_type = 'project' AND al.entity_id = ?
    ORDER BY al.id DESC LIMIT 10
");
$act_stmt->execute([$proj_id]);
$activities = $act_stmt->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <!-- Project Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h1 class="h2 fw-bold text-white mb-0"><?= e($project['title']) ?></h1>
                    <span class="badge <?= get_status_badge_class($project['status']) ?> fs-6"><?= e(ucfirst(str_replace('_', ' ', $project['status']))) ?></span>
                </div>
                <p class="text-muted small mb-0">
                    <i class="fa-solid fa-location-dot text-danger me-1"></i><?= e($project['location']) ?>
                    • Client: <strong class="text-info"><?= e($project['client_name'] ? ($project['client_company'] ?: $project['client_name']) : 'Internal Developer') ?></strong>
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= BASE_URL ?>/contractor/projects.php" class="btn btn-outline-light btn-sm fw-bold">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Projects
                </a>
                <a href="<?= BASE_URL ?>/contractor/edit-project.php?id=<?= $proj_id ?>" class="btn btn-warning btn-sm fw-bold">
                    <i class="fa-solid fa-pen me-1"></i> Edit Project
                </a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show py-2 px-3 small mb-3">
                <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Key Metrics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="bc-card p-3">
                    <div class="text-muted extra-small uppercase">Overall Progress</div>
                    <div class="display-6 font-monospace text-warning fw-bold my-1"><?= (int)$project['progress_percent'] ?>%</div>
                    <div class="progress" style="height: 4px;">
                        <div class="progress-bar bg-amber" style="width: <?= (int)$project['progress_percent'] ?>%"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="bc-card p-3">
                    <div class="text-muted extra-small uppercase">Total Budget</div>
                    <div class="display-6 font-monospace text-success fw-bold my-1" style="font-size: 1.5rem;"><?= format_currency($project['budget']) ?></div>
                    <div class="extra-small text-muted">Allocated Funds</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="bc-card p-3">
                    <div class="text-muted extra-small uppercase">Project Timeline</div>
                    <div class="fw-bold text-light my-1" style="font-size: 0.95rem;"><?= format_date($project['start_date']) ?></div>
                    <div class="extra-small text-muted">Exp: <?= format_date($project['end_date']) ?></div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="bc-card p-3">
                    <div class="text-muted extra-small uppercase">Workforce & Tasks</div>
                    <div class="fw-bold text-info my-1" style="font-size: 0.95rem;"><?= count($members) ?> Members</div>
                    <div class="extra-small text-muted"><?= count($tasks) ?> Total Tasks</div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Left Column: Milestones & Tasks -->
            <div class="col-lg-8">
                <!-- Milestones Section -->
                <div class="bc-card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 text-white fw-bold mb-0">
                            <i class="fa-solid fa-flag-checkered text-warning me-2"></i>Project Milestones (<?= count($milestones) ?>)
                        </h2>
                        <a href="<?= BASE_URL ?>/contractor/milestones.php?project_id=<?= $proj_id ?>" class="btn btn-amber btn-sm fw-bold extra-small py-1 px-3">
                            <i class="fa-solid fa-plus me-1"></i> Manage Milestones
                        </a>
                    </div>

                    <?php if (empty($milestones)): ?>
                        <div class="bc-empty-state py-4 text-center">
                            <p class="text-muted small mb-0">No project milestones created yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="vstack gap-3">
                            <?php foreach ($milestones as $ms): ?>
                                <?php $deadline = get_deadline_status($ms['target_date'], $ms['status']); ?>
                                <div class="p-3 bg-dark rounded-3 border border-secondary">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div>
                                            <h3 class="h6 text-white fw-bold mb-0"><?= e($ms['title']) ?></h3>
                                            <span class="text-muted extra-small"><?= e($ms['description']) ?></span>
                                        </div>
                                        <span class="badge <?= get_status_badge_class($ms['status']) ?>"><?= e(ucfirst($ms['status'])) ?></span>
                                    </div>
                                    <div class="d-flex align-items-center gap-3 mt-2 extra-small">
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between text-muted mb-1">
                                                <span>Progress</span>
                                                <span class="text-warning font-monospace fw-bold"><?= (int)$ms['progress_percent'] ?>%</span>
                                            </div>
                                            <div class="progress" style="height: 5px;">
                                                <div class="progress-bar bg-amber" style="width: <?= (int)$ms['progress_percent'] ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="<?= $deadline['class'] ?>">
                                            <i class="fa-solid fa-clock me-1"></i><?= $deadline['label'] ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Tasks Section -->
                <div class="bc-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 text-white fw-bold mb-0">
                            <i class="fa-solid fa-list-check text-info me-2"></i>Workforce Tasks (<?= count($tasks) ?>)
                        </h2>
                        <a href="<?= BASE_URL ?>/contractor/create-task.php?project_id=<?= $proj_id ?>" class="btn btn-amber btn-sm fw-bold extra-small py-1 px-3">
                            <i class="fa-solid fa-plus me-1"></i> Create Task
                        </a>
                    </div>

                    <?php if (empty($tasks)): ?>
                        <div class="bc-empty-state py-4 text-center">
                            <p class="text-muted small mb-0">No site tasks assigned for this project yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-custom align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Task Title</th>
                                        <th>Assigned Worker</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Progress</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tasks as $t): ?>
                                        <tr>
                                            <td>
                                                <a href="<?= BASE_URL ?>/contractor/task-details.php?id=<?= $t['id'] ?>" class="fw-bold text-white text-decoration-none hover-amber">
                                                    <?= e($t['title']) ?>
                                                </a>
                                                <?php if (!empty($t['milestone_title'])): ?>
                                                    <div class="extra-small text-muted"><i class="fa-solid fa-flag me-1"></i><?= e($t['milestone_title']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small text-light">
                                                <?= e($t['assigned_worker_name'] ?: 'Unassigned') ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= get_priority_badge_class($t['priority']) ?>"><?= e(ucfirst($t['priority'])) ?></span>
                                            </td>
                                            <td>
                                                <span class="badge <?= get_status_badge_class($t['status']) ?>"><?= e(ucfirst(str_replace('_', ' ', $t['status']))) ?></span>
                                            </td>
                                            <td>
                                                <span class="font-monospace text-warning small"><?= (int)$t['progress_percent'] ?>%</span>
                                            </td>
                                            <td class="text-end">
                                                <a href="<?= BASE_URL ?>/contractor/task-details.php?id=<?= $t['id'] ?>" class="btn btn-outline-light btn-sm extra-small py-1 px-2">
                                                    Manage
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Workforce & Activity -->
            <div class="col-lg-4">
                <!-- Project Site Map Card -->
                <div class="bc-card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h6 text-white fw-bold mb-0"><i class="fa-solid fa-map-location-dot text-warning me-2"></i>Project Site Map</h3>
                        <?php if ($project['location_lat'] && $project['location_lng']): ?>
                            <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $project['location_lat'] ?>,<?= $project['location_lng'] ?>" target="_blank" rel="noopener" class="btn btn-outline-warning btn-sm extra-small">
                                <i class="fa-solid fa-route me-1"></i> Directions
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="text-muted extra-small mb-2">
                        <i class="fa-solid fa-location-dot me-1 text-warning"></i><?= e($project['location']) ?>
                    </div>

                    <?php if ($project['location_lat'] && $project['location_lng']): ?>
                        <div id="projectDetailMap" style="height: 220px; width: 100%;" class="rounded border border-secondary bg-dark mb-2"></div>
                        <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            if (window.BC_Maps) {
                                const mapObj = BC_Maps.initProjectMap('projectDetailMap', <?= (float)$project['location_lat'] ?>, <?= (float)$project['location_lng'] ?>, 14);
                                if (mapObj) {
                                    BC_Maps.addProjectMarker(mapObj, <?= (float)$project['location_lat'] ?>, <?= (float)$project['location_lng'] ?>, "<?= e($project['title']) ?>", "<strong><?= e($project['title']) ?></strong><br><small><?= e($project['location']) ?></small>");
                                }
                            }
                        });
                        </script>
                    <?php else: ?>
                        <div class="p-3 bg-dark rounded text-center text-muted extra-small border border-secondary">
                            Project location has not been mapped yet.
                            <a href="<?= BASE_URL ?>/contractor/edit-project.php?id=<?= $proj_id ?>" class="d-block text-warning mt-1">Set Coordinates</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Workforce Members Card -->
                <div class="bc-card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h6 text-white fw-bold mb-0"><i class="fa-solid fa-users text-amber me-2"></i>Assigned Workforce</h3>
                        <a href="<?= BASE_URL ?>/contractor/workers.php?project_id=<?= $proj_id ?>" class="extra-small text-info text-decoration-none">View All</a>
                    </div>

                    <?php if (empty($members)): ?>
                        <p class="text-muted small">No workers assigned to project yet.</p>
                    <?php else: ?>
                        <div class="vstack gap-2">
                            <?php foreach ($members as $m): ?>
                                <div class="d-flex align-items-center justify-content-between p-2 bg-dark rounded-3 border border-secondary">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-secondary text-white fw-bold d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.75rem;">
                                            <?= strtoupper(substr($m['worker_name'], 0, 2)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-white extra-small"><?= e($m['worker_name']) ?></div>
                                            <div class="text-muted" style="font-size: 0.7rem;"><?= e($m['role_in_project']) ?></div>
                                        </div>
                                    </div>
                                    <span class="badge bg-secondary extra-small"><?= (int)$m['active_tasks'] ?> Tasks</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Documents Card -->
                <div class="bc-card p-4 mb-4">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-file-pdf text-danger me-2"></i>Project Documents</h3>
                    <?php if (empty($documents)): ?>
                        <p class="text-muted small mb-0">No documents uploaded.</p>
                    <?php else: ?>
                        <div class="vstack gap-2">
                            <?php foreach ($documents as $doc): ?>
                                <div class="p-2 bg-dark rounded-3 border border-secondary d-flex justify-content-between align-items-center extra-small">
                                    <div>
                                        <div class="fw-bold text-light"><?= e($doc['title']) ?></div>
                                        <div class="text-muted"><?= format_date($doc['created_at']) ?></div>
                                    </div>
                                    <span class="badge bg-secondary"><?= e($doc['file_type']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Activity Logs -->
                <div class="bc-card p-4">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-clock-rotate-left text-warning me-2"></i>Recent Activity</h3>
                    <?php if (empty($activities)): ?>
                        <p class="text-muted small mb-0">No activity recorded yet.</p>
                    <?php else: ?>
                        <div class="vstack gap-2 extra-small">
                            <?php foreach ($activities as $act): ?>
                                <div class="border-bottom border-secondary pb-2">
                                    <div class="fw-bold text-light"><?= e($act['action']) ?></div>
                                    <div class="text-muted"><?= e($act['details']) ?></div>
                                    <div class="text-muted" style="font-size: 0.7rem;"><?= format_datetime($act['created_at']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
