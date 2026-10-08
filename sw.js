/**
 * BuildConnect Web Push Notification Service Worker
 * Handlers for Push Events and Desktop Notification Click Actions on Localhost
 */

const CACHE_NAME = 'buildconnect-push-v1';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

// Handle Push Event from Push Server / Backend
self.addEventListener('push', (event) => {
    let payload = {
        title: 'BuildConnect Notification',
        body: 'You have a new update in BuildConnect.',
        icon: '/assets/images/logo.png',
        badge: '/assets/images/logo.png',
        url: '/notifications.php',
        tag: 'bc-notif-' + Date.now(),
        data: {}
    };

    if (event.data) {
        try {
            const data = event.data.json();
            payload = { ...payload, ...data };
        } catch (e) {
            payload.body = event.data.text();
        }
    }

    const options = {
        body: payload.body,
        icon: payload.icon || '/assets/images/logo.png',
        badge: payload.badge || '/assets/images/logo.png',
        vibrate: [100, 50, 100],
        tag: payload.tag || 'bc-push-notification',
        data: {
            url: payload.url || '/notifications.php',
            timestamp: Date.now()
        },
        actions: [
            { action: 'open', title: 'View Notification' },
            { action: 'close', title: 'Dismiss' }
        ]
    };

    event.waitUntil(
        self.registration.showNotification(payload.title, options)
    );
});

// Handle Notification Click
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    if (event.action === 'close') {
        return;
    }

    const targetUrl = event.notification.data ? event.notification.data.url : '/notifications.php';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if (client.url.includes(targetUrl) && 'focus' in client) {
                    return client.focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});
