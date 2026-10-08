/**
 * BuildConnect Foundation JavaScript (app.js)
 * Reusable foundation helpers: mobile nav, alert dismissal, form helper, loading state.
 */

document.addEventListener('DOMContentLoaded', () => {
    initAlertDismissal();
    initMobileNav();
    initFormHelpers();
});

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
