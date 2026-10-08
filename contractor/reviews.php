<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(ROLE_CONTRACTOR);

$page_title = "Worker Reviews - Contractor - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = get_logged_user();
$db = getDB();

$errors = [];
$success = '';

// Pre-fill parameters if navigated from contract details
$target_worker_id = isset($_GET['worker_id']) ? (int)$_GET['worker_id'] : 0;
$target_project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$target_contract_id = isset($_GET['contract_id']) ? (int)$_GET['contract_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "CSRF verification failed.";
    } else {
        $action_type = $_POST['action_type'] ?? 'create';

        if ($action_type === 'create') {
            $project_id = (int)($_POST['project_id'] ?? 0);
            $contract_id = !empty($_POST['contract_id']) ? (int)$_POST['contract_id'] : null;
            $worker_id = (int)($_POST['worker_id'] ?? 0);
            $rating = (int)($_POST['rating'] ?? 5);
            $review_text = sanitize($_POST['review_text'] ?? '');

            // Validations
            if (empty($project_id)) {
                $errors[] = "Please select a valid project.";
            } else {
                // Verify contractor owns project
                $p_chk = $db->prepare("SELECT id FROM projects WHERE id = ? AND contractor_id = ?");
                $p_chk->execute([$project_id, $user['id']]);
                if (!$p_chk->fetch()) {
                    $errors[] = "Unauthorized project selection.";
                }
            }

            if (empty($worker_id)) {
                $errors[] = "Please select a worker to review.";
            } elseif ($worker_id === $user['id']) {
                $errors[] = "Self-reviews are strictly prohibited.";
            } else {
                // Check legitimate relationship (Worker is a project member or contractor's contract holder)
                $rel_chk = $db->prepare("
                    SELECT 1 FROM project_members pm
                    WHERE pm.project_id = ? AND pm.user_id = ?
                    UNION
                    SELECT 1 FROM contracts c
                    WHERE c.project_id = ? AND c.worker_id = ? AND c.contractor_id = ?
                ");
                $rel_chk->execute([$project_id, $worker_id, $project_id, $worker_id, $user['id']]);
                if (!$rel_chk->fetch()) {
                    $errors[] = "Worker has no legitimate project relationship with your firm.";
                }
            }

            if ($rating < 1 || $rating > 5) {
                $errors[] = "Rating must be between 1 and 5 stars.";
            }

            if (empty($review_text)) {
                $errors[] = "Review comments cannot be empty.";
            }

            // Check duplicate review
            if ($contract_id) {
                $dup_stmt = $db->prepare("SELECT id FROM reviews WHERE contract_id = ? AND reviewer_id = ?");
                $dup_stmt->execute([$contract_id, $user['id']]);
                if ($dup_stmt->fetch()) {
                    $errors[] = "You have already submitted a review for this contract.";
                }
            } else {
                $dup_stmt = $db->prepare("SELECT id FROM reviews WHERE project_id = ? AND reviewer_id = ? AND reviewee_id = ?");
                $dup_stmt->execute([$project_id, $user['id'], $worker_id]);
                if ($dup_stmt->fetch()) {
                    $errors[] = "You have already submitted a review for this worker on this project.";
                }
            }

            if (empty($errors)) {
                try {
                    $db->beginTransaction();

                    $ins = $db->prepare("
                        INSERT INTO reviews (project_id, contract_id, reviewer_id, reviewee_id, rating, review_text, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, 'published', NOW())
                    ");
                    $ins->execute([$project_id, $contract_id, $user['id'], $worker_id, $rating, $review_text]);

                    $new_review_id = $db->lastInsertId();

                    // Update cached average rating for worker
                    update_user_rating_cache($worker_id);

                    // Send notification to worker
                    $db->prepare("
                        INSERT INTO notifications (user_id, title, message, type, link, created_at)
                        VALUES (?, ?, ?, 'info', ?, NOW())
                    ")->execute([
                        $worker_id,
                        "New Performance Review Received",
                        "Contractor {$user['name']} posted a {$rating}-star review for your work.",
                        "worker/reviews.php"
                    ]);

                    log_activity($user['id'], "Submitted Worker Review", "Rated worker ID {$worker_id} {$rating} stars", "review", $new_review_id);

                    $db->commit();

                    set_flash_message("Performance review for worker submitted successfully!", "success");
                    redirect("contractor/reviews.php");

                } catch (Exception $e) {
                    $db->rollBack();
                    $errors[] = "Failed to submit review: " . $e->getMessage();
                }
            }
        } elseif ($action_type === 'edit') {
            $review_id = (int)($_POST['review_id'] ?? 0);
            $rating = (int)($_POST['rating'] ?? 5);
            $review_text = sanitize($_POST['review_text'] ?? '');

            $rev_stmt = $db->prepare("SELECT * FROM reviews WHERE id = ? AND reviewer_id = ?");
            $rev_stmt->execute([$review_id, $user['id']]);
            $rev = $rev_stmt->fetch();

            if (!$rev) {
                $errors[] = "Review not found or unauthorized.";
            } else {
                // 7 day edit limit check
                $created_ts = strtotime($rev['created_at']);
                if (time() > ($created_ts + (7 * 86400))) {
                    $errors[] = "Reviews can only be edited within 7 days of submission.";
                }
            }

            if ($rating < 1 || $rating > 5) $errors[] = "Rating must be between 1 and 5 stars.";
            if (empty($review_text)) $errors[] = "Review comments cannot be empty.";

            if (empty($errors)) {
                try {
                    $db->beginTransaction();

                    $up = $db->prepare("UPDATE reviews SET rating = ?, review_text = ?, updated_at = NOW() WHERE id = ? AND reviewer_id = ?");
                    $up->execute([$rating, $review_text, $review_id, $user['id']]);

                    update_user_rating_cache($rev['reviewee_id']);

                    log_activity($user['id'], "Updated Review", "Updated review ID {$review_id}", "review", $review_id);

                    $db->commit();

                    set_flash_message("Review successfully updated!", "success");
                    redirect("contractor/reviews.php");

                } catch (Exception $e) {
                    $db->rollBack();
                    $errors[] = "Failed to update review: " . $e->getMessage();
                }
            }
        } elseif ($action_type === 'report') {
            $review_id = (int)($_POST['review_id'] ?? 0);
            $reason = sanitize($_POST['report_reason'] ?? '');

            if (empty($reason)) {
                $errors[] = "Please state the reason for flagging this review.";
            } else {
                $ins_rep = $db->prepare("INSERT INTO review_reports (review_id, reported_by, reason, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
                $ins_rep->execute([$review_id, $user['id'], $reason]);

                log_activity($user['id'], "Reported Review", "Flagged review ID {$review_id}", "review_report", $db->lastInsertId());

                set_flash_message("Review reported to admin moderation team.", "info");
                redirect("contractor/reviews.php");
            }
        }
    }
}

// Fetch eligible workers for contractor review form
$eligible_workers_stmt = $db->prepare("
    SELECT DISTINCT u.id as worker_user_id, u.name as worker_name, p.id as project_id, p.title as project_title, w.trade_title, c.id as contract_id, c.contract_number
    FROM project_members pm
    JOIN projects p ON pm.project_id = p.id
    JOIN users u ON pm.user_id = u.id
    LEFT JOIN workers w ON u.id = w.user_id
    LEFT JOIN contracts c ON c.project_id = p.id AND c.worker_id = u.id AND c.contractor_id = ?
    WHERE p.contractor_id = ? AND u.role = 'worker'
    ORDER BY p.title ASC, u.name ASC
");
$eligible_workers_stmt->execute([$user['id'], $user['id']]);
$eligible_workers = $eligible_workers_stmt->fetchAll();

// Fetch reviews given by this contractor
$my_reviews_stmt = $db->prepare("
    SELECT r.*, u.name as worker_name, p.title as project_title, c.contract_number, w.trade_title
    FROM reviews r
    JOIN users u ON r.reviewee_id = u.id
    LEFT JOIN workers w ON u.id = w.user_id
    JOIN projects p ON r.project_id = p.id
    LEFT JOIN contracts c ON r.contract_id = c.id
    WHERE r.reviewer_id = ?
    ORDER BY r.id DESC
");
$my_reviews_stmt->execute([$user['id']]);
$my_reviews = $my_reviews_stmt->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-star text-warning me-2"></i>Worker Rating & Review Manager</h1>
                <p class="text-muted small mb-0">Rate workers on safety adherence, work quality, and reliability for completed contracts.</p>
            </div>
            <button class="btn btn-amber btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#newReviewModal">
                <i class="fa-solid fa-plus me-1"></i> Submit Worker Review
            </button>
        </div>

        <?php if ($flash = get_flash_message()): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> py-2 px-3 small mb-4">
                <i class="fa-solid fa-circle-info me-1"></i> <?= sanitize($flash['message']) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger py-2 px-3 small mb-4">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= sanitize($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <?php if (empty($my_reviews)): ?>
                <div class="col-12">
                    <div class="bc-card p-5 text-center text-muted">
                        <i class="fa-solid fa-star-half-stroke fs-1 opacity-25 mb-3"></i>
                        <p class="mb-2">No worker reviews submitted yet.</p>
                        <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#newReviewModal">
                            Submit First Review
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($my_reviews as $rev): 
                    $can_edit = (time() <= (strtotime($rev['created_at']) + (7 * 86400)));
                ?>
                    <div class="col-md-6">
                        <div class="bc-card p-4 h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h3 class="h6 text-white fw-bold mb-0"><?= sanitize($rev['worker_name']) ?></h3>
                                        <div class="text-muted extra-small"><?= sanitize($rev['trade_title'] ?? 'Worker') ?></div>
                                    </div>
                                    <?= render_star_rating($rev['rating']) ?>
                                </div>
                                <div class="text-muted extra-small mb-2">
                                    <i class="fa-solid fa-building me-1 text-info"></i><?= sanitize($rev['project_title']) ?>
                                    <?php if ($rev['contract_number']): ?>
                                        <span class="ms-1 font-monospace text-warning">(<?= sanitize($rev['contract_number']) ?>)</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-light small mb-0 bg-dark p-3 rounded-3 border border-secondary">
                                    "<?= sanitize($rev['review_text']) ?>"
                                </p>
                            </div>

                            <div class="pt-3 border-top border-secondary mt-3 d-flex justify-content-between align-items-center">
                                <span class="text-muted extra-small"><i class="fa-solid fa-clock me-1"></i><?= format_date($rev['created_at']) ?></span>
                                
                                <div class="btn-group btn-group-sm">
                                    <?php if ($can_edit): ?>
                                        <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editReviewModal<?= $rev['id'] ?>" title="Edit Review (7-day window)">
                                            <i class="fa-solid fa-pen"></i> Edit
                                        </button>
                                    <?php else: ?>
                                        <span class="badge bg-dark text-muted border border-secondary" title="Review locked after 7 days"><i class="fa-solid fa-lock me-1"></i>Locked</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Modal for this review -->
                    <?php if ($can_edit): ?>
                        <div class="modal fade" id="editReviewModal<?= $rev['id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content bg-dark border-secondary text-light">
                                    <div class="modal-header border-secondary">
                                        <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-pen me-2 text-warning"></i>Edit Review</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form action="<?= BASE_URL ?>/contractor/reviews.php" method="POST">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action_type" value="edit">
                                        <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label text-muted small fw-semibold">Worker</label>
                                                <input type="text" class="form-control bg-secondary text-light" readonly value="<?= sanitize($rev['worker_name']) ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label text-muted small fw-semibold">Rating (1 to 5 Stars)</label>
                                                <select class="form-select bg-dark text-light border-secondary" name="rating">
                                                    <?php for ($s = 5; $s >= 1; $s--): ?>
                                                        <option value="<?= $s ?>" <?= ($rev['rating'] == $s) ? 'selected' : '' ?>><?= $s ?> Stars</option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label text-muted small fw-semibold">Review Comments</label>
                                                <textarea class="form-control bg-dark text-light border-secondary" name="review_text" rows="3" required><?= sanitize($rev['review_text']) ?></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-secondary">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-amber btn-sm fw-bold">Update Review</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Submit Review Modal -->
<div class="modal fade" id="newReviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-star me-2 text-warning"></i>Rate Worker Performance</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>/contractor/reviews.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action_type" value="create">
                <div class="modal-body">
                    <?php if (empty($eligible_workers)): ?>
                        <div class="alert alert-warning py-2 px-3 small">
                            No active project workers available for review. Create a project and assign workers first.
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-semibold">Select Worker & Project <span class="text-danger">*</span></label>
                            <select class="form-select bg-dark text-light border-secondary" name="worker_project_combo" required onchange="
                                var val = this.value.split('|');
                                document.getElementById('req_worker_id').value = val[0];
                                document.getElementById('req_project_id').value = val[1];
                                document.getElementById('req_contract_id').value = val[2] || '';
                            ">
                                <option value="">-- Choose Worker & Project --</option>
                                <?php foreach ($eligible_workers as $ew): 
                                    $combo = $ew['worker_user_id'] . '|' . $ew['project_id'] . '|' . ($ew['contract_id'] ?? '');
                                    $selected = ($target_worker_id == $ew['worker_user_id'] && $target_project_id == $ew['project_id']) ? 'selected' : '';
                                ?>
                                    <option value="<?= $combo ?>" <?= $selected ?>>
                                        <?= sanitize($ew['worker_name']) ?> — <?= sanitize($ew['project_title']) ?> <?= $ew['contract_number'] ? ('(' . $ew['contract_number'] . ')') : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="worker_id" id="req_worker_id" value="<?= $target_worker_id ?>">
                            <input type="hidden" name="project_id" id="req_project_id" value="<?= $target_project_id ?>">
                            <input type="hidden" name="contract_id" id="req_contract_id" value="<?= $target_contract_id ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small fw-semibold">Rating (1 to 5 Stars) <span class="text-danger">*</span></label>
                            <select class="form-select bg-dark text-light border-secondary" name="rating">
                                <option value="5" selected>5 Stars - Outstanding Performance</option>
                                <option value="4">4 Stars - Excellent Work</option>
                                <option value="3">3 Stars - Satisfactory</option>
                                <option value="2">2 Stars - Needs Improvement</option>
                                <option value="1">1 Star - Unsatisfactory</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small fw-semibold">Review Comments <span class="text-danger">*</span></label>
                            <textarea class="form-control bg-dark text-light border-secondary" name="review_text" rows="4" required placeholder="Comment on work quality, safety standards, punctuality, and technical skills..."></textarea>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <?php if (!empty($eligible_workers)): ?>
                        <button type="submit" class="btn btn-amber btn-sm fw-bold">Submit Review</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
