<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$page_title = "System Settings Foundation - Admin - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$current_user = currentUser();
$db = getDB();

// Test DB Status
$db_connected = false;
try {
    $db_connected = ($db->query("SELECT 1")->fetchColumn() == 1);
} catch (Exception $e) {
    $db_connected = false;
}
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-sliders text-warning me-2"></i>System Information & Settings</h1>
                <p class="text-muted small mb-0">Platform environment parameters, framework version info, and database status.</p>
            </div>
        </div>

        <div class="bc-card p-4 mb-4" style="max-width: 760px;">
            <h3 class="h5 text-white fw-bold mb-3"><i class="fa-solid fa-server text-info me-2"></i>Application Environment Summary</h3>
            
            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <tbody>
                        <tr>
                            <td class="text-muted small fw-semibold" style="width: 220px;">Application Name</td>
                            <td class="fw-bold text-white"><?= e(APP_NAME) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted small fw-semibold">Framework Version</td>
                            <td><span class="badge bg-dark border border-secondary font-monospace"><?= e(APP_VERSION) ?></span></td>
                        </tr>
                        <tr>
                            <td class="text-muted small fw-semibold">Tagline</td>
                            <td class="text-muted extra-small"><?= e(APP_TAGLINE) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted small fw-semibold">Database Driver</td>
                            <td class="font-monospace text-warning"><?= strtoupper(e(DB_DRIVER)) ?> (PDO)</td>
                        </tr>
                        <tr>
                            <td class="text-muted small fw-semibold">Database Host</td>
                            <td class="font-monospace text-white"><?= e(DB_HOST) ?> (Database: <?= e(DB_NAME) ?>)</td>
                        </tr>
                        <tr>
                            <td class="text-muted small fw-semibold">Database Status</td>
                            <td>
                                <?php if ($db_connected): ?>
                                    <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i>Connected (Operational)</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="fa-solid fa-circle-xmark me-1"></i>Disconnected</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted small fw-semibold">PHP Version</td>
                            <td class="font-monospace text-info"><?= phpversion() ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted small fw-semibold">Default Map Center</td>
                            <td class="text-muted extra-small">Ahmedabad, Gujarat, India (<?= DEFAULT_LATITUDE ?>, <?= DEFAULT_LONGITUDE ?>)</td>
                        </tr>
                        <tr>
                            <td class="text-muted small fw-semibold">AI Service Engine</td>
                            <td>
                                <span class="badge <?= AI_ENABLED ? 'bg-success' : 'bg-secondary' ?> me-2">
                                    <i class="fa-solid fa-brain me-1"></i><?= AI_ENABLED ? 'Enabled' : 'Disabled' ?>
                                </span>
                                <span class="text-muted extra-small font-monospace">Model: <?= sanitize(AI_MODEL) ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted small fw-semibold">AI Key Status</td>
                            <td>
                                <?php if (!empty(AI_API_KEY)): ?>
                                    <span class="badge bg-success"><i class="fa-solid fa-key me-1"></i>Key Configured (<?= sanitize(substr(AI_API_KEY, 0, 4)) ?>****)</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-shield-cat me-1"></i>Offline / Algorithmic Engine</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
