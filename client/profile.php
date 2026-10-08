<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('client');

$page_title = "Client Profile - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$user_id = (int)$user['id'];
$flash = get_flash_message();

// Fetch Client Profile
$stmt = $db->prepare("SELECT * FROM clients WHERE user_id = ?");
$stmt->execute([$user_id]);
$client = $stmt->fetch();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-id-card text-warning me-2"></i>Client Profile
                </h1>
                <p class="text-muted small mb-0">View company identity, contact representative, and account status.</p>
            </div>
            <div>
                <a href="<?= BASE_URL ?>/client/edit-profile.php" class="btn btn-amber btn-sm fw-bold">
                    <i class="fa-solid fa-pen-to-square me-1"></i> Edit Profile
                </a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show mb-4" role="alert">
                <i class="fa-solid fa-circle-info me-2"></i> <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Left Profile Card -->
            <div class="col-lg-4">
                <div class="bc-card p-4 text-center h-100">
                    <div class="position-relative d-inline-block mb-3">
                        <img src="<?= BASE_URL ?>/assets/images/<?= !empty($user['avatar']) ? e($user['avatar']) : 'default_avatar.png' ?>" 
                             alt="<?= e($user['name']) ?>" 
                             class="rounded-circle border border-warning shadow" 
                             style="width: 110px; height: 110px; object-fit: cover;"
                             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['name']) ?>&background=3b82f6&color=fff&size=128';">
                    </div>
                    
                    <h3 class="h4 text-white fw-bold mb-1"><?= e($user['name']) ?></h3>
                    <div class="text-warning fw-semibold mb-2">
                        <i class="fa-solid fa-building me-1"></i><?= e($client['company_name'] ?? 'Client Organization') ?>
                    </div>
                    <span class="badge bg-warning text-dark text-uppercase font-monospace px-3 py-1 mb-3">
                        <?= e($user['role']) ?>
                    </span>

                    <hr class="border-secondary my-3">

                    <div class="text-start space-y-2">
                        <div class="d-flex justify-content-between text-muted small py-1">
                            <span>Client Category:</span>
                            <span class="text-white font-semibold"><?= e($client['client_type'] ?? 'Commercial Developer') ?></span>
                        </div>
                        <div class="d-flex justify-content-between text-muted small py-1">
                            <span>Account Status:</span>
                            <span class="badge <?= get_status_badge_class($user['status']) ?>"><?= e($user['status']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between text-muted small py-1">
                            <span>Member Since:</span>
                            <span class="text-white"><?= format_date($user['created_at']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Profile Information -->
            <div class="col-lg-8">
                <div class="bc-card p-4 h-100">
                    <h3 class="h5 text-white fw-bold mb-4">
                        <i class="fa-solid fa-user-gear text-warning me-2"></i>Organization & Contact Details
                    </h3>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="text-muted extra-small text-uppercase fw-semibold d-block">Representative Name</label>
                            <span class="text-white fs-6 fw-semibold"><?= e($user['name']) ?></span>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted extra-small text-uppercase fw-semibold d-block">Email Address</label>
                            <span class="text-white fs-6 font-monospace"><?= e($user['email']) ?></span>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted extra-small text-uppercase fw-semibold d-block">Phone Number</label>
                            <span class="text-white fs-6"><?= !empty($user['phone']) ? e($user['phone']) : 'Not specified' ?></span>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted extra-small text-uppercase fw-semibold d-block">Company / Organization</label>
                            <span class="text-warning fs-6 fw-bold"><?= e($client['company_name'] ?? 'Not specified') ?></span>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted extra-small text-uppercase fw-semibold d-block">City</label>
                            <span class="text-white fs-6"><?= !empty($client['city']) ? e($client['city']) : 'Not specified' ?></span>
                        </div>

                        <div class="col-md-6">
                            <label class="text-muted extra-small text-uppercase fw-semibold d-block">State / Region</label>
                            <span class="text-white fs-6"><?= !empty($client['state']) ? e($client['state']) : 'Not specified' ?></span>
                        </div>

                        <div class="col-12">
                            <label class="text-muted extra-small text-uppercase fw-semibold d-block">Corporate Office Address</label>
                            <span class="text-white fs-6"><?= !empty($client['address']) ? e($client['address']) : 'Not specified' ?></span>
                        </div>
                    </div>

                    <div class="pt-3 border-top border-secondary text-end">
                        <a href="<?= BASE_URL ?>/client/edit-profile.php" class="btn btn-amber btn-sm fw-bold">
                            <i class="fa-solid fa-pen-to-square me-1"></i> Update Profile Information
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
