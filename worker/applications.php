<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('worker');

$page_title = "My Applications - Worker - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$worker_user_id = (int)$user['id'];

$error = '';
$flash = get_flash_message();

// Handle Application Withdrawal (allowed only if Pending or Shortlisted)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security validation failed (CSRF token invalid).';
    } else {
        $app_id = (int)($_POST['application_id'] ?? 0);
        $action = sanitize($_POST['action'] ?? '');

        if ($action === 'withdraw') {
            // Verify application belongs strictly to logged-in worker
            $stmt_check = $db->prepare("
                SELECT ja.id, ja.status, j.title as job_title, j.contractor_id
                FROM job_applications ja
                JOIN jobs j ON ja.job_id = j.id
                WHERE ja.id = ? AND ja.worker_id = ?
            ");
            $stmt_check->execute([$app_id, $worker_user_id]);
            $target_app = $stmt_check->fetch();

            if (!$target_app) {
                $error = 'Access Denied: Application not found or ownership mismatch.';
            } elseif (!in_array($target_app['status'], ['pending', 'shortlisted'])) {
                $error = "You cannot withdraw an application with status '{$target_app['status']}'.";
            } else {
                $up_stmt = $db->prepare("UPDATE job_applications SET status = 'withdrawn', updated_at = NOW() WHERE id = ? AND worker_id = ?");
                $up_stmt->execute([$app_id, $worker_user_id]);

                create_notification($target_app['contractor_id'], 'Application Withdrawn', "Worker '{$user['name']}' withdrew their application for position '{$target_app['job_title']}'.", 'info', 'contractor/applications.php');
                log_activity($worker_user_id, 'Application Withdrawn', "Withdrew application #{$app_id} for job '{$target_app['job_title']}'", 'job_application', $app_id);

                set_flash_message("Your application for '{$target_app['job_title']}' has been withdrawn.", 'info');
                redirect('worker/applications.php');
            }
        }
    }
}

// Fetch applications belonging ONLY to logged-in worker
$stmt = $db->prepare("
    SELECT ja.*, 
           j.id as job_id, j.title as job_title, j.trade_required, j.pay_rate, j.pay_type, j.location as job_location,
           p.title as project_title, c.company_name as contractor_company, u.name as contractor_name
    FROM job_applications ja
    JOIN jobs j ON ja.job_id = j.id
    JOIN projects p ON j.project_id = p.id
    JOIN users u ON j.contractor_id = u.id
    LEFT JOIN contractors c ON u.id = c.user_id
    WHERE ja.worker_id = ?
    ORDER BY ja.id DESC
");
$stmt->execute([$worker_user_id]);
$applications = $stmt->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-file-signature text-warning me-2"></i>My Job Applications
                </h1>
                <p class="text-muted small mb-0">Track the review progress of trade job applications you submitted to contractors.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/worker/jobs.php" class="btn btn-amber btn-sm fw-bold">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Browse More Jobs
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

        <div class="bc-card p-4">
            <?php if (empty($applications)): ?>
                <div class="bc-empty-state py-5 text-center">
                    <i class="fa-solid fa-folder-open fs-1 text-muted mb-3"></i>
                    <h3 class="h5 text-white fw-bold">No Job Applications Submitted</h3>
                    <p class="text-muted small mb-4">You have not applied for any trade job vacancies yet.</p>
                    <a href="<?= BASE_URL ?>/worker/jobs.php" class="btn btn-amber btn-sm fw-bold">
                        <i class="fa-solid fa-plus me-1"></i> Browse Open Vacancies
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Position & Trade</th>
                                <th>Contractor & Project</th>
                                <th>Rate Offered</th>
                                <th>Applied Date</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_URL ?>/worker/job-details.php?id=<?= $app['job_id'] ?>" class="fw-bold text-white text-decoration-none hover-amber">
                                            <?= e($app['job_title']) ?>
                                        </a>
                                        <div class="mt-1">
                                            <span class="badge bg-dark border border-secondary text-warning extra-small"><?= e($app['trade_required']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-info small"><?= e($app['contractor_company'] ?: $app['contractor_name']) ?></div>
                                        <div class="extra-small text-muted"><i class="fa-solid fa-building me-1"></i><?= e($app['project_title']) ?></div>
                                    </td>
                                    <td>
                                        <span class="font-monospace text-warning fw-bold"><?= format_currency($app['expected_pay'] ?: $app['pay_rate']) ?></span>
                                        <span class="text-muted extra-small">/ <?= e($app['pay_type']) ?></span>
                                    </td>
                                    <td class="text-muted extra-small">
                                        <?= format_datetime($app['applied_at']) ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= get_status_badge_class($app['status']) ?> py-1 px-3">
                                            <?= e(ucfirst($app['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2">
                                            <a href="<?= BASE_URL ?>/worker/job-details.php?id=<?= $app['job_id'] ?>" class="btn btn-outline-light btn-sm extra-small py-1 px-2" title="View Job Details">
                                                View Job
                                            </a>

                                            <?php if (in_array($app['status'], ['pending', 'shortlisted'])): ?>
                                                <form action="<?= BASE_URL ?>/worker/applications.php" method="POST" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                                    <button type="submit" name="action" value="withdraw" class="btn btn-outline-danger btn-sm extra-small py-1 px-2" onclick="return confirm('Withdraw this application?');">
                                                        Withdraw
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
