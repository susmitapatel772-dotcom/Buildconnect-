<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$page_title = "Contractor Directory - Admin - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$current_user = currentUser();
$db = getDB();

$error = '';
$flash = get_flash_message();

// Handle Edit & Status Toggle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security validation failed (CSRF token invalid).';
    } else {
        $action = $_POST['action'] ?? '';
        $user_id = (int)($_POST['user_id'] ?? 0);
        $contractor_id = (int)($_POST['contractor_id'] ?? 0);

        if ($action === 'edit_contractor') {
            $company_name = sanitize($_POST['company_name'] ?? '');
            $license_no = sanitize($_POST['license_no'] ?? '');
            $address = sanitize($_POST['company_address'] ?? '');
            $status = sanitize($_POST['status'] ?? 'active');

            $db->prepare("UPDATE contractors SET company_name = ?, license_no = ?, company_address = ? WHERE id = ?")->execute([$company_name, $license_no, $address, $contractor_id]);
            $db->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$status, $user_id]);

            log_activity($current_user['id'], 'Contractor Details Updated', "Updated Contractor record #{$contractor_id}", 'contractor', $contractor_id);
            set_flash_message("Contractor details updated successfully.", 'success');
            redirect('admin/contractors.php');
        }
    }
}

// Search
$search = trim($_GET['search'] ?? '');
$where = ["u.role = 'contractor'"];
$params = [];

if ($search !== '') {
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR c.company_name LIKE ? OR c.license_no LIKE ?)";
    $term = "%{$search}%";
    $params = [$term, $term, $term, $term];
}

$sql = "
    SELECT c.*, u.id as user_id, u.name, u.email, u.phone, u.status as user_status, u.created_at as joined_at,
    (SELECT COUNT(*) FROM projects WHERE contractor_id = u.id) as project_count
    FROM contractors c
    JOIN users u ON c.user_id = u.id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY c.id DESC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$contractors = $stmt->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-city text-info me-2"></i>Contractor Directory</h1>
                <p class="text-muted small mb-0">Registered construction management companies, license details, and status control.</p>
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
            <form action="<?= BASE_URL ?>/admin/contractors.php" method="GET" class="row g-3">
                <div class="col-md-11">
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" class="form-control" name="search" placeholder="Search contractors by name, email, company, or license number..." value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-amber w-100 fw-bold"><i class="fa-solid fa-filter"></i></button>
                </div>
            </form>
        </div>

        <!-- Contractors Table -->
        <div class="bc-card p-4">
            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Company & Contact</th>
                            <th>License No</th>
                            <th>Phone</th>
                            <th>Projects</th>
                            <th>Status</th>
                            <th>Joined Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($contractors)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No contractor records found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($contractors as $c): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-white"><?= e($c['company_name']) ?></div>
                                        <div class="text-muted extra-small">Contact: <?= e($c['name']) ?> (<?= e($c['email']) ?>)</div>
                                    </td>
                                    <td><span class="badge bg-dark border border-secondary font-monospace"><?= e($c['license_no'] ?: 'N/A') ?></span></td>
                                    <td class="small text-muted"><?= e($c['phone'] ?: 'N/A') ?></td>
                                    <td><span class="badge bg-info text-dark fw-bold"><?= $c['project_count'] ?> Projects</span></td>
                                    <td><span class="badge <?= get_status_badge_class($c['user_status']) ?>"><?= e($c['user_status']) ?></span></td>
                                    <td class="text-muted extra-small"><?= format_date($c['joined_at']) ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editContractorModal<?= $c['id'] ?>">
                                            <i class="fa-solid fa-pen-to-square"></i> Edit
                                        </button>
                                    </td>
                                </tr>

                                <!-- Edit Contractor Modal -->
                                <div class="modal fade" id="editContractorModal<?= $c['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content bg-dark border-secondary text-light">
                                            <div class="modal-header border-secondary">
                                                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-building me-2 text-warning"></i>Edit Contractor Company</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form action="<?= BASE_URL ?>/admin/contractors.php" method="POST">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="edit_contractor">
                                                <input type="hidden" name="contractor_id" value="<?= $c['id'] ?>">
                                                <input type="hidden" name="user_id" value="<?= $c['user_id'] ?>">
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label text-muted small fw-semibold">Company Name</label>
                                                        <input type="text" class="form-control" name="company_name" required value="<?= e($c['company_name']) ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label text-muted small fw-semibold">License Number</label>
                                                        <input type="text" class="form-control" name="license_no" value="<?= e($c['license_no']) ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label text-muted small fw-semibold">Company Address</label>
                                                        <textarea class="form-control" name="company_address" rows="2"><?= e($c['company_address']) ?></textarea>
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
