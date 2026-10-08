<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('client');

$page_title = "Project Reports - Client - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$client_user_id = (int)$user['id'];

// Fetch Accessible Client Projects with summary metrics
$stmt = $db->prepare("
    SELECT p.*, u.name as contractor_name, c.company_name as contractor_company
    FROM projects p
    LEFT JOIN users u ON p.contractor_id = u.id
    LEFT JOIN contractors c ON c.user_id = u.id
    WHERE p.client_id = ?
    ORDER BY p.id DESC
");
$stmt->execute([$client_user_id]);
$projects = $stmt->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-chart-pie text-warning me-2"></i>Executive Project Reports
                </h1>
                <p class="text-muted small mb-0">Comprehensive progress summaries, milestone delivery metrics, and project status analytics.</p>
            </div>
        </div>

        <?php if (empty($projects)): ?>
            <div class="bc-card p-5 text-center">
                <div class="bc-empty-state border-0">
                    <i class="fa-solid fa-chart-pie fs-1 text-warning mb-3"></i>
                    <h3 class="h5 text-white fw-bold">No Reporting Data Available</h3>
                    <p class="text-muted small mb-0">There are no project records linked to your client account.</p>
                </div>
            </div>
        <?php else: ?>
            <!-- Project Executive Report Cards -->
            <div class="row g-4 mb-4">
                <?php foreach ($projects as $p): ?>
                    <?php
                    // Fetch Milestone Summary for this project
                    $stmt_ms = $db->prepare("
                        SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
                        FROM milestones 
                        WHERE project_id = ?
                    ");
                    $stmt_ms->execute([$p['id']]);
                    $m_stats = $stmt_ms->fetch();

                    // Fetch Documents Count
                    $stmt_dc = $db->prepare("SELECT COUNT(*) FROM project_documents WHERE project_id = ?");
                    $stmt_dc->execute([$p['id']]);
                    $docs_count = (int)$stmt_dc->fetchColumn();
                    ?>
                    <div class="col-lg-6">
                        <div class="bc-card p-4 h-100 border-secondary">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h2 class="h5 text-white fw-bold mb-1">
                                        <a href="<?= BASE_URL ?>/client/project-details.php?id=<?= $p['id'] ?>" class="text-white text-decoration-none hover-warning">
                                            <?= e($p['title']) ?>
                                        </a>
                                    </h2>
                                    <div class="text-warning fw-semibold extra-small">
                                        <i class="fa-solid fa-building me-1"></i>Contractor: <?= e($p['contractor_company'] ?? $p['contractor_name'] ?? 'Contractor') ?>
                                    </div>
                                </div>
                                <span class="badge <?= get_status_badge_class($p['status']) ?> text-uppercase">
                                    <?= e($p['status']) ?>
                                </span>
                            </div>

                            <!-- Progress Summary Bar -->
                            <div class="bg-dark p-3 rounded-3 border border-secondary mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted extra-small text-uppercase fw-semibold">Overall Site Completion</span>
                                    <span class="fs-5 fw-bold text-warning font-monospace"><?= (int)$p['progress_percent'] ?>%</span>
                                </div>
                                <div class="progress bg-secondary" style="height: 8px;">
                                    <div class="progress-bar bg-warning" style="width: <?= (int)$p['progress_percent'] ?>%;"></div>
                                </div>
                            </div>

                            <!-- Milestone Breakdown -->
                            <div class="row g-2 text-center mb-3">
                                <div class="col-4">
                                    <div class="bg-dark p-2 rounded border border-secondary">
                                        <span class="text-muted extra-small d-block">Completed</span>
                                        <span class="fw-bold text-success font-monospace"><?= (int)($m_stats['completed'] ?? 0) ?></span>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="bg-dark p-2 rounded border border-secondary">
                                        <span class="text-muted extra-small d-block">In Progress</span>
                                        <span class="fw-bold text-info font-monospace"><?= (int)($m_stats['in_progress'] ?? 0) ?></span>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="bg-dark p-2 rounded border border-secondary">
                                        <span class="text-muted extra-small d-block">Pending</span>
                                        <span class="fw-bold text-warning font-monospace"><?= (int)($m_stats['pending'] ?? 0) ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center text-muted extra-small pt-2 border-top border-secondary">
                                <span><i class="fa-solid fa-file-contract me-1 text-primary"></i><?= $docs_count ?> Documents</span>
                                <span>Budget: <strong class="text-white font-monospace"><?= format_currency($p['budget']) ?></strong></span>
                                <a href="<?= BASE_URL ?>/client/project-details.php?id=<?= $p['id'] ?>" class="btn btn-outline-amber btn-sm extra-small">
                                    Detailed Report <i class="fa-solid fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
