<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$current_user = currentUser();
$db = getDB();

$error = '';
$success = '';

// =========================================================================
// 1. POST ACTION HANDLER (PRG Pattern: Execute BEFORE header.php / navbar.php)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        set_flash_message('Security validation failed (CSRF token invalid). Please try again.', 'danger');
        redirect('admin/projects.php');
    }

    $action = $_POST['action'] ?? '';

    // ACTION: ADD PROJECT
    if ($action === 'add_project') {
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $location = sanitize($_POST['location'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $city = sanitize($_POST['city'] ?? 'Ahmedabad');
        $state = sanitize($_POST['state'] ?? 'Gujarat');
        $postal_code = sanitize($_POST['postal_code'] ?? '');
        $budget = (float)($_POST['budget'] ?? 0);
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $status = sanitize($_POST['status'] ?? 'in_progress');
        $progress_percent = max(0, min(100, (int)($_POST['progress_percent'] ?? 0)));
        $contractor_id = (int)($_POST['contractor_id'] ?? 0);
        $client_id = (int)($_POST['client_id'] ?? 0);
        $lat = !empty($_POST['location_lat']) ? (float)$_POST['location_lat'] : DEFAULT_LATITUDE;
        $lng = !empty($_POST['location_lng']) ? (float)$_POST['location_lng'] : DEFAULT_LONGITUDE;

        // Validation
        if (empty($title)) {
            set_flash_message('Project Title is required.', 'danger');
            redirect('admin/projects.php');
        }
        if ($contractor_id <= 0) {
            set_flash_message('Please select a valid Contractor.', 'danger');
            redirect('admin/projects.php');
        }
        if ($client_id <= 0) {
            set_flash_message('Please select a valid Client.', 'danger');
            redirect('admin/projects.php');
        }
        if ($budget < 0) {
            set_flash_message('Project Budget must be a non-negative number.', 'danger');
            redirect('admin/projects.php');
        }
        if ($start_date && $end_date && strtotime($end_date) < strtotime($start_date)) {
            set_flash_message('End Date cannot be before Start Date.', 'danger');
            redirect('admin/projects.php');
        }

        $valid_statuses = ['pending', 'planning', 'active', 'in_progress', 'on_hold', 'completed', 'cancelled'];
        if (!in_array($status, $valid_statuses)) {
            $status = 'in_progress';
        }

        $qr_token = 'BC-PRJ-' . strtoupper(bin2hex(random_bytes(8)));

        try {
            $stmt = $db->prepare("
                INSERT INTO projects 
                (contractor_id, client_id, title, description, location, address, city, state, postal_code, location_lat, location_lng, budget, start_date, end_date, status, progress_percent, qr_code_token)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $contractor_id, $client_id, $title, $description, $location, $address, $city, $state, $postal_code,
                $lat, $lng, $budget, $start_date, $end_date, $status, $progress_percent, $qr_token
            ]);
            $new_id = $db->lastInsertId();

            log_activity($current_user['id'], 'Project Created', "Admin created new project '{$title}' (#{$new_id})", 'project', $new_id);
            set_flash_message("Project '{$title}' created successfully.", 'success');
        } catch (Exception $e) {
            error_log("Add Project Error: " . $e->getMessage());
            set_flash_message("Failed to create project: " . $e->getMessage(), 'danger');
        }
        redirect('admin/projects.php');
    }

    // ACTION: EDIT PROJECT
    if ($action === 'edit_project') {
        $project_id = (int)($_POST['project_id'] ?? 0);
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $location = sanitize($_POST['location'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $city = sanitize($_POST['city'] ?? 'Ahmedabad');
        $state = sanitize($_POST['state'] ?? 'Gujarat');
        $postal_code = sanitize($_POST['postal_code'] ?? '');
        $budget = (float)($_POST['budget'] ?? 0);
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $status = sanitize($_POST['status'] ?? 'in_progress');
        $progress_percent = max(0, min(100, (int)($_POST['progress_percent'] ?? 0)));
        $contractor_id = (int)($_POST['contractor_id'] ?? 0);
        $client_id = (int)($_POST['client_id'] ?? 0);
        $lat = !empty($_POST['location_lat']) ? (float)$_POST['location_lat'] : DEFAULT_LATITUDE;
        $lng = !empty($_POST['location_lng']) ? (float)$_POST['location_lng'] : DEFAULT_LONGITUDE;

        if ($project_id <= 0 || empty($title) || $contractor_id <= 0 || $client_id <= 0) {
            set_flash_message('Invalid data submitted for Project update.', 'danger');
            redirect('admin/projects.php');
        }

        if ($start_date && $end_date && strtotime($end_date) < strtotime($start_date)) {
            set_flash_message('End Date cannot be before Start Date.', 'danger');
            redirect('admin/projects.php');
        }

        $valid_statuses = ['pending', 'planning', 'active', 'in_progress', 'on_hold', 'completed', 'cancelled'];
        if (!in_array($status, $valid_statuses)) {
            $status = 'in_progress';
        }

        try {
            $stmt = $db->prepare("
                UPDATE projects 
                SET title = ?, description = ?, location = ?, address = ?, city = ?, state = ?, postal_code = ?,
                    location_lat = ?, location_lng = ?, budget = ?, start_date = ?, end_date = ?, status = ?,
                    progress_percent = ?, contractor_id = ?, client_id = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $title, $description, $location, $address, $city, $state, $postal_code,
                $lat, $lng, $budget, $start_date, $end_date, $status,
                $progress_percent, $contractor_id, $client_id, $project_id
            ]);

            log_activity($current_user['id'], 'Project Updated', "Updated Project #{$project_id} ('{$title}')", 'project', $project_id);
            set_flash_message("Project #{$project_id} updated successfully.", 'success');
        } catch (Exception $e) {
            error_log("Edit Project Error: " . $e->getMessage());
            set_flash_message("Failed to update project: " . $e->getMessage(), 'danger');
        }
        redirect('admin/projects.php');
    }

    // ACTION: UPDATE STATUS ONLY
    if ($action === 'update_status') {
        $project_id = (int)($_POST['project_id'] ?? 0);
        $new_status = sanitize($_POST['status'] ?? 'in_progress');
        $valid_statuses = ['pending', 'planning', 'active', 'in_progress', 'on_hold', 'completed', 'cancelled'];

        if ($project_id > 0 && in_array($new_status, $valid_statuses)) {
            try {
                $stmt = $db->prepare("UPDATE projects SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $project_id]);
                log_activity($current_user['id'], 'Project Status Change', "Changed Project #{$project_id} status to {$new_status}", 'project', $project_id);
                set_flash_message("Project #{$project_id} status changed to '{$new_status}'.", 'success');
            } catch (Exception $e) {
                set_flash_message("Error updating status: " . $e->getMessage(), 'danger');
            }
        }
        redirect('admin/projects.php');
    }

    // ACTION: DELETE PROJECT (Transaction-safe)
    if ($action === 'delete_project') {
        $project_id = (int)($_POST['project_id'] ?? 0);
        if ($project_id > 0) {
            try {
                $db->beginTransaction();

                // Safely delete dependent records if required by FK constraint
                $db->prepare("DELETE FROM project_documents WHERE project_id = ?")->execute([$project_id]);
                $db->prepare("DELETE FROM attendance WHERE project_id = ?")->execute([$project_id]);
                $db->prepare("DELETE FROM tasks WHERE project_id = ?")->execute([$project_id]);
                $db->prepare("DELETE FROM milestones WHERE project_id = ?")->execute([$project_id]);
                $db->prepare("DELETE FROM project_members WHERE project_id = ?")->execute([$project_id]);
                $db->prepare("DELETE FROM job_applications WHERE job_id IN (SELECT id FROM jobs WHERE project_id = ?)")->execute([$project_id]);
                $db->prepare("DELETE FROM jobs WHERE project_id = ?")->execute([$project_id]);
                $db->prepare("DELETE FROM payments WHERE project_id = ?")->execute([$project_id]);
                $db->prepare("DELETE FROM reviews WHERE project_id = ?")->execute([$project_id]);
                $db->prepare("DELETE FROM contracts WHERE project_id = ?")->execute([$project_id]);
                
                // Finally delete main project
                $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
                $stmt->execute([$project_id]);

                $db->commit();
                log_activity($current_user['id'], 'Project Deleted', "Deleted Project #{$project_id}", 'project', $project_id);
                set_flash_message("Project #{$project_id} and all related operational records deleted successfully.", 'success');
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log("Delete Project Error: " . $e->getMessage());
                set_flash_message("Failed to delete project: " . $e->getMessage(), 'danger');
            }
        }
        redirect('admin/projects.php');
    }
}

// =========================================================================
// 2. FETCH KPI STATISTICS
// =========================================================================
$total_projects = (int)$db->query("SELECT COUNT(*) FROM projects")->fetchColumn();
$active_projects = (int)$db->query("SELECT COUNT(*) FROM projects WHERE status IN ('active', 'in_progress')")->fetchColumn();
$completed_projects = (int)$db->query("SELECT COUNT(*) FROM projects WHERE status = 'completed'")->fetchColumn();
$pending_projects = (int)$db->query("SELECT COUNT(*) FROM projects WHERE status IN ('pending', 'planning', 'on_hold')")->fetchColumn();
$total_budget = (float)$db->query("SELECT SUM(budget) FROM projects")->fetchColumn();
$avg_progress = (float)$db->query("SELECT AVG(progress_percent) FROM projects")->fetchColumn();

// =========================================================================
// 3. FETCH DROPDOWNS (Clients & Contractors)
// =========================================================================
$clients_list = $db->query("
    SELECT u.id, u.name, u.email, cl.company_name 
    FROM users u 
    LEFT JOIN clients cl ON u.id = cl.user_id 
    WHERE u.role = 'client' AND u.status != 'suspended' 
    ORDER BY u.name ASC
")->fetchAll();

$contractors_list = $db->query("
    SELECT u.id, u.name, u.email, c.company_name 
    FROM users u 
    LEFT JOIN contractors c ON u.id = c.user_id 
    WHERE u.role = 'contractor' AND u.status != 'suspended' 
    ORDER BY u.name ASC
")->fetchAll();

// =========================================================================
// 4. SEARCH & FILTERING LOGIC
// =========================================================================
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? 'all');
$contractor_filter = (int)($_GET['contractor_id'] ?? 0);
$client_filter = (int)($_GET['client_id'] ?? 0);

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(p.title LIKE ? OR p.location LIKE ? OR p.city LIKE ? OR uc.name LIKE ? OR c.company_name LIKE ? OR ucl.name LIKE ? OR cl.company_name LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term, $term, $term, $term]);
}

if ($status_filter !== 'all' && !empty($status_filter)) {
    if ($status_filter === 'active') {
        $where[] = "p.status IN ('active', 'in_progress')";
    } else {
        $where[] = "p.status = ?";
        $params[] = $status_filter;
    }
}

if ($contractor_filter > 0) {
    $where[] = "p.contractor_id = ?";
    $params[] = $contractor_filter;
}

if ($client_filter > 0) {
    $where[] = "p.client_id = ?";
    $params[] = $client_filter;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Count filtered
$count_query = "
    SELECT COUNT(*) 
    FROM projects p
    LEFT JOIN users uc ON p.contractor_id = uc.id
    LEFT JOIN contractors c ON uc.id = c.user_id
    LEFT JOIN users ucl ON p.client_id = ucl.id
    LEFT JOIN clients cl ON ucl.id = cl.user_id
    {$where_clause}
";
$stmt_count = $db->prepare($count_query);
$stmt_count->execute($params);
$filtered_count = (int)$stmt_count->fetchColumn();
$total_pages = max(1, ceil($filtered_count / $limit));

// Fetch paginated projects
$data_query = "
    SELECT p.*,
           uc.name AS contractor_user_name,
           uc.email AS contractor_email,
           c.company_name AS contractor_company,
           ucl.name AS client_user_name,
           ucl.email AS client_email,
           cl.company_name AS client_company
    FROM projects p
    LEFT JOIN users uc ON p.contractor_id = uc.id
    LEFT JOIN contractors c ON uc.id = c.user_id
    LEFT JOIN users ucl ON p.client_id = ucl.id
    LEFT JOIN clients cl ON ucl.id = cl.user_id
    {$where_clause}
    ORDER BY p.id DESC
    LIMIT {$limit} OFFSET {$offset}
";
$stmt_data = $db->prepare($data_query);
$stmt_data->execute($params);
$projects = $stmt_data->fetchAll();

// =========================================================================
// 5. RENDER PAGE HEADER & NAVBAR
// =========================================================================
$page_title = "Global Projects Management - Admin - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
$flash = get_flash_message();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <!-- HEADER TITLE -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <div>
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-building text-warning me-2"></i>Global Construction Projects</h1>
                <p class="text-muted small mb-0">Monitor, create, manage, and inspect all platform development sites and contractor assignments.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= BASE_URL ?>/admin/project-map.php" class="btn btn-outline-info btn-sm fw-bold">
                    <i class="fa-solid fa-map-location-dot me-1"></i> Interactive Map View
                </a>
                <button type="button" class="btn btn-warning btn-sm fw-bold text-dark" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                    <i class="fa-solid fa-plus me-1"></i> Add New Project
                </button>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show py-2.5 px-3 small border-0 shadow-sm mb-4" role="alert">
                <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check text-success' : 'fa-triangle-exclamation text-danger' ?> me-2"></i>
                <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- KPI SUMMARY CARDS -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-4 col-xl-2">
                <div class="bc-card p-3 border-0 bg-dark rounded-3 shadow-sm h-100">
                    <div class="text-muted extra-small text-uppercase fw-bold mb-1">Total Projects</div>
                    <div class="h3 fw-bold text-white mb-0"><?= $total_projects ?></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="bc-card p-3 border-0 bg-dark rounded-3 shadow-sm h-100">
                    <div class="text-muted extra-small text-uppercase fw-bold mb-1">Active Sites</div>
                    <div class="h3 fw-bold text-success mb-0"><?= $active_projects ?></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="bc-card p-3 border-0 bg-dark rounded-3 shadow-sm h-100">
                    <div class="text-muted extra-small text-uppercase fw-bold mb-1">Completed</div>
                    <div class="h3 fw-bold text-info mb-0"><?= $completed_projects ?></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="bc-card p-3 border-0 bg-dark rounded-3 shadow-sm h-100">
                    <div class="text-muted extra-small text-uppercase fw-bold mb-1">Pending / Hold</div>
                    <div class="h3 fw-bold text-warning mb-0"><?= $pending_projects ?></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="bc-card p-3 border-0 bg-dark rounded-3 shadow-sm h-100">
                    <div class="text-muted extra-small text-uppercase fw-bold mb-1">Total Valuation</div>
                    <div class="h3 fw-bold text-warning mb-0" style="font-size: 1.1rem;"><?= format_currency($total_budget, '₹') ?></div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="bc-card p-3 border-0 bg-dark rounded-3 shadow-sm h-100">
                    <div class="text-muted extra-small text-uppercase fw-bold mb-1">Avg Progress</div>
                    <div class="h3 fw-bold text-primary mb-0"><?= number_format($avg_progress, 1) ?>%</div>
                </div>
            </div>
        </div>

        <!-- SEARCH AND FILTER BAR -->
        <div class="bc-card p-3 mb-4 rounded-3 border-secondary">
            <form method="GET" action="<?= BASE_URL ?>/admin/projects.php" class="row g-2 align-items-center">
                <div class="col-12 col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-dark border-secondary text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" name="search" class="form-select-sm bg-dark border-secondary text-light form-control" placeholder="Search project, location, contractor, client..." value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <select name="status" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active / In Progress</option>
                        <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="planning" <?= $status_filter === 'planning' ? 'selected' : '' ?>>Planning</option>
                        <option value="on_hold" <?= $status_filter === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="contractor_id" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="0">All Contractors</option>
                        <?php foreach ($contractors_list as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $contractor_filter === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= e($c['company_name'] ?: $c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="client_id" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="0">All Clients</option>
                        <?php foreach ($clients_list as $cl): ?>
                            <option value="<?= $cl['id'] ?>" <?= $client_filter === (int)$cl['id'] ? 'selected' : '' ?>>
                                <?= e($cl['company_name'] ?: $cl['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-warning btn-sm fw-bold w-100"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                    <?php if (!empty($search) || $status_filter !== 'all' || $contractor_filter > 0 || $client_filter > 0): ?>
                        <a href="<?= BASE_URL ?>/admin/projects.php" class="btn btn-outline-secondary btn-sm" title="Reset Filters"><i class="fa-solid fa-rotate-left"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- PROJECTS TABLE CARD -->
        <div class="bc-card p-4 rounded-3 border-secondary">
            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Client</th>
                            <th>Contractor</th>
                            <th>Location</th>
                            <th>Budget</th>
                            <th>Dates</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($projects)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-building-circle-xmark fa-2x mb-2 text-secondary d-block"></i>
                                    No projects found matching the current criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($projects as $p): ?>
                                <?php
                                $contractor_display = $p['contractor_company'] ?: $p['contractor_user_name'] ?: 'Unassigned';
                                $client_display = $p['client_company'] ?: $p['client_user_name'] ?: 'Unassigned';
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-white"><?= e($p['title']) ?></div>
                                        <div class="text-muted extra-small">
                                            Token: <span class="font-monospace text-info"><?= e($p['qr_code_token'] ?: 'N/A') ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold text-light"><?= e($client_display) ?></div>
                                        <div class="extra-small text-muted"><?= e($p['client_user_name'] ?: '') ?></div>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold text-light"><?= e($contractor_display) ?></div>
                                        <div class="extra-small text-muted"><?= e($p['contractor_user_name'] ?: '') ?></div>
                                    </td>
                                    <td>
                                        <div class="small text-light"><i class="fa-solid fa-location-dot text-danger me-1"></i><?= e($p['city'] ?: $p['location']) ?></div>
                                        <div class="extra-small text-muted"><?= e($p['location']) ?></div>
                                    </td>
                                    <td class="fw-bold text-warning">
                                        <?= format_currency($p['budget'], '₹') ?>
                                    </td>
                                    <td class="extra-small text-muted">
                                        <div><span class="text-light">Start:</span> <?= format_date($p['start_date']) ?></div>
                                        <div><span class="text-light">End:</span> <?= format_date($p['end_date']) ?></div>
                                    </td>
                                    <td style="min-width: 130px;">
                                        <div class="d-flex justify-content-between align-items-center extra-small mb-1">
                                            <span class="fw-bold text-white"><?= (int)$p['progress_percent'] ?>%</span>
                                        </div>
                                        <div class="progress bg-dark border border-secondary" style="height: 6px;">
                                            <div class="progress-bar bg-warning" role="progressbar" style="width: <?= (int)$p['progress_percent'] ?>%;"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <form action="<?= BASE_URL ?>/admin/projects.php" method="POST" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="project_id" value="<?= $p['id'] ?>">
                                            <select name="status" class="form-select form-select-sm bg-dark border-secondary text-light py-0 px-1 extra-small" onchange="this.form.submit()">
                                                <option value="pending" <?= $p['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="planning" <?= $p['status'] === 'planning' ? 'selected' : '' ?>>Planning</option>
                                                <option value="active" <?= $p['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                <option value="in_progress" <?= $p['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                                <option value="on_hold" <?= $p['status'] === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                                                <option value="completed" <?= $p['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                                <option value="cancelled" <?= $p['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            </select>
                                        </form>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-info" title="View Project Details" onclick="viewProject(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>)">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-warning" title="Edit Project" onclick="editProject(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>)">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" title="Delete Project" onclick="deleteProject(<?= $p['id'] ?>, '<?= e(addslashes($p['title'])) ?>')">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top border-secondary">
                    <div class="small text-muted">
                        Showing Page <strong class="text-white"><?= $page ?></strong> of <strong class="text-white"><?= $total_pages ?></strong> (Total <?= $filtered_count ?> projects)
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link bg-dark text-light border-secondary" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                                    <a class="page-link <?= $page === $i ? 'bg-warning text-dark border-warning fw-bold' : 'bg-dark text-light border-secondary' ?>" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link bg-dark text-light border-secondary" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- ========================================================================= -->
<!-- MODAL 1: ADD PROJECT MODAL -->
<!-- ========================================================================= -->
<div class="modal fade" id="addProjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark text-light border-secondary shadow-lg">
            <form action="<?= BASE_URL ?>/admin/projects.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_project">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-white fw-bold"><i class="fa-solid fa-building-circle-check text-warning me-2"></i>Create New Project</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-8">
                            <label class="form-label small text-muted">Project Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control bg-dark text-light border-secondary" required placeholder="e.g. Green Heights Residency">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted">Initial Status</label>
                            <select name="status" class="form-select bg-dark text-light border-secondary">
                                <option value="in_progress" selected>In Progress</option>
                                <option value="active">Active</option>
                                <option value="planning">Planning</option>
                                <option value="pending">Pending</option>
                                <option value="on_hold">On Hold</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-muted">Description</label>
                            <textarea name="description" class="form-control bg-dark text-light border-secondary" rows="2" placeholder="Brief project details, scope and specifications..."></textarea>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small text-muted">Contractor Assignment <span class="text-danger">*</span></label>
                            <select name="contractor_id" class="form-select bg-dark text-light border-secondary" required>
                                <option value="">Select Contractor...</option>
                                <?php foreach ($contractors_list as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= e($c['company_name'] ?: $c['name']) ?> (<?= e($c['name']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small text-muted">Client Owner <span class="text-danger">*</span></label>
                            <select name="client_id" class="form-select bg-dark text-light border-secondary" required>
                                <option value="">Select Client...</option>
                                <?php foreach ($clients_list as $cl): ?>
                                    <option value="<?= $cl['id'] ?>"><?= e($cl['company_name'] ?: $cl['name']) ?> (<?= e($cl['name']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small text-muted">Location Address</label>
                            <input type="text" name="location" class="form-control bg-dark text-light border-secondary" placeholder="e.g. Satellite Road, Ahmedabad">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-muted">City</label>
                            <input type="text" name="city" class="form-control bg-dark text-light border-secondary" value="Ahmedabad">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-muted">State</label>
                            <input type="text" name="state" class="form-control bg-dark text-light border-secondary" value="Gujarat">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted">Total Budget (₹)</label>
                            <input type="number" step="0.01" name="budget" class="form-control bg-dark text-light border-secondary" placeholder="e.g. 12500000" value="0.00">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted">Start Date</label>
                            <input type="date" name="start_date" class="form-control bg-dark text-light border-secondary">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted">Target End Date</label>
                            <input type="date" name="end_date" class="form-control bg-dark text-light border-secondary">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted">Initial Progress (%)</label>
                            <input type="number" name="progress_percent" min="0" max="100" class="form-control bg-dark text-light border-secondary" value="0">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted">Latitude</label>
                            <input type="text" name="location_lat" class="form-control bg-dark text-light border-secondary" value="<?= DEFAULT_LATITUDE ?>">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted">Longitude</label>
                            <input type="text" name="location_lng" class="form-control bg-dark text-light border-secondary" value="<?= DEFAULT_LONGITUDE ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-bold text-dark"><i class="fa-solid fa-check me-1"></i> Create Project</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 2: EDIT PROJECT MODAL -->
<!-- ========================================================================= -->
<div class="modal fade" id="editProjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark text-light border-secondary shadow-lg">
            <form action="<?= BASE_URL ?>/admin/projects.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit_project">
                <input type="hidden" name="project_id" id="edit_project_id">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-white fw-bold"><i class="fa-solid fa-pen-to-square text-warning me-2"></i>Edit Project Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-8">
                            <label class="form-label small text-muted">Project Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="edit_title" class="form-control bg-dark text-light border-secondary" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted">Status</label>
                            <select name="status" id="edit_status" class="form-select bg-dark text-light border-secondary">
                                <option value="pending">Pending</option>
                                <option value="planning">Planning</option>
                                <option value="active">Active</option>
                                <option value="in_progress">In Progress</option>
                                <option value="on_hold">On Hold</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-muted">Description</label>
                            <textarea name="description" id="edit_description" class="form-control bg-dark text-light border-secondary" rows="2"></textarea>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small text-muted">Contractor Assignment <span class="text-danger">*</span></label>
                            <select name="contractor_id" id="edit_contractor_id" class="form-select bg-dark text-light border-secondary" required>
                                <option value="">Select Contractor...</option>
                                <?php foreach ($contractors_list as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= e($c['company_name'] ?: $c['name']) ?> (<?= e($c['name']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small text-muted">Client Owner <span class="text-danger">*</span></label>
                            <select name="client_id" id="edit_client_id" class="form-select bg-dark text-light border-secondary" required>
                                <option value="">Select Client...</option>
                                <?php foreach ($clients_list as $cl): ?>
                                    <option value="<?= $cl['id'] ?>"><?= e($cl['company_name'] ?: $cl['name']) ?> (<?= e($cl['name']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label small text-muted">Location Address</label>
                            <input type="text" name="location" id="edit_location" class="form-control bg-dark text-light border-secondary">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-muted">City</label>
                            <input type="text" name="city" id="edit_city" class="form-control bg-dark text-light border-secondary">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-muted">State</label>
                            <input type="text" name="state" id="edit_state" class="form-control bg-dark text-light border-secondary">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted">Budget (₹)</label>
                            <input type="number" step="0.01" name="budget" id="edit_budget" class="form-control bg-dark text-light border-secondary">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted">Start Date</label>
                            <input type="date" name="start_date" id="edit_start_date" class="form-control bg-dark text-light border-secondary">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted">Target End Date</label>
                            <input type="date" name="end_date" id="edit_end_date" class="form-control bg-dark text-light border-secondary">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted">Progress (%)</label>
                            <input type="number" name="progress_percent" id="edit_progress_percent" min="0" max="100" class="form-control bg-dark text-light border-secondary">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted">Latitude</label>
                            <input type="text" name="location_lat" id="edit_location_lat" class="form-control bg-dark text-light border-secondary">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted">Longitude</label>
                            <input type="text" name="location_lng" id="edit_location_lng" class="form-control bg-dark text-light border-secondary">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-bold text-dark"><i class="fa-solid fa-save me-1"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 3: VIEW PROJECT DETAILS MODAL -->
<!-- ========================================================================= -->
<div class="modal fade" id="viewProjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark text-light border-secondary shadow-lg">
            <div class="modal-header border-secondary">
                <div>
                    <h5 class="modal-title text-white fw-bold mb-0" id="view_project_title">Project Details</h5>
                    <div class="extra-small text-muted">Token: <span id="view_project_token" class="font-monospace text-info"></span></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="p-2.5 bg-black rounded border border-secondary">
                            <div class="extra-small text-muted">Status</div>
                            <div id="view_status_badge" class="mt-1"></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-2.5 bg-black rounded border border-secondary">
                            <div class="extra-small text-muted">Overall Progress</div>
                            <div class="h5 fw-bold text-warning mb-0" id="view_progress_text">0%</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-2.5 bg-black rounded border border-secondary">
                            <div class="extra-small text-muted">Total Budget</div>
                            <div class="h5 fw-bold text-success mb-0" id="view_budget_text">₹0.00</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="p-2.5 bg-black rounded border border-secondary">
                            <div class="extra-small text-muted">Location</div>
                            <div class="small fw-semibold text-light text-truncate" id="view_location_text">N/A</div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <div class="p-3 bg-black rounded border border-secondary">
                            <h6 class="fw-bold text-warning mb-2"><i class="fa-solid fa-user-tie me-1"></i> Client Information</h6>
                            <div class="small text-white fw-bold" id="view_client_name">N/A</div>
                            <div class="extra-small text-muted" id="view_client_email">N/A</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="p-3 bg-black rounded border border-secondary">
                            <h6 class="fw-bold text-warning mb-2"><i class="fa-solid fa-helmet-safety me-1"></i> Contractor Firm</h6>
                            <div class="small text-white fw-bold" id="view_contractor_name">N/A</div>
                            <div class="extra-small text-muted" id="view_contractor_email">N/A</div>
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-black rounded border border-secondary mb-3">
                    <h6 class="fw-bold text-light mb-1"><i class="fa-solid fa-align-left text-info me-1"></i> Description</h6>
                    <p class="small text-muted mb-0" id="view_description_text">No detailed description provided for this project.</p>
                </div>

                <div class="p-3 bg-black rounded border border-secondary">
                    <h6 class="fw-bold text-light mb-2"><i class="fa-solid fa-calendar-days text-primary me-1"></i> Timeline & Map Coordinates</h6>
                    <div class="row text-muted extra-small">
                        <div class="col-6">Start Date: <span class="text-white" id="view_start_date">N/A</span></div>
                        <div class="col-6">Target End: <span class="text-white" id="view_end_date">N/A</span></div>
                        <div class="col-12 mt-2">Coordinates: <span class="font-monospace text-info" id="view_coords">N/A</span></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 4: DELETE CONFIRMATION MODAL -->
<!-- ========================================================================= -->
<div class="modal fade" id="deleteProjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light border-danger shadow-lg">
            <form action="<?= BASE_URL ?>/admin/projects.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_project">
                <input type="hidden" name="project_id" id="delete_project_id">
                <div class="modal-header border-danger">
                    <h5 class="modal-title text-danger fw-bold"><i class="fa-solid fa-triangle-exclamation me-2"></i>Confirm Project Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-light mb-2">Are you sure you want to delete the project <strong id="delete_project_title" class="text-white"></strong>?</p>
                    <div class="alert alert-danger py-2 px-3 extra-small rounded mb-0">
                        <i class="fa-solid fa-shield-cat me-1"></i> This operation will permanently erase the project and clean all related tasks, milestones, attendance, and contracts.
                    </div>
                </div>
                <div class="modal-footer border-danger">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-bold"><i class="fa-solid fa-trash me-1"></i> Delete Project</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewProject(p) {
    document.getElementById('view_project_title').innerText = p.title || 'Project Details';
    document.getElementById('view_project_token').innerText = p.qr_code_token || 'N/A';
    document.getElementById('view_progress_text').innerText = (p.progress_percent || 0) + '%';
    document.getElementById('view_budget_text').innerText = '₹' + (parseFloat(p.budget) || 0).toLocaleString('en-IN', {minimumFractionDigits: 2});
    document.getElementById('view_location_text').innerText = (p.city || p.location || 'N/A');
    document.getElementById('view_description_text').innerText = p.description || 'No detailed description provided.';
    
    document.getElementById('view_client_name').innerText = (p.client_company ? p.client_company + ' (' + p.client_user_name + ')' : (p.client_user_name || 'Unassigned'));
    document.getElementById('view_client_email').innerText = p.client_email || '';
    
    document.getElementById('view_contractor_name').innerText = (p.contractor_company ? p.contractor_company + ' (' + p.contractor_user_name + ')' : (p.contractor_user_name || 'Unassigned'));
    document.getElementById('view_contractor_email').innerText = p.contractor_email || '';
    
    document.getElementById('view_start_date').innerText = p.start_date || 'N/A';
    document.getElementById('view_end_date').innerText = p.end_date || 'N/A';
    document.getElementById('view_coords').innerText = (p.location_lat && p.location_lng) ? p.location_lat + ', ' + p.location_lng : 'Not Mapped';

    const badgeClass = (p.status === 'active' || p.status === 'in_progress' || p.status === 'completed') ? 'bg-success' : 'bg-warning text-dark';
    document.getElementById('view_status_badge').innerHTML = '<span class="badge ' + badgeClass + ' text-capitalize">' + p.status + '</span>';

    const modal = new bootstrap.Modal(document.getElementById('viewProjectModal'));
    modal.show();
}

function editProject(p) {
    document.getElementById('edit_project_id').value = p.id;
    document.getElementById('edit_title').value = p.title || '';
    document.getElementById('edit_description').value = p.description || '';
    document.getElementById('edit_location').value = p.location || '';
    document.getElementById('edit_city').value = p.city || 'Ahmedabad';
    document.getElementById('edit_state').value = p.state || 'Gujarat';
    document.getElementById('edit_budget').value = p.budget || 0;
    document.getElementById('edit_start_date').value = p.start_date || '';
    document.getElementById('edit_end_date').value = p.end_date || '';
    document.getElementById('edit_progress_percent').value = p.progress_percent || 0;
    document.getElementById('edit_status').value = p.status || 'in_progress';
    document.getElementById('edit_contractor_id').value = p.contractor_id || '';
    document.getElementById('edit_client_id').value = p.client_id || '';
    document.getElementById('edit_location_lat').value = p.location_lat || '23.0225';
    document.getElementById('edit_location_lng').value = p.location_lng || '72.5714';

    const modal = new bootstrap.Modal(document.getElementById('editProjectModal'));
    modal.show();
}

function deleteProject(id, title) {
    document.getElementById('delete_project_id').value = id;
    document.getElementById('delete_project_title').innerText = title;
    const modal = new bootstrap.Modal(document.getElementById('deleteProjectModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
