<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('worker');

$page_title = "My Documents & Credentials - BuildConnect";
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
    'Skill Certificate',
    'Identity Verification',
    'Training Certificate',
    'Experience Certificate',
    'Safety License',
    'Other Document'
];

// Handle Secure Document Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $errors[] = "CSRF security check failed.";
    }

    $document_type = sanitize($_POST['document_type'] ?? 'Skill Certificate');
    if (!in_array($document_type, $allowed_doc_types, true)) {
        $document_type = 'Skill Certificate';
    }

    if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Please select a valid document file to upload.";
    } else {
        $file_tmp = $_FILES['document']['tmp_name'];
        $file_size = $_FILES['document']['size'];
        $original_name = $_FILES['document']['name'];
        $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

        // Strict Security Validation
        $allowed_exts = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'doc', 'docx'];
        $allowed_mimes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];

        // Size Limit: 5MB
        if ($file_size > 5 * 1024 * 1024) {
            $errors[] = "Document file size must not exceed 5MB.";
        }

        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file_tmp);
        finfo_close($finfo);

        if (!in_array($file_ext, $allowed_exts, true) || !in_array($mime_type, $allowed_mimes, true)) {
            $errors[] = "Forbidden file type. Only PDF, Images (JPG, PNG, WEBP), and Word documents are allowed.";
        }

        // Check for double extension or php injection tricks
        if (preg_match('/\.php|\.phtml|\.exe|\.sh|\.bat|\.js/i', $original_name)) {
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
                        INSERT INTO worker_documents (worker_id, document_type, file_path, file_name, status, created_at)
                        VALUES (?, ?, ?, ?, 'pending', NOW())
                    ");
                    $stmt_ins->execute([$worker_profile_id, $document_type, $safe_file_path, sanitize($original_name)]);
                    $new_doc_id = (int)$db->lastInsertId();

                    log_activity($user_id, 'Document Uploaded', "Uploaded document '{$document_type}': {$original_name}", 'document', $new_doc_id);

                    set_flash_message("Document uploaded successfully! It is now pending Admin verification.", "success");
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
                    <i class="fa-solid fa-file-contract text-warning me-2"></i>My Trade Credentials & Documents
                </h1>
                <p class="text-muted small mb-0">Upload certifications, identity verification docs, and licenses for Admin verification.</p>
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
                <i class="fa-solid fa-folder-open text-info me-2"></i>Uploaded Document Credentials
            </h2>

            <?php if (empty($documents)): ?>
                <div class="bc-empty-state py-5 text-center">
                    <i class="fa-solid fa-file-circle-plus fs-1 text-warning mb-3"></i>
                    <h3 class="h5 text-white fw-bold">No Documents Uploaded Yet</h3>
                    <p class="text-muted small mb-3">
                        Upload your skill certifications or official ID documents to get your worker profile verified by administrators.
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
                                <th>Original File Name</th>
                                <th>Status</th>
                                <th>Uploaded Date</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $doc): ?>
                                <tr>
                                    <td>
                                        <strong class="text-white"><i class="fa-solid fa-file-lines me-2 text-warning"></i><?= e($doc['document_type']) ?></strong>
                                    </td>
                                    <td>
                                        <span class="text-light small font-monospace"><?= e($doc['file_name']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge <?= get_status_badge_class($doc['status']) ?> text-uppercase font-monospace">
                                            <?= e($doc['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted extra-small">
                                        <?= format_date($doc['created_at']) ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>/<?= e($doc['file_path']) ?>" target="_blank" rel="noopener" class="btn btn-outline-amber btn-sm extra-small">
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
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-upload me-2 text-warning"></i>Upload Credential Document</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= BASE_URL ?>/worker/documents.php" method="POST" enctype="multipart/form-data" data-loading="true">
                <?= csrf_field() ?>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Document Category <span class="text-danger">*</span></label>
                        <select name="document_type" class="form-select" required>
                            <?php foreach ($allowed_doc_types as $dt): ?>
                                <option value="<?= e($dt) ?>"><?= e($dt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Select File <span class="text-danger">*</span></label>
                        <input type="file" name="document" class="form-control" required accept=".pdf, .png, .jpg, .jpeg, .webp, .doc, .docx">
                        <div class="form-text text-muted extra-small">Max size 5MB. Permitted: PDF, Images (JPG, PNG, WEBP), Word docs. Executables prohibited.</div>
                    </div>
                </div>

                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-amber btn-sm fw-bold"><i class="fa-solid fa-upload me-1"></i> Submit for Verification</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
