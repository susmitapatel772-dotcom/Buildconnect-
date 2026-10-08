<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('contractor');

$page_title = "Application Review & Hiring - Contractor - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$contractor_id = (int)$user['id'];

$app_id = (int)($_GET['id'] ?? 0);
$error = '';
$flash = get_flash_message();

// Fetch application + job + project details + worker info with strict contractor ownership validation
$stmt = $db->prepare("
    SELECT ja.*, 
           j.id as job_id, j.title as job_title, j.trade_required, j.spots_available, j.spots_filled, j.status as job_status, j.pay_rate, j.pay_type, j.project_id,
           p.title as project_title, p.location as project_location,
           u.id as worker_user_id, u.name as worker_name, u.email as worker_email, u.phone as worker_phone, u.avatar as worker_avatar,
           w.trade_title as worker_trade, w.experience_years, w.bio as worker_bio, w.city as worker_city, w.state as worker_state,
           w.availability_status, w.verification_status, w.rating_avg, w.reviews_count
    FROM job_applications ja
    JOIN jobs j ON ja.job_id = j.id
    JOIN projects p ON j.project_id = p.id
    JOIN users u ON ja.worker_id = u.id
    LEFT JOIN workers w ON u.id = w.user_id
    WHERE ja.id = ? AND j.contractor_id = ?
");
$stmt->execute([$app_id, $contractor_id]);
$app = $stmt->fetch();

if (!$app) {
    ?>
    <div class="bc-layout">
        <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="bc-main-content">
            <div class="bc-card p-5 text-center my-5 border-danger">
                <i class="fa-solid fa-lock text-danger fs-1 mb-3"></i>
                <h2 class="h4 text-white fw-bold">Access Denied</h2>
                <p class="text-muted small mb-4">The requested application does not exist or belongs to another contractor.</p>
                <a href="<?= BASE_URL ?>/contractor/applications.php" class="btn btn-amber btn-sm fw-bold">
                    <i class="fa-solid fa-arrow-left me-1"></i> Return to Applications
                </a>
            </div>
        </main>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Fetch worker skills
$stmt_sk = $db->prepare("
    SELECT s.name, ws.proficiency_level 
    FROM worker_skills ws 
    JOIN skills s ON ws.skill_id = s.id 
    WHERE ws.worker_id = (SELECT id FROM workers WHERE user_id = ?)
");
$stmt_sk->execute([$app['worker_user_id']]);
$worker_skills = $stmt_sk->fetchAll();

// Fetch worker experience records
$stmt_exp = $db->prepare("
    SELECT * FROM worker_experience 
    WHERE worker_id = (SELECT id FROM workers WHERE user_id = ?)
    ORDER BY is_current DESC, start_date DESC
");
$stmt_exp->execute([$app['worker_user_id']]);
$worker_experiences = $stmt_exp->fetchAll();

// Handle Hiring / Shortlisting / Rejection Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security validation failed (CSRF token invalid).';
    } else {
        $action = sanitize($_POST['action'] ?? '');

        if ($action === 'accept') {
            // Check available positions
            if ($app['spots_filled'] >= $app['spots_available']) {
                $error = 'Position Filled: All worker positions for this job posting have already been filled.';
            } elseif (!in_array($app['status'], ['pending', 'shortlisted'])) {
                $error = "Cannot accept an application that is currently '{$app['status']}'.";
            } else {
                // Execute TRANSACTION SAFE hiring process
                try {
                    $db->beginTransaction();

                    // 1. Double check spot availability inside transaction
                    $t_stmt = $db->prepare("SELECT spots_available, spots_filled FROM jobs WHERE id = ? FOR UPDATE");
                    $t_stmt->execute([$app['job_id']]);
                    $job_fresh = $t_stmt->fetch();

                    if ($job_fresh['spots_filled'] >= $job_fresh['spots_available']) {
                        throw new Exception("Position Filled: Required worker limit reached.");
                    }

                    // 2. Create project_members relationship if not already a member
                    $mem_stmt = $db->prepare("
                        INSERT INTO project_members (project_id, user_id, role_in_project, joined_at)
                        VALUES (?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE role_in_project = VALUES(role_in_project)
                    ");
                    $mem_stmt->execute([
                        $app['project_id'],
                        $app['worker_user_id'],
                        $app['trade_required']
                    ]);

                    // 3. Update application status to Accepted
                    $up_app_stmt = $db->prepare("UPDATE job_applications SET status = 'accepted', updated_at = NOW() WHERE id = ?");
                    $up_app_stmt->execute([$app_id]);

                    // 4. Update job spots_filled count & status if full
                    $new_spots_filled = $job_fresh['spots_filled'] + 1;
                    $new_job_status = ($new_spots_filled >= $job_fresh['spots_available']) ? 'filled' : 'published';

                    $up_job_stmt = $db->prepare("UPDATE jobs SET spots_filled = ?, status = ?, updated_at = NOW() WHERE id = ?");
                    $up_job_stmt->execute([$new_spots_filled, $new_job_status, $app['job_id']]);

                    // 5. Create notification for worker
                    create_notification(
                        $app['worker_user_id'],
                        'Application Accepted & Hired!',
                        "Congratulations! Apex Builders accepted your application for position '{$app['job_title']}'. You have been added as a project member.",
                        'success',
                        'worker/applications.php'
                    );

                    // 6. Log activity
                    log_activity(
                        $contractor_id,
                        'Worker Hired',
                        "Accepted candidate '{$app['worker_name']}' for job #{$app['job_id']} ('{$app['job_title']}'). Project membership created.",
                        'job_application',
                        $app_id
                    );

                    // 7. Commit Transaction
                    $db->commit();

                    set_flash_message("Candidate '{$app['worker_name']}' successfully accepted & hired into the project!", 'success');
                    redirect("contractor/application-details.php?id={$app_id}");

                } catch (Exception $ex) {
                    $db->rollBack();
                    $error = "Hiring Transaction Failed: " . $ex->getMessage();
                }
            }

        } elseif ($action === 'shortlist') {
            if (in_array($app['status'], ['pending', 'rejected'])) {
                $up_stmt = $db->prepare("UPDATE job_applications SET status = 'shortlisted', updated_at = NOW() WHERE id = ?");
                $up_stmt->execute([$app_id]);

                create_notification($app['worker_user_id'], 'Application Shortlisted', "Your application for '{$app['job_title']}' has been shortlisted by the contractor.", 'info', 'worker/applications.php');
                log_activity($contractor_id, 'Application Shortlisted', "Shortlisted worker '{$app['worker_name']}' for job #{$app['job_id']}", 'job_application', $app_id);
                set_flash_message("Applicant '{$app['worker_name']}' has been shortlisted.", 'success');
                redirect("contractor/application-details.php?id={$app_id}");
            }

        } elseif ($action === 'reject') {
            if (in_array($app['status'], ['pending', 'shortlisted'])) {
                $up_stmt = $db->prepare("UPDATE job_applications SET status = 'rejected', updated_at = NOW() WHERE id = ?");
                $up_stmt->execute([$app_id]);

                create_notification($app['worker_user_id'], 'Application Status Update', "Your application for '{$app['job_title']}' was not selected.", 'warning', 'worker/applications.php');
                log_activity($contractor_id, 'Application Rejected', "Rejected worker '{$app['worker_name']}' for job #{$app['job_id']}", 'job_application', $app_id);
                set_flash_message("Applicant '{$app['worker_name']}' status updated to Rejected.", 'info');
                redirect("contractor/application-details.php?id={$app_id}");
            }
        }
    }
}
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h1 class="h2 fw-bold text-white mb-0">Applicant Review: <?= e($app['worker_name']) ?></h1>
                    <span class="badge <?= get_status_badge_class($app['status']) ?> fs-6"><?= e(ucfirst($app['status'])) ?></span>
                </div>
                <p class="text-muted small mb-0">
                    Applying for: <a href="<?= BASE_URL ?>/contractor/job-details.php?id=<?= $app['job_id'] ?>" class="text-warning text-decoration-none fw-semibold"><?= e($app['job_title']) ?></a>
                    • Applied <?= format_datetime($app['applied_at']) ?>
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= BASE_URL ?>/contractor/applications.php" class="btn btn-outline-light btn-sm fw-bold">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Applications
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

        <div class="row g-4 mb-4">
            <!-- Left Column: Worker Profile & Cover Letter -->
            <div class="col-lg-8">
                <!-- Cover Note & Proposal -->
                <div class="bc-card p-4 mb-4">
                    <h2 class="h5 text-white fw-bold mb-3">
                        <i class="fa-solid fa-envelope-open-text text-amber me-2"></i>Candidate Application Cover Note
                    </h2>
                    <div class="p-3 bg-dark rounded-3 border border-secondary text-light mb-3" style="white-space: pre-line;">
                        <?= e($app['cover_note'] ?: 'No cover note submitted by candidate.') ?>
                    </div>

                    <?php if ($app['expected_pay']): ?>
                        <div class="extra-small text-muted">
                            Candidate Expected Pay: <span class="text-warning font-monospace fw-bold"><?= format_currency($app['expected_pay']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Worker Skills & Background -->
                <div class="bc-card p-4 mb-4">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-screwdriver-wrench text-info me-2"></i>Skills & Trade Proficiencies</h3>
                    <?php if (empty($worker_skills)): ?>
                        <p class="text-muted small">No specific skills listed in worker profile.</p>
                    <?php else: ?>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <?php foreach ($worker_skills as $sk): ?>
                                <span class="badge bg-dark border border-secondary text-light py-2 px-3">
                                    <i class="fa-solid fa-check text-success me-1"></i><?= e($sk['name']) ?>
                                    <span class="text-warning extra-small"> (<?= e(ucfirst($sk['proficiency_level'])) ?>)</span>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Experience History -->
                <div class="bc-card p-4">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-briefcase text-warning me-2"></i>Work Experience History</h3>
                    <?php if (empty($worker_experiences)): ?>
                        <p class="text-muted small mb-0">No documented work experience listed.</p>
                    <?php else: ?>
                        <div class="vstack gap-3">
                            <?php foreach ($worker_experiences as $exp): ?>
                                <div class="p-3 bg-dark rounded-3 border border-secondary">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h4 class="h6 text-white fw-bold mb-0"><?= e($exp['job_title']) ?></h4>
                                        <span class="badge bg-secondary extra-small"><?= format_date($exp['start_date']) ?> - <?= $exp['is_current'] ? 'Present' : format_date($exp['end_date']) ?></span>
                                    </div>
                                    <div class="text-warning small mb-2"><i class="fa-solid fa-building me-1"></i><?= e($exp['company_name']) ?></div>
                                    <p class="text-muted extra-small mb-0"><?= e($exp['description']) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Worker Card & Hiring Actions -->
            <div class="col-lg-4">
                <!-- Worker Information Summary -->
                <div class="bc-card p-4 mb-4">
                    <div class="text-center mb-3">
                        <div class="rounded-circle bg-secondary text-white fw-bold d-inline-flex align-items-center justify-content-center mb-2" style="width: 64px; height: 64px; font-size: 1.5rem;">
                            <?= strtoupper(substr($app['worker_name'], 0, 2)) ?>
                        </div>
                        <h3 class="h5 text-white fw-bold mb-1"><?= e($app['worker_name']) ?></h3>
                        <p class="text-warning small mb-1"><?= e($app['worker_trade'] ?: 'Construction Specialist') ?></p>

                        <?php if ($app['verification_status'] === 'approved'): ?>
                            <span class="badge bg-success py-1 px-2 extra-small"><i class="fa-solid fa-shield-check me-1"></i>Verified Worker</span>
                        <?php else: ?>
                            <span class="badge bg-secondary py-1 px-2 extra-small">Unverified Worker</span>
                        <?php endif; ?>
                    </div>

                    <hr class="border-secondary my-3">

                    <div class="vstack gap-2 extra-small">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Experience:</span>
                            <span class="text-light fw-bold"><?= (int)$app['experience_years'] ?> Years</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Location:</span>
                            <span class="text-light"><?= e($app['worker_city'] ?: 'Ahmedabad') ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Availability:</span>
                            <span class="badge bg-info extra-small"><?= e(ucfirst($app['availability_status'])) ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">AI Match Rating:</span>
                            <span class="text-purple font-monospace fw-bold"><?= $app['match_score'] ?>%</span>
                        </div>
                    </div>
                </div>

                <!-- Hiring Decision Panel -->
                <div class="bc-card p-4 border-warning">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-user-check text-warning me-2"></i>Hiring Decision</h3>

                    <div class="mb-3 text-muted extra-small">
                        Current Position Availability:
                        <span class="font-monospace text-light fw-bold ms-1"><?= (int)$app['spots_filled'] ?> / <?= (int)$app['spots_available'] ?> Filled</span>
                    </div>

                    <?php if ($app['spots_filled'] >= $app['spots_available'] && $app['status'] !== 'accepted'): ?>
                        <div class="alert alert-danger py-2 px-3 extra-small mb-3">
                            <i class="fa-solid fa-ban me-1"></i> Position Filled! No additional workers can be accepted.
                        </div>
                    <?php endif; ?>

                    <form action="<?= BASE_URL ?>/contractor/application-details.php?id=<?= $app_id ?>" method="POST" class="d-grid gap-2">
                        <?= csrf_field() ?>

                        <?php if ($app['status'] === 'accepted'): ?>
                            <div class="alert alert-success text-center py-2 px-3 small fw-bold mb-0">
                                <i class="fa-solid fa-circle-check me-1"></i> Candidate Hired & Joined Project Team
                            </div>
                        <?php else: ?>
                            <button type="submit" name="action" value="accept" class="btn btn-success fw-bold py-2" <?= $app['spots_filled'] >= $app['spots_available'] ? 'disabled' : '' ?> onclick="return confirm('Confirm hiring this worker? This will add them to your project team.');">
                                <i class="fa-solid fa-user-plus me-1"></i> Accept & Hire Candidate
                            </button>

                            <?php if ($app['status'] !== 'shortlisted'): ?>
                                <button type="submit" name="action" value="shortlist" class="btn btn-outline-info py-2 fw-bold">
                                    <i class="fa-solid fa-star me-1"></i> Shortlist Candidate
                                </button>
                            <?php endif; ?>

                            <?php if ($app['status'] !== 'rejected'): ?>
                                <button type="submit" name="action" value="reject" class="btn btn-outline-danger py-2 fw-bold" onclick="return confirm('Are you sure you want to reject this applicant?');">
                                    <i class="fa-solid fa-user-xmark me-1"></i> Reject Application
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
