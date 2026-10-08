<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$page_title = "Platform Activity Logs - Admin - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$current_user = currentUser();
$db = getDB();

$search = trim($_GET['search'] ?? '');
$where = ["1=1"];
$params = [];

if ($search !== '') {
    $where[] = "(a.action LIKE ? OR a.details LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $term = "%{$search}%";
    $params = [$term, $term, $term, $term];
}

$sql = "
    SELECT a.*, u.name as user_name, u.email as user_email, u.role as user_role
    FROM activity_logs a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY a.id DESC LIMIT 100
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-list-check text-success me-2"></i>System Activity Logs</h1>
                <p class="text-muted small mb-0">Audit log of system events, authentication actions, verification updates, and administrative changes.</p>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="bc-card p-3 mb-4">
            <form action="<?= BASE_URL ?>/admin/activity-logs.php" method="GET" class="row g-3">
                <div class="col-md-11">
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" class="form-control" name="search" placeholder="Search activity logs by action, details, user name, or email..." value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-amber w-100 fw-bold"><i class="fa-solid fa-filter"></i></button>
                </div>
            </form>
        </div>

        <div class="bc-card p-4">
            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Log ID</th>
                            <th>Action Event</th>
                            <th>User Context</th>
                            <th>Entity Reference</th>
                            <th>Details</th>
                            <th>IP Address</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No activity logs recorded.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="font-monospace text-muted">#<?= $log['id'] ?></td>
                                    <td>
                                        <span class="fw-bold text-warning small"><?= e($log['action']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($log['user_name']): ?>
                                            <div class="fw-semibold text-white small"><?= e($log['user_name']) ?></div>
                                            <div class="text-muted extra-small"><?= e($log['user_email']) ?> (<?= e($log['user_role']) ?>)</div>
                                        <?php else: ?>
                                            <span class="text-muted small">System / Guest</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($log['entity_type']): ?>
                                            <span class="badge bg-dark border border-secondary text-uppercase"><?= e($log['entity_type']) ?> #<?= $log['entity_id'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted extra-small">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-light extra-small opacity-75" style="max-width: 250px;"><?= e($log['details']) ?></td>
                                    <td class="font-monospace text-muted extra-small"><?= e($log['ip_address'] ?: '127.0.0.1') ?></td>
                                    <td class="text-muted extra-small"><?= format_datetime($log['created_at']) ?></td>
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
