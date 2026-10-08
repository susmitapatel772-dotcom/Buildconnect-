<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('client');

$page_title = "Project Tasks - Client Dashboard - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$client_user_id = (int)$user['id'];

$error = '';
$flash = get_flash_message();

// Handle Task Actions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security validation failed (CSRF token invalid). Please try again.';
    } else {
        $action = sanitize($_POST['action'] ?? 'create');

        if ($action === 'create') {
            $project_id = (int)($_POST['project_id'] ?? 0);
            $milestone_id = !empty($_POST['milestone_id']) ? (int)$_POST['milestone_id'] : null;
            $assigned_worker_id = !empty($_POST['assigned_to_worker_id']) ? (int)$_POST['assigned_to_worker_id'] : null;
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $priority = sanitize($_POST['priority'] ?? 'medium');
            $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
            $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

            // Server-side Authorization: Verify project belongs to logged-in client
            $p_chk = $db->prepare("SELECT id, title, contractor_id FROM projects WHERE id = ? AND client_id = ?");
            $p_chk->execute([$project_id, $client_user_id]);
            $project = $p_chk->fetch();

            if (!$project) {
                $error = 'Unauthorized project selection or project does not exist.';
            } elseif (empty($title)) {
                $error = 'Please enter a valid task title.';
            } else {
                try {
                    $db->beginTransaction();

                    $stmt = $db->prepare("
                        INSERT INTO tasks (project_id, milestone_id, title, description, assigned_to_worker_id, priority, status, progress_percent, start_date, due_date, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, 'pending', 0, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $project_id,
                        $milestone_id,
                        $title,
                        $description,
                        $assigned_worker_id,
                        $priority,
                        $start_date,
                        $due_date
                    ]);
                    $task_id = $db->lastInsertId();

                    if ($milestone_id) {
                        calculateMilestoneProgress($milestone_id);
                    }
                    calculateProjectProgress($project_id);

                    log_activity($client_user_id, 'CLIENT_CREATED_TASK', "Created task: {$title} on project: " . $project['title'], 'task', $task_id);
                    create_notification($project['contractor_id'], 'New Task Posted', "Client {$user['name']} added new task: {$title} for project {$project['title']}.", 'TASK', "/contractor/tasks.php", 'task', $task_id);

                    $db->commit();
                    set_flash_message('Task "' . e($title) . '" created successfully!', 'success');
                    redirect('/client/tasks.php');
                } catch (Exception $e) {
                    $db->rollBack();
                    error_log("Client task creation failed: " . $e->getMessage());
                    $error = 'Failed to create task due to a database error.';
                }
            }
        } elseif ($action === 'update_status') {
            $task_id = (int)($_POST['task_id'] ?? 0);
            $new_status = sanitize($_POST['status'] ?? '');
            $new_progress = isset($_POST['progress_percent']) ? max(0, min(100, (int)$_POST['progress_percent'])) : null;

            // Authorization: Task must belong to a project owned by logged-in client
            $t_chk = $db->prepare("
                SELECT t.id, t.project_id, t.milestone_id, t.title, p.title as project_title 
                FROM tasks t 
                JOIN projects p ON t.project_id = p.id 
                WHERE t.id = ? AND p.client_id = ?
            ");
            $t_chk->execute([$task_id, $client_user_id]);
            $task = $t_chk->fetch();

            if (!$task) {
                $error = 'Unauthorized task access or task not found.';
            } elseif (!in_array($new_status, ['pending', 'todo', 'in_progress', 'review', 'blocked', 'done', 'completed', 'cancelled'])) {
                $error = 'Invalid task status selected.';
            } else {
                try {
                    $db->beginTransaction();

                    if ($new_progress === null) {
                        $new_progress = ($new_status === 'completed' || $new_status === 'done') ? 100 : (($new_status === 'in_progress') ? 50 : 0);
                    }

                    $completed_at_sql = ($new_status === 'completed' || $new_status === 'done') ? "NOW()" : "NULL";

                    $up = $db->prepare("
                        UPDATE tasks 
                        SET status = ?, progress_percent = ?, completed_at = {$completed_at_sql}, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $up->execute([$new_status, $new_progress, $task_id]);

                    if ($task['milestone_id']) {
                        calculateMilestoneProgress($task['milestone_id']);
                    }
                    calculateProjectProgress($task['project_id']);

                    log_activity($client_user_id, 'CLIENT_UPDATED_TASK_STATUS', "Updated task #{$task_id} status to {$new_status}", 'task', $task_id);

                    $db->commit();
                    set_flash_message('Task status updated successfully!', 'success');
                    redirect('/client/tasks.php');
                } catch (Exception $e) {
                    $db->rollBack();
                    error_log("Client task status update failed: " . $e->getMessage());
                    $error = 'Failed to update task status.';
                }
            }
        }
    }
}

// Search & Filter GET parameters
$filter_project_id = (int)($_GET['project_id'] ?? 0);
$filter_status = sanitize($_GET['status'] ?? 'all');
$filter_priority = sanitize($_GET['priority'] ?? 'all');
$search = sanitize($_GET['q'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Fetch Client Authorized Projects
$cp_stmt = $db->prepare("SELECT id, title FROM projects WHERE client_id = ? ORDER BY id DESC");
$cp_stmt->execute([$client_user_id]);
$client_projects = $cp_stmt->fetchAll();

// Build Query
$where = ["p.client_id = ?"];
$params = [$client_user_id];

if ($filter_project_id > 0) {
    $where[] = "t.project_id = ?";
    $params[] = $filter_project_id;
}

if (in_array($filter_status, ['pending', 'todo', 'in_progress', 'review', 'blocked', 'done', 'completed', 'cancelled'])) {
    $where[] = "t.status = ?";
    $params[] = $filter_status;
}

if (in_array($filter_priority, ['low', 'medium', 'high', 'urgent', 'critical'])) {
    $where[] = "t.priority = ?";
    $params[] = $filter_priority;
}

if (!empty($search)) {
    $where[] = "(t.title LIKE ? OR t.description LIKE ?)";
    $s_term = "%{$search}%";
    $params[] = $s_term;
    $params[] = $s_term;
}

$where_sql = implode(' AND ', $where);

// Total Count for Pagination
$count_stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    WHERE {$where_sql}
");
$count_stmt->execute($params);
$total_rows = (int)$count_stmt->fetchColumn();
$total_pages = max(1, ceil($total_rows / $per_page));

// Summary Metrics
$m_stmt = $db->prepare("
    SELECT 
        COUNT(t.id) as total_tasks,
        SUM(CASE WHEN t.status IN ('in_progress', 'todo') THEN 1 ELSE 0 END) as active_tasks,
        SUM(CASE WHEN t.status IN ('completed', 'done') THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN t.priority IN ('high', 'urgent', 'critical') THEN 1 ELSE 0 END) as high_priority_tasks
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    WHERE p.client_id = ?
");
$m_stmt->execute([$client_user_id]);
$metrics = $m_stmt->fetch();

// Fetch Tasks Listing
$sql = "
    SELECT t.*, p.title as project_title, m.title as milestone_title,
           w.name as worker_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    LEFT JOIN milestones m ON t.milestone_id = m.id
    LEFT JOIN users w ON t.assigned_to_worker_id = w.id
    WHERE {$where_sql}
    ORDER BY t.due_date ASC, t.id DESC
    LIMIT {$per_page} OFFSET {$offset}
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();
?>

<div class="dashboard-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="dashboard-content">
        <!-- Page Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h1 class="h2 fw-extrabold text-white bc-page-title mb-1">
                    <i class="fa-solid fa-list-check text-warning me-2"></i>Construction Project Tasks
                </h1>
                <p class="text-slate-400 small mb-0">Monitor site execution tasks, deliverables, milestone progress, and worker assignments.</p>
            </div>
            <div>
                <button type="button" class="btn btn-orange fw-bold rounded-3 px-3 py-2 shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#newTaskModal">
                    <i class="fa-solid fa-plus"></i> Post New Task
                </button>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show mb-4" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i> <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= sanitize($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- KPI Metrics Row -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="bc-metric-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bc-metric-badge-blue p-2.5 rounded-3"><i class="fa-solid fa-list-check fs-5"></i></div>
                        <span class="text-slate-400 extra-small fw-bold">Total Tasks</span>
                    </div>
                    <div class="fs-3 fw-extrabold text-white mb-1"><?= number_format($metrics['total_tasks']) ?></div>
                    <div class="text-slate-400 extra-small">Across All Projects</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="bc-metric-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bc-metric-badge-amber p-2.5 rounded-3"><i class="fa-solid fa-person-digging fs-5"></i></div>
                        <span class="text-slate-400 extra-small fw-bold">Active In Progress</span>
                    </div>
                    <div class="fs-3 fw-extrabold text-warning mb-1"><?= number_format($metrics['active_tasks']) ?></div>
                    <div class="text-warning extra-small fw-semibold">Under Site Execution</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="bc-metric-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bc-metric-badge-green p-2.5 rounded-3"><i class="fa-solid fa-circle-check fs-5"></i></div>
                        <span class="text-slate-400 extra-small fw-bold">Completed Tasks</span>
                    </div>
                    <div class="fs-3 fw-extrabold text-success mb-1"><?= number_format($metrics['completed_tasks']) ?></div>
                    <div class="text-success extra-small fw-semibold"><i class="fa-solid fa-check me-1"></i>Verified Deliverables</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="bc-metric-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bc-metric-badge-red p-2.5 rounded-3"><i class="fa-solid fa-triangle-exclamation fs-5"></i></div>
                        <span class="text-slate-400 extra-small fw-bold">High / Critical Priority</span>
                    </div>
                    <div class="fs-3 fw-extrabold text-danger mb-1"><?= number_format($metrics['high_priority_tasks']) ?></div>
                    <div class="text-danger extra-small fw-semibold">Requires Priority Focus</div>
                </div>
            </div>
        </div>

        <!-- Filter Controls -->
        <div class="bc-surface-card p-3 mb-4">
            <form action="<?= BASE_URL ?>/client/tasks.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <input type="text" name="q" value="<?= e($search) ?>" class="form-control form-control-sm" placeholder="Search task title or description...">
                </div>

                <div class="col-md-3">
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="0">All My Projects</option>
                        <?php foreach ($client_projects as $cp): ?>
                            <option value="<?= $cp['id'] ?>" <?= $filter_project_id == $cp['id'] ? 'selected' : '' ?>>
                                <?= e($cp['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="all">All Statuses</option>
                        <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="in_progress" <?= $filter_status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="blocked" <?= $filter_status === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                        <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="priority" class="form-select form-select-sm">
                        <option value="all">All Priorities</option>
                        <option value="critical" <?= $filter_priority === 'critical' ? 'selected' : '' ?>>Critical</option>
                        <option value="high" <?= $filter_priority === 'high' ? 'selected' : '' ?>>High</option>
                        <option value="medium" <?= $filter_priority === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="low" <?= $filter_priority === 'low' ? 'selected' : '' ?>>Low</option>
                    </select>
                </div>

                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-outline-blue btn-sm fw-bold">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Tasks Listing Table -->
        <div class="bc-surface-card p-4">
            <?php if (empty($tasks)): ?>
                <div class="text-center py-5 text-muted extra-small">
                    <i class="fa-solid fa-list-check fs-1 mb-3 text-warning"></i>
                    <h3 class="h6 text-dark fw-bold mb-1">No Tasks Found</h3>
                    <p class="text-muted extra-small mb-3">No construction tasks match your search and filter criteria.</p>
                    <button type="button" class="btn btn-orange btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#newTaskModal">
                        <i class="fa-solid fa-plus me-1"></i> Post First Task
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 extra-small">
                        <thead>
                            <tr>
                                <th>Task Title & Milestone</th>
                                <th>Project Name</th>
                                <th>Assigned Specialist</th>
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
                                        <span class="fw-bold text-dark"><?= e($t['title']) ?></span>
                                        <?php if (!empty($t['milestone_title'])): ?>
                                            <div class="extra-small text-muted"><i class="fa-solid fa-flag text-warning me-1"></i><?= e($t['milestone_title']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-info"><?= e($t['project_title']) ?></span>
                                    </td>
                                    <td class="text-muted">
                                        <?php if (!empty($t['worker_name'])): ?>
                                            <i class="fa-solid fa-user text-warning me-1"></i><?= e($t['worker_name']) ?>
                                        <?php else: ?>
                                            <span class="fst-italic">Contractor Team</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= get_priority_badge_class($t['priority']) ?>"><?= e(ucfirst($t['priority'])) ?></span>
                                    </td>
                                    <td>
                                        <span class="<?= $deadline['class'] ?> extra-small">
                                            <i class="fa-solid fa-clock me-1"></i><?= $deadline['label'] ?>
                                        </span>
                                    </td>
                                    <td style="min-width: 120px;">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height: 6px;">
                                                <div class="progress-bar bg-warning" style="width: <?= (int)$t['progress_percent'] ?>%;"></div>
                                            </div>
                                            <span class="fw-bold font-monospace text-dark"><?= (int)$t['progress_percent'] ?>%</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?= get_status_badge_class($t['status']) ?>"><?= e(ucfirst(str_replace('_', ' ', $t['status']))) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-blue extra-small" data-bs-toggle="modal" data-bs-target="#updateTaskModal<?= $t['id'] ?>">
                                            <i class="fa-solid fa-pen-to-square me-1"></i> Update Status
                                        </button>

                                        <!-- Update Task Status Modal -->
                                        <div class="modal fade text-start" id="updateTaskModal<?= $t['id'] ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                                                    <div class="modal-header border-0 bg-dark p-3 px-4">
                                                        <h5 class="modal-title fw-bold text-white fs-6 mb-0">Update Task Status</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <form action="<?= BASE_URL ?>/client/tasks.php" method="POST">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                                                        <div class="modal-body p-4">
                                                            <div class="mb-3">
                                                                <label class="form-label extra-small fw-bold text-muted">TASK TITLE</label>
                                                                <input type="text" class="form-control rounded-3" value="<?= e($t['title']) ?>" disabled>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label extra-small fw-bold text-muted">STATUS</label>
                                                                <select name="status" class="form-select rounded-3" required>
                                                                    <option value="pending" <?= $t['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                                    <option value="in_progress" <?= $t['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                                                    <option value="review" <?= $t['status'] === 'review' ? 'selected' : '' ?>>Under Review</option>
                                                                    <option value="blocked" <?= $t['status'] === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                                                                    <option value="completed" <?= $t['status'] === 'completed' || $t['status'] === 'done' ? 'selected' : '' ?>>Completed</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label extra-small fw-bold text-muted">PROGRESS (%)</label>
                                                                <input type="number" min="0" max="100" name="progress_percent" class="form-control rounded-3" value="<?= (int)$t['progress_percent'] ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer border-top bg-dark p-3 px-4">
                                                            <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-sm btn-orange text-dark fw-bold px-4">Save Changes</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center pt-3 border-top border-secondary mt-3">
                        <span class="text-muted extra-small">Showing Page <?= $page ?> of <?= $total_pages ?> (Total <?= $total_rows ?> Tasks)</span>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= BASE_URL ?>/client/tasks.php?page=<?= $page - 1 ?>&project_id=<?= $filter_project_id ?>&status=<?= urlencode($filter_status) ?>&priority=<?= urlencode($filter_priority) ?>&q=<?= urlencode($search) ?>">Prev</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= BASE_URL ?>/client/tasks.php?page=<?= $i ?>&project_id=<?= $filter_project_id ?>&status=<?= urlencode($filter_status) ?>&priority=<?= urlencode($filter_priority) ?>&q=<?= urlencode($search) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= BASE_URL ?>/client/tasks.php?page=<?= $page + 1 ?>&project_id=<?= $filter_project_id ?>&status=<?= urlencode($filter_status) ?>&priority=<?= urlencode($filter_priority) ?>&q=<?= urlencode($search) ?>">Next</a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Create New Task Modal -->
        <div class="modal fade" id="newTaskModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="modal-header border-0 bg-dark p-3 px-4">
                        <h5 class="modal-title fw-bold text-white fs-6 mb-0">
                            <i class="fa-solid fa-list-check text-warning me-2"></i>Post New Project Task
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="<?= BASE_URL ?>/client/tasks.php" method="POST" data-loading="true">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create">
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label extra-small fw-bold text-muted">SELECT PROJECT</label>
                                <select name="project_id" class="form-select rounded-3" required>
                                    <option value="">-- Choose Project --</option>
                                    <?php foreach ($client_projects as $cp): ?>
                                        <option value="<?= $cp['id'] ?>"><?= e($cp['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label extra-small fw-bold text-muted">TASK TITLE</label>
                                <input type="text" name="title" class="form-control rounded-3" placeholder="e.g. Inspect Foundation Concrete Pouring Quality" required>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label extra-small fw-bold text-muted">PRIORITY LEVEL</label>
                                    <select name="priority" class="form-select rounded-3" required>
                                        <option value="medium">Medium Priority</option>
                                        <option value="high">High Priority</option>
                                        <option value="critical">Critical / Urgent</option>
                                        <option value="low">Low Priority</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label extra-small fw-bold text-muted">DUE DATE</label>
                                    <input type="date" name="due_date" class="form-control rounded-3" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label extra-small fw-bold text-muted">TASK DESCRIPTION & REQUIREMENTS</label>
                                <textarea name="description" rows="3" class="form-control rounded-3" placeholder="Specify requirements, safety guidelines, and deliverables for the site team..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer border-top bg-dark p-3 px-4">
                            <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-orange text-dark fw-bold px-4">Create Task</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
