/*
 * Heartwise service worker: makes the site installable as an app and keeps
 * a friendly page for when the phone is offline. Pages and private data are
 * always loaded fresh from the server (never cached), so nobody sees stale
 * or someone else's information.
 */
const CACHE = 'heartwise-v1';
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
