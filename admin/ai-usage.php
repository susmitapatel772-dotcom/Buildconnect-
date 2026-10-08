<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(ROLE_ADMIN);

$page_title = "AI Usage & Audit Monitor - Admin - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$db = getDB();

// Aggregated Metrics from ai_usage_logs
$total_requests = (int)$db->query("SELECT COUNT(*) FROM ai_usage_logs")->fetchColumn();
$success_count = (int)$db->query("SELECT COUNT(*) FROM ai_usage_logs WHERE status IN ('success', 'cached')")->fetchColumn();
$fallback_count = (int)$db->query("SELECT COUNT(*) FROM ai_usage_logs WHERE status = 'fallback'")->fetchColumn();
$failed_count = (int)$db->query("SELECT COUNT(*) FROM ai_usage_logs WHERE status = 'failed'")->fetchColumn();

$avg_time = (float)$db->query("SELECT AVG(response_time_ms) FROM ai_usage_logs WHERE response_time_ms > 0")->fetchColumn();

// Requests by Action
$actions_breakdown = $db->query("
    SELECT action, COUNT(*) as count 
    FROM ai_usage_logs 
    GROUP BY action 
    ORDER BY count DESC
")->fetchAll();

// Recent AI Action Logs
$recent_logs = $db->query("
    SELECT l.*, u.name as user_name, u.role as user_role
    FROM ai_usage_logs l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.id DESC
    LIMIT 25
")->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <!-- Page Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-microchip text-warning me-2"></i>AI Usage & Audit Monitor
                </h1>
                <p class="text-muted small mb-0">Real-time tracking of AI request volumes, API performance, fallback rates, and system audit logs.</p>
            </div>
            <div>
                <span class="badge <?= AI_ENABLED ? 'bg-success' : 'bg-warning text-dark' ?> fs-6 p-2">
                    <i class="fa-solid fa-power-off me-1"></i>AI Engine: <?= AI_ENABLED ? 'ENABLED' : 'DISABLED' ?>
                </span>
            </div>
        </div>

        <!-- KPIs -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="bc-card p-3 text-center h-100">
                    <div class="text-muted extra-small text-uppercase fw-semibold mb-1">Total AI Requests</div>
                    <div class="display-6 fw-bold text-warning font-monospace mb-1"><?= number_format($total_requests) ?></div>
                    <div class="extra-small text-muted">All Features</div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="bc-card p-3 text-center h-100">
                    <div class="text-muted extra-small text-uppercase fw-semibold mb-1">Successful Runs</div>
                    <div class="display-6 fw-bold text-success font-monospace mb-1"><?= number_format($success_count) ?></div>
                    <div class="extra-small text-muted">Success / Cached</div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="bc-card p-3 text-center h-100">
                    <div class="text-muted extra-small text-uppercase fw-semibold mb-1">Fallback Executions</div>
                    <div class="display-6 fw-bold text-info font-monospace mb-1"><?= number_format($fallback_count) ?></div>
                    <div class="extra-small text-muted">Algorithmic Engine</div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="bc-card p-3 text-center h-100">
                    <div class="text-muted extra-small text-uppercase fw-semibold mb-1">Avg Latency</div>
                    <div class="display-6 fw-bold text-emerald font-monospace mb-1"><?= round($avg_time, 1) ?> ms</div>
                    <div class="extra-small text-muted">Model: <?= sanitize(AI_MODEL) ?></div>
                </div>
            </div>
        </div>

        <!-- Feature Breakdown & Logs -->
        <div class="row g-4 mb-4">
            <!-- Requests by Action -->
            <div class="col-lg-4">
                <div class="bc-card p-4 h-100">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-pie-chart text-warning me-2"></i>Requests by Feature</h3>
                    <?php if (empty($actions_breakdown)): ?>
                        <div class="text-muted small py-3 text-center">No AI usage logs recorded yet.</div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush bg-transparent">
                            <?php foreach ($actions_breakdown as $ab): ?>
                                <li class="list-group-item bg-transparent text-light border-secondary px-0 py-2 d-flex justify-content-between align-items-center">
                                    <span class="text-capitalize font-monospace"><?= sanitize(str_replace('_', ' ', $ab['action'])) ?></span>
                                    <span class="badge bg-warning text-dark font-monospace fw-bold"><?= number_format($ab['count']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Log Activity Table -->
            <div class="col-lg-8">
                <div class="bc-card p-4 h-100">
                    <h3 class="h6 text-white fw-bold mb-3"><i class="fa-solid fa-list text-info me-2"></i>Recent AI Audit Logs</h3>
                    <?php if (empty($recent_logs)): ?>
                        <div class="text-muted small py-3 text-center">No recent AI activity recorded.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle mb-0 extra-small">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Status</th>
                                        <th>Latency</th>
                                        <th class="text-end">Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_logs as $log): ?>
                                        <tr>
                                            <td class="fw-semibold text-white">
                                                <?= sanitize($log['user_name'] ?? 'System') ?>
                                                <span class="text-muted extra-small d-block"><?= sanitize($log['user_role'] ?? 'system') ?></span>
                                            </td>
                                            <td class="font-monospace text-warning"><?= sanitize($log['action']) ?></td>
                                            <td>
                                                <span class="badge <?= $log['status'] === 'failed' ? 'bg-danger' : 'bg-success' ?>">
                                                    <?= strtoupper($log['status']) ?>
                                                </span>
                                            </td>
                                            <td class="font-monospace text-muted"><?= $log['response_time_ms'] ?> ms</td>
                                            <td class="text-end font-monospace text-muted"><?= format_date($log['created_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
