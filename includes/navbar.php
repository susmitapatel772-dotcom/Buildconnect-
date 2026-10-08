<?php
$user = get_logged_user();
?>
<nav class="bc-navbar bc-navbar-modern d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-4 flex-grow-1">
        <a href="<?= BASE_URL ?>/index.php" class="bc-brand-logo flex-shrink-0">
            <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="BuildConnect Logo" class="bc-brand-logo-img">
            <span class="bc-brand-text"><span class="bc-brand-build">Build</span><span class="bc-brand-connect">Connect</span></span>
        </a>

        <!-- Global Search Input with Ctrl+K Keyboard Badge -->
        <?php if ($user): ?>
            <div class="position-relative d-none d-md-block flex-grow-1" style="max-width: 380px;">
                <i class="fa-solid fa-magnifying-glass position-absolute top-50 start-0 translate-middle-y ms-3 text-muted" style="font-size: 0.85rem;"></i>
                <input type="text" class="form-control bc-global-search-input py-1.5" placeholder="Search users, projects, companies..." aria-label="Global Search">
                <span class="position-absolute top-50 end-0 translate-middle-y me-2.5 bc-kbd-shortcut">Ctrl + K</span>
            </div>
        <?php endif; ?>

        <!-- Header Navigation Links -->
        <?php $curr_script = basename($_SERVER['PHP_SELF']); ?>
        <div class="d-none d-xl-flex align-items-center gap-3 bc-nav-link-group">
            <a href="<?= BASE_URL ?>/index.php" class="bc-nav-link <?= ($curr_script === 'index.php' || $curr_script === '') ? 'active' : '' ?>">Home</a>
            <a href="<?= BASE_URL ?>/about.php" class="bc-nav-link <?= $curr_script === 'about.php' ? 'active' : '' ?>">About</a>
            <a href="<?= BASE_URL ?>/services.php" class="bc-nav-link <?= $curr_script === 'services.php' ? 'active' : '' ?>">Services</a>
            <a href="<?= BASE_URL ?>/how-it-works.php" class="bc-nav-link <?= $curr_script === 'how-it-works.php' ? 'active' : '' ?>">How It Works</a>
            <a href="<?= BASE_URL ?>/index.php#contact" class="bc-nav-link <?= $curr_script === 'contact.php' ? 'active' : '' ?>">Contact</a>
        </div>
    </div>

    <div class="d-flex align-items-center gap-3">
        <?php if ($user): 
            $unread_count = get_unread_notification_count($user['id']);
            $nav_notifications = get_user_notifications($user['id'], 5);
            $user_role_label = ucfirst($user['role']);
            if ($user['role'] === 'admin') $user_role_label = 'Executive Admin';
            elseif ($user['role'] === 'client') $user_role_label = 'Client';
            elseif ($user['role'] === 'worker') $user_role_label = 'Worker';
            elseif ($user['role'] === 'contractor') $user_role_label = 'Contractor';

            $user_avatar = (!empty($user['avatar']) && file_exists(__DIR__ . '/../assets/images/' . $user['avatar'])) ? $user['avatar'] : 'logo.png';
        ?>
            <!-- Theme Toggle Icon -->
            <button class="btn btn-sm btn-link text-slate-400 p-1 text-decoration-none d-none d-sm-inline-block" type="button" title="Toggle Light/Dark Theme">
                <i class="fa-regular fa-sun fs-5" style="color: #9FB6CF;"></i>
            </button>

            <!-- Notification Dropdown -->
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm position-relative text-light border-0" type="button" id="notifDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-bell text-warning fs-5" style="color: #FFAA16 !important;"></i>
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

            <!-- User Profile Avatar Pill (Name first, Role underneath) -->
            <div class="d-flex align-items-center gap-2 ps-2 border-start border-secondary border-opacity-50">
                <img src="<?= BASE_URL ?>/assets/images/<?= sanitize($user_avatar) ?>" alt="Avatar" class="rounded-circle border border-2 border-warning" style="width: 36px; height: 36px; object-fit: cover;">
                <div class="profile-info text-start d-flex flex-column justify-content-center ms-1 me-1" style="line-height: 1.2; min-width: 130px; flex-shrink: 0;">
                    <div class="fw-bold profile-name bc-user-name"><?= sanitize($user['name'] ?? 'User') ?></div>
                    <div class="extra-small profile-role bc-user-role"><?= sanitize($user_role_label) ?></div>
                </div>
            </div>

            <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-danger btn-sm ms-1">Sign Out</a>
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
