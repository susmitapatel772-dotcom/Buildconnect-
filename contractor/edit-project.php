<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('contractor');

$page_title = "Edit Project - Contractor - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$contractor_id = (int)$user['id'];
$proj_id = (int)($_GET['id'] ?? 0);

$error = '';
$flash = get_flash_message();

// Verify project belongs to current contractor strictly
$stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND contractor_id = ?");
$stmt->execute([$proj_id, $contractor_id]);
$proj = $stmt->fetch();

if (!$proj) {
    ?>
    <div class="bc-layout">
        <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="bc-main-content">
            <div class="bc-card p-5 text-center my-5 border-danger">
                <i class="fa-solid fa-lock text-danger fs-1 mb-3"></i>
                <h2 class="h4 text-white fw-bold">Access Denied</h2>
                <p class="text-muted small mb-4">You do not have permission to view or edit this project.</p>
                <a href="<?= BASE_URL ?>/contractor/projects.php" class="btn btn-amber btn-sm fw-bold">
                    <i class="fa-solid fa-arrow-left me-1"></i> Return to Projects
                </a>
            </div>
        </main>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// Fetch clients for dropdown
$clients_stmt = $db->query("
    SELECT u.id, u.name, c.company_name 
    FROM users u 
    LEFT JOIN clients c ON u.id = c.user_id 
    WHERE u.role = 'client' AND u.status = 'active'
    ORDER BY u.name ASC
");
$clients = $clients_stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security validation failed (CSRF token invalid).';
    } else {
        $title = sanitize($_POST['title'] ?? '');
        $client_id = (int)($_POST['client_id'] ?? 0);
        $description = sanitize($_POST['description'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $city = sanitize($_POST['city'] ?? 'Ahmedabad');
        $state_val = sanitize($_POST['state'] ?? 'Gujarat');
        $postal_code = sanitize($_POST['postal_code'] ?? '');
        $location = sanitize($_POST['location'] ?? '') ?: ($address . ', ' . $city . ', ' . $state_val);
        $location_lat = (float)($_POST['location_lat'] ?? $proj['location_lat']);
        $location_lng = (float)($_POST['location_lng'] ?? $proj['location_lng']);
        $budget = (float)($_POST['budget'] ?? 0);
        $start_date = sanitize($_POST['start_date'] ?? '');
        $end_date = sanitize($_POST['end_date'] ?? '');
        $status = sanitize($_POST['status'] ?? 'in_progress');

        if (empty($title)) {
            $error = 'Project title is required.';
        } elseif (empty($location)) {
            $error = 'Project location is required.';
        } elseif ($budget <= 0) {
            $error = 'Project budget must be greater than zero.';
        } elseif (!validate_coordinates($location_lat, $location_lng)) {
            $error = 'Invalid coordinates provided. Latitude must be -90 to 90 and Longitude -180 to 180.';
        } elseif (!in_array($status, ['planning', 'active', 'in_progress', 'on_hold', 'completed', 'cancelled'])) {
            $error = 'Invalid project status selected.';
        } elseif (!empty($start_date) && !empty($end_date) && strtotime($end_date) < strtotime($start_date)) {
            $error = 'End date cannot be prior to start date.';
        } else {
            $up_stmt = $db->prepare("
                UPDATE projects SET 
                    client_id = ?, title = ?, description = ?, location = ?,
                    address = ?, city = ?, state = ?, postal_code = ?,
                    location_lat = ?, location_lng = ?, budget = ?, 
                    start_date = ?, end_date = ?, status = ?, updated_at = NOW()
                WHERE id = ? AND contractor_id = ?
            ");

            $success = $up_stmt->execute([
                $client_id > 0 ? $client_id : null,
                $title,
                $description,
                $location,
                $address,
                $city,
                $state_val,
                $postal_code,
                $location_lat,
                $location_lng,
                $budget,
                $start_date ?: null,
                $end_date ?: null,
                $status,
                $proj_id,
                $contractor_id
            ]);

            if ($success) {
                // Synchronize client member in project_members if changed
                if ($client_id > 0) {
                    $mem_stmt = $db->prepare("
                        INSERT INTO project_members (project_id, user_id, role_in_project, status, joined_at)
                        VALUES (?, ?, 'Client Representative', 'active', NOW())
                        ON DUPLICATE KEY UPDATE status = 'active'
                    ");
                    $mem_stmt->execute([$proj_id, $client_id]);
                }

                // Recalculate project progress
                calculateProjectProgress($proj_id);

                log_activity($contractor_id, 'Project Location Updated', "Updated project location for #{$proj_id} ('{$title}') to {$city}, {$state_val}", 'project', $proj_id);
                set_flash_message("Project '{$title}' updated successfully.", 'success');
                redirect("contractor/project-details.php?id={$proj_id}");
            } else {
                $error = 'Failed to update project due to a database error.';
            }
        }
    }
}
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-pen-to-square text-warning me-2"></i>Edit Project #<?= $proj_id ?>
                </h1>
                <p class="text-muted small mb-0">Modify construction project details, location coordinates, and assigned client representative.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/contractor/project-details.php?id=<?= $proj_id ?>" class="btn btn-outline-light btn-sm fw-bold">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Details
                </a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 px-3 small rounded-3 mb-4">
                <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= sanitize($error) ?>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>/contractor/edit-project.php?id=<?= $proj_id ?>" method="POST" class="bc-card p-4">
            <?= csrf_field() ?>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label text-light fw-bold small mb-1">Project Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" value="<?= e($proj['title']) ?>" class="form-control bg-dark border-secondary text-light" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label text-light fw-bold small mb-1">Assign Client Representative</label>
                    <select name="client_id" class="form-select bg-dark border-secondary text-light">
                        <option value="0">-- No Client / Internal Project --</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $proj['client_id'] == $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['name']) ?> <?= $c['company_name'] ? "({$c['company_name']})" : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Location Information Section -->
                <div class="col-12 mt-4">
                    <h5 class="fw-bold text-white mb-2"><i class="fa-solid fa-map-location-dot text-warning me-2"></i>Project Site Location & Interactive Map</h5>
                    <p class="text-muted extra-small mb-3">Update site address parameters or click directly on the interactive map below to adjust coordinates.</p>
                </div>

                <div class="col-md-6">
                    <label class="form-label text-light fw-bold small mb-1">Street Address</label>
                    <input type="text" name="address" value="<?= e($proj['address'] ?? '') ?>" class="form-control bg-dark border-secondary text-light" placeholder="e.g. SG Highway, Bodakdev">
                </div>

                <div class="col-md-3">
                    <label class="form-label text-light fw-bold small mb-1">City <span class="text-danger">*</span></label>
                    <input type="text" name="city" value="<?= e($proj['city'] ?? 'Ahmedabad') ?>" class="form-control bg-dark border-secondary text-light" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label text-light fw-bold small mb-1">State / Region</label>
                    <input type="text" name="state" value="<?= e($proj['state'] ?? 'Gujarat') ?>" class="form-control bg-dark border-secondary text-light">
                </div>

                <div class="col-md-6">
                    <label class="form-label text-light fw-bold small mb-1">Full Location Text Summary <span class="text-danger">*</span></label>
                    <input type="text" name="location" value="<?= e($proj['location']) ?>" class="form-control bg-dark border-secondary text-light" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label text-light fw-bold small mb-1">Latitude</label>
                    <input type="number" step="0.0000001" name="location_lat" id="latInput" value="<?= e($proj['location_lat'] ?: DEFAULT_LATITUDE) ?>" class="form-control bg-dark border-secondary text-light">
                </div>

                <div class="col-md-3">
                    <label class="form-label text-light fw-bold small mb-1">Longitude</label>
                    <input type="number" step="0.0000001" name="location_lng" id="lngInput" value="<?= e($proj['location_lng'] ?: DEFAULT_LONGITUDE) ?>" class="form-control bg-dark border-secondary text-light">
                </div>

                <!-- Location Map Picker -->
                <div class="col-12 my-2">
                    <label class="form-label text-muted extra-small d-block mb-1"><i class="fa-solid fa-crosshairs me-1 text-warning"></i>Click on the map to set exact project GPS marker:</label>
                    <div id="locationPickerMap" style="height: 260px; width: 100%;" class="rounded border border-secondary bg-dark"></div>
                </div>

                <div class="col-md-6">
                    <label class="form-label text-light fw-bold small mb-1">Total Project Budget (INR ₹) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="1" name="budget" value="<?= e($proj['budget']) ?>" class="form-control bg-dark border-secondary text-light" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label text-light fw-bold small mb-1">Start Date</label>
                    <input type="date" name="start_date" value="<?= e($proj['start_date']) ?>" class="form-control bg-dark border-secondary text-light">
                </div>

                <div class="col-md-3">
                    <label class="form-label text-light fw-bold small mb-1">Expected Completion Date</label>
                    <input type="date" name="end_date" value="<?= e($proj['end_date']) ?>" class="form-control bg-dark border-secondary text-light">
                </div>

                <div class="col-md-6">
                    <label class="form-label text-light fw-bold small mb-1">Project Status</label>
                    <select name="status" class="form-select bg-dark border-secondary text-light">
                        <option value="planning" <?= $proj['status'] === 'planning' ? 'selected' : '' ?>>Planning Phase</option>
                        <option value="in_progress" <?= in_array($proj['status'], ['active', 'in_progress']) ? 'selected' : '' ?>>Active / In Progress</option>
                        <option value="on_hold" <?= $proj['status'] === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                        <option value="completed" <?= $proj['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= $proj['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label text-light fw-bold small mb-1">Project Overview & Description</label>
                    <textarea name="description" rows="4" class="form-control bg-dark border-secondary text-light"><?= e($proj['description']) ?></textarea>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top border-secondary">
                <a href="<?= BASE_URL ?>/contractor/project-details.php?id=<?= $proj_id ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-warning fw-bold px-4">
                    <i class="fa-solid fa-save me-1"></i> Update Project
                </button>
            </div>
        </form>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.BC_Maps) {
        BC_Maps.enableLocationPicker('locationPickerMap', 'latInput', 'lngInput');
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
