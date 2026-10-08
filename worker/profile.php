<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('worker');

$page_title = "My Worker Profile - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$user_id = (int)$user['id'];
$flash = get_flash_message();

// Fetch Worker Profile
$stmt = $db->prepare("SELECT * FROM workers WHERE user_id = ?");
$stmt->execute([$user_id]);
$worker = $stmt->fetch();
$worker_profile_id = $worker ? (int)$worker['id'] : 0;

// Fetch Skills
$stmt_s = $db->prepare("
    SELECT ws.*, s.name as skill_name, s.category 
    FROM worker_skills ws 
    JOIN skills s ON ws.skill_id = s.id 
    WHERE ws.worker_id = ?
");
$stmt_s->execute([$worker_profile_id]);
$skills = $stmt_s->fetchAll();

// Fetch Experience
$stmt_exp = $db->prepare("SELECT * FROM worker_experience WHERE worker_id = ? ORDER BY start_date DESC");
$stmt_exp->execute([$worker_profile_id]);
$experiences = $stmt_exp->fetchAll();

// Fetch Rating Summary & Published Reviews
$rating_summary = calculate_user_rating_summary($user_id);

$rev_stmt = $db->prepare("
    SELECT r.*, u.name as contractor_name, c_co.company_name, p.title as project_title
    FROM reviews r
    JOIN users u ON r.reviewer_id = u.id
    LEFT JOIN contractors c_co ON u.id = c_co.user_id
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
                    <i class="fa-solid fa-id-card text-warning me-2"></i>My Worker Profile & Reputation
                </h1>
                <p class="text-muted small mb-0">Manage your trade credentials, reviews, ratings, and experience.</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-warning btn-sm fw-bold" onclick="getAIProfileSuggestions()">
                    <i class="fa-solid fa-wand-magic-sparkles me-1"></i> Improve Profile with AI
                </button>
                <a href="<?= BASE_URL ?>/worker/edit-profile.php" class="btn btn-amber btn-sm fw-bold">
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
            <!-- Left Profile Card -->
            <div class="col-lg-4">
                <div class="bc-card p-4 text-center mb-4">
                    <div class="position-relative d-inline-block mb-3">
                        <img src="<?= BASE_URL ?>/assets/images/<?= !empty($user['avatar']) ? e($user['avatar']) : 'default_avatar.png' ?>" 
                             alt="<?= e($user['name']) ?>" 
                             class="rounded-circle border border-warning shadow" 
                             style="width: 110px; height: 110px; object-fit: cover;"
                             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['name']) ?>&background=10b981&color=fff&size=128';">
                    </div>
                    
                    <h3 class="h4 text-white fw-bold mb-1"><?= e($user['name']) ?></h3>
                    <div class="text-warning fw-semibold mb-2">
                        <i class="fa-solid fa-helmet-safety me-1"></i><?= e($worker['trade_title'] ?? 'General Specialist') ?>
                    </div>
                    
                    <div class="d-flex justify-content-center gap-2 mb-3">
                        <span class="badge <?= get_status_badge_class($worker['verification_status'] ?? 'pending') ?> text-uppercase font-monospace px-3 py-1">
                            <i class="fa-solid fa-shield-halved me-1"></i><?= e($worker['verification_status'] ?? 'pending') ?>
                        </span>
                        <span class="badge bg-dark border border-secondary text-info font-monospace px-3 py-1">
                            <i class="fa-solid fa-clock me-1"></i><?= e($worker['availability_status'] ?? 'available') ?>
                        </span>
                    </div>

                    <hr class="border-secondary my-3">

                    <div class="text-start space-y-2">
                        <div class="d-flex justify-content-between text-muted small py-1">
                            <span>Experience:</span>
                            <span class="text-white fw-semibold"><?= (int)($worker['experience_years'] ?? 0) ?> Years</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted small py-1">
                            <span>Hourly Rate:</span>
                            <span class="text-warning font-monospace fw-bold"><?= format_currency($worker['hourly_rate'] ?? 35) ?> / hr</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted small py-1">
                            <span>Daily Rate:</span>
                            <span class="text-warning font-monospace fw-bold"><?= format_currency($worker['daily_rate'] ?? 280) ?> / day</span>
                        </div>
                        <div class="d-flex justify-content-between text-muted small py-1">
                            <span>Rating Summary:</span>
                            <span class="text-warning fw-bold">
                                <?= render_star_rating($rating_summary['avg_rating']) ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between text-muted small py-1">
                            <span>Total Reviews:</span>
                            <span class="text-white fw-bold"><?= $rating_summary['total_reviews'] ?> reviews</span>
                        </div>
                    </div>
                </div>

                <!-- Rating Distribution Breakdown -->
                <div class="bc-card p-4">
                    <h5 class="fw-bold text-white mb-3"><i class="fa-solid fa-star text-warning me-2"></i>Rating Breakdown</h5>
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

            <!-- Right Profile Information -->
            <div class="col-lg-8">
                <div class="bc-card p-4 mb-4">
                    <h3 class="h5 text-white fw-bold mb-4">
                        <i class="fa-solid fa-user-gear text-warning me-2"></i>Personal & Trade Information
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
                            <label class="text-muted extra-small text-uppercase fw-semibold d-block">City / Location</label>
                            <span class="text-white fs-6"><?= !empty($worker['city']) ? e($worker['city']) : 'Not specified' ?> <?= !empty($worker['state']) ? ', ' . e($worker['state']) : '' ?></span>
                        </div>

                        <div class="col-12">
                            <label class="text-muted extra-small text-uppercase fw-semibold d-block">Professional Bio & Scope</label>
                            <p class="text-light opacity-90 mt-1 mb-0">
                                <?= !empty($worker['bio']) ? nl2br(e($worker['bio'])) : 'No biography provided yet.' ?>
                            </p>
                        </div>
                    </div>

                    <!-- Skills Section Overview -->
                    <div class="border-top border-secondary pt-3 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h4 class="h6 text-white fw-bold mb-0">
                                <i class="fa-solid fa-screwdriver-wrench me-2 text-warning"></i>Trade Skills & Specializations
                            </h4>
                            <a href="<?= BASE_URL ?>/worker/skills.php" class="btn btn-outline-secondary btn-sm extra-small">Manage Skills</a>
                        </div>
                        <?php if (empty($skills)): ?>
                            <span class="text-muted extra-small">No skills added yet.</span>
                        <?php else: ?>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <?php foreach ($skills as $sk): ?>
                                    <span class="badge bg-dark border border-secondary text-warning px-3 py-2">
                                        <?= e($sk['skill_name']) ?> <small class="text-muted"> (<?= e($sk['proficiency_level']) ?>)</small>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Work History Overview -->
                    <div class="border-top border-secondary pt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h4 class="h6 text-white fw-bold mb-0">
                                <i class="fa-solid fa-briefcase me-2 text-info"></i>Work History Highlights
                            </h4>
                            <a href="<?= BASE_URL ?>/worker/experience.php" class="btn btn-outline-secondary btn-sm extra-small">Manage Experience</a>
                        </div>
                        <?php if (empty($experiences)): ?>
                            <span class="text-muted extra-small">No work experience entries listed.</span>
                        <?php else: ?>
                            <div class="list-group list-group-flush bg-transparent">
                                <?php foreach ($experiences as $ex): ?>
                                    <div class="list-group-item bg-transparent text-light border-secondary px-0 py-2">
                                        <div class="d-flex justify-content-between">
                                            <strong class="text-white"><?= e($ex['job_title']) ?></strong>
                                            <span class="text-muted extra-small">
                                                <?= format_date($ex['start_date'], 'M Y') ?> - <?= $ex['is_current'] ? '<span class="text-success">Present</span>' : format_date($ex['end_date'], 'M Y') ?>
                                            </span>
                                        </div>
                                        <div class="text-warning extra-small fw-semibold"><?= e($ex['company_name']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Contractor Reviews Received -->
                <div class="bc-card p-4">
                    <h4 class="h6 text-white fw-bold mb-3">
                        <i class="fa-solid fa-comments text-warning me-2"></i>Reviews Received from Contractors (<?= count($reviews_received) ?>)
                    </h4>

                    <?php if (empty($reviews_received)): ?>
                        <div class="text-muted extra-small py-3">No reviews received from contractors yet.</div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($reviews_received as $rev): ?>
                                <div class="bg-dark p-3 rounded-3 border border-secondary">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <div class="fw-bold text-white small"><?= sanitize($rev['company_name'] ?: $rev['contractor_name']) ?></div>
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
        <!-- AI Profile Improvement Container -->
        <div id="ai_profile_box" class="bc-card p-4 mb-4 border-warning" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h6 text-warning fw-bold mb-0"><i class="fa-solid fa-brain me-2"></i>AI Profile Optimization Recommendations</h3>
                <button type="button" class="btn-close btn-close-white" onclick="document.getElementById('ai_profile_box').style.display='none'"></button>
            </div>
            <div id="ai_profile_content" class="small text-light"></div>
        </div>

    </main>
</div>

<script>
function getAIProfileSuggestions() {
    const box = document.getElementById('ai_profile_box');
    const content = document.getElementById('ai_profile_content');
    box.style.display = 'block';
    content.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Analyzing your profile credentials...';

    fetch('<?= BASE_URL ?>/api/ai.php?action=worker_profile_assistance')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data && data.data.suggestions) {
                let html = '<ul class="list-group list-group-flush bg-transparent mb-0">';
                data.data.suggestions.forEach(s => {
                    html += `<li class="list-group-item bg-transparent text-light border-secondary px-0 py-2 d-flex align-items-start gap-2">
                        <i class="fa-solid fa-circle-check text-success mt-1 fs-7"></i>
                        <span>${s}</span>
                    </li>`;
                });
                html += '</ul>';
                content.innerHTML = html;
            } else {
                content.innerHTML = '<span class="text-danger">AI profile analysis is temporarily unavailable.</span>';
            }
        })
        .catch(err => {
            content.innerHTML = '<span class="text-danger">AI service request timed out.</span>';
        });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
