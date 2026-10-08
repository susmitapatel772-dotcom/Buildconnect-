<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('worker');

$page_title = "My Identity Documents - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$user_id = (int)$user['id'];
$flash = get_flash_message();

// Fetch Worker Profile ID
$stmt = $db->prepare("SELECT id FROM workers WHERE user_id = ?");
$stmt->execute([$user_id]);
$worker = $stmt->fetch();

if (!$worker) {
    $stmt_ins = $db->prepare("INSERT INTO workers (user_id) VALUES (?)");
    $stmt_ins->execute([$user_id]);
    $worker_profile_id = (int)$db->lastInsertId();
} else {
    $worker_profile_id = (int)$worker['id'];
}

$errors = [];
$allowed_doc_types = [
    'Aadhaar Card',
    'PAN Card',
    'Driving Licence',
    'Voter ID Card',
    'Passport',
    'Other Government-Issued ID',
    'Trade Certificate',
    'Skill Certificate',
    'Experience Certificate',
    'Other Supporting Document'
];

// Handle Secure Document Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = "CSRF security check failed.";
    }

    $document_type = sanitize($_POST['document_type'] ?? '');
    if (empty($document_type) || !in_array($document_type, $allowed_doc_types, true)) {
        $errors[] = "Please select a valid document type from the list.";
    }

    $document_number = sanitize($_POST['document_number'] ?? '');
    $issue_date = !empty($_POST['issue_date']) ? sanitize($_POST['issue_date']) : null;
    $expiry_date = !empty($_POST['expiry_date']) ? sanitize($_POST['expiry_date']) : null;

    if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Please select a valid document file to upload.";
    } else {
        $file_tmp = $_FILES['document']['tmp_name'];
        $file_size = $_FILES['document']['size'];
        $original_name = $_FILES['document']['name'];
        $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

        // Strict Security Validation: Allowed file extensions
        $allowed_exts = ['pdf', 'png', 'jpg', 'jpeg', 'webp'];
        $allowed_mimes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp'
        ];

        // File Size Limit: 5MB
        if ($file_size > 5 * 1024 * 1024) {
            $errors[] = "Document file size must not exceed 5MB.";
        }

        // Validate MIME type safely
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file_tmp);
        finfo_close($finfo);

        if (!in_array($file_ext, $allowed_exts, true) || !in_array($mime_type, $allowed_mimes, true)) {
            $errors[] = "Forbidden file type. Only PDF and Image files (JPG, JPEG, PNG, WEBP) are allowed.";
        }

        // Strict prevention of script or executable injection
        if (preg_match('/\.php|\.phtml|\.exe|\.sh|\.bat|\.js|\.cmd|\.vbs|\.pl|\.cgi/i', $original_name)) {
            $errors[] = "Executable or script files are strictly forbidden.";
        }

        if (empty($errors)) {
            $upload_dir = ROOT_PATH . '/uploads/documents/';
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0755, true);
            }

            // Generate safe, unguessable server filename
            $safe_file_path = 'uploads/documents/doc_w' . $worker_profile_id . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $file_ext;
            $destination = ROOT_PATH . '/' . $safe_file_path;

            if (move_uploaded_file($file_tmp, $destination)) {
                try {
                    $stmt_ins = $db->prepare("
                        INSERT INTO worker_documents (worker_id, document_type, document_number, file_path, file_name, issue_date, expiry_date, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                    ");
                    $stmt_ins->execute([
                        $worker_profile_id,
                        $document_type,
                        !empty($document_number) ? $document_number : null,
                        $safe_file_path,
                        sanitize($original_name),
                        $issue_date,
                        $expiry_date
                    ]);
                    $new_doc_id = (int)$db->lastInsertId();

                    log_activity($user_id, 'Identity Document Uploaded', "Uploaded {$document_type}: {$original_name}", 'document', $new_doc_id);

                    set_flash_message("Identity document uploaded successfully! It is now pending Admin verification.", "success");
                    redirect('worker/documents.php');
                } catch (PDOException $e) {
                    $errors[] = "Database insert error: " . $e->getMessage();
                }
            } else {
                $errors[] = "Failed to store uploaded document on server.";
            }
        }
    }
}

