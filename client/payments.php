<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('client');

$page_title = "Project Payments - Client Dashboard - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$client_user_id = (int)$user['id'];

$error = '';
$flash = get_flash_message();

// Handle New Payment POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $error = 'Security validation failed (CSRF token invalid). Please try again.';
    } else {
        $project_id = (int)($_POST['project_id'] ?? 0);
        $payee_id = (int)($_POST['payee_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $payment_method = sanitize($_POST['payment_method'] ?? 'Direct Transfer');
        $contract_id = !empty($_POST['contract_id']) ? (int)$_POST['contract_id'] : null;

        // Server-side Authorization: Verify project belongs to logged-in client
        $auth_stmt = $db->prepare("SELECT id, contractor_id, title FROM projects WHERE id = ? AND client_id = ?");
        $auth_stmt->execute([$project_id, $client_user_id]);
        $project = $auth_stmt->fetch();

        if (!$project) {
            $error = 'Unauthorized project selection or project does not exist.';
        } elseif ($amount <= 0) {
            $error = 'Please enter a valid payment amount greater than ₹0.';
        } else {
            // Default payee to project contractor if not specified
            if ($payee_id <= 0) {
                $payee_id = (int)$project['contractor_id'];
            }

            try {
                $db->beginTransaction();

                $stmt = $db->prepare("
                    INSERT INTO payments (contract_id, project_id, payer_id, payee_id, amount, payment_method, status, paid_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW())
                ");
                $stmt->execute([
                    $contract_id,
                    $project_id,
                    $client_user_id,
                    $payee_id,
                    $amount,
                    $payment_method
                ]);
                $payment_id = $db->lastInsertId();

                log_activity($client_user_id, 'CLIENT_PAYMENT_DISBURSED', "Disbursed " . format_currency($amount) . " for project: " . $project['title'], 'payment', $payment_id);
                create_notification($payee_id, 'Payment Received', "Client {$user['name']} disbursed " . format_currency($amount) . " for project {$project['title']}.", 'PAYMENT', "/contractor/payments.php", 'payment', $payment_id);

                $db->commit();
                set_flash_message('Payment of ' . format_currency($amount) . ' recorded successfully!', 'success');
                redirect('/client/payments.php');
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Client payment creation failed: " . $e->getMessage());
                $error = 'Failed to process payment record due to a database error.';
            }
        }
    }
}

// Search and Filter GET parameters
$filter_project_id = (int)($_GET['project_id'] ?? 0);
$filter_status = sanitize($_GET['status'] ?? 'all');
$search = sanitize($_GET['q'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Fetch Client Authorized Projects for dropdown
$p_stmt = $db->prepare("
    SELECT p.id, p.title, p.contractor_id, u.name as contractor_name, c.company_name as contractor_company 
    FROM projects p
    LEFT JOIN users u ON p.contractor_id = u.id
    LEFT JOIN contractors c ON c.user_id = u.id
    WHERE p.client_id = ? 
    ORDER BY p.id DESC
");
$p_stmt->execute([$client_user_id]);
$client_projects = $p_stmt->fetchAll();

// Build Payments Query
$where = ["p.client_id = ?"];
$params = [$client_user_id];

if ($filter_project_id > 0) {
    $where[] = "pay.project_id = ?";
    $params[] = $filter_project_id;
}

if (in_array($filter_status, ['completed', 'pending', 'failed'])) {
    $where[] = "pay.status = ?";
    $params[] = $filter_status;
}

if (!empty($search)) {
    $where[] = "(p.title LIKE ? OR payee.name LIKE ? OR pay.payment_method LIKE ?)";
    $s_term = "%{$search}%";
    $params[] = $s_term;
    $params[] = $s_term;
    $params[] = $s_term;
}

$where_sql = implode(' AND ', $where);

// Summary Metrics
$m_stmt = $db->prepare("
    SELECT 
        COALESCE(SUM(pay.amount), 0) as total_disbursed,
        COALESCE(SUM(CASE WHEN pay.status = 'completed' THEN pay.amount ELSE 0 END), 0) as completed_amount,
        COALESCE(SUM(CASE WHEN pay.status = 'pending' THEN pay.amount ELSE 0 END), 0) as pending_amount,
        COUNT(pay.id) as payment_count
    FROM payments pay
    JOIN projects p ON pay.project_id = p.id
    WHERE p.client_id = ?
");
$m_stmt->execute([$client_user_id]);
$metrics = $m_stmt->fetch();

// Total Payments Count for Pagination
$count_stmt = $db->prepare("
    SELECT COUNT(*) 
    FROM payments pay
    JOIN projects p ON pay.project_id = p.id
    LEFT JOIN users payee ON pay.payee_id = payee.id
    WHERE {$where_sql}
");
$count_stmt->execute($params);
$total_rows = (int)$count_stmt->fetchColumn();
$total_pages = max(1, ceil($total_rows / $per_page));

// Fetch Payments Listing
$sql = "
    SELECT pay.*, p.title as project_title, payee.name as payee_name, payee.role as payee_role,
           c.contract_number, c.title as contract_title
    FROM payments pay
    JOIN projects p ON pay.project_id = p.id
    LEFT JOIN users payee ON pay.payee_id = payee.id
    LEFT JOIN contracts c ON pay.contract_id = c.id
    WHERE {$where_sql}
    ORDER BY pay.paid_at DESC, pay.id DESC
    LIMIT {$per_page} OFFSET {$offset}
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();
?>

<div class="dashboard-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="dashboard-content">
        <!-- Page Header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h1 class="h2 fw-extrabold text-white bc-page-title mb-1">
                    <i class="fa-solid fa-indian-rupee-sign text-warning me-2"></i>Project Payments & Disbursements
                </h1>
                <p class="text-slate-400 small mb-0">Track milestone disbursements, contractor payouts, and payment history for your construction projects.</p>
            </div>
            <div>
                <button type="button" class="btn btn-orange fw-bold rounded-3 px-3 py-2 shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#newPaymentModal">
                    <i class="fa-solid fa-plus"></i> Record New Payment
                </button>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show mb-4" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i> <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= sanitize($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- KPI Statistics Row -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="bc-metric-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bc-metric-badge-blue p-2.5 rounded-3"><i class="fa-solid fa-wallet fs-5"></i></div>
                        <span class="text-slate-400 extra-small fw-bold">Total Disbursed</span>
                    </div>
                    <div class="bc-kpi-amount bc-kpi-amount-white mb-1"><?= format_currency($metrics['total_disbursed']) ?></div>
                    <div class="text-slate-400 extra-small"><?= number_format($metrics['payment_count']) ?> Transactions Recorded</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="bc-metric-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bc-metric-badge-green p-2.5 rounded-3"><i class="fa-solid fa-circle-check fs-5"></i></div>
                        <span class="text-slate-400 extra-small fw-bold">Completed Payouts</span>
                    </div>
                    <div class="bc-kpi-amount bc-kpi-amount-completed mb-1"><?= format_currency($metrics['completed_amount']) ?></div>
                    <div class="text-success extra-small fw-semibold"><i class="fa-solid fa-check me-1"></i>Verified Clearances</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="bc-metric-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bc-metric-badge-amber p-2.5 rounded-3"><i class="fa-solid fa-clock fs-5"></i></div>
                        <span class="text-slate-400 extra-small fw-bold">Pending Approval</span>
                    </div>
                    <div class="bc-kpi-amount bc-kpi-amount-pending mb-1"><?= format_currency($metrics['pending_amount']) ?></div>
                    <div class="text-warning extra-small fw-semibold">Escrow / Processing</div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="bc-metric-card h-100">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="stat-icon bc-metric-badge-purple p-2.5 rounded-3"><i class="fa-solid fa-building fs-5"></i></div>
                        <span class="text-slate-400 extra-small fw-bold">Active Projects</span>
                    </div>
                    <div class="bc-kpi-amount bc-kpi-amount-white mb-1"><?= count($client_projects) ?></div>
                    <div class="text-slate-400 extra-small">Monitored Developments</div>
                </div>
            </div>
        </div>

        <!-- Search & Filter Controls -->
        <div class="bc-surface-card p-3 mb-4">
            <form action="<?= BASE_URL ?>/client/payments.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <input type="text" name="q" value="<?= e($search) ?>" class="form-control form-control-sm" placeholder="Search project title, recipient, or payment method...">
                </div>

                <div class="col-md-4">
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="0">All My Projects</option>
                        <?php foreach ($client_projects as $cp): ?>
                            <option value="<?= $cp['id'] ?>" <?= $filter_project_id == $cp['id'] ? 'selected' : '' ?>>
                                <?= e($cp['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="all">All Statuses</option>
                        <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="failed" <?= $filter_status === 'failed' ? 'selected' : '' ?>>Failed</option>
                    </select>
                </div>

                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-outline-blue btn-sm fw-bold">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Payments Table -->
        <div class="bc-surface-card p-4">
            <?php if (empty($payments)): ?>
                <div class="text-center py-5 text-muted extra-small">
                    <i class="fa-solid fa-receipt fs-1 mb-3 text-warning"></i>
                    <h3 class="h6 text-dark fw-bold mb-1">No Payment Records Found</h3>
                    <p class="text-muted extra-small mb-3">No transactions match your search and filter criteria.</p>
                    <button type="button" class="btn btn-orange btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#newPaymentModal">
                        <i class="fa-solid fa-plus me-1"></i> Record First Payment
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 extra-small">
                        <thead>
                            <tr>
                                <th>Transaction Ref</th>
                                <th>Project Name</th>
                                <th>Recipient (Payee)</th>
                                <th>Amount (₹)</th>
                                <th>Payment Method</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $pay): ?>
                                <tr>
                                    <td>
                                        <strong class="font-monospace text-warning">#PAY-<?= str_pad($pay['id'], 6, '0', STR_PAD_LEFT) ?></strong>
                                        <?php if (!empty($pay['contract_number'])): ?>
                                            <div class="text-muted extra-small"><?= e($pay['contract_number']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-white"><?= e($pay['project_title']) ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-slate-200"><?= e($pay['payee_name'] ?? 'Contractor Firm') ?></span>
                                        <?php if (!empty($pay['payee_role'])): ?>
                                            <span class="badge bg-secondary bg-opacity-20 text-slate-300 ms-1 text-capitalize"><?= e($pay['payee_role']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong class="bc-table-amount"><?= format_currency($pay['amount']) ?></strong>
                                    </td>
                                    <td class="text-muted">
                                        <i class="fa-solid fa-credit-card me-1 text-info"></i><?= e($pay['payment_method']) ?>
                                    </td>
                                    <td class="text-muted">
                                        <?= format_datetime($pay['paid_at']) ?>
                                    </td>
                                    <td>
                                        <?php if ($pay['status'] === 'completed'): ?>
                                            <span class="badge badge-completed"><i class="fa-solid fa-check me-1"></i>Completed</span>
                                        <?php elseif ($pay['status'] === 'pending'): ?>
                                            <span class="badge badge-pending"><i class="fa-solid fa-clock me-1"></i>Pending</span>
                                        <?php else: ?>
                                            <span class="badge badge-rejected"><i class="fa-solid fa-xmark me-1"></i>Failed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center pt-3 border-top border-secondary mt-3">
                        <span class="text-muted extra-small">Showing Page <?= $page ?> of <?= $total_pages ?> (Total <?= $total_rows ?> Records)</span>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= BASE_URL ?>/client/payments.php?page=<?= $page - 1 ?>&project_id=<?= $filter_project_id ?>&status=<?= urlencode($filter_status) ?>&q=<?= urlencode($search) ?>">Prev</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= BASE_URL ?>/client/payments.php?page=<?= $i ?>&project_id=<?= $filter_project_id ?>&status=<?= urlencode($filter_status) ?>&q=<?= urlencode($search) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="<?= BASE_URL ?>/client/payments.php?page=<?= $page + 1 ?>&project_id=<?= $filter_project_id ?>&status=<?= urlencode($filter_status) ?>&q=<?= urlencode($search) ?>">Next</a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Record New Payment Modal -->
        <div class="modal fade" id="newPaymentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="modal-header border-0 bg-dark p-3 px-4">
                        <h5 class="modal-title fw-bold text-white fs-6 mb-0">
                            <i class="fa-solid fa-indian-rupee-sign text-warning me-2"></i>Record New Project Payment
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="<?= BASE_URL ?>/client/payments.php" method="POST" data-loading="true">
                        <?= csrf_field() ?>
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label extra-small fw-bold text-muted">SELECT PROJECT</label>
                                <select name="project_id" class="form-select rounded-3" required>
                                    <option value="">-- Choose Project --</option>
                                    <?php foreach ($client_projects as $cp): ?>
                                        <option value="<?= $cp['id'] ?>">
                                            <?= e($cp['title']) ?> (Contractor: <?= e($cp['contractor_company'] ?? $cp['contractor_name']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label extra-small fw-bold text-muted">DISBURSEMENT AMOUNT (₹)</label>
                                <input type="number" step="0.01" min="1" name="amount" class="form-control rounded-3" placeholder="e.g. 150000.00" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label extra-small fw-bold text-muted">PAYMENT METHOD</label>
                                <select name="payment_method" class="form-select rounded-3" required>
                                    <option value="Direct Transfer">Bank Direct Transfer (NEFT/RTGS)</option>
                                    <option value="UPI / Online">UPI / Online Transfer</option>
                                    <option value="Cheque">Cheque Payment</option>
                                    <option value="Escrow Account">Escrow Account Clearance</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer border-top bg-dark p-3 px-4">
                            <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-orange text-dark fw-bold px-4">Disburse Payment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
