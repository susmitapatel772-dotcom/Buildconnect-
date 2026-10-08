<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('contractor');

$page_title = "Manage Jobs - Contractor - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$contractor_id = (int)$user['id'];

$error = '';
$flash = get_flash_message();

// Handle Status Change Actions (Publish, Pause, Close)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security validation failed (CSRF token invalid).';
    } else {
        $job_id = (int)($_POST['job_id'] ?? 0);
        $action = sanitize($_POST['action'] ?? '');

        // Verify contractor owns the job
        $stmt_check = $db->prepare("SELECT id, title, status FROM jobs WHERE id = ? AND contractor_id = ?");
        $stmt_check->execute([$job_id, $contractor_id]);
        $target_job = $stmt_check->fetch();

        if (!$target_job) {
            $error = 'Access Denied: Job not found or ownership mismatch.';
        } else {
            $new_status = null;
            if ($action === 'publish') {
                $new_status = 'published';
            } elseif ($action === 'pause') {
                $new_status = 'paused';
            } elseif ($action === 'close') {
                $new_status = 'closed';
            }

            if ($new_status) {
                $stmt_up = $db->prepare("UPDATE jobs SET status = ?, updated_at = NOW() WHERE id = ? AND contractor_id = ?");
                $stmt_up->execute([$new_status, $job_id, $contractor_id]);

                log_activity($contractor_id, 'Job Status Updated', "Updated job #{$job_id} ('{$target_job['title']}') status to {$new_status}", 'job', $job_id);
                set_flash_message("Job '{$target_job['title']}' status updated to '" . ucfirst($new_status) . "'.", 'success');
                redirect('contractor/jobs.php');
            }
        }
    }
}

// Status Filter
$status_filter = sanitize($_GET['status'] ?? 'all');
$valid_statuses = ['draft', 'published', 'open', 'paused', 'closed', 'filled', 'cancelled'];

$sql = "
    SELECT j.*, p.title as project_title, p.id as project_id,
           (SELECT COUNT(*) FROM job_applications ja WHERE ja.job_id = j.id) as app_count
    FROM jobs j 
    JOIN projects p ON j.project_id = p.id 
    WHERE j.contractor_id = ? 
";
$params = [$contractor_id];

if (in_array($status_filter, $valid_statuses)) {
    if ($status_filter === 'published') {
        $sql .= " AND j.status IN ('published', 'open')";
    } else {
        $sql .= " AND j.status = ?";
        $params[] = $status_filter;
    }
}

$sql .= " ORDER BY j.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

