/* Service worker EPSV — notifications Web Push de la messagerie. */

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch (error) {
        data = { body: event.data ? event.data.text() : '' };
    }

    const title = data.title || 'EPSV — Messagerie';
    const options = {
        body: data.body || 'Nouveau message',
        icon: '/favicon.ico',
        badge: '/favicon.ico',
        tag: data.tag || 'epsv-chat',
        renotify: true,
        data: {
            url: data.url || '/chat',
        },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = event.notification.data?.url || '/chat';

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

        for (const client of windows) {
            if (client.url.includes(targetUrl) && 'focus' in client) {
                return client.focus();
            }
        }

        if (self.clients.openWindow) {
            return self.clients.openWindow(targetUrl);
        }

        return null;
    })());
});
