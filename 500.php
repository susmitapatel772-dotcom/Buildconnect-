<?php
$page_title = "500 Server Error - BuildConnect";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>

<div class="bc-layout">
    <?php if (is_logged_in()): require_once __DIR__ . '/includes/sidebar.php'; endif; ?>

    <main class="bc-main-content w-100">
        <div class="container py-5 text-center">
            <div class="bc-card p-5 mx-auto border-secondary" style="max-width: 600px;">
                <div class="display-1 fw-bold text-info font-monospace mb-3">500</div>
                <h1 class="h3 text-white fw-bold mb-3"><i class="fa-solid fa-triangle-exclamation text-warning me-2"></i>Something Went Wrong</h1>
                <p class="text-muted small mb-4">An unexpected server error occurred. Technical details have been logged securely. Please try your request again shortly.</p>
                <a href="<?= BASE_URL ?>/index.php" class="btn btn-warning fw-bold px-4">
                    <i class="fa-solid fa-house me-2"></i> Return to Homepage
                </a>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
