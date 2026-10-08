<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(ROLE_WORKER);

$page_title = "Digital Contract Details - Worker - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = get_logged_user();
$db = getDB();

$contract_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $db->prepare("
    SELECT c.*, 
           p.title as project_title, p.location as project_location,
           u_c.name as contractor_name, u_c.email as contractor_email, u_c.phone as contractor_phone,
           c_co.company_name as contractor_company, c_co.license_no as contractor_license,
           j.title as job_title
    FROM contracts c
    JOIN projects p ON c.project_id = p.id
    JOIN users u_c ON c.contractor_id = u_c.id
    LEFT JOIN contractors c_co ON c.contractor_id = c_co.user_id
    LEFT JOIN jobs j ON c.job_id = j.id
    WHERE c.id = ? AND c.worker_id = ?
");
$stmt->execute([$contract_id, $user['id']]);
$contract = $stmt->fetch();

if (!$contract) {
    set_flash_message("Contract not found or access denied.", "danger");
    redirect("worker/contracts.php");
}

$expired = is_contract_expired($contract);

$errors = [];

// Handle Contract Acceptance or Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "CSRF token verification failed.";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'accept') {
            // Verify status is pending
            if ($contract['status'] !== 'pending') {
                $errors[] = "Only pending contracts can be accepted.";
            } elseif ($expired) {
                $errors[] = "This contract has expired and cannot be accepted.";
            } else {
                // Verify worker is active project member
                $m_stmt = $db->prepare("
                    SELECT id FROM project_members 
                    WHERE project_id = ? AND user_id = ? AND status = 'active'
                ");
                $m_stmt->execute([$contract['project_id'], $user['id']]);
                if (!$m_stmt->fetch()) {
                    $errors[] = "You must be an active project member on this project to accept the contract.";
                }
            }

            if (empty($errors)) {
                try {
                    $db->beginTransaction();

                    $worker_sig = $user['name'] . ' (E-Signed)';
                    $up_stmt = $db->prepare("
                        UPDATE contracts 
                        SET status = 'active', 
                            worker_signature = ?, 
                            worker_signed_at = NOW(), 
                            signed_at = NOW(),
                            updated_at = NOW()
                        WHERE id = ? AND worker_id = ? AND status = 'pending'
                    ");
                    $up_stmt->execute([$worker_sig, $contract_id, $user['id']]);

                    // Send contractor notification
                    $notif = $db->prepare("
                        INSERT INTO notifications (user_id, title, message, type, link, created_at)
                        VALUES (?, ?, ?, 'success', ?, NOW())
                    ");
                    $notif->execute([
                        $contract['contractor_id'],
                        "Digital Contract Accepted!",
                        "Worker {$user['name']} accepted and signed digital contract {$contract['contract_number']}.",
                        "contractor/contract-details.php?id=" . $contract_id
                    ]);

                    log_activity($user['id'], "Contract Accepted", "Accepted and signed contract {$contract['contract_number']}", "contract", $contract_id);

                    $db->commit();

                    set_flash_message("Digital Contract {$contract['contract_number']} successfully accepted and signed!", "success");
                    redirect("worker/contract-details.php?id=" . $contract_id);

                } catch (Exception $e) {
                    $db->rollBack();
                    $errors[] = "Failed to accept contract: " . $e->getMessage();
                }
            }
        } elseif ($action === 'reject') {
            $reason = sanitize($_POST['rejection_reason'] ?? '');
            if (empty($reason)) {
                $errors[] = "Please provide a reason for rejecting this contract.";
            } elseif ($contract['status'] !== 'pending') {
                $errors[] = "Only pending contracts can be rejected.";
            }

            if (empty($errors)) {
                try {
                    $db->beginTransaction();

                    $up_stmt = $db->prepare("
                        UPDATE contracts 
                        SET status = 'rejected', 
                            rejection_reason = ?, 
                            updated_at = NOW()
                        WHERE id = ? AND worker_id = ? AND status = 'pending'
                    ");
                    $up_stmt->execute([$reason, $contract_id, $user['id']]);

                    // Send contractor notification
                    $notif = $db->prepare("
                        INSERT INTO notifications (user_id, title, message, type, link, created_at)
                        VALUES (?, ?, ?, 'warning', ?, NOW())
                    ");
                    $notif->execute([
                        $contract['contractor_id'],
                        "Digital Contract Rejected",
                        "Worker {$user['name']} rejected contract {$contract['contract_number']}. Reason: {$reason}",
                        "contractor/contract-details.php?id=" . $contract_id
                    ]);

                    log_activity($user['id'], "Contract Rejected", "Rejected contract {$contract['contract_number']}. Reason: {$reason}", "contract", $contract_id);

                    $db->commit();

                    set_flash_message("Contract rejected. Contractor has been notified.", "info");
                    redirect("worker/contract-details.php?id=" . $contract_id);

                } catch (Exception $e) {
                    $db->rollBack();
                    $errors[] = "Failed to reject contract: " . $e->getMessage();
                }
            }
        }
    }
}
?>

<style>
@media print {
    body {
        background-color: #ffffff !important;
        color: #000000 !important;
    }
    .bc-sidebar, .bc-navbar, .no-print, .btn, footer {
        display: none !important;
    }
    .bc-main-content {
        margin: 0 !important;
        padding: 0 !important;
    }
    .printable-contract {
        background: #ffffff !important;
        color: #000000 !important;
        border: 2px solid #000000 !important;
        padding: 30px !important;
        box-shadow: none !important;
    }
    .printable-contract h1, .printable-contract h2, .printable-contract h3, .printable-contract strong {
        color: #000000 !important;
    }
    .printable-contract .border-secondary {
        border-color: #cccccc !important;
    }
    .printable-contract .bg-dark {
        background-color: #f8f9fa !important;
        color: #000000 !important;
    }
}
</style>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <div>
                <a href="<?= BASE_URL ?>/worker/contracts.php" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Contracts
                </a>
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-file-contract text-success me-2"></i>Digital Contract Document</h1>
                <p class="text-muted small mb-0">Ref: <?= sanitize($contract['contract_number']) ?></p>
            </div>
            
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-outline-light btn-sm">
                    <i class="fa-solid fa-print me-1"></i> Print / Save PDF
                </button>

                <?php if ($contract['status'] === 'pending' && !$expired): ?>
                    <button class="btn btn-amber btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#acceptModal">
                        <i class="fa-solid fa-signature me-1"></i> Accept & E-Sign
                    </button>
                    <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
                        <i class="fa-solid fa-xmark me-1"></i> Reject
                    </button>
                <?php endif; ?>

                <?php if ($contract['status'] === 'completed' || $contract['status'] === 'active'): ?>
                    <a href="<?= BASE_URL ?>/worker/reviews.php" class="btn btn-amber btn-sm fw-bold">
                        <i class="fa-solid fa-star me-1"></i> Rate Contractor
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($flash = get_flash_message()): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> py-2 px-3 small mb-4 no-print">
                <i class="fa-solid fa-circle-info me-1"></i> <?= sanitize($flash['message']) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger py-2 px-3 small mb-4 no-print">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= sanitize($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Digital Contract Sheet -->
        <div class="printable-contract bc-card p-5">
            <div class="d-flex justify-content-between align-items-start border-bottom border-secondary pb-4 mb-4">
                <div>
                    <div class="text-warning fw-bold fs-4 tracking-wider"><i class="fa-solid fa-hard-hat me-2"></i>BUILD CONNECT</div>
                    <h2 class="h3 fw-bold text-white mb-0 mt-1">DIGITAL WORK CONTRACT</h2>
                    <div class="text-muted extra-small">Legally Binding Employment Agreement</div>
                </div>
                <div class="text-end">
                    <div class="fs-5 fw-bold text-warning font-monospace"><?= sanitize($contract['contract_number']) ?></div>
                    <span class="badge <?= get_status_badge_class($expired ? 'expired' : $contract['status']) ?> text-uppercase mt-1">
                        <?= sanitize($expired ? 'expired' : $contract['status']) ?>
                    </span>
                    <div class="text-muted extra-small mt-1">Issued: <?= format_date($contract['created_at']) ?></div>
                </div>
            </div>

            <!-- Party Details Row -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="bg-dark p-3 rounded-3 border border-secondary h-100">
                        <h6 class="text-warning extra-small fw-bold text-uppercase tracking-wider mb-2"><i class="fa-solid fa-building me-1"></i> Contractor / Employer</h6>
                        <div class="fw-bold text-white fs-6"><?= sanitize($contract['contractor_company'] ?: $contract['contractor_name']) ?></div>
                        <div class="text-muted small">Manager: <?= sanitize($contract['contractor_name']) ?></div>
                        <?php if ($contract['contractor_license']): ?>
                            <div class="text-muted extra-small">License No: <?= sanitize($contract['contractor_license']) ?></div>
                        <?php endif; ?>
                        <div class="text-muted extra-small">Contact: <?= sanitize($contract['contractor_email']) ?></div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="bg-dark p-3 rounded-3 border border-secondary h-100">
                        <h6 class="text-success extra-small fw-bold text-uppercase tracking-wider mb-2"><i class="fa-solid fa-user me-1"></i> Worker / Employee</h6>
                        <div class="fw-bold text-white fs-6"><?= sanitize($user['name']) ?></div>
                        <div class="text-muted extra-small">Email: <?= sanitize($user['email']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Project & Compensation Details -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="bg-dark p-3 rounded-3 border border-secondary">
                        <h6 class="text-info extra-small fw-bold text-uppercase tracking-wider mb-2"><i class="fa-solid fa-map-location-dot me-1"></i> Project & Role</h6>
                        <div class="fw-bold text-white"><?= sanitize($contract['project_title']) ?></div>
                        <div class="text-muted extra-small mb-1"><i class="fa-solid fa-location-dot me-1"></i><?= sanitize($contract['project_location']) ?></div>
                        <?php if ($contract['job_title']): ?>
                            <div class="text-light extra-small">Job Title: <strong><?= sanitize($contract['job_title']) ?></strong></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="bg-dark p-3 rounded-3 border border-secondary">
                        <h6 class="text-warning extra-small fw-bold text-uppercase tracking-wider mb-2"><i class="fa-solid fa-money-bill-wave me-1"></i> Compensation & Schedule</h6>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted extra-small">Agreed Compensation:</span>
                            <span class="fw-bold text-warning fs-5"><?= format_currency($contract['payment_amount'] ?: $contract['pay_amount']) ?> / <?= sanitize($contract['payment_type']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted extra-small">Working Hours:</span>
                            <span class="text-light extra-small"><?= sanitize($contract['working_hours']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted extra-small">Contract Period:</span>
                            <span class="text-light extra-small"><?= format_date($contract['start_date']) ?> → <?= format_date($contract['end_date']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Title & Scope -->
            <div class="mb-4">
                <h5 class="fw-bold text-white mb-2"><?= sanitize($contract['title']) ?></h5>
                <?php if ($contract['description']): ?>
                    <p class="text-light small opacity-90"><?= sanitize($contract['description']) ?></p>
                <?php endif; ?>
            </div>

            <!-- Terms and Conditions -->
            <div class="mb-4">
                <h6 class="text-uppercase text-muted extra-small fw-bold tracking-wider mb-2"><i class="fa-solid fa-gavel me-1"></i> Terms and Conditions</h6>
                <div class="bg-dark p-3 rounded-3 border border-secondary text-light extra-small font-monospace whitespace-pre-line" style="white-space: pre-wrap; line-height: 1.6;">
                    <?= sanitize($contract['terms']) ?>
                </div>
            </div>

            <!-- Signatures Section -->
            <div class="pt-4 border-top border-secondary">
                <h6 class="text-uppercase text-muted extra-small fw-bold tracking-wider mb-3"><i class="fa-solid fa-signature me-1"></i> Acceptance & E-Signature Status</h6>
                
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="p-3 bg-dark rounded-3 border border-secondary">
                            <div class="text-muted extra-small fw-bold mb-1">CONTRACTOR SIGNATURE</div>
                            <?php if ($contract['contractor_signature']): ?>
                                <div class="text-success fw-bold font-monospace fs-6 mb-1">
                                    <i class="fa-solid fa-check-circle me-1"></i><?= sanitize($contract['contractor_signature']) ?>
                                </div>
                                <div class="text-muted extra-small">Confirmed At: <?= format_datetime($contract['contractor_signed_at']) ?></div>
                            <?php else: ?>
                                <div class="text-warning extra-small"><i class="fa-solid fa-clock me-1"></i>Pending Contractor Confirmation</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-3 bg-dark rounded-3 border border-secondary">
                            <div class="text-muted extra-small fw-bold mb-1">WORKER SIGNATURE</div>
                            <?php if ($contract['worker_signature']): ?>
                                <div class="text-success fw-bold font-monospace fs-6 mb-1">
                                    <i class="fa-solid fa-check-circle me-1"></i><?= sanitize($contract['worker_signature']) ?>
                                </div>
                                <div class="text-muted extra-small">Digitally Accepted At: <?= format_datetime($contract['worker_signed_at']) ?></div>
                            <?php elseif ($contract['status'] === 'rejected'): ?>
                                <div class="text-danger fw-bold extra-small mb-1"><i class="fa-solid fa-times-circle me-1"></i>Rejected by You</div>
                                <?php if ($contract['rejection_reason']): ?>
                                    <div class="text-muted extra-small">Reason: <?= sanitize($contract['rejection_reason']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="text-warning extra-small mb-2"><i class="fa-solid fa-clock me-1"></i>Action Required: Pending Your Signature</div>
                                <button class="btn btn-amber btn-sm fw-bold no-print" data-bs-toggle="modal" data-bs-target="#acceptModal">
                                    <i class="fa-solid fa-signature me-1"></i> Accept & Sign Contract
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Accept Contract Modal -->
<div class="modal fade" id="acceptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-signature me-2 text-warning"></i>Digital Contract Acceptance</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>/worker/contract-details.php?id=<?= $contract_id ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="accept">
                <div class="modal-body">
                    <p class="text-muted small mb-3">By clicking <strong>Accept & E-Sign</strong>, you acknowledge and agree to all terms, compensation rates, and working hours specified in contract <strong><?= sanitize($contract['contract_number']) ?></strong>.</p>
                    
                    <div class="bg-secondary p-3 rounded-3 text-center mb-3">
                        <span class="text-white small fw-bold">Electronic Signature:</span>
                        <div class="fs-5 font-monospace text-warning mt-1"><?= sanitize($user['name']) ?> (E-Signed)</div>
                        <div class="extra-small text-muted">Timestamp: <?= date('M d, Y h:i A') ?></div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-amber btn-sm fw-bold">
                        <i class="fa-solid fa-check-circle me-1"></i> Accept & E-Sign Contract
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Contract Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-xmark me-2 text-danger"></i>Reject Contract</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>/worker/contract-details.php?id=<?= $contract_id ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reject">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control bg-dark text-light border-secondary" name="rejection_reason" rows="3" required placeholder="Explain why you are declining this contract terms..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm fw-bold">
                        Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
