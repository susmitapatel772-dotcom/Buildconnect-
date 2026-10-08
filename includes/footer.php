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
    <script src="<?= BASE_URL ?>/assets/js/main.js"></script>

    <!-- BuildConnect App JS -->
    <script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
