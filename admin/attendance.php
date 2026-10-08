<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$page_title = "Global Attendance Monitor - Admin - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$current_user = currentUser();
$db = getDB();

// Run maintenance helpers
expire_old_attendance_sessions();
update_incomplete_past_attendance();

// Filter Parameters
$filter_project_id = (int)($_GET['project_id'] ?? 0);
$filter_contractor_id = (int)($_GET['contractor_id'] ?? 0);
$filter_worker_id = (int)($_GET['worker_id'] ?? 0);
$filter_status = sanitize($_GET['status'] ?? 'all');
$filter_date_start = sanitize($_GET['date_start'] ?? '');
$filter_date_end = sanitize($_GET['date_end'] ?? '');

// Fetch dropdown options
$projects_stmt = $db->query("SELECT id, title FROM projects ORDER BY id DESC");
$all_projects = $projects_stmt->fetchAll();

$contractors_stmt = $db->query("SELECT u.id, u.name, c.company_name FROM users u LEFT JOIN contractors c ON u.id = c.user_id WHERE u.role = 'contractor' ORDER BY u.name ASC");
$all_contractors = $contractors_stmt->fetchAll();

$workers_stmt = $db->query("SELECT u.id, u.name FROM users u WHERE u.role = 'worker' ORDER BY u.name ASC");
$all_workers = $workers_stmt->fetchAll();

// Build query
$where_clauses = ["1=1"];
$params = [];

if ($filter_project_id > 0) {
    $where_clauses[] = "a.project_id = ?";
    $params[] = $filter_project_id;
}

if ($filter_contractor_id > 0) {
    $where_clauses[] = "p.contractor_id = ?";
    $params[] = $filter_contractor_id;
}

if ($filter_worker_id > 0) {
    $where_clauses[] = "a.worker_id = ?";
    $params[] = $filter_worker_id;
}

if (in_array($filter_status, ['present', 'late', 'half_day', 'absent', 'incomplete'])) {
    $where_clauses[] = "a.status = ?";
    $params[] = $filter_status;
}

if (!empty($filter_date_start)) {
    $where_clauses[] = "a.attendance_date >= ?";
    $params[] = $filter_date_start;
}

if (!empty($filter_date_end)) {
    $where_clauses[] = "a.attendance_date <= ?";
    $params[] = $filter_date_end;
}

$where_sql = implode(' AND ', $where_clauses);

