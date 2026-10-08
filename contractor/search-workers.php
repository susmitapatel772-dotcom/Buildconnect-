<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(ROLE_CONTRACTOR);

$page_title = "Smart Worker Finder - Contractor - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = get_logged_user();
$db = getDB();

$trade_filter = sanitize($_GET['trade'] ?? 'all');

$query = "SELECT wp.*, u.name, u.email, u.phone FROM worker_profiles wp JOIN users u ON wp.user_id = u.id WHERE u.status = 'active'";
$params = [];
if ($trade_filter !== 'all') {
    $query .= " AND wp.trade_skills LIKE ?";
    $params[] = "%" . $trade_filter . "%";
}
$query .= " ORDER BY wp.rating_avg DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$workers = $stmt->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-wand-magic-sparkles text-info me-2"></i>Smart Worker Recommendation Matrix</h1>
                <p class="text-muted small mb-0">Discover verified skilled tradespeople evaluated by AI skill and availability scoring.</p>
            </div>
            
            <div class="dropdown">
                <button class="btn btn-outline-amber btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fa-solid fa-filter me-1"></i> Trade: <?= ucfirst($trade_filter) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-dark border-secondary">
                    <li><a class="dropdown-item" href="?trade=all">All Trades</a></li>
                    <li><a class="dropdown-item" href="?trade=Welding">Structural Welding</a></li>
                    <li><a class="dropdown-item" href="?trade=Crane">Crane & Heavy Equipment</a></li>
                    <li><a class="dropdown-item" href="?trade=Electrical">Electrical</a></li>
                    <li><a class="dropdown-item" href="?trade=Masonry">Masonry</a></li>
                </ul>
            </div>
        </div>

        <div class="row g-4">
            <?php foreach ($workers as $w): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="bc-card p-4 h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h3 class="h5 text-white fw-bold mb-0"><?= sanitize($w['name']) ?></h3>
                                    <div class="text-muted extra-small"><i class="fa-solid fa-location-dot me-1 text-danger"></i><?= sanitize($w['city']) ?></div>
                                </div>
                                <span class="badge badge-ai"><i class="fa-solid fa-brain me-1"></i><?= calculate_ai_match_score($w['user_id'], 1) ?>% AI Match</span>
                            </div>

                            <div class="mb-3">
                                <span class="badge bg-secondary me-1"><?= sanitize($w['trade_skills']) ?></span>
                                <?php if ($w['verification_status'] === 'approved'): ?>
                                    <span class="badge bg-success"><i class="fa-solid fa-shield-check me-1"></i>Verified</span>
                                <?php endif; ?>
                            </div>

                            <p class="text-light extra-small opacity-75 mb-3"><?= sanitize($w['bio']) ?></p>

                            <div class="d-flex justify-content-between align-items-center bg-dark p-2 rounded-3 border border-secondary mb-3">
                                <div>
                                    <span class="text-muted extra-small d-block">Hourly Rate</span>
                                    <span class="fw-bold text-warning"><?= format_currency($w['hourly_rate']) ?>/hr</span>
                                </div>
                                <div class="text-end">
                                    <span class="text-muted extra-small d-block">Rating</span>
                                    <span class="text-warning fw-bold"><i class="fa-solid fa-star me-1"></i><?= number_format($w['rating_avg'], 1) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 border-top border-secondary">
                            <a href="<?= BASE_URL ?>/contractor/contracts.php?action=draft&worker_id=<?= $w['user_id'] ?>" class="btn btn-amber btn-sm w-100 fw-bold">
                                <i class="fa-solid fa-file-contract me-1"></i> Issue Contract Draft
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
