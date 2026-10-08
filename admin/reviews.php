<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(ROLE_ADMIN);

$user = get_logged_user();
$db = getDB();

$errors = [];
$success = '';
$status_filter = sanitize($_GET['status'] ?? 'all');
$redirect_url = "admin/reviews.php" . ($status_filter !== 'all' ? "?status=" . urlencode($status_filter) : "");

// Handle Moderation Actions BEFORE Output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "CSRF verification failed.";
    } else {
        $action = $_POST['action'] ?? '';

        if (in_array($action, ['hide', 'restore', 'flag'])) {
            $review_id = (int)($_POST['review_id'] ?? 0);

            $rev_stmt = $db->prepare("SELECT * FROM reviews WHERE id = ?");
            $rev_stmt->execute([$review_id]);
            $rev = $rev_stmt->fetch();

            if (!$rev) {
                $errors[] = "Review record not found.";
            } else {
                $new_status = 'published';
                if ($action === 'hide') $new_status = 'hidden';
                if ($action === 'flag') $new_status = 'flagged';
                if ($action === 'restore') $new_status = 'published';

                try {
                    $db->beginTransaction();

                    $up = $db->prepare("UPDATE reviews SET status = ?, updated_at = NOW() WHERE id = ?");
                    $up->execute([$new_status, $review_id]);

                    // Recalculate rating cache for reviewed user
                    update_user_rating_cache($rev['reviewee_id']);

                    log_activity($user['id'], "Moderated Review", "Changed review ID {$review_id} status to {$new_status}", "review", $review_id);

                    $db->commit();

                    set_flash_message("Review ID #{$review_id} status set to " . strtoupper($new_status), "success");
                    redirect($redirect_url);

                } catch (Exception $e) {
                    $db->rollBack();
                    $errors[] = "Failed to update review status: " . $e->getMessage();
                }
            }
        } elseif (in_array($action, ['resolve_report', 'dismiss_report'])) {
            $report_id = (int)($_POST['report_id'] ?? 0);
            $new_rep_status = ($action === 'resolve_report') ? 'resolved' : 'dismissed';

            $up_rep = $db->prepare("UPDATE review_reports SET status = ?, updated_at = NOW() WHERE id = ?");
            $up_rep->execute([$new_rep_status, $report_id]);

            log_activity($user['id'], "Moderated Review Report", "Set report ID {$report_id} to {$new_rep_status}", "review_report", $report_id);

            set_flash_message("Report #{$report_id} marked as " . strtoupper($new_rep_status), "info");
            redirect($redirect_url);
        }
    }
}

$page_title = "Review Moderation - Admin - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

// Fetch all reviews
$status_filter = sanitize($_GET['status'] ?? 'all');
$where_sql = "";
$params = [];

if ($status_filter !== 'all') {
    $where_sql = "WHERE r.status = ?";
    $params[] = $status_filter;
}

