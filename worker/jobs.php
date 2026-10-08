<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('worker');

$page_title = "Browse Jobs - Worker - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();

// Search & Filter Parameters
$search_query = sanitize($_GET['q'] ?? '');
$filter_trade = sanitize($_GET['trade'] ?? 'all');
$filter_location = sanitize($_GET['location'] ?? '');
$filter_emp_type = sanitize($_GET['emp_type'] ?? 'all');
$filter_pay_type = sanitize($_GET['pay_type'] ?? 'all');

// Build query for published/open jobs only
$sql = "
    SELECT j.*, p.title as project_title, c.company_name as contractor_company, u.name as contractor_name
    FROM jobs j
    JOIN projects p ON j.project_id = p.id
    JOIN users u ON j.contractor_id = u.id
    LEFT JOIN contractors c ON u.id = c.user_id
    WHERE j.status IN ('published', 'open')
";
$params = [];

if (!empty($search_query)) {
    $sql .= " AND (j.title LIKE ? OR j.description LIKE ? OR j.trade_required LIKE ? OR j.location LIKE ? OR j.city LIKE ?)";
    $q_param = "%{$search_query}%";
    $params[] = $q_param;
    $params[] = $q_param;
    $params[] = $q_param;
    $params[] = $q_param;
    $params[] = $q_param;
}

if ($filter_trade !== 'all' && !empty($filter_trade)) {
    $sql .= " AND j.trade_required = ?";
    $params[] = $filter_trade;
}

if (!empty($filter_location)) {
    $sql .= " AND (j.city LIKE ? OR j.state LIKE ? OR j.location LIKE ?)";
    $loc_param = "%{$filter_location}%";
    $params[] = $loc_param;
    $params[] = $loc_param;
    $params[] = $loc_param;
}

if ($filter_emp_type !== 'all' && !empty($filter_emp_type)) {
    $sql .= " AND j.employment_type = ?";
    $params[] = $filter_emp_type;
}

if ($filter_pay_type !== 'all' && !empty($filter_pay_type)) {
    $sql .= " AND j.pay_type = ?";
    $params[] = $filter_pay_type;
}

$sql .= " ORDER BY j.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

