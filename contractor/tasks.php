<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('contractor');

$page_title = "Workforce Tasks - Contractor - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$contractor_id = (int)$user['id'];

$error = '';
$flash = get_flash_message();

// Filters & Pagination
$filter_project_id = (int)($_GET['project_id'] ?? 0);
$filter_status = sanitize($_GET['status'] ?? 'all');
$filter_priority = sanitize($_GET['priority'] ?? 'all');
$filter_worker_id = (int)($_GET['worker_id'] ?? 0);
$search = sanitize($_GET['q'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Fetch contractor projects for filter dropdown
$p_stmt = $db->prepare("SELECT id, title FROM projects WHERE contractor_id = ? ORDER BY id DESC");
$p_stmt->execute([$contractor_id]);
$contractor_projects = $p_stmt->fetchAll();

// Fetch contractor project active workers for filter dropdown
$w_stmt = $db->prepare("
    SELECT DISTINCT u.id, u.name 
    FROM project_members pm
    JOIN projects p ON pm.project_id = p.id
    JOIN users u ON pm.user_id = u.id
    WHERE p.contractor_id = ? AND u.role = 'worker'
    ORDER BY u.name ASC
");
$w_stmt->execute([$contractor_id]);
$contractor_workers = $w_stmt->fetchAll();

// Build query
$where_clauses = ["p.contractor_id = ?"];
$params = [$contractor_id];

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

if ($filter_worker_id > 0) {
    $where_clauses[] = "t.assigned_to_worker_id = ?";
    $params[] = $filter_worker_id;
}

if (!empty($search)) {
    $where_clauses[] = "(t.title LIKE ? OR t.description LIKE ?)";
    $s_param = "%{$search}%";
    $params[] = $s_param;
    $params[] = $s_param;
}

$where_sql = implode(' AND ', $where_clauses);

// Total count
$count_stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    WHERE {$where_sql}
");
$count_stmt->execute($params);
$total_tasks = (int)$count_stmt->fetchColumn();
$total_pages = max(1, ceil($total_tasks / $per_page));

// Fetch tasks list
$sql = "
    SELECT t.*, p.title as project_title, m.title as milestone_title,
           u.name as worker_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    LEFT JOIN milestones m ON t.milestone_id = m.id
    LEFT JOIN users u ON t.assigned_to_worker_id = u.id
    WHERE {$where_sql}
    ORDER BY t.due_date ASC, t.id DESC
    LIMIT {$per_page} OFFSET {$offset}
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-list-check text-info me-2"></i>Workforce Task Management
                </h1>
                <p class="text-muted small mb-0">Assign construction tasks to active project workers, track progress, and monitor deadlines.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/contractor/create-task.php<?= $filter_project_id ? "?project_id={$filter_project_id}" : "" ?>" class="btn btn-amber fw-bold py-2 px-3">
                    <i class="fa-solid fa-plus me-1"></i> Assign New Task
                </a>
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

        <!-- Filters Bar -->
        <div class="bc-card p-3 mb-4">
            <form action="<?= BASE_URL ?>/contractor/tasks.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <input type="text" name="q" value="<?= e($search) ?>" class="form-control form-control-sm bg-dark border-secondary text-light" placeholder="Search task title...">
                </div>

                <div class="col-md-3">
                    <select name="project_id" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="0">All Contractor Projects</option>
                        <?php foreach ($contractor_projects as $cp): ?>
                            <option value="<?= $cp['id'] ?>" <?= $filter_project_id == $cp['id'] ? 'selected' : '' ?>>
                                <?= e($cp['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="all">All Statuses</option>
                        <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="in_progress" <?= $filter_status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="blocked" <?= $filter_status === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                        <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="priority" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="all">All Priorities</option>
                        <option value="critical" <?= $filter_priority === 'critical' ? 'selected' : '' ?>>Critical</option>
                        <option value="high" <?= $filter_priority === 'high' ? 'selected' : '' ?>>High</option>
                        <option value="medium" <?= $filter_priority === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="low" <?= $filter_priority === 'low' ? 'selected' : '' ?>>Low</option>
                    </select>
                </div>

                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-amber btn-sm fw-bold">
                        <i class="fa-solid fa-filter me-1"></i> Filter Tasks
                    </button>
                </div>
            </form>
        </div>

        <!-- Tasks List Table -->
        <div class="bc-card p-4">
            <?php if (empty($tasks)): ?>
                <div class="bc-empty-state py-5 text-center">
                    <i class="fa-solid fa-list-check fs-1 text-muted mb-3"></i>
                    <h3 class="h5 text-white fw-bold">No Tasks Found</h3>
                    <p class="text-muted small mb-4">No tasks match your search and filter criteria.</p>
                    <a href="<?= BASE_URL ?>/contractor/create-task.php" class="btn btn-amber btn-sm fw-bold">
                        <i class="fa-solid fa-plus me-1"></i> Assign First Task
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Task Title & Milestone</th>
                                <th>Project</th>
                                <th>Assigned Worker</th>
                                <th>Priority</th>
                                <th>Deadline</th>
                                <th>Progress</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $t): ?>
                                <?php $deadline = get_deadline_status($t['due_date'], $t['status']); ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_URL ?>/contractor/task-details.php?id=<?= $t['id'] ?>" class="fw-bold text-white text-decoration-none hover-amber">
                                            <?= e($t['title']) ?>
                                        </a>
                                        <?php if (!empty($t['milestone_title'])): ?>
                                            <div class="extra-small text-muted"><i class="fa-solid fa-flag me-1 text-warning"></i><?= e($t['milestone_title']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-info">
                                        <?= e($t['project_title']) ?>
                                    </td>
                                    <td class="small text-light">
                                        <?php if ($t['worker_name']): ?>
                                            <i class="fa-solid fa-user text-warning me-1"></i><?= e($t['worker_name']) ?>
                                        <?php else: ?>
                                            <span class="text-muted italic">Unassigned</span>
                                        <?php endif; ?>
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
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= BASE_URL ?>/contractor/task-details.php?id=<?= $t['id'] ?>" class="btn btn-outline-light" title="View Details">
                                                <i class="fa-solid fa-eye text-info"></i>
                                            </a>
                                            <a href="<?= BASE_URL ?>/contractor/edit-task.php?id=<?= $t['id'] ?>" class="btn btn-outline-light" title="Edit Task">
                                                <i class="fa-solid fa-pen text-warning"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Nav -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center pt-3 border-top border-secondary mt-3">
                        <span class="text-muted extra-small">Showing Page <?= $page ?> of <?= $total_pages ?> (Total <?= $total_tasks ?> Tasks)</span>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link bg-dark border-secondary text-light" href="<?= BASE_URL ?>/contractor/tasks.php?page=<?= $page - 1 ?>&project_id=<?= $filter_project_id ?>&status=<?= urlencode($filter_status) ?>&priority=<?= urlencode($filter_priority) ?>&q=<?= urlencode($search) ?>">Prev</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link <?= $i === $page ? 'bg-amber text-dark border-amber fw-bold' : 'bg-dark border-secondary text-light' ?>" href="<?= BASE_URL ?>/contractor/tasks.php?page=<?= $i ?>&project_id=<?= $filter_project_id ?>&status=<?= urlencode($filter_status) ?>&priority=<?= urlencode($filter_priority) ?>&q=<?= urlencode($search) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link bg-dark border-secondary text-light" href="<?= BASE_URL ?>/contractor/tasks.php?page=<?= $page + 1 ?>&project_id=<?= $filter_project_id ?>&status=<?= urlencode($filter_status) ?>&priority=<?= urlencode($filter_priority) ?>&q=<?= urlencode($search) ?>">Next</a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
