<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = get_logged_user();
$db = getDB();

// Handle direct link redirection & mark read for specific ID
if (isset($_GET['id'])) {
    $target_id = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([$target_id, $user['id']]);
    $notif = $stmt->fetch();

    if ($notif) {
        mark_notification_as_read($target_id, $user['id']);
        if (!empty($notif['link'])) {
            $dest = (strpos($notif['link'], 'http') === 0) ? $notif['link'] : BASE_URL . '/' . ltrim($notif['link'], '/');
            redirect($dest);
        }
    }
}

// Handle Form POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        if ($action === 'mark_read') {
            $notif_id = (int)($_POST['notification_id'] ?? 0);
            if ($notif_id > 0) {
                mark_notification_as_read($notif_id, $user['id']);
                set_flash_message("Notification marked as read.", "info");
            }
        } elseif ($action === 'mark_all_read') {
            mark_all_notifications_as_read($user['id']);
            set_flash_message("All notifications marked as read.", "success");
        }
    }
    redirect('notifications.php');
}

$page_title = "Notification Center - BuildConnect";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';

// Filtering and Pagination Parameters
$read_filter = sanitize($_GET['read_status'] ?? 'all');
$type_category = sanitize($_GET['category'] ?? 'all');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Map categories to notification types
$category_type_map = [
    'jobs' => ['JOB_APPLICATION', 'APPLICATION_STATUS', 'JOB_POSTED', 'WORKER_HIRED'],
    'projects' => ['PROJECT_STATUS', 'MILESTONE_COMPLETED'],
    'tasks' => ['TASK_ASSIGNED', 'TASK_UPDATED', 'TASK_COMPLETED'],
    'contracts' => ['CONTRACT'],
    'reviews' => ['REVIEW'],
    'attendance' => ['ATTENDANCE']
];

$where = ["user_id = ?"];
$params = [$user['id']];

if ($read_filter === 'unread') {
    $where[] = "is_read = 0";
} elseif ($read_filter === 'read') {
    $where[] = "is_read = 1";
}

if ($type_category !== 'all' && isset($category_type_map[$type_category])) {
    $in_clause = implode(',', array_fill(0, count($category_type_map[$type_category]), '?'));
    $where[] = "type IN ({$in_clause})";
    foreach ($category_type_map[$type_category] as $t) {
        $params[] = $t;
    }
}

$where_sql = implode(" AND ", $where);

// Count total matching notifications
$count_stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE {$where_sql}");
$count_stmt->execute($params);
$total_records = (int)$count_stmt->fetchColumn();
$total_pages = max(1, ceil($total_records / $per_page));

