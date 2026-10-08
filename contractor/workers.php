<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('contractor');

$page_title = "Workforce Management - Contractor - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$contractor_id = (int)$user['id'];

$error = '';
$flash = get_flash_message();

// Filter parameters
$filter_project_id = (int)($_GET['project_id'] ?? 0);
$filter_status = sanitize($_GET['status'] ?? 'active');

// Fetch contractor projects
$p_stmt = $db->prepare("SELECT id, title FROM projects WHERE contractor_id = ? ORDER BY id DESC");
$p_stmt->execute([$contractor_id]);
$contractor_projects = $p_stmt->fetchAll();

// Handle Worker Removal / Status Change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security validation failed (CSRF token invalid).';
    } else {
        $member_id = (int)($_POST['member_id'] ?? 0);
        $new_status = sanitize($_POST['status'] ?? 'removed');

        // Verify member belongs to a project owned by this contractor
        $chk_stmt = $db->prepare("
            SELECT pm.id, pm.project_id, pm.user_id, p.title as project_title, u.name as worker_name
            FROM project_members pm
            JOIN projects p ON pm.project_id = p.id
            JOIN users u ON pm.user_id = u.id
            WHERE pm.id = ? AND p.contractor_id = ?
        ");
        $chk_stmt->execute([$member_id, $contractor_id]);
        $target_member = $chk_stmt->fetch();

        if (!$target_member) {
            $error = 'Access Denied: Project member record not found or ownership mismatch.';
        } else {
            if ($new_status === 'removed') {
                // REMOVAL SAFETY CHECK (Section 18): Check active non-completed tasks
                $task_chk = $db->prepare("
                    SELECT COUNT(*) 
                    FROM tasks 
                    WHERE project_id = ? AND assigned_to_worker_id = ? AND status NOT IN ('completed', 'done', 'cancelled')
                ");
                $task_chk->execute([$target_member['project_id'], $target_member['user_id']]);
                $active_tasks_count = (int)$task_chk->fetchColumn();

                if ($active_tasks_count > 0) {
                    $error = "Cannot remove worker '{$target_member['worker_name']}' while they have {$active_tasks_count} active assigned task(s). Please reassign or complete their tasks first.";
                } else {
                    $up_stmt = $db->prepare("UPDATE project_members SET status = 'removed' WHERE id = ?");
                    $up_stmt->execute([$member_id]);

                    log_activity($contractor_id, 'Worker Removed', "Removed worker '{$target_member['worker_name']}' from project '{$target_member['project_title']}'", 'project_member', $member_id);
                    set_flash_message("Worker '{$target_member['worker_name']}' has been removed from project workforce.", 'info');
                    redirect('contractor/workers.php');
                }

            } elseif (in_array($new_status, ['active', 'completed'])) {
                $up_stmt = $db->prepare("UPDATE project_members SET status = ? WHERE id = ?");
                $up_stmt->execute([$new_status, $member_id]);

                log_activity($contractor_id, 'Worker Status Updated', "Updated worker '{$target_member['worker_name']}' status to {$new_status}", 'project_member', $member_id);
                set_flash_message("Worker '{$target_member['worker_name']}' status updated to '" . ucfirst($new_status) . "'.", 'success');
                redirect('contractor/workers.php');
            }
        }
    }
}

// Fetch project members list
$sql = "
    SELECT pm.*, p.title as project_title,
           u.name as worker_name, u.email as worker_email, u.phone as worker_phone,
           w.trade_title, w.experience_years, w.verification_status,
           (SELECT COUNT(*) FROM tasks t WHERE t.project_id = pm.project_id AND t.assigned_to_worker_id = pm.user_id AND t.status NOT IN ('completed', 'done', 'cancelled')) as active_tasks_count,
           (SELECT COUNT(*) FROM tasks t WHERE t.project_id = pm.project_id AND t.assigned_to_worker_id = pm.user_id AND t.status IN ('completed', 'done')) as completed_tasks_count
    FROM project_members pm
    JOIN projects p ON pm.project_id = p.id
    JOIN users u ON pm.user_id = u.id
    LEFT JOIN workers w ON u.id = w.user_id
    WHERE p.contractor_id = ? AND u.role = 'worker'
";
$params = [$contractor_id];

if ($filter_project_id > 0) {
    $sql .= " AND p.id = ?";
    $params[] = $filter_project_id;
}

if (in_array($filter_status, ['active', 'removed', 'completed'])) {
    $sql .= " AND pm.status = ?";
    $params[] = $filter_status;
}

$sql .= " ORDER BY pm.joined_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$workforce = $stmt->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-users text-amber me-2"></i>Project Workforce Management
                </h1>
                <p class="text-muted small mb-0">Manage hired trade specialists assigned to your active construction developments.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/contractor/applications.php" class="btn btn-amber fw-bold py-2 px-3">
                    <i class="fa-solid fa-user-plus me-1"></i> Hire More Workers
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

        <!-- Filter Bar -->
        <div class="bc-card p-3 mb-4">
            <form action="<?= BASE_URL ?>/contractor/workers.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-6">
                    <select name="project_id" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="0">All Contractor Projects</option>
                        <?php foreach ($contractor_projects as $cp): ?>
                            <option value="<?= $cp['id'] ?>" <?= $filter_project_id == $cp['id'] ? 'selected' : '' ?>>
                                <?= e($cp['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <select name="status" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Active Members</option>
                        <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed Assignment</option>
                        <option value="removed" <?= $filter_status === 'removed' ? 'selected' : '' ?>>Removed Members</option>
                    </select>
                </div>

                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-amber btn-sm fw-bold">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Workforce Table -->
        <div class="bc-card p-4">
            <?php if (empty($workforce)): ?>
                <div class="bc-empty-state py-5 text-center">
                    <i class="fa-solid fa-user-slash fs-1 text-muted mb-3"></i>
                    <h3 class="h5 text-white fw-bold">No Workforce Members Found</h3>
                    <p class="text-muted small mb-4">No project members match your filter criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Worker Candidate</th>
                                <th>Project & Role</th>
                                <th>Trade Title</th>
                                <th>Joined Date</th>
                                <th>Assigned Tasks</th>
                                <th>Assignment Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($workforce as $w): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle bg-secondary text-white fw-bold d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; font-size: 0.85rem;">
                                                <?= strtoupper(substr($w['worker_name'], 0, 2)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-white"><?= e($w['worker_name']) ?></div>
                                                <div class="extra-small text-muted"><?= e($w['worker_phone'] ?: $w['worker_email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-info small"><?= e($w['project_title']) ?></div>
                                        <div class="extra-small text-muted"><?= e($w['role_in_project']) ?></div>
                                    </td>
                                    <td class="small text-light">
                                        <?= e($w['trade_title'] ?: 'General Worker') ?>
                                    </td>
                                    <td class="text-muted extra-small">
                                        <?= format_date($w['joined_at']) ?>
                                    </td>
                                    <td>
                                        <div class="extra-small">
                                            <span class="badge bg-dark border border-secondary text-warning me-1"><?= (int)$w['active_tasks_count'] ?> Active</span>
                                            <span class="badge bg-dark border border-secondary text-success"><?= (int)$w['completed_tasks_count'] ?> Done</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?= get_status_badge_class($w['status']) ?>"><?= e(ucfirst($w['status'])) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <?php if ($w['status'] === 'active'): ?>
                                                <a href="<?= BASE_URL ?>/contractor/create-task.php?project_id=<?= $w['project_id'] ?>&worker_id=<?= $w['user_id'] ?>" class="btn btn-outline-amber btn-sm extra-small py-1 px-2" title="Assign Task">
                                                    <i class="fa-solid fa-plus me-1"></i> Task
                                                </a>
                                                <form action="<?= BASE_URL ?>/contractor/workers.php" method="POST" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="member_id" value="<?= $w['id'] ?>">
                                                    <button type="submit" name="status" value="removed" class="btn btn-outline-danger btn-sm extra-small py-1 px-2" onclick="return confirm('Remove worker from active project workforce?');">
                                                        Remove
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form action="<?= BASE_URL ?>/contractor/workers.php" method="POST" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="member_id" value="<?= $w['id'] ?>">
                                                    <button type="submit" name="status" value="active" class="btn btn-outline-success btn-sm extra-small py-1 px-2">
                                                        Re-activate
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
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
