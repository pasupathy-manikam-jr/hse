// HSE service worker: lets the report form open without signal. Reports made offline are
// queued in IndexedDB by the page itself (resources/js/lib/offline-queue.ts), not here.
const CACHE = 'hse-v1';

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((k) => k !== CACHE)
                        .map((k) => caches.delete(k)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // Built assets have content hashes in their names: serve from cache once fetched.
    if (url.pathname.includes('/build/')) {
        event.respondWith(
            caches.open(CACHE).then(async (cache) => {
                const cached = await cache.match(request);

                if (cached) {
                    return cached;
                }

                const response = await fetch(request);

                if (response.ok) {
                    cache.put(request, response.clone());
                }

                return response;
            }),
        );

        return;
    }

    // The report page: always the network when there is one, else the last copy seen.
    if (request.mode === 'navigate' && url.pathname.endsWith('/report')) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        caches
                            .open(CACHE)
                            .then((cache) => cache.put(request, copy));
                    }

                    return response;
                })
                .catch(() =>
                    caches
                        .match(request)
                        .then((cached) => cached ?? Response.error()),
                ),
        );
    }
});
