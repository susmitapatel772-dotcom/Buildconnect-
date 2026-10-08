/**
 * BuildConnect Web Push Notification Client Module
 * Real-time Browser Push Notifications & Dual Delivery Engine for Localhost
 */

const BuildConnectPush = {
    swRegistration: null,
    isSubscribed: false,
    pollInterval: null,
    lastCheckedTime: Math.floor(Date.now() / 1000) - 30,

    getBaseUrl() {
        return window.BASE_URL ? window.BASE_URL.replace(/\/$/, '') : '';
    },

    async init() {
        const baseUrl = this.getBaseUrl();
        
        // Update UI initially
        this.updateUIStatus(window.Notification ? Notification.permission : 'unsupported');

        if (!('Notification' in window)) {
            console.warn('Web Notifications API is not supported in this browser environment.');
            this.updateUIStatus('unsupported', 'Web Push API is not supported in this browser.');
            return false;
        }

        // Try registering root Service Worker if available
        if ('serviceWorker' in navigator) {
            try {
                const swPath = baseUrl ? `${baseUrl}/sw.js` : '/sw.js';
                this.swRegistration = await navigator.serviceWorker.register(swPath, { scope: baseUrl || '/' });
                console.log('BuildConnect Service Worker registered:', this.swRegistration.scope);
            } catch (error) {
                console.warn('Service Worker registration note:', error.message);
            }
        }

        const perm = Notification.permission;
        this.updateUIStatus(perm);

        // Auto-start polling for real-time push updates if granted
        if (perm === 'granted') {
            this.startLocalPushPoller();
        }

        return true;
    },

    async requestPermission() {
        if (!('Notification' in window)) {
            if (typeof showToast === 'function') showToast('Push notifications not supported in this browser', 'error');
            return 'unsupported';
        }

        try {
            const permission = await Notification.requestPermission();
            this.updateUIStatus(permission);

            if (permission === 'granted') {
                if (typeof showToast === 'function') showToast('Web Push Notifications enabled successfully!', 'success');
                await this.subscribeDevice();
                this.startLocalPushPoller();
                
                // Show instant desktop notification and audio chime
                this.playNotificationSound();
                this.showDesktopNotification(
                    '🔔 Localhost Push Enabled!',
                    'You will now receive instant desktop push alerts for job updates, applications, and system alerts.',
                    this.getBaseUrl() + '/notifications.php'
                );
            } else if (permission === 'denied') {
                if (typeof showToast === 'function') showToast('Notification permission was blocked in browser settings', 'error');
            }

            return permission;
        } catch (error) {
            console.error('Error requesting notification permission:', error);
            if (typeof showToast === 'function') showToast('Failed to enable push notifications: ' + error.message, 'error');
            return 'error';
        }
    },

    async subscribeDevice() {
        const baseUrl = this.getBaseUrl();
        const endpoint = 'localhost-push-' + Math.random().toString(36).substring(2, 12) + '-' + Date.now();

        try {
            const response = await fetch(`${baseUrl}/api/push-notifications.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'subscribe',
                    endpoint: endpoint,
                    user_agent: navigator.userAgent
                })
            });

            const data = await response.json();
            if (data.success) {
                this.isSubscribed = true;
                const tokenEl = document.getElementById('bc-push-token-display');
                if (tokenEl) tokenEl.textContent = endpoint;
            }
        } catch (err) {
            console.warn('Push subscription network warning:', err);
        }
    },

    async sendTestPush() {
        const baseUrl = this.getBaseUrl();

        // Check or request permission if default
        if ('Notification' in window && Notification.permission === 'default') {
            const perm = await this.requestPermission();
            if (perm !== 'granted') return;
        }

        try {
            if (typeof showToast === 'function') showToast('Triggering test push notification...', 'info');

            const response = await fetch(`${baseUrl}/api/push-notifications.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'test_push'
                })
            });

            const data = await response.json();

            if (data.success && data.notification) {
                const notif = data.notification;
                
                // Play notification audio sound
                this.playNotificationSound();

                // Show in-app Toast
                if (typeof showToast === 'function') {
                    showToast(`<strong>${notif.title}</strong><br>${notif.message}`, 'success');
                }

                // Show Desktop Browser Push Notification
                this.showDesktopNotification(
                    notif.title || '🔔 Test Localhost Push Notification',
                    notif.message || 'BuildConnect Web Push is working seamlessly on localhost!',
                    baseUrl + '/' + (notif.link || 'notifications.php')
                );
            } else {
                if (typeof showToast === 'function') showToast(data.message || 'Failed to trigger test push', 'error');
            }
        } catch (error) {
            console.error('Test push error:', error);
            if (typeof showToast === 'function') showToast('Error triggering push: ' + error.message, 'error');
        }
    },

    showDesktopNotification(title, body, url) {
        const baseUrl = this.getBaseUrl();
        const icon = `${baseUrl}/assets/images/logo.png`;
        const targetUrl = url || `${baseUrl}/notifications.php`;

        if (this.swRegistration && this.swRegistration.showNotification) {
            try {
                this.swRegistration.showNotification(title, {
                    body: body,
                    icon: icon,
                    badge: icon,
                    vibrate: [100, 50, 100],
                    tag: 'bc-push-' + Date.now(),
                    data: { url: targetUrl }
                });
                return;
            } catch (e) {
                console.warn('SW showNotification fallback:', e);
            }
        }

        if ('Notification' in window && Notification.permission === 'granted') {
            try {
                const notif = new Notification(title, {
                    body: body,
                    icon: icon
                });
                notif.onclick = () => {
                    window.focus();
                    window.location.href = targetUrl;
                };
            } catch (e) {
                console.warn('HTML5 Notification error:', e);
            }
        }
    },

    playNotificationSound() {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const ctx = new AudioCtx();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type = 'sine';
            osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
            osc.frequency.setValueAtTime(880, ctx.currentTime + 0.1); // A5

            gain.gain.setValueAtTime(0.15, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);

            osc.connect(gain);
            gain.connect(ctx.destination);

            osc.start();
            osc.stop(ctx.currentTime + 0.35);
        } catch (e) {
            // Audio context policy fallback
        }
    },

    startLocalPushPoller() {
        if (this.pollInterval) clearInterval(this.pollInterval);

        // Check for new push items every 10 seconds on localhost
        this.pollInterval = setInterval(() => {
            this.checkNewUnreadPush();
        }, 10000);
    },

    async checkNewUnreadPush() {
        const baseUrl = this.getBaseUrl();
        try {
            const response = await fetch(`${baseUrl}/api/push-notifications.php?action=poll_unread&since=${this.lastCheckedTime}`);
            const data = await response.json();

            if (data.success && data.notifications && data.notifications.length > 0) {
                data.notifications.forEach(n => {
                    this.playNotificationSound();
                    if (typeof showToast === 'function') {
                        showToast(`<strong>${n.title}</strong><br>${n.message}`, 'info');
                    }
                    this.showDesktopNotification(
                        `🔔 ${n.title}`,
                        n.message,
                        baseUrl + '/' + (n.link || 'notifications.php')
                    );
                });
                this.lastCheckedTime = Math.floor(Date.now() / 1000);
            }
        } catch (err) {
            // Quiet polling network errors
        }
    },

    updateUIStatus(permission, statusText = null) {
        const badgeEl = document.getElementById('bc-push-status-badge');
        const descEl = document.getElementById('bc-push-status-desc');
        const enableBtn = document.getElementById('bc-enable-push-btn');
        const tokenEl = document.getElementById('bc-push-token-display');

        if (!badgeEl) return;

        if (permission === 'granted') {
            badgeEl.className = 'badge bg-success text-white py-1.5 px-3 rounded-pill fs-7';
            badgeEl.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i> Push Active (Localhost)';
            if (descEl) descEl.textContent = 'Browser Web Push notifications are active. You will receive instant desktop popups & audio alerts.';
            if (enableBtn) {
                enableBtn.className = 'btn btn-outline-success btn-sm me-2 fw-semibold disabled';
                enableBtn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Web Push Enabled';
            }
            if (tokenEl && tokenEl.textContent === 'localhost-push-active') {
                tokenEl.textContent = 'active-' + Math.random().toString(36).substring(2, 10);
            }
        } else if (permission === 'denied') {
            badgeEl.className = 'badge bg-danger text-white py-1.5 px-3 rounded-pill fs-7';
            badgeEl.innerHTML = '<i class="fa-solid fa-circle-xmark me-1"></i> Push Blocked';
            if (descEl) descEl.textContent = 'Notifications are blocked in browser settings. Click the lock icon 🔒 next to URL in browser address bar to allow Notifications.';
            if (enableBtn) {
                enableBtn.className = 'btn btn-outline-secondary btn-sm me-2 fw-semibold';
                enableBtn.innerHTML = '<i class="fa-solid fa-lock me-1"></i> Reset Browser Lock';
                enableBtn.onclick = () => alert('Notifications are currently blocked by browser security.\n\nTo unblock:\n1. Click the lock 🔒 or site settings icon in your browser address bar.\n2. Set Notifications to "Allow".\n3. Refresh this page.');
            }
        } else {
            badgeEl.className = 'badge bg-warning text-dark py-1.5 px-3 rounded-pill fs-7';
            badgeEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i> Action Required';
            if (descEl) descEl.textContent = statusText || 'Enable Web Push notifications to receive instant desktop alerts on localhost.';
            if (enableBtn) {
                enableBtn.className = 'btn btn-amber btn-sm me-2 fw-bold shadow-sm';
                enableBtn.innerHTML = '<i class="fa-solid fa-bell me-1"></i> Enable Localhost Push';
                enableBtn.onclick = () => BuildConnectPush.requestPermission();
            }
        }
    }
};

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', () => {
    BuildConnectPush.init();
});
