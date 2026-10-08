<?php
$page_title = "Project Progress Analytics - Client - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_role(ROLE_CLIENT);
require_once __DIR__ . '/../includes/analytics-functions.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = get_logged_user();
$db = getDB();

// Client Projects for Filter Dropdown
$c_projects = $db->prepare("SELECT id, title FROM projects WHERE client_id = ? ORDER BY title ASC");
$c_projects->execute([$user['id']]);
$client_projects = $c_projects->fetchAll();

// Handle Filters
$project_id = (int)($_GET['project_id'] ?? 0);
$filters = ['project_id' => $project_id];

$analytics = get_client_analytics_data($user['id'], $filters);
$kpis = $analytics['kpis'];
$projects = $analytics['projects'];
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <!-- Page Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-chart-line text-warning me-2"></i>Project Progress Analytics
                </h1>
                <p class="text-muted small mb-0">Track real-time construction progress, milestone deliveries, and contractor performance.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/api/export-report.php?report=projects&<?= http_build_query($filters) ?>" class="btn btn-outline-warning btn-sm">
                    <i class="fa-solid fa-file-csv me-1"></i> Export Progress CSV
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="bc-card p-3 mb-4">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-8">
                    <label class="form-label extra-small text-muted mb-1">Select Project</label>
                    <select name="project_id" class="form-select form-select-sm bg-dark text-white border-secondary">
                        <option value="0">All My Projects</option>
                        <?php foreach ($client_projects as $cp): ?>
                            <option value="<?= $cp['id'] ?>" <?= $project_id === (int)$cp['id'] ? 'selected' : '' ?>><?= sanitize($cp['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2 align-self-end">
                    <button type="submit" class="btn btn-warning btn-sm w-100"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                    <a href="<?= BASE_URL ?>/client/analytics.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate me-1"></i> Reset</a>
                </div>
            </form>
        </div>

        <!-- KPIs -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="bc-card p-3 text-center h-100">
                    <div class="text-muted extra-small text-uppercase fw-semibold mb-1">Total Projects</div>
                    <div class="display-6 fw-bold text-warning font-monospace mb-1"><?= number_format($kpis['total_projects']) ?></div>
                    <div class="extra-small text-muted"><?= number_format($kpis['active_projects']) ?> Active | <?= number_format($kpis['completed_projects']) ?> Completed</div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="bc-card p-3 text-center h-100">
                    <div class="text-muted extra-small text-uppercase fw-semibold mb-1">Avg Site Completion</div>
                    <div class="display-6 fw-bold text-info font-monospace mb-1"><?= $kpis['avg_progress_percent'] ?>%</div>
                    <div class="extra-small text-muted">Across All Projects</div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="bc-card p-3 text-center h-100">
                    <div class="text-muted extra-small text-uppercase fw-semibold mb-1">Completed Milestones</div>
                    <div class="display-6 fw-bold text-success font-monospace mb-1"><?= number_format($kpis['completed_milestones']) ?></div>
                    <div class="extra-small text-muted"><?= number_format($kpis['upcoming_milestones']) ?> Upcoming Milestones</div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="bc-card p-3 text-center h-100">
                    <div class="text-muted extra-small text-uppercase fw-semibold mb-1">Pending Site Tasks</div>
                    <div class="display-6 fw-bold text-emerald font-monospace mb-1"><?= number_format($kpis['pending_tasks']) ?></div>
                    <div class="extra-small text-muted">In Progress / Todo</div>
                </div>
            </div>
        </div>

        <!-- Visual Charts -->
        <div class="row g-4 mb-4">
            <!-- Project Progress Comparison -->
            <div class="col-lg-7">
                <div class="bc-card p-4 h-100">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-chart-bar text-warning me-2"></i>Project Completion Breakdown (%)</h3>
                    <canvas id="clientProgressChart" style="max-height: 260px;"></canvas>
                </div>
            </div>

            <!-- Milestone & Task Delivery Status -->
            <div class="col-lg-5">
                <div class="bc-card p-4 h-100">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-flag-checkered text-success me-2"></i>Milestone Delivery Overview</h3>
                    <canvas id="clientMilestoneChart" style="max-height: 260px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Client Projects Table -->
        <div class="bc-card p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h6 text-white fw-bold mb-0"><i class="fa-solid fa-building text-warning me-2"></i>Project Progress & Milestone Summary</h3>
            </div>

            <?php if (empty($projects)): ?>
                <div class="text-muted small py-3 text-center">No projects assigned to your client account yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0 extra-small">
                        <thead>
                            <tr>
                                <th>Project Title</th>
                                <th>Contractor</th>
                                <th>Budget</th>
                                <th>Completion Progress</th>
                                <th>Milestones (Done/Total)</th>
                                <th>Tasks (Done/Total)</th>
                                <th>Target End Date</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $p): ?>
                                <tr>
                                    <td class="fw-semibold text-white"><?= sanitize($p['title']) ?></td>
                                    <td class="text-muted"><?= sanitize($p['contractor_company'] ?? $p['contractor_name'] ?? 'Contractor') ?></td>
                                    <td class="font-monospace text-warning"><?= format_currency($p['budget']) ?></td>
                                    <td style="min-width: 140px;">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress bg-secondary flex-grow-1" style="height: 6px;">
                                                <div class="progress-bar bg-warning" style="width: <?= (int)$p['progress_percent'] ?>%;"></div>
                                            </div>
                                            <span class="font-monospace fw-bold text-warning"><?= (int)$p['progress_percent'] ?>%</span>
                                        </div>
                                    </td>
                                    <td class="font-monospace text-success"><?= $p['completed_milestones_count'] ?> / <?= $p['milestones_count'] ?></td>
                                    <td class="font-monospace text-info"><?= $p['completed_tasks_count'] ?> / <?= $p['tasks_count'] ?></td>
                                    <td class="font-monospace text-muted"><?= format_date($p['end_date']) ?></td>
                                    <td><span class="badge <?= get_status_badge_class($p['status']) ?>"><?= strtoupper($p['status']) ?></span></td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>/client/project-details.php?id=<?= $p['id'] ?>" class="btn btn-outline-warning btn-sm extra-small">
                                            Details <i class="fa-solid fa-arrow-right ms-1"></i>
                                        </a>
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

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Project Progress Chart
    const titles = <?= json_encode(array_column($projects, 'title')) ?>;
    const progress = <?= json_encode(array_column($projects, 'progress_percent')) ?>;

    new Chart(document.getElementById('clientProgressChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: titles.length ? titles : ['No Projects'],
            datasets: [{
                label: 'Progress %',
                data: progress.length ? progress : [0],
                backgroundColor: '#3b82f6'
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { min: 0, max: 100, ticks: { color: '#94a3b8' } }, x: { ticks: { color: '#94a3b8' } } }
        }
    });

    // Milestone Overview Chart
    new Chart(document.getElementById('clientMilestoneChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Completed Milestones', 'Upcoming Milestones'],
            datasets: [{
                data: [<?= $kpis['completed_milestones'] ?>, <?= $kpis['upcoming_milestones'] ?>],
                backgroundColor: ['#10b981', '#f59e0b']
            }]
        },
        options: { plugins: { legend: { labels: { color: '#94a3b8' } } } }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
