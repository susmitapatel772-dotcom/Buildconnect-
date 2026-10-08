<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('client');

$page_title = "Project Monitoring - Client - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$client_user_id = (int)$user['id'];
$proj_id = (int)($_GET['id'] ?? 0);

// Fetch project with strict client authorization check
$stmt = $db->prepare("
    SELECT p.*, 
           u_c.name as contractor_name, u_c.email as contractor_email, u_c.phone as contractor_phone,
           c_prof.company_name as contractor_company, c_prof.license_no
    FROM projects p
    JOIN users u_c ON p.contractor_id = u_c.id
    LEFT JOIN contractors c_prof ON u_c.id = c_prof.user_id
    WHERE p.id = ? AND p.client_id = ?
");
$stmt->execute([$proj_id, $client_user_id]);
$project = $stmt->fetch();

if (!$project) {
    ?>
    <div class="bc-layout">
        <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="bc-main-content">
            <div class="bc-card p-5 text-center my-5 border-danger">
                <i class="fa-solid fa-lock text-danger fs-1 mb-3"></i>
                <h2 class="h4 text-white fw-bold">Access Denied</h2>
                <p class="text-muted small mb-4">The requested project was not found or is not associated with your client account.</p>
                <a href="<?= BASE_URL ?>/client/projects.php" class="btn btn-amber btn-sm fw-bold">
                    <i class="fa-solid fa-arrow-left me-1"></i> Return to My Projects
                </a>
            </div>
        </main>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Synchronize project progress
$project['progress_percent'] = calculateProjectProgress($proj_id);

// Fetch milestones
$ms_stmt = $db->prepare("
    SELECT m.*, 
           (SELECT COUNT(*) FROM tasks t WHERE t.milestone_id = m.id) as total_tasks,
           (SELECT COUNT(*) FROM tasks t WHERE t.milestone_id = m.id AND t.status IN ('completed', 'done')) as completed_tasks
    FROM milestones m
    WHERE m.project_id = ?
    ORDER BY m.target_date ASC, m.id ASC
");
$ms_stmt->execute([$proj_id]);
$milestones = $ms_stmt->fetchAll();

foreach ($milestones as &$ms) {
    $ms['progress_percent'] = calculateMilestoneProgress($ms['id']);
}
unset($ms);

// Fetch tasks summary count
$t_summary_stmt = $db->prepare("
    SELECT status, COUNT(*) as count 
    FROM tasks 
    WHERE project_id = ? 
    GROUP BY status
");
$t_summary_stmt->execute([$proj_id]);
$task_counts = $t_summary_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$total_tasks = array_sum($task_counts);

// Fetch documents
$docs_stmt = $db->prepare("SELECT * FROM project_documents WHERE project_id = ? ORDER BY id DESC");
$docs_stmt->execute([$proj_id]);
$documents = $docs_stmt->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <!-- Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h1 class="h2 fw-bold text-white mb-0"><?= e($project['title']) ?></h1>
                    <span class="badge <?= get_status_badge_class($project['status']) ?> fs-6"><?= e(ucfirst(str_replace('_', ' ', $project['status']))) ?></span>
                </div>
                <p class="text-muted small mb-0">
                    <i class="fa-solid fa-location-dot text-danger me-1"></i><?= e($project['location']) ?>
                    • Managing Contractor: <strong class="text-info"><?= e($project['contractor_company'] ?: $project['contractor_name']) ?></strong>
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= BASE_URL ?>/client/projects.php" class="btn btn-outline-light btn-sm fw-bold">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Projects
                </a>
                <a href="<?= BASE_URL ?>/client/progress.php?project_id=<?= $proj_id ?>" class="btn btn-amber btn-sm fw-bold">
                    <i class="fa-solid fa-chart-line me-1"></i> Progress Dashboard
                </a>
            </div>
        </div>

        <!-- Metric Gauges -->
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
                    <div class="text-muted extra-small uppercase">Total Project Budget</div>
                    <div class="display-6 font-monospace text-success fw-bold my-1" style="font-size: 1.5rem;"><?= format_currency($project['budget']) ?></div>
                    <div class="extra-small text-muted">Contractor Agreed Budget</div>
                </div>
            </div>

            <div class="col-md-3 col-6">
                <div class="bc-card p-3">
                    <div class="text-muted extra-small uppercase">Timeline & Completion</div>
                    <div class="fw-bold text-light my-1" style="font-size: 0.95rem;"><?= format_date($project['start_date']) ?></div>
                    <div class="extra-small text-muted">Exp Finish: <?= format_date($project['end_date']) ?></div>
                </div>
            </div>

            <div class="col-md-3 col-6">
                <div class="bc-card p-3">
                    <div class="text-muted extra-small uppercase">Milestones & Tasks</div>
                    <div class="fw-bold text-info my-1" style="font-size: 0.95rem;"><?= count($milestones) ?> Milestones</div>
                    <div class="extra-small text-muted"><?= (int)($task_counts['completed'] ?? 0) + (int)($task_counts['done'] ?? 0) ?> of <?= $total_tasks ?> Tasks Completed</div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Left Column: Description & Milestones -->
            <div class="col-lg-8">
                <div class="bc-card p-4 mb-4">
                    <h2 class="h5 text-white fw-bold mb-3"><i class="fa-solid fa-circle-info text-warning me-2"></i>Project Overview</h2>
                    <div class="text-light lead-sm" style="white-space: pre-line;">
                        <?= e($project['description'] ?: 'No detailed project description available.') ?>
                    </div>
                </div>

                <!-- Milestones List -->
                <div class="bc-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 text-white fw-bold mb-0">
                            <i class="fa-solid fa-flag-checkered text-warning me-2"></i>Milestone Progress Monitor
                        </h2>
                        <a href="<?= BASE_URL ?>/client/milestones.php?project_id=<?= $proj_id ?>" class="extra-small text-info text-decoration-none">
                            View All Milestones <i class="fa-solid fa-arrow-right ms-1"></i>
                        </a>
                    </div>

                    <?php if (empty($milestones)): ?>
                        <div class="bc-empty-state py-4 text-center">
                            <p class="text-muted small mb-0">No milestones set for this project.</p>
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
                                                <span>Completion Progress</span>
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
            </div>

            <!-- Right Column: Contractor Info & Documents -->
            <div class="col-lg-4">
                <!-- Project Site Map Card (Read Only) -->
                <div class="bc-card p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h6 text-white fw-bold mb-0"><i class="fa-solid fa-map-location-dot text-warning me-2"></i>Project Site Location</h3>
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
                        <div id="clientProjectMap" style="height: 220px; width: 100%;" class="rounded border border-secondary bg-dark mb-2"></div>
                        <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            if (window.BC_Maps) {
                                const mapObj = BC_Maps.initProjectMap('clientProjectMap', <?= (float)$project['location_lat'] ?>, <?= (float)$project['location_lng'] ?>, 14);
                                if (mapObj) {
                                    BC_Maps.addProjectMarker(mapObj, <?= (float)$project['location_lat'] ?>, <?= (float)$project['location_lng'] ?>, "<?= e($project['title']) ?>", "<strong><?= e($project['title']) ?></strong><br><small><?= e($project['location']) ?></small>");
                                }
                            }
                        });
                        </script>
                    <?php else: ?>
                        <div class="p-3 bg-dark rounded text-center text-muted extra-small border border-secondary">
                            Project location has not been mapped yet.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Managing Contractor Info Card -->
                <div class="bc-card p-4 mb-4">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-user-gear text-info me-2"></i>Managing Contractor</h3>
                    <div class="fw-bold text-white mb-1"><?= e($project['contractor_company'] ?: $project['contractor_name']) ?></div>
                    <?php if ($project['license_no']): ?>
                        <div class="extra-small text-muted mb-2">License: <span class="text-light"><?= e($project['license_no']) ?></span></div>
                    <?php endif; ?>
                    <div class="extra-small text-muted"><i class="fa-solid fa-envelope me-1"></i><?= e($project['contractor_email']) ?></div>
                    <div class="extra-small text-muted"><i class="fa-solid fa-phone me-1"></i><?= e($project['contractor_phone']) ?></div>
                </div>

                <!-- Documents Card -->
                <div class="bc-card p-4">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-file-pdf text-danger me-2"></i>Project Documents</h3>
                    <?php if (empty($documents)): ?>
                        <p class="text-muted small mb-0">No documents uploaded.</p>
                    <?php else: ?>
                        <div class="vstack gap-2 extra-small">
                            <?php foreach ($documents as $doc): ?>
                                <div class="p-2 bg-dark rounded-3 border border-secondary d-flex justify-content-between align-items-center">
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
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
