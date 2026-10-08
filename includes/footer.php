    <!-- Global Command Palette Modal (Ctrl + K) -->
    <div class="modal fade" id="commandPaletteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" style="background: rgba(255, 255, 255, 0.96); backdrop-filter: blur(12px);">
                <div class="modal-header border-0 pb-0 pt-3 px-4">
                    <div class="input-group input-group-lg border-bottom pb-2">
                        <span class="input-group-text bg-transparent border-0 pe-2 text-primary">
                            <i class="fa-solid fa-magnifying-glass fs-5"></i>
                        </span>
                        <input type="text" id="commandPaletteInput" class="form-control bg-transparent border-0 shadow-none fs-6" placeholder="Type a command or search sections... (Press Esc to close)" autofocus>
                        <span class="badge bg-secondary bg-opacity-10 text-secondary align-self-center px-2 py-1 small">Esc</span>
                    </div>
                </div>
                <div class="modal-body p-4" style="max-height: 420px; overflow-y: auto;">
                    <div class="text-uppercase text-muted extra-small fw-bold tracking-wider mb-2">Navigation Shortcuts</div>
                    <div class="list-group list-group-flush mb-3" id="commandPaletteList">
                        <a href="<?= BASE_URL ?>/admin/index.php" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between rounded-3 border-0 py-2.5 px-3">
                            <span><i class="fa-solid fa-gauge-high text-primary me-2"></i>Executive Admin Dashboard</span>
                            <span class="text-muted extra-small">Jump to Dashboard</span>
                        </a>
                        <a href="<?= BASE_URL ?>/admin/users.php" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between rounded-3 border-0 py-2.5 px-3">
                            <span><i class="fa-solid fa-users text-info me-2"></i>Manage Users</span>
                            <span class="text-muted extra-small">Users & Roles</span>
                        </a>
                        <a href="<?= BASE_URL ?>/admin/verification.php" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between rounded-3 border-0 py-2.5 px-3">
                            <span><i class="fa-solid fa-shield-halved text-success me-2"></i>Verification Queue</span>
                            <span class="text-muted extra-small">Verify Workers & Contractors</span>
                        </a>
                        <a href="<?= BASE_URL ?>/admin/projects.php" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between rounded-3 border-0 py-2.5 px-3">
                            <span><i class="fa-solid fa-building text-warning me-2"></i>Projects Overview</span>
                            <span class="text-muted extra-small">View All Projects</span>
                        </a>
                        <a href="<?= BASE_URL ?>/admin/analytics.php" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between rounded-3 border-0 py-2.5 px-3">
                            <span><i class="fa-solid fa-chart-line text-primary me-2"></i>Platform Analytics</span>
                            <span class="text-muted extra-small">Metrics & Graphs</span>
                        </a>
                        <a href="<?= BASE_URL ?>/admin/ai-usage.php" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between rounded-3 border-0 py-2.5 px-3">
                            <span><i class="fa-solid fa-brain text-info me-2"></i>AI Usage Monitor</span>
                            <span class="text-muted extra-small">AI Tokens & Performance</span>
                        </a>
                        <a href="<?= BASE_URL ?>/admin/settings.php" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between rounded-3 border-0 py-2.5 px-3">
                            <span><i class="fa-solid fa-sliders text-secondary me-2"></i>System Settings</span>
                            <span class="text-muted extra-small">Platform Configuration</span>
                        </a>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light py-2 px-4 d-flex justify-content-between text-muted extra-small">
                    <span><i class="fa-solid fa-keyboard me-1"></i> Use <kbd class="bg-white text-dark shadow-sm">Ctrl + K</kbd> to open anytime</span>
                    <span>BuildConnect SaaS Enterprise</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Leaflet JS (OpenStreetMap engine fallback) -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <?php if ($gmaps_key = get_google_maps_api_key()): ?>
        <script src="https://maps.googleapis.com/maps/api/js?key=<?= urlencode($gmaps_key) ?>&libraries=places"></script>
    <?php endif; ?>

    <!-- Chart.js Library for Analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- BuildConnect Maps JS -->
    <script src="<?= BASE_URL ?>/assets/js/maps.js"></script>

    <!-- BuildConnect Core JS -->
    <script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= time() ?>"></script>

    <!-- BuildConnect App JS -->
    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= time() ?>"></script>

    <!-- BuildConnect Web Push Notifications JS -->
    <script src="<?= BASE_URL ?>/assets/js/push-notifications.js?v=<?= time() ?>"></script>

    <!-- BuildConnect Independent Sidebar Scroll Persistence -->
    <script src="<?= BASE_URL ?>/assets/js/sidebar-scroll.js?v=<?= time() ?>"></script>
</body>
</html>

