<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('worker');

$page_title = "My Attendance - Worker - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$worker_user_id = (int)$user['id'];

// Expire old sessions & mark incomplete past check-ins
expire_old_attendance_sessions();
update_incomplete_past_attendance();

$error = '';
$flash = get_flash_message();

// Handle Worker Check-Out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security validation failed (CSRF token invalid).';
    } else {
        $action = sanitize($_POST['action'] ?? '');
        $att_id = (int)($_POST['attendance_id'] ?? 0);

        if ($action === 'checkout') {
            // Verify worker owns attendance record
            $chk_stmt = $db->prepare("
                SELECT a.*, p.title as project_title, p.contractor_id
                FROM attendance a
                JOIN projects p ON a.project_id = p.id
                WHERE a.id = ? AND a.worker_id = ?
            ");
            $chk_stmt->execute([$att_id, $worker_user_id]);
            $target_att = $chk_stmt->fetch();

            if (!$target_att) {
                $error = 'Access Denied: Attendance record not found or ownership mismatch.';
            } elseif (!empty($target_att['check_out_time'])) {
                $error = 'You have already checked out for this shift.';
            } else {
                // Calculate worked duration in hours
                $check_in_ts = strtotime($target_att['check_in_time']);
                $now_ts = time();
                $diff_seconds = max(0, $now_ts - $check_in_ts);
                $hours_worked = round($diff_seconds / 3600.0, 2);

                $up_stmt = $db->prepare("
                    UPDATE attendance SET 
                        check_out_time = NOW(), hours_worked = ?, updated_at = NOW()
                    WHERE id = ? AND worker_id = ?
                ");
                $success = $up_stmt->execute([$hours_worked, $att_id, $worker_user_id]);

                if ($success) {
                    $formatted_dur = format_work_hours($hours_worked);

                    // Notify contractor
                    create_notification(
                        $target_att['contractor_id'],
                        'Worker Checked Out',
                        "Worker '{$user['name']}' checked out for '{$target_att['project_title']}' after working {$formatted_dur}.",
                        'info',
                        'contractor/attendance.php'
                    );

                    // Notify worker
                    create_notification(
                        $worker_user_id,
                        'Check-out Successful',
                        "Check-out logged for '{$target_att['project_title']}'. Total Shift Duration: {$formatted_dur}.",
                        'success',
                        'worker/attendance.php'
                    );

                    log_activity($worker_user_id, 'Attendance Check-out', "Checked out for project #{$target_att['project_id']} ({$formatted_dur})", 'attendance', $att_id);
                    set_flash_message("Check-out successful! Total shift time: {$formatted_dur}.", 'success');
                    redirect('worker/attendance.php');
                } else {
                    $error = 'Failed to record check-out.';
                }
            }
        }
    }
}

// Fetch today's check-in status for worker
$today = date('Y-m-d');
$today_stmt = $db->prepare("
    SELECT a.*, p.title as project_title, p.location as project_location
    FROM attendance a
    JOIN projects p ON a.project_id = p.id
    WHERE a.worker_id = ? AND a.attendance_date = ?
    ORDER BY a.id DESC LIMIT 1
");
$today_stmt->execute([$worker_user_id, $today]);
$today_att = $today_stmt->fetch();

// Filters for attendance history
$filter_project_id = (int)($_GET['project_id'] ?? 0);
$filter_status = sanitize($_GET['status'] ?? 'all');

// Fetch worker assigned projects for dropdown
$p_stmt = $db->prepare("
    SELECT DISTINCT p.id, p.title 
    FROM projects p
    JOIN project_members pm ON p.id = pm.project_id
    WHERE pm.user_id = ? AND pm.status = 'active'
");
$p_stmt->execute([$worker_user_id]);
$worker_projects = $p_stmt->fetchAll();

// Build history query
$where_clauses = ["a.worker_id = ?"];
$params = [$worker_user_id];

if ($filter_project_id > 0) {
    $where_clauses[] = "a.project_id = ?";
    $params[] = $filter_project_id;
}

if (in_array($filter_status, ['present', 'late', 'half_day', 'absent', 'incomplete'])) {
    $where_clauses[] = "a.status = ?";
    $params[] = $filter_status;
}

$where_sql = implode(' AND ', $where_clauses);

$history_stmt = $db->prepare("
    SELECT a.*, p.title as project_title
    FROM attendance a
    JOIN projects p ON a.project_id = p.id
    WHERE {$where_sql}
    ORDER BY a.attendance_date DESC, a.check_in_time DESC
");
$history_stmt->execute($params);
$history_logs = $history_stmt->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-clipboard-user text-warning me-2"></i>My Site Attendance & Work Hours
                </h1>
                <p class="text-muted small mb-0">Track today's site check-in, log shift check-outs, and view past attendance history.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/worker/scan-attendance.php" class="btn btn-amber fw-bold py-2 px-3">
                    <i class="fa-solid fa-qrcode me-1"></i> Scan Attendance QR
                </a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show py-2 px-3 small mb-3">
                <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 px-3 small rounded-3 mb-4">
                <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= sanitize($error) ?>
            </div>
        <?php endif; ?>

        <!-- Today's Attendance Status Card -->
        <div class="bc-card p-4 mb-4 border-amber">
            <h2 class="h5 text-white fw-bold mb-3">
                <i class="fa-solid fa-clock text-warning me-2"></i>Today's Shift Status (<?= format_date($today) ?>)
            </h2>

            <?php if ($today_att): ?>
                <div class="p-3 bg-dark rounded-3 border border-secondary mb-3">
                    <div class="row align-items-center g-3">
                        <div class="col-md-5">
                            <div class="extra-small text-muted mb-1">Active Site Project:</div>
                            <h3 class="h6 text-white fw-bold mb-1"><?= e($today_att['project_title']) ?></h3>
                            <span class="badge <?= get_status_badge_class($today_att['status']) ?>"><?= e(ucfirst($today_att['status'])) ?></span>
                        </div>

                        <div class="col-md-4">
                            <div class="extra-small text-muted mb-1">Shift Timings:</div>
                            <div class="small text-success fw-bold font-monospace mb-1">
                                <i class="fa-solid fa-right-to-bracket me-1"></i>Check-in: <?= format_datetime($today_att['check_in_time'], 'h:i A') ?>
                            </div>
                            <?php if ($today_att['check_out_time']): ?>
                                <div class="small text-danger fw-bold font-monospace">
                                    <i class="fa-solid fa-right-from-bracket me-1"></i>Check-out: <?= format_datetime($today_att['check_out_time'], 'h:i A') ?>
                                </div>
                            <?php else: ?>
                                <div class="small text-warning fw-bold">Shift in Progress...</div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-3 text-md-end">
                            <?php if (!$today_att['check_out_time']): ?>
                                <form action="<?= BASE_URL ?>/worker/attendance.php" method="POST">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="checkout">
                                    <input type="hidden" name="attendance_id" value="<?= $today_att['id'] ?>">
                                    <button type="submit" class="btn btn-danger fw-bold py-2 px-3 shadow-sm" onclick="return confirm('Confirm check-out for today shift?');">
                                        <i class="fa-solid fa-right-from-bracket me-1"></i> Check Out Now
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="text-warning font-monospace fw-bold fs-5">
                                    <?= format_work_hours($today_att['hours_worked']) ?>
                                </div>
                                <div class="extra-small text-muted">Total Hours Worked</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="p-4 bg-dark rounded-3 border border-secondary text-center">
                    <i class="fa-solid fa-qrcode fs-2 text-warning mb-2"></i>
                    <h3 class="h6 text-white fw-bold">Not Checked In Today</h3>
                    <p class="text-muted small mb-3">Scan the site attendance QR generated by your contractor to log today's check-in.</p>
                    <a href="<?= BASE_URL ?>/worker/scan-attendance.php" class="btn btn-amber fw-bold btn-sm px-4">
                        <i class="fa-solid fa-qrcode me-1"></i> Scan Attendance QR
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Attendance History Section -->
        <div class="bc-card p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="h5 text-white fw-bold mb-0">
                    <i class="fa-solid fa-history text-info me-2"></i>Attendance History Logs
                </h2>
            </div>

            <!-- Filters -->
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <select name="project_id" class="form-select form-select-sm bg-dark border-secondary text-light" onchange="window.location.href='<?= BASE_URL ?>/worker/attendance.php?project_id=' + this.value">
                        <option value="0">All My Projects</option>
                        <?php foreach ($worker_projects as $wp): ?>
                            <option value="<?= $wp['id'] ?>" <?= $filter_project_id == $wp['id'] ? 'selected' : '' ?>><?= e($wp['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <select name="status" class="form-select form-select-sm bg-dark border-secondary text-light" onchange="window.location.href='<?= BASE_URL ?>/worker/attendance.php?status=' + this.value">
                        <option value="all">All Statuses</option>
                        <option value="present" <?= $filter_status === 'present' ? 'selected' : '' ?>>Present</option>
                        <option value="late" <?= $filter_status === 'late' ? 'selected' : '' ?>>Late</option>
                        <option value="half_day" <?= $filter_status === 'half_day' ? 'selected' : '' ?>>Half Day</option>
                        <option value="incomplete" <?= $filter_status === 'incomplete' ? 'selected' : '' ?>>Incomplete</option>
                    </select>
                </div>
            </div>

            <?php if (empty($history_logs)): ?>
                <div class="bc-empty-state py-5 text-center">
                    <i class="fa-solid fa-calendar-xmark fs-1 text-muted mb-3"></i>
                    <h3 class="h5 text-white fw-bold">No History Logs Found</h3>
                    <p class="text-muted small mb-0">No past attendance logs match your selection.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Project Name</th>
                                <th>Check-In</th>
                                <th>Check-Out</th>
                                <th>Total Duration</th>
                                <th>Status</th>
                                <th>Verification</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history_logs as $att): ?>
                                <tr>
                                    <td class="fw-bold text-white extra-small">
                                        <?= format_date($att['attendance_date']) ?>
                                    </td>
                                    <td class="small text-info">
                                        <?= e($att['project_title']) ?>
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
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
