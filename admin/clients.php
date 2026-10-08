<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$page_title = "Client Directory - Admin - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$current_user = currentUser();
$db = getDB();

$error = '';
$flash = get_flash_message();

// Action Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security validation failed (CSRF token invalid).';
    } else {
        $action = $_POST['action'] ?? '';
        $user_id = (int)($_POST['user_id'] ?? 0);
        $client_id = (int)($_POST['client_id'] ?? 0);

        if ($action === 'edit_client') {
            $company_name = sanitize($_POST['company_name'] ?? '');
            $client_type = sanitize($_POST['client_type'] ?? 'Commercial Developer');
            $address = sanitize($_POST['address'] ?? '');
            $status = sanitize($_POST['status'] ?? 'active');

            $db->prepare("UPDATE clients SET company_name = ?, client_type = ?, address = ? WHERE id = ?")->execute([$company_name, $client_type, $address, $client_id]);
            $db->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$status, $user_id]);

            log_activity($current_user['id'], 'Client Details Updated', "Updated Client record #{$client_id}", 'client', $client_id);
            set_flash_message("Client details updated successfully.", 'success');
            redirect('admin/clients.php');
        }
    }
}

// Search
$search = trim($_GET['search'] ?? '');
$where = ["u.role = 'client'"];
$params = [];

if ($search !== '') {
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR cl.company_name LIKE ?)";
    $term = "%{$search}%";
    $params = [$term, $term, $term];
}

$sql = "
    SELECT cl.*, u.id as user_id, u.name, u.email, u.phone, u.status as user_status, u.created_at as joined_at,
    (SELECT COUNT(*) FROM projects WHERE client_id = u.id) as project_count
    FROM clients cl
    JOIN users u ON cl.user_id = u.id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY cl.id DESC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-user-tie text-warning me-2"></i>Client Directory</h1>
                <p class="text-muted small mb-0">Project owners, real estate developers, and client organizations.</p>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show py-2 px-3 small mb-3">
                <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3">
                <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= sanitize($error) ?>
            </div>
        <?php endif; ?>

        <!-- Search Bar -->
        <div class="bc-card p-3 mb-4">
            <form action="<?= BASE_URL ?>/admin/clients.php" method="GET" class="row g-3">
                <div class="col-md-11">
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" class="form-control" name="search" placeholder="Search clients by name, email, or company name..." value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-amber w-100 fw-bold"><i class="fa-solid fa-filter"></i></button>
                </div>
            </form>
        </div>

        <!-- Clients Table -->
        <div class="bc-card p-4">
            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Company / Representative</th>
                            <th>Client Type</th>
                            <th>Phone</th>
                            <th>Monitored Projects</th>
                            <th>Status</th>
                            <th>Joined Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clients)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No client records found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($clients as $c): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-white"><?= e($c['company_name'] ?: $c['name']) ?></div>
                                        <div class="text-muted extra-small">Rep: <?= e($c['name']) ?> (<?= e($c['email']) ?>)</div>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= e($c['client_type']) ?></span></td>
                                    <td class="small text-muted"><?= e($c['phone'] ?: 'N/A') ?></td>
                                    <td><span class="badge bg-warning text-dark fw-bold"><?= $c['project_count'] ?> Projects</span></td>
                                    <td><span class="badge <?= get_status_badge_class($c['user_status']) ?>"><?= e($c['user_status']) ?></span></td>
                                    <td class="text-muted extra-small"><?= format_date($c['joined_at']) ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editClientModal<?= $c['id'] ?>">
                                            <i class="fa-solid fa-pen-to-square"></i> Edit
                                        </button>
                                    </td>
                                </tr>

                                <!-- Edit Client Modal -->
                                <div class="modal fade" id="editClientModal<?= $c['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content bg-dark border-secondary text-light">
                                            <div class="modal-header border-secondary">
                                                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-building-user me-2 text-warning"></i>Edit Client</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form action="<?= BASE_URL ?>/admin/clients.php" method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="edit_client">
                                                <input type="hidden" name="client_id" value="<?= $c['id'] ?>">
                                                <input type="hidden" name="user_id" value="<?= $c['user_id'] ?>">
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label text-muted small fw-semibold">Company Name</label>
                                                        <input type="text" class="form-control" name="company_name" required value="<?= e($c['company_name']) ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label text-muted small fw-semibold">Client Category</label>
                                                        <input type="text" class="form-control" name="client_type" value="<?= e($c['client_type']) ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label text-muted small fw-semibold">Account Status</label>
                                                        <select class="form-select" name="status">
                                                            <option value="active" <?= $c['user_status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                            <option value="inactive" <?= $c['user_status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                            <option value="suspended" <?= $c['user_status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-secondary">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-amber btn-sm fw-bold">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
