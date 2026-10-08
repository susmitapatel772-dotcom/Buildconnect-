<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('client');

$page_title = "My Construction Projects - Client - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$client_user_id = (int)$user['id'];

// Fetch projects associated with client
$stmt = $db->prepare("
    SELECT p.*, 
           u_c.name as contractor_name, c_prof.company_name as contractor_company,
           (SELECT COUNT(*) FROM milestones m WHERE m.project_id = p.id) as total_milestones,
           (SELECT COUNT(*) FROM milestones m WHERE m.project_id = p.id AND m.status = 'completed') as completed_milestones,
           (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id) as total_tasks,
           (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status IN ('completed', 'done')) as completed_tasks
    FROM projects p
    JOIN users u_c ON p.contractor_id = u_c.id
    LEFT JOIN contractors c_prof ON u_c.id = c_prof.user_id
    WHERE p.client_id = ?
    ORDER BY p.id DESC
");
$stmt->execute([$client_user_id]);
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
                    <i class="fa-solid fa-building text-warning me-2"></i>My Construction Developments
                </h1>
                <p class="text-muted small mb-0">Real-time client monitoring of construction progress, milestone achievements, and contractor performance.</p>
            </div>
        </div>

        <div class="bc-card p-4">
            <?php if (empty($projects)): ?>
                <div class="bc-empty-state py-5 text-center">
                    <i class="fa-solid fa-building-circle-exclamation fs-1 text-muted mb-3"></i>
                    <h3 class="h5 text-white fw-bold">No Projects Linked</h3>
                    <p class="text-muted small mb-0">No construction projects are currently linked to your client developer account.</p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($projects as $p): ?>
                        <div class="col-lg-6">
                            <div class="bc-card p-4 h-100 d-flex flex-column justify-content-between border-secondary">
                                <div>
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                        <div>
                                            <h2 class="h5 text-white fw-bold mb-1">
                                                <a href="<?= BASE_URL ?>/client/project-details.php?id=<?= $p['id'] ?>" class="text-white text-decoration-none hover-amber">
                                                    <?= e($p['title']) ?>
                                                </a>
                                            </h2>
                                            <div class="text-muted small">
                                                <i class="fa-solid fa-user-gear me-1 text-info"></i>Contractor: <?= e($p['contractor_company'] ?: $p['contractor_name']) ?>
                                            </div>
                                        </div>
                                        <span class="badge <?= get_status_badge_class($p['status']) ?>"><?= e(ucfirst(str_replace('_', ' ', $p['status']))) ?></span>
                                    </div>

                                    <div class="text-muted extra-small mb-3">
                                        <i class="fa-solid fa-location-dot text-danger me-1"></i><?= e($p['location']) ?>
                                    </div>

                                    <div class="p-3 bg-dark rounded-3 border border-secondary mb-3">
                                        <div class="d-flex justify-content-between extra-small mb-1">
                                            <span class="text-muted">Overall Completion Progress</span>
                                            <span class="font-monospace text-warning fw-bold"><?= (int)$p['progress_percent'] ?>%</span>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-amber" style="width: <?= (int)$p['progress_percent'] ?>%"></div>
                                        </div>
                                    </div>

                                    <div class="row g-2 text-muted extra-small mb-3">
                                        <div class="col-6">
                                            Milestones: <strong class="text-light"><?= (int)$p['completed_milestones'] ?> / <?= (int)$p['total_milestones'] ?> Done</strong>
                                        </div>
                                        <div class="col-6">
                                            Tasks: <strong class="text-light"><?= (int)$p['completed_tasks'] ?> / <?= (int)$p['total_tasks'] ?> Completed</strong>
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-3 border-top border-secondary d-flex justify-content-between align-items-center">
                                    <span class="text-muted extra-small">Budget: <strong class="text-warning font-monospace"><?= format_currency($p['budget']) ?></strong></span>
                                    <a href="<?= BASE_URL ?>/client/project-details.php?id=<?= $p['id'] ?>" class="btn btn-amber btn-sm fw-bold extra-small py-1 px-3">
                                        Project Monitoring <i class="fa-solid fa-arrow-right ms-1"></i>
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
