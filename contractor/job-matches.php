<?php
$page_title = "AI Worker Matching - Contractor - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_role(ROLE_CONTRACTOR);
require_once __DIR__ . '/../includes/ai.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = get_logged_user();
$db = getDB();

// Fetch Contractor's Jobs for Dropdown
$stmt = $db->prepare("
    SELECT j.id, j.title, p.title as project_title, j.trade_required, j.spots_available, j.spots_filled
    FROM jobs j
    JOIN projects p ON j.project_id = p.id
    WHERE j.contractor_id = ? AND j.status IN ('published', 'open')
    ORDER BY j.id DESC
");
$stmt->execute([$user['id']]);
$jobs = $stmt->fetchAll();

$selected_job_id = (int)($_GET['job_id'] ?? ($jobs[0]['id'] ?? 0));
$force_refresh = !empty($_GET['refresh']);

$matches = [];
$error_msg = null;
$selected_job = null;

if ($selected_job_id > 0) {
    foreach ($jobs as $j) {
        if ((int)$j['id'] === $selected_job_id) {
            $selected_job = $j;
            break;
        }
    }

    if ($selected_job) {
        try {
            $matches = ai_match_workers_for_job($selected_job_id, $user['id'], $force_refresh);
        } catch (Exception $e) {
            $error_msg = $e->getMessage();
        }
    }
}
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <!-- Page Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-wand-magic-sparkles text-warning me-2"></i>AI Worker Matching Assistant
                </h1>
                <p class="text-muted small mb-0">Evaluate candidates based on trade skills, experience, availability, and rating records.</p>
            </div>
            <?php if ($selected_job_id > 0): ?>
                <a href="<?= BASE_URL ?>/contractor/job-matches.php?job_id=<?= $selected_job_id ?>&refresh=1" class="btn btn-outline-warning btn-sm">
                    <i class="fa-solid fa-rotate me-1"></i> Refresh AI Analysis
                </a>
            <?php endif; ?>
        </div>

        <!-- Job Selection Bar -->
        <div class="bc-card p-3 mb-4">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-9">
                    <label class="form-label extra-small text-muted mb-1">Select Active Job Post</label>
                    <select name="job_id" class="form-select form-select-sm bg-dark text-white border-secondary" onchange="this.form.submit()">
                        <?php if (empty($jobs)): ?>
                            <option value="0">No active job postings found</option>
                        <?php else: ?>
                            <?php foreach ($jobs as $j): ?>
                                <option value="<?= $j['id'] ?>" <?= $selected_job_id === (int)$j['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($j['title']) ?> (<?= sanitize($j['project_title']) ?>) — Trade: <?= sanitize($j['trade_required']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-self-end">
                    <button type="submit" class="btn btn-warning btn-sm w-100"><i class="fa-solid fa-brain me-1"></i> Analyze Matches</button>
                </div>
            </form>
        </div>

        <!-- Notice Banner -->
        <div class="alert alert-dark border-secondary d-flex align-items-center mb-4 small text-muted">
            <i class="fa-solid fa-shield-halved text-warning fs-4 me-3"></i>
            <div>
                <strong class="text-white">AI-Assisted Recommendations:</strong> Match scores evaluate professional skills, experience, availability, and ratings. Final hiring decisions remain under human contractor responsibility.
            </div>
        </div>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger mb-4"><?= sanitize($error_msg) ?></div>
        <?php endif; ?>

        <?php if ($selected_job): ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h5 text-white fw-bold mb-0">
                    Top Candidates for "<span class="text-warning"><?= sanitize($selected_job['title']) ?></span>"
                </h3>
                <span class="badge bg-secondary font-monospace">Spots: <?= $selected_job['spots_filled'] ?> / <?= $selected_job['spots_available'] ?></span>
            </div>

            <?php if (empty($matches)): ?>
                <div class="bc-card p-5 text-center">
                    <i class="fa-solid fa-user-slash fs-1 text-muted mb-3"></i>
                    <h3 class="h5 text-white fw-bold">No Matching Workers Found</h3>
                    <p class="text-muted small mb-0">AI matching engine found no active candidates matching the required trade skills.</p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($matches as $m): ?>
                        <?php
                        $badge_class = 'bg-success';
                        if ($m['match_score'] < 70) $badge_class = 'bg-warning text-dark';
                        if ($m['match_score'] < 55) $badge_class = 'bg-secondary';
                        ?>
                        <div class="col-lg-6">
                            <div class="bc-card p-4 h-100 border-secondary position-relative">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h4 class="h5 text-white fw-bold mb-1"><?= sanitize($m['name']) ?></h4>
                                        <div class="text-warning extra-small fw-semibold">
                                            <i class="fa-solid fa-hammer me-1"></i><?= sanitize($m['trade_title']) ?>
                                            <span class="text-muted ms-2"><i class="fa-solid fa-location-dot me-1"></i><?= sanitize($m['city']) ?></span>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge <?= $badge_class ?> fs-6 font-monospace mb-1"><?= $m['match_score'] ?>% Match</span>
                                        <div class="extra-small text-muted font-monospace"><?= sanitize($m['recommendation']) ?></div>
                                    </div>
                                </div>

                                <div class="row g-2 mb-3 extra-small">
                                    <div class="col-4">
                                        <div class="bg-dark p-2 rounded border border-secondary text-center">
                                            <span class="text-muted d-block">Experience</span>
                                            <strong class="text-white"><?= $m['experience_years'] ?> Years</strong>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="bg-dark p-2 rounded border border-secondary text-center">
                                            <span class="text-muted d-block">Rating</span>
                                            <strong class="text-warning">★ <?= number_format($m['rating_avg'], 1) ?></strong>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="bg-dark p-2 rounded border border-secondary text-center">
                                            <span class="text-muted d-block">Verification</span>
                                            <strong class="<?= $m['verification_status'] === 'approved' ? 'text-success' : 'text-warning' ?>">
                                                <?= ucfirst($m['verification_status']) ?>
                                            </strong>
                                        </div>
                                    </div>
                                </div>

                                <!-- AI Match Strengths -->
                                <?php if (!empty($m['strengths'])): ?>
                                    <div class="mb-2">
                                        <div class="extra-small text-success fw-bold mb-1">Key Strengths:</div>
                                        <ul class="list-unstyled extra-small text-muted mb-0">
                                            <?php foreach ($m['strengths'] as $st): ?>
                                                <li><i class="fa-solid fa-check text-success me-1"></i><?= sanitize($st) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <!-- AI Match Concerns -->
                                <?php if (!empty($m['concerns'])): ?>
                                    <div class="mb-3">
                                        <div class="extra-small text-warning fw-bold mb-1">Considerations:</div>
                                        <ul class="list-unstyled extra-small text-muted mb-0">
                                            <?php foreach ($m['concerns'] as $cn): ?>
                                                <li><i class="fa-solid fa-circle-info text-warning me-1"></i><?= sanitize($cn) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex justify-content-between align-items-center pt-2 border-top border-secondary mt-auto">
                                    <span class="extra-small text-muted">
                                        <?= $m['is_cached'] ? '<i class="fa-solid fa-clock-rotate-left me-1"></i>Cached Analysis' : '<i class="fa-solid fa-bolt me-1 text-warning"></i>Live AI Result' ?>
                                    </span>
                                    <div class="d-flex gap-2">
                                        <a href="<?= BASE_URL ?>/contractor/create-contract.php?worker_id=<?= $m['worker_id'] ?>&job_id=<?= $selected_job_id ?>" class="btn btn-warning btn-sm extra-small">
                                            <i class="fa-solid fa-file-signature me-1"></i> Offer Contract
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
