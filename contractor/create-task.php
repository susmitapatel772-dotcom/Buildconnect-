<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('contractor');

$page_title = "Assign New Task - Contractor - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$contractor_id = (int)$user['id'];

$error = '';
$flash = get_flash_message();

// Fetch contractor projects
$p_stmt = $db->prepare("SELECT id, title FROM projects WHERE contractor_id = ? ORDER BY id DESC");
$p_stmt->execute([$contractor_id]);
$projects = $p_stmt->fetchAll();

// Pre-selected project ID
$selected_project_id = (int)($_GET['project_id'] ?? $_POST['project_id'] ?? 0);
$selected_worker_id = (int)($_GET['worker_id'] ?? $_POST['assigned_to_worker_id'] ?? 0);

// Form defaults
$title = '';
$project_id = $selected_project_id;
$milestone_id = 0;
$assigned_to_worker_id = $selected_worker_id;
$description = '';
$priority = 'medium';
$status = 'pending';
$progress_percent = 0;
$start_date = date('Y-m-d');
$due_date = '';

// Dynamically fetch milestones & active workers for selected project
$project_milestones = [];
$project_workers = [];

if ($project_id > 0) {
    // Milestones
    $ms_stmt = $db->prepare("SELECT id, title FROM milestones WHERE project_id = ? ORDER BY id ASC");
    $ms_stmt->execute([$project_id]);
    $project_milestones = $ms_stmt->fetchAll();

    // Active workers in project_members
    $w_stmt = $db->prepare("
        SELECT u.id, u.name, w.trade_title
        FROM project_members pm
        JOIN users u ON pm.user_id = u.id
        LEFT JOIN workers w ON u.id = w.user_id
        WHERE pm.project_id = ? AND pm.status = 'active' AND u.role = 'worker'
        ORDER BY u.name ASC
    ");
    $w_stmt->execute([$project_id]);
    $project_workers = $w_stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security validation failed (CSRF token invalid).';
    } else {
        $title = sanitize($_POST['title'] ?? '');
        $project_id = (int)($_POST['project_id'] ?? 0);
        $milestone_id = (int)($_POST['milestone_id'] ?? 0);
        $assigned_to_worker_id = (int)($_POST['assigned_to_worker_id'] ?? 0);
        $description = sanitize($_POST['description'] ?? '');
        $priority = sanitize($_POST['priority'] ?? 'medium');
        $status = sanitize($_POST['status'] ?? 'pending');
        $progress_percent = max(0, min(100, (int)($_POST['progress_percent'] ?? 0)));
        $start_date = sanitize($_POST['start_date'] ?? '');
        $due_date = sanitize($_POST['due_date'] ?? '');

        // Validations
        if (empty($title)) {
            $error = 'Task title is required.';
        } elseif ($project_id <= 0) {
            $error = 'Please select a valid project for this task.';
        } elseif (!in_array($priority, ['low', 'medium', 'high', 'urgent', 'critical'])) {
            $error = 'Invalid priority selected.';
        } elseif (!in_array($status, ['pending', 'todo', 'in_progress', 'review', 'blocked', 'done', 'completed', 'cancelled'])) {
            $error = 'Invalid status selected.';
        } elseif (!empty($start_date) && !empty($due_date) && strtotime($due_date) < strtotime($start_date)) {
            $error = 'Due date cannot be prior to start date.';
        } else {
            // 1. Verify contractor owns project
            $pv_stmt = $db->prepare("SELECT id FROM projects WHERE id = ? AND contractor_id = ?");
            $pv_stmt->execute([$project_id, $contractor_id]);
            if (!$pv_stmt->fetch()) {
                $error = 'Access Denied: The selected project does not belong to your contractor account.';
            } else {
                // 2. Verify milestone belongs to project if supplied
                if ($milestone_id > 0) {
                    $mv_stmt = $db->prepare("SELECT id FROM milestones WHERE id = ? AND project_id = ?");
                    $mv_stmt->execute([$milestone_id, $project_id]);
                    if (!$mv_stmt->fetch()) {
                        $error = 'The selected milestone does not belong to this project.';
                    }
                }

                // 3. Verify assigned worker is an active member of project if supplied
                if (empty($error) && $assigned_to_worker_id > 0) {
                    $wv_stmt = $db->prepare("SELECT id FROM project_members WHERE project_id = ? AND user_id = ? AND status = 'active'");
                    $wv_stmt->execute([$project_id, $assigned_to_worker_id]);
                    if (!$wv_stmt->fetch()) {
                        $error = 'Task Assignment Failed: The selected worker is not an active member of this project.';
                    }
                }

                if (empty($error)) {
                    $completed_at = (in_array($status, ['completed', 'done']) || $progress_percent == 100) ? date('Y-m-d H:i:s') : null;

                    $ins_stmt = $db->prepare("
                        INSERT INTO tasks (
                            project_id, milestone_id, title, description, assigned_to_worker_id, 
                            priority, status, progress_percent, start_date, due_date, completed_at, created_at, updated_at
                        ) VALUES (
                            ?, ?, ?, ?, ?, 
                            ?, ?, ?, ?, ?, ?, NOW(), NOW()
                        )
                    ");

                    $success = $ins_stmt->execute([
                        $project_id,
                        $milestone_id > 0 ? $milestone_id : null,
                        $title,
                        $description,
                        $assigned_to_worker_id > 0 ? $assigned_to_worker_id : null,
                        $priority,
                        $status,
                        $progress_percent,
                        $start_date ?: null,
                        $due_date ?: null,
                        $completed_at
                    ]);

                    if ($success) {
                        $task_id = $db->lastInsertId();

                        // Recalculate progress
                        if ($milestone_id > 0) {
                            calculateMilestoneProgress($milestone_id);
                        }
                        calculateProjectProgress($project_id);

                        // Notify worker if assigned
                        if ($assigned_to_worker_id > 0) {
                            create_notification(
                                $assigned_to_worker_id,
                                'New Task Assigned',
                                "You have been assigned task '{$title}' on your project.",
                                'info',
                                'worker/task-details.php?id=' . $task_id
                            );
                        }

                        log_activity($contractor_id, 'Task Created', "Created task #{$task_id} ('{$title}') for project #{$project_id}", 'task', $task_id);
                        set_flash_message("Task '{$title}' assigned successfully.", 'success');
                        redirect("contractor/tasks.php?project_id={$project_id}");
                    } else {
                        $error = 'Failed to create task due to a database error.';
                    }
                }
            }
        }
    }
}
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-plus-circle text-amber me-2"></i>Assign New Workforce Task
                </h1>
                <p class="text-muted small mb-0">Assign site tasks to active project workers and establish milestone deadlines.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/contractor/tasks.php" class="btn btn-outline-light btn-sm fw-bold">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Tasks
                </a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 px-3 small rounded-3 mb-4">
                <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= sanitize($error) ?>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>/contractor/create-task.php" method="POST" class="bc-card p-4">
            <?= csrf_field() ?>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label text-light fw-bold small mb-1">Target Project <span class="text-danger">*</span></label>
                    <select name="project_id" class="form-select bg-dark border-secondary text-light" onchange="window.location.href='<?= BASE_URL ?>/contractor/create-task.php?project_id=' + this.value" required>
                        <option value="">-- Select Project --</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $project_id == $p['id'] ? 'selected' : '' ?>>
                                <?= e($p['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label text-light fw-bold small mb-1">Assign to Worker (Active Project Members Only)</label>
                    <select name="assigned_to_worker_id" class="form-select bg-dark border-secondary text-light">
                        <option value="0">-- Unassigned / Pool Task --</option>
                        <?php foreach ($project_workers as $w): ?>
                            <option value="<?= $w['id'] ?>" <?= $assigned_to_worker_id == $w['id'] ? 'selected' : '' ?>>
                                <?= e($w['name']) ?> <?= $w['trade_title'] ? "({$w['trade_title']})" : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="extra-small text-muted mt-1">Only workers with active project membership can be assigned tasks.</div>
                </div>

                <div class="col-md-8">
                    <label class="form-label text-light fw-bold small mb-1">Task Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" value="<?= e($title) ?>" class="form-control bg-dark border-secondary text-light" placeholder="e.g. Structural steel beam welding & jointing" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label text-light fw-bold small mb-1">Associated Milestone Phase</label>
                    <select name="milestone_id" class="form-select bg-dark border-secondary text-light">
                        <option value="0">-- General Project Task --</option>
                        <?php foreach ($project_milestones as $ms): ?>
                            <option value="<?= $ms['id'] ?>" <?= $milestone_id == $ms['id'] ? 'selected' : '' ?>>
                                <?= e($ms['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label text-light fw-bold small mb-1">Priority Level</label>
                    <select name="priority" class="form-select bg-dark border-secondary text-light">
                        <option value="low" <?= $priority === 'low' ? 'selected' : '' ?>>Low</option>
                        <option value="medium" <?= $priority === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="high" <?= $priority === 'high' ? 'selected' : '' ?>>High</option>
                        <option value="critical" <?= in_array($priority, ['critical', 'urgent']) ? 'selected' : '' ?>>Critical / Urgent</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label text-light fw-bold small mb-1">Initial Status</label>
                    <select name="status" class="form-select bg-dark border-secondary text-light">
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="blocked" <?= $status === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label text-light fw-bold small mb-1">Initial Progress % (0-100)</label>
                    <input type="number" min="0" max="100" name="progress_percent" value="<?= e($progress_percent) ?>" class="form-control bg-dark border-secondary text-light">
                </div>

                <div class="col-md-6">
                    <label class="form-label text-light fw-bold small mb-1">Start Date</label>
                    <input type="date" name="start_date" value="<?= e($start_date) ?>" class="form-control bg-dark border-secondary text-light">
                </div>

                <div class="col-md-6">
                    <label class="form-label text-light fw-bold small mb-1">Due Date / Deadline</label>
                    <input type="date" name="due_date" value="<?= e($due_date) ?>" class="form-control bg-dark border-secondary text-light">
                </div>

                <div class="col-12">
                    <label class="form-label text-light fw-bold small mb-1">Task Instructions & Description</label>
                    <textarea name="description" rows="4" class="form-control bg-dark border-secondary text-light" placeholder="Detail safety guidelines, equipment tools required, and exact site location..."><?= e($description) ?></textarea>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top border-secondary">
                <a href="<?= BASE_URL ?>/contractor/tasks.php" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-amber fw-bold px-4">
                    <i class="fa-solid fa-check me-1"></i> Create & Assign Task
                </button>
            </div>
        </form>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
