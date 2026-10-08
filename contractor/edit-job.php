<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('contractor');

$page_title = "Edit Trade Job - Contractor - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$contractor_id = (int)$user['id'];

$job_id = (int)($_GET['id'] ?? 0);
$error = '';
$flash = get_flash_message();

// Verify job exists and belongs to authenticated contractor strictly
$stmt = $db->prepare("SELECT * FROM jobs WHERE id = ? AND contractor_id = ?");
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
                <p class="text-muted small mb-4">You do not have permission to view or edit this job posting.</p>
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

// Fetch contractor projects
$stmt_p = $db->prepare("SELECT id, title, location FROM projects WHERE contractor_id = ? ORDER BY id DESC");
$stmt_p->execute([$contractor_id]);
$projects = $stmt_p->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security validation failed (CSRF token invalid).';
    } else {
        $title = sanitize($_POST['title'] ?? '');
        $project_id = (int)($_POST['project_id'] ?? 0);
        $trade_required = sanitize($_POST['trade_required'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $pay_rate = (float)($_POST['pay_rate'] ?? 0);
        $pay_type = sanitize($_POST['pay_type'] ?? 'hourly');
        $location = sanitize($_POST['location'] ?? '');
        $city = sanitize($_POST['city'] ?? '');
        $state = sanitize($_POST['state'] ?? '');
        $employment_type = sanitize($_POST['employment_type'] ?? 'Full-Time');
        $spots_available = (int)($_POST['spots_available'] ?? 1);
        $start_date = sanitize($_POST['start_date'] ?? '');
        $end_date = sanitize($_POST['end_date'] ?? '');
        $status = sanitize($_POST['status'] ?? 'published');

        if (empty($title)) {
            $error = 'Job title is required.';
        } elseif ($project_id <= 0) {
            $error = 'Please select a valid project.';
        } elseif (empty($trade_required)) {
            $error = 'Required trade or skill category is required.';
        } elseif (empty($description)) {
            $error = 'Job description is required.';
        } elseif ($pay_rate <= 0) {
            $error = 'Pay rate must be greater than zero.';
        } elseif ($spots_available < $job['spots_filled']) {
            $error = "Workers required cannot be less than currently filled spots ({$job['spots_filled']}).";
        } elseif (!in_array($pay_type, ['hourly', 'daily', 'fixed'])) {
            $error = 'Invalid payment type selected.';
        } elseif (!in_array($status, ['draft', 'published', 'open', 'paused', 'closed', 'cancelled'])) {
            $error = 'Invalid status selected.';
        } else {
            // Verify project belongs to current contractor
            $stmt_pv = $db->prepare("SELECT id FROM projects WHERE id = ? AND contractor_id = ?");
            $stmt_pv->execute([$project_id, $contractor_id]);
            if (!$stmt_pv->fetch()) {
                $error = 'Access Denied: Selected project does not belong to your contractor account.';
            } else {
                if (!empty($start_date) && !empty($end_date) && strtotime($end_date) < strtotime($start_date)) {
                    $error = 'End date cannot be prior to start date.';
                } else {
                    $update_stmt = $db->prepare("
                        UPDATE jobs SET 
                            project_id = ?, title = ?, trade_required = ?, description = ?, 
                            pay_rate = ?, pay_type = ?, location = ?, city = ?, state = ?, 
                            employment_type = ?, start_date = ?, end_date = ?, spots_available = ?, 
                            status = ?, updated_at = NOW()
                        WHERE id = ? AND contractor_id = ?
                    ");

                    $success = $update_stmt->execute([
                        $project_id,
                        $title,
                        $trade_required,
                        $description,
                        $pay_rate,
                        $pay_type,
                        $location ?: $city,
                        $city,
                        $state,
                        $employment_type,
                        $start_date ?: null,
                        $end_date ?: null,
                        $spots_available,
                        $status,
                        $job_id,
                        $contractor_id
                    ]);

                    if ($success) {
                        log_activity($contractor_id, 'Job Updated', "Updated job posting #{$job_id} ('{$title}')", 'job', $job_id);
                        set_flash_message("Job posting '{$title}' updated successfully.", 'success');
                        redirect("contractor/job-details.php?id={$job_id}");
                    } else {
                        $error = 'Failed to update job posting due to a database error.';
                    }
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
                    <i class="fa-solid fa-pen-to-square text-warning me-2"></i>Edit Job Posting #<?= $job_id ?>
                </h1>
                <p class="text-muted small mb-0">Modify trade position requirements, pay terms, or availability status.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/contractor/job-details.php?id=<?= $job_id ?>" class="btn btn-outline-light btn-sm fw-bold">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Details
                </a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 px-3 small rounded-3 mb-4">
                <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= sanitize($error) ?>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>/contractor/edit-job.php?id=<?= $job_id ?>" method="POST" class="bc-card p-4">
            <?= csrf_field() ?>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label text-light fw-bold small mb-1">Associated Project <span class="text-danger">*</span></label>
                    <select name="project_id" class="form-select bg-dark border-secondary text-light" required>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $job['project_id'] == $p['id'] ? 'selected' : '' ?>>
                                <?= e($p['title']) ?> (<?= e($p['location']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label text-light fw-bold small mb-1">Job Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" value="<?= e($job['title']) ?>" class="form-control bg-dark border-secondary text-light" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label text-light fw-bold small mb-1">Required Trade / Skill <span class="text-danger">*</span></label>
                    <select name="trade_required" class="form-select bg-dark border-secondary text-light" required>
                        <option value="Structural Welding" <?= $job['trade_required'] === 'Structural Welding' ? 'selected' : '' ?>>Structural Welding</option>
                        <option value="Tower Crane Operation" <?= $job['trade_required'] === 'Tower Crane Operation' ? 'selected' : '' ?>>Tower Crane Operation</option>
                        <option value="Commercial Electrical Wiring" <?= $job['trade_required'] === 'Commercial Electrical Wiring' ? 'selected' : '' ?>>Commercial Electrical Wiring</option>
                        <option value="Concrete Formwork & Masonry" <?= $job['trade_required'] === 'Concrete Formwork & Masonry' ? 'selected' : '' ?>>Concrete Formwork & Masonry</option>
                        <option value="Plumbing & High Pressure Piping" <?= $job['trade_required'] === 'Plumbing & High Pressure Piping' ? 'selected' : '' ?>>Plumbing & High Pressure Piping</option>
                        <option value="Carpentry & Framing" <?= $job['trade_required'] === 'Carpentry & Framing' ? 'selected' : '' ?>>Carpentry & Framing</option>
                        <option value="General Construction" <?= $job['trade_required'] === 'General Construction' ? 'selected' : '' ?>>General Construction</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label text-light fw-bold small mb-1">Employment Type</label>
                    <select name="employment_type" class="form-select bg-dark border-secondary text-light">
                        <option value="Full-Time" <?= $job['employment_type'] === 'Full-Time' ? 'selected' : '' ?>>Full-Time</option>
                        <option value="Part-Time" <?= $job['employment_type'] === 'Part-Time' ? 'selected' : '' ?>>Part-Time</option>
                        <option value="Contract" <?= $job['employment_type'] === 'Contract' ? 'selected' : '' ?>>Contract</option>
                        <option value="Day Labor" <?= $job['employment_type'] === 'Day Labor' ? 'selected' : '' ?>>Day Labor</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label text-light fw-bold small mb-1">Pay Rate (INR ₹) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="1" name="pay_rate" value="<?= e($job['pay_rate']) ?>" class="form-control bg-dark border-secondary text-light" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label text-light fw-bold small mb-1">Pay Frequency</label>
                    <select name="pay_type" class="form-select bg-dark border-secondary text-light">
                        <option value="hourly" <?= $job['pay_type'] === 'hourly' ? 'selected' : '' ?>>Hourly (₹/hr)</option>
                        <option value="daily" <?= $job['pay_type'] === 'daily' ? 'selected' : '' ?>>Daily Rate (₹/day)</option>
                        <option value="fixed" <?= $job['pay_type'] === 'fixed' ? 'selected' : '' ?>>Fixed Contract Lump-sum</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label text-light fw-bold small mb-1">Workers Required <span class="text-danger">*</span></label>
                    <input type="number" min="<?= max(1, (int)$job['spots_filled']) ?>" max="100" name="spots_available" value="<?= e($job['spots_available']) ?>" class="form-control bg-dark border-secondary text-light" required>
                    <div class="extra-small text-muted mt-1">Currently filled: <?= (int)$job['spots_filled'] ?> positions</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label text-light fw-bold small mb-1">Specific Site Address / Area</label>
                    <input type="text" name="location" value="<?= e($job['location']) ?>" class="form-control bg-dark border-secondary text-light">
                </div>

                <div class="col-md-3">
                    <label class="form-label text-light fw-bold small mb-1">City</label>
                    <input type="text" name="city" value="<?= e($job['city']) ?>" class="form-control bg-dark border-secondary text-light">
                </div>

                <div class="col-md-3">
                    <label class="form-label text-light fw-bold small mb-1">State</label>
                    <input type="text" name="state" value="<?= e($job['state']) ?>" class="form-control bg-dark border-secondary text-light">
                </div>

                <div class="col-md-6">
                    <label class="form-label text-light fw-bold small mb-1">Estimated Start Date</label>
                    <input type="date" name="start_date" value="<?= e($job['start_date']) ?>" class="form-control bg-dark border-secondary text-light">
                </div>

                <div class="col-md-6">
                    <label class="form-label text-light fw-bold small mb-1">Estimated End Date</label>
                    <input type="date" name="end_date" value="<?= e($job['end_date']) ?>" class="form-control bg-dark border-secondary text-light">
                </div>

                <div class="col-12">
                    <label class="form-label text-light fw-bold small mb-1">Job Description & Responsibilities <span class="text-danger">*</span></label>
                    <textarea name="description" rows="4" class="form-control bg-dark border-secondary text-light" required><?= e($job['description']) ?></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label text-light fw-bold small mb-1">Status</label>
                    <select name="status" class="form-select bg-dark border-secondary text-light">
                        <option value="published" <?= in_array($job['status'], ['published', 'open']) ? 'selected' : '' ?>>Published / Open for Applications</option>
                        <option value="draft" <?= $job['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="paused" <?= $job['status'] === 'paused' ? 'selected' : '' ?>>Paused (Not accepting applications)</option>
                        <option value="closed" <?= $job['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                        <option value="cancelled" <?= $job['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top border-secondary">
                <a href="<?= BASE_URL ?>/contractor/job-details.php?id=<?= $job_id ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-warning fw-bold px-4">
                    <i class="fa-solid fa-save me-1"></i> Update Job Posting
                </button>
            </div>
        </form>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
