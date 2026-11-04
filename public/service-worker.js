const CACHE_NAME = 'him-shell-v2';
const APP_SHELL = '/';
const BUILD_PREFIX = '/build/';

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(CACHE_NAME);

        // Pre-cache the HTML shell when the server is available. Installation
        // should still succeed when the app is first opened without a network.
        try {
            await cache.add(new Request(APP_SHELL, { cache: 'reload' }));
        } catch {
            // The navigation handler will cache the shell on the next visit.
        }

        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(
            keys
                .filter((key) => key.startsWith('him-shell-') && key !== CACHE_NAME)
                .map((key) => caches.delete(key))
        );
        await self.clients.claim();
    })());
});

/**
 * Cache a successful response without Response.clone().
 *
 * The response body is read once and used to build two independent Response
 * objects. This avoids clone-on-consumed-body failures and keeps cache-write
 * errors from preventing the browser from receiving the network response.
 */
async function cacheResponse(request, response, cacheKey = request) {
    if (!response || !response.ok || response.type === 'opaque') {
        return response;
    }

    const body = await response.arrayBuffer();
    const responseInit = {
        status: response.status,
        statusText: response.statusText,
        headers: new Headers(response.headers),
    };

    const clientResponse = new Response(body.slice(0), responseInit);
    const cachedResponse = new Response(body.slice(0), responseInit);

    try {
        const cache = await caches.open(CACHE_NAME);
        await cache.put(cacheKey, cachedResponse);
    } catch {
        // Cache storage can be unavailable or full. Do not fail the request.
    }

    return clientResponse;
}

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (
        request.method !== 'GET' ||
        url.origin !== self.location.origin ||
        url.pathname.startsWith('/api/')
    ) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith((async () => {
            try {
                const response = await fetch(request);

                if (!response.ok) {
                    return response;
                }

                return await cacheResponse(request, response, APP_SHELL);
            } catch {
                const cachedShell = await caches.match(APP_SHELL);

                return cachedShell || new Response(
                    'The health center application is unavailable offline. Reconnect and try again.',
                    {
                        status: 503,
                        headers: { 'Content-Type': 'text/plain; charset=utf-8' },
                    }
                );
            }
        })());

        return;
    }

    if (url.pathname.startsWith(BUILD_PREFIX)) {
        event.respondWith((async () => {
            const cachedAsset = await caches.match(request);

            if (cachedAsset) {
                return cachedAsset;
            }

            try {
                const response = await fetch(request);
                return await cacheResponse(request, response);
            } catch {
                return await caches.match(request) || new Response('', { status: 504 });
            }
        })());
    }
});
