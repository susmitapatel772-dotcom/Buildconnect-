<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('contractor');

$page_title = "Manage Projects - Contractor - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$contractor_id = (int)$user['id'];

$error = '';
$flash = get_flash_message();

// Search & Filter & Pagination parameters
$search = sanitize($_GET['q'] ?? '');
$status_filter = sanitize($_GET['status'] ?? 'all');
$valid_statuses = ['planning', 'active', 'in_progress', 'on_hold', 'completed', 'cancelled'];

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Base query for contractor projects
$where_clauses = ["p.contractor_id = ?"];
$params = [$contractor_id];

if (!empty($search)) {
    $where_clauses[] = "(p.title LIKE ? OR p.location LIKE ? OR u_c.name LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (in_array($status_filter, $valid_statuses)) {
    if ($status_filter === 'active') {
        $where_clauses[] = "p.status IN ('active', 'in_progress')";
    } else {
        $where_clauses[] = "p.status = ?";
        $params[] = $status_filter;
    }
}

$where_sql = implode(' AND ', $where_clauses);

// Count total
$count_stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM projects p
    LEFT JOIN users u_c ON p.client_id = u_c.id
    WHERE {$where_sql}
");
$count_stmt->execute($params);
$total_projects = (int)$count_stmt->fetchColumn();
$total_pages = max(1, ceil($total_projects / $per_page));

// Fetch projects with stats
$sql = "
    SELECT p.*, u_c.name as client_name, c_prof.company_name as client_company,
           (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = p.id AND pm.status = 'active') as active_workers_count,
           (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status NOT IN ('completed', 'done', 'cancelled')) as pending_tasks_count,
           (SELECT COUNT(*) FROM milestones m WHERE m.project_id = p.id) as total_milestones_count
    FROM projects p
    LEFT JOIN users u_c ON p.client_id = u_c.id
    LEFT JOIN clients c_prof ON u_c.id = c_prof.user_id
    WHERE {$where_sql}
    ORDER BY p.id DESC
    LIMIT {$per_page} OFFSET {$offset}
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$projects = $stmt->fetchAll();

// Synchronize progress for each project
foreach ($projects as &$proj) {
    $proj['progress_percent'] = calculateProjectProgress($proj['id']);
}
unset($proj);
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-building text-warning me-2"></i>Construction Projects
                </h1>
                <p class="text-muted small mb-0">Overview of active site developments, workforce allocation, and milestone progress.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/contractor/create-project.php" class="btn btn-amber fw-bold py-2 px-3">
                    <i class="fa-solid fa-plus me-1"></i> Create New Project
                </a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show py-2 px-3 small mb-3">
                <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Search & Filter Bar -->
        <div class="bc-card p-3 mb-4">
            <form action="<?= BASE_URL ?>/contractor/projects.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-dark border-secondary text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" name="q" value="<?= e($search) ?>" class="form-control bg-dark border-secondary text-light" placeholder="Search project name, location, or client...">
                    </div>
                </div>

                <div class="col-md-4">
                    <select name="status" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active / In Progress</option>
                        <option value="planning" <?= $status_filter === 'planning' ? 'selected' : '' ?>>Planning</option>
                        <option value="on_hold" <?= $status_filter === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                        <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <div class="col-md-3 d-grid">
                    <button type="submit" class="btn btn-amber btn-sm fw-bold">
                        <i class="fa-solid fa-filter me-1"></i> Filter Projects
                    </button>
                </div>
            </form>
        </div>

        <!-- Projects Table -->
        <div class="bc-card p-4">
            <?php if (empty($projects)): ?>
                <div class="bc-empty-state py-5 text-center">
                    <i class="fa-solid fa-building-circle-exclamation fs-1 text-muted mb-3"></i>
                    <h3 class="h5 text-white fw-bold">No Projects Found</h3>
                    <p class="text-muted small mb-4">No construction projects match your search query.</p>
                    <a href="<?= BASE_URL ?>/contractor/create-project.php" class="btn btn-amber btn-sm fw-bold">
                        <i class="fa-solid fa-plus me-1"></i> Create First Project
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Project Name & Client</th>
                                <th>Location</th>
                                <th>Budget</th>
                                <th>Timeline</th>
                                <th>Progress</th>
                                <th>Workforce & Tasks</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $p): ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_URL ?>/contractor/project-details.php?id=<?= $p['id'] ?>" class="fw-bold text-white text-decoration-none hover-amber">
                                            <?= e($p['title']) ?>
                                        </a>
                                        <div class="extra-small text-muted">
                                            Client: <?= e($p['client_name'] ? ($p['client_company'] ?: $p['client_name']) : 'Internal Developer') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-muted small"><i class="fa-solid fa-location-dot text-danger me-1"></i><?= e($p['location']) ?></span>
                                    </td>
                                    <td>
                                        <span class="font-monospace text-warning fw-bold"><?= format_currency($p['budget']) ?></span>
                                    </td>
                                    <td class="text-muted extra-small">
                                        <?= format_date($p['start_date']) ?> - <?= format_date($p['end_date']) ?>
                                    </td>
                                    <td style="min-width: 140px;">
                                        <div class="d-flex justify-content-between extra-small mb-1">
                                            <span class="text-muted">Progress</span>
                                            <span class="font-monospace text-warning fw-bold"><?= (int)$p['progress_percent'] ?>%</span>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-amber" style="width: <?= (int)$p['progress_percent'] ?>%"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="extra-small">
                                            <span class="badge bg-dark border border-secondary text-info me-1"><i class="fa-solid fa-users me-1"></i><?= (int)$p['active_workers_count'] ?> Workers</span>
                                            <span class="badge bg-dark border border-secondary text-warning"><i class="fa-solid fa-list-check me-1"></i><?= (int)$p['pending_tasks_count'] ?> Tasks</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?= get_status_badge_class($p['status']) ?>"><?= e(ucfirst(str_replace('_', ' ', $p['status']))) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= BASE_URL ?>/contractor/project-details.php?id=<?= $p['id'] ?>" class="btn btn-outline-light" title="View Details">
                                                <i class="fa-solid fa-eye text-info"></i>
                                            </a>
                                            <a href="<?= BASE_URL ?>/contractor/edit-project.php?id=<?= $p['id'] ?>" class="btn btn-outline-light" title="Edit Project">
                                                <i class="fa-solid fa-pen text-warning"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Nav -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center pt-3 border-top border-secondary mt-3">
                        <span class="text-muted extra-small">Showing Page <?= $page ?> of <?= $total_pages ?> (Total <?= $total_projects ?> Projects)</span>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link bg-dark border-secondary text-light" href="<?= BASE_URL ?>/contractor/projects.php?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>">Prev</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link <?= $i === $page ? 'bg-amber text-dark border-amber fw-bold' : 'bg-dark border-secondary text-light' ?>" href="<?= BASE_URL ?>/contractor/projects.php?page=<?= $i ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link bg-dark border-secondary text-light" href="<?= BASE_URL ?>/contractor/projects.php?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>">Next</a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
