<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(ROLE_CONTRACTOR);

$page_title = "Digital Contracts - Contractor - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = get_logged_user();
$db = getDB();

$status_filter = sanitize($_GET['status'] ?? 'all');

$where_clause = "WHERE c.contractor_id = ?";
$params = [$user['id']];

if (!empty($status_filter) && $status_filter !== 'all') {
    $where_clause .= " AND c.status = ?";
    $params[] = $status_filter;
}

$stmt = $db->prepare("
    SELECT c.*, p.title as project_title, u.name as worker_name, u.email as worker_email, w.trade_title
    FROM contracts c
    JOIN projects p ON c.project_id = p.id
    JOIN users u ON c.worker_id = u.id
    LEFT JOIN workers w ON u.id = w.user_id
    {$where_clause}
    ORDER BY c.id DESC
");
$stmt->execute($params);
$contracts = $stmt->fetchAll();

// Counts for status filters
$counts_stmt = $db->prepare("
    SELECT status, COUNT(*) as cnt FROM contracts WHERE contractor_id = ? GROUP BY status
");
$counts_stmt->execute([$user['id']]);
$raw_counts = $counts_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$total_contracts = array_sum($raw_counts);
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-file-contract text-warning me-2"></i>Digital Worker Contracts</h1>
                <p class="text-muted small mb-0">Manage employment agreements, terms, and digital worker e-signatures.</p>
            </div>
            <a href="<?= BASE_URL ?>/contractor/create-contract.php" class="btn btn-amber fw-bold btn-sm">
                <i class="fa-solid fa-plus me-1"></i> Issue New Contract
            </a>
        </div>

        <?php if ($flash = get_flash_message()): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> py-2 px-3 small mb-4">
                <i class="fa-solid fa-circle-info me-1"></i> <?= sanitize($flash['message']) ?>
            </div>
        <?php endif; ?>

        <!-- Filters Bar -->
        <div class="bc-card p-3 mb-4">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="text-muted extra-small me-2"><i class="fa-solid fa-filter me-1"></i>Filter Status:</span>
                <a href="<?= BASE_URL ?>/contractor/contracts.php?status=all" class="btn btn-sm <?= ($status_filter === 'all') ? 'btn-amber' : 'btn-outline-secondary text-light' ?>">
                    All <span class="badge bg-dark ms-1"><?= $total_contracts ?></span>
                </a>
                <a href="<?= BASE_URL ?>/contractor/contracts.php?status=pending" class="btn btn-sm <?= ($status_filter === 'pending') ? 'btn-amber' : 'btn-outline-secondary text-light' ?>">
                    Pending Worker <span class="badge bg-dark ms-1"><?= $raw_counts['pending'] ?? 0 ?></span>
                </a>
                <a href="<?= BASE_URL ?>/contractor/contracts.php?status=active" class="btn btn-sm <?= ($status_filter === 'active') ? 'btn-amber' : 'btn-outline-secondary text-light' ?>">
                    Active <span class="badge bg-dark ms-1"><?= $raw_counts['active'] ?? 0 ?></span>
                </a>
                <a href="<?= BASE_URL ?>/contractor/contracts.php?status=completed" class="btn btn-sm <?= ($status_filter === 'completed') ? 'btn-amber' : 'btn-outline-secondary text-light' ?>">
                    Completed <span class="badge bg-dark ms-1"><?= $raw_counts['completed'] ?? 0 ?></span>
                </a>
                <a href="<?= BASE_URL ?>/contractor/contracts.php?status=draft" class="btn btn-sm <?= ($status_filter === 'draft') ? 'btn-amber' : 'btn-outline-secondary text-light' ?>">
                    Drafts <span class="badge bg-dark ms-1"><?= $raw_counts['draft'] ?? 0 ?></span>
                </a>
                <a href="<?= BASE_URL ?>/contractor/contracts.php?status=rejected" class="btn btn-sm <?= ($status_filter === 'rejected') ? 'btn-amber' : 'btn-outline-secondary text-light' ?>">
                    Rejected <span class="badge bg-dark ms-1"><?= $raw_counts['rejected'] ?? 0 ?></span>
                </a>
                <a href="<?= BASE_URL ?>/contractor/contracts.php?status=cancelled" class="btn btn-sm <?= ($status_filter === 'cancelled') ? 'btn-amber' : 'btn-outline-secondary text-light' ?>">
                    Cancelled <span class="badge bg-dark ms-1"><?= $raw_counts['cancelled'] ?? 0 ?></span>
                </a>
            </div>
        </div>

        <div class="bc-card p-4">
            <?php if (empty($contracts)): ?>
                <div class="text-center text-muted py-5">
                    <i class="fa-solid fa-file-signature fs-1 opacity-25 mb-3"></i>
                    <p class="mb-2">No digital contracts found matching current criteria.</p>
                    <a href="<?= BASE_URL ?>/contractor/create-contract.php" class="btn btn-outline-warning btn-sm mt-2">
                        Create New Contract
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Contract Ref</th>
                                <th>Worker</th>
                                <th>Project</th>
                                <th>Compensation</th>
                                <th>Worker E-Sign</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contracts as $ct): 
                                $expired = is_contract_expired($ct);
                                $status_display = $expired ? 'expired' : $ct['status'];
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-white"><?= sanitize($ct['contract_number'] ?: ('#CT-' . $ct['id'])) ?></div>
                                        <div class="text-light small"><?= sanitize($ct['title']) ?></div>
                                        <div class="text-muted extra-small">Issued: <?= format_date($ct['created_at']) ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-white"><?= sanitize($ct['worker_name']) ?></div>
                                        <div class="text-muted extra-small"><?= sanitize($ct['trade_title'] ?? 'Worker') ?></div>
                                    </td>
                                    <td class="small text-muted">
                                        <i class="fa-solid fa-building me-1"></i><?= sanitize($ct['project_title']) ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-warning"><?= format_currency($ct['payment_amount'] ?: $ct['pay_amount']) ?></div>
                                        <div class="text-muted extra-small text-capitalize"><?= sanitize($ct['payment_type']) ?> rate</div>
                                    </td>
                                    <td>
                                        <?php if (!empty($ct['worker_signature'])): ?>
                                            <span class="badge bg-success" title="Signed: <?= format_datetime($ct['worker_signed_at']) ?>">
                                                <i class="fa-solid fa-signature me-1"></i>Signed
                                            </span>
                                        <?php elseif ($ct['status'] === 'pending'): ?>
                                            <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i>Awaiting Worker</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Unsigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= get_status_badge_class($status_display) ?> text-capitalize">
                                            <?= sanitize($status_display) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= BASE_URL ?>/contractor/contract-details.php?id=<?= $ct['id'] ?>" class="btn btn-outline-light" title="View Digital Contract">
                                                <i class="fa-solid fa-eye me-1"></i> View
                                            </a>
                                            <?php if ($ct['status'] === 'draft'): ?>
                                                <a href="<?= BASE_URL ?>/contractor/edit-contract.php?id=<?= $ct['id'] ?>" class="btn btn-outline-warning" title="Edit Draft">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
