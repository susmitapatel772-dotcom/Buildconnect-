<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('client');

$page_title = "Project Milestones - Client - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$client_user_id = (int)$user['id'];
$filter_project_id = (int)($_GET['project_id'] ?? 0);

// Fetch client projects for dropdown
$p_stmt = $db->prepare("SELECT id, title FROM projects WHERE client_id = ? ORDER BY id DESC");
$p_stmt->execute([$client_user_id]);
$client_projects = $p_stmt->fetchAll();

// Build Query
$sql = "
    SELECT m.*, p.title as project_title, p.location as project_location
    FROM milestones m
    JOIN projects p ON m.project_id = p.id
    WHERE p.client_id = ?
";
$params = [$client_user_id];

if ($filter_project_id > 0) {
    $sql .= " AND p.id = ?";
    $params[] = $filter_project_id;
}

$sql .= " ORDER BY m.target_date ASC, m.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$milestones = $stmt->fetchAll();

foreach ($milestones as &$ms) {
    $ms['progress_percent'] = calculateMilestoneProgress($ms['id']);
}
unset($ms);
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-flag-checkered text-warning me-2"></i>Project Milestones Tracker
                </h1>
                <p class="text-muted small mb-0">Read-only monitoring of construction phase deadlines, progress, and financial deliverables.</p>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="bc-card p-3 mb-4">
            <form action="<?= BASE_URL ?>/client/milestones.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-8">
                    <select name="project_id" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="0">All Associated Projects</option>
                        <?php foreach ($client_projects as $cp): ?>
                            <option value="<?= $cp['id'] ?>" <?= $filter_project_id == $cp['id'] ? 'selected' : '' ?>>
                                <?= e($cp['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-grid">
                    <button type="submit" class="btn btn-amber btn-sm fw-bold">
                        <i class="fa-solid fa-filter me-1"></i> Filter Milestones
                    </button>
                </div>
            </form>
        </div>

        <!-- Milestones List -->
        <div class="bc-card p-4">
            <?php if (empty($milestones)): ?>
                <div class="bc-empty-state py-5 text-center">
                    <i class="fa-solid fa-flag fs-1 text-muted mb-3"></i>
                    <h3 class="h5 text-white fw-bold">No Milestones Recorded</h3>
                    <p class="text-muted small mb-0">No project milestones match your filter selection.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Milestone Title</th>
                                <th>Project Name</th>
                                <th>Target Date</th>
                                <th>Budget Allocation</th>
                                <th>Completion Progress</th>
                                <th>Completed Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($milestones as $ms): ?>
                                <?php $deadline = get_deadline_status($ms['target_date'], $ms['status']); ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-white"><?= e($ms['title']) ?></div>
                                        <div class="text-muted extra-small"><?= e($ms['description']) ?></div>
                                    </td>
                                    <td class="small text-info">
                                        <?= e($ms['project_title']) ?>
                                    </td>
                                    <td>
                                        <div class="<?= $deadline['class'] ?> extra-small">
                                            <i class="fa-solid fa-clock me-1"></i><?= $deadline['label'] ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="font-monospace text-warning fw-bold"><?= format_currency($ms['amount']) ?></span>
                                    </td>
                                    <td style="min-width: 130px;">
                                        <div class="d-flex justify-content-between extra-small mb-1">
                                            <span class="text-muted">Progress</span>
                                            <span class="text-warning font-monospace fw-bold"><?= (int)$ms['progress_percent'] ?>%</span>
                                        </div>
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar bg-amber" style="width: <?= (int)$ms['progress_percent'] ?>%"></div>
                                        </div>
                                    </td>
                                    <td class="text-muted extra-small">
                                        <?= $ms['completed_at'] ? format_date($ms['completed_at']) : 'N/A' ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= get_status_badge_class($ms['status']) ?>"><?= e(ucfirst($ms['status'])) ?></span>
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
