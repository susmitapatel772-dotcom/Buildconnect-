<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('contractor');

$page_title = "My Projects Map - Contractor - BuildConnect";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = currentUser();
$db = getDB();
$contractor_id = (int)$user['id'];

$stmt = $db->prepare("
    SELECT id, title, location, address, city, state, location_lat, location_lng, status, progress_percent, budget
    FROM projects
    WHERE contractor_id = ?
    ORDER BY id DESC
");
$stmt->execute([$contractor_id]);
$projects = $stmt->fetchAll();

// Format project markers JSON for JS map loader
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
            'url' => BASE_URL . '/contractor/project-details.php?id=' . $p['id']
        ];
    }
}
?>

<div class="bc-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="bc-main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 fw-bold text-white mb-1"><i class="fa-solid fa-map-location-dot text-warning me-2"></i>My Projects Site Map</h1>
                <p class="text-muted small mb-0">Geographic visual distribution of your construction projects.</p>
            </div>
            <a href="<?= BASE_URL ?>/contractor/projects.php" class="btn btn-outline-light btn-sm fw-bold">
                <i class="fa-solid fa-list me-1"></i> List View
            </a>
        </div>

        <div class="bc-card p-4 mb-4">
            <div id="contractorProjectsMap" style="height: 520px; width: 100%;" class="rounded border border-secondary bg-dark"></div>
        </div>

        <!-- Projects Grid Overview -->
        <div class="row g-4">
            <?php foreach ($projects as $proj): ?>
                <div class="col-md-4">
                    <div class="bc-card p-3 h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h3 class="h6 text-white fw-bold mb-0"><?= sanitize($proj['title']) ?></h3>
                                <span class="badge <?= get_status_badge_class($proj['status']) ?> text-capitalize"><?= sanitize($proj['status']) ?></span>
                            </div>
                            <div class="text-muted extra-small mb-2"><i class="fa-solid fa-location-dot me-1 text-warning"></i><?= sanitize($proj['location']) ?></div>
                            <div class="text-muted extra-small mb-2">Progress: <strong class="text-light"><?= $proj['progress_percent'] ?>%</strong></div>
                        </div>
                        <div class="pt-2 border-top border-secondary d-flex justify-content-between align-items-center">
                            <?php if ($proj['location_lat'] && $proj['location_lng']): ?>
                                <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $proj['location_lat'] ?>,<?= $proj['location_lng'] ?>" target="_blank" rel="noopener" class="text-warning extra-small text-decoration-none">
                                    <i class="fa-solid fa-route me-1"></i> Directions
                                </a>
                            <?php else: ?>
                                <span class="text-muted extra-small">No GPS</span>
                            <?php endif; ?>
                            <a href="<?= BASE_URL ?>/contractor/project-details.php?id=<?= $proj['id'] ?>" class="btn btn-amber btn-sm extra-small fw-bold">Details</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const projectsData = <?= json_encode($map_projects) ?>;
    if (window.BC_Maps) {
        const mapObj = BC_Maps.initProjectMap('contractorProjectsMap', <?= DEFAULT_LATITUDE ?>, <?= DEFAULT_LONGITUDE ?>, 11);
        if (mapObj && projectsData.length > 0) {
            BC_Maps.loadProjectMarkers(mapObj, projectsData);
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
