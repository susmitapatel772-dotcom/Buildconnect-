<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(ROLE_WORKER);

$page_title = "My Reviews & Ratings - Worker - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = get_logged_user();
$db = getDB();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "CSRF verification failed.";
    } else {
        $action_type = $_POST['action_type'] ?? 'create';

        if ($action_type === 'create') {
            $project_id = (int)($_POST['project_id'] ?? 0);
            $contract_id = !empty($_POST['contract_id']) ? (int)$_POST['contract_id'] : null;
            $contractor_user_id = (int)($_POST['contractor_user_id'] ?? 0);
            $rating = (int)($_POST['rating'] ?? 5);
            $review_text = sanitize($_POST['review_text'] ?? '');

            if (empty($project_id) || empty($contractor_user_id)) {
                $errors[] = "Please select a valid contractor & project.";
            } elseif ($contractor_user_id === $user['id']) {
                $errors[] = "Self-reviews are not allowed.";
            } else {
                // Verify legitimate relationship
                $rel_chk = $db->prepare("
                    SELECT 1 FROM project_members pm
                    JOIN projects p ON pm.project_id = p.id
                    WHERE pm.project_id = ? AND pm.user_id = ? AND p.contractor_id = ?
                    UNION
                    SELECT 1 FROM contracts c
                    WHERE c.project_id = ? AND c.worker_id = ? AND c.contractor_id = ?
                ");
                $rel_chk->execute([$project_id, $user['id'], $contractor_user_id, $project_id, $user['id'], $contractor_user_id]);
                if (!$rel_chk->fetch()) {
                    $errors[] = "No legitimate project or contract relationship with this contractor.";
                }
            }

            if ($rating < 1 || $rating > 5) $errors[] = "Rating must be between 1 and 5 stars.";
            if (empty($review_text)) $errors[] = "Review comments cannot be empty.";

            // Check duplicate
            if ($contract_id) {
                $dup = $db->prepare("SELECT id FROM reviews WHERE contract_id = ? AND reviewer_id = ?");
                $dup->execute([$contract_id, $user['id']]);
                if ($dup->fetch()) $errors[] = "You have already reviewed this contract.";
            } else {
                $dup = $db->prepare("SELECT id FROM reviews WHERE project_id = ? AND reviewer_id = ? AND reviewee_id = ?");
                $dup->execute([$project_id, $user['id'], $contractor_user_id]);
                if ($dup->fetch()) $errors[] = "You have already reviewed this contractor for this project.";
            }

            if (empty($errors)) {
                try {
                    $db->beginTransaction();

                    $ins = $db->prepare("
                        INSERT INTO reviews (project_id, contract_id, reviewer_id, reviewee_id, rating, review_text, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, 'published', NOW())
                    ");
                    $ins->execute([$project_id, $contract_id, $user['id'], $contractor_user_id, $rating, $review_text]);

                    $review_id = $db->lastInsertId();

                    update_user_rating_cache($contractor_user_id);

                    // Notify contractor
                    $db->prepare("
                        INSERT INTO notifications (user_id, title, message, type, link, created_at)
                        VALUES (?, ?, ?, 'info', ?, NOW())
                    ")->execute([
                        $contractor_user_id,
                        "New Review Received from Worker",
                        "Worker {$user['name']} posted a {$rating}-star review for your firm.",
                        "contractor/reviews.php"
                    ]);

                    log_activity($user['id'], "Submitted Contractor Review", "Rated contractor ID {$contractor_user_id} {$rating} stars", "review", $review_id);

                    $db->commit();

                    set_flash_message("Contractor review submitted successfully!", "success");
                    redirect("worker/reviews.php");

                } catch (Exception $e) {
                    $db->rollBack();
                    $errors[] = "Failed to submit review: " . $e->getMessage();
                }
            }
        } elseif ($action_type === 'report') {
            $review_id = (int)($_POST['review_id'] ?? 0);
            $reason = sanitize($_POST['report_reason'] ?? '');

            if (empty($reason)) {
                $errors[] = "Please state the reason for flagging this review.";
            } else {
                $db->prepare("INSERT INTO review_reports (review_id, reported_by, reason, status, created_at) VALUES (?, ?, ?, 'pending', NOW())")
                   ->execute([$review_id, $user['id'], $reason]);

                log_activity($user['id'], "Reported Review", "Flagged review ID {$review_id}", "review_report", $db->lastInsertId());

                set_flash_message("Review reported to admin moderation team.", "info");
                redirect("worker/reviews.php");
            }
        }
    }
}

