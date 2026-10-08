<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$current_user = currentUser();
$db = getDB();

$error = '';

// Handle Approve / Reject Actions BEFORE HTML Output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security validation failed (CSRF token invalid).';
    } else {
        $action = $_POST['action'] ?? '';
        $worker_id = (int)($_POST['worker_id'] ?? 0);

        if (in_array($action, ['approve', 'reject'])) {
            $new_status = ($action === 'approve') ? 'approved' : 'rejected';
            $stmt = $db->prepare("
                UPDATE workers 
                SET verification_status = ?, verified_at = CURRENT_TIMESTAMP, verified_by_user_id = ? 
                WHERE id = ?
            ");
            if ($stmt->execute([$new_status, $current_user['id'], $worker_id])) {
                log_activity(
                    $current_user['id'],
                    'Worker Verification Queue Action',
                    "Worker #{$worker_id} verification marked as {$new_status}",
                    'worker',
                    $worker_id
                );
                set_flash_message("Worker verification decision recorded as '{$new_status}'.", 'success');
                redirect('admin/verification.php');
            }
        }
    }
}

$page_title = "Worker Verification Queue - Admin - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$flash = get_flash_message();

// Fetch pending verification candidates
$stmt = $db->prepare("
    SELECT w.*, u.name, u.email, u.phone, u.created_at as joined_at
    FROM workers w
    JOIN users u ON w.user_id = u.id
    WHERE w.verification_status = 'pending'
    ORDER BY w.id ASC
");
$stmt->execute();
$pending_workers = $stmt->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-id-card text-success me-2"></i>Worker Verification Queue</h1>
                <p class="text-muted small mb-0">Review pending worker trade profiles, experience credentials, and grant verification badges.</p>
            </div>
            <a href="<?= BASE_URL ?>/admin/workers.php" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-users me-1"></i> All Workers
            </a>
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

        <div class="bc-card p-4">
            <?php if (empty($pending_workers)): ?>
                <div class="bc-empty-state py-5">
                    <i class="fa-solid fa-circle-check text-success fs-1 mb-2"></i>
                    <h3 class="h5 text-white fw-bold">Verification Queue Clear</h3>
                    <p class="mb-0">There are currently no worker profiles waiting for verification review.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Worker Details</th>
                                <th>Trade Title & Skills</th>
                                <th>Experience</th>
                                <th>Base City</th>
                                <th>Submitted Date</th>
                                <th class="text-end">Verification Decision</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_workers as $pw): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-white"><?= e($pw['name']) ?></div>
                                        <div class="text-muted extra-small"><?= e($pw['email']) ?> | <?= e($pw['phone'] ?: 'N/A') ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= e($pw['trade_title']) ?></span>
                                    </td>
                                    <td class="fw-bold text-warning"><?= $pw['experience_years'] ?> Years</td>
                                    <td class="text-muted small"><?= e($pw['city']) ?></td>
                                    <td class="text-muted extra-small"><?= format_date($pw['joined_at']) ?></td>
                                    <td class="text-end">
                                        <form action="<?= BASE_URL ?>/admin/verification.php" method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="worker_id" value="<?= $pw['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-success btn-sm me-1 fw-bold">
                                                <i class="fa-solid fa-check me-1"></i> Approve
                                            </button>
                                        </form>

                                        <form action="<?= BASE_URL ?>/admin/verification.php" method="POST" class="d-inline" onsubmit="return confirm('Reject worker verification request?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="worker_id" value="<?= $pw['id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                <i class="fa-solid fa-xmark me-1"></i> Reject
                                            </button>
                                        </form>
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
