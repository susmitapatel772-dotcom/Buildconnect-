<?php
$page_title = "Access Denied - BuildConnect";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

$user = currentUser();
$dashboard_url = $user ? get_role_redirect_url($user['role']) : 'index.php';
?>

<div class="container py-5 d-flex justify-content-center align-items-center" style="min-height: 70vh;">
    <div class="bc-card p-4 p-md-5 text-center shadow-lg border-danger" style="max-width: 500px;">
        <div class="text-danger mb-3 display-4">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <h1 class="h3 text-white fw-bold mb-2">Access Denied</h1>
        <p class="text-muted small mb-4">
            You do not have permission to access this area.
        </p>

        <div class="d-flex justify-content-center gap-3">
            <a href="<?= BASE_URL ?>/<?= $dashboard_url ?>" class="btn btn-amber btn-sm font-semibold">
                <i class="fa-solid fa-gauge me-1"></i> Return to Dashboard
            </a>
            <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-danger btn-sm">
                <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
