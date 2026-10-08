<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(ROLE_CONTRACTOR);

$page_title = "Digital Contract Document - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = get_logged_user();
$db = getDB();

$contract_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $db->prepare("
    SELECT c.*, 
           p.title as project_title, p.location as project_location,
           u_w.name as worker_name, u_w.email as worker_email, u_w.phone as worker_phone,
           w.trade_title as worker_trade,
           c_co.company_name as contractor_company, c_co.license_no as contractor_license,
           j.title as job_title
    FROM contracts c
    JOIN projects p ON c.project_id = p.id
    JOIN users u_w ON c.worker_id = u_w.id
    LEFT JOIN workers w ON u_w.id = w.user_id
    LEFT JOIN contractors c_co ON c.contractor_id = c_co.user_id
    LEFT JOIN jobs j ON c.job_id = j.id
    WHERE c.id = ? AND c.contractor_id = ?
");
$stmt->execute([$contract_id, $user['id']]);
$contract = $stmt->fetch();

if (!$contract) {
    set_flash_message("Contract not found or access denied.", "danger");
    redirect("contractor/contracts.php");
}

$expired = is_contract_expired($contract);

// Handle POST actions: Send, Cancel, Mark Completed
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message("CSRF verification failed.", "danger");
        redirect("contractor/contract-details.php?id=" . $contract_id);
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'send' && $contract['status'] === 'draft') {
        $db->beginTransaction();
        try {
            $e_sig = $user['name'] . ' (E-Signed)';
            $up = $db->prepare("
                UPDATE contracts SET status = 'pending', contractor_signature = ?, contractor_signed_at = NOW(), updated_at = NOW()
                WHERE id = ? AND contractor_id = ?
            ");
            $up->execute([$e_sig, $contract_id, $user['id']]);

            // Notify Worker
            $db->prepare("
                INSERT INTO notifications (user_id, title, message, type, link, created_at)
                VALUES (?, ?, ?, 'info', ?, NOW())
            ")->execute([
                $contract['worker_id'],
                "Digital Contract Received",
                "Contractor {$user['name']} issued digital contract {$contract['contract_number']}.",
                "worker/contract-details.php?id=" . $contract_id
            ]);

            log_activity($user['id'], "Contract Sent", "Sent contract {$contract['contract_number']} to worker ID {$contract['worker_id']}", "contract", $contract_id);
            $db->commit();
            set_flash_message("Contract successfully sent to worker for signature.", "success");
        } catch (Exception $e) {
            $db->rollBack();
            set_flash_message("Failed to send contract: " . $e->getMessage(), "danger");
        }
        redirect("contractor/contract-details.php?id=" . $contract_id);
    }

    if ($action === 'cancel' && in_array($contract['status'], ['draft', 'pending', 'active'])) {
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE contracts SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND contractor_id = ?")
               ->execute([$contract_id, $user['id']]);

            $db->prepare("
                INSERT INTO notifications (user_id, title, message, type, link, created_at)
                VALUES (?, ?, ?, 'warning', ?, NOW())
            ")->execute([
                $contract['worker_id'],
                "Digital Contract Cancelled",
                "Contract {$contract['contract_number']} has been cancelled by contractor {$user['name']}.",
                "worker/contract-details.php?id=" . $contract_id
            ]);

            log_activity($user['id'], "Contract Cancelled", "Cancelled contract {$contract['contract_number']}", "contract", $contract_id);
            $db->commit();
            set_flash_message("Contract successfully cancelled.", "info");
        } catch (Exception $e) {
            $db->rollBack();
            set_flash_message("Failed to cancel contract: " . $e->getMessage(), "danger");
        }
        redirect("contractor/contract-details.php?id=" . $contract_id);
    }

    if ($action === 'complete' && $contract['status'] === 'active') {
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE contracts SET status = 'completed', updated_at = NOW() WHERE id = ? AND contractor_id = ?")
               ->execute([$contract_id, $user['id']]);

            $db->prepare("
                INSERT INTO notifications (user_id, title, message, type, link, created_at)
                VALUES (?, ?, ?, 'success', ?, NOW())
            ")->execute([
                $contract['worker_id'],
                "Digital Contract Work Completed",
                "Work on contract {$contract['contract_number']} has been marked as completed! You can now submit a review.",
                "worker/reviews.php"
            ]);

            log_activity($user['id'], "Contract Completed", "Marked contract {$contract['contract_number']} as completed", "contract", $contract_id);
            $db->commit();
            set_flash_message("Contract marked as Completed! You can now submit a worker review.", "success");
        } catch (Exception $e) {
            $db->rollBack();
            set_flash_message("Failed to complete contract: " . $e->getMessage(), "danger");
        }
        redirect("contractor/contract-details.php?id=" . $contract_id);
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
                <a href="<?= BASE_URL ?>/contractor/contracts.php" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Contracts
                </a>
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-file-contract text-warning me-2"></i>Digital Work Contract Document</h1>
                <p class="text-muted small mb-0">Ref: <?= sanitize($contract['contract_number']) ?></p>
            </div>
            
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-outline-light btn-sm">
                    <i class="fa-solid fa-print me-1"></i> Print / Download PDF
                </button>

                <?php if ($contract['status'] === 'draft'): ?>
                    <a href="<?= BASE_URL ?>/contractor/edit-contract.php?id=<?= $contract_id ?>" class="btn btn-outline-warning btn-sm">
                        <i class="fa-solid fa-pen-to-square me-1"></i> Edit Draft
                    </a>
                    <form action="" method="POST" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="send">
                        <button type="submit" class="btn btn-amber btn-sm fw-bold">
                            <i class="fa-solid fa-paper-plane me-1"></i> Send to Worker
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($contract['status'] === 'active'): ?>
                    <form action="" method="POST" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="complete">
                        <button type="submit" class="btn btn-success btn-sm fw-bold" onclick="return confirm('Mark this contract as completed?');">
                            <i class="fa-solid fa-circle-check me-1"></i> Mark Completed
                        </button>
                    </form>
                    <a href="<?= BASE_URL ?>/contractor/reviews.php?worker_id=<?= $contract['worker_id'] ?>&project_id=<?= $contract['project_id'] ?>&contract_id=<?= $contract['id'] ?>" class="btn btn-amber btn-sm fw-bold">
                        <i class="fa-solid fa-star me-1"></i> Rate Worker
                    </a>
                <?php endif; ?>

                <?php if (in_array($contract['status'], ['pending', 'active'])): ?>
                    <form action="" method="POST" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="cancel">
                        <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to cancel this contract?');">
                            <i class="fa-solid fa-ban me-1"></i> Cancel Contract
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($flash = get_flash_message()): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> py-2 px-3 small mb-4 no-print">
                <i class="fa-solid fa-circle-info me-1"></i> <?= sanitize($flash['message']) ?>
            </div>
        <?php endif; ?>

        <!-- Digital Contract Printable Sheet -->
        <div class="printable-contract bc-card p-5">
            <div class="d-flex justify-content-between align-items-start border-bottom border-secondary pb-4 mb-4">
                <div>
                    <div class="text-warning fw-bold fs-4 tracking-wider"><i class="fa-solid fa-hard-hat me-2"></i>BUILD CONNECT</div>
                    <h2 class="h3 fw-bold text-white mb-0 mt-1">DIGITAL WORK CONTRACT</h2>
                    <div class="text-muted extra-small">Legally Binding Electronic Agreement</div>
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
                        <div class="fw-bold text-white fs-6"><?= sanitize($contract['contractor_company'] ?: $user['name']) ?></div>
                        <div class="text-muted small">Represented by: <?= sanitize($user['name']) ?></div>
                        <?php if ($contract['contractor_license']): ?>
                            <div class="text-muted extra-small">License No: <?= sanitize($contract['contractor_license']) ?></div>
                        <?php endif; ?>
                        <div class="text-muted extra-small">Contact: <?= sanitize($user['email']) ?> | <?= sanitize($user['phone']) ?></div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="bg-dark p-3 rounded-3 border border-secondary h-100">
                        <h6 class="text-success extra-small fw-bold text-uppercase tracking-wider mb-2"><i class="fa-solid fa-user me-1"></i> Worker / Employee</h6>
                        <div class="fw-bold text-white fs-6"><?= sanitize($contract['worker_name']) ?></div>
                        <div class="text-muted small">Trade: <?= sanitize($contract['worker_trade'] ?? 'General Specialist') ?></div>
                        <div class="text-muted extra-small">Email: <?= sanitize($contract['worker_email']) ?></div>
                        <div class="text-muted extra-small">Phone: <?= sanitize($contract['worker_phone'] ?? 'N/A') ?></div>
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
                            <span class="text-muted extra-small">Agreed Payment:</span>
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

            <!-- Title & Description -->
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

            <!-- E-Signatures & Timestamps -->
            <div class="pt-4 border-top border-secondary">
                <h6 class="text-uppercase text-muted extra-small fw-bold tracking-wider mb-3"><i class="fa-solid fa-signature me-1"></i> Digital Signature & Acceptance Info</h6>
                
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="p-3 bg-dark rounded-3 border border-secondary">
                            <div class="text-muted extra-small fw-bold mb-1">CONTRACTOR E-SIGNATURE</div>
                            <?php if ($contract['contractor_signature']): ?>
                                <div class="text-success fw-bold font-monospace fs-6 mb-1">
                                    <i class="fa-solid fa-check-circle me-1"></i><?= sanitize($contract['contractor_signature']) ?>
                                </div>
                                <div class="text-muted extra-small">Signed At: <?= format_datetime($contract['contractor_signed_at']) ?></div>
                            <?php else: ?>
                                <div class="text-warning extra-small"><i class="fa-solid fa-clock me-1"></i>Draft - Unsigned</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-3 bg-dark rounded-3 border border-secondary">
                            <div class="text-muted extra-small fw-bold mb-1">WORKER E-SIGNATURE</div>
                            <?php if ($contract['worker_signature']): ?>
                                <div class="text-success fw-bold font-monospace fs-6 mb-1">
                                    <i class="fa-solid fa-check-circle me-1"></i><?= sanitize($contract['worker_signature']) ?>
                                </div>
                                <div class="text-muted extra-small">Signed At: <?= format_datetime($contract['worker_signed_at']) ?></div>
                            <?php elseif ($contract['status'] === 'rejected'): ?>
                                <div class="text-danger fw-bold extra-small mb-1"><i class="fa-solid fa-times-circle me-1"></i>Rejected by Worker</div>
                                <?php if ($contract['rejection_reason']): ?>
                                    <div class="text-muted extra-small">Reason: <?= sanitize($contract['rejection_reason']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="text-warning extra-small"><i class="fa-solid fa-clock me-1"></i>Awaiting Worker Signature</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
