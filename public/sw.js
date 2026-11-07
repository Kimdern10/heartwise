/*
 * Heartwise service worker: makes the site installable as an app and keeps
 * a friendly page for when the phone is offline. Pages and private data are
 * always loaded fresh from the server (never cached), so nobody sees stale
 * or someone else's information.
 */
const CACHE = 'heartwise-v2';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll([OFFLINE_URL, '/icons/icon-192.png'])));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Only page navigations get the offline fallback; everything else goes straight to the network.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
    }
});

/* Phone notifications (Web Push): show them, and open the right page when tapped. */
self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: 'Heartwise', body: event.data ? event.data.text() : '' };
    }

    event.waitUntil(
        self.registration.showNotification(data.title || 'Heartwise', {
            body: data.body || '',
            icon: data.icon || '/icons/icon-192.png',
            badge: '/icons/icon-192.png',
            tag: data.tag,
            renotify: !!data.tag,
            data: { url: (data.data && data.data.url) || '/dashboard' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/dashboard';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            for (const client of windows) {
                if ('focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }
            return self.clients.openWindow(url);
        }),
    );
});
