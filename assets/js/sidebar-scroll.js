/**
 * BuildConnect Independent Sidebar Scroll Persistence Engine
 * Uses role-specific sessionStorage keys to restore sidebar position without jumping.
 */

document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('.dashboard-sidebar, .bc-sidebar');
    if (!sidebar) return;

    let roleKey = 'buildconnect_general_sidebar_scroll';
    if (document.body.classList.contains('admin-app') || sidebar.classList.contains('bc-sidebar-admin')) {
        roleKey = 'buildconnect_admin_sidebar_scroll';
    } else if (document.body.classList.contains('worker-app') || sidebar.classList.contains('bc-sidebar-worker')) {
        roleKey = 'buildconnect_worker_sidebar_scroll';
    } else if (document.body.classList.contains('client-app') || sidebar.classList.contains('bc-sidebar-client')) {
        roleKey = 'buildconnect_client_sidebar_scroll';
    } else if (sidebar.classList.contains('bc-sidebar-contractor')) {
        roleKey = 'buildconnect_contractor_sidebar_scroll';
    }

    // Restore scroll position
    try {
        const savedPos = sessionStorage.getItem(roleKey) || localStorage.getItem(roleKey);
        if (savedPos !== null) {
            sidebar.scrollTop = parseInt(savedPos, 10);
        }
    } catch (e) {
        // Storage access fallback
    }

    // Debounced position save
    let timeoutId = null;
    sidebar.addEventListener('scroll', () => {
        if (timeoutId) clearTimeout(timeoutId);
        timeoutId = setTimeout(() => {
            try {
                sessionStorage.setItem(roleKey, sidebar.scrollTop.toString());
                localStorage.setItem(roleKey, sidebar.scrollTop.toString());
            } catch (e) {}
        }, 100);
    }, { passive: true });

    sidebar.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            try {
                sessionStorage.setItem(roleKey, sidebar.scrollTop.toString());
                localStorage.setItem(roleKey, sidebar.scrollTop.toString());
            } catch (e) {}
        });
    });
});
