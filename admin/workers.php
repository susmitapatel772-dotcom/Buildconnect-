<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$current_user = currentUser();
$db = getDB();

$error = '';

// Action Handlers BEFORE HTML Output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security validation failed (CSRF token invalid).';
    } else {
        $action = $_POST['action'] ?? '';
        $worker_id = (int)($_POST['worker_id'] ?? 0);
        $user_id = (int)($_POST['user_id'] ?? 0);

        if ($action === 'verify_worker') {
            $new_v_status = sanitize($_POST['verification_status'] ?? 'approved');
            $stmt = $db->prepare("UPDATE workers SET verification_status = ?, verified_at = CURRENT_TIMESTAMP, verified_by_user_id = ? WHERE id = ?");
            if ($stmt->execute([$new_v_status, $current_user['id'], $worker_id])) {
                log_activity($current_user['id'], 'Worker Verification Update', "Set verification status of Worker #{$worker_id} to {$new_v_status}", 'worker', $worker_id);
                set_flash_message("Worker verification status updated to '{$new_v_status}'.", 'success');
                redirect('admin/workers.php');
            }
        } elseif ($action === 'toggle_user_status') {
            $new_user_status = sanitize($_POST['new_status'] ?? 'active');
            $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
            if ($stmt->execute([$new_user_status, $user_id])) {
                log_activity($current_user['id'], 'User Status Toggle', "Set User #{$user_id} status to {$new_user_status}", 'user', $user_id);
                set_flash_message("Account status updated to '{$new_user_status}'.", 'success');
                redirect('admin/workers.php');
            }
        }
    }
}

$page_title = "Worker Management - Admin - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$flash = get_flash_message();

// Search and Verification Filter
$search = trim($_GET['search'] ?? '');
$v_filter = trim($_GET['v_status'] ?? 'all');

$where = ["u.role = 'worker'"];
$params = [];

if ($search !== '') {
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR w.trade_title LIKE ? OR w.city LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}

if ($v_filter !== 'all' && in_array($v_filter, ['pending', 'approved', 'rejected'])) {
    $where[] = "w.verification_status = ?";
    $params[] = $v_filter;
}

$sql = "
    SELECT w.*, u.id as user_id, u.name, u.email, u.phone, u.status as user_status, u.created_at as joined_at
    FROM workers w
    JOIN users u ON w.user_id = u.id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY CASE WHEN w.verification_status = 'pending' THEN 0 ELSE 1 END, w.id DESC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$workers = $stmt->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-helmet-safety text-warning me-2"></i>Worker Management</h1>
                <p class="text-muted small mb-0">Skilled trades directory, verification badges, and account status management.</p>
            </div>
            <a href="<?= BASE_URL ?>/admin/verification.php" class="btn btn-amber btn-sm fw-bold">
                <i class="fa-solid fa-id-card me-1"></i> Verification Queue
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

        <!-- Search & Filter Controls -->
        <div class="bc-card p-3 mb-4">
            <form action="<?= BASE_URL ?>/admin/workers.php" method="GET" class="row g-3">
                <div class="col-md-7">
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" class="form-control" name="search" placeholder="Search workers by name, email, trade, or city..." value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select class="form-select" name="v_status" onchange="this.form.submit()">
                        <option value="all" <?= $v_filter === 'all' ? 'selected' : '' ?>>All Verification Statuses</option>
                        <option value="pending" <?= $v_filter === 'pending' ? 'selected' : '' ?>>Pending Verification</option>
                        <option value="approved" <?= $v_filter === 'approved' ? 'selected' : '' ?>>Approved / Verified</option>
                        <option value="rejected" <?= $v_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-amber w-100 fw-bold"><i class="fa-solid fa-filter"></i></button>
                </div>
            </form>
        </div>

        <!-- Worker Directory Grid -->
        <div class="row g-4">
            <?php if (empty($workers)): ?>
                <div class="col-12">
                    <div class="bc-empty-state">
                        <i class="fa-solid fa-helmet-safety"></i>
                        <p class="mb-0">No construction workers found matching the specified query.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($workers as $w): ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="bc-card p-4 h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h3 class="h5 text-white fw-bold mb-0"><?= e($w['name']) ?></h3>
                                        <div class="text-muted extra-small"><i class="fa-solid fa-envelope me-1"></i><?= e($w['email']) ?></div>
                                    </div>
                                    <span class="badge <?= get_status_badge_class($w['verification_status']) ?> text-uppercase">
                                        <?= e($w['verification_status']) ?>
                                    </span>
                                </div>

                                <div class="mb-3">
                                    <span class="badge bg-secondary me-1"><i class="fa-solid fa-screwdriver-wrench me-1"></i><?= e($w['trade_title']) ?></span>
                                    <span class="badge bg-dark border border-secondary"><i class="fa-solid fa-briefcase me-1"></i><?= $w['experience_years'] ?> Yrs Exp</span>
                                </div>

                                <p class="text-light extra-small opacity-75 mb-3"><?= e($w['bio'] ?: 'No bio provided.') ?></p>

                                <div class="d-flex justify-content-between align-items-center bg-dark p-2 rounded-3 border border-secondary mb-3">
                                    <div>
                                        <span class="text-muted extra-small d-block">Hourly Rate</span>
                                        <span class="fw-bold text-warning"><?= format_currency($w['hourly_rate']) ?>/hr</span>
                                    </div>
                                    <div class="text-end">
                                        <span class="text-muted extra-small d-block">Account Status</span>
                                        <span class="badge <?= get_status_badge_class($w['user_status']) ?>"><?= e($w['user_status']) ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-3 border-top border-secondary d-flex gap-2">
                                <!-- Verify / Reject Actions -->
                                <?php if ($w['verification_status'] !== 'approved'): ?>
                                    <form action="<?= BASE_URL ?>/admin/workers.php" method="POST" class="w-100">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="verify_worker">
                                        <input type="hidden" name="worker_id" value="<?= $w['id'] ?>">
                                        <input type="hidden" name="verification_status" value="approved">
                                        <button type="submit" class="btn btn-success btn-sm w-100 fw-bold"><i class="fa-solid fa-check me-1"></i> Verify</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($w['verification_status'] !== 'rejected'): ?>
                                    <form action="<?= BASE_URL ?>/admin/workers.php" method="POST" class="w-100">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="verify_worker">
                                        <input type="hidden" name="worker_id" value="<?= $w['id'] ?>">
                                        <input type="hidden" name="verification_status" value="rejected">
                                        <button type="submit" class="btn btn-outline-danger btn-sm w-100"><i class="fa-solid fa-ban me-1"></i> Reject</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
