<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// 1. Authenticate user as client
requireRole('client');

$user = currentUser();
$client_user_id = (int)$user['id'];
$doc_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($doc_id <= 0) {
    set_flash_message("Invalid document request.", "danger");
    redirect('client/documents.php');
}

$db = getDB();

// 2. Strict Ownership Verification: Document MUST belong to a project assigned to the authenticated client
$stmt = $db->prepare("
    SELECT pd.*, p.title as project_title, p.client_id 
    FROM project_documents pd 
    JOIN projects p ON pd.project_id = p.id 
    WHERE pd.id = ? AND p.client_id = ?
");
$stmt->execute([$doc_id, $client_user_id]);
$document = $stmt->fetch();

// Security block if document doesn't exist or client is unauthorized
if (!$document) {
    set_flash_message("Access Denied: You do not have authorization to download this document.", "danger");
    redirect('client/documents.php');
}

// 3. Prevent Path Traversal & Check Disk File Existence
$file_relative = ltrim($document['file_path'], '/\\');
$full_path = ROOT_PATH . '/' . $file_relative;
$real_path = realpath($full_path);
$real_root = realpath(ROOT_PATH);

if (!$real_path || strpos($real_path, $real_root) !== 0 || !file_exists($real_path)) {
    // If physical demo file doesn't exist on disk, serve a dynamic safe fallback text response
    log_activity($client_user_id, 'Document View Attempted', "Attempted download for document ID {$doc_id} ('{$document['title']}')", 'document', $doc_id);

    header('Content-Type: text/plain');
    header('Content-Disposition: inline; filename="document_preview.txt"');
    echo "BuildConnect Secure Document Preview\n";
    echo "====================================\n";
    echo "Document Title: " . $document['title'] . "\n";
    echo "Project: " . $document['project_title'] . "\n";
    echo "File Type: " . $document['file_type'] . "\n";
    echo "Uploaded Date: " . $document['created_at'] . "\n\n";
    echo "Note: This document record is verified and active in the system database.";
    exit();
}

// 4. Log Activity
log_activity($client_user_id, 'Document Downloaded', "Downloaded document '{$document['title']}'", 'document', $doc_id);

// 5. Send Secure File Headers
$mime_type = mime_content_type($real_path) ?: 'application/octet-stream';
$download_filename = basename($document['file_path']);

header('Content-Description: File Transfer');
header('Content-Type: ' . $mime_type);
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $download_filename) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($real_path));

readfile($real_path);
exit();
