<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$page_title = "Global Job Applications - Admin - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$current_user = currentUser();
$db = getDB();

$stmt = $db->prepare("
    SELECT ja.*, 
           j.title as job_title, j.trade_required,
           u.name as worker_name, u.email as worker_email,
           c.company_name as contractor_name, cu.name as contractor_user_name,
           p.title as project_title
    FROM job_applications ja
    JOIN jobs j ON ja.job_id = j.id
    JOIN projects p ON j.project_id = p.id
    JOIN users u ON ja.worker_id = u.id
    JOIN users cu ON j.contractor_id = cu.id
    LEFT JOIN contractors c ON cu.id = c.user_id
    ORDER BY ja.id DESC
");
$stmt->execute();
$applications = $stmt->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-file-signature text-warning me-2"></i>Global Job Application Monitor</h1>
                <p class="text-muted small mb-0">System-wide log of trade job applications, candidate match scores, and hiring outcomes.</p>
            </div>
        </div>

        <div class="bc-card p-4">
            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Applicant Worker</th>
                            <th>Target Job</th>
                            <th>Contractor & Project</th>
                            <th>AI Fit Score</th>
                            <th>Applied Date</th>
                            <th>Application Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($applications)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No job applications logged in the database yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-white"><?= e($app['worker_name']) ?></div>
                                        <div class="text-muted extra-small"><?= e($app['worker_email']) ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-light"><?= e($app['job_title']) ?></div>
                                        <div class="extra-small text-muted"><i class="fa-solid fa-screwdriver-wrench me-1 text-warning"></i><?= e($app['trade_required']) ?></div>
                                    </td>
                                    <td>
                                        <div class="small text-info"><?= e($app['contractor_name'] ?: $app['contractor_user_name']) ?></div>
                                        <div class="extra-small text-muted"><?= e($app['project_title']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-purple font-monospace text-white" style="background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);">
                                            <i class="fa-solid fa-brain me-1"></i><?= $app['match_score'] ?>% Match
                                        </span>
                                    </td>
                                    <td class="text-muted extra-small"><?= format_datetime($app['applied_at']) ?></td>
                                    <td>
                                        <span class="badge <?= get_status_badge_class($app['status']) ?>">
                                            <?= e(ucfirst($app['status'])) ?>
                                        </span>
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
