<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('worker');

$page_title = "Edit Worker Profile - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$user_id = (int)$user['id'];

// Fetch worker record
$stmt = $db->prepare("SELECT * FROM workers WHERE user_id = ?");
$stmt->execute([$user_id]);
$worker = $stmt->fetch();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = "Security check failed. CSRF token mismatch.";
    }

    $name = sanitize($_POST['name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $trade_title = sanitize($_POST['trade_title'] ?? '');
    $experience_years = (int)($_POST['experience_years'] ?? 0);
    $hourly_rate = (float)($_POST['hourly_rate'] ?? 0);
    $daily_rate = (float)($_POST['daily_rate'] ?? 0);
    $city = sanitize($_POST['city'] ?? '');
    $state = sanitize($_POST['state'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $bio = sanitize($_POST['bio'] ?? '');

    if (empty($name)) {
        $errors[] = "Full name is required.";
    }
    if (empty($trade_title)) {
        $errors[] = "Trade title / specialization is required.";
    }

    // Avatar File Upload Validation & Processing
    $avatar_filename = $user['avatar']; // default to current avatar
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['avatar']['tmp_name'];
        $file_size = $_FILES['avatar']['size'];
        $original_name = $_FILES['avatar']['name'];
        $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];

        // Validate File Size (Max 3MB)
        if ($file_size > 3 * 1024 * 1024) {
            $errors[] = "Profile photo size must not exceed 3MB.";
        }

        // Validate Extension & MIME
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
            $safe_filename = 'avatar_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
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

            // 2. Insert or Update workers table
            if ($worker) {
                $stmt_w = $db->prepare("
                    UPDATE workers 
                    SET trade_title = ?, experience_years = ?, hourly_rate = ?, daily_rate = ?, city = ?, state = ?, address = ?, bio = ?, updated_at = NOW()
                    WHERE user_id = ?
                ");
                $stmt_w->execute([$trade_title, $experience_years, $hourly_rate, $daily_rate, $city, $state, $address, $bio, $user_id]);
            } else {
                $stmt_w = $db->prepare("
                    INSERT INTO workers (user_id, trade_title, experience_years, hourly_rate, daily_rate, city, state, address, bio)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt_w->execute([$user_id, $trade_title, $experience_years, $hourly_rate, $daily_rate, $city, $state, $address, $bio]);
            }

            $db->commit();

            log_activity($user_id, 'Worker Profile Updated', "Updated worker trade profile for {$name}", 'user', $user_id);

            set_flash_message("Profile updated successfully!", "success");
            redirect('worker/profile.php');
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
                    <i class="fa-solid fa-pen-to-square text-warning me-2"></i>Edit Worker Profile
                </h1>
                <p class="text-muted small mb-0">Update your trade title, rates, location, and professional biography.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/worker/profile.php" class="btn btn-outline-secondary btn-sm">
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
            <form action="<?= BASE_URL ?>/worker/edit-profile.php" method="POST" enctype="multipart/form-data" data-loading="true">
                <?= csrf_field() ?>

                <h3 class="h5 text-white fw-bold mb-3 border-bottom border-secondary pb-2">
                    <i class="fa-solid fa-user me-2 text-warning"></i>Personal Credentials
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
                        <label class="form-label text-muted small fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= e($_POST['name'] ?? $user['name']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">Email Address (Read-only)</label>
                        <input type="email" class="form-control bg-dark opacity-75" value="<?= e($user['email']) ?>" disabled>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">Phone Number</label>
                        <input type="text" name="phone" class="form-control" value="<?= e($_POST['phone'] ?? $user['phone']) ?>" placeholder="+91 98765 43212">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">Verification Badge Status</label>
                        <input type="text" class="form-control bg-dark opacity-75 text-capitalize" value="<?= e($worker['verification_status'] ?? 'pending') ?>" disabled>
                    </div>

                    <div class="col-12">
                        <label class="form-label text-muted small fw-semibold">Profile Photo Upload</label>
                        <input type="file" name="avatar" class="form-control" accept="image/png, image/jpeg, image/webp">
                        <div class="form-text text-muted extra-small">Max size 3MB. Formats: JPG, PNG, WEBP.</div>
                    </div>
                </div>

                <h3 class="h5 text-white fw-bold mb-3 border-bottom border-secondary pb-2">
                    <i class="fa-solid fa-helmet-safety me-2 text-warning"></i>Trade & Rate Parameters
                </h3>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">Trade Specialization <span class="text-danger">*</span></label>
                        <input type="text" name="trade_title" class="form-control" value="<?= e($_POST['trade_title'] ?? ($worker['trade_title'] ?? '')) ?>" required placeholder="e.g. Structural Welder & Crane Operator">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">Experience (Years)</label>
                        <input type="number" min="0" max="60" name="experience_years" class="form-control" value="<?= (int)($_POST['experience_years'] ?? ($worker['experience_years'] ?? 0)) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">Hourly Rate (₹/hr)</label>
                        <input type="number" step="0.50" min="0" name="hourly_rate" class="form-control font-monospace" value="<?= (float)($_POST['hourly_rate'] ?? ($worker['hourly_rate'] ?? 35)) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">Daily Rate (₹/day)</label>
                        <input type="number" step="1.00" min="0" name="daily_rate" class="form-control font-monospace" value="<?= (float)($_POST['daily_rate'] ?? ($worker['daily_rate'] ?? 280)) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">City</label>
                        <input type="text" name="city" class="form-control" value="<?= e($_POST['city'] ?? ($worker['city'] ?? '')) ?>" placeholder="Ahmedabad">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">State / Region</label>
                        <input type="text" name="state" class="form-control" value="<?= e($_POST['state'] ?? ($worker['state'] ?? '')) ?>" placeholder="Gujarat">
                    </div>

                    <div class="col-12">
                        <label class="form-label text-muted small fw-semibold">Full Address</label>
                        <input type="text" name="address" class="form-control" value="<?= e($_POST['address'] ?? ($worker['address'] ?? '')) ?>" placeholder="Street or residential area">
                    </div>

                    <div class="col-12">
                        <label class="form-label text-muted small fw-semibold">Professional Bio & Experience Summary</label>
                        <textarea name="bio" class="form-control" rows="4" placeholder="Detail your heavy machinery certifications, safety training, past site experience..."><?= e($_POST['bio'] ?? ($worker['bio'] ?? '')) ?></textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 pt-3 border-top border-secondary">
                    <a href="<?= BASE_URL ?>/worker/profile.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
                    <button type="submit" class="btn btn-amber btn-sm fw-bold">
                        <i class="fa-solid fa-save me-1"></i> Save Profile Changes
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
