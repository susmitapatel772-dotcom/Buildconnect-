<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('contractor');

$page_title = "Edit Contractor Profile - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$user_id = (int)$user['id'];

// Fetch current contractor profile record
$stmt = $db->prepare("SELECT * FROM contractors WHERE user_id = ?");
$stmt->execute([$user_id]);
$contractor = $stmt->fetch();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = "CSRF security token mismatch or expired request.";
    }

    $name = sanitize($_POST['name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $company_name = sanitize($_POST['company_name'] ?? '');
    $license_no = sanitize($_POST['license_no'] ?? '');
    $company_address = sanitize($_POST['company_address'] ?? '');
    $company_description = sanitize($_POST['company_description'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $state = sanitize($_POST['state'] ?? '');
    $website = sanitize($_POST['website'] ?? '');

    if (empty($name)) {
        $errors[] = "Contact name is required.";
    }
    if (empty($company_name)) {
        $errors[] = "Company name is required.";
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // 1. Update users table (name, phone)
            $stmt_u = $db->prepare("UPDATE users SET name = ?, phone = ?, updated_at = NOW() WHERE id = ?");
            $stmt_u->execute([$name, $phone, $user_id]);

            // 2. Insert or Update contractors table
            if ($contractor) {
                $stmt_c = $db->prepare("
                    UPDATE contractors 
                    SET company_name = ?, license_no = ?, company_address = ?, company_description = ?, city = ?, state = ?, website = ?, updated_at = NOW()
                    WHERE user_id = ?
                ");
                $stmt_c->execute([$company_name, $license_no, $company_address, $company_description, $city, $state, $website, $user_id]);
            } else {
                $stmt_c = $db->prepare("
                    INSERT INTO contractors (user_id, company_name, license_no, company_address, company_description, city, state, website)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt_c->execute([$user_id, $company_name, $license_no, $company_address, $company_description, $city, $state, $website]);
            }

            $db->commit();

            // Log activity
            log_activity($user_id, 'Profile Updated', "Updated contractor company profile for {$company_name}", 'user', $user_id);

            set_flash_message("Profile updated successfully.", "success");
            redirect('contractor/profile.php');
        } catch (PDOException $e) {
            $db->rollBack();
            $errors[] = "Database error updating profile: " . $e->getMessage();
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
                    <i class="fa-solid fa-pen-to-square text-warning me-2"></i>Edit Contractor Profile
                </h1>
                <p class="text-muted small mb-0">Update company identity, licensing, and contact details.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/contractor/profile.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Profile
                </a>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="bc-card p-4 mx-auto" style="max-width: 800px;">
            <form action="<?= BASE_URL ?>/contractor/edit-profile.php" method="POST" data-loading="true">
                <?= csrf_field() ?>

                <h3 class="h5 text-white fw-bold mb-3 border-bottom border-secondary pb-2">
                    <i class="fa-solid fa-user me-2 text-warning"></i>Contact Representative Information
                </h3>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">User ID</label>
                        <input type="text" class="form-control bg-dark opacity-75" value="<?= (int)$user['id'] ?>" disabled>
                        <div class="form-text text-muted extra-small">User ID cannot be altered.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">Role</label>
                        <input type="text" class="form-control bg-dark opacity-75 text-uppercase" value="<?= e($user['role']) ?>" disabled>
                        <div class="form-text text-muted extra-small">System role is fixed by security privileges.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">Representative Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= e($_POST['name'] ?? $user['name']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">Email Address (Read-only)</label>
                        <input type="email" class="form-control bg-dark opacity-75" value="<?= e($user['email']) ?>" disabled>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">Phone Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="+91 98765 43210" value="<?= e($_POST['phone'] ?? $user['phone']) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">Account Status</label>
                        <input type="text" class="form-control bg-dark opacity-75" value="<?= e($user['status']) ?>" disabled>
                    </div>
                </div>

                <h3 class="h5 text-white fw-bold mb-3 border-bottom border-secondary pb-2">
                    <i class="fa-solid fa-building me-2 text-warning"></i>Company Profile & Licensing
                </h3>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">Company Name <span class="text-danger">*</span></label>
                        <input type="text" name="company_name" class="form-control" value="<?= e($_POST['company_name'] ?? ($contractor['company_name'] ?? '')) ?>" required placeholder="e.g. Apex Builders Inc.">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">Contractor License / Reg No.</label>
                        <input type="text" name="license_no" class="form-control font-monospace" value="<?= e($_POST['license_no'] ?? ($contractor['license_no'] ?? '')) ?>" placeholder="GJ-LIC-984210">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">City</label>
                        <input type="text" name="city" class="form-control" value="<?= e($_POST['city'] ?? ($contractor['city'] ?? '')) ?>" placeholder="e.g. Ahmedabad">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">State / Region</label>
                        <input type="text" name="state" class="form-control" value="<?= e($_POST['state'] ?? ($contractor['state'] ?? '')) ?>" placeholder="e.g. Gujarat">
                    </div>

                    <div class="col-12">
                        <label class="form-label text-muted small fw-semibold">Company Office Address</label>
                        <input type="text" name="company_address" class="form-control" value="<?= e($_POST['company_address'] ?? ($contractor['company_address'] ?? '')) ?>" placeholder="e.g. SG Highway, Bodakdev, Ahmedabad">
                    </div>

                    <div class="col-12">
                        <label class="form-label text-muted small fw-semibold">Website URL</label>
                        <input type="url" name="website" class="form-control" value="<?= e($_POST['website'] ?? ($contractor['website'] ?? '')) ?>" placeholder="https://apexbuilders.example.com">
                    </div>

                    <div class="col-12">
                        <label class="form-label text-muted small fw-semibold">Company Description / Overview</label>
                        <textarea name="company_description" class="form-control" rows="4" placeholder="Brief description of construction specialties, achievements, and capabilities..."><?= e($_POST['company_description'] ?? ($contractor['company_description'] ?? '')) ?></textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 pt-3 border-top border-secondary">
                    <a href="<?= BASE_URL ?>/contractor/profile.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
                    <button type="submit" class="btn btn-amber btn-sm fw-bold">
                        <i class="fa-solid fa-save me-1"></i> Save Profile Changes
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
