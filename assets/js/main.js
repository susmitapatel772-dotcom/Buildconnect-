/**
 * BuildConnect Core JavaScript Framework
 */

document.addEventListener('DOMContentLoaded', () => {
    initTooltips();
    initMapIfPresent();
    initSignaturePadIfPresent();
    initNavbarToggle();
});

// Navbar Mobile Toggle Helper
function initNavbarToggle() {
    const toggleBtns = document.querySelectorAll('[data-bc-toggle="navbar"]');
    const mobileMenu = document.getElementById('bc-navbar-menu');

    if (!mobileMenu) return;

    toggleBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            mobileMenu.classList.toggle('d-none');
        });
    });
}

// Toast Notification Helper
function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toast-container');
    if (!toastContainer) return;

    const bgClass = type === 'success' ? 'bg-success' : type === 'error' ? 'bg-danger' : 'bg-primary';
    const toastId = 'toast-' + Date.now();

    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'} me-2"></i> ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;

    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    const toastEl = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
    toast.show();
}

// Map Initialization Helper (Leaflet OpenStreetMap)
function initMapIfPresent() {
    const mapElement = document.getElementById('leaflet-map');
    if (!mapElement) return;

    const lat = parseFloat(mapElement.dataset.lat || 37.7749);
    const lng = parseFloat(mapElement.dataset.lng || -122.4194);
    const zoom = parseInt(mapElement.dataset.zoom || 12);
    const title = mapElement.dataset.title || 'Project Location';

    if (typeof L !== 'undefined') {
        const map = L.map('leaflet-map').setView([lat, lng], zoom);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; BuildConnect Maps'
        }).addTo(map);

        // Custom Marker
        const marker = L.marker([lat, lng]).addTo(map);
        marker.bindPopup(`<b>${title}</b><br>Coordinates: ${lat.toFixed(4)}, ${lng.toFixed(4)}`).openPopup();
    }
}

// Digital Signature Canvas Handler
function initSignaturePadIfPresent() {
    const canvas = document.getElementById('signature-canvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    let isDrawing = false;

    // Set canvas dimensions
    canvas.width = canvas.offsetWidth;
    canvas.height = 180;

    ctx.strokeStyle = '#FFAA16';
    ctx.lineWidth = 3;
    ctx.lineCap = 'round';

    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        return {
            x: (e.clientX || e.touches[0].clientX) - rect.left,
            y: (e.clientY || e.touches[0].clientY) - rect.top
        };
    }

    function startDrawing(e) {
        isDrawing = true;
        const pos = getPos(e);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
    }

    function draw(e) {
        if (!isDrawing) return;
        const pos = getPos(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
    }

    function stopDrawing() {
        isDrawing = false;
        const hiddenInput = document.getElementById('signature-data');
        if (hiddenInput) {
            hiddenInput.value = canvas.toDataURL();
        }
    }

    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseleave', stopDrawing);

    // Touch events for mobile/tablet signature
    canvas.addEventListener('touchstart', (e) => { e.preventDefault(); startDrawing(e); });
    canvas.addEventListener('touchmove', (e) => { e.preventDefault(); draw(e); });
    canvas.addEventListener('touchend', stopDrawing);

    const clearBtn = document.getElementById('clear-signature');
    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            const hiddenInput = document.getElementById('signature-data');
            if (hiddenInput) hiddenInput.value = '';
        });
    }
}

// QR Code Attendance Scanner Helper
function startQRScanner(onSuccessCallback) {
    if (typeof Html5QrcodeScanner === 'undefined') {
        showToast('QR Scanner Library not loaded', 'error');
        return;
    }

    const scanner = new Html5QrcodeScanner("reader", { fps: 10, qrbox: { width: 250, height: 250 } }, false);
    scanner.render((decodedText, decodedResult) => {
        scanner.clear();
        if (onSuccessCallback) {
            onSuccessCallback(decodedText);
        }
    }, (error) => {
        // Quiet scan errors
    });
}

function initTooltips() {
    if (typeof bootstrap !== 'undefined') {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
}
