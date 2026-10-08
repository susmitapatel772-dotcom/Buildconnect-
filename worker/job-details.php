<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('worker');

$page_title = "Job Details - Worker - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$job_id = (int)($_GET['id'] ?? 0);
$flash = get_flash_message();

// Fetch job + contractor + project details
$stmt = $db->prepare("
    SELECT j.*, p.title as project_title, p.location as project_location,
           c.company_name as contractor_company, c.city as contractor_city, u.name as contractor_name, u.email as contractor_email
    FROM jobs j
    JOIN projects p ON j.project_id = p.id
    JOIN users u ON j.contractor_id = u.id
    LEFT JOIN contractors c ON u.id = c.user_id
    WHERE j.id = ?
");
$stmt->execute([$job_id]);
$job = $stmt->fetch();

if (!$job) {
    ?>
    <div class="bc-layout">
        <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="bc-main-content">
            <div class="bc-card p-5 text-center my-5 border-danger">
                <i class="fa-solid fa-triangle-exclamation text-danger fs-1 mb-3"></i>
                <h2 class="h4 text-white fw-bold">Job Posting Not Found</h2>
                <p class="text-muted small mb-4">The trade job posting you are looking for does not exist or has been removed.</p>
                <a href="<?= BASE_URL ?>/worker/jobs.php" class="btn btn-amber btn-sm fw-bold">
                    <i class="fa-solid fa-arrow-left me-1"></i> Browse Open Jobs
                </a>
            </div>
        </main>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Server-side eligibility check (Section 9)
$worker_user_id = (int)$user['id'];
$is_contractor_owner = ($job['contractor_id'] === $worker_user_id);
$is_published = in_array($job['status'], ['published', 'open']);
$has_spots = ($job['spots_filled'] < $job['spots_available']);

// Check if worker already applied
$app_stmt = $db->prepare("SELECT id, status, applied_at FROM job_applications WHERE job_id = ? AND worker_id = ?");
$app_stmt->execute([$job_id, $worker_user_id]);
$existing_app = $app_stmt->fetch();

// Check if already hired in project_members
$mem_stmt = $db->prepare("SELECT id FROM project_members WHERE project_id = ? AND user_id = ?");
$mem_stmt->execute([$job['project_id'], $worker_user_id]);
$already_member = $mem_stmt->fetch();

$is_eligible = $is_published && !$is_contractor_owner && !$existing_app && !$already_member && $has_spots;
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h1 class="h2 fw-bold text-white mb-0"><?= e($job['title']) ?></h1>
                    <span class="badge bg-dark border border-secondary text-warning fs-6"><?= e($job['trade_required']) ?></span>
                </div>
                <p class="text-muted small mb-0">
                    Posted by <strong class="text-info"><?= e($job['contractor_company'] ?: $job['contractor_name']) ?></strong>
                    • Project: <strong class="text-light"><?= e($job['project_title']) ?></strong>
                </p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/worker/jobs.php" class="btn btn-outline-light btn-sm fw-bold">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Jobs
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
            <!-- Job Description & Details -->
            <div class="col-lg-8">
                <div class="bc-card p-4 h-100">
                    <h2 class="h5 text-white fw-bold mb-3">
                        <i class="fa-solid fa-file-lines text-warning me-2"></i>Job Description & Scope of Work
                    </h2>
                    <div class="text-light lead-sm mb-4" style="white-space: pre-line;">
                        <?= e($job['description']) ?>
                    </div>

                    <h3 class="h6 text-white fw-bold mb-3 border-top border-secondary pt-3">
                        <i class="fa-solid fa-list-check text-info me-2"></i>Position Requirements & Terms
                    </h3>

                    <div class="row g-3 text-muted small">
                        <div class="col-sm-6">
                            <span class="fw-bold text-light">Required Trade Skill:</span>
                            <div class="text-warning fw-semibold mt-1"><?= e($job['trade_required']) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <span class="fw-bold text-light">Employment Type:</span>
                            <div class="text-light mt-1"><?= e($job['employment_type']) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <span class="fw-bold text-light">Job Location:</span>
                            <div class="text-light mt-1"><i class="fa-solid fa-location-dot text-danger me-1"></i><?= e($job['city'] ? "{$job['location']}, {$job['city']}, {$job['state']}" : $job['location']) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <span class="fw-bold text-light">Project Dates:</span>
                            <div class="text-light mt-1"><?= format_date($job['start_date']) ?> to <?= format_date($job['end_date']) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Application Sidebar & Action -->
            <div class="col-lg-4">
                <div class="bc-card p-4 mb-4">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-sack-dollar text-success me-2"></i>Compensation & Openings</h3>

                    <div class="p-3 bg-dark rounded-3 border border-secondary text-center mb-3">
                        <div class="display-6 font-monospace text-warning fw-bold mb-1">
                            <?= format_currency($job['pay_rate']) ?>
                        </div>
                        <div class="text-muted extra-small uppercase">Pay Rate (<?= e(ucfirst($job['pay_type'])) ?>)</div>
                    </div>

                    <div class="d-flex justify-content-between py-2 border-bottom border-secondary extra-small">
                        <span class="text-muted">Total Positions:</span>
                        <span class="text-light fw-bold"><?= (int)$job['spots_available'] ?> Workers</span>
                    </div>

                    <div class="d-flex justify-content-between py-2 border-bottom border-secondary extra-small">
                        <span class="text-muted">Available Openings:</span>
                        <span class="text-warning font-monospace fw-bold"><?= max(0, (int)$job['spots_available'] - (int)$job['spots_filled']) ?> Remaining</span>
                    </div>

                    <div class="d-flex justify-content-between py-2 extra-small mb-3">
                        <span class="text-muted">Posted Date:</span>
                        <span class="text-light"><?= format_date($job['created_at']) ?></span>
                    </div>

                    <!-- Application Action Button / Status Badge -->
                    <div class="d-grid">
                        <?php if ($existing_app): ?>
                            <div class="alert alert-info text-center py-2 px-3 small fw-bold mb-0">
                                <i class="fa-solid fa-check-double me-1"></i> Already Applied
                                <div class="extra-small text-muted mt-1">Status: <?= ucfirst($existing_app['status']) ?> (<?= format_date($existing_app['applied_at']) ?>)</div>
                            </div>
                        <?php elseif ($already_member): ?>
                            <div class="alert alert-success text-center py-2 px-3 small fw-bold mb-0">
                                <i class="fa-solid fa-user-check me-1"></i> You are already a Hired Member on this Project
                            </div>
                        <?php elseif (!$has_spots): ?>
                            <div class="alert alert-warning text-center py-2 px-3 small fw-bold mb-0">
                                <i class="fa-solid fa-lock me-1"></i> Position Filled (No open spots)
                            </div>
                        <?php elseif (!$is_published): ?>
                            <div class="alert alert-secondary text-center py-2 px-3 small text-muted mb-0">
                                Job is currently not accepting applications (Status: <?= ucfirst($job['status']) ?>)
                            </div>
                        <?php elseif ($is_contractor_owner): ?>
                            <div class="alert alert-dark text-center py-2 px-3 extra-small text-muted mb-0">
                                You are the owner of this job posting.
                            </div>
                        <?php elseif ($is_eligible): ?>
                            <a href="<?= BASE_URL ?>/worker/apply-job.php?id=<?= $job['id'] ?>" class="btn btn-amber fw-bold py-2 shadow-sm">
                                <i class="fa-solid fa-paper-plane me-1"></i> Apply Now
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Contractor Info Card -->
                <div class="bc-card p-4">
                    <h3 class="h6 text-white fw-bold mb-2"><i class="fa-solid fa-building text-info me-2"></i>Hiring Company</h3>
                    <div class="fw-bold text-white mb-1"><?= e($job['contractor_company'] ?: $job['contractor_name']) ?></div>
                    <div class="text-muted extra-small"><i class="fa-solid fa-location-dot me-1 text-danger"></i><?= e($job['contractor_city'] ?: 'Gujarat, India') ?></div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
