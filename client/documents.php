<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('client');

$page_title = "Project Documents - Client - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$client_user_id = (int)$user['id'];
$flash = get_flash_message();

// Fetch project documents for projects owned by the logged-in client ONLY
$stmt = $db->prepare("
    SELECT pd.*, p.title as project_title, p.id as project_id, u.name as uploaded_by_name 
    FROM project_documents pd 
    JOIN projects p ON pd.project_id = p.id 
    LEFT JOIN users u ON pd.uploaded_by_user_id = u.id 
    WHERE p.client_id = ? 
    ORDER BY pd.id DESC
");
$stmt->execute([$client_user_id]);
$documents = $stmt->fetchAll();
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1">
                    <i class="fa-solid fa-file-contract text-warning me-2"></i>Project Documents & Legal Agreements
                </h1>
                <p class="text-muted small mb-0">Access contracts, inspection reports, and architectural plans linked to your projects.</p>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= sanitize($flash['type']) ?> alert-dismissible fade show mb-4" role="alert">
                <i class="fa-solid fa-circle-info me-2"></i> <?= sanitize($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="bc-card p-4">
            <h2 class="h5 text-white fw-bold mb-3">
                <i class="fa-solid fa-folder-open text-primary me-2"></i>Authorized Document Library
            </h2>

            <?php if (empty($documents)): ?>
                <div class="bc-empty-state py-5 text-center">
                    <i class="fa-solid fa-file-pdf fs-1 text-warning mb-3"></i>
                    <h3 class="h5 text-white fw-bold">No Documents Available</h3>
                    <p class="text-muted small mb-0">There are no uploaded documents associated with your project sites yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Document Title</th>
                                <th>Project Site</th>
                                <th>Category</th>
                                <th>Uploaded By</th>
                                <th>Upload Date</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $doc): ?>
                                <tr>
                                    <td>
                                        <strong class="text-white"><i class="fa-solid fa-file-lines me-2 text-warning"></i><?= e($doc['title']) ?></strong>
                                    </td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/client/project-details.php?id=<?= $doc['project_id'] ?>" class="text-info text-decoration-none fw-semibold">
                                            <?= e($doc['project_title']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge bg-dark border border-secondary text-warning font-monospace"><?= e($doc['file_type']) ?></span>
                                    </td>
                                    <td>
                                        <span class="text-light small"><?= e($doc['uploaded_by_name'] ?? 'System') ?></span>
                                    </td>
                                    <td class="text-muted extra-small">
                                        <?= format_datetime($doc['created_at']) ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>/client/download-document.php?id=<?= $doc['id'] ?>" class="btn btn-outline-amber btn-sm extra-small">
                                            <i class="fa-solid fa-download me-1"></i> Secure Download
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
