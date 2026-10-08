<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('contractor');

$page_title = "Manage Applications - Contractor - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$contractor_id = (int)$user['id'];

$error = '';
$flash = get_flash_message();

// Handle Quick Status Actions (Shortlist / Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security validation failed (CSRF token invalid).';
    } else {
        $app_id = (int)($_POST['application_id'] ?? 0);
        $action = sanitize($_POST['action'] ?? '');

        // Verify application belongs to a job owned by this contractor
        $stmt_check = $db->prepare("
            SELECT ja.id, ja.status, ja.worker_id, j.id as job_id, j.title as job_title, u.name as worker_name
            FROM job_applications ja
            JOIN jobs j ON ja.job_id = j.id
            JOIN users u ON ja.worker_id = u.id
            WHERE ja.id = ? AND j.contractor_id = ?
        ");
        $stmt_check->execute([$app_id, $contractor_id]);
        $target_app = $stmt_check->fetch();

        if (!$target_app) {
            $error = 'Access Denied: Application not found or ownership mismatch.';
        } else {
            if ($action === 'shortlist' && in_array($target_app['status'], ['pending', 'rejected'])) {
                $up_stmt = $db->prepare("UPDATE job_applications SET status = 'shortlisted', updated_at = NOW() WHERE id = ?");
                $up_stmt->execute([$app_id]);

                create_notification($target_app['worker_id'], 'Application Shortlisted', "Your application for '{$target_app['job_title']}' has been shortlisted by the contractor.", 'info', 'worker/applications.php');
                log_activity($contractor_id, 'Application Shortlisted', "Shortlisted worker '{$target_app['worker_name']}' for job #{$target_app['job_id']}", 'job_application', $app_id);
                set_flash_message("Applicant '{$target_app['worker_name']}' has been shortlisted.", 'success');
                redirect('contractor/applications.php');

            } elseif ($action === 'reject' && in_array($target_app['status'], ['pending', 'shortlisted'])) {
                $up_stmt = $db->prepare("UPDATE job_applications SET status = 'rejected', updated_at = NOW() WHERE id = ?");
                $up_stmt->execute([$app_id]);

                create_notification($target_app['worker_id'], 'Application Status Update', "Your application for '{$target_app['job_title']}' was not selected.", 'warning', 'worker/applications.php');
                log_activity($contractor_id, 'Application Rejected', "Rejected worker '{$target_app['worker_name']}' for job #{$target_app['job_id']}", 'job_application', $app_id);
                set_flash_message("Applicant '{$target_app['worker_name']}' status updated to Rejected.", 'info');
                redirect('contractor/applications.php');
            }
        }
    }
}

// Filtering options
$filter_job_id = (int)($_GET['job_id'] ?? 0);
$filter_status = sanitize($_GET['status'] ?? 'all');
$filter_trade = sanitize($_GET['trade'] ?? 'all');

// Fetch contractor jobs for dropdown
$jobs_stmt = $db->prepare("SELECT id, title FROM jobs WHERE contractor_id = ? ORDER BY id DESC");
$jobs_stmt->execute([$contractor_id]);
$contractor_jobs = $jobs_stmt->fetchAll();

// Build Query
$sql = "
    SELECT ja.*, j.title as job_title, j.trade_required, j.spots_available, j.spots_filled, j.status as job_status,
           u.name as worker_name, u.email as worker_email, u.phone as worker_phone,
           w.trade_title, w.experience_years, w.city as worker_city, w.rating_avg, w.verification_status
    FROM job_applications ja
    JOIN jobs j ON ja.job_id = j.id
    JOIN users u ON ja.worker_id = u.id
    LEFT JOIN workers w ON u.id = w.user_id
    WHERE j.contractor_id = ?
";
$params = [$contractor_id];

if ($filter_job_id > 0) {
    $sql .= " AND j.id = ?";
    $params[] = $filter_job_id;
}

if (in_array($filter_status, ['pending', 'shortlisted', 'accepted', 'rejected', 'withdrawn'])) {
    $sql .= " AND ja.status = ?";
    $params[] = $filter_status;
}

if ($filter_trade !== 'all' && !empty($filter_trade)) {
    $sql .= " AND j.trade_required = ?";
    $params[] = $filter_trade;
}

