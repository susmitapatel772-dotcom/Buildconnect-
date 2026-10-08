<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(ROLE_WORKER);

$page_title = "Digital Contracts - Worker - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = get_logged_user();
$db = getDB();

$status_filter = sanitize($_GET['status'] ?? 'all');

$where_clause = "WHERE c.worker_id = ?";
$params = [$user['id']];

if (!empty($status_filter) && $status_filter !== 'all') {
    $where_clause .= " AND c.status = ?";
    $params[] = $status_filter;
}

$stmt = $db->prepare("
    SELECT c.*, p.title as project_title, 
           u_c.name as contractor_name, c_co.company_name
    FROM contracts c
    JOIN projects p ON c.project_id = p.id
    JOIN users u_c ON c.contractor_id = u_c.id
    LEFT JOIN contractors c_co ON c.contractor_id = c_co.user_id
    {$where_clause}
    ORDER BY c.id DESC
");
$stmt->execute($params);
$contracts = $stmt->fetchAll();

// Filter counts
$counts_stmt = $db->prepare("
    SELECT status, COUNT(*) as cnt FROM contracts WHERE worker_id = ? GROUP BY status
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
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-file-contract text-success me-2"></i>My Digital Contracts</h1>
                <p class="text-muted small mb-0">Review compensation terms and sign employment agreements online.</p>
            </div>
        </div>

        <?php if ($flash = get_flash_message()): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> py-2 px-3 small mb-4">
                <i class="fa-solid fa-circle-info me-1"></i> <?= sanitize($flash['message']) ?>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="bc-card p-3 mb-4">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="text-muted extra-small me-2"><i class="fa-solid fa-filter me-1"></i>Filter Status:</span>
                <a href="<?= BASE_URL ?>/worker/contracts.php?status=all" class="btn btn-sm <?= ($status_filter === 'all') ? 'btn-amber' : 'btn-outline-secondary text-light' ?>">
                    All <span class="badge bg-dark ms-1"><?= $total_contracts ?></span>
                </a>
                <a href="<?= BASE_URL ?>/worker/contracts.php?status=pending" class="btn btn-sm <?= ($status_filter === 'pending') ? 'btn-amber' : 'btn-outline-secondary text-light' ?>">
                    Pending Acceptance <span class="badge bg-warning text-dark ms-1"><?= $raw_counts['pending'] ?? 0 ?></span>
                </a>
                <a href="<?= BASE_URL ?>/worker/contracts.php?status=active" class="btn btn-sm <?= ($status_filter === 'active') ? 'btn-amber' : 'btn-outline-secondary text-light' ?>">
                    Active <span class="badge bg-dark ms-1"><?= $raw_counts['active'] ?? 0 ?></span>
                </a>
                <a href="<?= BASE_URL ?>/worker/contracts.php?status=completed" class="btn btn-sm <?= ($status_filter === 'completed') ? 'btn-amber' : 'btn-outline-secondary text-light' ?>">
                    Completed <span class="badge bg-dark ms-1"><?= $raw_counts['completed'] ?? 0 ?></span>
                </a>
                <a href="<?= BASE_URL ?>/worker/contracts.php?status=rejected" class="btn btn-sm <?= ($status_filter === 'rejected') ? 'btn-amber' : 'btn-outline-secondary text-light' ?>">
                    Rejected <span class="badge bg-dark ms-1"><?= $raw_counts['rejected'] ?? 0 ?></span>
                </a>
            </div>
        </div>

        <div class="row g-4">
            <?php if (empty($contracts)): ?>
                <div class="col-12">
                    <div class="bc-card p-5 text-center text-muted">
                        <i class="fa-solid fa-file-signature fs-1 opacity-25 mb-3"></i>
                        <p class="mb-0">No digital contracts found matching current criteria.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($contracts as $ct): 
                    $expired = is_contract_expired($ct);
                    $status_display = $expired ? 'expired' : $ct['status'];
                ?>
                    <div class="col-md-6">
                        <div class="bc-card p-4 h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <span class="font-monospace text-warning extra-small fw-bold"><?= sanitize($ct['contract_number'] ?: ('#CT-' . $ct['id'])) ?></span>
                                        <h3 class="h5 text-white fw-bold mb-0 mt-1"><?= sanitize($ct['title']) ?></h3>
                                    </div>
                                    <span class="badge <?= get_status_badge_class($status_display) ?> text-capitalize"><?= sanitize($status_display) ?></span>
                                </div>

                                <div class="text-muted extra-small mb-1"><i class="fa-solid fa-building me-1 text-info"></i>Contractor: <?= sanitize($ct['company_name'] ?: $ct['contractor_name']) ?></div>
                                <div class="text-muted extra-small mb-3"><i class="fa-solid fa-city me-1 text-warning"></i>Project: <?= sanitize($ct['project_title']) ?></div>

                                <div class="d-flex justify-content-between align-items-center bg-dark p-2 rounded-3 border border-secondary mb-3">
                                    <span class="text-muted extra-small">Agreed Compensation</span>
                                    <span class="fw-bold text-warning fs-5"><?= format_currency($ct['payment_amount'] ?: $ct['pay_amount']) ?> / <?= sanitize($ct['payment_type']) ?></span>
                                </div>
                            </div>

                            <div class="pt-3 border-top border-secondary d-flex justify-content-between align-items-center">
                                <span class="text-muted extra-small">Issued: <?= format_date($ct['created_at']) ?></span>
                                
                                <a href="<?= BASE_URL ?>/worker/contract-details.php?id=<?= $ct['id'] ?>" class="btn <?= ($ct['status'] === 'pending') ? 'btn-amber' : 'btn-outline-light' ?> btn-sm fw-bold">
                                    <i class="fa-solid fa-eye me-1"></i> View Contract
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
