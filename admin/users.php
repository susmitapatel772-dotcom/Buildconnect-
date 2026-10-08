<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$page_title = "User Management - Admin - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$current_user = currentUser();
$db = getDB();

$error = '';
$success = '';

// Handle POST actions: Status Change & User Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security validation failed (CSRF token invalid).';
    } else {
        $action = $_POST['action'] ?? '';
        $target_user_id = (int)($_POST['target_user_id'] ?? 0);

        // Safety Guard: Admin cannot alter their own active account status or demote themselves
        if ($target_user_id === (int)$current_user['id']) {
            $error = 'You cannot modify or suspend your own active administrator account.';
        } else {
            if ($action === 'update_status') {
                $new_status = sanitize($_POST['new_status'] ?? 'active');
                if (in_array($new_status, ['active', 'inactive', 'suspended'])) {
                    $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
                    if ($stmt->execute([$new_status, $target_user_id])) {
                        log_activity($current_user['id'], 'User Status Update', "Changed status of User #{$target_user_id} to {$new_status}", 'user', $target_user_id);
                        set_flash_message("User status updated to '{$new_status}' successfully.", 'success');
                        redirect('admin/users.php');
                    }
                }
            } elseif ($action === 'edit_user') {
                $name = sanitize($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = sanitize($_POST['phone'] ?? '');
                $role = sanitize($_POST['role'] ?? 'worker');
                $status = sanitize($_POST['status'] ?? 'active');

                if (empty($name) || empty($email)) {
                    $error = 'Name and Email address are required.';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Invalid email address format.';
                } else {
                    // Check duplicate email for other users
                    $chk = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $chk->execute([$email, $target_user_id]);
                    if ($chk->fetch()) {
                        $error = 'Another user with this email address already exists.';
                    } else {
                        $upd = $db->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ?, status = ? WHERE id = ?");
                        if ($upd->execute([$name, $email, $phone, $role, $status, $target_user_id])) {
                            log_activity($current_user['id'], 'User Profile Updated', "Updated details for User #{$target_user_id}", 'user', $target_user_id);
                            set_flash_message("User details updated successfully.", 'success');
                            redirect('admin/users.php');
                        }
                    }
                }
            }
        }
    }
}

// Search and Filter logic
$search = trim($_GET['search'] ?? '');
$role_filter = trim($_GET['role'] ?? 'all');
$status_filter = trim($_GET['status'] ?? 'all');

$where_clauses = ["1=1"];
$params = [];

if ($search !== '') {
    $where_clauses[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($role_filter !== 'all' && in_array($role_filter, ['admin', 'contractor', 'worker', 'client'])) {
    $where_clauses[] = "role = ?";
    $params[] = $role_filter;
}

if ($status_filter !== 'all' && in_array($status_filter, ['active', 'inactive', 'suspended'])) {
    $where_clauses[] = "status = ?";
    $params[] = $status_filter;
}

$sql = "SELECT * FROM users WHERE " . implode(" AND ", $where_clauses) . " ORDER BY id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$flash = get_flash_message();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-users text-warning me-2"></i>User Management</h1>
                <p class="text-muted small mb-0">Search, filter, edit details, and manage user account statuses.</p>
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

        <!-- Search & Filter Controls -->
        <div class="bc-card p-3 mb-4">
            <form action="<?= BASE_URL ?>/admin/users.php" method="GET" class="row g-3">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" class="form-control" name="search" placeholder="Search by name, email, or phone..." value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="role" onchange="this.form.submit()">
                        <option value="all" <?= $role_filter === 'all' ? 'selected' : '' ?>>All Roles</option>
                        <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="contractor" <?= $role_filter === 'contractor' ? 'selected' : '' ?>>Contractor</option>
                        <option value="worker" <?= $role_filter === 'worker' ? 'selected' : '' ?>>Worker</option>
                        <option value="client" <?= $role_filter === 'client' ? 'selected' : '' ?>>Client</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="status" onchange="this.form.submit()">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-amber w-100 fw-bold"><i class="fa-solid fa-filter"></i></button>
                </div>
            </form>
        </div>

        <!-- Users Data Table -->
        <div class="bc-card p-4">
            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No users found matching the selected criteria.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td class="font-monospace text-muted">#<?= $u['id'] ?></td>
                                    <td class="fw-bold text-white"><?= e($u['name']) ?></td>
                                    <td class="small text-muted"><?= e($u['email']) ?></td>
                                    <td class="small text-muted"><?= e($u['phone'] ?: 'N/A') ?></td>
                                    <td>
                                        <span class="badge bg-dark border border-secondary text-uppercase" style="font-size: 0.7rem;">
                                            <?= e($u['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= get_status_badge_class($u['status']) ?>">
                                            <?= e($u['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted extra-small"><?= format_date($u['created_at']) ?></td>
                                    <td class="text-end">
                                        <!-- Edit Modal Trigger -->
                                        <button class="btn btn-outline-warning btn-sm me-1" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $u['id'] ?>" title="Edit User">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>

                                        <!-- Status Switch Button -->
                                        <?php if ((int)$u['id'] !== (int)$current_user['id']): ?>
                                            <?php if ($u['status'] === 'active'): ?>
                                                <form action="<?= BASE_URL ?>/admin/users.php" method="POST" class="d-inline" onsubmit="return confirm('Suspend user account #<?= $u['id'] ?>?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                                    <input type="hidden" name="new_status" value="suspended">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Suspend User">
                                                        <i class="fa-solid fa-ban"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form action="<?= BASE_URL ?>/admin/users.php" method="POST" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                                    <input type="hidden" name="new_status" value="active">
                                                    <button type="submit" class="btn btn-outline-success btn-sm" title="Activate User">
                                                        <i class="fa-solid fa-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <!-- Modal Edit User -->
                                <div class="modal fade" id="editUserModal<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content bg-dark border-secondary text-light">
                                            <div class="modal-header border-secondary">
                                                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-user-pen me-2 text-warning"></i>Edit User #<?= $u['id'] ?></h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form action="<?= BASE_URL ?>/admin/users.php" method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="edit_user">
                                                <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label text-muted small fw-semibold">Full Name</label>
                                                        <input type="text" class="form-control" name="name" required value="<?= e($u['name']) ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label text-muted small fw-semibold">Email Address</label>
                                                        <input type="email" class="form-control" name="email" required value="<?= e($u['email']) ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label text-muted small fw-semibold">Phone Number</label>
                                                        <input type="text" class="form-control" name="phone" value="<?= e($u['phone']) ?>">
                                                    </div>
                                                    <div class="row g-2 mb-3">
                                                        <div class="col-6">
                                                            <label class="form-label text-muted small fw-semibold">Role</label>
                                                            <select class="form-select" name="role" <?= (int)$u['id'] === (int)$current_user['id'] ? 'disabled' : '' ?>>
                                                                <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                                <option value="contractor" <?= $u['role'] === 'contractor' ? 'selected' : '' ?>>Contractor</option>
                                                                <option value="worker" <?= $u['role'] === 'worker' ? 'selected' : '' ?>>Worker</option>
                                                                <option value="client" <?= $u['role'] === 'client' ? 'selected' : '' ?>>Client</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-6">
                                                            <label class="form-label text-muted small fw-semibold">Account Status</label>
                                                            <select class="form-select" name="status" <?= (int)$u['id'] === (int)$current_user['id'] ? 'disabled' : '' ?>>
                                                                <option value="active" <?= $u['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                                <option value="inactive" <?= $u['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                                <option value="suspended" <?= $u['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                                            </select>
                                                        </div>
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
