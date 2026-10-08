<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('contractor');

$page_title = "Post New Job - Contractor - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$contractor_id = (int)$user['id'];

$error = '';
$flash = get_flash_message();

// Fetch projects owned by contractor
$stmt_p = $db->prepare("SELECT id, title, location FROM projects WHERE contractor_id = ? ORDER BY id DESC");
$stmt_p->execute([$contractor_id]);
$projects = $stmt_p->fetchAll();

// Pre-selected project ID if passed via GET
$selected_project_id = (int)($_GET['project_id'] ?? 0);

// Form defaults
$title = '';
$project_id = $selected_project_id;
$trade_required = '';
$description = '';
$pay_rate = '';
$pay_type = 'hourly';
$location = '';
$city = 'Ahmedabad';
$state = 'Gujarat';
$employment_type = 'Full-Time';
$spots_available = 1;
$start_date = date('Y-m-d');
$end_date = '';
$status = 'published';

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

        // Validation
        if (empty($title)) {
            $error = 'Job title is required.';
        } elseif ($project_id <= 0) {
            $error = 'Please select a valid project for this job posting.';
        } elseif (empty($trade_required)) {
            $error = 'Required trade or skill category is required.';
        } elseif (empty($description)) {
            $error = 'Job description is required.';
        } elseif ($pay_rate <= 0) {
            $error = 'Pay rate must be greater than zero.';
        } elseif ($spots_available < 1) {
            $error = 'Workers required must be at least 1.';
        } elseif (!in_array($pay_type, ['hourly', 'daily', 'fixed'])) {
            $error = 'Invalid payment type selected.';
        } elseif (!in_array($status, ['draft', 'published'])) {
            $error = 'Invalid status selected.';
        } else {
            // Verify project belongs to current contractor
            $stmt_pv = $db->prepare("SELECT id FROM projects WHERE id = ? AND contractor_id = ?");
            $stmt_pv->execute([$project_id, $contractor_id]);
            if (!$stmt_pv->fetch()) {
                $error = 'Access Denied: The selected project does not belong to your contractor account.';
            } else {
                if (!empty($start_date) && !empty($end_date) && strtotime($end_date) < strtotime($start_date)) {
                    $error = 'End date cannot be prior to the start date.';
                } else {
                    $insert_stmt = $db->prepare("
                        INSERT INTO jobs (
                            project_id, contractor_id, title, trade_required, description, 
                            pay_rate, pay_type, location, city, state, employment_type, 
                            start_date, end_date, spots_available, spots_filled, status, created_at, updated_at
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NOW(), NOW()
                        )
                    ");

                    $success = $insert_stmt->execute([
                        $project_id,
                        $contractor_id,
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
                        $status
                    ]);

                    if ($success) {
                        $job_id = $db->lastInsertId();
                        log_activity($contractor_id, 'Job Created', "Created trade job posting #{$job_id} ('{$title}')", 'job', $job_id);
                        set_flash_message("Trade job posting '{$title}' created successfully.", 'success');
                        redirect('contractor/jobs.php');
                    } else {
                        $error = 'Failed to create job posting due to a database error.';
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
                    <i class="fa-solid fa-plus-circle text-amber me-2"></i>Post New Trade Job
                </h1>
                <p class="text-muted small mb-0">Create a recruitment vacancy for skilled trade workers on your construction project.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/contractor/jobs.php" class="btn btn-outline-light btn-sm fw-bold">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Jobs
                </a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 px-3 small rounded-3 mb-4">
                <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= sanitize($error) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($projects)): ?>
            <div class="bc-card p-5 text-center my-4">
                <i class="fa-solid fa-building-circle-exclamation text-warning fs-1 mb-3"></i>
                <h3 class="h5 text-white fw-bold">No Active Projects Found</h3>
                <p class="text-muted small mb-4">You must create at least one project before you can post trade job vacancies.</p>
                <a href="<?= BASE_URL ?>/contractor/create-project.php" class="btn btn-amber fw-bold btn-sm">
                    <i class="fa-solid fa-plus me-1"></i> Create Project First
                </a>
            </div>
        <?php else: ?>
            <form action="<?= BASE_URL ?>/contractor/create-job.php" method="POST" class="bc-card p-4">
                <?= csrf_field() ?>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label text-light fw-bold small mb-1">Associated Project <span class="text-danger">*</span></label>
                        <select name="project_id" class="form-select bg-dark border-secondary text-light" required>
                            <option value="">-- Select Project --</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $project_id == $p['id'] ? 'selected' : '' ?>>
                                    <?= e($p['title']) ?> (<?= e($p['location']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-light fw-bold small mb-1">Job Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" value="<?= e($title) ?>" class="form-control bg-dark border-secondary text-light" placeholder="e.g. Senior Structural Welder" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-light fw-bold small mb-1">Required Trade / Skill <span class="text-danger">*</span></label>
                        <select name="trade_required" class="form-select bg-dark border-secondary text-light" required>
                            <option value="">-- Select Trade --</option>
                            <option value="Structural Welding" <?= $trade_required === 'Structural Welding' ? 'selected' : '' ?>>Structural Welding</option>
                            <option value="Tower Crane Operation" <?= $trade_required === 'Tower Crane Operation' ? 'selected' : '' ?>>Tower Crane Operation</option>
                            <option value="Commercial Electrical Wiring" <?= $trade_required === 'Commercial Electrical Wiring' ? 'selected' : '' ?>>Commercial Electrical Wiring</option>
                            <option value="Concrete Formwork & Masonry" <?= $trade_required === 'Concrete Formwork & Masonry' ? 'selected' : '' ?>>Concrete Formwork & Masonry</option>
                            <option value="Plumbing & High Pressure Piping" <?= $trade_required === 'Plumbing & High Pressure Piping' ? 'selected' : '' ?>>Plumbing & High Pressure Piping</option>
                            <option value="Carpentry & Framing" <?= $trade_required === 'Carpentry & Framing' ? 'selected' : '' ?>>Carpentry & Framing</option>
                            <option value="General Construction" <?= $trade_required === 'General Construction' ? 'selected' : '' ?>>General Construction</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-light fw-bold small mb-1">Employment Type</label>
                        <select name="employment_type" class="form-select bg-dark border-secondary text-light">
                            <option value="Full-Time" <?= $employment_type === 'Full-Time' ? 'selected' : '' ?>>Full-Time</option>
                            <option value="Part-Time" <?= $employment_type === 'Part-Time' ? 'selected' : '' ?>>Part-Time</option>
                            <option value="Contract" <?= $employment_type === 'Contract' ? 'selected' : '' ?>>Contract</option>
                            <option value="Day Labor" <?= $employment_type === 'Day Labor' ? 'selected' : '' ?>>Day Labor</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-light fw-bold small mb-1">Pay Rate (INR ₹) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="1" name="pay_rate" value="<?= e($pay_rate) ?>" class="form-control bg-dark border-secondary text-light" placeholder="e.g. 45.00" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-light fw-bold small mb-1">Pay Frequency</label>
                        <select name="pay_type" class="form-select bg-dark border-secondary text-light">
                            <option value="hourly" <?= $pay_type === 'hourly' ? 'selected' : '' ?>>Hourly (₹/hr)</option>
                            <option value="daily" <?= $pay_type === 'daily' ? 'selected' : '' ?>>Daily Rate (₹/day)</option>
                            <option value="fixed" <?= $pay_type === 'fixed' ? 'selected' : '' ?>>Fixed Contract Lump-sum</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-light fw-bold small mb-1">Workers Required <span class="text-danger">*</span></label>
                        <input type="number" min="1" max="100" name="spots_available" value="<?= e($spots_available) ?>" class="form-control bg-dark border-secondary text-light" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-light fw-bold small mb-1">Specific Site Address / Area</label>
                        <input type="text" name="location" value="<?= e($location) ?>" class="form-control bg-dark border-secondary text-light" placeholder="e.g. Bodakdev, SG Highway">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-light fw-bold small mb-1">City</label>
                        <input type="text" name="city" value="<?= e($city) ?>" class="form-control bg-dark border-secondary text-light">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-light fw-bold small mb-1">State</label>
                        <input type="text" name="state" value="<?= e($state) ?>" class="form-control bg-dark border-secondary text-light">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-light fw-bold small mb-1">Estimated Start Date</label>
                        <input type="date" name="start_date" value="<?= e($start_date) ?>" class="form-control bg-dark border-secondary text-light">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-light fw-bold small mb-1">Estimated End Date</label>
                        <input type="date" name="end_date" value="<?= e($end_date) ?>" class="form-control bg-dark border-secondary text-light">
                    </div>

                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label text-light fw-bold small mb-0">Job Description & Responsibilities <span class="text-danger">*</span></label>
                            <button type="button" class="btn btn-outline-warning btn-sm extra-small" onclick="improveWithAI()">
                                <i class="fa-solid fa-wand-magic-sparkles me-1"></i> Improve with AI
                            </button>
                        </div>
                        <textarea id="job_description_field" name="description" rows="4" class="form-control bg-dark border-secondary text-light" placeholder="Describe the scope of work, required certifications, physical requirements, and site conditions..." required><?= e($description) ?></textarea>
                    </div>

                    <!-- AI Suggestion Review Container -->
                    <div id="ai_suggestion_box" class="col-12" style="display: none;">
                        <div class="alert alert-dark border-warning p-3 rounded-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold text-warning small"><i class="fa-solid fa-brain me-1"></i> AI Job Description Suggestion</span>
                                <button type="button" class="btn-close btn-close-white" onclick="document.getElementById('ai_suggestion_box').style.display='none'"></button>
                            </div>
                            <div id="ai_suggestion_content" class="small text-light mb-3"></div>
                            <button type="button" class="btn btn-warning btn-sm extra-small fw-bold" onclick="applyAISuggestion()">
                                <i class="fa-solid fa-check me-1"></i> Use Suggestion
                            </button>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-light fw-bold small mb-1">Posting Status</label>
                        <select name="status" class="form-select bg-dark border-secondary text-light">
                            <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Publish Immediately (Visible to Workers)</option>
                            <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Save as Draft (Private)</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 pt-3 border-top border-secondary">
                    <a href="<?= BASE_URL ?>/contractor/jobs.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-amber fw-bold px-4">
                        <i class="fa-solid fa-check me-1"></i> Save Job Posting
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </main>
</div>

<script>
let lastAISuggestionText = '';

function improveWithAI() {
    const title = document.querySelector('input[name="title"]').value.trim();
    const trade = document.querySelector('select[name="trade_required"]').value;
    const desc = document.getElementById('job_description_field').value.trim();

    if (!title) {
        alert('Please enter a Job Title before requesting AI improvement.');
        return;
    }

    const box = document.getElementById('ai_suggestion_box');
    const content = document.getElementById('ai_suggestion_content');
    box.style.display = 'block';
    content.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Generating AI enhanced job description...';

    const url = '<?= BASE_URL ?>/api/ai.php?action=improve_job_description&title=' + encodeURIComponent(title) + '&skills=' + encodeURIComponent(trade) + '&description=' + encodeURIComponent(desc);

    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                const res = data.data;
                let text = res.description;
                if (res.responsibilities && res.responsibilities.length) {
                    text += "\n\nKey Responsibilities:\n• " + res.responsibilities.join("\n• ");
                }
                if (res.requirements && res.requirements.length) {
                    text += "\n\nJob Requirements:\n• " + res.requirements.join("\n• ");
                }
                lastAISuggestionText = text;
                content.innerText = text;
            } else {
                content.innerHTML = '<span class="text-danger">AI analysis is temporarily unavailable. You can continue typing manually.</span>';
            }
        })
        .catch(err => {
            content.innerHTML = '<span class="text-danger">AI service request timed out. You can continue typing manually.</span>';
        });
}

function applyAISuggestion() {
    if (lastAISuggestionText) {
        document.getElementById('job_description_field').value = lastAISuggestionText;
        document.getElementById('ai_suggestion_box').style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
