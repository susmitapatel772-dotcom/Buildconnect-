<?php
$user = get_logged_user();
?>
<nav class="bc-navbar d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-4">
        <a href="<?= BASE_URL ?>/index.php" class="bc-brand-logo">
            <i class="fa-solid fa-helmet-safety"></i>
            <span>Build<span class="text-warning">Connect</span></span>
        </a>

        <!-- Public Navigation Links -->
        <?php $curr_script = basename($_SERVER['PHP_SELF']); ?>
        <div class="d-none d-lg-flex align-items-center gap-3">
            <a href="<?= BASE_URL ?>/index.php" class="text-decoration-none small fw-medium <?= $curr_script === 'index.php' ? 'text-warning fw-bold border-bottom border-2 border-warning pb-1' : 'text-light opacity-75' ?>">Home</a>
            <a href="<?= BASE_URL ?>/about.php" class="text-decoration-none small fw-medium <?= $curr_script === 'about.php' ? 'text-warning fw-bold border-bottom border-2 border-warning pb-1' : 'text-light opacity-75' ?>">About</a>
            <a href="<?= BASE_URL ?>/services.php" class="text-decoration-none small fw-medium <?= $curr_script === 'services.php' ? 'text-warning fw-bold border-bottom border-2 border-warning pb-1' : 'text-light opacity-75' ?>">Services</a>
            <a href="<?= BASE_URL ?>/how-it-works.php" class="text-decoration-none small fw-medium <?= $curr_script === 'how-it-works.php' ? 'text-warning fw-bold border-bottom border-2 border-warning pb-1' : 'text-light opacity-75' ?>">How It Works</a>
            <a href="<?= BASE_URL ?>/index.php#contact" class="text-light text-decoration-none small fw-medium opacity-75">Contact</a>
        </div>
    </div>

    <div class="d-flex align-items-center gap-3">
        <?php if ($user): 
            $unread_count = get_unread_notification_count($user['id']);
            $nav_notifications = get_user_notifications($user['id'], 5);
        ?>
            <!-- Notification Dropdown -->
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm position-relative text-light" type="button" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                    <i class="fa-solid fa-bell text-warning"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">
                            <?= $unread_count ?>
                        </span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end bg-dark border-secondary p-0 shadow-lg" aria-labelledby="notifDropdown" style="width: 320px; max-height: 420px; overflow-y: auto;">
                    <div class="p-3 border-bottom border-secondary d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-white small"><i class="fa-solid fa-bell text-warning me-1"></i> Notifications</span>
                        <?php if ($unread_count > 0): ?>
                            <span class="badge bg-warning text-dark extra-small"><?= $unread_count ?> Unread</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="list-group list-group-flush">
                        <?php if (empty($nav_notifications)): ?>
                            <div class="p-3 text-center text-muted extra-small">No notifications present</div>
                        <?php else: ?>
                            <?php foreach ($nav_notifications as $n): ?>
                                <a href="<?= BASE_URL ?>/notifications.php?id=<?= $n['id'] ?>" class="list-group-item list-group-item-action bg-dark text-light border-secondary p-2.5 extra-small">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <span class="fw-bold text-white <?= !$n['is_read'] ? 'text-warning' : '' ?>"><?= sanitize($n['title']) ?></span>
                                        <span class="text-muted extra-small" style="font-size: 0.65rem;"><?= format_datetime($n['created_at']) ?></span>
                                    </div>
                                    <p class="text-muted extra-small mb-0 text-truncate" style="max-width: 280px;"><?= sanitize($n['message']) ?></p>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="p-2 text-center border-top border-secondary bg-secondary">
                        <a href="<?= BASE_URL ?>/notifications.php" class="text-warning text-decoration-none extra-small fw-bold">
                            View All Notifications <i class="fa-solid fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <span class="badge bg-warning text-dark text-uppercase font-monospace px-2 py-1">
                <?= sanitize($user['role']) ?>
            </span>
            <span class="text-light small fw-semibold d-none d-sm-inline"><?= sanitize($user['name']) ?></span>
            <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-danger btn-sm">Sign Out</a>
        <?php else: 
            $curr_page = basename($_SERVER['PHP_SELF']);
        ?>
            <a href="<?= BASE_URL ?>/login.php" class="btn <?= $curr_page === 'login.php' ? 'btn-amber' : 'btn-outline-amber' ?> btn-sm"><i class="fa-solid fa-right-to-bracket me-1"></i>Login</a>
            <a href="<?= BASE_URL ?>/register.php" class="btn <?= $curr_page === 'register.php' ? 'btn-amber' : 'btn-outline-amber' ?> btn-sm"><i class="fa-solid fa-user-plus me-1"></i>Register</a>
        <?php endif; ?>

        <!-- Mobile Toggle Button -->
        <button class="btn btn-outline-secondary btn-sm d-lg-none" type="button" data-bc-toggle="navbar" aria-label="Toggle navigation">
            <i class="fa-solid fa-bars"></i>
        </button>
    </div>
</nav>

<!-- Mobile Navigation Drawer -->
<div id="bc-navbar-menu" class="d-none bg-dark border-bottom border-secondary p-3 d-lg-none">
    <div class="d-flex flex-column gap-2">
        <a href="<?= BASE_URL ?>/index.php" class="text-light text-decoration-none small py-1">Home</a>
        <a href="<?= BASE_URL ?>/about.php" class="text-light text-decoration-none small py-1 opacity-75">About</a>
        <a href="<?= BASE_URL ?>/services.php" class="text-light text-decoration-none small py-1 opacity-75">Services</a>
        <a href="<?= BASE_URL ?>/how-it-works.php" class="text-light text-decoration-none small py-1 opacity-75">How It Works</a>
        <a href="<?= BASE_URL ?>/index.php#contact" class="text-light text-decoration-none small py-1 opacity-75">Contact</a>
    </div>
</div>
