<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('worker');

$page_title = "Update Task Progress - Worker - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$worker_user_id = (int)$user['id'];
$task_id = (int)($_GET['id'] ?? 0);

$error = '';
$flash = get_flash_message();

// Verify task belongs strictly to logged-in worker
$stmt = $db->prepare("
    SELECT t.*, p.title as project_title, p.location as project_location, p.contractor_id,
           m.title as milestone_title,
           c.company_name as contractor_company, u_c.name as contractor_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    JOIN users u_c ON p.contractor_id = u_c.id
    LEFT JOIN contractors c ON u_c.id = c.user_id
    LEFT JOIN milestones m ON t.milestone_id = m.id
    WHERE t.id = ? AND t.assigned_to_worker_id = ?
");
$stmt->execute([$task_id, $worker_user_id]);
$task = $stmt->fetch();

if (!$task) {
    ?>
    <div class="bc-layout">
        <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="bc-main-content">
            <div class="bc-card p-5 text-center my-5 border-danger">
                <i class="fa-solid fa-lock text-danger fs-1 mb-3"></i>
                <h2 class="h4 text-white fw-bold">Access Denied</h2>
                <p class="text-muted small mb-4">The requested task does not exist or is not assigned to your account.</p>
                <a href="<?= BASE_URL ?>/worker/tasks.php" class="btn btn-amber btn-sm fw-bold">
                    <i class="fa-solid fa-arrow-left me-1"></i> Return to My Tasks
                </a>
            </div>
        </main>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Handle Worker Status & Progress Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security validation failed (CSRF token invalid).';
    } else {
        $new_status = sanitize($_POST['status'] ?? $task['status']);
        $new_progress = max(0, min(100, (int)($_POST['progress_percent'] ?? $task['progress_percent'])));
        $worker_notes = sanitize($_POST['worker_notes'] ?? '');

        if (!in_array($new_status, ['pending', 'todo', 'in_progress', 'review', 'blocked', 'done', 'completed'])) {
            $error = 'Invalid task status selected.';
        } else {
            $completed_at = (in_array($new_status, ['completed', 'done']) || $new_progress == 100) ? date('Y-m-d H:i:s') : null;

            $up_stmt = $db->prepare("
                UPDATE tasks SET 
                    status = ?, progress_percent = ?, completed_at = ?, updated_at = NOW()
                WHERE id = ? AND assigned_to_worker_id = ?
            ");
            $success = $up_stmt->execute([
                $new_status,
                $new_progress,
                $completed_at,
                $task_id,
                $worker_user_id
            ]);

            if ($success) {
                // Synchronize progress
                if ($task['milestone_id'] > 0) {
                    calculateMilestoneProgress($task['milestone_id']);
                }
                calculateProjectProgress($task['project_id']);

                // Notify contractor
                create_notification(
                    $task['contractor_id'],
                    'Worker Task Progress Update',
                    "Worker '{$user['name']}' updated task '{$task['title']}' to status '" . ucfirst($new_status) . "' ({$new_progress}% progress).",
                    $new_status === 'blocked' ? 'warning' : 'info',
                    "contractor/task-details.php?id={$task_id}"
                );

                // Activity log
                log_activity(
                    $worker_user_id,
                    'Worker Task Updated',
                    "Updated task #{$task_id} ('{$task['title']}') to {$new_status} ({$new_progress}%)",
                    'task',
                    $task_id
                );

                set_flash_message("Task progress updated successfully to {$new_progress}%.", 'success');
                redirect("worker/task-details.php?id={$task_id}");
            } else {
                $error = 'Failed to update task progress.';
            }
        }
    }
}

$deadline = get_deadline_status($task['due_date'], $task['status']);
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h1 class="h2 fw-bold text-white mb-0"><?= e($task['title']) ?></h1>
                    <span class="badge <?= get_status_badge_class($task['status']) ?> fs-6"><?= e(ucfirst(str_replace('_', ' ', $task['status']))) ?></span>
                    <span class="badge <?= get_priority_badge_class($task['priority']) ?> fs-6"><?= e(ucfirst($task['priority'])) ?></span>
                </div>
                <p class="text-muted small mb-0">
                    Project: <strong class="text-info"><?= e($task['project_title']) ?></strong>
                    • Contractor: <span class="text-light"><?= e($task['contractor_company'] ?: $task['contractor_name']) ?></span>
                </p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/worker/tasks.php" class="btn btn-outline-light btn-sm fw-bold">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to My Tasks
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
            <div class="alert alert-danger py-2 px-3 small rounded-3 mb-4">
                <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= sanitize($error) ?>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <!-- Left Column: Task Overview & Instructions -->
            <div class="col-lg-8">
                <div class="bc-card p-4 h-100">
                    <h2 class="h5 text-white fw-bold mb-3">
                        <i class="fa-solid fa-clipboard-list text-warning me-2"></i>Task Specifications & Instructions
                    </h2>
                    <div class="text-light lead-sm mb-4" style="white-space: pre-line;">
                        <?= e($task['description'] ?: 'No detailed instructions provided.') ?>
                    </div>

                    <div class="row g-3 text-muted extra-small border-top border-secondary pt-3">
                        <div class="col-sm-6">
                            <span class="fw-bold text-light">Milestone Phase:</span>
                            <span class="text-warning ms-1"><?= e($task['milestone_title'] ?: 'General Site Task') ?></span>
                        </div>
                        <div class="col-sm-6">
                            <span class="fw-bold text-light">Site Location:</span>
                            <span class="text-light ms-1"><i class="fa-solid fa-location-dot text-danger me-1"></i><?= e($task['project_location']) ?></span>
                        </div>
                        <div class="col-sm-6">
                            <span class="fw-bold text-light">Start Date:</span>
                            <span class="text-light ms-1"><?= format_date($task['start_date']) ?></span>
                        </div>
                        <div class="col-sm-6">
                            <span class="fw-bold text-light">Deadline:</span>
                            <span class="<?= $deadline['class'] ?> ms-1"><i class="fa-solid fa-clock me-1"></i><?= $deadline['label'] ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Update Progress Form -->
            <div class="col-lg-4">
                <form action="<?= BASE_URL ?>/worker/task-details.php?id=<?= $task_id ?>" method="POST" class="bc-card p-4 border-amber">
                    <?= csrf_field() ?>

                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-sliders text-amber me-2"></i>Update Task Status</h3>

                    <div class="mb-3">
                        <label class="form-label text-light fw-bold extra-small mb-1">Completion Progress % (0-100)</label>
                        <input type="number" min="0" max="100" name="progress_percent" value="<?= e($task['progress_percent']) ?>" class="form-control bg-dark border-secondary text-light font-monospace fw-bold" required>
                        <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar bg-amber" style="width: <?= (int)$task['progress_percent'] ?>%"></div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-light fw-bold extra-small mb-1">Current Task Status</label>
                        <select name="status" class="form-select bg-dark border-secondary text-light">
                            <option value="pending" <?= $task['status'] === 'pending' ? 'selected' : '' ?>>Pending Start</option>
                            <option value="in_progress" <?= $task['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="blocked" <?= $task['status'] === 'blocked' ? 'selected' : '' ?>>Blocked (Report Issue)</option>
                            <option value="completed" <?= in_array($task['status'], ['completed', 'done']) ? 'selected' : '' ?>>Mark Completed (100%)</option>
                        </select>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-amber fw-bold py-2">
                            <i class="fa-solid fa-check me-1"></i> Save Task Progress
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
