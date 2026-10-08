<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$page_title = "Platform Projects Map - Admin - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();

$stmt = $db->query("
    SELECT p.id, p.title, p.location, p.city, p.state, p.location_lat, p.location_lng, p.status, p.progress_percent,
           u.name as contractor_name, c.company_name
    FROM projects p
    JOIN users u ON p.contractor_id = u.id
    LEFT JOIN contractors c ON u.id = c.user_id
    ORDER BY p.id DESC
");
$projects = $stmt->fetchAll();

$map_projects = [];
foreach ($projects as $p) {
    if (!empty($p['location_lat']) && !empty($p['location_lng'])) {
        $map_projects[] = [
            'id' => $p['id'],
            'title' => $p['title'],
            'location' => $p['location'],
            'city' => $p['city'] ?: $p['location'],
            'status' => $p['status'],
            'progress_percent' => (int)$p['progress_percent'],
            'latitude' => (float)$p['location_lat'],
            'longitude' => (float)$p['location_lng'],
            'url' => BASE_URL . '/admin/projects.php?id=' . $p['id']
        ];
    }
}
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-earth-americas text-warning me-2"></i>Platform Construction Projects Map</h1>
                <p class="text-muted small mb-0">System-wide geographic overview of all registered construction sites.</p>
            </div>
            <a href="<?= BASE_URL ?>/admin/projects.php" class="btn btn-outline-light btn-sm fw-bold">
                <i class="fa-solid fa-list me-1"></i> Projects Directory
            </a>
        </div>

        <div class="bc-card p-4 mb-4">
            <div id="adminProjectsMap" style="height: 550px; width: 100%;" class="rounded border border-secondary bg-dark"></div>
        </div>

        <div class="bc-card p-4">
            <h5 class="fw-bold text-white mb-3"><i class="fa-solid fa-building me-2 text-info"></i>Site Location Inventory (<?= count($projects) ?>)</h5>
            <div class="table-responsive">
                <table class="table table-custom align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Contractor Firm</th>
                            <th>City / Location</th>
                            <th>Coordinates</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $p): ?>
                            <tr>
                                <td class="fw-bold text-white"><?= sanitize($p['title']) ?></td>
                                <td class="small text-light"><?= sanitize($p['company_name'] ?: $p['contractor_name']) ?></td>
                                <td class="small text-muted"><i class="fa-solid fa-location-dot me-1 text-warning"></i><?= sanitize($p['location']) ?></td>
                                <td class="font-monospace extra-small text-info">
                                    <?= $p['location_lat'] && $p['location_lng'] ? number_format($p['location_lat'], 4) . ', ' . number_format($p['location_lng'], 4) : '<span class="text-muted">Unmapped</span>' ?>
                                </td>
                                <td><span class="badge <?= get_status_badge_class($p['status']) ?> text-capitalize"><?= sanitize($p['status']) ?></span></td>
                                <td class="text-end">
                                    <?php if ($p['location_lat'] && $p['location_lng']): ?>
                                        <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $p['location_lat'] ?>,<?= $p['location_lng'] ?>" target="_blank" rel="noopener" class="btn btn-outline-warning btn-sm extra-small">
                                            <i class="fa-solid fa-route"></i> Directions
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const projectsData = <?= json_encode($map_projects) ?>;
    if (window.BC_Maps) {
        const mapObj = BC_Maps.initProjectMap('adminProjectsMap', <?= DEFAULT_LATITUDE ?>, <?= DEFAULT_LONGITUDE ?>, 11);
        if (mapObj && projectsData.length > 0) {
            BC_Maps.loadProjectMarkers(mapObj, projectsData);
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
