<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('client');

$page_title = "Edit Client Profile - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$user_id = (int)$user['id'];

// Fetch client profile record
$stmt = $db->prepare("SELECT * FROM clients WHERE user_id = ?");
$stmt->execute([$user_id]);
$client = $stmt->fetch();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = "CSRF security check failed.";
    }

    $name = sanitize($_POST['name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $company_name = sanitize($_POST['company_name'] ?? '');
    $client_type = sanitize($_POST['client_type'] ?? 'Commercial Developer');
    $address = sanitize($_POST['address'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $state = sanitize($_POST['state'] ?? '');

    if (empty($name)) {
        $errors[] = "Representative name is required.";
    }

    // Avatar File Upload Validation
    $avatar_filename = $user['avatar'];
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['avatar']['tmp_name'];
        $file_size = $_FILES['avatar']['size'];
        $original_name = $_FILES['avatar']['name'];
        $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];

        if ($file_size > 3 * 1024 * 1024) {
            $errors[] = "Profile photo size must not exceed 3MB.";
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file_tmp);
        finfo_close($finfo);

        if (!in_array($file_ext, $allowed_exts, true) || !in_array($mime_type, $allowed_mimes, true)) {
            $errors[] = "Invalid image file format. Only JPG, PNG, and WEBP images are permitted.";
        }

        if (empty($errors)) {
            $upload_dir = ROOT_PATH . '/assets/images/';
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0755, true);
            }
            $safe_filename = 'avatar_client_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
            $destination = $upload_dir . $safe_filename;

            if (move_uploaded_file($file_tmp, $destination)) {
                $avatar_filename = $safe_filename;
            } else {
                $errors[] = "Failed to save uploaded profile image.";
            }
        }
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();

            // 1. Update users table (name, phone, avatar)
            $stmt_u = $db->prepare("UPDATE users SET name = ?, phone = ?, avatar = ?, updated_at = NOW() WHERE id = ?");
            $stmt_u->execute([$name, $phone, $avatar_filename, $user_id]);

            // 2. Insert or Update clients table
            if ($client) {
                $stmt_c = $db->prepare("
                    UPDATE clients 
                    SET company_name = ?, client_type = ?, address = ?, city = ?, state = ?, updated_at = NOW()
                    WHERE user_id = ?
                ");
                $stmt_c->execute([$company_name, $client_type, $address, $city, $state, $user_id]);
            } else {
                $stmt_c = $db->prepare("
                    INSERT INTO clients (user_id, company_name, client_type, address, city, state)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt_c->execute([$user_id, $company_name, $client_type, $address, $city, $state]);
            }

            $db->commit();

            log_activity($user_id, 'Client Profile Updated', "Updated client profile for {$name}", 'user', $user_id);

            set_flash_message("Profile updated successfully!", "success");
            redirect('client/profile.php');
        } catch (PDOException $e) {
            $db->rollBack();
            $errors[] = "Database update error: " . $e->getMessage();
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
                    <i class="fa-solid fa-pen-to-square text-warning me-2"></i>Edit Client Profile
                </h1>
                <p class="text-muted small mb-0">Update contact representative information, company details, and location.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/client/profile.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Profile
                </a>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> <strong>Validation Errors:</strong>
                <ul class="mb-0 mt-2 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="bc-card p-4 mx-auto" style="max-width: 800px;">
            <form action="<?= BASE_URL ?>/client/edit-profile.php" method="POST" enctype="multipart/form-data" data-loading="true">
                <?= csrf_field() ?>

                <h3 class="h5 text-white fw-bold mb-3 border-bottom border-secondary pb-2">
                    <i class="fa-solid fa-user me-2 text-warning"></i>Contact Representative Information
                </h3>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">User ID (Read-only)</label>
                        <input type="text" class="form-control bg-dark opacity-75" value="<?= $user_id ?>" disabled>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">Role Permissions</label>
                        <input type="text" class="form-control bg-dark opacity-75 text-uppercase" value="<?= e($user['role']) ?>" disabled>
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
                        <input type="text" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? $user['phone']) ?>" placeholder="+91 98765 43213">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">Account Status</label>
                        <input type="text" class="form-control bg-dark opacity-75 text-capitalize" value="<?= e($user['status']) ?>" disabled>
                    </div>

                    <div class="col-12">
                        <label class="form-label text-muted small fw-semibold">Profile Photo Upload</label>
                        <input type="file" name="avatar" class="form-control" accept="image/png, image/jpeg, image/webp">
                        <div class="form-text text-muted extra-small">Max size 3MB. Formats: JPG, PNG, WEBP.</div>
                    </div>
                </div>

                <h3 class="h5 text-white fw-bold mb-3 border-bottom border-secondary pb-2">
                    <i class="fa-solid fa-building me-2 text-warning"></i>Organization Details
                </h3>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">Company / Organization Name</label>
                        <input type="text" name="company_name" class="form-control" value="<?= e($_POST['company_name'] ?? ($client['company_name'] ?? '')) ?>" placeholder="e.g. Skyline Urban Developers">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">Client Category</label>
                        <input type="text" name="client_type" class="form-control" value="<?= e($_POST['client_type'] ?? ($client['client_type'] ?? 'Commercial Developer')) ?>" placeholder="e.g. Commercial Real Estate Developer">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">City</label>
                        <input type="text" name="city" class="form-control" value="<?= e($_POST['city'] ?? ($client['city'] ?? '')) ?>" placeholder="e.g. Ahmedabad">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">State / Region</label>
                        <input type="text" name="state" class="form-control" value="<?= e($_POST['state'] ?? ($client['state'] ?? '')) ?>" placeholder="e.g. Gujarat">
                    </div>

                    <div class="col-12">
                        <label class="form-label text-muted small fw-semibold">Corporate Office Address</label>
                        <input type="text" name="address" class="form-control" value="<?= e($_POST['address'] ?? ($client['address'] ?? '')) ?>" placeholder="e.g. Prahlad Nagar, SG Highway, Ahmedabad">
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 pt-3 border-top border-secondary">
                    <a href="<?= BASE_URL ?>/client/profile.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
                    <button type="submit" class="btn btn-amber btn-sm fw-bold">
                        <i class="fa-solid fa-save me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