// Fetch reviews received by worker
$reviews_received_stmt = $db->prepare("
    SELECT r.*, u.name as contractor_name, c_co.company_name, p.title as project_title, c.contract_number
    FROM reviews r
    JOIN users u ON r.reviewer_id = u.id
    LEFT JOIN contractors c_co ON u.id = c_co.user_id
    JOIN projects p ON r.project_id = p.id
    LEFT JOIN contracts c ON r.contract_id = c.id
    WHERE r.reviewee_id = ? AND r.status = 'published'
    ORDER BY r.id DESC
");
$reviews_received_stmt->execute([$user['id']]);
$reviews_received = $reviews_received_stmt->fetchAll();

// Fetch eligible contractors for worker review form
$eligible_contractors_stmt = $db->prepare("
    SELECT DISTINCT u.id as contractor_user_id, u.name as contractor_name, c_co.company_name, p.id as project_id, p.title as project_title, c.id as contract_id, c.contract_number
    FROM project_members pm
    JOIN projects p ON pm.project_id = p.id
    JOIN users u ON p.contractor_id = u.id
    LEFT JOIN contractors c_co ON u.id = c_co.user_id
    LEFT JOIN contracts c ON c.project_id = p.id AND c.worker_id = ? AND c.contractor_id = u.id
    WHERE pm.user_id = ?
    ORDER BY p.title ASC
");
$eligible_contractors_stmt->execute([$user['id'], $user['id']]);
$eligible_contractors = $eligible_contractors_stmt->fetchAll();

// Fetch reviews given by worker
$reviews_given_stmt = $db->prepare("
    SELECT r.*, u.name as contractor_name, c_co.company_name, p.title as project_title, c.contract_number
    FROM reviews r
    JOIN users u ON r.reviewee_id = u.id
    LEFT JOIN contractors c_co ON u.id = c_co.user_id
    JOIN projects p ON r.project_id = p.id
    LEFT JOIN contracts c ON r.contract_id = c.id
    WHERE r.reviewer_id = ?
    ORDER BY r.id DESC
");
$reviews_given_stmt->execute([$user['id']]);
$reviews_given = $reviews_given_stmt->fetchAll();

// Calculate rating summary for worker
$summary = calculate_user_rating_summary($user['id']);
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-star text-warning me-2"></i>My Reviews & Reputation</h1>
                <p class="text-muted small mb-0">Contractor feedback, ratings, and workplace recommendations.</p>
            </div>
            <button class="btn btn-amber btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#rateContractorModal">
                <i class="fa-solid fa-plus me-1"></i> Rate Contractor
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

        <!-- Overall Rating Summary Card -->
        <div class="bc-card p-4 mb-4">
            <div class="row align-items-center g-4">
                <div class="col-md-4 text-center border-end border-secondary">
                    <div class="display-3 fw-bold text-warning mb-0"><?= number_format($summary['avg_rating'], 1) ?></div>
                    <div class="mb-1"><?= render_star_rating($summary['avg_rating'], false) ?></div>
                    <div class="text-muted extra-small">Based on <?= $summary['total_reviews'] ?> contractor review<?= $summary['total_reviews'] === 1 ? '' : 's' ?></div>
                </div>

                <div class="col-md-8">
                    <div class="d-flex flex-column gap-2">
                        <?php for ($star = 5; $star >= 1; $star--): 
                            $cnt = $summary["star_{$star}_count"];
                            $pct = $summary["star_{$star}_pct"];
                        ?>
                            <div class="d-flex align-items-center extra-small">
                                <span class="text-muted me-2" style="width: 50px;"><?= $star ?> Star</span>
                                <div class="progress bg-dark border border-secondary flex-grow-1" style="height: 8px;">
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $pct ?>%;"></div>
                                </div>
                                <span class="text-muted ms-2" style="width: 40px; text-align: right;"><?= $cnt ?></span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabbed Reviews Navigation -->
        <ul class="nav nav-tabs border-secondary mb-4" id="reviewTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active text-light bg-dark border-secondary" id="received-tab" data-bs-toggle="tab" data-bs-target="#received-pane" type="button" role="tab">
                    <i class="fa-solid fa-inbox me-1 text-warning"></i> Reviews Received (<?= count($reviews_received) ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link text-light bg-dark border-secondary" id="given-tab" data-bs-toggle="tab" data-bs-target="#given-pane" type="button" role="tab">
                    <i class="fa-solid fa-paper-plane me-1 text-info"></i> Reviews Given (<?= count($reviews_given) ?>)
                </button>
            </li>
        </ul>

        <div class="tab-content" id="reviewTabsContent">
            <!-- Reviews Received Pane -->
            <div class="tab-pane fade show active" id="received-pane" role="tabpanel">
                <div class="row g-4">
                    <?php if (empty($reviews_received)): ?>
                        <div class="col-12">
                            <div class="bc-card p-5 text-center text-muted">
                                <i class="fa-solid fa-comment-slash fs-1 opacity-25 mb-3"></i>
                                <p class="mb-0">No reviews received from contractors yet.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($reviews_received as $rev): ?>
                            <div class="col-md-6">
                                <div class="bc-card p-4 h-100 d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h3 class="h6 text-white fw-bold mb-0"><?= sanitize($rev['company_name'] ?: $rev['contractor_name']) ?></h3>
                                            <?= render_star_rating($rev['rating']) ?>
                                        </div>
                                        <div class="text-muted extra-small mb-2"><i class="fa-solid fa-building me-1"></i>Project: <?= sanitize($rev['project_title']) ?></div>
                                        <p class="text-light small mb-0 bg-dark p-3 rounded-3 border border-secondary">
                                            "<?= sanitize($rev['review_text']) ?>"
                                        </p>
                                    </div>

                                    <div class="pt-3 border-top border-secondary mt-3 d-flex justify-content-between align-items-center">
                                        <span class="text-muted extra-small"><?= format_date($rev['created_at']) ?></span>
                                        <button class="btn btn-outline-secondary btn-sm extra-small" data-bs-toggle="modal" data-bs-target="#reportModal<?= $rev['id'] ?>">
                                            <i class="fa-solid fa-flag text-danger me-1"></i> Report
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Report Modal -->
                            <div class="modal fade" id="reportModal<?= $rev['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content bg-dark border-secondary text-light">
                                        <div class="modal-header border-secondary">
                                            <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-flag me-2 text-danger"></i>Report Inappropriate Review</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form action="<?= BASE_URL ?>/worker/reviews.php" method="POST">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action_type" value="report">
                                            <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label text-muted small fw-semibold">Reason for Flagging <span class="text-danger">*</span></label>
                                                    <textarea class="form-control bg-dark text-light border-secondary" name="report_reason" rows="3" required placeholder="Explain why this review violates platform safety policies..."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-secondary">
                                                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-danger btn-sm fw-bold">Submit Report</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reviews Given Pane -->
            <div class="tab-pane fade" id="given-pane" role="tabpanel">
                <div class="row g-4">
                    <?php if (empty($reviews_given)): ?>
                        <div class="col-12">
                            <div class="bc-card p-5 text-center text-muted">
                                <p class="mb-0">You have not submitted any contractor reviews yet.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($reviews_given as $rg): ?>
                            <div class="col-md-6">
                                <div class="bc-card p-4 h-100">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h3 class="h6 text-white fw-bold mb-0"><?= sanitize($rg['company_name'] ?: $rg['contractor_name']) ?></h3>
                                        <?= render_star_rating($rg['rating']) ?>
                                    </div>
                                    <div class="text-muted extra-small mb-2"><i class="fa-solid fa-building me-1"></i>Project: <?= sanitize($rg['project_title']) ?></div>
                                    <p class="text-light small mb-0 bg-dark p-3 rounded-3 border border-secondary">
                                        "<?= sanitize($rg['review_text']) ?>"
                                    </p>
                                    <div class="text-muted extra-small mt-3">Submitted: <?= format_date($rg['created_at']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Rate Contractor Modal -->
<div class="modal fade" id="rateContractorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-star me-2 text-warning"></i>Rate General Contractor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>/worker/reviews.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action_type" value="create">
                <div class="modal-body">
                    <?php if (empty($eligible_contractors)): ?>
                        <div class="alert alert-warning py-2 px-3 small">
                            No active or completed project relationships found. You can review contractors once associated with a project.
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-semibold">Select Contractor & Project <span class="text-danger">*</span></label>
                            <select class="form-select bg-dark text-light border-secondary" name="contractor_project_combo" required onchange="
                                var val = this.value.split('|');
                                document.getElementById('req_contractor_user_id').value = val[0];
                                document.getElementById('req_project_id').value = val[1];
                                document.getElementById('req_contract_id').value = val[2] || '';
                            ">
                                <option value="">-- Choose Contractor & Project --</option>
                                <?php foreach ($eligible_contractors as $ec): 
                                    $combo = $ec['contractor_user_id'] . '|' . $ec['project_id'] . '|' . ($ec['contract_id'] ?? '');
                                ?>
                                    <option value="<?= $combo ?>">
                                        <?= sanitize($ec['company_name'] ?: $ec['contractor_name']) ?> — <?= sanitize($ec['project_title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="contractor_user_id" id="req_contractor_user_id">
                            <input type="hidden" name="project_id" id="req_project_id">
                            <input type="hidden" name="contract_id" id="req_contract_id">
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small fw-semibold">Rating (1 to 5 Stars) <span class="text-danger">*</span></label>
                            <select class="form-select bg-dark text-light border-secondary" name="rating">
                                <option value="5" selected>5 Stars - Outstanding Contractor</option>
                                <option value="4">4 Stars - Great Work Environment</option>
                                <option value="3">3 Stars - Satisfactory</option>
                                <option value="2">2 Stars - Payout / Safety Issues</option>
                                <option value="1">1 Star - Unsatisfactory</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted small fw-semibold">Review Comments <span class="text-danger">*</span></label>
                            <textarea class="form-control bg-dark text-light border-secondary" name="review_text" rows="4" required placeholder="Comment on payout timeliness, site safety standards, management clarity..."></textarea>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <?php if (!empty($eligible_contractors)): ?>
                        <button type="submit" class="btn btn-amber btn-sm fw-bold">Submit Review</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
