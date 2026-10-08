<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('worker');

$page_title = "My Assigned Tasks - Worker - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$worker_user_id = (int)$user['id'];

$error = '';
$flash = get_flash_message();

// Filters
$filter_project_id = (int)($_GET['project_id'] ?? 0);
$filter_status = sanitize($_GET['status'] ?? 'all');
$filter_priority = sanitize($_GET['priority'] ?? 'all');

// Fetch worker projects for dropdown
$p_stmt = $db->prepare("
    SELECT DISTINCT p.id, p.title 
    FROM projects p
    JOIN project_members pm ON p.id = pm.project_id
    WHERE pm.user_id = ? AND pm.status = 'active'
    ORDER BY p.id DESC
");
$p_stmt->execute([$worker_user_id]);
$worker_projects = $p_stmt->fetchAll();

// Build query for worker tasks ONLY
$where_clauses = ["t.assigned_to_worker_id = ?"];
$params = [$worker_user_id];

if ($filter_project_id > 0) {
    $where_clauses[] = "t.project_id = ?";
    $params[] = $filter_project_id;
}

if (in_array($filter_status, ['pending', 'todo', 'in_progress', 'review', 'blocked', 'done', 'completed', 'cancelled'])) {
    $where_clauses[] = "t.status = ?";
    $params[] = $filter_status;
}

if (in_array($filter_priority, ['low', 'medium', 'high', 'urgent', 'critical'])) {
    $where_clauses[] = "t.priority = ?";
    $params[] = $filter_priority;
}

$where_sql = implode(' AND ', $where_clauses);

$stmt = $db->prepare("
    SELECT t.*, p.title as project_title, m.title as milestone_title,
           c.company_name as contractor_company, u.name as contractor_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    JOIN users u ON p.contractor_id = u.id
    LEFT JOIN contractors c ON u.id = c.user_id
    LEFT JOIN milestones m ON t.milestone_id = m.id
    WHERE {$where_sql}
    ORDER BY t.due_date ASC, t.id DESC
");
$stmt->execute($params);
$tasks = $stmt->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-list-check text-warning me-2"></i>My Assigned Site Tasks
                </h1>
                <p class="text-muted small mb-0">Track construction site assignments, update progress, and report completion status to contractors.</p>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show py-2 px-3 small mb-3">
                <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3">
                <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= sanitize($error) ?>
            </div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="bc-card p-3 mb-4">
            <form action="<?= BASE_URL ?>/worker/tasks.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <select name="project_id" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="0">All My Projects</option>
                        <?php foreach ($worker_projects as $wp): ?>
                            <option value="<?= $wp['id'] ?>" <?= $filter_project_id == $wp['id'] ? 'selected' : '' ?>>
                                <?= e($wp['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="all">All Task Statuses</option>
                        <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="in_progress" <?= $filter_status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="blocked" <?= $filter_status === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                        <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="priority" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="all">All Priorities</option>
                        <option value="critical" <?= $filter_priority === 'critical' ? 'selected' : '' ?>>Critical</option>
                        <option value="high" <?= $filter_priority === 'high' ? 'selected' : '' ?>>High</option>
                        <option value="medium" <?= $filter_priority === 'medium' ? 'selected' : '' ?>>Medium</option>
                    </select>
                </div>

                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-amber btn-sm fw-bold">
                        <i class="fa-solid fa-filter me-1"></i> Filter Tasks
                    </button>
                </div>
            </form>
        </div>

        <!-- Tasks Table -->
        <div class="bc-card p-4">
            <?php if (empty($tasks)): ?>
                <div class="bc-empty-state py-5 text-center">
                    <i class="fa-solid fa-check-double fs-1 text-muted mb-3"></i>
                    <h3 class="h5 text-white fw-bold">No Tasks Assigned</h3>
                    <p class="text-muted small mb-0">You currently have no site tasks assigned under the selected filters.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Task Title</th>
                                <th>Project & Contractor</th>
                                <th>Milestone</th>
                                <th>Priority</th>
                                <th>Deadline</th>
                                <th>Progress</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $t): ?>
                                <?php $deadline = get_deadline_status($t['due_date'], $t['status']); ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_URL ?>/worker/task-details.php?id=<?= $t['id'] ?>" class="fw-bold text-white text-decoration-none hover-amber">
                                            <?= e($t['title']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-info small"><?= e($t['project_title']) ?></div>
                                        <div class="extra-small text-muted"><?= e($t['contractor_company'] ?: $t['contractor_name']) ?></div>
                                    </td>
                                    <td class="small text-muted">
                                        <?= e($t['milestone_title'] ?: 'General Task') ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= get_priority_badge_class($t['priority']) ?>"><?= e(ucfirst($t['priority'])) ?></span>
                                    </td>
                                    <td>
                                        <div class="<?= $deadline['class'] ?> extra-small">
                                            <i class="fa-solid fa-clock me-1"></i><?= $deadline['label'] ?>
                                        </div>
                                    </td>
                                    <td style="min-width: 110px;">
                                        <div class="d-flex justify-content-between extra-small mb-1">
                                            <span class="text-muted">Prog</span>
                                            <span class="text-warning font-monospace fw-bold"><?= (int)$t['progress_percent'] ?>%</span>
                                        </div>
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar bg-amber" style="width: <?= (int)$t['progress_percent'] ?>%"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?= get_status_badge_class($t['status']) ?>"><?= e(ucfirst(str_replace('_', ' ', $t['status']))) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>/worker/task-details.php?id=<?= $t['id'] ?>" class="btn btn-amber btn-sm extra-small py-1 px-3 fw-bold">
                                            Update Progress <i class="fa-solid fa-chevron-right ms-1"></i>
                                        </a>
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