$sql .= " ORDER BY ja.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-file-signature text-amber me-2"></i>Job Applicant Reviewer
                </h1>
                <p class="text-muted small mb-0">Review candidate applications, shortlist top candidates, and accept workers into project teams.</p>
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

        <!-- Filters Bar -->
        <div class="bc-card p-3 mb-4">
            <form action="<?= BASE_URL ?>/contractor/applications.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <select name="job_id" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="0">All Jobs</option>
                        <?php foreach ($contractor_jobs as $cj): ?>
                            <option value="<?= $cj['id'] ?>" <?= $filter_job_id == $cj['id'] ? 'selected' : '' ?>>
                                <?= e($cj['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Application Statuses</option>
                        <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                        <option value="shortlisted" <?= $filter_status === 'shortlisted' ? 'selected' : '' ?>>Shortlisted</option>
                        <option value="accepted" <?= $filter_status === 'accepted' ? 'selected' : '' ?>>Accepted (Hired)</option>
                        <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="withdrawn" <?= $filter_status === 'withdrawn' ? 'selected' : '' ?>>Withdrawn</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <select name="trade" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="all">All Trade Categories</option>
                        <option value="Structural Welding" <?= $filter_trade === 'Structural Welding' ? 'selected' : '' ?>>Structural Welding</option>
                        <option value="Tower Crane Operation" <?= $filter_trade === 'Tower Crane Operation' ? 'selected' : '' ?>>Tower Crane Operation</option>
                        <option value="Commercial Electrical Wiring" <?= $filter_trade === 'Commercial Electrical Wiring' ? 'selected' : '' ?>>Commercial Electrical Wiring</option>
                        <option value="Concrete Formwork & Masonry" <?= $filter_trade === 'Concrete Formwork & Masonry' ? 'selected' : '' ?>>Concrete Formwork & Masonry</option>
                    </select>
                </div>

                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-amber btn-sm fw-bold">
                        <i class="fa-solid fa-filter me-1"></i> Filter Results
                    </button>
                </div>
            </form>
        </div>

        <!-- Applications List -->
        <div class="bc-card p-4">
            <?php if (empty($applications)): ?>
                <div class="bc-empty-state py-5 text-center">
                    <i class="fa-solid fa-users-slash fs-1 text-muted mb-3"></i>
                    <h3 class="h5 text-white fw-bold">No Applications Found</h3>
                    <p class="text-muted small mb-0">No candidate submissions match your selected filter criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Worker Candidate</th>
                                <th>Target Job</th>
                                <th>Experience & Verification</th>
                                <th>Fit Score</th>
                                <th>Applied Date</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle bg-secondary text-white fw-bold d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                                <?= strtoupper(substr($app['worker_name'], 0, 2)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-white"><?= e($app['worker_name']) ?></div>
                                                <div class="extra-small text-muted"><?= e($app['worker_phone'] ?: $app['worker_email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/contractor/job-details.php?id=<?= $app['job_id'] ?>" class="text-info text-decoration-none fw-semibold small">
                                            <?= e($app['job_title']) ?>
                                        </a>
                                        <div class="extra-small text-muted"><i class="fa-solid fa-screwdriver-wrench me-1 text-warning"></i><?= e($app['trade_required']) ?></div>
                                    </td>
                                    <td>
                                        <div class="small text-light"><?= (int)$app['experience_years'] ?> Years Experience</div>
                                        <div>
                                            <?php if ($app['verification_status'] === 'approved'): ?>
                                                <span class="badge bg-success extra-small"><i class="fa-solid fa-shield-check me-1"></i>Verified Worker</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary extra-small">Unverified</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-purple text-white font-monospace" style="background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);">
                                            <i class="fa-solid fa-brain me-1"></i><?= $app['match_score'] ?>% Match
                                        </span>
                                    </td>
                                    <td class="text-muted extra-small">
                                        <?= format_datetime($app['applied_at']) ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= get_status_badge_class($app['status']) ?>"><?= e(ucfirst($app['status'])) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1 align-items-center">
                                            <a href="<?= BASE_URL ?>/contractor/application-details.php?id=<?= $app['id'] ?>" class="btn btn-amber btn-sm fw-bold extra-small py-1 px-2" title="Full Application & Hire Review">
                                                Review <i class="fa-solid fa-chevron-right ms-1"></i>
                                            </a>

                                            <?php if (in_array($app['status'], ['pending', 'rejected'])): ?>
                                                <form action="<?= BASE_URL ?>/contractor/applications.php" method="POST" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                                    <button type="submit" name="action" value="shortlist" class="btn btn-outline-info btn-sm extra-small py-1 px-2" title="Shortlist Worker">
                                                        Shortlist
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if (in_array($app['status'], ['pending', 'shortlisted'])): ?>
                                                <form action="<?= BASE_URL ?>/contractor/applications.php" method="POST" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                                    <button type="submit" name="action" value="reject" class="btn btn-outline-danger btn-sm extra-small py-1 px-2" title="Reject Worker" onclick="return confirm('Reject this application?');">
                                                        Reject
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
