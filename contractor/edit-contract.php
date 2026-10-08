<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(ROLE_CONTRACTOR);

$page_title = "Edit Digital Contract - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = get_logged_user();
$db = getDB();

$contract_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $db->prepare("
    SELECT c.*, p.title as project_title, u.name as worker_name, u.email as worker_email
    FROM contracts c
    JOIN projects p ON c.project_id = p.id
    JOIN users u ON c.worker_id = u.id
    WHERE c.id = ? AND c.contractor_id = ?
");
$stmt->execute([$contract_id, $user['id']]);
$contract = $stmt->fetch();

if (!$contract) {
    set_flash_message("Contract not found or access denied.", "danger");
    redirect("contractor/contracts.php");
}

$is_editable = ($contract['status'] === 'draft');

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_editable) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "CSRF verification failed.";
    } else {
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $payment_type = sanitize($_POST['payment_type'] ?? 'hourly');
        $payment_amount = (float)($_POST['payment_amount'] ?? 0);
        $working_hours = sanitize($_POST['working_hours'] ?? '');
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $terms = sanitize($_POST['terms'] ?? '');
        $action = $_POST['action_type'] ?? 'draft';

        if (empty($title)) $errors[] = "Title cannot be empty.";
        if ($payment_amount <= 0) $errors[] = "Payment amount must be greater than ₹0.00.";
        if (empty($terms)) $errors[] = "Terms and conditions cannot be empty.";

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                $status = ($action === 'send') ? 'pending' : 'draft';
                $contractor_signature = ($action === 'send') ? ($user['name'] . ' (E-Signed)') : $contract['contractor_signature'];
                $contractor_signed_at = ($action === 'send') ? date('Y-m-d H:i:s') : $contract['contractor_signed_at'];

                $up_stmt = $db->prepare("
                    UPDATE contracts SET
                        title = ?,
                        description = ?,
                        terms = ?,
                        payment_type = ?,
                        payment_amount = ?,
                        pay_amount = ?,
                        working_hours = ?,
                        start_date = ?,
                        end_date = ?,
                        contractor_signature = ?,
                        contractor_signed_at = ?,
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ? AND contractor_id = ?
                ");
                $up_stmt->execute([
                    $title,
                    $description,
                    $terms,
                    $payment_type,
                    $payment_amount,
                    $payment_amount,
                    $working_hours,
                    $start_date,
                    $end_date,
                    $contractor_signature,
                    $contractor_signed_at,
                    $status,
                    $contract_id,
                    $user['id']
                ]);

                if ($status === 'pending') {
                    // Send worker notification
                    $notif_stmt = $db->prepare("
                        INSERT INTO notifications (user_id, title, message, type, link, created_at)
                        VALUES (?, ?, ?, 'info', ?, NOW())
                    ");
                    $notif_stmt->execute([
                        $contract['worker_id'],
                        "Digital Contract Sent for Signature",
                        "Contractor {$user['name']} sent digital contract {$contract['contract_number']} ({$title}).",
                        "worker/contract-details.php?id=" . $contract_id
                    ]);

                    log_activity($user['id'], "Contract Sent", "Issued contract {$contract['contract_number']} to worker ID {$contract['worker_id']}", "contract", $contract_id);
                } else {
                    log_activity($user['id'], "Contract Updated", "Updated contract draft {$contract['contract_number']}", "contract", $contract_id);
                }

                $db->commit();

                set_flash_message("Contract {$contract['contract_number']} successfully updated!", "success");
                redirect("contractor/contract-details.php?id=" . $contract_id);

            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = "Failed to update contract: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="<?= BASE_URL ?>/contractor/contract-details.php?id=<?= $contract_id ?>" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Contract Details
                </a>
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-pen-to-square text-warning me-2"></i>Edit Contract: <?= sanitize($contract['contract_number']) ?></h1>
                <p class="text-muted small mb-0">Modify contract terms before issuing to worker.</p>
            </div>
        </div>

        <?php if (!$is_editable): ?>
            <div class="alert alert-warning py-3 px-4 mb-4 fw-bold">
                <i class="fa-solid fa-lock me-2 fs-5"></i> Active contracts cannot be edited.
                <span class="fw-normal block extra-small text-muted mt-1">Once a contract has been sent, signed, or activated, legal terms are locked to protect both parties.</span>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger py-2 px-3 small mb-4">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= sanitize($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>/contractor/edit-contract.php?id=<?= $contract_id ?>" method="POST">
            <?= csrf_field() ?>
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="bc-card p-4 mb-4">
                        <h5 class="fw-bold text-white mb-3"><i class="fa-solid fa-file-contract text-warning me-2"></i>Contract Summary</h5>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-semibold">Project</label>
                                <input type="text" class="form-control bg-dark text-muted border-secondary" readonly value="<?= sanitize($contract['project_title']) ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-semibold">Worker</label>
                                <input type="text" class="form-control bg-dark text-muted border-secondary" readonly value="<?= sanitize($contract['worker_name']) ?> (<?= sanitize($contract['worker_email']) ?>)">
                            </div>

                            <div class="col-12">
                                <label class="form-label text-muted small fw-semibold">Contract Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" name="title" required value="<?= sanitize($_POST['title'] ?? $contract['title']) ?>" <?= !$is_editable ? 'readonly' : '' ?>>
                            </div>

                            <div class="col-12">
                                <label class="form-label text-muted small fw-semibold">Description</label>
                                <textarea class="form-control bg-dark text-light border-secondary" name="description" rows="2" <?= !$is_editable ? 'readonly' : '' ?>><?= sanitize($_POST['description'] ?? $contract['description']) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="bc-card p-4 mb-4">
                        <h5 class="fw-bold text-white mb-3"><i class="fa-solid fa-coins text-warning me-2"></i>Compensation & Schedule</h5>
                        
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label text-muted small fw-semibold">Payment Type</label>
                                <select class="form-select bg-dark text-light border-secondary" name="payment_type" <?= !$is_editable ? 'disabled' : '' ?>>
                                    <option value="hourly" <?= ($contract['payment_type'] === 'hourly') ? 'selected' : '' ?>>Hourly Rate (₹/hr)</option>
                                    <option value="daily" <?= ($contract['payment_type'] === 'daily') ? 'selected' : '' ?>>Daily Rate (₹/day)</option>
                                    <option value="fixed" <?= ($contract['payment_type'] === 'fixed') ? 'selected' : '' ?>>Fixed Rate (₹)</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label text-muted small fw-semibold">Agreed Amount (₹)</label>
                                <input type="number" step="0.01" min="1" class="form-control bg-dark text-light border-secondary" name="payment_amount" required value="<?= sanitize($_POST['payment_amount'] ?? ($contract['payment_amount'] ?: $contract['pay_amount'])) ?>" <?= !$is_editable ? 'readonly' : '' ?>>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label text-muted small fw-semibold">Working Hours</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" name="working_hours" value="<?= sanitize($_POST['working_hours'] ?? $contract['working_hours']) ?>" <?= !$is_editable ? 'readonly' : '' ?>>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-semibold">Start Date</label>
                                <input type="date" class="form-control bg-dark text-light border-secondary" name="start_date" value="<?= sanitize($_POST['start_date'] ?? $contract['start_date']) ?>" <?= !$is_editable ? 'readonly' : '' ?>>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-semibold">End Date</label>
                                <input type="date" class="form-control bg-dark text-light border-secondary" name="end_date" value="<?= sanitize($_POST['end_date'] ?? $contract['end_date']) ?>" <?= !$is_editable ? 'readonly' : '' ?>>
                            </div>
                        </div>
                    </div>

                    <div class="bc-card p-4">
                        <h5 class="fw-bold text-white mb-3"><i class="fa-solid fa-scroll text-warning me-2"></i>Terms & Legal Conditions</h5>
                        <div class="mb-3">
                            <textarea class="form-control bg-dark text-light border-secondary font-monospace extra-small" name="terms" rows="8" required <?= !$is_editable ? 'readonly' : '' ?>><?= sanitize($_POST['terms'] ?? $contract['terms']) ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="bc-card p-4 sticky-top" style="top: 100px;">
                        <h5 class="fw-bold text-white mb-3"><i class="fa-solid fa-paper-plane text-warning me-2"></i>Actions</h5>

                        <?php if ($is_editable): ?>
                            <div class="d-grid gap-2 mt-3">
                                <button type="submit" name="action_type" value="send" class="btn btn-amber fw-bold py-2">
                                    <i class="fa-solid fa-paper-plane me-1"></i> Save & Send to Worker
                                </button>
                                <button type="submit" name="action_type" value="draft" class="btn btn-outline-secondary text-white py-2">
                                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes
                                </button>
                            </div>
                        <?php else: ?>
                            <p class="text-muted extra-small">Terms are locked because this contract status is <strong><?= sanitize($contract['status']) ?></strong>.</p>
                            <a href="<?= BASE_URL ?>/contractor/contract-details.php?id=<?= $contract_id ?>" class="btn btn-outline-light btn-sm w-100">
                                View Contract Details
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
