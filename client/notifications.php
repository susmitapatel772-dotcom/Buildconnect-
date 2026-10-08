<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('client');

$page_title = "Notifications - Client - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$user_id = (int)$user['id'];
$flash = get_flash_message();

// Handle Mark as Read / Mark All as Read Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (verify_csrf_token($token)) {
        $action = $_POST['action'] ?? '';
        if ($action === 'mark_read') {
            $notif_id = (int)($_POST['notification_id'] ?? 0);
            if ($notif_id > 0) {
                $stmt_u = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
                $stmt_u->execute([$notif_id, $user_id]);
                log_activity($user_id, 'Notification Marked Read', "Marked notification ID {$notif_id} as read", 'notification', $notif_id);
            }
        } elseif ($action === 'mark_all_read') {
            $stmt_u = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt_u->execute([$user_id]);
            log_activity($user_id, 'Notifications Marked All Read', "Marked all notifications as read", 'notification', $user_id);
        }
        set_flash_message("Notifications updated.", "info");
        redirect('client/notifications.php');
    }
}

// Fetch Notifications for this client
$stmt_n = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC");
$stmt_n->execute([$user_id]);
$notifications = $stmt_n->fetchAll();

// Count unread notifications
$unread_count = 0;
foreach ($notifications as $n) {
    if (!$n['is_read']) $unread_count++;
}
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-bell text-warning me-2"></i>Notifications Inbox
                </h1>
                <p class="text-muted small mb-0">Project updates, milestone completions, and document notifications.</p>
            </div>
            <?php if ($unread_count > 0): ?>
                <form action="<?= BASE_URL ?>/client/notifications.php" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-outline-amber btn-sm fw-bold">
                        <i class="fa-solid fa-check-double me-1"></i> Mark All as Read
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show mb-4" role="alert">
                <i class="fa-solid fa-circle-info me-2"></i> <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="bc-card p-4">
            <h2 class="h5 text-white fw-bold mb-3">
                <i class="fa-solid fa-inbox text-info me-2"></i>Notification History
            </h2>

            <?php if (empty($notifications)): ?>
                <div class="bc-empty-state py-5 text-center">
                    <i class="fa-solid fa-bell-slash fs-1 text-warning mb-3"></i>
                    <h3 class="h5 text-white fw-bold">No Notifications</h3>
                    <p class="text-muted small mb-0">Your notification inbox is clear.</p>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush bg-transparent">
                    <?php foreach ($notifications as $notif): ?>
                        <div class="list-group-item bg-transparent text-light border-secondary px-0 py-3 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-start gap-3">
                                <div class="mt-1">
                                    <?php if ($notif['is_read']): ?>
                                        <i class="fa-regular fa-envelope-open text-muted fs-5"></i>
                                    <?php else: ?>
                                        <i class="fa-solid fa-envelope text-warning fs-5"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-white mb-1">
                                        <?= e($notif['title']) ?>
                                        <?php if (!$notif['is_read']): ?>
                                            <span class="badge bg-warning text-dark extra-small ms-2">New</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-muted small mb-1"><?= e($notif['message']) ?></p>
                                    <span class="text-muted extra-small"><i class="fa-solid fa-clock me-1"></i><?= format_datetime($notif['created_at']) ?></span>
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-2">
                                <?php if (!empty($notif['link'])): ?>
                                    <a href="<?= BASE_URL ?>/<?= e($notif['link']) ?>" class="btn btn-outline-secondary btn-sm extra-small">View</a>
                                <?php endif; ?>

                                <?php if (!$notif['is_read']): ?>
                                    <form action="<?= BASE_URL ?>/client/notifications.php" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="mark_read">
                                        <input type="hidden" name="notification_id" value="<?= $notif['id'] ?>">
                                        <button type="submit" class="btn btn-outline-amber btn-sm extra-small">Mark Read</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
