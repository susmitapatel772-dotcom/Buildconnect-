/**
 * BuildConnect Centralized Reusable Map Library (Google Maps & OpenStreetMap/Leaflet Fallback)
 */

window.BC_Maps = (function () {
    const DEFAULT_LAT = 23.0225;
    const DEFAULT_LNG = 72.5714;
    const DEFAULT_ZOOM = 13;

    function hasGoogleMaps() {
        return typeof window.google !== 'undefined' && typeof window.google.maps !== 'undefined';
    }

    function hasLeaflet() {
        return typeof window.L !== 'undefined';
    }

    /**
     * Initialize a project map inside a container element
     */
    function initializeProjectMap(containerId, lat, lng, zoom, options) {
        const el = document.getElementById(containerId);
        if (!el) return null;

        lat = (typeof lat === 'number' && !isNaN(lat)) ? lat : DEFAULT_LAT;
        lng = (typeof lng === 'number' && !isNaN(lng)) ? lng : DEFAULT_LNG;
        zoom = zoom || DEFAULT_ZOOM;
        options = options || {};

        if (hasGoogleMaps()) {
            try {
                const mapOptions = {
                    center: { lat: lat, lng: lng },
                    zoom: zoom,
                    mapTypeControl: true,
                    streetViewControl: false
                };
                const gMap = new google.maps.Map(el, mapOptions);

                return {
                    type: 'google',
                    instance: gMap,
                    markers: [],
                    center: function (cLat, cLng) {
                        gMap.setCenter({ lat: cLat, lng: cLng });
                    }
                };
            } catch (e) {
                console.warn("Google Maps init failed, falling back to Leaflet:", e);
            }
        }

        if (hasLeaflet()) {
            const lMap = L.map(containerId).setView([lat, lng], zoom);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; BuildConnect Maps'
            }).addTo(lMap);

            return {
                type: 'leaflet',
                instance: lMap,
                markers: [],
                center: function (cLat, cLng) {
                    lMap.setView([cLat, cLng]);
                }
            };
        }

        // Fallback UI if neither map engine is active
        el.innerHTML = '<div class="p-4 text-center text-muted bg-dark rounded border border-secondary"><i class="fa-solid fa-map-location-dot fs-1 mb-2 text-warning"></i><p class="mb-0">Map viewer loaded (Coordinates: ' + lat + ', ' + lng + ')</p></div>';
        return null;
    }

    /**
     * Add a project marker with popup info window
     */
    function addProjectMarker(mapObj, lat, lng, title, popupHtml) {
        if (!mapObj || !mapObj.instance) return null;

        lat = parseFloat(lat);
        lng = parseFloat(lng);
        if (isNaN(lat) || isNaN(lng)) return null;

        if (mapObj.type === 'google') {
            const marker = new google.maps.Marker({
                position: { lat: lat, lng: lng },
                map: mapObj.instance,
                title: title || ''
            });

            if (popupHtml) {
                const infoWindow = new google.maps.InfoWindow({ content: popupHtml });
                marker.addListener('click', function () {
                    infoWindow.open(mapObj.instance, marker);
                });
            }

            mapObj.markers.push(marker);
            return marker;
        }

        if (mapObj.type === 'leaflet') {
            const lMarker = L.marker([lat, lng]).addTo(mapObj.instance);
            if (popupHtml) {
                lMarker.bindPopup(popupHtml);
            }
            if (title) {
                lMarker.bindTooltip(title);
            }
            mapObj.markers.push(lMarker);
            return lMarker;
        }

        return null;
    }

    /**
     * Load multiple project markers on a single map
     */
    function loadProjectMarkers(mapObj, projectsArray) {
        if (!mapObj || !Array.isArray(projectsArray) || projectsArray.length === 0) return;

        const bounds = [];

        projectsArray.forEach(function (p) {
            const lat = parseFloat(p.location_lat || p.latitude);
            const lng = parseFloat(p.location_lng || p.longitude);

            if (!isNaN(lat) && !isNaN(lng)) {
                bounds.push([lat, lng]);

                const popupHtml = `
                    <div style="color: #0f172a; padding: 4px;">
                        <h6 style="font-weight: bold; margin-bottom: 4px;">${escapeHtml(p.title)}</h6>
                        <div style="font-size: 0.8rem; color: #475569;"><i class="fa-solid fa-location-dot"></i> ${escapeHtml(p.city || p.location)}</div>
                        <div style="font-size: 0.8rem; margin-top: 4px;">Status: <strong>${escapeHtml(p.status)}</strong> | Progress: <strong>${p.progress_percent}%</strong></div>
                        ${p.url ? `<a href="${p.url}" class="btn btn-primary btn-sm mt-2 extra-small text-white" style="font-size: 0.75rem; padding: 2px 8px;">View Details</a>` : ''}
                    </div>
                `;

                addProjectMarker(mapObj, lat, lng, p.title, popupHtml);
            }
        });

        // Fit map bounds if multiple markers exist
        if (bounds.length > 1) {
            if (mapObj.type === 'google' && window.google.maps.LatLngBounds) {
                const gBounds = new google.maps.LatLngBounds();
                bounds.forEach(b => gBounds.extend({ lat: b[0], lng: b[1] }));
                mapObj.instance.fitBounds(gBounds);
            } else if (mapObj.type === 'leaflet') {
                mapObj.instance.fitBounds(bounds, { padding: [30, 30] });
            }
        }
    }

    /**
     * Interactive Location Picker for project creation/editing
     */
    function enableLocationPicker(containerId, latInputId, lngInputId) {
        const latInput = document.getElementById(latInputId);
        const lngInput = document.getElementById(lngInputId);

        if (!latInput || !lngInput) return;

        let initLat = parseFloat(latInput.value) || DEFAULT_LAT;
        let initLng = parseFloat(lngInput.value) || DEFAULT_LNG;

        const mapObj = initializeProjectMap(containerId, initLat, initLng, 14);
        if (!mapObj) return;

        let activeMarker = addProjectMarker(mapObj, initLat, initLng, "Selected Location", "<strong>Selected Project Location</strong>");

        function updateCoords(lat, lng) {
            latInput.value = lat.toFixed(7);
            lngInput.value = lng.toFixed(7);

            if (mapObj.type === 'google' && activeMarker) {
                activeMarker.setPosition({ lat: lat, lng: lng });
            } else if (mapObj.type === 'leaflet') {
                if (activeMarker) mapObj.instance.removeLayer(activeMarker);
                activeMarker = L.marker([lat, lng]).addTo(mapObj.instance);
            }
        }

        if (mapObj.type === 'google') {
            mapObj.instance.addListener('click', function (e) {
                updateCoords(e.latLng.lat(), e.latLng.lng());
            });
        } else if (mapObj.type === 'leaflet') {
            mapObj.instance.on('click', function (e) {
                updateCoords(e.latlng.lat, e.latlng.lng);
            });
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    return {
        initProjectMap: initializeProjectMap,
        addProjectMarker: addProjectMarker,
        loadProjectMarkers: loadProjectMarkers,
        enableLocationPicker: enableLocationPicker
    };
})();
