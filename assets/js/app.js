/**
 * BuildConnect Foundation JavaScript (app.js)
 * Reusable foundation helpers: mobile nav, alert dismissal, form helper, loading state.
 */

document.addEventListener('DOMContentLoaded', () => {
    initAlertDismissal();
    initMobileNav();
    initFormHelpers();
    initRoleSidebarScroll('bc-worker-sidebar', 'bc_worker_sidebar_scroll');
    initRoleSidebarScroll('bc-admin-sidebar', 'buildconnect_admin_sidebar_scroll');
    initRoleSidebarScroll('bc-client-sidebar', 'buildconnect_client_sidebar_scroll');
    initLogoutCleanup();
    initCommandPalette();
    initSidebarCollapse();
    initExportReport();
});

// Command Palette (Ctrl + K) Keyboard Shortcut & Quick Search
function initCommandPalette() {
    const paletteModalEl = document.getElementById('commandPaletteModal');
    if (!paletteModalEl) return;

    // Use Bootstrap modal instance if available
    let bsModal = null;
    if (typeof bootstrap !== 'undefined') {
        bsModal = new bootstrap.Modal(paletteModalEl);
    }

    // Ctrl + K keyboard event
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            if (bsModal) bsModal.show();
        }
    });

    // Trigger Command Palette when clicking top global search input
    const topSearch = document.getElementById('bc-global-search-input');
    if (topSearch) {
        topSearch.addEventListener('click', (e) => {
            e.preventDefault();
            if (bsModal) bsModal.show();
        });
    }

    // Command Palette Input Filtering
    const searchInput = document.getElementById('commandPaletteInput');
    const itemsList = document.getElementById('commandPaletteList');
    if (searchInput && itemsList) {
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.toLowerCase().trim();
            const items = itemsList.querySelectorAll('a');
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(query) ? 'flex' : 'none';
            });
        });
    }
}

// Sidebar Collapse / Mini Icon Toggle & Mobile Drawer Support
function initSidebarCollapse() {
    const collapseBtn = document.getElementById('bc-sidebar-collapse-btn');
    const sidebar = document.querySelector('.bc-sidebar');
    if (collapseBtn && sidebar) {
        collapseBtn.addEventListener('click', () => {
            sidebar.classList.toggle('bc-sidebar-collapsed');
            const isCollapsed = sidebar.classList.contains('bc-sidebar-collapsed');
            localStorage.setItem('bc_sidebar_collapsed', isCollapsed ? '1' : '0');
        });

        // Restore user collapse preference
        if (localStorage.getItem('bc_sidebar_collapsed') === '1') {
            sidebar.classList.add('bc-sidebar-collapsed');
        }
    }
}

// CSV Executive Export Report Helper
function initExportReport() {
    const exportBtn = document.getElementById('bc-export-report-btn');
    if (!exportBtn) return;

    exportBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const csvContent = "data:text/csv;charset=utf-8," 
            + "Metric,Value,Status\n"
            + "Total Users,12,Active\n"
            + "Total Companies,5,Active\n"
            + "Total Contractors,4,Active\n"
            + "Total Clients,3,Active\n"
            + "Pending Verifications,1,Pending\n";
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `BuildConnect_Executive_Report_${new Date().toISOString().slice(0,10)}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        if (typeof showNotification === 'function') {
            showNotification('Executive CSV Report downloaded successfully!', 'success');
        }
    });
}


// Role-Specific Sidebar Scroll Position Preservation (Worker, Admin, Client)
function initRoleSidebarScroll(elementId, storageKey) {
    const sidebar = document.getElementById(elementId);
    if (!sidebar) return;

    // 1. Restore saved scroll position across page reloads & page navigations
    const savedPos = sessionStorage.getItem(storageKey);
    if (savedPos !== null) {
        const targetPos = parseFloat(savedPos);
        if (!isNaN(targetPos)) {
            sidebar.scrollTop = targetPos;
        }
    }

    // 2. Continuously save scroll position as user scrolls inside sidebar
    sidebar.addEventListener('scroll', () => {
        sessionStorage.setItem(storageKey, sidebar.scrollTop.toString());
    }, { passive: true });
}

// Clear sidebar scroll state on logout
function initLogoutCleanup() {
    const logoutLinks = document.querySelectorAll('a[href*="logout.php"]');
    logoutLinks.forEach(link => {
        link.addEventListener('click', () => {
            sessionStorage.removeItem('buildconnect_admin_sidebar_scroll');
            sessionStorage.removeItem('buildconnect_client_sidebar_scroll');
            sessionStorage.removeItem('bc_worker_sidebar_scroll');
        });
    });
}



// Auto-dismiss or manual dismiss for alerts
function initAlertDismissal() {
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        const closeBtn = alert.querySelector('.btn-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                alert.classList.add('fade');
                setTimeout(() => alert.remove(), 200);
            });
        }
    });
}

// Mobile Navigation Toggle
function initMobileNav() {
    const navToggle = document.querySelector('[data-bc-toggle="navbar"]');
    const navMenu = document.querySelector('#bc-navbar-menu');
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', () => {
            navMenu.classList.toggle('d-none');
            navMenu.classList.toggle('d-block');
        });
    }
}

// Form Helpers: Submit Button Loading State
function initFormHelpers() {
    const forms = document.querySelectorAll('form[data-loading="true"]');
    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                const originalHtml = submitBtn.innerHTML;
                submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Processing...`;
                // Restore button after 8s fallback in case of aborted submit
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHtml;
                }, 8000);
            }
        });
    });
}

// Reusable toast/alert helper
function showNotification(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show shadow-sm mb-2`;
    alertDiv.role = 'alert';
    alertDiv.innerHTML = `
        <span>${message}</span>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    container.appendChild(alertDiv);

    setTimeout(() => {
        alertDiv.classList.remove('show');
        setTimeout(() => alertDiv.remove(), 250);
    }, 4000);
}
