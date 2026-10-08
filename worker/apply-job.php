<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('worker');

$page_title = "Apply for Job - Worker - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$worker_user_id = (int)$user['id'];
$job_id = (int)($_GET['id'] ?? $_POST['job_id'] ?? 0);

$error = '';
$flash = get_flash_message();

// Fetch job details
$stmt = $db->prepare("
    SELECT j.*, p.title as project_title, c.company_name as contractor_company, u.name as contractor_name
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
                <h2 class="h4 text-white fw-bold">Job Not Found</h2>
                <p class="text-muted small mb-4">The job vacancy you are trying to apply for does not exist.</p>
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

// Server-side eligibility validation (Section 9)
if (!in_array($job['status'], ['published', 'open'])) {
    $error = "This job is currently not accepting applications (Status: " . ucfirst($job['status']) . ").";
} elseif ($job['contractor_id'] === $worker_user_id) {
    $error = "You cannot apply to your own job posting.";
} elseif ($job['spots_filled'] >= $job['spots_available']) {
    $error = "This position has already been filled.";
}

// Check duplicate application
$chk_stmt = $db->prepare("SELECT id FROM job_applications WHERE job_id = ? AND worker_id = ?");
$chk_stmt->execute([$job_id, $worker_user_id]);
if ($chk_stmt->fetch()) {
    $error = "You have already submitted an application for this job posting.";
}

// Form fields
$cover_note = '';
$expected_pay = $job['pay_rate'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security validation failed (CSRF token invalid).';
    } else {
        $cover_note = sanitize($_POST['cover_note'] ?? '');
        $expected_pay = (float)($_POST['expected_pay'] ?? $job['pay_rate']);

        if (empty($cover_note)) {
            $error = 'Please provide a brief cover note explaining your experience and availability.';
        } else {
            try {
                // Calculate basic AI fit score based on worker trade matching
                $stmt_w = $db->prepare("SELECT trade_title, experience_years FROM workers WHERE user_id = ?");
                $stmt_w->execute([$worker_user_id]);
                $w_profile = $stmt_w->fetch();

                $match_score = 80; // default base score
                if ($w_profile) {
                    if (strcasecmp($w_profile['trade_title'], $job['trade_required']) === 0) {
                        $match_score += 15;
                    }
                    if ($w_profile['experience_years'] >= 5) {
                        $match_score += 5;
                    }
                }
                $match_score = min(99, $match_score);

                // Insert application record
                $ins_stmt = $db->prepare("
                    INSERT INTO job_applications (job_id, worker_id, match_score, cover_note, expected_pay, status, applied_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW())
                ");

                $success = $ins_stmt->execute([
                    $job_id,
                    $worker_user_id,
                    $match_score,
                    $cover_note,
                    $expected_pay
                ]);

                if ($success) {
                    $app_id = $db->lastInsertId();

                    // Notification for contractor
                    create_notification(
                        $job['contractor_id'],
                        'New Job Application Received',
                        "Worker '{$user['name']}' submitted an application for '{$job['title']}'.",
                        'info',
                        "contractor/application-details.php?id={$app_id}"
                    );

                    // Activity log
                    log_activity(
                        $worker_user_id,
                        'Job Application Submitted',
                        "Submitted job application #{$app_id} for '{$job['title']}'",
                        'job_application',
                        $app_id
                    );

                    set_flash_message("Application submitted successfully.", 'success');
                    redirect('worker/applications.php');
                } else {
                    $error = 'Failed to submit application. Please try again.';
                }
            } catch (PDOException $ex) {
                if ($ex->getCode() == 23000) {
                    $error = "You have already submitted an application for this job posting.";
                } else {
                    $error = "Database Error: " . $ex->getMessage();
                }
            }
        }
    }
}
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-paper-plane text-warning me-2"></i>Apply for Job
                </h1>
                <p class="text-muted small mb-0">Submit your proposal and experience details to the contractor.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/worker/job-details.php?id=<?= $job_id ?>" class="btn btn-outline-light btn-sm fw-bold">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Details
                </a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 px-3 small rounded-3 mb-4">
                <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= sanitize($error) ?>
            </div>
        <?php endif; ?>

        <!-- Job Summary Banner -->
        <div class="bc-card p-4 mb-4 border-warning">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <span class="badge bg-dark border border-secondary text-warning extra-small mb-1"><?= e($job['trade_required']) ?></span>
                    <h2 class="h4 text-white fw-bold mb-1"><?= e($job['title']) ?></h2>
                    <p class="text-muted small mb-0">
                        Contractor: <strong class="text-info"><?= e($job['contractor_company'] ?: $job['contractor_name']) ?></strong>
                        • Location: <span class="text-light"><?= e($job['location']) ?></span>
                    </p>
                </div>
                <div class="text-end">
                    <div class="text-warning font-monospace fw-bold fs-4"><?= format_currency($job['pay_rate']) ?></div>
                    <div class="text-muted extra-small">/ <?= e($job['pay_type']) ?> (<?= e($job['employment_type']) ?>)</div>
                </div>
            </div>
        </div>

        <?php if (empty($error) || $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <form action="<?= BASE_URL ?>/worker/apply-job.php?id=<?= $job_id ?>" method="POST" class="bc-card p-4">
                <?= csrf_field() ?>
                <input type="hidden" name="job_id" value="<?= $job_id ?>">

                <div class="mb-4">
                    <label class="form-label text-light fw-bold small mb-1">Expected Rate / Compensation (INR ₹)</label>
                    <input type="number" step="0.01" min="1" name="expected_pay" value="<?= e($expected_pay) ?>" class="form-control bg-dark border-secondary text-light" style="max-width: 300px;">
                    <div class="extra-small text-muted mt-1">Contractor posted rate: <?= format_currency($job['pay_rate']) ?> / <?= e($job['pay_type']) ?></div>
                </div>

                <div class="mb-4">
                    <label class="form-label text-light fw-bold small mb-1">Cover Note / Experience Pitch <span class="text-danger">*</span></label>
                    <textarea name="cover_note" rows="5" class="form-control bg-dark border-secondary text-light" placeholder="Explain your relevant experience in <?= e($job['trade_required']) ?>, safety certifications, equipment experience, and immediate availability..." required><?= e($cover_note) ?></textarea>
                    <div class="extra-small text-muted mt-1">Your saved skills, work experience, and uploaded trade documents will automatically be attached to this application.</div>
                </div>

                <div class="d-flex justify-content-end gap-2 pt-3 border-top border-secondary">
                    <a href="<?= BASE_URL ?>/worker/job-details.php?id=<?= $job_id ?>" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-amber fw-bold px-4">
                        <i class="fa-solid fa-paper-plane me-1"></i> Submit Application
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