// Counts for filter pills
$counts_stmt = $db->prepare("
    SELECT status, COUNT(*) as count 
    FROM jobs 
    WHERE contractor_id = ? 
    GROUP BY status
");
$counts_stmt->execute([$contractor_id]);
$raw_counts = $counts_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$total_count = array_sum($raw_counts);
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-briefcase text-warning me-2"></i>My Trade Job Postings
                </h1>
                <p class="text-muted small mb-0">Manage project trade vacancies, publish listings, and review applicant positions.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/contractor/create-job.php" class="btn btn-amber fw-bold py-2 px-3">
                    <i class="fa-solid fa-plus me-1"></i> Post New Job
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

        <!-- Filter Nav Tabs -->
        <div class="bc-card p-2 mb-4">
            <div class="nav nav-pills gap-1">
                <a href="<?= BASE_URL ?>/contractor/jobs.php?status=all" class="nav-link <?= $status_filter === 'all' ? 'active bg-amber text-dark fw-bold' : 'text-light' ?> py-1 px-3 extra-small">
                    All Jobs (<?= $total_count ?>)
                </a>
                <a href="<?= BASE_URL ?>/contractor/jobs.php?status=published" class="nav-link <?= $status_filter === 'published' ? 'active bg-amber text-dark fw-bold' : 'text-light' ?> py-1 px-3 extra-small">
                    Published (<?= ($raw_counts['published'] ?? 0) + ($raw_counts['open'] ?? 0) ?>)
                </a>
                <a href="<?= BASE_URL ?>/contractor/jobs.php?status=draft" class="nav-link <?= $status_filter === 'draft' ? 'active bg-amber text-dark fw-bold' : 'text-light' ?> py-1 px-3 extra-small">
                    Drafts (<?= $raw_counts['draft'] ?? 0 ?>)
                </a>
                <a href="<?= BASE_URL ?>/contractor/jobs.php?status=paused" class="nav-link <?= $status_filter === 'paused' ? 'active bg-amber text-dark fw-bold' : 'text-light' ?> py-1 px-3 extra-small">
                    Paused (<?= $raw_counts['paused'] ?? 0 ?>)
                </a>
                <a href="<?= BASE_URL ?>/contractor/jobs.php?status=closed" class="nav-link <?= $status_filter === 'closed' ? 'active bg-amber text-dark fw-bold' : 'text-light' ?> py-1 px-3 extra-small">
                    Closed / Filled (<?= ($raw_counts['closed'] ?? 0) + ($raw_counts['filled'] ?? 0) ?>)
                </a>
            </div>
        </div>

        <div class="bc-card p-4">
            <?php if (empty($jobs)): ?>
                <div class="bc-empty-state py-5 text-center">
                    <i class="fa-solid fa-folder-open fs-1 text-muted mb-3"></i>
                    <h3 class="h5 text-white fw-bold">No Job Postings Found</h3>
                    <p class="text-muted small mb-3">
                        <?= $status_filter !== 'all' ? 'No jobs found matching the selected status filter.' : 'You have not created any trade job vacancies yet.' ?>
                    </p>
                    <a href="<?= BASE_URL ?>/contractor/create-job.php" class="btn btn-amber btn-sm fw-bold">
                        <i class="fa-solid fa-plus me-1"></i> Create First Job
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Job Title & Trade</th>
                                <th>Associated Project</th>
                                <th>Compensation</th>
                                <th>Location</th>
                                <th>Positions Filled</th>
                                <th>Applicants</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jobs as $j): ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_URL ?>/contractor/job-details.php?id=<?= $j['id'] ?>" class="fw-bold text-white text-decoration-none hover-amber">
                                            <?= e($j['title']) ?>
                                        </a>
                                        <div class="mt-1">
                                            <span class="badge bg-dark border border-secondary text-warning extra-small"><?= e($j['trade_required']) ?></span>
                                            <span class="badge bg-secondary extra-small ms-1"><?= e($j['employment_type']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/contractor/project-details.php?id=<?= $j['project_id'] ?>" class="text-info text-decoration-none fw-semibold small">
                                            <i class="fa-solid fa-building me-1"></i><?= e($j['project_title']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="text-warning font-monospace fw-bold"><?= format_currency($j['pay_rate']) ?></span>
                                        <span class="text-muted extra-small">/ <?= e($j['pay_type']) ?></span>
                                    </td>
                                    <td>
                                        <span class="text-muted small"><i class="fa-solid fa-location-dot text-danger me-1"></i><?= e($j['city'] ? "{$j['city']}, {$j['state']}" : $j['location']) ?></span>
                                    </td>
                                    <td>
                                        <span class="font-monospace text-light small"><?= (int)$j['spots_filled'] ?> / <?= (int)$j['spots_available'] ?></span>
                                        <?php if ($j['spots_filled'] >= $j['spots_available']): ?>
                                            <span class="badge bg-danger ms-1 extra-small">Filled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/contractor/applications.php?job_id=<?= $j['id'] ?>" class="badge bg-primary text-white text-decoration-none py-1 px-2">
                                            <i class="fa-solid fa-users me-1"></i><?= (int)$j['app_count'] ?> Applicants
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge <?= get_status_badge_class($j['status']) ?>"><?= e(ucfirst($j['status'])) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= BASE_URL ?>/contractor/job-details.php?id=<?= $j['id'] ?>" class="btn btn-outline-light" title="View Details">
                                                <i class="fa-solid fa-eye text-info"></i>
                                            </a>
                                            <a href="<?= BASE_URL ?>/contractor/edit-job.php?id=<?= $j['id'] ?>" class="btn btn-outline-light" title="Edit Job">
                                                <i class="fa-solid fa-pen text-warning"></i>
                                            </a>

                                            <!-- Toggle Status Dropdown -->
                                            <form action="<?= BASE_URL ?>/contractor/jobs.php" method="POST" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="job_id" value="<?= $j['id'] ?>">
                                                <?php if (in_array($j['status'], ['draft', 'paused'])): ?>
                                                    <button type="submit" name="action" value="publish" class="btn btn-outline-success" title="Publish Job">
                                                        <i class="fa-solid fa-play"></i>
                                                    </button>
                                                <?php elseif (in_array($j['status'], ['published', 'open'])): ?>
                                                    <button type="submit" name="action" value="pause" class="btn btn-outline-warning" title="Pause Applications">
                                                        <i class="fa-solid fa-pause"></i>
                                                    </button>
                                                    <button type="submit" name="action" value="close" class="btn btn-outline-danger" title="Close Job">
                                                        <i class="fa-solid fa-lock"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </form>
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
