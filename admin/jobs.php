<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$page_title = "Global Job Postings - Admin - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$current_user = currentUser();
$db = getDB();

$error = '';
$flash = get_flash_message();

// Status Change Handler by Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security validation failed (CSRF token invalid).';
    } else {
        $job_id = (int)($_POST['job_id'] ?? 0);
        $new_status = sanitize($_POST['status'] ?? 'published');

        if (in_array($new_status, ['draft', 'published', 'open', 'paused', 'closed', 'filled', 'cancelled'])) {
            $stmt = $db->prepare("UPDATE jobs SET status = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$new_status, $job_id])) {
                log_activity($current_user['id'], 'Admin Job Status Change', "Updated Job #{$job_id} status to {$new_status}", 'job', $job_id);
                set_flash_message("Job #{$job_id} status updated to '" . ucfirst($new_status) . "'.", 'success');
                redirect('admin/jobs.php');
            } else {
                $error = 'Failed to update job status in database.';
            }
        } else {
            $error = 'Invalid status selected.';
        }
    }
}

// Fetch all jobs system-wide
$stmt = $db->prepare("
    SELECT j.*, p.title as project_title, c.company_name, u.name as contractor_user_name,
           (SELECT COUNT(*) FROM job_applications ja WHERE ja.job_id = j.id) as app_count
    FROM jobs j
    LEFT JOIN projects p ON j.project_id = p.id
    LEFT JOIN users u ON j.contractor_id = u.id
    LEFT JOIN contractors c ON u.id = c.user_id
    ORDER BY j.id DESC
");
$stmt->execute();
$jobs = $stmt->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-briefcase text-info me-2"></i>Global Job Postings</h1>
                <p class="text-muted small mb-0">Platform trade listings, contractor posts, and availability tracking across all projects.</p>
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

        <div class="bc-card p-4">
            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Job Title & Location</th>
                            <th>Contractor</th>
                            <th>Project</th>
                            <th>Trade Required</th>
                            <th>Pay Rate</th>
                            <th>Spots</th>
                            <th>Applicants</th>
                            <th>Status</th>
                            <th class="text-end">Manage Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($jobs)): ?>
                            <tr><td colspan="9" class="text-center py-4 text-muted">No job postings recorded in database.</td></tr>
                        <?php else: ?>
                            <?php foreach ($jobs as $j): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-white"><?= e($j['title']) ?></div>
                                        <div class="text-muted extra-small"><i class="fa-solid fa-location-dot me-1 text-danger"></i><?= e($j['city'] ? "{$j['city']}, {$j['state']}" : $j['location']) ?></div>
                                    </td>
                                    <td class="small text-muted"><?= e($j['company_name'] ?: $j['contractor_user_name']) ?></td>
                                    <td class="small text-muted"><?= e($j['project_title']) ?></td>
                                    <td><span class="badge bg-secondary"><?= e($j['trade_required']) ?></span></td>
                                    <td class="fw-bold text-warning"><?= format_currency($j['pay_rate']) ?>/<?= e($j['pay_type']) ?></td>
                                    <td class="small text-muted"><?= (int)$j['spots_filled'] ?> / <?= (int)$j['spots_available'] ?></td>
                                    <td class="small text-light font-monospace"><?= (int)$j['app_count'] ?> Applications</td>
                                    <td>
                                        <span class="badge <?= get_status_badge_class($j['status']) ?>">
                                            <?= e(ucfirst($j['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <form action="<?= BASE_URL ?>/admin/jobs.php" method="POST" class="d-inline-flex gap-1">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="job_id" value="<?= $j['id'] ?>">
                                            <select name="status" class="form-select form-select-sm bg-dark border-secondary text-light py-0 px-2" style="font-size: 0.75rem;" onchange="this.form.submit()">
                                                <option value="published" <?= in_array($j['status'], ['published', 'open']) ? 'selected' : '' ?>>Published</option>
                                                <option value="draft" <?= $j['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                                                <option value="paused" <?= $j['status'] === 'paused' ? 'selected' : '' ?>>Paused</option>
                                                <option value="closed" <?= $j['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                                                <option value="filled" <?= $j['status'] === 'filled' ? 'selected' : '' ?>>Filled</option>
                                                <option value="cancelled" <?= $j['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            </select>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
