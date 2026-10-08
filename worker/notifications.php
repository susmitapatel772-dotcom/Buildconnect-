<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(ROLE_WORKER);

$page_title = "Notifications - Worker - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = get_logged_user();
$notifications = get_user_notifications($user['id'], 20);
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-bell text-warning me-2"></i>My Notifications</h1>
                <p class="text-muted small mb-0">Hiring updates, contract signing requests, and attendance alerts.</p>
            </div>
        </div>

        <div class="bc-card p-4" style="max-width: 720px;">
            <div class="list-group list-group-flush bg-transparent">
                <?php if (empty($notifications)): ?>
                    <p class="text-muted text-center py-4">No notifications present.</p>
                <?php else: ?>
                    <?php foreach ($notifications as $n): ?>
                        <div class="list-group-item bg-transparent text-light border-secondary px-0 py-3">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <span class="fw-bold text-warning"><?= sanitize($n['title']) ?></span>
                                <span class="text-muted extra-small"><?= format_datetime($n['created_at']) ?></span>
                            </div>
                            <p class="text-light small mb-2"><?= sanitize($n['message']) ?></p>
                            <?php if ($n['link']): ?>
                                <a href="<?= BASE_URL . $n['link'] ?>" class="btn btn-outline-amber btn-sm extra-small">View Link <i class="fa-solid fa-arrow-right ms-1"></i></a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
