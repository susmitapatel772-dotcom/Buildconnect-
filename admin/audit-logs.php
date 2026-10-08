<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$page_title = "Admin Audit Logs - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$current_user = currentUser();
$db = getDB();

// Filters & Search Parameters
$search = trim($_GET['search'] ?? '');
$filter_role = trim($_GET['role'] ?? '');
$filter_entity = trim($_GET['entity_type'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;

$where = ["1=1"];
$params = [];

if ($search !== '') {
    $where[] = "(a.action LIKE ? OR a.details LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR a.entity_type LIKE ? OR a.ip_address LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($filter_role !== '') {
    if ($filter_role === 'system') {
        $where[] = "a.user_id IS NULL";
    } else {
        $where[] = "u.role = ?";
        $params[] = $filter_role;
    }
}

if ($filter_entity !== '') {
    $where[] = "a.entity_type = ?";
    $params[] = $filter_entity;
}

if ($date_from !== '') {
    $where[] = "a.created_at >= ?";
    $params[] = $date_from . " 00:00:00";
}

if ($date_to !== '') {
    $where[] = "a.created_at <= ?";
    $params[] = $date_to . " 23:59:59";
}

$where_clause = implode(" AND ", $where);

// Calculate total matching records for pagination
$count_sql = "
    SELECT COUNT(*) 
    FROM activity_logs a 
    LEFT JOIN users u ON a.user_id = u.id 
    WHERE {$where_clause}
";
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total_records = (int)$count_stmt->fetchColumn();

$total_pages = max(1, (int)ceil($total_records / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;

// Fetch logs with limit and offset
$sql = "
    SELECT a.*, u.name as user_name, u.email as user_email, u.role as user_role
    FROM activity_logs a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE {$where_clause}
    ORDER BY a.id DESC
    LIMIT {$per_page} OFFSET {$offset}
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Statistics Summary
$stats = [
    'total' => (int)$db->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn(),
    'admin_actions' => (int)$db->query("SELECT COUNT(*) FROM activity_logs a JOIN users u ON a.user_id = u.id WHERE u.role = 'admin'")->fetchColumn(),
    'user_actions' => (int)$db->query("SELECT COUNT(*) FROM activity_logs a JOIN users u ON a.user_id = u.id WHERE u.role != 'admin'")->fetchColumn(),
    'today' => (int)$db->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn()
];

// Available entity types for filter dropdown
$entity_types = $db->query("SELECT DISTINCT entity_type FROM activity_logs WHERE entity_type IS NOT NULL AND entity_type != '' ORDER BY entity_type ASC")->fetchAll(PDO::FETCH_COLUMN);

// Build query parameters array for pagination URL builder
if (!function_exists('get_filter_params')) {
    function get_filter_params($extra = []) {
        $current = $_GET;
        unset($current['page']);
        return http_build_query(array_merge($current, $extra));
    }
}
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-shield-halved text-success me-2"></i>System Audit Logs
                </h1>
                <p class="text-muted small mb-0">
                    Comprehensive audit log of system events, authentication actions, user updates, and administrative changes.
                </p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/admin/audit-logs.php" class="btn btn-outline-secondary btn-sm me-2" title="Refresh Audit Logs">
                    <i class="fa-solid fa-rotate me-1"></i>Refresh
                </a>
            </div>
        </div>

        <!-- Audit Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="bc-card p-3 d-flex align-items-center gap-3">
                    <div class="rounded-circle p-3 bg-primary bg-opacity-10 text-primary fs-4">
                        <i class="fa-solid fa-list-check"></i>
                    </div>
                    <div>
                        <div class="text-muted extra-small text-uppercase fw-semibold">Total Audit Logs</div>
                        <div class="h4 fw-bold text-white mb-0"><?= number_format($stats['total']) ?></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="bc-card p-3 d-flex align-items-center gap-3">
                    <div class="rounded-circle p-3 bg-warning bg-opacity-10 text-warning fs-4">
                        <i class="fa-solid fa-user-shield"></i>
                    </div>
                    <div>
                        <div class="text-muted extra-small text-uppercase fw-semibold">Admin Actions</div>
                        <div class="h4 fw-bold text-white mb-0"><?= number_format($stats['admin_actions']) ?></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="bc-card p-3 d-flex align-items-center gap-3">
                    <div class="rounded-circle p-3 bg-info bg-opacity-10 text-info fs-4">
                        <i class="fa-solid fa-users font-monospace"></i>
                    </div>
                    <div>
                        <div class="text-muted extra-small text-uppercase fw-semibold">User Actions</div>
                        <div class="h4 fw-bold text-white mb-0"><?= number_format($stats['user_actions']) ?></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="bc-card p-3 d-flex align-items-center gap-3">
                    <div class="rounded-circle p-3 bg-success bg-opacity-10 text-success fs-4">
                        <i class="fa-solid fa-calendar-day"></i>
                    </div>
                    <div>
                        <div class="text-muted extra-small text-uppercase fw-semibold">Logged Today</div>
                        <div class="h4 fw-bold text-white mb-0"><?= number_format($stats['today']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div class="bc-card p-3 mb-4">
            <form action="<?= BASE_URL ?>/admin/audit-logs.php" method="GET" class="row g-2 align-items-center">
                <!-- Search Input -->
                <div class="col-12 col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-dark border-secondary text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" class="form-control bg-dark text-white border-secondary" name="search" placeholder="Search by action, details, user..." value="<?= e($search) ?>">
                    </div>
                </div>

                <!-- Entity Type Filter -->
                <div class="col-6 col-md-2">
                    <select name="entity_type" class="form-select form-select-sm bg-dark text-white border-secondary">
                        <option value="">All Entities</option>
                        <?php foreach ($entity_types as $ent): ?>
                            <option value="<?= e($ent) ?>" <?= $filter_entity === $ent ? 'selected' : '' ?>><?= e(ucfirst($ent)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- User Role Filter -->
                <div class="col-6 col-md-2">
                    <select name="role" class="form-select form-select-sm bg-dark text-white border-secondary">
                        <option value="">All User Roles</option>
                        <option value="admin" <?= $filter_role === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="contractor" <?= $filter_role === 'contractor' ? 'selected' : '' ?>>Contractor</option>
                        <option value="worker" <?= $filter_role === 'worker' ? 'selected' : '' ?>>Worker</option>
                        <option value="client" <?= $filter_role === 'client' ? 'selected' : '' ?>>Client</option>
                        <option value="system" <?= $filter_role === 'system' ? 'selected' : '' ?>>System / Guest</option>
                    </select>
                </div>

                <!-- Date Range Filters -->
                <div class="col-6 col-md-1.5" style="min-width: 130px;">
                    <input type="date" name="date_from" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?= e($date_from) ?>" title="From Date">
                </div>
                <div class="col-6 col-md-1.5" style="min-width: 130px;">
                    <input type="date" name="date_to" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?= e($date_to) ?>" title="To Date">
                </div>

                <!-- Filter Actions -->
                <div class="col-12 col-md-auto d-flex gap-2 ms-auto">
                    <button type="submit" class="btn btn-amber btn-sm fw-bold px-3">
                        <i class="fa-solid fa-filter me-1"></i>Filter
                    </button>
                    <?php if ($search !== '' || $filter_role !== '' || $filter_entity !== '' || $date_from !== '' || $date_to !== ''): ?>
                        <a href="<?= BASE_URL ?>/admin/audit-logs.php" class="btn btn-outline-secondary btn-sm" title="Clear All Filters">
                            <i class="fa-solid fa-xmark me-1"></i>Reset
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Audit Logs Table Card -->
        <div class="bc-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-muted small fw-semibold">
                    Showing <?= min(1, $total_records) ?>–<?= min($offset + count($logs), $total_records) ?> of <?= number_format($total_records) ?> audit log entries
                </span>
            </div>

            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Log ID</th>
                            <th>Action Event</th>
                            <th>User Context</th>
                            <th>Entity Reference</th>
                            <th>Details</th>
                            <th>IP Address</th>
                            <th>Timestamp</th>
                            <th class="text-end">View</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fa-solid fa-file-shield fa-3x mb-3 text-secondary opacity-50"></i>
                                        <div class="fw-bold text-white fs-5 mb-1">No Audit Logs Found</div>
                                        <p class="small mb-2">No activity records match your current filter parameters or search term.</p>
                                        <?php if ($search !== '' || $filter_role !== '' || $filter_entity !== '' || $date_from !== '' || $date_to !== ''): ?>
                                            <a href="<?= BASE_URL ?>/admin/audit-logs.php" class="btn btn-outline-amber btn-sm mt-2">
                                                <i class="fa-solid fa-rotate-left me-1"></i>Reset Filters
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="font-monospace text-muted small">#<?= $log['id'] ?></td>
                                    <td>
                                        <span class="fw-bold text-warning small"><?= e($log['action']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($log['user_name']): ?>
                                            <div class="fw-semibold text-white small"><?= e($log['user_name']) ?></div>
                                            <div class="text-muted extra-small"><?= e($log['user_email']) ?> (<?= e(ucfirst($log['user_role'])) ?>)</div>
                                        <?php else: ?>
                                            <span class="badge bg-secondary text-dark extra-small">System / Guest</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($log['entity_type']): ?>
                                            <span class="badge bg-dark border border-secondary text-uppercase extra-small">
                                                <?= e($log['entity_type']) ?><?= $log['entity_id'] ? " #{$log['entity_id']}" : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted extra-small">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-light extra-small opacity-75" style="max-width: 280px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?= e($log['details']) ?>
                                    </td>
                                    <td class="font-monospace text-muted extra-small"><?= e($log['ip_address'] ?: '127.0.0.1') ?></td>
                                    <td class="text-muted extra-small" style="white-space: nowrap;"><?= format_datetime($log['created_at']) ?></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-info p-1 px-2" data-bs-toggle="modal" data-bs-target="#logModal<?= $log['id'] ?>" title="View Audit Details">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>

                                <!-- Details Modal for Log #<?= $log['id'] ?> -->
                                <div class="modal fade" id="logModal<?= $log['id'] ?>" tabindex="-1" aria-labelledby="logModalLabel<?= $log['id'] ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content bg-dark border-secondary text-light">
                                            <div class="modal-header border-secondary">
                                                <h5 class="modal-title text-white fw-bold" id="logModalLabel<?= $log['id'] ?>">
                                                    <i class="fa-solid fa-shield-halved text-success me-2"></i>Audit Record #<?= $log['id'] ?>
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="text-muted extra-small text-uppercase fw-bold d-block">Action Event</label>
                                                    <div class="fw-bold text-warning fs-6"><?= e($log['action']) ?></div>
                                                </div>

                                                <div class="row g-3 mb-3">
                                                    <div class="col-6">
                                                        <label class="text-muted extra-small text-uppercase fw-bold d-block">Performed By</label>
                                                        <div class="fw-semibold text-white small"><?= e($log['user_name'] ?? 'System / Guest') ?></div>
                                                        <?php if ($log['user_email']): ?>
                                                            <div class="text-muted extra-small"><?= e($log['user_email']) ?> (<?= e(ucfirst($log['user_role'])) ?>)</div>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="col-6">
                                                        <label class="text-muted extra-small text-uppercase fw-bold d-block">Target Entity</label>
                                                        <?php if ($log['entity_type']): ?>
                                                            <span class="badge bg-dark border border-secondary text-uppercase">
                                                                <?= e($log['entity_type']) ?><?= $log['entity_id'] ? " #{$log['entity_id']}" : '' ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted small">None</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <div class="row g-3 mb-3">
                                                    <div class="col-6">
                                                        <label class="text-muted extra-small text-uppercase fw-bold d-block">IP Address</label>
                                                        <div class="font-monospace text-info small"><?= e($log['ip_address'] ?: '127.0.0.1') ?></div>
                                                    </div>

                                                    <div class="col-6">
                                                        <label class="text-muted extra-small text-uppercase fw-bold d-block">Timestamp</label>
                                                        <div class="text-light small"><?= format_datetime($log['created_at']) ?></div>
                                                    </div>
                                                </div>

                                                <div class="mb-0">
                                                    <label class="text-muted extra-small text-uppercase fw-bold d-block">Full Description / Context Details</label>
                                                    <div class="p-3 bg-black bg-opacity-50 rounded border border-secondary text-light font-monospace extra-small" style="white-space: pre-wrap; word-break: break-word;">
                                                        <?= e($log['details'] ?: 'No additional details recorded.') ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-secondary">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top border-secondary border-opacity-50 flex-wrap gap-2">
                    <div class="text-muted extra-small">
                        Page <?= $page ?> of <?= $total_pages ?>
                    </div>
                    <nav aria-label="Audit log pagination">
                        <ul class="pagination pagination-sm mb-0">
                            <!-- Previous Page Link -->
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link bg-dark border-secondary text-light" href="<?= BASE_URL ?>/admin/audit-logs.php?<?= get_filter_params(['page' => $page - 1]) ?>" aria-label="Previous">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </a>
                            </li>

                            <!-- Page Numbers -->
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                                    <a class="page-link <?= ($i === $page) ? 'bg-amber border-amber text-dark fw-bold' : 'bg-dark border-secondary text-light' ?>" href="<?= BASE_URL ?>/admin/audit-logs.php?<?= get_filter_params(['page' => $i]) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <!-- Next Page Link -->
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                <a class="page-link bg-dark border-secondary text-light" href="<?= BASE_URL ?>/admin/audit-logs.php?<?= get_filter_params(['page' => $page + 1]) ?>" aria-label="Next">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