// Get list of jobs worker has already applied to
$app_stmt = $db->prepare("SELECT job_id, status FROM job_applications WHERE worker_id = ?");
$app_stmt->execute([$user['id']]);
$applied_jobs = $app_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-briefcase text-warning me-2"></i>Trade Job Marketplace
                </h1>
                <p class="text-muted small mb-0">Browse and apply for verified trade positions posted by licensed construction contractors.</p>
            </div>
        </div>

        <!-- Search & Filter Form -->
        <div class="bc-card p-4 mb-4">
            <form action="<?= BASE_URL ?>/worker/jobs.php" method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label text-light extra-small fw-bold mb-1">Search Keywords</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-dark border-secondary text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" name="q" value="<?= e($search_query) ?>" class="form-control bg-dark border-secondary text-light" placeholder="Title, trade, or keywords...">
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label text-light extra-small fw-bold mb-1">Trade Category</label>
                    <select name="trade" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="all">All Trades</option>
                        <option value="Structural Welding" <?= $filter_trade === 'Structural Welding' ? 'selected' : '' ?>>Structural Welding</option>
                        <option value="Tower Crane Operation" <?= $filter_trade === 'Tower Crane Operation' ? 'selected' : '' ?>>Tower Crane Operation</option>
                        <option value="Commercial Electrical Wiring" <?= $filter_trade === 'Commercial Electrical Wiring' ? 'selected' : '' ?>>Commercial Electrical Wiring</option>
                        <option value="Concrete Formwork & Masonry" <?= $filter_trade === 'Concrete Formwork & Masonry' ? 'selected' : '' ?>>Concrete Formwork & Masonry</option>
                        <option value="Plumbing & High Pressure Piping" <?= $filter_trade === 'Plumbing & High Pressure Piping' ? 'selected' : '' ?>>Plumbing & High Pressure Piping</option>
                        <option value="General Construction" <?= $filter_trade === 'General Construction' ? 'selected' : '' ?>>General Construction</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label text-light extra-small fw-bold mb-1">City / Location</label>
                    <input type="text" name="location" value="<?= e($filter_location) ?>" class="form-control form-control-sm bg-dark border-secondary text-light" placeholder="e.g. Ahmedabad">
                </div>

                <div class="col-md-2 d-grid align-self-end">
                    <button type="submit" class="btn btn-amber btn-sm fw-bold">
                        <i class="fa-solid fa-filter me-1"></i> Search Jobs
                    </button>
                </div>
            </form>
        </div>

        <!-- Job Cards Grid / List -->
        <?php if (empty($jobs)): ?>
            <div class="bc-card p-5 text-center my-4">
                <i class="fa-solid fa-briefcase-blank fs-1 text-muted mb-3"></i>
                <h3 class="h5 text-white fw-bold">No Vacancies Found</h3>
                <p class="text-muted small mb-0">No active job listings match your search criteria. Try expanding your filters or search keywords.</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($jobs as $j): ?>
                    <?php
                    $is_applied = isset($applied_jobs[$j['id']]);
                    $app_status = $is_applied ? $applied_jobs[$j['id']] : null;
                    $is_filled = $j['spots_filled'] >= $j['spots_available'];
                    ?>
                    <div class="col-lg-6">
                        <div class="bc-card p-4 h-100 d-flex flex-column justify-content-between border-hover-amber">
                            <div>
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                    <div>
                                        <span class="badge bg-dark border border-secondary text-warning extra-small mb-1"><?= e($j['trade_required']) ?></span>
                                        <h2 class="h5 text-white fw-bold mb-1">
                                            <a href="<?= BASE_URL ?>/worker/job-details.php?id=<?= $j['id'] ?>" class="text-white text-decoration-none hover-amber">
                                                <?= e($j['title']) ?>
                                            </a>
                                        </h2>
                                        <div class="text-muted small">
                                            <i class="fa-solid fa-building me-1 text-info"></i><?= e($j['contractor_company'] ?: $j['contractor_name']) ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="text-warning font-monospace fw-bold fs-5"><?= format_currency($j['pay_rate']) ?></div>
                                        <div class="text-muted extra-small">/ <?= e($j['pay_type']) ?></div>
                                    </div>
                                </div>

                                <p class="text-light extra-small line-clamp-2 mb-3">
                                    <?= e($j['description']) ?>
                                </p>

                                <div class="row g-2 extra-small text-muted mb-3">
                                    <div class="col-6">
                                        <i class="fa-solid fa-location-dot text-danger me-1"></i><?= e($j['city'] ? "{$j['city']}, {$j['state']}" : $j['location']) ?>
                                    </div>
                                    <div class="col-6">
                                        <i class="fa-solid fa-clock text-amber me-1"></i><?= e($j['employment_type']) ?>
                                    </div>
                                    <div class="col-6">
                                        <i class="fa-solid fa-calendar me-1"></i>Start: <?= format_date($j['start_date']) ?>
                                    </div>
                                    <div class="col-6">
                                        <i class="fa-solid fa-users text-info me-1"></i>Openings: <?= (int)$j['spots_available'] - (int)$j['spots_filled'] ?> of <?= (int)$j['spots_available'] ?>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-3 border-top border-secondary d-flex justify-content-between align-items-center">
                                <span class="text-muted extra-small">Posted <?= format_date($j['created_at']) ?></span>

                                <?php if ($is_applied): ?>
                                    <span class="badge <?= get_status_badge_class($app_status) ?> py-2 px-3">
                                        <i class="fa-solid fa-check-circle me-1"></i> Applied (<?= ucfirst($app_status) ?>)
                                    </span>
                                <?php elseif ($is_filled): ?>
                                    <span class="badge bg-secondary py-2 px-3 text-muted">Position Filled</span>
                                <?php else: ?>
                                    <a href="<?= BASE_URL ?>/worker/job-details.php?id=<?= $j['id'] ?>" class="btn btn-amber btn-sm fw-bold px-3">
                                        View & Apply <i class="fa-solid fa-arrow-right ms-1"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