$reviews_stmt = $db->prepare("
    SELECT r.*, 
           u_by.name as reviewer_name, u_by.role as reviewer_role,
           u_to.name as reviewee_name, u_to.role as reviewee_role,
           p.title as project_title, c.contract_number
    FROM reviews r
    JOIN users u_by ON r.reviewer_id = u_by.id
    JOIN users u_to ON r.reviewee_id = u_to.id
    JOIN projects p ON r.project_id = p.id
    LEFT JOIN contracts c ON r.contract_id = c.id
    {$where_sql}
    ORDER BY r.id DESC
");
$reviews_stmt->execute($params);
$reviews = $reviews_stmt->fetchAll();

// Fetch reports queue
$reports_stmt = $db->query("
    SELECT rr.*, 
           r.review_text, r.rating, r.status as review_status,
           u_by.name as reporter_name, u_rev.name as reviewer_name
    FROM review_reports rr
    JOIN reviews r ON rr.review_id = r.id
    JOIN users u_by ON rr.reported_by = u_by.id
    JOIN users u_rev ON r.reviewer_id = u_rev.id
    ORDER BY rr.id DESC
");
$reports = $reports_stmt->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-star text-warning me-2"></i>Review & Rating Moderation</h1>
                <p class="text-muted small mb-0">Platform-wide oversight for worker and contractor reviews.</p>
            </div>
        </div>

        <?php if ($flash = get_flash_message()): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> py-2 px-3 small mb-4">
                <i class="fa-solid fa-circle-info me-1"></i> <?= sanitize($flash['message']) ?>
            </div>
        <?php endif; ?>

        <!-- Reported Reviews Queue -->
        <?php if (!empty($reports)): ?>
            <div class="bc-card p-4 mb-4 border border-danger">
                <h5 class="fw-bold text-danger mb-3"><i class="fa-solid fa-triangle-exclamation me-2"></i>Reported Reviews Queue (<?= count($reports) ?>)</h5>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Report Ref</th>
                                <th>Reported By</th>
                                <th>Reason</th>
                                <th>Review Content</th>
                                <th>Report Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reports as $rep): ?>
                                <tr>
                                    <td class="font-monospace fw-bold text-white">#REP-<?= $rep['id'] ?></td>
                                    <td>
                                        <div class="fw-bold text-white"><?= sanitize($rep['reporter_name']) ?></div>
                                        <div class="text-muted extra-small"><?= format_date($rep['created_at']) ?></div>
                                    </td>
                                    <td class="small text-danger fw-semibold"><?= sanitize($rep['reason']) ?></td>
                                    <td class="small text-light">
                                        <div class="text-muted extra-small">By: <?= sanitize($rep['reviewer_name']) ?> (Rating: <?= $rep['rating'] ?>★)</div>
                                        "<?= sanitize($rep['review_text']) ?>"
                                    </td>
                                    <td>
                                        <span class="badge <?= get_status_badge_class($rep['status']) ?> text-capitalize"><?= sanitize($rep['status']) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($rep['status'] === 'pending'): ?>
                                            <form action="" method="POST" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="report_id" value="<?= $rep['id'] ?>">
                                                <input type="hidden" name="action" value="resolve_report">
                                                <button type="submit" class="btn btn-outline-success btn-sm" title="Mark Resolved">
                                                    <i class="fa-solid fa-check me-1"></i> Resolve
                                                </button>
                                            </form>
                                            <form action="" method="POST" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="report_id" value="<?= $rep['id'] ?>">
                                                <input type="hidden" name="action" value="dismiss_report">
                                                <button type="submit" class="btn btn-outline-secondary btn-sm" title="Dismiss Report">
                                                    <i class="fa-solid fa-xmark me-1"></i> Dismiss
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted extra-small">Processed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Filters Bar -->
        <div class="bc-card p-3 mb-4">
            <div class="d-flex gap-2 align-items-center">
                <span class="text-muted extra-small me-2"><i class="fa-solid fa-filter me-1"></i>Status Filter:</span>
                <a href="<?= BASE_URL ?>/admin/reviews.php?status=all" class="btn btn-sm <?= ($status_filter === 'all') ? 'btn-amber' : 'btn-outline-secondary text-light' ?>">All Reviews</a>
                <a href="<?= BASE_URL ?>/admin/reviews.php?status=published" class="btn btn-sm <?= ($status_filter === 'published') ? 'btn-amber' : 'btn-outline-secondary text-light' ?>">Published</a>
                <a href="<?= BASE_URL ?>/admin/reviews.php?status=hidden" class="btn btn-sm <?= ($status_filter === 'hidden') ? 'btn-amber' : 'btn-outline-secondary text-light' ?>">Hidden</a>
                <a href="<?= BASE_URL ?>/admin/reviews.php?status=flagged" class="btn btn-sm <?= ($status_filter === 'flagged') ? 'btn-amber' : 'btn-outline-secondary text-light' ?>">Flagged</a>
            </div>
        </div>

        <div class="bc-card p-4">
            <?php if (empty($reviews)): ?>
                <div class="text-center text-muted py-5">
                    <i class="fa-solid fa-star-half-stroke fs-1 opacity-25 mb-3"></i>
                    <p class="mb-0">No reviews found in moderation database.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Review ID</th>
                                <th>Reviewer</th>
                                <th>Recipient</th>
                                <th>Project</th>
                                <th>Rating</th>
                                <th>Comments</th>
                                <th>Status</th>
                                <th class="text-end">Moderation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $rev): ?>
                                <tr>
                                    <td class="font-monospace fw-bold text-white">#REV-<?= $rev['id'] ?></td>
                                    <td>
                                        <div class="fw-bold text-white"><?= sanitize($rev['reviewer_name']) ?></div>
                                        <div class="text-muted extra-small text-capitalize">(<?= sanitize($rev['reviewer_role']) ?>)</div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-white"><?= sanitize($rev['reviewee_name']) ?></div>
                                        <div class="text-muted extra-small text-capitalize">(<?= sanitize($rev['reviewee_role']) ?>)</div>
                                    </td>
                                    <td class="small text-muted">
                                        <?= sanitize($rev['project_title']) ?>
                                        <?php if ($rev['contract_number']): ?>
                                            <div class="font-monospace text-warning extra-small"><?= sanitize($rev['contract_number']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= render_star_rating($rev['rating']) ?></td>
                                    <td class="small text-light" style="max-width: 250px;">"<?= sanitize($rev['review_text']) ?>"</td>
                                    <td>
                                        <span class="badge <?= get_status_badge_class($rev['status']) ?> text-capitalize"><?= sanitize($rev['status']) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($rev['status'] === 'published'): ?>
                                                <form action="" method="POST" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                                    <input type="hidden" name="action" value="hide">
                                                    <button type="submit" class="btn btn-outline-warning" title="Hide Review">
                                                        <i class="fa-solid fa-eye-slash"></i> Hide
                                                    </button>
                                                </form>
                                                <form action="" method="POST" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                                    <input type="hidden" name="action" value="flag">
                                                    <button type="submit" class="btn btn-outline-danger" title="Flag Review">
                                                        <i class="fa-solid fa-flag"></i> Flag
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form action="" method="POST" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="review_id" value="<?= $rev['id'] ?>">
                                                    <input type="hidden" name="action" value="restore">
                                                    <button type="submit" class="btn btn-outline-success" title="Restore Published State">
                                                        <i class="fa-solid fa-check"></i> Restore
                                                    </button>
                                                </form>
                                            <?php endif; ?>
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
