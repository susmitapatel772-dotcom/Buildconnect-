<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('worker');

$page_title = "Work Experience - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$user_id = (int)$user['id'];
$flash = get_flash_message();

// Get worker profile record
$stmt = $db->prepare("SELECT id FROM workers WHERE user_id = ?");
$stmt->execute([$user_id]);
$worker = $stmt->fetch();

if (!$worker) {
    $stmt_ins = $db->prepare("INSERT INTO workers (user_id) VALUES (?)");
    $stmt_ins->execute([$user_id]);
    $worker_profile_id = (int)$db->lastInsertId();
} else {
    $worker_profile_id = (int)$worker['id'];
}

$errors = [];

// Handle Actions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = "CSRF security check failed.";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add' || $action === 'edit') {
            $exp_id = (int)($_POST['exp_id'] ?? 0);
            $job_title = sanitize($_POST['job_title'] ?? '');
            $company_name = sanitize($_POST['company_name'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $start_date = $_POST['start_date'] ?? '';
            $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
            $is_current = isset($_POST['is_current']) ? 1 : 0;

            if ($is_current) {
                $end_date = null;
            }

            if (empty($job_title)) {
                $errors[] = "Job title / role is required.";
            }
            if (empty($company_name)) {
                $errors[] = "Company / contractor name is required.";
            }
            if (empty($start_date) || !strtotime($start_date)) {
                $errors[] = "Valid start date is required.";
            }
            if (!$is_current && !empty($end_date) && strtotime($end_date) < strtotime($start_date)) {
                $errors[] = "End date cannot be earlier than start date.";
            }

            if (empty($errors)) {
                try {
                    if ($action === 'add') {
                        $stmt_ins = $db->prepare("
                            INSERT INTO worker_experience (worker_id, job_title, company_name, description, start_date, end_date, is_current)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt_ins->execute([$worker_profile_id, $job_title, $company_name, $description, $start_date, $end_date, $is_current]);

                        log_activity($user_id, 'Experience Added', "Added experience entry: {$job_title} at {$company_name}", 'experience', $db->lastInsertId());
                        set_flash_message("Work experience entry added successfully!", "success");
                    } else {
                        // Strict ownership check for UPDATE
                        $stmt_u = $db->prepare("
                            UPDATE worker_experience 
                            SET job_title = ?, company_name = ?, description = ?, start_date = ?, end_date = ?, is_current = ?, updated_at = NOW()
                            WHERE id = ? AND worker_id = ?
                        ");
                        $stmt_u->execute([$job_title, $company_name, $description, $start_date, $end_date, $is_current, $exp_id, $worker_profile_id]);

                        log_activity($user_id, 'Experience Updated', "Updated experience entry: {$job_title} at {$company_name}", 'experience', $exp_id);
                        set_flash_message("Work experience updated successfully!", "success");
                    }
                    redirect('worker/experience.php');
                } catch (PDOException $e) {
                    $errors[] = "Database error: " . $e->getMessage();
                }
            }
        } elseif ($action === 'delete') {
            $exp_id = (int)($_POST['exp_id'] ?? 0);

            if ($exp_id <= 0) {
                $errors[] = "Invalid experience record specified.";
            } else {
                try {
                    // Strict Ownership Validation for DELETE
                    $stmt_del = $db->prepare("DELETE FROM worker_experience WHERE id = ? AND worker_id = ?");
                    $stmt_del->execute([$exp_id, $worker_profile_id]);

                    if ($stmt_del->rowCount() > 0) {
                        log_activity($user_id, 'Experience Deleted', "Deleted experience entry ID {$exp_id}", 'experience', $exp_id);
                        set_flash_message("Work experience record deleted.", "info");
                        redirect('worker/experience.php');
                    } else {
                        $errors[] = "Access denied or record not found.";
                    }
                } catch (PDOException $e) {
                    $errors[] = "Error deleting experience record: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch Work Experiences for this worker only
$stmt_exp = $db->prepare("SELECT * FROM worker_experience WHERE worker_id = ? ORDER BY is_current DESC, start_date DESC");
$stmt_exp->execute([$worker_profile_id]);
$experiences = $stmt_exp->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-briefcase text-warning me-2"></i>Work Experience History
                </h1>
                <p class="text-muted small mb-0">Record past construction jobs, contractors, projects, and structural achievements.</p>
            </div>
            <div>
                <button class="btn btn-amber btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#expModal" onclick="resetExpForm();">
                    <i class="fa-solid fa-plus me-1"></i> Add Experience
                </button>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show mb-4" role="alert">
                <i class="fa-solid fa-circle-info me-2"></i> <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="bc-card p-4">
            <h2 class="h5 text-white fw-bold mb-3">
                <i class="fa-solid fa-list text-info me-2"></i>Career History Entries
            </h2>

            <?php if (empty($experiences)): ?>
                <div class="bc-empty-state py-5 text-center">
                    <i class="fa-solid fa-briefcase fs-1 text-warning mb-3"></i>
                    <h3 class="h5 text-white fw-bold">No Work Experience Added</h3>
                    <p class="text-muted small mb-3">
                        Listing your work history helps contractors gauge your site experience and assign you to major projects.
                    </p>
                    <button class="btn btn-amber btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#expModal" onclick="resetExpForm();">
                        <i class="fa-solid fa-plus me-1"></i> Add Work Experience
                    </button>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($experiences as $ex): ?>
                        <div class="bc-card p-3 border-secondary mb-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h3 class="h5 text-white fw-bold mb-1"><?= e($ex['job_title']) ?></h3>
                                    <div class="text-warning fw-semibold small mb-2">
                                        <i class="fa-solid fa-building me-1"></i><?= e($ex['company_name']) ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-dark border border-secondary text-info font-monospace extra-small">
                                        <?= format_date($ex['start_date'], 'M Y') ?> - <?= $ex['is_current'] ? '<span class="text-success fw-bold">Present</span>' : format_date($ex['end_date'], 'M Y') ?>
                                    </span>
                                </div>
                            </div>

                            <?php if (!empty($ex['description'])): ?>
                                <p class="text-light extra-small opacity-85 mt-2 mb-3"><?= nl2br(e($ex['description'])) ?></p>
                            <?php endif; ?>

                            <div class="d-flex justify-content-end gap-2 pt-2 border-top border-secondary">
                                <button type="button" class="btn btn-outline-secondary btn-sm extra-small" 
                                        onclick='editExp(<?= json_encode($ex) ?>);'
                                        data-bs-toggle="modal" data-bs-target="#expModal">
                                    <i class="fa-solid fa-pen-to-square me-1"></i> Edit
                                </button>
                                <form action="<?= BASE_URL ?>/worker/experience.php" method="POST" class="d-inline" onsubmit="return confirm('Delete this work experience entry?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="exp_id" value="<?= $ex['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm extra-small">
                                        <i class="fa-solid fa-trash me-1"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Experience Modal (Add / Edit) -->
<div class="modal fade" id="expModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-white" id="expModalTitle"><i class="fa-solid fa-briefcase me-2 text-warning"></i>Add Work Experience</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>/worker/experience.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="expAction" value="add">
                <input type="hidden" name="exp_id" id="expId" value="0">

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Job Title / Role <span class="text-danger">*</span></label>
                        <input type="text" name="job_title" id="expJobTitle" class="form-control" required placeholder="e.g. Senior Structural Welder">
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Company / Contractor Name <span class="text-danger">*</span></label>
                        <input type="text" name="company_name" id="expCompanyName" class="form-control" required placeholder="e.g. Gujarat Infra Steel Corp">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small fw-semibold">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" id="expStartDate" class="form-control" required>
                        </div>
                        <div class="col-6" id="endDateCol">
                            <label class="form-label text-muted small fw-semibold">End Date</label>
                            <input type="date" name="end_date" id="expEndDate" class="form-control">
                        </div>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_current" id="expIsCurrent" onchange="toggleEndDate(this.checked);">
                        <label class="form-check-label text-light small" for="expIsCurrent">
                            I currently work here
                        </label>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Responsibilities & Achievements</label>
                        <textarea name="description" id="expDescription" class="form-control" rows="3" placeholder="Describe key construction projects, tools operated, and structural responsibilities..."></textarea>
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-amber btn-sm fw-bold" id="expSubmitBtn"><i class="fa-solid fa-save me-1"></i> Save Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetExpForm() {
    document.getElementById('expModalTitle').innerHTML = '<i class="fa-solid fa-briefcase me-2 text-warning"></i>Add Work Experience';
    document.getElementById('expAction').value = 'add';
    document.getElementById('expId').value = '0';
    document.getElementById('expJobTitle').value = '';
    document.getElementById('expCompanyName').value = '';
    document.getElementById('expStartDate').value = '';
    document.getElementById('expEndDate').value = '';
    document.getElementById('expIsCurrent').checked = false;
    document.getElementById('expDescription').value = '';
    toggleEndDate(false);
}

function editExp(exp) {
    document.getElementById('expModalTitle').innerHTML = '<i class="fa-solid fa-pen-to-square me-2 text-warning"></i>Edit Work Experience';
    document.getElementById('expAction').value = 'edit';
    document.getElementById('expId').value = exp.id;
    document.getElementById('expJobTitle').value = exp.job_title;
    document.getElementById('expCompanyName').value = exp.company_name;
    document.getElementById('expStartDate').value = exp.start_date;
    document.getElementById('expEndDate').value = exp.end_date || '';
    document.getElementById('expIsCurrent').checked = exp.is_current == 1;
    document.getElementById('expDescription').value = exp.description || '';
    toggleEndDate(exp.is_current == 1);
}

function toggleEndDate(isCurrent) {
    const endDateCol = document.getElementById('endDateCol');
    const endDateInput = document.getElementById('expEndDate');
    if (isCurrent) {
        endDateCol.style.opacity = '0.4';
        endDateInput.disabled = true;
    } else {
        endDateCol.style.opacity = '1';
        endDateInput.disabled = false;
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
