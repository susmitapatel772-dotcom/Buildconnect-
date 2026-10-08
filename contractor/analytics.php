<?php
$page_title = "Business & Project Analytics - Contractor - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_role(ROLE_CONTRACTOR);
require_once __DIR__ . '/../includes/analytics-functions.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = get_logged_user();
$db = getDB();

// Contractor's projects list for filter dropdown
$c_projects = $db->prepare("SELECT id, title FROM projects WHERE contractor_id = ? ORDER BY title ASC");
$c_projects->execute([$user['id']]);
$contractor_projects = $c_projects->fetchAll();

// Handle filters
$date_range = sanitize($_GET['date_range'] ?? 'all');
$start_date = sanitize($_GET['start_date'] ?? '');
$end_date = sanitize($_GET['end_date'] ?? '');
$project_id = (int)($_GET['project_id'] ?? 0);
$status = sanitize($_GET['status'] ?? '');

$filters = [
    'date_range' => $date_range,
    'start_date' => $start_date,
    'end_date' => $end_date,
    'project_id' => $project_id,
    'status' => $status
];

$analytics = get_contractor_analytics_data($user['id'], $filters);
$kpis = $analytics['kpis'];
$proj_perf = $analytics['project_performance'];
$job_perf = $analytics['job_performance'];
$workforce = $analytics['workforce_analytics'];
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <!-- Page Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-chart-line text-warning me-2"></i>Business & Project Analytics
                </h1>
                <p class="text-muted small mb-0">Project delivery progress, workforce productivity, job hiring funnels, and attendance tracking.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= BASE_URL ?>/api/export-report.php?report=projects&<?= http_build_query($filters) ?>" class="btn btn-outline-warning btn-sm">
                    <i class="fa-solid fa-file-csv me-1"></i> Export Projects CSV
                </a>
                <a href="<?= BASE_URL ?>/api/export-report.php?report=attendance&<?= http_build_query($filters) ?>" class="btn btn-outline-success btn-sm">
                    <i class="fa-solid fa-file-csv me-1"></i> Export Attendance CSV
                </a>
                <a href="<?= BASE_URL ?>/api/export-report.php?report=jobs&<?= http_build_query($filters) ?>" class="btn btn-outline-info btn-sm">
                    <i class="fa-solid fa-file-csv me-1"></i> Export Jobs CSV
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="bc-card p-3 mb-4">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <label class="form-label extra-small text-muted mb-1">Date Range</label>
                    <select name="date_range" class="form-select form-select-sm bg-dark text-white border-secondary" onchange="toggleCustomDates(this.value)">
                        <option value="all" <?= $date_range === 'all' ? 'selected' : '' ?>>All Time</option>
                        <option value="today" <?= $date_range === 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="7days" <?= $date_range === '7days' ? 'selected' : '' ?>>Last 7 Days</option>
                        <option value="30days" <?= $date_range === '30days' ? 'selected' : '' ?>>Last 30 Days</option>
                        <option value="90days" <?= $date_range === '90days' ? 'selected' : '' ?>>Last 90 Days</option>
                        <option value="this_year" <?= $date_range === 'this_year' ? 'selected' : '' ?>>This Year</option>
                        <option value="custom" <?= $date_range === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                    </select>
                </div>
                <div class="col-md-2 custom-date-field" style="display: <?= $date_range === 'custom' ? 'block' : 'none' ?>;">
                    <label class="form-label extra-small text-muted mb-1">Start Date</label>
                    <input type="date" name="start_date" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?= sanitize($start_date) ?>">
                </div>
                <div class="col-md-2 custom-date-field" style="display: <?= $date_range === 'custom' ? 'block' : 'none' ?>;">
                    <label class="form-label extra-small text-muted mb-1">End Date</label>
                    <input type="date" name="end_date" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?= sanitize($end_date) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label extra-small text-muted mb-1">Select Project</label>
                    <select name="project_id" class="form-select form-select-sm bg-dark text-white border-secondary">
                        <option value="0">All Projects</option>
                        <?php foreach ($contractor_projects as $cp): ?>
                            <option value="<?= $cp['id'] ?>" <?= $project_id === (int)$cp['id'] ? 'selected' : '' ?>><?= sanitize($cp['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label extra-small text-muted mb-1">Project Status</label>
                    <select name="status" class="form-select form-select-sm bg-dark text-white border-secondary">
                        <option value="">All Statuses</option>
                        <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>In Progress / Active</option>
                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="on_hold" <?= $status === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2 align-self-end">
                    <button type="submit" class="btn btn-warning btn-sm w-100"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                    <a href="<?= BASE_URL ?>/contractor/analytics.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate me-1"></i> Reset</a>
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
                    <div class="text-muted extra-small text-uppercase fw-semibold mb-1">Active Workers</div>
                    <div class="display-6 fw-bold text-info font-monospace mb-1"><?= number_format($kpis['active_workers']) ?></div>
                    <div class="extra-small text-muted">Assigned to Projects</div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="bc-card p-3 text-center h-100">
                    <div class="text-muted extra-small text-uppercase fw-semibold mb-1">Hired Workers</div>
                    <div class="display-6 fw-bold text-success font-monospace mb-1"><?= number_format($kpis['hired_workers']) ?></div>
                    <div class="extra-small text-muted"><?= number_format($kpis['total_applications']) ?> Applications Received</div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="bc-card p-3 text-center h-100">
                    <div class="text-muted extra-small text-uppercase fw-semibold mb-1">Active Contracts</div>
                    <div class="display-6 fw-bold text-emerald font-monospace mb-1"><?= number_format($kpis['active_contracts']) ?></div>
                    <div class="extra-small text-muted"><?= number_format($kpis['completed_contracts']) ?> Completed</div>
                </div>
            </div>
        </div>

        <!-- Visual Analytics Charts -->
        <div class="row g-4 mb-4">
            <!-- Project Progress Breakdown -->
            <div class="col-lg-6">
                <div class="bc-card p-4 h-100">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-chart-bar text-warning me-2"></i>Project Completion Rates (%)</h3>
                    <canvas id="cProjChart" style="max-height: 260px;"></canvas>
                </div>
            </div>

            <!-- Task & Workforce Distribution -->
            <div class="col-lg-6">
                <div class="bc-card p-4 h-100">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-list-check text-info me-2"></i>Workforce Task Overview</h3>
                    <canvas id="cTaskChart" style="max-height: 260px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Detailed Project Performance Table -->
        <div class="bc-card p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h6 text-white fw-bold mb-0"><i class="fa-solid fa-building text-warning me-2"></i>Project Performance Metrics</h3>
            </div>

            <?php if (empty($proj_perf)): ?>
                <div class="text-muted small py-3 text-center">No projects found matching the selected filters.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0 extra-small">
                        <thead>
                            <tr>
                                <th>Project Title</th>
                                <th>Status</th>
                                <th>Progress</th>
                                <th>Tasks (Done/Total)</th>
                                <th>Milestones (Done/Total)</th>
                                <th>Active Workers</th>
                                <th>Site Hours</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($proj_perf as $p): ?>
                                <tr>
                                    <td class="fw-semibold text-white"><?= sanitize($p['title']) ?></td>
                                    <td><span class="badge <?= get_status_badge_class($p['status']) ?>"><?= strtoupper($p['status']) ?></span></td>
                                    <td style="min-width: 140px;">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress bg-secondary flex-grow-1" style="height: 6px;">
                                                <div class="progress-bar bg-warning" style="width: <?= $p['progress_percent'] ?>%;"></div>
                                            </div>
                                            <span class="font-monospace fw-bold text-warning"><?= $p['progress_percent'] ?>%</span>
                                        </div>
                                    </td>
                                    <td class="font-monospace"><?= $p['completed_tasks_count'] ?> / <?= $p['tasks_count'] ?></td>
                                    <td class="font-monospace"><?= $p['completed_milestones_count'] ?> / <?= $p['milestones_count'] ?></td>
                                    <td class="font-monospace text-info"><?= $p['workers_count'] ?></td>
                                    <td class="font-monospace text-success"><?= number_format($p['attendance_hours'], 1) ?> hrs</td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>/contractor/project-details.php?id=<?= $p['id'] ?>" class="btn btn-outline-warning btn-sm extra-small">
                                            View Project <i class="fa-solid fa-arrow-right ms-1"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Job Hiring Performance Table -->
        <div class="bc-card p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h6 text-white fw-bold mb-0"><i class="fa-solid fa-briefcase text-info me-2"></i>Job Market Hiring Funnel</h3>
            </div>

            <?php if (empty($job_perf)): ?>
                <div class="text-muted small py-3 text-center">No job postings found for selected filters.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0 extra-small">
                        <thead>
                            <tr>
                                <th>Job Title</th>
                                <th>Status</th>
                                <th>Spots (Filled/Total)</th>
                                <th>Applications</th>
                                <th>Shortlisted</th>
                                <th>Accepted</th>
                                <th>Rejected</th>
                                <th class="text-end">Hiring Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($job_perf as $j): ?>
                                <?php $rate = ($j['apps_count'] > 0) ? round(($j['accepted_count'] / $j['apps_count']) * 100, 1) : 0; ?>
                                <tr>
                                    <td class="fw-semibold text-white"><?= sanitize($j['title']) ?></td>
                                    <td><span class="badge <?= get_status_badge_class($j['status']) ?>"><?= strtoupper($j['status']) ?></span></td>
                                    <td class="font-monospace"><?= $j['spots_filled'] ?> / <?= $j['spots_available'] ?></td>
                                    <td class="font-monospace text-info"><?= $j['apps_count'] ?></td>
                                    <td class="font-monospace text-warning"><?= $j['shortlisted_count'] ?></td>
                                    <td class="font-monospace text-success"><?= $j['accepted_count'] ?></td>
                                    <td class="font-monospace text-danger"><?= $j['rejected_count'] ?></td>
                                    <td class="text-end font-monospace fw-bold text-emerald"><?= $rate ?>%</td>
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
function toggleCustomDates(val) {
    document.querySelectorAll('.custom-date-field').forEach(el => {
        el.style.display = (val === 'custom') ? 'block' : 'none';
    });
}

document.addEventListener('DOMContentLoaded', () => {
    // Project Progress Chart
    const pTitles = <?= json_encode(array_column($proj_perf, 'title')) ?>;
    const pProgress = <?= json_encode(array_column($proj_perf, 'progress_percent')) ?>;

    new Chart(document.getElementById('cProjChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: pTitles.length ? pTitles : ['No Projects'],
            datasets: [{
                label: 'Progress %',
                data: pProgress.length ? pProgress : [0],
                backgroundColor: '#f59e0b'
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { min: 0, max: 100, ticks: { color: '#94a3b8' } }, x: { ticks: { color: '#94a3b8' } } }
        }
    });

    // Workforce Task Overview Chart
    new Chart(document.getElementById('cTaskChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Tasks Completed', 'Tasks Pending', 'Tasks Overdue'],
            datasets: [{
                data: [<?= $workforce['tasks_completed'] ?>, <?= max(0, $workforce['tasks_assigned'] - $workforce['tasks_completed'] - $workforce['tasks_overdue']) ?>, <?= $workforce['tasks_overdue'] ?>],
                backgroundColor: ['#10b981', '#3b82f6', '#ef4444']
            }]
        },
        options: { plugins: { legend: { labels: { color: '#94a3b8' } } } }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
