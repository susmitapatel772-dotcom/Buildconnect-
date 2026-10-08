<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(ROLE_CONTRACTOR);

$page_title = "Create Digital Contract - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = get_logged_user();
$db = getDB();

$errors = [];
$success = '';

// Selected project from query string
$selected_project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$selected_worker_id = isset($_GET['worker_id']) ? (int)$_GET['worker_id'] : 0;
$selected_job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

// Get contractor's projects
$stmt = $db->prepare("SELECT id, title FROM projects WHERE contractor_id = ? ORDER BY title ASC");
$stmt->execute([$user['id']]);
$projects = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = "CSRF verification failed. Please try again.";
    } else {
        $project_id = (int)($_POST['project_id'] ?? 0);
        $worker_id = (int)($_POST['worker_id'] ?? 0);
        $job_id = !empty($_POST['job_id']) ? (int)$_POST['job_id'] : null;
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $payment_type = sanitize($_POST['payment_type'] ?? 'hourly');
        $payment_amount = (float)($_POST['payment_amount'] ?? 0);
        $working_hours = sanitize($_POST['working_hours'] ?? '8 hours/day, Mon-Fri');
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $terms = sanitize($_POST['terms'] ?? '');
        $action = $_POST['action_type'] ?? 'draft'; // 'draft' or 'send'

        // Server-side validations
        if (empty($project_id)) {
            $errors[] = "Please select a valid project.";
        } else {
            // Verify contractor owns project
            $p_stmt = $db->prepare("SELECT id, title FROM projects WHERE id = ? AND contractor_id = ?");
            $p_stmt->execute([$project_id, $user['id']]);
            $project_data = $p_stmt->fetch();
            if (!$project_data) {
                $errors[] = "Unauthorized project selection.";
            }
        }

        if (empty($worker_id)) {
            $errors[] = "Please select a worker for this contract.";
        } else {
            // Verify worker is active project member or hired applicant
            $m_stmt = $db->prepare("
                SELECT pm.id FROM project_members pm
                WHERE pm.project_id = ? AND pm.user_id = ? AND pm.status = 'active'
            ");
            $m_stmt->execute([$project_id, $worker_id]);
            if (!$m_stmt->fetch()) {
                // Check if worker accepted job application
                $h_stmt = $db->prepare("
                    SELECT ja.id FROM job_applications ja
                    JOIN jobs j ON ja.job_id = j.id
                    WHERE j.project_id = ? AND ja.worker_id = ? AND ja.status = 'accepted'
                ");
                $h_stmt->execute([$project_id, $worker_id]);
                if (!$h_stmt->fetch()) {
                    $errors[] = "Worker must be an active project member or hired worker on this project.";
                }
            }
        }

        if (empty($title)) {
            $errors[] = "Contract title is required.";
        }

        if ($payment_amount <= 0) {
            $errors[] = "Payment amount must be greater than ₹0.00.";
        }

        if (!in_array($payment_type, ['hourly', 'daily', 'fixed'])) {
            $payment_type = 'hourly';
        }

        if (empty($terms)) {
            $errors[] = "Contract terms & conditions cannot be empty.";
        }

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                $contract_number = generate_contract_number($db);
                $status = ($action === 'send') ? 'pending' : 'draft';
                $contractor_signature = ($action === 'send') ? ($user['name'] . ' (E-Signed)') : null;
                $contractor_signed_at = ($action === 'send') ? date('Y-m-d H:i:s') : null;

                $ins_stmt = $db->prepare("
                    INSERT INTO contracts (
                        project_id, job_id, contractor_id, worker_id, contract_number,
                        title, description, terms, payment_type, payment_amount, pay_amount,
                        working_hours, start_date, end_date, contractor_signature, contractor_signed_at,
                        status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $ins_stmt->execute([
                    $project_id,
                    $job_id,
                    $user['id'],
                    $worker_id,
                    $contract_number,
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
                    $status
                ]);

                $contract_id = $db->lastInsertId();

                if ($status === 'pending') {
                    // Send notification to worker
                    $notif_stmt = $db->prepare("
                        INSERT INTO notifications (user_id, title, message, type, link, created_at)
                        VALUES (?, ?, ?, 'info', ?, NOW())
                    ");
                    $notif_stmt->execute([
                        $worker_id,
                        "New Digital Contract Received",
                        "Contractor {$user['name']} issued contract {$contract_number} ({$title}) for project {$project_data['title']}.",
                        "worker/contract-details.php?id=" . $contract_id
                    ]);

                    log_activity($user['id'], "Contract Sent", "Sent contract {$contract_number} to worker ID {$worker_id}", "contract", $contract_id);
                } else {
                    log_activity($user['id'], "Contract Draft Created", "Created contract draft {$contract_number}", "contract", $contract_id);
                }

                $db->commit();

                set_flash_message("Digital Contract {$contract_number} successfully created!", "success");
                redirect("contractor/contract-details.php?id=" . $contract_id);

            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = "Failed to create contract: " . $e->getMessage();
            }
        }
    }
}

// Fetch active project members for selected project if available
$project_members = [];
if ($selected_project_id > 0) {
    $m_stmt = $db->prepare("
        SELECT u.id, u.name, u.email, w.trade_title
        FROM project_members pm
        JOIN users u ON pm.user_id = u.id
        LEFT JOIN workers w ON u.id = w.user_id
        WHERE pm.project_id = ? AND pm.status = 'active' AND u.role = 'worker'
        ORDER BY u.name ASC
    ");
    $m_stmt->execute([$selected_project_id]);
    $project_members = $m_stmt->fetchAll();
}
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="<?= BASE_URL ?>/contractor/contracts.php" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Contracts
                </a>
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-file-signature text-warning me-2"></i>Create Digital Work Contract</h1>
                <p class="text-muted small mb-0">Generate legally binding digital agreements for project workers.</p>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger py-2 px-3 small mb-4">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= sanitize($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>/contractor/create-contract.php" method="POST" id="contractForm">
            <?= csrf_field() ?>
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="bc-card p-4 mb-4">
                        <h5 class="fw-bold text-white mb-3"><i class="fa-solid fa-file-contract text-warning me-2"></i>Contract Overview</h5>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-semibold">Select Project <span class="text-danger">*</span></label>
                                <select class="form-select bg-dark text-light border-secondary" name="project_id" id="projectIdSelect" required onchange="this.form.method='GET'; this.form.action=''; this.form.submit();">
                                    <option value="">-- Select Project --</option>
                                    <?php foreach ($projects as $p): ?>
                                        <option value="<?= $p['id'] ?>" <?= ($selected_project_id == $p['id']) ? 'selected' : '' ?>>
                                            <?= sanitize($p['title']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-semibold">Assigned Worker <span class="text-danger">*</span></label>
                                <select class="form-select bg-dark text-light border-secondary" name="worker_id" required>
                                    <option value="">-- Select Worker --</option>
                                    <?php if ($selected_project_id == 0): ?>
                                        <option value="" disabled>Select a project first to load members</option>
                                    <?php elseif (empty($project_members)): ?>
                                        <option value="" disabled>No active workers found in this project</option>
                                    <?php else: ?>
                                        <?php foreach ($project_members as $w): ?>
                                            <option value="<?= $w['id'] ?>" <?= ($selected_worker_id == $w['id']) ? 'selected' : '' ?>>
                                                <?= sanitize($w['name']) ?> (<?= sanitize($w['trade_title'] ?? 'Worker') ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label text-muted small fw-semibold">Contract Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" name="title" required placeholder="e.g. Structural Steel Welding Agreement" value="<?= sanitize($_POST['title'] ?? 'Digital Employment Agreement') ?>">
                            </div>

                            <div class="col-12">
                                <label class="form-label text-muted small fw-semibold">Contract Scope / Description</label>
                                <textarea class="form-control bg-dark text-light border-secondary" name="description" rows="2" placeholder="Brief overview of work scope and site duties..."><?= sanitize($_POST['description'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="bc-card p-4 mb-4">
                        <h5 class="fw-bold text-white mb-3"><i class="fa-solid fa-coins text-warning me-2"></i>Compensation & Work Schedule</h5>
                        
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label text-muted small fw-semibold">Payment Type <span class="text-danger">*</span></label>
                                <select class="form-select bg-dark text-light border-secondary" name="payment_type">
                                    <option value="hourly" selected>Hourly Rate (₹/hr)</option>
                                    <option value="daily">Daily Rate (₹/day)</option>
                                    <option value="fixed">Fixed Project Rate (₹)</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label text-muted small fw-semibold">Agreed Amount (₹) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="1" class="form-control bg-dark text-light border-secondary" name="payment_amount" required placeholder="45.00" value="<?= sanitize($_POST['payment_amount'] ?? '45.00') ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label text-muted small fw-semibold">Working Hours</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" name="working_hours" placeholder="e.g. 8 hours/day, Mon-Fri" value="<?= sanitize($_POST['working_hours'] ?? '8 hours/day, Mon-Fri') ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-semibold">Start Date</label>
                                <input type="date" class="form-control bg-dark text-light border-secondary" name="start_date" value="<?= sanitize($_POST['start_date'] ?? date('Y-m-d')) ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-semibold">End Date</label>
                                <input type="date" class="form-control bg-dark text-light border-secondary" name="end_date" value="<?= sanitize($_POST['end_date'] ?? date('Y-m-d', strtotime('+3 months'))) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="bc-card p-4">
                        <h5 class="fw-bold text-white mb-3"><i class="fa-solid fa-scroll text-warning me-2"></i>Terms & Legal Conditions</h5>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-semibold">Terms and Conditions Text <span class="text-danger">*</span></label>
                            <textarea class="form-control bg-dark text-light border-secondary font-monospace extra-small" name="terms" rows="8" required><?= sanitize($_POST['terms'] ?? "1. COMPLIANCE & SAFETY: Worker agrees to comply with all site safety rules, wearing required PPE at all times.\n2. COMPENSATION: Payment will be processed bi-weekly based on verified QR attendance logs at the agreed rate.\n3. DURATION: Contract commences on the Start Date and terminates on the End Date unless extended by mutual consent.\n4. TERMINATION: Either party may terminate this agreement with 3 days written notice for non-performance or safety breaches.") ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="bc-card p-4 sticky-top" style="top: 100px;">
                        <h5 class="fw-bold text-white mb-3"><i class="fa-solid fa-paper-plane text-warning me-2"></i>Actions</h5>
                        <p class="text-muted extra-small">You can save this contract as a draft or confirm and issue it directly to the worker for e-signature.</p>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" name="action_type" value="send" class="btn btn-amber fw-bold py-2">
                                <i class="fa-solid fa-paper-plane me-1"></i> Confirm & Send to Worker
                            </button>
                            <button type="submit" name="action_type" value="draft" class="btn btn-outline-secondary text-white py-2">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Save Draft
                            </button>
                            <a href="<?= BASE_URL ?>/contractor/contracts.php" class="btn btn-dark text-muted btn-sm text-center mt-2">
                                Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