// Fetch paginated notifications
$list_stmt = $db->prepare("
    SELECT * FROM notifications 
    WHERE {$where_sql} 
    ORDER BY id DESC 
    LIMIT {$per_page} OFFSET {$offset}
");
$list_stmt->execute($params);
$notifications = $list_stmt->fetchAll();

$unread_count = get_unread_notification_count($user['id']);
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-bell text-warning me-2"></i>Notification Center</h1>
                <p class="text-muted small mb-0">Platform alerts, job applications, contracts, and project updates.</p>
            </div>
            
            <?php if ($unread_count > 0): ?>
                <form action="<?= BASE_URL ?>/notifications.php" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-amber btn-sm fw-bold">
                        <i class="fa-solid fa-check-double me-1"></i> Mark All as Read
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($flash = get_flash_message()): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> py-2 px-3 small mb-4">
                <i class="fa-solid fa-circle-info me-1"></i> <?= sanitize($flash['message']) ?>
            </div>
        <?php endif; ?>

        <!-- Filters Bar -->
        <div class="bc-card p-3 mb-4">
            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="text-muted extra-small me-1"><i class="fa-solid fa-filter me-1"></i>Category:</span>
                    <a href="<?= BASE_URL ?>/notifications.php?category=all&read_status=<?= $read_filter ?>" class="btn btn-sm <?= ($type_category === 'all') ? 'btn-amber' : 'btn-outline-secondary text-light' ?>">All</a>
                    <a href="<?= BASE_URL ?>/notifications.php?category=jobs&read_status=<?= $read_filter ?>" class="btn btn-sm <?= ($type_category === 'jobs') ? 'btn-amber' : 'btn-outline-secondary text-light' ?>">Jobs & Hiring</a>
                    <a href="<?= BASE_URL ?>/notifications.php?category=projects&read_status=<?= $read_filter ?>" class="btn btn-sm <?= ($type_category === 'projects') ? 'btn-amber' : 'btn-outline-secondary text-light' ?>">Projects</a>
                    <a href="<?= BASE_URL ?>/notifications.php?category=tasks&read_status=<?= $read_filter ?>" class="btn btn-sm <?= ($type_category === 'tasks') ? 'btn-amber' : 'btn-outline-secondary text-light' ?>">Tasks</a>
                    <a href="<?= BASE_URL ?>/notifications.php?category=contracts&read_status=<?= $read_filter ?>" class="btn btn-sm <?= ($type_category === 'contracts') ? 'btn-amber' : 'btn-outline-secondary text-light' ?>">Contracts</a>
                    <a href="<?= BASE_URL ?>/notifications.php?category=reviews&read_status=<?= $read_filter ?>" class="btn btn-sm <?= ($type_category === 'reviews') ? 'btn-amber' : 'btn-outline-secondary text-light' ?>">Reviews</a>
                </div>

                <div class="d-flex gap-2">
                    <a href="<?= BASE_URL ?>/notifications.php?read_status=all&category=<?= $type_category ?>" class="btn btn-sm <?= ($read_filter === 'all') ? 'btn-secondary' : 'btn-outline-secondary text-muted' ?>">All Status</a>
                    <a href="<?= BASE_URL ?>/notifications.php?read_status=unread&category=<?= $type_category ?>" class="btn btn-sm <?= ($read_filter === 'unread') ? 'btn-danger' : 'btn-outline-secondary text-muted' ?>">Unread Only</a>
                </div>
            </div>
        </div>

        <div class="bc-card p-4">
            <?php if (empty($notifications)): ?>
                <div class="text-center text-muted py-5">
                    <i class="fa-solid fa-bell-slash fs-1 opacity-25 mb-3"></i>
                    <p class="mb-0">No notifications found matching selected criteria.</p>
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
                                        <?= sanitize($notif['title']) ?>
                                        <span class="badge bg-dark border border-secondary text-warning extra-small ms-2"><?= sanitize($notif['type']) ?></span>
                                        <?php if (!$notif['is_read']): ?>
                                            <span class="badge bg-danger extra-small ms-1">Unread</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-light small mb-1 opacity-90"><?= sanitize($notif['message']) ?></p>
                                    <span class="text-muted extra-small"><i class="fa-solid fa-clock me-1"></i><?= format_datetime($notif['created_at']) ?></span>
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-2">
                                <?php if (!empty($notif['link'])): ?>
                                    <a href="<?= BASE_URL ?>/notifications.php?id=<?= $notif['id'] ?>" class="btn btn-outline-warning btn-sm extra-small fw-bold">
                                        View Resource <i class="fa-solid fa-arrow-right ms-1"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if (!$notif['is_read']): ?>
                                    <form action="<?= BASE_URL ?>/notifications.php" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="mark_read">
                                        <input type="hidden" name="notification_id" value="<?= $notif['id'] ?>">
                                        <button type="submit" class="btn btn-outline-secondary btn-sm extra-small">
                                            Mark Read
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination Navigation -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center pt-4 mt-2 border-top border-secondary">
                        <span class="text-muted extra-small">Page <?= $page ?> of <?= $total_pages ?> (<?= $total_records ?> total)</span>
                        <div class="btn-group btn-group-sm">
                            <?php if ($page > 1): ?>
                                <a href="<?= BASE_URL ?>/notifications.php?page=<?= $page - 1 ?>&category=<?= $type_category ?>&read_status=<?= $read_filter ?>" class="btn btn-outline-secondary">Previous</a>
                            <?php endif; ?>
                            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                                <a href="<?= BASE_URL ?>/notifications.php?page=<?= $p ?>&category=<?= $type_category ?>&read_status=<?= $read_filter ?>" class="btn <?= ($p === $page) ? 'btn-amber' : 'btn-outline-secondary' ?>"><?= $p ?></a>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <a href="<?= BASE_URL ?>/notifications.php?page=<?= $page + 1 ?>&category=<?= $type_category ?>&read_status=<?= $read_filter ?>" class="btn btn-outline-secondary">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
