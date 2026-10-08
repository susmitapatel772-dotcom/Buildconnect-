<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('contractor');

$page_title = "Contractor Profile - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$user_id = (int)$user['id'];

$flash = get_flash_message();

// Fetch contractor company profile
$stmt = $db->prepare("SELECT * FROM contractors WHERE user_id = ?");
$stmt->execute([$user_id]);
$contractor = $stmt->fetch();

// Calculate contractor rating summary
$rating_summary = calculate_user_rating_summary($user_id);

// Projects count
$p_cnt_stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE contractor_id = ? AND status = 'completed'");
$p_cnt_stmt->execute([$user_id]);
$completed_projects_count = (int)$p_cnt_stmt->fetchColumn();

// Fetch reviews received by contractor from workers
$rev_stmt = $db->prepare("
    SELECT r.*, u.name as worker_name, p.title as project_title, w.trade_title
    FROM reviews r
    JOIN users u ON r.reviewer_id = u.id
    LEFT JOIN workers w ON u.id = w.user_id
    JOIN projects p ON r.project_id = p.id
    WHERE r.reviewee_id = ? AND r.status = 'published'
    ORDER BY r.id DESC
");
$rev_stmt->execute([$user_id]);
$reviews_received = $rev_stmt->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-user-gear text-warning me-2"></i>Contractor Profile & Reputation
                </h1>
                <p class="text-muted small mb-0">View company details, verification credentials, project achievements, and reviews.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/contractor/edit-profile.php" class="btn btn-amber btn-sm fw-bold">
                    <i class="fa-solid fa-pen-to-square me-1"></i> Edit Profile
                </a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show mb-4" role="alert">
                <i class="fa-solid fa-circle-info me-2"></i> <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Profile Summary Card -->
            <div class="col-lg-4">
                <div class="bc-card p-4 text-center mb-4">
                    <div class="position-relative d-inline-block mb-3">
                        <img src="<?= BASE_URL ?>/assets/images/<?= !empty($user['avatar']) ? e($user['avatar']) : 'default_avatar.png' ?>" 
                             alt="<?= e($user['name']) ?>" 
                             class="rounded-circle border border-warning shadow" 
                             style="width: 110px; height: 110px; object-fit: cover;"
                             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['name']) ?>&background=f59e0b&color=0f172a&size=128';">
                    </div>
                    
                    <h3 class="h4 text-white fw-bold mb-1"><?= e($user['name']) ?></h3>
                    <div class="text-warning fw-semibold mb-2">
                        <i class="fa-solid fa-building me-1"></i><?= e($contractor['company_name'] ?? 'Company Profile Pending') ?>
                    </div>
                    <span class="badge bg-warning text-dark text-uppercase font-monospace px-3 py-1 mb-3">
                        <?= e($user['role']) ?>
                    </span>

                    <hr class="border-secondary my-3">

                    <div class="text-start space-y-2">
                        <div class="d-flex justify-content-between text-muted small py-1">
                            <span>Account Status:</span>
                            <span class="badge <?= get_status_badge_class($user['status']) ?>"><?= e($user['status']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between text-muted small py-1">
                            <span>Completed Projects:</span>
                            <span class="text-success fw-bold"><?= $completed_projects_count ?> Projects</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted small py-1">
                            <span>Company Rating:</span>
                            <span class="text-warning fw-bold">
                                <?= render_star_rating($rating_summary['avg_rating']) ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between text-muted small py-1">
                            <span>Total Worker Reviews:</span>
                            <span class="text-white fw-bold"><?= $rating_summary['total_reviews'] ?> reviews</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted small py-1">
                            <span>License No:</span>
                            <span class="text-white font-monospace"><?= e($contractor['license_no'] ?? 'N/A') ?></span>
                        </div>
                    </div>
                </div>

                <!-- Rating Breakdown Card -->
                <div class="bc-card p-4">
                    <h5 class="fw-bold text-white mb-3"><i class="fa-solid fa-star text-warning me-2"></i>Worker Rating Breakdown</h5>
                    <div class="d-flex flex-column gap-2">
                        <?php for ($star = 5; $star >= 1; $star--): 
                            $cnt = $rating_summary["star_{$star}_count"];
                            $pct = $rating_summary["star_{$star}_pct"];
                        ?>
                            <div class="d-flex align-items-center extra-small">
                                <span class="text-muted me-2" style="width: 45px;"><?= $star ?> Star</span>
                                <div class="progress bg-dark border border-secondary flex-grow-1" style="height: 8px;">
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $pct ?>%;"></div>
                                </div>
                                <span class="text-muted ms-2" style="width: 35px; text-align: right;"><?= $cnt ?></span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <!-- Detailed Information Card -->
            <div class="col-lg-8">
                <div class="bc-card p-4 mb-4">
                    <h3 class="h5 text-white fw-bold mb-4">
                        <i class="fa-solid fa-id-card text-warning me-2"></i>Company & Account Details
                    </h3>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="text-muted extra-small text-uppercase fw-semibold d-block">Full Name</label>
                            <span class="text-white fs-6 fw-semibold"><?= e($user['name']) ?></span>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted extra-small text-uppercase fw-semibold d-block">Email Address</label>
                            <span class="text-white fs-6 font-monospace"><?= e($user['email']) ?></span>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted extra-small text-uppercase fw-semibold d-block">Phone Number</label>
                            <span class="text-white fs-6"><?= !empty($user['phone']) ? e($user['phone']) : 'Not specified' ?></span>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted extra-small text-uppercase fw-semibold d-block">Company Name</label>
                            <span class="text-warning fs-6 fw-bold"><?= e($contractor['company_name'] ?? 'Not specified') ?></span>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted extra-small text-uppercase fw-semibold d-block">City</label>
                            <span class="text-white fs-6"><?= !empty($contractor['city']) ? e($contractor['city']) : 'Not specified' ?></span>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted extra-small text-uppercase fw-semibold d-block">State / Region</label>
                            <span class="text-white fs-6"><?= !empty($contractor['state']) ? e($contractor['state']) : 'Not specified' ?></span>
                        </div>

                        <div class="col-12">
                            <label class="text-muted extra-small text-uppercase fw-semibold d-block">Company Address</label>
                            <span class="text-white fs-6"><?= !empty($contractor['company_address']) ? e($contractor['company_address']) : 'Not specified' ?></span>
                        </div>

                        <div class="col-12">
                            <label class="text-muted extra-small text-uppercase fw-semibold d-block">Official Website</label>
                            <?php if (!empty($contractor['website'])): ?>
                                <a href="<?= e($contractor['website']) ?>" target="_blank" rel="noopener" class="text-info text-decoration-none">
                                    <i class="fa-solid fa-globe me-1"></i><?= e($contractor['website']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">Not specified</span>
                            <?php endif; ?>
                        </div>

                        <div class="col-12">
                            <label class="text-muted extra-small text-uppercase fw-semibold d-block">Company Overview & Description</label>
                            <p class="text-light opacity-85 mt-1 mb-0">
                                <?= !empty($contractor['company_description']) ? nl2br(e($contractor['company_description'])) : 'No company description provided.' ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Reviews Received from Workers -->
                <div class="bc-card p-4">
                    <h4 class="h6 text-white fw-bold mb-3">
                        <i class="fa-solid fa-comments text-warning me-2"></i>Reviews Received from Workers (<?= count($reviews_received) ?>)
                    </h4>

                    <?php if (empty($reviews_received)): ?>
                        <div class="text-muted extra-small py-3">No reviews received from workers yet.</div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($reviews_received as $rev): ?>
                                <div class="bg-dark p-3 rounded-3 border border-secondary">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <div>
                                            <span class="fw-bold text-white small"><?= sanitize($rev['worker_name']) ?></span>
                                            <span class="text-muted extra-small me-2">(<?= sanitize($rev['trade_title'] ?? 'Worker') ?>)</span>
                                        </div>
                                        <?= render_star_rating($rev['rating']) ?>
                                    </div>
                                    <div class="text-muted extra-small mb-2"><i class="fa-solid fa-building me-1"></i>Project: <?= sanitize($rev['project_title']) ?></div>
                                    <p class="text-light extra-small mb-0">"<?= sanitize($rev['review_text']) ?>"</p>
                                    <div class="text-muted extra-small mt-2 text-end"><?= format_date($rev['created_at']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
