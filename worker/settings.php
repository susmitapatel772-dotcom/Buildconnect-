<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('worker');

$page_title = "Worker Settings - BuildConnect";
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
                    <i class="fa-solid fa-sliders text-warning me-2"></i>Account & Security Settings
                </h1>
                <p class="text-muted small mb-0">Configure your worker account, security preferences, and site notifications.</p>
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
                        <i class="fa-solid fa-shield-halved text-warning me-2"></i>Account Credentials
                    </h3>
                    <p class="text-muted small mb-4">
                        Your account is secured via bcrypt password hashing and session authorization tokens.
                    </p>

                    <div class="mb-3">
                        <label class="text-muted extra-small text-uppercase fw-semibold d-block">Registered Email</label>
                        <span class="text-white font-monospace"><?= e($user['email']) ?></span>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted extra-small text-uppercase fw-semibold d-block">Role Privilege Level</label>
                        <span class="badge bg-warning text-dark text-uppercase font-monospace"><?= e($user['role']) ?></span>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted extra-small text-uppercase fw-semibold d-block">Session Security</label>
                        <span class="text-success small"><i class="fa-solid fa-lock me-1"></i> CSRF & Session Regeneration Active</span>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="bc-card p-4 h-100">
                    <h3 class="h5 text-white fw-bold mb-3">
                        <i class="fa-solid fa-bell text-info me-2"></i>Job & Site Alerts
                    </h3>
                    <p class="text-muted small mb-4">
                        Configure notification preferences for project assignments and document verification status.
                    </p>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="projectNotif" checked disabled>
                        <label class="form-check-label text-white small" for="projectNotif">
                            Project Assignment Alerts
                        </label>
                        <div class="text-muted extra-small">Receive notifications when contractors assign you to construction sites.</div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="docNotif" checked disabled>
                        <label class="form-check-label text-white small" for="docNotif">
                            Document Verification Updates
                        </label>
                        <div class="text-muted extra-small">Receive alerts when administrators review your uploaded credentials.</div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
