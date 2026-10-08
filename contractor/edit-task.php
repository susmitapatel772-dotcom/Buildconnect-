<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('contractor');

$page_title = "Edit Task - Contractor - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$contractor_id = (int)$user['id'];
$task_id = (int)($_GET['id'] ?? 0);

$error = '';
$flash = get_flash_message();

// Verify task belongs to contractor's project
$stmt = $db->prepare("
    SELECT t.*, p.contractor_id 
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    WHERE t.id = ? AND p.contractor_id = ?
");
$stmt->execute([$task_id, $contractor_id]);
$task = $stmt->fetch();

if (!$task) {
    ?>
    <div class="bc-layout">
        <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="bc-main-content">
            <div class="bc-card p-5 text-center my-5 border-danger">
                <i class="fa-solid fa-lock text-danger fs-1 mb-3"></i>
                <h2 class="h4 text-white fw-bold">Access Denied</h2>
                <p class="text-muted small mb-4">You do not have permission to view or edit this task.</p>
                <a href="<?= BASE_URL ?>/contractor/tasks.php" class="btn btn-amber btn-sm fw-bold">
                    <i class="fa-solid fa-arrow-left me-1"></i> Return to Tasks
                </a>
            </div>
        </main>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Fetch contractor projects
$p_stmt = $db->prepare("SELECT id, title FROM projects WHERE contractor_id = ? ORDER BY id DESC");
$p_stmt->execute([$contractor_id]);
$projects = $p_stmt->fetchAll();

// Fetch milestones for task's project
$ms_stmt = $db->prepare("SELECT id, title FROM milestones WHERE project_id = ? ORDER BY id ASC");
$ms_stmt->execute([$task['project_id']]);
$project_milestones = $ms_stmt->fetchAll();

// Fetch active workers in task's project
$w_stmt = $db->prepare("
    SELECT u.id, u.name, w.trade_title
    FROM project_members pm
    JOIN users u ON pm.user_id = u.id
    LEFT JOIN workers w ON u.id = w.user_id
    WHERE pm.project_id = ? AND pm.status = 'active' AND u.role = 'worker'
    ORDER BY u.name ASC
");
$w_stmt->execute([$task['project_id']]);
$project_workers = $w_stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security validation failed (CSRF token invalid).';
    } else {
        $title = sanitize($_POST['title'] ?? '');
        $milestone_id = (int)($_POST['milestone_id'] ?? 0);
        $assigned_to_worker_id = (int)($_POST['assigned_to_worker_id'] ?? 0);
        $description = sanitize($_POST['description'] ?? '');
        $priority = sanitize($_POST['priority'] ?? 'medium');
        $status = sanitize($_POST['status'] ?? 'pending');
        $progress_percent = max(0, min(100, (int)($_POST['progress_percent'] ?? 0)));
        $start_date = sanitize($_POST['start_date'] ?? '');
        $due_date = sanitize($_POST['due_date'] ?? '');

        if (empty($title)) {
            $error = 'Task title is required.';
        } elseif (!in_array($priority, ['low', 'medium', 'high', 'urgent', 'critical'])) {
            $error = 'Invalid priority selected.';
        } elseif (!in_array($status, ['pending', 'todo', 'in_progress', 'review', 'blocked', 'done', 'completed', 'cancelled'])) {
            $error = 'Invalid status selected.';
        } elseif (!empty($start_date) && !empty($due_date) && strtotime($due_date) < strtotime($start_date)) {
            $error = 'Due date cannot be prior to start date.';
        } else {
            // Verify assigned worker is active member of project
            if ($assigned_to_worker_id > 0) {
                $wv_stmt = $db->prepare("SELECT id FROM project_members WHERE project_id = ? AND user_id = ? AND status = 'active'");
                $wv_stmt->execute([$task['project_id'], $assigned_to_worker_id]);
                if (!$wv_stmt->fetch()) {
                    $error = 'Task Assignment Failed: Selected worker is not an active member of this project.';
                }
            }

            if (empty($error)) {
                $completed_at = (in_array($status, ['completed', 'done']) || $progress_percent == 100) ? date('Y-m-d H:i:s') : null;

                $up_stmt = $db->prepare("
                    UPDATE tasks SET 
                        milestone_id = ?, title = ?, description = ?, assigned_to_worker_id = ?, 
                        priority = ?, status = ?, progress_percent = ?, start_date = ?, 
                        due_date = ?, completed_at = ?, updated_at = NOW()
                    WHERE id = ?
                ");

                $success = $up_stmt->execute([
                    $milestone_id > 0 ? $milestone_id : null,
                    $title,
                    $description,
                    $assigned_to_worker_id > 0 ? $assigned_to_worker_id : null,
                    $priority,
                    $status,
                    $progress_percent,
                    $start_date ?: null,
                    $due_date ?: null,
                    $completed_at,
                    $task_id
                ]);

                if ($success) {
                    if ($milestone_id > 0) {
                        calculateMilestoneProgress($milestone_id);
                    }
                    calculateProjectProgress($task['project_id']);

                    if ($assigned_to_worker_id > 0 && $assigned_to_worker_id != $task['assigned_to_worker_id']) {
                        create_notification(
                            $assigned_to_worker_id,
                            'Task Assigned to You',
                            "You have been assigned task '{$title}'.",
                            'info',
                            'worker/task-details.php?id=' . $task_id
                        );
                    }

                    log_activity($contractor_id, 'Task Updated', "Updated task #{$task_id} ('{$title}')", 'task', $task_id);
                    set_flash_message("Task '{$title}' updated successfully.", 'success');
                    redirect("contractor/task-details.php?id={$task_id}");
                } else {
                    $error = 'Failed to update task due to a database error.';
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
                    <i class="fa-solid fa-pen-to-square text-warning me-2"></i>Edit Task #<?= $task_id ?>
                </h1>
                <p class="text-muted small mb-0">Modify task instructions, worker assignment, priority level, or deadline.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/contractor/task-details.php?id=<?= $task_id ?>" class="btn btn-outline-light btn-sm fw-bold">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Details
                </a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 px-3 small rounded-3 mb-4">
                <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= sanitize($error) ?>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>/contractor/edit-task.php?id=<?= $task_id ?>" method="POST" class="bc-card p-4">
            <?= csrf_field() ?>

            <div class="row g-3 mb-4">
                <div class="col-md-8">
                    <label class="form-label text-light fw-bold small mb-1">Task Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" value="<?= e($task['title']) ?>" class="form-control bg-dark border-secondary text-light" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label text-light fw-bold small mb-1">Assign to Worker</label>
                    <select name="assigned_to_worker_id" class="form-select bg-dark border-secondary text-light">
                        <option value="0">-- Unassigned --</option>
                        <?php foreach ($project_workers as $w): ?>
                            <option value="<?= $w['id'] ?>" <?= $task['assigned_to_worker_id'] == $w['id'] ? 'selected' : '' ?>>
                                <?= e($w['name']) ?> <?= $w['trade_title'] ? "({$w['trade_title']})" : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label text-light fw-bold small mb-1">Milestone Phase</label>
                    <select name="milestone_id" class="form-select bg-dark border-secondary text-light">
                        <option value="0">-- General Task --</option>
                        <?php foreach ($project_milestones as $ms): ?>
                            <option value="<?= $ms['id'] ?>" <?= $task['milestone_id'] == $ms['id'] ? 'selected' : '' ?>>
                                <?= e($ms['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label text-light fw-bold small mb-1">Priority</label>
                    <select name="priority" class="form-select bg-dark border-secondary text-light">
                        <option value="low" <?= $task['priority'] === 'low' ? 'selected' : '' ?>>Low</option>
                        <option value="medium" <?= $task['priority'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="high" <?= $task['priority'] === 'high' ? 'selected' : '' ?>>High</option>
                        <option value="critical" <?= in_array($task['priority'], ['critical', 'urgent']) ? 'selected' : '' ?>>Critical / Urgent</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label text-light fw-bold small mb-1">Status</label>
                    <select name="status" class="form-select bg-dark border-secondary text-light">
                        <option value="pending" <?= $task['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="in_progress" <?= $task['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="blocked" <?= $task['status'] === 'blocked' ? 'selected' : '' ?>>Blocked</option>
                        <option value="completed" <?= in_array($task['status'], ['completed', 'done']) ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= $task['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label text-light fw-bold small mb-1">Progress % (0-100)</label>
                    <input type="number" min="0" max="100" name="progress_percent" value="<?= e($task['progress_percent']) ?>" class="form-control bg-dark border-secondary text-light">
                </div>

                <div class="col-md-4">
                    <label class="form-label text-light fw-bold small mb-1">Start Date</label>
                    <input type="date" name="start_date" value="<?= e($task['start_date']) ?>" class="form-control bg-dark border-secondary text-light">
                </div>

                <div class="col-md-4">
                    <label class="form-label text-light fw-bold small mb-1">Due Date</label>
                    <input type="date" name="due_date" value="<?= e($task['due_date']) ?>" class="form-control bg-dark border-secondary text-light">
                </div>

                <div class="col-12">
                    <label class="form-label text-light fw-bold small mb-1">Task Instructions & Description</label>
                    <textarea name="description" rows="4" class="form-control bg-dark border-secondary text-light"><?= e($task['description']) ?></textarea>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top border-secondary">
                <a href="<?= BASE_URL ?>/contractor/task-details.php?id=<?= $task_id ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-warning fw-bold px-4">
                    <i class="fa-solid fa-save me-1"></i> Update Task
                </button>
            </div>
        </form>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