// System-wide Metrics
$metrics_stmt = $db->prepare("
    SELECT 
        COUNT(DISTINCT a.id) as total_records,
        SUM(CASE WHEN a.status IN ('present', 'late', 'half_day') THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN a.status = 'incomplete' THEN 1 ELSE 0 END) as incomplete_count,
        SUM(a.hours_worked) as total_hours
    FROM attendance a
    JOIN projects p ON a.project_id = p.id
    WHERE {$where_sql}
");
$metrics_stmt->execute($params);
$metrics = $metrics_stmt->fetch();

// Fetch Global Attendance Logs
$sql = "
    SELECT a.*, p.title as project_title,
           u_w.name as worker_name, u_w.email as worker_email,
           u_c.name as contractor_name, c_prof.company_name as contractor_company
    FROM attendance a
    JOIN projects p ON a.project_id = p.id
    JOIN users u_w ON a.worker_id = u_w.id
    JOIN users u_c ON p.contractor_id = u_c.id
    LEFT JOIN contractors c_prof ON u_c.id = c_prof.user_id
    WHERE {$where_sql}
    ORDER BY a.attendance_date DESC, a.check_in_time DESC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$attendance_logs = $stmt->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-clipboard-user text-warning me-2"></i>Global Attendance Monitor
                </h1>
                <p class="text-muted small mb-0">System-wide monitoring of construction site check-ins, QR verification logs, and work hour reports.</p>
            </div>
        </div>

        <!-- Global Metrics -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="bc-card p-3 text-center">
                    <div class="text-muted extra-small uppercase">Total Records</div>
                    <div class="display-6 font-monospace text-info fw-bold my-1"><?= (int)($metrics['total_records'] ?? 0) ?></div>
                    <div class="extra-small text-muted">Attendance Entries</div>
                </div>
            </div>

            <div class="col-md-3 col-6">
                <div class="bc-card p-3 text-center">
                    <div class="text-muted extra-small uppercase">Present Workers</div>
                    <div class="display-6 font-monospace text-success fw-bold my-1"><?= (int)($metrics['present_count'] ?? 0) ?></div>
                    <div class="extra-small text-muted">Verified Checks</div>
                </div>
            </div>

            <div class="col-md-3 col-6">
                <div class="bc-card p-3 text-center">
                    <div class="text-muted extra-small uppercase">Late / Incomplete</div>
                    <div class="display-6 font-monospace text-warning fw-bold my-1"><?= (int)($metrics['late_count'] ?? 0) + (int)($metrics['incomplete_count'] ?? 0) ?></div>
                    <div class="extra-small text-muted">Exceptions</div>
                </div>
            </div>

            <div class="col-md-3 col-6">
                <div class="bc-card p-3 text-center">
                    <div class="text-muted extra-small uppercase">Total Work Hours</div>
                    <div class="display-6 font-monospace text-amber fw-bold my-1"><?= format_work_hours($metrics['total_hours'] ?? 0) ?></div>
                    <div class="extra-small text-muted">Platform Aggregate</div>
                </div>
            </div>
        </div>

        <!-- Filters Bar -->
        <div class="bc-card p-3 mb-4">
            <form action="<?= BASE_URL ?>/admin/attendance.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <select name="project_id" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="0">All Projects</option>
                        <?php foreach ($all_projects as $ap): ?>
                            <option value="<?= $ap['id'] ?>" <?= $filter_project_id == $ap['id'] ? 'selected' : '' ?>><?= e($ap['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="contractor_id" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="0">All Contractors</option>
                        <?php foreach ($all_contractors as $ac): ?>
                            <option value="<?= $ac['id'] ?>" <?= $filter_contractor_id == $ac['id'] ? 'selected' : '' ?>><?= e($ac['company_name'] ?: $ac['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="worker_id" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="0">All Workers</option>
                        <?php foreach ($all_workers as $aw): ?>
                            <option value="<?= $aw['id'] ?>" <?= $filter_worker_id == $aw['id'] ? 'selected' : '' ?>><?= e($aw['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="all">All Statuses</option>
                        <option value="present" <?= $filter_status === 'present' ? 'selected' : '' ?>>Present</option>
                        <option value="late" <?= $filter_status === 'late' ? 'selected' : '' ?>>Late</option>
                        <option value="half_day" <?= $filter_status === 'half_day' ? 'selected' : '' ?>>Half Day</option>
                        <option value="incomplete" <?= $filter_status === 'incomplete' ? 'selected' : '' ?>>Incomplete</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <input type="date" name="date_start" value="<?= e($filter_date_start) ?>" class="form-control form-control-sm bg-dark border-secondary text-light">
                </div>

                <div class="col-md-1 d-grid">
                    <button type="submit" class="btn btn-amber btn-sm fw-bold"><i class="fa-solid fa-filter"></i></button>
                </div>
            </form>
        </div>

        <!-- Global Logs Table -->
        <div class="bc-card p-4">
            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Worker Candidate</th>
                            <th>Project & Contractor</th>
                            <th>Date</th>
                            <th>Check-In</th>
                            <th>Check-Out</th>
                            <th>Work Hours</th>
                            <th>Status</th>
                            <th>Verification</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($attendance_logs)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">No attendance logs found matching filters.</td></tr>
                        <?php else: ?>
                            <?php foreach ($attendance_logs as $att): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-white"><?= e($att['worker_name']) ?></div>
                                        <div class="text-muted extra-small"><?= e($att['worker_email']) ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-info small"><?= e($att['project_title']) ?></div>
                                        <div class="extra-small text-muted"><?= e($att['contractor_company'] ?: $att['contractor_name']) ?></div>
                                    </td>
                                    <td class="text-muted extra-small">
                                        <?= format_date($att['attendance_date']) ?>
                                    </td>
                                    <td>
                                        <span class="font-monospace text-success small"><i class="fa-solid fa-right-to-bracket me-1"></i><?= format_datetime($att['check_in_time'], 'h:i A') ?></span>
                                    </td>
                                    <td>
                                        <?php if ($att['check_out_time']): ?>
                                            <span class="font-monospace text-danger small"><i class="fa-solid fa-right-from-bracket me-1"></i><?= format_datetime($att['check_out_time'], 'h:i A') ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary extra-small">Incomplete / Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="font-monospace text-warning fw-bold small"><?= format_work_hours($att['hours_worked']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge <?= get_status_badge_class($att['status']) ?>"><?= e(ucfirst($att['status'])) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($att['verified_by_qr']): ?>
                                            <span class="badge bg-dark border border-secondary text-info extra-small"><i class="fa-solid fa-qrcode me-1 text-warning"></i>QR Verified</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary extra-small">Manual</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
