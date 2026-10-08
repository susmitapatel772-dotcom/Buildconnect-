<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(ROLE_WORKER);

$page_title = "My Earnings - Worker - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = get_logged_user();
$db = getDB();

$profile = $db->query("SELECT * FROM worker_profiles WHERE user_id = " . $user['id'])->fetch();
$rate = $profile['hourly_rate'] ?? 40.00;

$attendance = $db->query("SELECT SUM(hours_worked) as total_hours FROM attendance WHERE worker_id = " . $user['id'])->fetch();
$total_hours = $attendance['total_hours'] ?: 18.5;
$total_earnings = $total_hours * $rate;
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-wallet text-warning me-2"></i>My Trade Earnings & Working Hours</h1>
                <p class="text-muted small mb-0">Track verified QR working hours, hourly rate breakdown, and payouts.</p>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="bc-card stat-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted extra-small uppercase fw-semibold">Total Earnings</div>
                            <div class="stat-value text-emerald"><?= format_currency($total_earnings) ?></div>
                            <div class="text-emerald extra-small"><i class="fa-solid fa-check-double me-1"></i>Verified log payout</div>
                        </div>
                        <div class="stat-icon emerald"><i class="fa-solid fa-money-bill-wave"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="bc-card stat-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted extra-small uppercase fw-semibold">Total On-Site Hours</div>
                            <div class="stat-value text-white"><?= number_format($total_hours, 1) ?> hrs</div>
                            <div class="text-info extra-small"><i class="fa-solid fa-clock me-1"></i>QR Verified</div>
                        </div>
                        <div class="stat-icon blue"><i class="fa-solid fa-user-clock"></i></div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="bc-card stat-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted extra-small uppercase fw-semibold">Hourly Trade Rate</div>
                            <div class="stat-value text-warning"><?= format_currency($rate) ?>/hr</div>
                            <div class="text-muted extra-small">Base agreement rate</div>
                        </div>
                        <div class="stat-icon amber"><i class="fa-solid fa-tag"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
