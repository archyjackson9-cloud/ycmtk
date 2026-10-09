// CY-Market service worker.
//
// Deliberately conservative: this is a live storefront where stock and
// prices change constantly, checkout/cart/account pages carry CSRF state,
// and the whole admin panel runs on Livewire - caching any of that would
// risk showing stale data or breaking a form submit. So the only things
// this worker ever caches are static, hash-versioned, or truly safe to
// reuse: built CSS/JS (/build/*, filenames already change per deploy),
// uploaded media (/storage/*), the app icons, and one offline fallback
// page. Everything else (every /admin request, every POST, every other
// page) is passed straight through to the network, untouched.
const CACHE_VERSION = 'cymarket-v1';
const OFFLINE_URL = '/offline.html';
const PRECACHE_URLS = [OFFLINE_URL, '/icons/icon-192.png', '/icons/icon-512.png'];

const CACHEABLE_PREFIXES = ['/build/', '/storage/', '/icons/'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_VERSION)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_VERSION).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

function isCacheableAsset(url) {
    return CACHEABLE_PREFIXES.some((prefix) => url.pathname.startsWith(prefix));
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Only ever act on same-origin GET requests - everything else (POSTs,
    // cross-origin calls, webhooks) goes straight to the network.
    if (request.method !== 'GET' || new URL(request.url).origin !== self.location.origin) {
        return;
    }

    const url = new URL(request.url);

    // Page navigations: always try the network first (so stock/prices/cart
    // state are current), and only fall back to the offline page when the
    // network is genuinely unreachable.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL))
        );
        return;
    }

    // Static, hash-versioned or upload assets: stale-while-revalidate -
    // serve the cached copy instantly if there is one, and refresh the
    // cache in the background for next time.
    if (isCacheableAsset(url)) {
        event.respondWith(
            caches.open(CACHE_VERSION).then((cache) => cache.match(request).then((cached) => {
                const network = fetch(request).then((response) => {
                    if (response.ok) {
                        cache.put(request, response.clone());
                    }

                    return response;
                }).catch(() => cached);

                return cached || network;
            }))
        );
    }

    // Anything else (admin panel pages, account/order pages, JSON, etc.)
    // is left completely untouched - the browser handles it normally.
});
