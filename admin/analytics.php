<?php
$page_title = "Platform Analytics - Admin - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_role(ROLE_ADMIN);
require_once __DIR__ . '/../includes/analytics-functions.php';
require_once __DIR__ . '/../includes/navbar.php';

$db = getDB();

// Handle Filter parameters
$date_range = sanitize($_GET['date_range'] ?? 'all');
$start_date = sanitize($_GET['start_date'] ?? '');
$end_date = sanitize($_GET['end_date'] ?? '');
$city = sanitize($_GET['city'] ?? '');
$status = sanitize($_GET['status'] ?? '');

$filters = [
    'date_range' => $date_range,
    'start_date' => $start_date,
    'end_date' => $end_date,
    'city' => $city,
    'status' => $status
];

$analytics = get_admin_analytics_data($filters);
$kpis = $analytics['kpis'];
$growth = $analytics['user_growth'];

// Distinct cities for filter dropdown
$cities = $db->query("SELECT DISTINCT city FROM projects WHERE city IS NOT NULL AND city != '' ORDER BY city ASC")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <!-- Page Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-chart-pie text-success me-2"></i>Platform Analytics Engine
                </h1>
                <p class="text-muted small mb-0">Real SQL database aggregations across users, projects, hiring, attendance, contracts, and ratings.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= BASE_URL ?>/api/export-report.php?report=projects&<?= http_build_query($filters) ?>" class="btn btn-outline-warning btn-sm">
                    <i class="fa-solid fa-file-csv me-1"></i> Projects CSV
                </a>
                <a href="<?= BASE_URL ?>/api/export-report.php?report=jobs&<?= http_build_query($filters) ?>" class="btn btn-outline-info btn-sm">
                    <i class="fa-solid fa-file-csv me-1"></i> Jobs CSV
                </a>
                <a href="<?= BASE_URL ?>/api/export-report.php?report=attendance&<?= http_build_query($filters) ?>" class="btn btn-outline-success btn-sm">
                    <i class="fa-solid fa-file-csv me-1"></i> Attendance CSV
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
                <div class="col-md-2">
                    <label class="form-label extra-small text-muted mb-1">City</label>
                    <select name="city" class="form-select form-select-sm bg-dark text-white border-secondary">
                        <option value="">All Cities</option>
                        <?php foreach ($cities as $c): ?>
                            <option value="<?= sanitize($c) ?>" <?= $city === $c ? 'selected' : '' ?>><?= sanitize($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label extra-small text-muted mb-1">Project Status</label>
                    <select name="status" class="form-select form-select-sm bg-dark text-white border-secondary">
                        <option value="">All Statuses</option>
                        <option value="planning" <?= $status === 'planning' ? 'selected' : '' ?>>Planning</option>
                        <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>In Progress / Active</option>
                        <option value="on_hold" <?= $status === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2 align-self-end">
                    <button type="submit" class="btn btn-warning btn-sm w-100"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                    <a href="<?= BASE_URL ?>/admin/analytics.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate me-1"></i> Reset</a>
                </div>
            </form>
        </div>

        <!-- Primary Platform KPIs -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="bc-card p-3 text-center h-100">
                    <div class="text-muted extra-small text-uppercase fw-semibold mb-1">Total Users</div>
                    <div class="display-6 fw-bold text-warning font-monospace mb-1"><?= number_format($kpis['total_users']) ?></div>
                    <div class="extra-small text-muted">
                        Growth: <strong class="text-success"><?= $growth['growth_pct'] ?></strong>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="bc-card p-3 text-center h-100">
                    <div class="text-muted extra-small text-uppercase fw-semibold mb-1">Verified Workers</div>
                    <div class="display-6 fw-bold text-success font-monospace mb-1"><?= number_format($kpis['verified_workers']) ?></div>
                    <div class="extra-small text-muted">Out of <?= number_format($kpis['total_workers']) ?> Workers</div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="bc-card p-3 text-center h-100">
                    <div class="text-muted extra-small text-uppercase fw-semibold mb-1">Active Projects</div>
                    <div class="display-6 fw-bold text-info font-monospace mb-1"><?= number_format($kpis['active_projects']) ?></div>
                    <div class="extra-small text-muted">Total Projects: <?= number_format($kpis['total_projects']) ?></div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="bc-card p-3 text-center h-100">
                    <div class="text-muted extra-small text-uppercase fw-semibold mb-1">App Acceptance Rate</div>
                    <div class="display-6 fw-bold text-emerald font-monospace mb-1"><?= $kpis['acceptance_rate'] ?>%</div>
                    <div class="extra-small text-muted"><?= number_format($kpis['accepted_applications']) ?> of <?= number_format($kpis['total_applications']) ?> Apps</div>
                </div>
            </div>
        </div>

        <!-- Secondary KPIs -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="bc-card p-3 border-secondary">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted extra-small">Total Contracts</div>
                            <div class="fs-4 fw-bold text-white font-monospace"><?= number_format($kpis['total_contracts']) ?></div>
                        </div>
                        <div class="stat-icon emerald small"><i class="fa-solid fa-file-contract"></i></div>
                    </div>
                    <div class="extra-small text-muted mt-2">Completion Rate: <strong class="text-success"><?= $kpis['contract_completion_rate'] ?>%</strong></div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="bc-card p-3 border-secondary">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted extra-small">Site Attendance</div>
                            <div class="fs-4 fw-bold text-white font-monospace"><?= number_format($kpis['total_attendance_records']) ?></div>
                        </div>
                        <div class="stat-icon amber small"><i class="fa-solid fa-clock"></i></div>
                    </div>
                    <div class="extra-small text-muted mt-2">Total Hours: <strong class="text-warning"><?= number_format($kpis['total_work_hours'], 1) ?> hrs</strong></div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="bc-card p-3 border-secondary">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted extra-small">Open Jobs</div>
                            <div class="fs-4 fw-bold text-white font-monospace"><?= number_format($kpis['open_jobs']) ?></div>
                        </div>
                        <div class="stat-icon blue small"><i class="fa-solid fa-briefcase"></i></div>
                    </div>
                    <div class="extra-small text-muted mt-2">Filled Jobs: <strong class="text-info"><?= number_format($kpis['filled_jobs']) ?></strong></div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="bc-card p-3 border-secondary">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted extra-small">Platform Rating</div>
                            <div class="fs-4 fw-bold text-warning font-monospace">★ <?= $kpis['avg_platform_rating'] ?></div>
                        </div>
                        <div class="stat-icon purple small"><i class="fa-solid fa-star"></i></div>
                    </div>
                    <div class="extra-small text-muted mt-2">Total Reviews: <strong class="text-white"><?= number_format($kpis['total_reviews']) ?></strong></div>
                </div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="row g-4 mb-4">
            <!-- User Distribution Chart -->
            <div class="col-lg-6">
                <div class="bc-card p-4 h-100">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-users text-warning me-2"></i>User Distribution by Role</h3>
                    <canvas id="userDistChart" style="max-height: 260px;"></canvas>
                </div>
            </div>

            <!-- Project Status Chart -->
            <div class="col-lg-6">
                <div class="bc-card p-4 h-100">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-building text-info me-2"></i>Project Portfolio Status</h3>
                    <canvas id="projectStatusChart" style="max-height: 260px;"></canvas>
                </div>
            </div>

            <!-- Application Funnel Chart -->
            <div class="col-lg-6">
                <div class="bc-card p-4 h-100">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-filter text-success me-2"></i>Hiring & Application Funnel</h3>
                    <canvas id="appFunnelChart" style="max-height: 260px;"></canvas>
                </div>
            </div>

            <!-- Star Rating Distribution -->
            <div class="col-lg-6">
                <div class="bc-card p-4 h-100">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-star text-warning me-2"></i>Ratings & Reviews Breakdown</h3>
                    <canvas id="ratingsChart" style="max-height: 260px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Leaderboards & Breakdown Tables -->
        <div class="row g-4 mb-4">
            <!-- Top Rated Workers -->
            <div class="col-lg-6">
                <div class="bc-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h6 text-white fw-bold mb-0"><i class="fa-solid fa-helmet-safety text-warning me-2"></i>Top Rated Workers</h3>
                        <a href="<?= BASE_URL ?>/admin/workers.php" class="extra-small text-warning">View All</a>
                    </div>
                    <?php if (empty($analytics['top_workers'])): ?>
                        <div class="text-muted small py-3 text-center">No reviews submitted for workers yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle mb-0 extra-small">
                                <thead>
                                    <tr>
                                        <th>Worker</th>
                                        <th>Trade</th>
                                        <th>City</th>
                                        <th class="text-end">Rating</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($analytics['top_workers'] as $tw): ?>
                                        <tr>
                                            <td class="fw-semibold text-white"><?= sanitize($tw['name']) ?></td>
                                            <td class="text-muted"><?= sanitize($tw['trade_title']) ?></td>
                                            <td><?= sanitize($tw['city']) ?></td>
                                            <td class="text-end text-warning fw-bold">★ <?= number_format($tw['rating_avg'], 1) ?> (<?= $tw['reviews_count'] ?>)</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Rated Contractors -->
            <div class="col-lg-6">
                <div class="bc-card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h3 class="h6 text-white fw-bold mb-0"><i class="fa-solid fa-city text-info me-2"></i>Top Rated Contractors</h3>
                        <a href="<?= BASE_URL ?>/admin/contractors.php" class="extra-small text-info">View All</a>
                    </div>
                    <?php if (empty($analytics['top_contractors'])): ?>
                        <div class="text-muted small py-3 text-center">No contractor profiles registered yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle mb-0 extra-small">
                                <thead>
                                    <tr>
                                        <th>Company</th>
                                        <th>Contractor</th>
                                        <th>City</th>
                                        <th class="text-end">Rating</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($analytics['top_contractors'] as $tc): ?>
                                        <tr>
                                            <td class="fw-semibold text-white"><?= sanitize($tc['company_name']) ?></td>
                                            <td class="text-muted"><?= sanitize($tc['name']) ?></td>
                                            <td><?= sanitize($tc['city'] ?? 'N/A') ?></td>
                                            <td class="text-end text-warning fw-bold">★ <?= number_format($tc['rating_avg'], 1) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
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
    // User Distribution Chart
    new Chart(document.getElementById('userDistChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Workers', 'Contractors', 'Clients'],
            datasets: [{
                data: [<?= $kpis['total_workers'] ?>, <?= $kpis['total_contractors'] ?>, <?= $kpis['total_clients'] ?>],
                backgroundColor: ['#f59e0b', '#3b82f6', '#10b981']
            }]
        },
        options: { plugins: { legend: { labels: { color: '#94a3b8' } } } }
    });

    // Project Status Chart
    const pStatuses = <?= json_encode(array_column($analytics['projects_by_status'], 'status')) ?>;
    const pCounts = <?= json_encode(array_column($analytics['projects_by_status'], 'count')) ?>;
    new Chart(document.getElementById('projectStatusChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: pStatuses.map(s => s.replace('_', ' ').toUpperCase()),
            datasets: [{
                label: 'Projects',
                data: pCounts,
                backgroundColor: ['#f59e0b', '#10b981', '#64748b', '#ef4444', '#3b82f6']
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { color: '#94a3b8' } }, x: { ticks: { color: '#94a3b8' } } }
        }
    });

    // Application Funnel Chart
    new Chart(document.getElementById('appFunnelChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: ['Total Apps', 'Shortlisted', 'Accepted', 'Rejected', 'Withdrawn'],
            datasets: [{
                label: 'Applications',
                data: [<?= $kpis['total_applications'] ?>, <?= $kpis['shortlisted_applications'] ?>, <?= $kpis['accepted_applications'] ?>, <?= $kpis['rejected_applications'] ?>, <?= $kpis['withdrawn_applications'] ?>],
                backgroundColor: ['#3b82f6', '#f59e0b', '#10b981', '#ef4444', '#64748b']
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { color: '#94a3b8' } }, x: { ticks: { color: '#94a3b8' } } }
        }
    });

    // Ratings Breakdown Chart
    const ratingsData = <?= json_encode($kpis['ratings_breakdown']) ?>;
    new Chart(document.getElementById('ratingsChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: ['5 Stars', '4 Stars', '3 Stars', '2 Stars', '1 Star'],
            datasets: [{
                label: 'Reviews Count',
                data: [ratingsData[5], ratingsData[4], ratingsData[3], ratingsData[2], ratingsData[1]],
                backgroundColor: '#f59e0b'
            }]
        },
        options: {
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { color: '#94a3b8' } }, y: { ticks: { color: '#94a3b8' } } }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