// Fetch Documents belonging strictly to this worker
$stmt_docs = $db->prepare("SELECT * FROM worker_documents WHERE worker_id = ? ORDER BY id DESC");
$stmt_docs->execute([$worker_profile_id]);
$documents = $stmt_docs->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-id-card text-warning me-2"></i>My Identity Documents
                </h1>
                <p class="text-muted small mb-0">Upload your identity documents, such as Aadhaar Card, PAN Card, Driving Licence, Voter ID Card, and other accepted documents for Admin verification.</p>
            </div>
            <div>
                <button class="btn btn-amber btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#uploadDocModal">
                    <i class="fa-solid fa-upload me-1"></i> Upload Document
                </button>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show mb-4" role="alert">
                <i class="fa-solid fa-circle-info me-2"></i> <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4">
                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="bc-card p-4">
            <h2 class="h5 text-white fw-bold mb-3">
                <i class="fa-solid fa-folder-open text-info me-2"></i>My Uploaded Documents
            </h2>

            <?php if (empty($documents)): ?>
                <div class="bc-empty-state py-5 text-center">
                    <i class="fa-solid fa-id-card-clip fs-1 text-warning mb-3"></i>
                    <h3 class="h5 text-white fw-bold">No Identity Documents Uploaded Yet</h3>
                    <p class="text-muted small mb-3">
                        Upload your identity proof or supporting certificates so the administrator can review and verify your worker profile.
                    </p>
                    <button class="btn btn-amber btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#uploadDocModal">
                        <i class="fa-solid fa-upload me-1"></i> Upload Document Now
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Document Type</th>
                                <th>Document Number</th>
                                <th>Original File Name</th>
                                <th>Validity / Expiry</th>
                                <th>Status</th>
                                <th>Uploaded Date</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $doc): ?>
                                <tr>
                                    <td>
                                        <strong class="text-white">
                                            <i class="fa-solid fa-id-card me-2 text-warning"></i><?= e($doc['document_type']) ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <span class="text-info font-monospace small">
                                            <?= e(mask_identity_number($doc['document_number'] ?? '')) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-light small font-monospace"><?= e($doc['file_name']) ?></span>
                                    </td>
                                    <td class="text-muted extra-small">
                                        <?php if (!empty($doc['expiry_date'])): ?>
                                            <span>Exp: <?= format_date($doc['expiry_date']) ?></span>
                                        <?php elseif (!empty($doc['issue_date'])): ?>
                                            <span>Issued: <?= format_date($doc['issue_date']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                            $status_label = ucfirst($doc['status']);
                                            if ($doc['status'] === 'approved') {
                                                $status_label = 'Verified';
                                            }
                                        ?>
                                        <span class="badge <?= get_status_badge_class($doc['status']) ?> text-uppercase font-monospace">
                                            <?= e($status_label) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted extra-small">
                                        <?= format_date($doc['created_at']) ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>/worker/download-document.php?id=<?= (int)$doc['id'] ?>" target="_blank" rel="noopener" class="btn btn-outline-amber btn-sm extra-small">
                                            <i class="fa-solid fa-eye me-1"></i> View Document
                                        </a>
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

<!-- Upload Document Modal -->
<div class="modal fade" id="uploadDocModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-upload me-2 text-warning"></i>Upload Identity Document</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>/worker/documents.php" method="POST" enctype="multipart/form-data" data-loading="true">
                <?= csrf_field() ?>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Document Type <span class="text-danger">*</span></label>
                        <select name="document_type" class="form-select" required>
                            <option value="" disabled selected>-- Select Document Type --</option>
                            <?php foreach ($allowed_doc_types as $dt): ?>
                                <option value="<?= e($dt) ?>"><?= e($dt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Document Number <span class="text-muted">(Optional)</span></label>
                        <input type="text" name="document_number" class="form-control" placeholder="e.g. Aadhaar / PAN / Licence Number" maxlength="100">
                        <div class="form-text text-muted extra-small">Optional. Used for identity verification reference. Numbers are masked for privacy.</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small fw-semibold">Issue Date <span class="text-muted">(Optional)</span></label>
                            <input type="date" name="issue_date" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small fw-semibold">Expiry Date <span class="text-muted">(Optional)</span></label>
                            <input type="date" name="expiry_date" class="form-control">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Select Document File <span class="text-danger">*</span></label>
                        <input type="file" name="document" class="form-control" required accept=".pdf, .png, .jpg, .jpeg, .webp">
                        <div class="form-text text-muted extra-small">Max size 5MB. Allowed formats: PDF, Images (JPG, JPEG, PNG, WEBP). Executables prohibited.</div>
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-amber btn-sm fw-bold"><i class="fa-solid fa-upload me-1"></i> Upload Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
