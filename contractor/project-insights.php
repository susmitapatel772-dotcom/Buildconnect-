<?php
$page_title = "AI Project Risk Insights - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_auth();
require_once __DIR__ . '/../includes/ai.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = get_logged_user();
$db = getDB();
$is_admin = ($user['role'] === ROLE_ADMIN);

// Fetch Contractor / Accessible Projects
if ($is_admin) {
    $stmt = $db->query("SELECT id, title, city, progress_percent, status FROM projects ORDER BY id DESC");
} else {
    $stmt = $db->prepare("SELECT id, title, city, progress_percent, status FROM projects WHERE contractor_id = ? ORDER BY id DESC");
    $stmt->execute([$user['id']]);
}
$projects = $stmt->fetchAll();

$selected_project_id = (int)($_GET['project_id'] ?? ($projects[0]['id'] ?? 0));
$insights = null;
$error_msg = null;

if ($selected_project_id > 0) {
    try {
        $insights = ai_analyze_project_insights($selected_project_id, $user['id'], $is_admin);
    } catch (Exception $e) {
        $error_msg = $e->getMessage();
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
                    <i class="fa-solid fa-brain text-warning me-2"></i>AI Project Health & Risk Insights
                </h1>
                <p class="text-muted small mb-0">Automated diagnostic evaluation of task deadlines, milestone delivery schedules, and site progress risks.</p>
            </div>
        </div>

        <!-- Project Filter Bar -->
        <div class="bc-card p-3 mb-4">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-9">
                    <label class="form-label extra-small text-muted mb-1">Select Project for Risk Analysis</label>
                    <select name="project_id" class="form-select form-select-sm bg-dark text-white border-secondary" onchange="this.form.submit()">
                        <?php if (empty($projects)): ?>
                            <option value="0">No projects available</option>
                        <?php else: ?>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $selected_project_id === (int)$p['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($p['title']) ?> (<?= sanitize($p['city']) ?>) — Progress: <?= $p['progress_percent'] ?>%
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-self-end">
                    <button type="submit" class="btn btn-warning btn-sm w-100"><i class="fa-solid fa-microchip me-1"></i> Analyze Health</button>
                </div>
            </form>
        </div>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger mb-4"><?= sanitize($error_msg) ?></div>
        <?php endif; ?>

        <?php if ($insights): ?>
            <?php
            $health_badge = 'bg-success';
            $border_color = 'border-success';
            if ($insights['health'] === 'Needs Attention') {
                $health_badge = 'bg-warning text-dark';
                $border_color = 'border-warning';
            } elseif ($insights['health'] === 'At Risk') {
                $health_badge = 'bg-danger';
                $border_color = 'border-danger';
            }
            ?>

            <!-- Primary Diagnosis Banner -->
            <div class="bc-card p-4 mb-4 <?= $border_color ?>">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                    <div>
                        <div class="extra-small text-muted text-uppercase fw-bold mb-1">PROJECT HEALTH DIAGNOSIS</div>
                        <h2 class="h3 text-white fw-bold mb-1"><?= sanitize($insights['title']) ?></h2>
                        <div class="d-flex align-items-center gap-3 mt-2">
                            <span class="badge <?= $health_badge ?> fs-6 text-uppercase px-3 py-2">
                                <i class="fa-solid fa-heart-pulse me-1"></i><?= sanitize($insights['health']) ?>
                            </span>
                            <span class="badge bg-dark border border-secondary text-muted extra-small">
                                Risk Level: <strong class="text-white text-uppercase"><?= sanitize($insights['risk_level']) ?></strong>
                            </span>
                        </div>
                    </div>
                    <div class="text-md-end w-100 w-md-auto" style="min-width: 220px;">
                        <div class="extra-small text-muted mb-1">Site Completion Progress</div>
                        <div class="display-6 fw-bold text-warning font-monospace mb-2"><?= $insights['progress_percent'] ?>%</div>
                        <div class="progress bg-secondary" style="height: 8px;">
                            <div class="progress-bar bg-warning" style="width: <?= $insights['progress_percent'] ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Observations & Recommendations Grid -->
            <div class="row g-4 mb-4">
                <!-- Observations -->
                <div class="col-lg-6">
                    <div class="bc-card p-4 h-100">
                        <h3 class="h6 text-white fw-bold mb-3">
                            <i class="fa-solid fa-eye text-info me-2"></i>Key AI Observations
                        </h3>
                        <ul class="list-group list-group-flush bg-transparent">
                            <?php foreach ($insights['observations'] as $obs): ?>
                                <li class="list-group-item bg-transparent text-light border-secondary px-0 py-2 d-flex align-items-start gap-2">
                                    <i class="fa-solid fa-circle-dot text-warning mt-1 fs-7"></i>
                                    <span><?= sanitize($obs) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <!-- Recommendations -->
                <div class="col-lg-6">
                    <div class="bc-card p-4 h-100">
                        <h3 class="h6 text-white fw-bold mb-3">
                            <i class="fa-solid fa-lightbulb text-warning me-2"></i>Recommended Actions
                        </h3>
                        <ul class="list-group list-group-flush bg-transparent">
                            <?php foreach ($insights['recommendations'] as $rec): ?>
                                <li class="list-group-item bg-transparent text-light border-secondary px-0 py-2 d-flex align-items-start gap-2">
                                    <i class="fa-solid fa-circle-check text-success mt-1 fs-7"></i>
                                    <span><?= sanitize($rec) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Executive AI Summary Box -->
            <div class="bc-card p-4">
                <h3 class="h6 text-white fw-bold mb-2">
                    <i class="fa-solid fa-file-lines text-success me-2"></i>Executive Project Summary
                </h3>
                <p class="text-muted small mb-0"><?= sanitize($insights['summary']) ?></p>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
