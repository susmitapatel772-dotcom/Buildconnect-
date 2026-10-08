<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('contractor');

$page_title = "Job Details - Contractor - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$contractor_id = (int)$user['id'];

$job_id = (int)($_GET['id'] ?? 0);
$flash = get_flash_message();

// Fetch job with strict contractor ownership validation
$stmt = $db->prepare("
    SELECT j.*, p.title as project_title, p.id as project_id, p.location as project_location
    FROM jobs j
    JOIN projects p ON j.project_id = p.id
    WHERE j.id = ? AND j.contractor_id = ?
");
$stmt->execute([$job_id, $contractor_id]);
$job = $stmt->fetch();

if (!$job) {
    ?>
    <div class="bc-layout">
        <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="bc-main-content">
            <div class="bc-card p-5 text-center my-5 border-danger">
                <i class="fa-solid fa-lock text-danger fs-1 mb-3"></i>
                <h2 class="h4 text-white fw-bold">Access Denied</h2>
                <p class="text-muted small mb-4">The requested job posting was not found or does not belong to your contractor account.</p>
                <a href="<?= BASE_URL ?>/contractor/jobs.php" class="btn btn-amber btn-sm fw-bold">
                    <i class="fa-solid fa-arrow-left me-1"></i> Return to My Jobs
                </a>
            </div>
        </main>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Fetch applications for this job
$stmt_apps = $db->prepare("
    SELECT ja.*, u.name as worker_name, u.email as worker_email, u.phone as worker_phone, u.avatar as worker_avatar,
           w.trade_title, w.experience_years, w.verification_status, w.rating_avg
    FROM job_applications ja
    JOIN users u ON ja.worker_id = u.id
    LEFT JOIN workers w ON u.id = w.user_id
    WHERE ja.job_id = ?
    ORDER BY ja.id DESC
");
$stmt_apps->execute([$job_id]);
$applications = $stmt_apps->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h1 class="h2 fw-bold text-white mb-0"><?= e($job['title']) ?></h1>
                    <span class="badge <?= get_status_badge_class($job['status']) ?> fs-6"><?= e(ucfirst($job['status'])) ?></span>
                </div>
                <p class="text-muted small mb-0">
                    Project: <a href="<?= BASE_URL ?>/contractor/project-details.php?id=<?= $job['project_id'] ?>" class="text-info text-decoration-none fw-semibold"><?= e($job['project_title']) ?></a>
                    • Posted <?= format_date($job['created_at']) ?>
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= BASE_URL ?>/contractor/jobs.php" class="btn btn-outline-light btn-sm fw-bold">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Jobs
                </a>
                <a href="<?= BASE_URL ?>/contractor/edit-job.php?id=<?= $job_id ?>" class="btn btn-warning btn-sm fw-bold">
                    <i class="fa-solid fa-pen me-1"></i> Edit Job
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
            <!-- Job Overview Card -->
            <div class="col-lg-8">
                <div class="bc-card p-4 h-100">
                    <h2 class="h5 text-white fw-bold mb-3">
                        <i class="fa-solid fa-circle-info text-warning me-2"></i>Job Specification & Description
                    </h2>
                    <div class="text-light lead-sm mb-4" style="white-space: pre-line;">
                        <?= e($job['description']) ?>
                    </div>

                    <div class="row g-3 pt-3 border-top border-secondary text-muted small">
                        <div class="col-sm-6">
                            <span class="fw-bold text-light">Required Trade:</span>
                            <span class="badge bg-dark border border-secondary text-warning ms-1"><?= e($job['trade_required']) ?></span>
                        </div>
                        <div class="col-sm-6">
                            <span class="fw-bold text-light">Employment Type:</span>
                            <span class="text-light ms-1"><?= e($job['employment_type']) ?></span>
                        </div>
                        <div class="col-sm-6">
                            <span class="fw-bold text-light">Location:</span>
                            <span class="text-light ms-1"><i class="fa-solid fa-location-dot text-danger me-1"></i><?= e($job['city'] ? "{$job['location']}, {$job['city']}, {$job['state']}" : $job['location']) ?></span>
                        </div>
                        <div class="col-sm-6">
                            <span class="fw-bold text-light">Duration:</span>
                            <span class="text-light ms-1"><?= format_date($job['start_date']) ?> to <?= format_date($job['end_date']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recruitment Progress Sidebar -->
            <div class="col-lg-4">
                <div class="bc-card p-4 mb-4">
                    <h3 class="h6 text-white fw-bold mb-3">
                        <i class="fa-solid fa-chart-pie text-info me-2"></i>Recruitment Status
                    </h3>

                    <div class="p-3 bg-dark rounded-3 border border-secondary text-center mb-3">
                        <div class="display-6 font-monospace text-warning fw-bold mb-1">
                            <?= (int)$job['spots_filled'] ?> / <?= (int)$job['spots_available'] ?>
                        </div>
                        <div class="text-muted extra-small uppercase">Worker Spots Filled</div>
                        <div class="progress mt-2" style="height: 6px;">
                            <?php $percent = $job['spots_available'] > 0 ? min(100, round(($job['spots_filled'] / $job['spots_available']) * 100)) : 0; ?>
                            <div class="progress-bar bg-amber" style="width: <?= $percent ?>%"></div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between py-2 border-bottom border-secondary extra-small">
                        <span class="text-muted">Pay Rate:</span>
                        <span class="font-monospace text-warning fw-bold"><?= format_currency($job['pay_rate']) ?> / <?= e($job['pay_type']) ?></span>
                    </div>

                    <div class="d-flex justify-content-between py-2 border-bottom border-secondary extra-small">
                        <span class="text-muted">Total Applicants:</span>
                        <span class="fw-bold text-light"><?= count($applications) ?> Candidates</span>
                    </div>

                    <div class="d-flex justify-content-between py-2 extra-small">
                        <span class="text-muted">Created Date:</span>
                        <span class="text-light"><?= format_datetime($job['created_at']) ?></span>
                    </div>
                </div>

                <!-- Quick Action Status Form -->
                <div class="bc-card p-4">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-sliders text-warning me-2"></i>Manage Status</h3>
                    <form action="<?= BASE_URL ?>/contractor/jobs.php" method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="job_id" value="<?= $job_id ?>">
                        <div class="d-grid gap-2">
                            <?php if (in_array($job['status'], ['draft', 'paused'])): ?>
                                <button type="submit" name="action" value="publish" class="btn btn-success btn-sm fw-bold">
                                    <i class="fa-solid fa-play me-1"></i> Publish Job Listing
                                </button>
                            <?php elseif (in_array($job['status'], ['published', 'open'])): ?>
                                <button type="submit" name="action" value="pause" class="btn btn-warning btn-sm fw-bold text-dark">
                                    <i class="fa-solid fa-pause me-1"></i> Pause Applications
                                </button>
                                <button type="submit" name="action" value="close" class="btn btn-danger btn-sm fw-bold">
                                    <i class="fa-solid fa-lock me-1"></i> Close Vacancy
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Applicants Table Section -->
        <div class="bc-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 text-white fw-bold mb-0">
                    <i class="fa-solid fa-users text-amber me-2"></i>Received Applications (<?= count($applications) ?>)
                </h2>
                <a href="<?= BASE_URL ?>/contractor/applications.php?job_id=<?= $job_id ?>" class="btn btn-outline-info btn-sm">
                    View in Applicant Reviewer <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
            </div>

            <?php if (empty($applications)): ?>
                <div class="bc-empty-state py-4 text-center">
                    <i class="fa-solid fa-user-slash fs-2 text-muted mb-2"></i>
                    <p class="text-muted small mb-0">No workers have applied for this position yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Worker Candidate</th>
                                <th>Trade Title</th>
                                <th>AI Match Score</th>
                                <th>Applied Date</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center text-white fw-bold" style="width: 36px; height: 36px; font-size: 0.85rem;">
                                                <?= strtoupper(substr($app['worker_name'], 0, 2)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-white"><?= e($app['worker_name']) ?></div>
                                                <div class="text-muted extra-small"><?= e($app['worker_phone'] ?: $app['worker_email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small text-light"><?= e($app['trade_title'] ?: 'Construction Specialist') ?></div>
                                        <div class="extra-small text-muted"><?= (int)$app['experience_years'] ?> yrs experience</div>
                                    </td>
                                    <td>
                                        <span class="badge bg-purple text-white font-monospace" style="background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);">
                                            <i class="fa-solid fa-brain me-1"></i><?= $app['match_score'] ?>%
                                        </span>
                                    </td>
                                    <td class="text-muted extra-small">
                                        <?= format_datetime($app['applied_at']) ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= get_status_badge_class($app['status']) ?>"><?= e(ucfirst($app['status'])) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>/contractor/application-details.php?id=<?= $app['id'] ?>" class="btn btn-amber btn-sm fw-bold extra-small py-1 px-3">
                                            Review Application <i class="fa-solid fa-chevron-right ms-1"></i>
                                        </a>
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
