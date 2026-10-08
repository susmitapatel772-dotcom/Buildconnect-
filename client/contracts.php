<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('client');

$page_title = "Digital Contracts - Client Dashboard - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$client_user_id = (int)$user['id'];

$flash = get_flash_message();
$error = '';

// Search and Filter GET parameters
$filter_project_id = (int)($_GET['project_id'] ?? 0);
$filter_status = sanitize($_GET['status'] ?? 'all');
$search = sanitize($_GET['q'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Fetch Client Authorized Projects
$cp_stmt = $db->prepare("SELECT id, title FROM projects WHERE client_id = ? ORDER BY id DESC");
$cp_stmt->execute([$client_user_id]);
$client_projects = $cp_stmt->fetchAll();

// Build Contracts Query
$where = ["p.client_id = ?"];
$params = [$client_user_id];

if ($filter_project_id > 0) {
    $where[] = "c.project_id = ?";
    $params[] = $filter_project_id;
}

if (in_array($filter_status, ['active', 'pending', 'completed', 'cancelled', 'draft'])) {
    $where[] = "c.status = ?";
    $params[] = $filter_status;
}

if (!empty($search)) {
    $where[] = "(c.contract_number LIKE ? OR c.title LIKE ? OR contractor_user.name LIKE ? OR worker_user.name LIKE ?)";
    $s_term = "%{$search}%";
    $params[] = $s_term;
    $params[] = $s_term;
    $params[] = $s_term;
    $params[] = $s_term;
}

$where_sql = implode(' AND ', $where);

// Total Count
$count_stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM contracts c
    JOIN projects p ON c.project_id = p.id
    LEFT JOIN users contractor_user ON c.contractor_id = contractor_user.id
    LEFT JOIN users worker_user ON c.worker_id = worker_user.id
    WHERE {$where_sql}
");
$count_stmt->execute($params);
$total_rows = (int)$count_stmt->fetchColumn();
$total_pages = max(1, ceil($total_rows / $per_page));

// Fetch Contracts Listing
$sql = "
    SELECT c.*, p.title as project_title,
           contractor_user.name as contractor_name, contractor_firm.company_name as contractor_company,
           worker_user.name as worker_name
    FROM contracts c
    JOIN projects p ON c.project_id = p.id
    LEFT JOIN users contractor_user ON c.contractor_id = contractor_user.id
    LEFT JOIN contractors contractor_firm ON contractor_firm.user_id = contractor_user.id
    LEFT JOIN users worker_user ON c.worker_id = worker_user.id
    WHERE {$where_sql}
    ORDER BY c.id DESC
    LIMIT {$per_page} OFFSET {$offset}
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$contracts = $stmt->fetchAll();
?>

<div class="dashboard-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="dashboard-content">
        <!-- Page Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h1 class="h2 fw-extrabold text-white bc-page-title mb-1">
                    <i class="fa-solid fa-file-contract text-warning me-2"></i>Digital Contracts & Agreements
                </h1>
                <p class="text-slate-400 small mb-0">Review binding digital agreements between contractors and site specialists across your developments.</p>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show mb-4" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i> <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Filter Controls -->
        <div class="bc-surface-card p-3 mb-4">
            <form action="<?= BASE_URL ?>/client/contracts.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <input type="text" name="q" value="<?= e($search) ?>" class="form-control form-control-sm" placeholder="Search contract #, title, contractor, or worker...">
                </div>

                <div class="col-md-4">
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="0">All My Projects</option>
                        <?php foreach ($client_projects as $cp): ?>
                            <option value="<?= $cp['id'] ?>" <?= $filter_project_id == $cp['id'] ? 'selected' : '' ?>>
                                <?= e($cp['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="all">All Statuses</option>
                        <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending Signatures</option>
                        <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>

                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-outline-blue btn-sm fw-bold">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Contracts Table -->
        <div class="bc-surface-card p-4">
            <?php if (empty($contracts)): ?>
                <div class="text-center py-5 text-muted extra-small">
                    <i class="fa-solid fa-file-signature fs-1 mb-3 text-warning"></i>
                    <h3 class="h6 text-dark fw-bold mb-1">No Digital Contracts Found</h3>
                    <p class="text-muted extra-small mb-0">No active work contracts are registered under your projects.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 extra-small">
                        <thead>
                            <tr>
                                <th>Contract Ref</th>
                                <th>Project Name</th>
                                <th>Prime Contractor</th>
                                <th>Hired Specialist</th>
                                <th>Contract Rate (₹)</th>
                                <th>Duration</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contracts as $c): ?>
                                <tr>
                                    <td>
                                        <strong class="font-monospace text-warning"><?= e($c['contract_number'] ?? '#BC-' . $c['id']) ?></strong>
                                        <div class="fw-semibold text-dark"><?= e($c['title']) ?></div>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-info"><?= e($c['project_title']) ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark"><?= e($c['contractor_company'] ?? $c['contractor_name']) ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark"><i class="fa-solid fa-helmet-safety text-warning me-1"></i><?= e($c['worker_name']) ?></span>
                                    </td>
                                    <td>
                                        <strong class="font-monospace text-success fs-6"><?= format_currency($c['pay_amount'] ?: $c['payment_amount']) ?></strong>
                                        <div class="text-muted extra-small text-capitalize"><?= e($c['payment_type']) ?> rate</div>
                                    </td>
                                    <td class="text-muted">
                                        <?= format_date($c['start_date']) ?> - <?= format_date($c['end_date']) ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= get_status_badge_class($c['status']) ?>"><?= e(ucfirst($c['status'])) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center pt-3 border-top border-secondary mt-3">
                        <span class="text-muted extra-small">Showing Page <?= $page ?> of <?= $total_pages ?> (Total <?= $total_rows ?> Contracts)</span>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= BASE_URL ?>/client/contracts.php?page=<?= $page - 1 ?>&project_id=<?= $filter_project_id ?>&status=<?= urlencode($filter_status) ?>&q=<?= urlencode($search) ?>">Prev</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= BASE_URL ?>/client/contracts.php?page=<?= $i ?>&project_id=<?= $filter_project_id ?>&status=<?= urlencode($filter_status) ?>&q=<?= urlencode($search) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= BASE_URL ?>/client/contracts.php?page=<?= $page + 1 ?>&project_id=<?= $filter_project_id ?>&status=<?= urlencode($filter_status) ?>&q=<?= urlencode($search) ?>">Next</a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
