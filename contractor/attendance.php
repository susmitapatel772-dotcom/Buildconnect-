<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('contractor');

$page_title = "Attendance Dashboard - Contractor - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$contractor_id = (int)$user['id'];

// Run automated maintenance background helpers
expire_old_attendance_sessions();
update_incomplete_past_attendance();

$flash = get_flash_message();

// Filters
$filter_project_id = (int)($_GET['project_id'] ?? 0);
$filter_worker_id = (int)($_GET['worker_id'] ?? 0);
$filter_status = sanitize($_GET['status'] ?? 'all');
$filter_date_start = sanitize($_GET['date_start'] ?? date('Y-m-d'));
$filter_date_end = sanitize($_GET['date_end'] ?? date('Y-m-d'));

// Fetch contractor projects
$p_stmt = $db->prepare("SELECT id, title FROM projects WHERE contractor_id = ? ORDER BY id DESC");
$p_stmt->execute([$contractor_id]);
$contractor_projects = $p_stmt->fetchAll();

// Fetch contractor project workers
$w_stmt = $db->prepare("
    SELECT DISTINCT u.id, u.name 
    FROM project_members pm
    JOIN projects p ON pm.project_id = p.id
    JOIN users u ON pm.user_id = u.id
    WHERE p.contractor_id = ? AND u.role = 'worker'
    ORDER BY u.name ASC
");
$w_stmt->execute([$contractor_id]);
$contractor_workers = $w_stmt->fetchAll();

// Build Query for attendance logs
$where_clauses = ["p.contractor_id = ?"];
$params = [$contractor_id];

if ($filter_project_id > 0) {
    $where_clauses[] = "a.project_id = ?";
    $params[] = $filter_project_id;
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

// Metrics for selected filters
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

// Fetch attendance logs list
$sql = "
    SELECT a.*, p.title as project_title,
           u.name as worker_name, u.email as worker_email, u.phone as worker_phone,
           w.trade_title
    FROM attendance a
    JOIN projects p ON a.project_id = p.id
    JOIN users u ON a.worker_id = u.id
    LEFT JOIN workers w ON u.id = w.user_id
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
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-clipboard-user text-warning me-2"></i>Site Attendance Dashboard
                </h1>
                <p class="text-muted small mb-0">Monitor worker site check-ins, verify QR sessions, and generate attendance reports.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/contractor/generate-attendance.php<?= $filter_project_id ? "?project_id={$filter_project_id}" : "" ?>" class="btn btn-amber fw-bold py-2 px-3">
                    <i class="fa-solid fa-qrcode me-1"></i> Generate Attendance QR
                </a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show py-2 px-3 small mb-3">
                <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Today's Metric Gauges -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="bc-card p-3 text-center">
                    <div class="text-muted extra-small uppercase">Present Workers</div>
                    <div class="display-6 font-monospace text-success fw-bold my-1"><?= (int)($metrics['present_count'] ?? 0) ?></div>
                    <div class="extra-small text-muted">Checked In</div>
                </div>
            </div>

            <div class="col-md-3 col-6">
                <div class="bc-card p-3 text-center">
                    <div class="text-muted extra-small uppercase">Late Check-Ins</div>
                    <div class="display-6 font-monospace text-warning fw-bold my-1"><?= (int)($metrics['late_count'] ?? 0) ?></div>
                    <div class="extra-small text-muted">Past Start Time</div>
                </div>
            </div>

            <div class="col-md-3 col-6">
                <div class="bc-card p-3 text-center">
                    <div class="text-muted extra-small uppercase">Incomplete Records</div>
                    <div class="display-6 font-monospace text-danger fw-bold my-1"><?= (int)($metrics['incomplete_count'] ?? 0) ?></div>
                    <div class="extra-small text-muted">Missing Check-Out</div>
                </div>
            </div>

            <div class="col-md-3 col-6">
                <div class="bc-card p-3 text-center">
                    <div class="text-muted extra-small uppercase">Total Hours Worked</div>
                    <div class="display-6 font-monospace text-amber fw-bold my-1"><?= format_work_hours($metrics['total_hours'] ?? 0) ?></div>
                    <div class="extra-small text-muted">Accumulated Time</div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="bc-card p-3 mb-4">
            <form action="<?= BASE_URL ?>/contractor/attendance.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <select name="project_id" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="0">All Contractor Projects</option>
                        <?php foreach ($contractor_projects as $cp): ?>
                            <option value="<?= $cp['id'] ?>" <?= $filter_project_id == $cp['id'] ? 'selected' : '' ?>>
                                <?= e($cp['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="worker_id" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="0">All Workers</option>
                        <?php foreach ($contractor_workers as $cw): ?>
                            <option value="<?= $cw['id'] ?>" <?= $filter_worker_id == $cw['id'] ? 'selected' : '' ?>>
                                <?= e($cw['name']) ?>
                            </option>
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

                <div class="col-md-2">
                    <input type="date" name="date_end" value="<?= e($filter_date_end) ?>" class="form-control form-control-sm bg-dark border-secondary text-light">
                </div>

                <div class="col-md-1 d-grid">
                    <button type="submit" class="btn btn-amber btn-sm fw-bold">
                        <i class="fa-solid fa-filter"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- Attendance Logs Table -->
        <div class="bc-card p-4">
            <?php if (empty($attendance_logs)): ?>
                <div class="bc-empty-state py-5 text-center">
                    <i class="fa-solid fa-calendar-xmark fs-1 text-muted mb-3"></i>
                    <h3 class="h5 text-white fw-bold">No Attendance Records Found</h3>
                    <p class="text-muted small mb-0">No site attendance records match your filter criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Worker Candidate</th>
                                <th>Project Name</th>
                                <th>Date</th>
                                <th>Check-In</th>
                                <th>Check-Out</th>
                                <th>Work Duration</th>
                                <th>Status</th>
                                <th>Verification</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_logs as $att): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle bg-secondary text-white fw-bold d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; font-size: 0.85rem;">
                                                <?= strtoupper(substr($att['worker_name'], 0, 2)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-white"><?= e($att['worker_name']) ?></div>
                                                <div class="extra-small text-muted"><?= e($att['trade_title'] ?: 'Worker') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="small text-info">
                                        <?= e($att['project_title']) ?>
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
                                            <span class="badge bg-secondary extra-small">Still Checked In</span>
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
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
