<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('contractor');

$page_title = "Contractor Settings - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$user_id = (int)$user['id'];
$flash = get_flash_message();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-sliders text-warning me-2"></i>Account & System Settings
                </h1>
                <p class="text-muted small mb-0">Configure security settings, notifications, and company profile preferences.</p>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show mb-4" role="alert">
                <i class="fa-solid fa-circle-info me-2"></i> <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="bc-card p-4 h-100">
                    <h3 class="h5 text-white fw-bold mb-3">
                        <i class="fa-solid fa-shield-halved text-warning me-2"></i>Security & Authentication
                    </h3>
                    <p class="text-muted small mb-4">
                        Your account is secured via standard bcrypt password hashing and session tokens.
                    </p>

                    <div class="mb-3">
                        <label class="text-muted extra-small text-uppercase fw-semibold d-block">Registered Email</label>
                        <span class="text-white font-monospace"><?= e($user['email']) ?></span>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted extra-small text-uppercase fw-semibold d-block">Role Permissions</label>
                        <span class="badge bg-warning text-dark text-uppercase font-monospace"><?= e($user['role']) ?></span>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted extra-small text-uppercase fw-semibold d-block">Session Security</label>
                        <span class="text-success small"><i class="fa-solid fa-lock me-1"></i> CSRF Guard Enabled</span>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="bc-card p-4 h-100">
                    <h3 class="h5 text-white fw-bold mb-3">
                        <i class="fa-solid fa-bell text-info me-2"></i>Notifications & Preferences
                    </h3>
                    <p class="text-muted small mb-4">
                        Receive instant alerts regarding worker check-ins, job applications, and project updates.
                    </p>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="emailNotif" checked disabled>
                        <label class="form-check-label text-white small" for="emailNotif">
                            Project Activity Alerts
                        </label>
                        <div class="text-muted extra-small">Receive notifications when project status or workforce updates occur.</div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="appNotif" checked disabled>
                        <label class="form-check-label text-white small" for="appNotif">
                            Job Application Alerts
                        </label>
                        <div class="text-muted extra-small">Receive notifications when tradespeople apply for posted jobs.</div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
