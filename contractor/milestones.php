<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('contractor');

$page_title = "Manage Milestones - Contractor - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$contractor_id = (int)$user['id'];

$error = '';
$flash = get_flash_message();

// Filter by Project ID
$filter_project_id = (int)($_GET['project_id'] ?? 0);

// Fetch contractor projects for dropdown
$p_stmt = $db->prepare("SELECT id, title FROM projects WHERE contractor_id = ? ORDER BY id DESC");
$p_stmt->execute([$contractor_id]);
$contractor_projects = $p_stmt->fetchAll();

// Handle Milestone Actions (Create / Edit / Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security validation failed (CSRF token invalid).';
    } else {
        $action = sanitize($_POST['action'] ?? '');
        $ms_id = (int)($_POST['milestone_id'] ?? 0);

        if ($action === 'create') {
            $project_id = (int)($_POST['project_id'] ?? 0);
            $title = sanitize($_POST['title'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $target_date = sanitize($_POST['target_date'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            $status = sanitize($_POST['status'] ?? 'pending');

            if (empty($title)) {
                $error = 'Milestone title is required.';
            } elseif ($project_id <= 0) {
                $error = 'Please select a valid project.';
            } else {
                // Verify contractor owns project
                $pv_stmt = $db->prepare("SELECT id FROM projects WHERE id = ? AND contractor_id = ?");
                $pv_stmt->execute([$project_id, $contractor_id]);
                if (!$pv_stmt->fetch()) {
                    $error = 'Access Denied: Project does not belong to your contractor account.';
                } else {
                    $ins_stmt = $db->prepare("
                        INSERT INTO milestones (project_id, title, description, target_date, amount, progress_percent, status, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, 0, ?, NOW(), NOW())
                    ");
                    $ins_stmt->execute([
                        $project_id,
                        $title,
                        $description,
                        $target_date ?: null,
                        $amount,
                        $status
                    ]);

                    $new_ms_id = $db->lastInsertId();
                    calculateProjectProgress($project_id);

                    log_activity($contractor_id, 'Milestone Created', "Created milestone '{$title}' for project #{$project_id}", 'milestone', $new_ms_id);
                    set_flash_message("Milestone '{$title}' created successfully.", 'success');
                    redirect("contractor/milestones.php?project_id={$project_id}");
                }
            }

        } elseif ($action === 'edit') {
            $title = sanitize($_POST['title'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $target_date = sanitize($_POST['target_date'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            $progress_percent = (int)($_POST['progress_percent'] ?? 0);
            $status = sanitize($_POST['status'] ?? 'pending');

            // Verify contractor owns milestone's project
            $chk_stmt = $db->prepare("
                SELECT m.id, m.project_id 
                FROM milestones m 
                JOIN projects p ON m.project_id = p.id 
                WHERE m.id = ? AND p.contractor_id = ?
            ");
            $chk_stmt->execute([$ms_id, $contractor_id]);
            $target_ms = $chk_stmt->fetch();

            if (!$target_ms) {
                $error = 'Access Denied: Milestone not found or ownership mismatch.';
            } elseif (empty($title)) {
                $error = 'Milestone title is required.';
            } else {
                $completed_at = ($status === 'completed' || $progress_percent == 100) ? date('Y-m-d H:i:s') : null;

                $up_stmt = $db->prepare("
                    UPDATE milestones SET 
                        title = ?, description = ?, target_date = ?, amount = ?, 
                        progress_percent = ?, status = ?, completed_at = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $up_stmt->execute([
                    $title,
                    $description,
                    $target_date ?: null,
                    $amount,
                    $progress_percent,
                    $status,
                    $completed_at,
                    $ms_id
                ]);

                calculateMilestoneProgress($ms_id);
                calculateProjectProgress($target_ms['project_id']);

                log_activity($contractor_id, 'Milestone Updated', "Updated milestone #{$ms_id} ('{$title}')", 'milestone', $ms_id);
                set_flash_message("Milestone '{$title}' updated successfully.", 'success');
                redirect("contractor/milestones.php?project_id={$target_ms['project_id']}");
            }

        } elseif ($action === 'delete') {
            // Verify ownership
            $chk_stmt = $db->prepare("
                SELECT m.id, m.project_id, m.title 
                FROM milestones m 
                JOIN projects p ON m.project_id = p.id 
                WHERE m.id = ? AND p.contractor_id = ?
            ");
            $chk_stmt->execute([$ms_id, $contractor_id]);
            $target_ms = $chk_stmt->fetch();

            if (!$target_ms) {
                $error = 'Access Denied: Milestone not found or ownership mismatch.';
            } else {
                // Unlink tasks before deletion
                $unlink_stmt = $db->prepare("UPDATE tasks SET milestone_id = NULL WHERE milestone_id = ?");
                $unlink_stmt->execute([$ms_id]);

                $del_stmt = $db->prepare("DELETE FROM milestones WHERE id = ?");
                $del_stmt->execute([$ms_id]);

                calculateProjectProgress($target_ms['project_id']);

                log_activity($contractor_id, 'Milestone Deleted', "Deleted milestone #{$ms_id} ('{$target_ms['title']}')", 'milestone', $ms_id);
                set_flash_message("Milestone '{$target_ms['title']}' deleted.", 'info');
                redirect("contractor/milestones.php?project_id={$target_ms['project_id']}");
            }
        }
    }
}

// Build list query
$sql = "
    SELECT m.*, p.title as project_title,
           (SELECT COUNT(*) FROM tasks t WHERE t.milestone_id = m.id) as total_tasks,
           (SELECT COUNT(*) FROM tasks t WHERE t.milestone_id = m.id AND t.status IN ('completed', 'done')) as done_tasks
    FROM milestones m
    JOIN projects p ON m.project_id = p.id
    WHERE p.contractor_id = ?
";
$params = [$contractor_id];

if ($filter_project_id > 0) {
    $sql .= " AND p.id = ?";
    $params[] = $filter_project_id;
}

$sql .= " ORDER BY m.target_date ASC, m.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$milestones = $stmt->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-flag-checkered text-warning me-2"></i>Project Milestones
                </h1>
                <p class="text-muted small mb-0">Define construction phases, target dates, funding allocations, and completion status.</p>
            </div>
            <div>
                <button type="button" class="btn btn-amber fw-bold py-2 px-3" data-bs-toggle="modal" data-bs-target="#createMilestoneModal">
                    <i class="fa-solid fa-plus me-1"></i> Add New Milestone
                </button>
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

        <!-- Project Filter Bar -->
        <div class="bc-card p-3 mb-4">
            <form action="<?= BASE_URL ?>/contractor/milestones.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-8">
                    <select name="project_id" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="0">All Contractor Projects</option>
                        <?php foreach ($contractor_projects as $cp): ?>
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

        <!-- Milestones Table -->
        <div class="bc-card p-4">
            <?php if (empty($milestones)): ?>
                <div class="bc-empty-state py-5 text-center">
                    <i class="fa-solid fa-flag fs-1 text-muted mb-3"></i>
                    <h3 class="h5 text-white fw-bold">No Milestones Recorded</h3>
                    <p class="text-muted small mb-4">No project milestones created for the selected filter.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Milestone Phase</th>
                                <th>Project</th>
                                <th>Target Date</th>
                                <th>Allocation Budget</th>
                                <th>Progress</th>
                                <th>Tasks</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($milestones as $ms): ?>
                                <?php $deadline = get_deadline_status($ms['target_date'], $ms['status']); ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-white"><?= e($ms['title']) ?></div>
                                        <?php if ($ms['description']): ?>
                                            <div class="text-muted extra-small line-clamp-1"><?= e($ms['description']) ?></div>
                                        <?php endif; ?>
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
                                    <td style="min-width: 120px;">
                                        <div class="d-flex justify-content-between extra-small mb-1">
                                            <span class="text-muted">Progress</span>
                                            <span class="text-warning font-monospace fw-bold"><?= (int)$ms['progress_percent'] ?>%</span>
                                        </div>
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar bg-amber" style="width: <?= (int)$ms['progress_percent'] ?>%"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-dark border border-secondary text-light extra-small">
                                            <?= (int)$ms['done_tasks'] ?> / <?= (int)$ms['total_tasks'] ?> Tasks Done
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= get_status_badge_class($ms['status']) ?>"><?= e(ucfirst($ms['status'])) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#editMilestoneModal<?= $ms['id'] ?>" title="Edit Milestone">
                                                <i class="fa-solid fa-pen text-warning"></i>
                                            </button>
                                            <form action="<?= BASE_URL ?>/contractor/milestones.php" method="POST" class="d-inline" onsubmit="return confirm('Delete this milestone?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="milestone_id" value="<?= $ms['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="Delete Milestone">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Edit Milestone Modal -->
                                <div class="modal fade" id="editMilestoneModal<?= $ms['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <form action="<?= BASE_URL ?>/contractor/milestones.php" method="POST" class="modal-content bg-dark text-light border-secondary">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="edit">
                                            <input type="hidden" name="milestone_id" value="<?= $ms['id'] ?>">

                                            <div class="modal-header border-secondary">
                                                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-pen text-warning me-2"></i>Edit Milestone #<?= $ms['id'] ?></h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>

                                            <div class="modal-body vstack gap-3">
                                                <div>
                                                    <label class="form-label small fw-bold text-light mb-1">Title <span class="text-danger">*</span></label>
                                                    <input type="text" name="title" value="<?= e($ms['title']) ?>" class="form-control bg-dark border-secondary text-light" required>
                                                </div>

                                                <div>
                                                    <label class="form-label small fw-bold text-light mb-1">Target Completion Date</label>
                                                    <input type="date" name="target_date" value="<?= e($ms['target_date']) ?>" class="form-control bg-dark border-secondary text-light">
                                                </div>

                                                <div>
                                                    <label class="form-label small fw-bold text-light mb-1">Budget Allocation (INR ₹)</label>
                                                    <input type="number" step="0.01" name="amount" value="<?= e($ms['amount']) ?>" class="form-control bg-dark border-secondary text-light">
                                                </div>

                                                <div>
                                                    <label class="form-label small fw-bold text-light mb-1">Progress Percentage (0-100%)</label>
                                                    <input type="number" min="0" max="100" name="progress_percent" value="<?= e($ms['progress_percent']) ?>" class="form-control bg-dark border-secondary text-light">
                                                </div>

                                                <div>
                                                    <label class="form-label small fw-bold text-light mb-1">Status</label>
                                                    <select name="status" class="form-select bg-dark border-secondary text-light">
                                                        <option value="pending" <?= $ms['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                        <option value="in_progress" <?= $ms['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                                        <option value="completed" <?= $ms['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                                        <option value="delayed" <?= $ms['status'] === 'delayed' ? 'selected' : '' ?>>Delayed</option>
                                                    </select>
                                                </div>

                                                <div>
                                                    <label class="form-label small fw-bold text-light mb-1">Description</label>
                                                    <textarea name="description" rows="3" class="form-control bg-dark border-secondary text-light"><?= e($ms['description']) ?></textarea>
                                                </div>
                                            </div>

                                            <div class="modal-footer border-secondary">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-warning btn-sm fw-bold">Update Milestone</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Create Milestone Modal -->
<div class="modal fade" id="createMilestoneModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="<?= BASE_URL ?>/contractor/milestones.php" method="POST" class="modal-content bg-dark text-light border-secondary">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">

            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-plus-circle text-amber me-2"></i>Add Project Milestone</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body vstack gap-3">
                <div>
                    <label class="form-label small fw-bold text-light mb-1">Associated Project <span class="text-danger">*</span></label>
                    <select name="project_id" class="form-select bg-dark border-secondary text-light" required>
                        <option value="">-- Select Project --</option>
                        <?php foreach ($contractor_projects as $cp): ?>
                            <option value="<?= $cp['id'] ?>" <?= $filter_project_id == $cp['id'] ? 'selected' : '' ?>><?= e($cp['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label small fw-bold text-light mb-1">Milestone Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control bg-dark border-secondary text-light" placeholder="e.g. Structural Steel Erection" required>
                </div>

                <div>
                    <label class="form-label small fw-bold text-light mb-1">Target Completion Date</label>
                    <input type="date" name="target_date" class="form-control bg-dark border-secondary text-light">
                </div>

                <div>
                    <label class="form-label small fw-bold text-light mb-1">Budget Allocation (INR ₹)</label>
                    <input type="number" step="0.01" min="0" name="amount" class="form-control bg-dark border-secondary text-light" placeholder="e.g. 500000.00">
                </div>

                <div>
                    <label class="form-label small fw-bold text-light mb-1">Status</label>
                    <select name="status" class="form-select bg-dark border-secondary text-light">
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>

                <div>
                    <label class="form-label small fw-bold text-light mb-1">Description</label>
                    <textarea name="description" rows="3" class="form-control bg-dark border-secondary text-light" placeholder="Describe the deliverables required to complete this milestone phase..."></textarea>
                </div>
            </div>

            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-amber btn-sm fw-bold">Save Milestone</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
