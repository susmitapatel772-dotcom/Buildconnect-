<?php
$page_title = "Work & Performance Analytics - Worker - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_role(ROLE_WORKER);
require_once __DIR__ . '/../includes/analytics-functions.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = get_logged_user();
$db = getDB();

// Worker's projects for dropdown filter
$w_projects = $db->prepare("
    SELECT p.id, p.title 
    FROM project_members pm
    JOIN projects p ON pm.project_id = p.id
    WHERE pm.user_id = ? AND pm.status = 'active'
    ORDER BY p.title ASC
");
$w_projects->execute([$user['id']]);
$worker_projects = $w_projects->fetchAll();

// Handle Filters
$date_range = sanitize($_GET['date_range'] ?? '30days');
$start_date = sanitize($_GET['start_date'] ?? '');
$end_date = sanitize($_GET['end_date'] ?? '');
$project_id = (int)($_GET['project_id'] ?? 0);

$filters = [
    'date_range' => $date_range,
    'start_date' => $start_date,
    'end_date' => $end_date,
    'project_id' => $project_id
];

$analytics = get_worker_analytics_data($user['id'], $filters);
$kpis = $analytics['kpis'];
$att_trend = $analytics['attendance_trend'];
$task_breakdown = $analytics['task_status_breakdown'];

// Fetch Worker's Tasks List
$t_sql = "
    SELECT t.*, p.title as project_title
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    WHERE t.assigned_to_worker_id = ?
";
$t_params = [$user['id']];
if ($project_id > 0) {
    $t_sql .= " AND t.project_id = ?";
    $t_params[] = $project_id;
}
$t_sql .= " ORDER BY t.due_date ASC LIMIT 15";
$stmt = $db->prepare($t_sql);
$stmt->execute($t_params);
$my_tasks = $stmt->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <!-- Page Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-chart-line text-warning me-2"></i>My Performance Analytics
                </h1>
                <p class="text-muted small mb-0">Track your site attendance, task completion efficiency, ratings, and contract history.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= BASE_URL ?>/api/export-report.php?report=attendance&<?= http_build_query($filters) ?>" class="btn btn-outline-success btn-sm">
                    <i class="fa-solid fa-file-csv me-1"></i> Export Attendance CSV
                </a>
                <a href="<?= BASE_URL ?>/api/export-report.php?report=applications&<?= http_build_query($filters) ?>" class="btn btn-outline-warning btn-sm">
                    <i class="fa-solid fa-file-csv me-1"></i> Export Applications CSV
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="bc-card p-3 mb-4">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <label class="form-label extra-small text-muted mb-1">Date Range</label>
                    <select name="date_range" class="form-select form-select-sm bg-dark text-white border-secondary" onchange="toggleCustomDates(this.value)">
                        <option value="7days" <?= $date_range === '7days' ? 'selected' : '' ?>>Last 7 Days</option>
                        <option value="30days" <?= $date_range === '30days' ? 'selected' : '' ?>>Last 30 Days</option>
                        <option value="90days" <?= $date_range === '90days' ? 'selected' : '' ?>>Last 90 Days</option>
                        <option value="this_year" <?= $date_range === 'this_year' ? 'selected' : '' ?>>This Year</option>
                        <option value="all" <?= $date_range === 'all' ? 'selected' : '' ?>>All Time</option>
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
                <div class="col-md-4">
                    <label class="form-label extra-small text-muted mb-1">Select Project</label>
                    <select name="project_id" class="form-select form-select-sm bg-dark text-white border-secondary">
                        <option value="0">All My Projects</option>
                        <?php foreach ($worker_projects as $wp): ?>
                            <option value="<?= $wp['id'] ?>" <?= $project_id === (int)$wp['id'] ? 'selected' : '' ?>><?= sanitize($wp['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2 align-self-end">
                    <button type="submit" class="btn btn-warning btn-sm w-100"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                    <a href="<?= BASE_URL ?>/worker/analytics.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate me-1"></i> Reset</a>
                </div>
            </form>
        </div>

        <!-- KPIs -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="bc-card p-3 text-center h-100">
                    <div class="text-muted extra-small text-uppercase fw-semibold mb-1">Attendance Days</div>
                    <div class="display-6 fw-bold text-success font-monospace mb-1"><?= number_format($kpis['attendance_days']) ?></div>
                    <div class="extra-small text-muted">Late Days: <strong class="text-warning"><?= number_format($kpis['late_days']) ?></strong></div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="bc-card p-3 text-center h-100">
                    <div class="text-muted extra-small text-uppercase fw-semibold mb-1">Total Hours Worked</div>
                    <div class="display-6 fw-bold text-warning font-monospace mb-1"><?= number_format($kpis['total_hours'], 1) ?></div>
                    <div class="extra-small text-muted">Avg Daily: <strong class="text-white"><?= number_format($kpis['avg_daily_hours'], 1) ?> hrs</strong></div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="bc-card p-3 text-center h-100">
                    <div class="text-muted extra-small text-uppercase fw-semibold mb-1">Task Completion Rate</div>
                    <div class="display-6 fw-bold text-info font-monospace mb-1"><?= $kpis['task_completion_rate'] ?>%</div>
                    <div class="extra-small text-muted"><?= $kpis['completed_tasks'] ?> of <?= $kpis['total_tasks'] ?> Tasks</div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="bc-card p-3 text-center h-100">
                    <div class="text-muted extra-small text-uppercase fw-semibold mb-1">My Performance Rating</div>
                    <div class="display-6 fw-bold text-amber font-monospace mb-1">★ <?= $kpis['rating_avg'] ?></div>
                    <div class="extra-small text-muted"><?= $kpis['reviews_count'] ?> Reviews Received</div>
                </div>
            </div>
        </div>

        <!-- Visual Analytics Charts -->
        <div class="row g-4 mb-4">
            <!-- Attendance Hours Trend -->
            <div class="col-lg-7">
                <div class="bc-card p-4 h-100">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-clock text-success me-2"></i>Daily Site Hours Trend</h3>
                    <canvas id="wTrendChart" style="max-height: 260px;"></canvas>
                </div>
            </div>

            <!-- Task Status Breakdown -->
            <div class="col-lg-5">
                <div class="bc-card p-4 h-100">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-tasks text-info me-2"></i>Task Status Breakdown</h3>
                    <canvas id="wTaskChart" style="max-height: 260px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Assigned Tasks Table -->
        <div class="bc-card p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h6 text-white fw-bold mb-0"><i class="fa-solid fa-list-check text-warning me-2"></i>My Assigned Tasks & Progress</h3>
            </div>

            <?php if (empty($my_tasks)): ?>
                <div class="text-muted small py-3 text-center">No assigned tasks found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0 extra-small">
                        <thead>
                            <tr>
                                <th>Task Title</th>
                                <th>Project</th>
                                <th>Priority</th>
                                <th>Due Date</th>
                                <th>Progress</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_tasks as $t): ?>
                                <tr>
                                    <td class="fw-semibold text-white"><?= sanitize($t['title']) ?></td>
                                    <td class="text-muted"><?= sanitize($t['project_title']) ?></td>
                                    <td><span class="badge bg-secondary"><?= strtoupper($t['priority']) ?></span></td>
                                    <td class="font-monospace text-warning"><?= format_date($t['due_date']) ?></td>
                                    <td style="min-width: 120px;">
                                        <div class="progress bg-secondary" style="height: 6px;">
                                            <div class="progress-bar bg-warning" style="width: <?= (int)$t['progress_percent'] ?>%;"></div>
                                        </div>
                                    </td>
                                    <td><span class="badge <?= get_status_badge_class($t['status']) ?>"><?= strtoupper($t['status']) ?></span></td>
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
    // Daily Hours Trend Chart
    const dates = <?= json_encode(array_column($att_trend, 'attendance_date')) ?>;
    const hours = <?= json_encode(array_column($att_trend, 'hours')) ?>;

    new Chart(document.getElementById('wTrendChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: dates.length ? dates : ['No Data'],
            datasets: [{
                label: 'Hours Worked',
                data: hours.length ? hours : [0],
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { color: '#94a3b8' } }, x: { ticks: { color: '#94a3b8' } } }
        }
    });

    // Task Status Chart
    const tStatuses = <?= json_encode(array_column($task_breakdown, 'status')) ?>;
    const tCounts = <?= json_encode(array_column($task_breakdown, 'count')) ?>;

    new Chart(document.getElementById('wTaskChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: tStatuses.length ? tStatuses.map(s => s.toUpperCase()) : ['No Tasks'],
            datasets: [{
                data: tCounts.length ? tCounts : [1],
                backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#64748b']
            }]
        },
        options: { plugins: { legend: { labels: { color: '#94a3b8' } } } }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
