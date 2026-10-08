<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('worker');

$page_title = "My Assigned Projects - Worker - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$worker_user_id = (int)$user['id'];

// Fetch projects where worker is an active member
$stmt = $db->prepare("
    SELECT p.*, pm.role_in_project, pm.joined_at,
           u_c.name as contractor_name, c_prof.company_name as contractor_company,
           (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.assigned_to_worker_id = ?) as my_tasks_count,
           (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.assigned_to_worker_id = ? AND t.status NOT IN ('completed', 'done', 'cancelled')) as my_pending_tasks_count
    FROM projects p
    JOIN project_members pm ON p.id = pm.project_id
    JOIN users u_c ON p.contractor_id = u_c.id
    LEFT JOIN contractors c_prof ON u_c.id = c_prof.user_id
    WHERE pm.user_id = ? AND pm.status = 'active'
    ORDER BY p.id DESC
");
$stmt->execute([$worker_user_id, $worker_user_id, $worker_user_id]);
$projects = $stmt->fetchAll();

// Synchronize project progress
foreach ($projects as &$proj) {
    $proj['progress_percent'] = calculateProjectProgress($proj['id']);
}
unset($proj);
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-building text-warning me-2"></i>My Assigned Projects
                </h1>
                <p class="text-muted small mb-0">Overview of construction developments and site projects where you are an active team member.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/worker/tasks.php" class="btn btn-amber btn-sm fw-bold">
                    <i class="fa-solid fa-list-check me-1"></i> View My Tasks
                </a>
            </div>
        </div>

        <div class="bc-card p-4">
            <?php if (empty($projects)): ?>
                <div class="bc-empty-state py-5 text-center">
                    <i class="fa-solid fa-building-circle-exclamation fs-1 text-muted mb-3"></i>
                    <h3 class="h5 text-white fw-bold">No Active Project Assignments</h3>
                    <p class="text-muted small mb-4">You are not currently assigned to any construction projects. Apply for trade job postings to join project teams.</p>
                    <a href="<?= BASE_URL ?>/worker/jobs.php" class="btn btn-amber btn-sm fw-bold">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Browse Open Trade Jobs
                    </a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($projects as $p): ?>
                        <div class="col-lg-6">
                            <div class="bc-card p-4 h-100 d-flex flex-column justify-content-between border-secondary">
                                <div>
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                        <div>
                                            <span class="badge bg-dark border border-secondary text-warning extra-small mb-1"><?= e($p['role_in_project']) ?></span>
                                            <h2 class="h5 text-white fw-bold mb-1"><?= e($p['title']) ?></h2>
                                            <div class="text-muted small">
                                                <i class="fa-solid fa-building me-1 text-info"></i><?= e($p['contractor_company'] ?: $p['contractor_name']) ?>
                                            </div>
                                        </div>
                                        <span class="badge <?= get_status_badge_class($p['status']) ?>"><?= e(ucfirst(str_replace('_', ' ', $p['status']))) ?></span>
                                    </div>

                                    <div class="text-muted extra-small mb-3 d-flex justify-content-between align-items-center">
                                        <span><i class="fa-solid fa-location-dot text-danger me-1"></i><?= e($p['location']) ?></span>
                                        <?php if ($p['location_lat'] && $p['location_lng']): ?>
                                            <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $p['location_lat'] ?>,<?= $p['location_lng'] ?>" target="_blank" rel="noopener" class="text-warning extra-small text-decoration-none">
                                                <i class="fa-solid fa-route me-1"></i> Directions
                                            </a>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($p['location_lat'] && $p['location_lng']): ?>
                                        <div id="workerMap<?= $p['id'] ?>" style="height: 140px; width: 100%;" class="rounded border border-secondary bg-dark mb-3"></div>
                                        <script>
                                        document.addEventListener('DOMContentLoaded', function () {
                                            if (window.BC_Maps) {
                                                const mapObj = BC_Maps.initProjectMap('workerMap<?= $p['id'] ?>', <?= (float)$p['location_lat'] ?>, <?= (float)$p['location_lng'] ?>, 13);
                                                if (mapObj) {
                                                    BC_Maps.addProjectMarker(mapObj, <?= (float)$p['location_lat'] ?>, <?= (float)$p['location_lng'] ?>, "<?= e($p['title']) ?>", "<strong><?= e($p['title']) ?></strong><br><small><?= e($p['location']) ?></small>");
                                                }
                                            }
                                        });
                                        </script>
                                    <?php endif; ?>

                                    <div class="p-3 bg-dark rounded-3 border border-secondary mb-3">
                                        <div class="d-flex justify-content-between extra-small mb-1">
                                            <span class="text-muted">Overall Project Progress</span>
                                            <span class="font-monospace text-warning fw-bold"><?= (int)$p['progress_percent'] ?>%</span>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-amber" style="width: <?= (int)$p['progress_percent'] ?>%"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-3 border-top border-secondary d-flex justify-content-between align-items-center extra-small">
                                    <div class="text-muted">
                                        My Assigned Tasks: <strong class="text-warning"><?= (int)$p['my_pending_tasks_count'] ?> Pending</strong> / <?= (int)$p['my_tasks_count'] ?> Total
                                    </div>
                                    <a href="<?= BASE_URL ?>/worker/tasks.php?project_id=<?= $p['id'] ?>" class="btn btn-amber btn-sm fw-bold extra-small py-1 px-3">
                                        View Tasks <i class="fa-solid fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
