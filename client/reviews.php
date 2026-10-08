<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('client');

$page_title = "Project & Contractor Reviews - Client Dashboard - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$client_user_id = (int)$user['id'];

$error = '';
$flash = get_flash_message();

// Handle New Review Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security validation failed (CSRF token invalid). Please try again.';
    } else {
        $project_id = (int)($_POST['project_id'] ?? 0);
        $reviewee_id = (int)($_POST['reviewee_id'] ?? 0);
        $rating = (int)($_POST['rating'] ?? 5);
        $review_text = trim($_POST['review_text'] ?? '');

        // Server-side Authorization: Verify project belongs to logged-in client
        $p_stmt = $db->prepare("SELECT id, title, contractor_id FROM projects WHERE id = ? AND client_id = ?");
        $p_stmt->execute([$project_id, $client_user_id]);
        $project = $p_stmt->fetch();

        if (!$project) {
            $error = 'Unauthorized project selection or project does not exist.';
        } elseif ($rating < 1 || $rating > 5) {
            $error = 'Please provide a valid rating between 1 and 5 stars.';
        } elseif (empty($review_text)) {
            $error = 'Please enter your review feedback text.';
        } else {
            // Default reviewee_id to project contractor if not explicitly specified
            if ($reviewee_id <= 0) {
                $reviewee_id = (int)$project['contractor_id'];
            }

            // Verify reviewee is linked to project
            $r_chk = $db->prepare("
                SELECT id FROM users 
                WHERE id = ? AND (
                    id = ? OR 
                    id IN (SELECT user_id FROM project_members WHERE project_id = ?)
                )
            ");
            $r_chk->execute([$reviewee_id, $project['contractor_id'], $project_id]);
            if (!$r_chk->fetch()) {
                $error = 'Selected contractor or worker is not associated with this project.';
            } else {
                try {
                    $db->beginTransaction();

                    // Check for duplicate existing review on same project for same reviewee
                    $dup_stmt = $db->prepare("SELECT id FROM reviews WHERE project_id = ? AND reviewer_id = ? AND reviewee_id = ?");
                    $dup_stmt->execute([$project_id, $client_user_id, $reviewee_id]);
                    $existing_review = $dup_stmt->fetch();

                    if ($existing_review) {
                        $up_stmt = $db->prepare("
                            UPDATE reviews 
                            SET rating = ?, review_text = ?, status = 'published', updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $up_stmt->execute([$rating, $review_text, $existing_review['id']]);
                        $review_id = $existing_review['id'];
                        set_flash_message('Your existing review was updated successfully!', 'success');
                    } else {
                        $ins_stmt = $db->prepare("
                            INSERT INTO reviews (project_id, reviewer_id, reviewee_id, rating, review_text, status, created_at)
                            VALUES (?, ?, ?, ?, ?, 'published', NOW())
                        ");
                        $ins_stmt->execute([$project_id, $client_user_id, $reviewee_id, $rating, $review_text]);
                        $review_id = $db->lastInsertId();
                        set_flash_message('Thank you! Your review has been submitted successfully.', 'success');
                    }

                    // Recalculate cached rating stats for reviewee
                    update_user_rating_cache($reviewee_id);

                    log_activity($client_user_id, 'CLIENT_SUBMITTED_REVIEW', "Submitted {$rating}-star review for user #{$reviewee_id} on project #{$project_id}", 'review', $review_id);
                    create_notification($reviewee_id, 'New Review Received', "Client {$user['name']} published a {$rating}-star review for project {$project['title']}.", 'REVIEW', "/reviews.php", 'review', $review_id);

                    $db->commit();
                    redirect('/client/reviews.php');
                } catch (Exception $e) {
                    $db->rollBack();
                    error_log("Client review submission error: " . $e->getMessage());
                    $error = 'Failed to submit review due to a database error.';
                }
            }
        }
    }
}

// Search and Filter GET parameters
$filter_project_id = (int)($_GET['project_id'] ?? 0);
$filter_rating = (int)($_GET['rating'] ?? 0);
$search = sanitize($_GET['q'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Fetch Client Authorized Projects
$cp_stmt = $db->prepare("
    SELECT p.id, p.title, p.contractor_id, u.name as contractor_name, c.company_name as contractor_company 
    FROM projects p
    LEFT JOIN users u ON p.contractor_id = u.id
    LEFT JOIN contractors c ON c.user_id = u.id
    WHERE p.client_id = ? 
    ORDER BY p.id DESC
");
$cp_stmt->execute([$client_user_id]);
$client_projects = $cp_stmt->fetchAll();

// Fetch Eligible Reviewees (Contractors & Workers on Client Projects)
$rev_stmt = $db->prepare("
    SELECT DISTINCT u.id, u.name, u.role, p.id as project_id, p.title as project_title, c.company_name
    FROM projects p
    JOIN users u ON (p.contractor_id = u.id OR u.id IN (SELECT user_id FROM project_members WHERE project_id = p.id))
    LEFT JOIN contractors c ON c.user_id = u.id
    WHERE p.client_id = ? AND u.id != ?
    ORDER BY u.name ASC
");
$rev_stmt->execute([$client_user_id, $client_user_id]);
$eligible_reviewees = $rev_stmt->fetchAll();

// Build Reviews Query (Client's submitted reviews)
$where = ["r.reviewer_id = ?"];
$params = [$client_user_id];

if ($filter_project_id > 0) {
    $where[] = "r.project_id = ?";
    $params[] = $filter_project_id;
}

if ($filter_rating > 0 && $filter_rating <= 5) {
    $where[] = "r.rating = ?";
    $params[] = $filter_rating;
}

if (!empty($search)) {
    $where[] = "(p.title LIKE ? OR reviewee.name LIKE ? OR r.review_text LIKE ?)";
    $s_term = "%{$search}%";
    $params[] = $s_term;
    $params[] = $s_term;
    $params[] = $s_term;
}

$where_sql = implode(' AND ', $where);

// Total Count
$count_stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM reviews r
    JOIN projects p ON r.project_id = p.id
    JOIN users reviewee ON r.reviewee_id = reviewee.id
    WHERE {$where_sql}
");
$count_stmt->execute($params);
$total_rows = (int)$count_stmt->fetchColumn();
$total_pages = max(1, ceil($total_rows / $per_page));

// Summary Metrics
$m_stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_reviews,
        COALESCE(AVG(rating), 5.0) as avg_rating,
        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as star_5_count
    FROM reviews r
    WHERE r.reviewer_id = ?
");
$m_stmt->execute([$client_user_id]);
$metrics = $m_stmt->fetch();

// Fetch Reviews Listing
$sql = "
    SELECT r.*, p.title as project_title, reviewee.name as reviewee_name, reviewee.role as reviewee_role,
           c.company_name as reviewee_company
    FROM reviews r
    JOIN projects p ON r.project_id = p.id
    JOIN users reviewee ON r.reviewee_id = reviewee.id
    LEFT JOIN contractors c ON c.user_id = reviewee.id
    WHERE {$where_sql}
    ORDER BY r.id DESC
    LIMIT {$per_page} OFFSET {$offset}
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$reviews = $stmt->fetchAll();
?>

<div class="dashboard-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="dashboard-content">
        <!-- Page Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h1 class="h2 fw-extrabold text-white bc-page-title mb-1">
                    <i class="fa-solid fa-star text-warning me-2"></i>Ratings & Feedback Moderation
                </h1>
                <p class="text-slate-400 small mb-0">Rate and provide performance feedback for prime contractors and specialists assigned to your projects.</p>
            </div>
            <div>
                <button type="button" class="btn btn-orange fw-bold rounded-3 px-3 py-2 shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#newReviewModal">
                    <i class="fa-solid fa-plus"></i> Submit New Review
                </button>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show mb-4" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i> <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= sanitize($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- KPI Metrics Row -->
        <div class="row g-3 mb-4">
            <div class="col-xl-4 col-md-6">
                <div class="bc-metric-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bc-metric-badge-amber p-2.5 rounded-3"><i class="fa-solid fa-star fs-5"></i></div>
                        <span class="text-slate-400 extra-small fw-bold">Average Rating Given</span>
                    </div>
                    <div class="fs-3 fw-bold text-white mb-1">
                        <?= render_star_rating($metrics['avg_rating'], true) ?>
                    </div>
                    <div class="text-slate-400 extra-small">Across All Submitted Reviews</div>
                </div>
            </div>

            <div class="col-xl-4 col-md-6">
                <div class="bc-metric-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bc-metric-badge-blue p-2.5 rounded-3"><i class="fa-solid fa-comments fs-5"></i></div>
                        <span class="text-slate-400 extra-small fw-bold">Total Reviews Submitted</span>
                    </div>
                    <div class="fs-3 fw-bold text-white mb-1"><?= number_format($metrics['total_reviews']) ?></div>
                    <div class="text-slate-400 extra-small">Feedback Submissions</div>
                </div>
            </div>

            <div class="col-xl-4 col-md-6">
                <div class="bc-metric-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bc-metric-badge-green p-2.5 rounded-3"><i class="fa-solid fa-award fs-5"></i></div>
                        <span class="text-slate-400 extra-small fw-bold">5-Star Endorsements</span>
                    </div>
                    <div class="fs-3 fw-bold text-white mb-1"><?= number_format($metrics['star_5_count']) ?></div>
                    <div class="text-success extra-small fw-semibold"><i class="fa-solid fa-check me-1"></i>Top Quality Ratings</div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="bc-surface-card p-3 mb-4">
            <form action="<?= BASE_URL ?>/client/reviews.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <input type="text" name="q" value="<?= e($search) ?>" class="form-control form-control-sm" placeholder="Search contractor name, project, or review text...">
                </div>

                <div class="col-md-4">
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="0">All My Projects</option>
                        <?php foreach ($client_projects as $cp): ?>
                            <option value="<?= $cp['id'] ?>" <?= $filter_project_id == $cp['id'] ? 'selected' : '' ?>>
                                <?= e($cp['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="rating" class="form-select form-select-sm">
                        <option value="0">All Ratings</option>
                        <option value="5" <?= $filter_rating === 5 ? 'selected' : '' ?>>5 Stars</option>
                        <option value="4" <?= $filter_rating === 4 ? 'selected' : '' ?>>4 Stars</option>
                        <option value="3" <?= $filter_rating === 3 ? 'selected' : '' ?>>3 Stars</option>
                        <option value="2" <?= $filter_rating === 2 ? 'selected' : '' ?>>2 Stars</option>
                        <option value="1" <?= $filter_rating === 1 ? 'selected' : '' ?>>1 Star</option>
                    </select>
                </div>

                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-outline-blue btn-sm fw-bold">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Reviews Stream Listing -->
        <div class="bc-surface-card p-4">
            <?php if (empty($reviews)): ?>
                <div class="text-center py-5 text-muted extra-small">
                    <i class="fa-solid fa-star fs-1 mb-3 text-warning"></i>
                    <h3 class="h6 text-dark fw-bold mb-1">No Reviews Submitted Yet</h3>
                    <p class="text-muted extra-small mb-3">You haven't reviewed any contractors or specialists for your projects.</p>
                    <button type="button" class="btn btn-orange btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#newReviewModal">
                        <i class="fa-solid fa-plus me-1"></i> Write First Review
                    </button>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($reviews as $rev): ?>
                        <div class="p-3.5 rounded-3 bg-dark border border-secondary">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-circle bg-warning bg-opacity-10 text-warning fw-bold p-2 text-center rounded-circle" style="width: 38px; height: 38px; line-height: 22px;">
                                        <?= strtoupper(substr($rev['reviewee_name'], 0, 2)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-white small">
                                            <?= e($rev['reviewee_company'] ?? $rev['reviewee_name']) ?>
                                            <span class="badge bg-secondary bg-opacity-20 text-light ms-1 extra-small text-capitalize"><?= e($rev['reviewee_role']) ?></span>
                                        </div>
                                        <div class="text-muted extra-small">
                                            <i class="fa-solid fa-building text-info me-1"></i><?= e($rev['project_title']) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-3">
                                    <div><?= render_star_rating($rev['rating'], true) ?></div>
                                    <span class="text-muted extra-small"><?= format_date($rev['created_at']) ?></span>
                                </div>
                            </div>
                            <p class="text-light extra-small mb-0 mt-2 bg-secondary bg-opacity-10 p-2.5 rounded-3 border border-secondary border-opacity-30">
                                "<span class="fst-italic"><?= e($rev['review_text']) ?></span>"
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center pt-3 border-top border-secondary mt-3">
                        <span class="text-muted extra-small">Showing Page <?= $page ?> of <?= $total_pages ?> (Total <?= $total_rows ?> Reviews)</span>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= BASE_URL ?>/client/reviews.php?page=<?= $page - 1 ?>&project_id=<?= $filter_project_id ?>&rating=<?= $filter_rating ?>&q=<?= urlencode($search) ?>">Prev</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= BASE_URL ?>/client/reviews.php?page=<?= $i ?>&project_id=<?= $filter_project_id ?>&rating=<?= $filter_rating ?>&q=<?= urlencode($search) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= BASE_URL ?>/client/reviews.php?page=<?= $page + 1 ?>&project_id=<?= $filter_project_id ?>&rating=<?= $filter_rating ?>&q=<?= urlencode($search) ?>">Next</a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Submit New Review Modal -->
        <div class="modal fade" id="newReviewModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="modal-header border-0 bg-dark p-3 px-4">
                        <h5 class="modal-title fw-bold text-white fs-6 mb-0">
                            <i class="fa-solid fa-star text-warning me-2"></i>Write Contractor Review
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="<?= BASE_URL ?>/client/reviews.php" method="POST" data-loading="true">
                        <?= csrf_field() ?>
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label extra-small fw-bold text-muted">SELECT PROJECT & CONTRACTOR</label>
                                <select name="project_id" class="form-select rounded-3" required>
                                    <option value="">-- Choose Project --</option>
                                    <?php foreach ($client_projects as $cp): ?>
                                        <option value="<?= $cp['id'] ?>">
                                            <?= e($cp['title']) ?> (<?= e($cp['contractor_company'] ?? $cp['contractor_name']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label extra-small fw-bold text-muted">RATING (1 TO 5 STARS)</label>
                                <select name="rating" class="form-select rounded-3" required>
                                    <option value="5">★★★★★ 5 Stars - Exceptional</option>
                                    <option value="4">★★★★☆ 4 Stars - Very Good</option>
                                    <option value="3">★★★☆☆ 3 Stars - Average</option>
                                    <option value="2">★★☆☆☆ 2 Stars - Below Expectations</option>
                                    <option value="1">★☆☆☆☆ 1 Star - Poor Performance</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label extra-small fw-bold text-muted">DETAILED FEEDBACK TEXT</label>
                                <textarea name="review_text" rows="4" class="form-control rounded-3" placeholder="Describe quality of work, adherence to deadlines, safety compliance, and communication..." required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer border-top bg-dark p-3 px-4">
                            <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-orange text-dark fw-bold px-4">Submit Review</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
