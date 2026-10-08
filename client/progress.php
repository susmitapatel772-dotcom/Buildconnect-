<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('client');

$page_title = "Progress Dashboard - Client - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$client_user_id = (int)$user['id'];
$filter_project_id = (int)($_GET['project_id'] ?? 0);

// Fetch client projects
$p_stmt = $db->prepare("SELECT id, title FROM projects WHERE client_id = ? ORDER BY id DESC");
$p_stmt->execute([$client_user_id]);
$client_projects = $p_stmt->fetchAll();

// Default project selection if not specified
if ($filter_project_id <= 0 && !empty($client_projects)) {
    $filter_project_id = $client_projects[0]['id'];
}

$project = null;
$milestones = [];
$task_stats = [];

if ($filter_project_id > 0) {
    // Verify client authorization
    $stmt = $db->prepare("
        SELECT p.*, u_c.name as contractor_name, c_prof.company_name as contractor_company
        FROM projects p
        JOIN users u_c ON p.contractor_id = u_c.id
        LEFT JOIN contractors c_prof ON u_c.id = c_prof.user_id
        WHERE p.id = ? AND p.client_id = ?
    ");
    $stmt->execute([$filter_project_id, $client_user_id]);
    $project = $stmt->fetch();

    if ($project) {
        $project['progress_percent'] = calculateProjectProgress($filter_project_id);

        // Fetch milestones
        $ms_stmt = $db->prepare("SELECT * FROM milestones WHERE project_id = ? ORDER BY target_date ASC");
        $ms_stmt->execute([$filter_project_id]);
        $milestones = $ms_stmt->fetchAll();

        foreach ($milestones as &$ms) {
            $ms['progress_percent'] = calculateMilestoneProgress($ms['id']);
        }
        unset($ms);

        // Task statistics
        $t_stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_tasks,
                SUM(CASE WHEN status IN ('completed', 'done') THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
                SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) as blocked_tasks,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks
            FROM tasks 
            WHERE project_id = ?
        ");
        $t_stmt->execute([$filter_project_id]);
        $task_stats = $t_stmt->fetch();
    }
}
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-chart-line text-warning me-2"></i>Construction Progress Monitor
                </h1>
                <p class="text-muted small mb-0">Track project velocity, milestone achievements, and completion metrics.</p>
            </div>
        </div>

        <!-- Project Filter Bar -->
        <div class="bc-card p-3 mb-4">
            <form action="<?= BASE_URL ?>/client/progress.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-8">
                    <select name="project_id" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <?php foreach ($client_projects as $cp): ?>
                            <option value="<?= $cp['id'] ?>" <?= $filter_project_id == $cp['id'] ? 'selected' : '' ?>>
                                <?= e($cp['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-grid">
                    <button type="submit" class="btn btn-amber btn-sm fw-bold">
                        <i class="fa-solid fa-chart-pie me-1"></i> Load Dashboard
                    </button>
                </div>
            </form>
        </div>

        <?php if (!$project): ?>
            <div class="bc-card p-5 text-center my-4">
                <i class="fa-solid fa-chart-area fs-1 text-muted mb-3"></i>
                <h3 class="h5 text-white fw-bold">No Project Selected</h3>
                <p class="text-muted small mb-0">Please select a construction project above to inspect progress stats.</p>
            </div>
        <?php else: ?>
            <!-- Progress Gauges -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="bc-card p-4 text-center h-100">
                        <h3 class="h6 text-muted extra-small uppercase mb-2">Overall Project Completion</h3>
                        <div class="display-4 font-monospace text-warning fw-bold my-2"><?= (int)$project['progress_percent'] ?>%</div>
                        <div class="progress mb-3" style="height: 8px;">
                            <div class="progress-bar bg-amber" style="width: <?= (int)$project['progress_percent'] ?>%"></div>
                        </div>
                        <span class="badge <?= get_status_badge_class($project['status']) ?>"><?= e(ucfirst(str_replace('_', ' ', $project['status']))) ?></span>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="bc-card p-4 text-center h-100">
                        <h3 class="h6 text-muted extra-small uppercase mb-2">Milestone Completion</h3>
                        <?php
                        $total_ms = count($milestones);
                        $completed_ms = 0;
                        foreach ($milestones as $m) {
                            if ($m['status'] === 'completed' || $m['progress_percent'] == 100) $completed_ms++;
                        }
                        $ms_percent = $total_ms > 0 ? round(($completed_ms / $total_ms) * 100) : 0;
                        ?>
                        <div class="display-4 font-monospace text-info fw-bold my-2"><?= $completed_ms ?> / <?= $total_ms ?></div>
                        <div class="progress mb-3" style="height: 8px;">
                            <div class="progress-bar bg-info" style="width: <?= $ms_percent ?>%"></div>
                        </div>
                        <div class="extra-small text-muted"><?= $ms_percent ?>% Milestones Achieved</div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="bc-card p-4 text-center h-100">
                        <h3 class="h6 text-muted extra-small uppercase mb-2">Workforce Task Execution</h3>
                        <?php
                        $t_done = (int)($task_stats['completed_tasks'] ?? 0);
                        $t_total = (int)($task_stats['total_tasks'] ?? 0);
                        $t_percent = $t_total > 0 ? round(($t_done / $t_total) * 100) : 0;
                        ?>
                        <div class="display-4 font-monospace text-success fw-bold my-2"><?= $t_done ?> / <?= $t_total ?></div>
                        <div class="progress mb-3" style="height: 8px;">
                            <div class="progress-bar bg-success" style="width: <?= $t_percent ?>%"></div>
                        </div>
                        <div class="extra-small text-muted"><?= $t_percent ?>% Site Tasks Completed</div>
                    </div>
                </div>
            </div>

            <!-- Milestone Completion Breakdown -->
            <div class="bc-card p-4">
                <h2 class="h5 text-white fw-bold mb-3">
                    <i class="fa-solid fa-list-check text-warning me-2"></i>Milestone Breakdown
                </h2>

                <?php if (empty($milestones)): ?>
                    <p class="text-muted small">No milestones defined for this project.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Milestone Phase</th>
                                    <th>Target Completion Date</th>
                                    <th>Budget Allocation</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($milestones as $m): ?>
                                    <?php $deadline = get_deadline_status($m['target_date'], $m['status']); ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-white"><?= e($m['title']) ?></div>
                                            <div class="extra-small text-muted"><?= e($m['description']) ?></div>
                                        </td>
                                        <td>
                                            <div class="<?= $deadline['class'] ?> extra-small">
                                                <i class="fa-solid fa-clock me-1"></i><?= $deadline['label'] ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="font-monospace text-warning fw-bold"><?= format_currency($m['amount']) ?></span>
                                        </td>
                                        <td style="min-width: 140px;">
                                            <div class="d-flex justify-content-between extra-small mb-1">
                                                <span class="text-muted">Progress</span>
                                                <span class="text-warning font-monospace fw-bold"><?= (int)$m['progress_percent'] ?>%</span>
                                            </div>
                                            <div class="progress" style="height: 5px;">
                                                <div class="progress-bar bg-amber" style="width: <?= (int)$m['progress_percent'] ?>%"></div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?= get_status_badge_class($m['status']) ?>"><?= e(ucfirst($m['status'])) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
