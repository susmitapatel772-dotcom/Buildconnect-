<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('contractor');

$page_title = "Task Details - Contractor - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$contractor_id = (int)$user['id'];
$task_id = (int)($_GET['id'] ?? 0);

$flash = get_flash_message();

// Fetch task with strict contractor ownership verification
$stmt = $db->prepare("
    SELECT t.*, p.title as project_title, p.location as project_location,
           m.title as milestone_title,
           u.name as worker_name, u.email as worker_email, u.phone as worker_phone,
           w.trade_title
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    LEFT JOIN milestones m ON t.milestone_id = m.id
    LEFT JOIN users u ON t.assigned_to_worker_id = u.id
    LEFT JOIN workers w ON u.id = w.user_id
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
                <p class="text-muted small mb-4">The requested task does not exist or belongs to another contractor.</p>
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
                    <span class="badge <?= get_priority_badge_class($task['priority']) ?> fs-6"><?= e(ucfirst($task['priority'])) ?> Priority</span>
                </div>
                <p class="text-muted small mb-0">
                    Project: <a href="<?= BASE_URL ?>/contractor/project-details.php?id=<?= $task['project_id'] ?>" class="text-info text-decoration-none fw-semibold"><?= e($task['project_title']) ?></a>
                    <?php if ($task['milestone_title']): ?>
                        • Milestone: <span class="text-warning"><?= e($task['milestone_title']) ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= BASE_URL ?>/contractor/tasks.php?project_id=<?= $task['project_id'] ?>" class="btn btn-outline-light btn-sm fw-bold">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Tasks
                </a>
                <a href="<?= BASE_URL ?>/contractor/edit-task.php?id=<?= $task_id ?>" class="btn btn-warning btn-sm fw-bold">
                    <i class="fa-solid fa-pen me-1"></i> Edit Task
                </a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show py-2 px-3 small mb-3">
                <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <!-- Left Column: Task Description & Progress -->
            <div class="col-lg-8">
                <div class="bc-card p-4 h-100">
                    <h2 class="h5 text-white fw-bold mb-3">
                        <i class="fa-solid fa-align-left text-warning me-2"></i>Task Description & Instructions
                    </h2>
                    <div class="text-light lead-sm mb-4" style="white-space: pre-line;">
                        <?= e($task['description'] ?: 'No detailed instructions provided.') ?>
                    </div>

                    <div class="p-3 bg-dark rounded-3 border border-secondary mb-4">
                        <div class="d-flex justify-content-between text-muted extra-small mb-1">
                            <span class="fw-bold text-light">Task Completion Progress</span>
                            <span class="text-warning font-monospace fw-bold fs-6"><?= (int)$task['progress_percent'] ?>%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-amber" style="width: <?= (int)$task['progress_percent'] ?>%"></div>
                        </div>
                    </div>

                    <div class="row g-3 text-muted extra-small border-top border-secondary pt-3">
                        <div class="col-sm-6">
                            <span class="fw-bold text-light">Start Date:</span>
                            <span class="text-light ms-1"><?= format_date($task['start_date']) ?></span>
                        </div>
                        <div class="col-sm-6">
                            <span class="fw-bold text-light">Deadline Status:</span>
                            <span class="<?= $deadline['class'] ?> ms-1"><i class="fa-solid fa-clock me-1"></i><?= $deadline['label'] ?></span>
                        </div>
                        <div class="col-sm-6">
                            <span class="fw-bold text-light">Created At:</span>
                            <span class="text-light ms-1"><?= format_datetime($task['created_at']) ?></span>
                        </div>
                        <div class="col-sm-6">
                            <span class="fw-bold text-light">Completed At:</span>
                            <span class="text-light ms-1"><?= $task['completed_at'] ? format_datetime($task['completed_at']) : 'Not yet completed' ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Assigned Worker Info -->
            <div class="col-lg-4">
                <div class="bc-card p-4 mb-4">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-user-gear text-info me-2"></i>Assigned Worker</h3>

                    <?php if ($task['worker_name']): ?>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-circle bg-secondary text-white fw-bold d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; font-size: 1.1rem;">
                                <?= strtoupper(substr($task['worker_name'], 0, 2)) ?>
                            </div>
                            <div>
                                <h4 class="h6 text-white fw-bold mb-0"><?= e($task['worker_name']) ?></h4>
                                <div class="text-warning extra-small"><?= e($task['trade_title'] ?: 'Construction Specialist') ?></div>
                                <div class="text-muted extra-small"><?= e($task['worker_phone'] ?: $task['worker_email']) ?></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-dark text-center py-3 extra-small text-muted mb-3">
                            <i class="fa-solid fa-user-slash me-1"></i> Task is currently unassigned.
                        </div>
                    <?php endif; ?>

                    <div class="d-grid mt-3 mb-3">
                        <a href="<?= BASE_URL ?>/contractor/edit-task.php?id=<?= $task_id ?>" class="btn btn-amber btn-sm fw-bold">
                            <i class="fa-solid fa-user-pen me-1"></i> Reassign or Edit Task
                        </a>
                    </div>
                </div>

                <!-- AI Task Risk Analysis Widget -->
                <div class="bc-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h6 text-white fw-bold mb-0"><i class="fa-solid fa-brain text-warning me-2"></i>AI Risk Analysis</h3>
                        <button type="button" class="btn btn-outline-warning btn-sm extra-small" onclick="analyzeTaskRisk(<?= $task_id ?>)">
                            <i class="fa-solid fa-magnifying-glass-chart me-1"></i> Assess Risk
                        </button>
                    </div>
                    <div id="ai_task_risk_box" class="small text-muted">
                        Click "Assess Risk" to evaluate deadline and priority status.
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function analyzeTaskRisk(taskId) {
    const box = document.getElementById('ai_task_risk_box');
    box.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Analyzing task completion risk...';

    fetch('<?= BASE_URL ?>/api/ai.php?action=task_risk&task_id=' + taskId)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.risk) {
                const r = data.risk;
                let badge = 'bg-success';
                if (r.risk_level === 'medium') badge = 'bg-warning text-dark';
                if (r.risk_level === 'high') badge = 'bg-danger';

                box.innerHTML = `
                    <div class="mb-2">
                        <span class="badge ${badge} text-uppercase font-monospace me-2">${r.risk_level} RISK</span>
                    </div>
                    <div class="text-light mb-2"><strong>Reason:</strong> ${r.explanation}</div>
                    <div class="text-warning extra-small"><strong>Action:</strong> ${r.recommended_action}</div>
                `;
            } else {
                box.innerHTML = '<span class="text-danger">Unable to analyze risk at this time.</span>';
            }
        })
        .catch(err => {
            box.innerHTML = '<span class="text-danger">AI request timed out.</span>';
        });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
