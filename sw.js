const CACHE_NAME = "kfz-pwa-v3";

const ASSETS = [
    "/kfz-pwa/",
    "/wp-content/plugins/fleet-pwa/manifest.json",
    "/wp-content/plugins/fleet-pwa/public/app.js",
    "/wp-content/plugins/fleet-pwa/public/db.js",
    "/wp-content/plugins/fleet-pwa/public/sync.js",
    "/wp-content/plugins/fleet-pwa/public/jsQR.js",
    "/wp-content/plugins/fleet-pwa/img/kfz-pwa192.png"
];

// INSTALL
self.addEventListener("install", event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.addAll(ASSETS))
    );
    self.skipWaiting();
});

// ACTIVATE
self.addEventListener("activate", event => {
    event.waitUntil(self.clients.claim());
});

// FETCH
self.addEventListener("fetch", event => {

    const req = event.request;
    const url = new URL(req.url);

    // ❌ API nicht cachen
    if (url.pathname.startsWith("/wp-json/")) return;

    // ✅ Navigation (App Shell)
    if (req.mode === "navigate") {
        event.respondWith(
            fetch(req).catch(() => caches.match("/kfz-pwa/"))
        );
        return;
    }

    // ✅ Assets (Cache first)
    event.respondWith(
        caches.match(req).then(res => {
            return res || fetch(req).then(fetchRes => {
                // optional: dynamisch cachen
                return caches.open(CACHE_NAME).then(cache => {
                    cache.put(req, fetchRes.clone());
                    return fetchRes;
                });
            });
        })
    );
});
