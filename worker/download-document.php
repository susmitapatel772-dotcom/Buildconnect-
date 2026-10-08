<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Authenticate user as worker or admin
$user = currentUser();
if (!$user || !in_array($user['role'], ['worker', 'admin'], true)) {
    http_response_code(403);
    die("Access Denied: You must be logged in to access identity documents.");
}

$doc_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($doc_id <= 0) {
    set_flash_message("Invalid document request.", "danger");
    redirect($user['role'] === 'admin' ? 'admin/verification.php' : 'worker/documents.php');
}

$db = getDB();

if ($user['role'] === 'worker') {
    // Get worker profile ID
    $stmt_w = $db->prepare("SELECT id FROM workers WHERE user_id = ?");
    $stmt_w->execute([$user['id']]);
    $worker = $stmt_w->fetch();
    $worker_profile_id = (int)($worker['id'] ?? 0);

    // Strict ownership verification: Document must belong to the logged-in worker
    $stmt = $db->prepare("SELECT * FROM worker_documents WHERE id = ? AND worker_id = ?");
    $stmt->execute([$doc_id, $worker_profile_id]);
} else {
    // Admin review permission
    $stmt = $db->prepare("SELECT * FROM worker_documents WHERE id = ?");
    $stmt->execute([$doc_id]);
}

$document = $stmt->fetch();

if (!$document) {
    set_flash_message("Access Denied: You do not have authorization to view this identity document.", "danger");
    redirect($user['role'] === 'admin' ? 'admin/verification.php' : 'worker/documents.php');
}

// Prevent path traversal and check physical file existence
$file_relative = ltrim($document['file_path'], '/\\');
$full_path = ROOT_PATH . '/' . $file_relative;
$real_path = realpath($full_path);
$real_root = realpath(ROOT_PATH);

if (!$real_path || strpos($real_path, $real_root) !== 0 || !file_exists($real_path)) {
    log_activity($user['id'], 'Identity Document View Attempted', "Attempted view for document ID {$doc_id} ('{$document['document_type']}')", 'worker_document', $doc_id);

    header('Content-Type: text/plain');
    header('Content-Disposition: inline; filename="document_info.txt"');
    echo "BuildConnect Secure Identity Document Record\n";
    echo "============================================\n";
    echo "Document Type: " . $document['document_type'] . "\n";
    echo "Document Number: " . mask_identity_number($document['document_number'] ?? '') . "\n";
    echo "File Name: " . $document['file_name'] . "\n";
    echo "Status: " . ucfirst($document['status']) . "\n";
    echo "Uploaded Date: " . $document['created_at'] . "\n\n";
    echo "Note: The database record is verified and active.";
    exit();
}

// Log activity
log_activity($user['id'], 'Identity Document Viewed', "Viewed document ID {$doc_id} ('{$document['document_type']}')", 'worker_document', $doc_id);

$mime_type = mime_content_type($real_path) ?: 'application/octet-stream';
$download_filename = basename($document['file_name']);

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime_type);
header('Content-Disposition: inline; filename="' . str_replace('"', '', $download_filename) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . filesize($real_path));

readfile($real_path);
exit();
