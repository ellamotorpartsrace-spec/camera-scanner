const CACHE_NAME = 'ella-scanner-v2'; // Bumped version to force cache clear
const ASSETS = [
  'css/bootstrap-5.3.8-dist/css/bootstrap.min.css',
  'icon-512.png',
  'manifest.json'
];

self.addEventListener('install', (event) => {
  self.skipWaiting(); // Force the new service worker to activate immediately
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS);
    })
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName); // Clear old caches
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  // Network-First Strategy for all requests to ensure dashboard stats are always live
  event.respondWith(
    fetch(event.request)
      .then((networkResponse) => {
        return caches.open(CACHE_NAME).then((cache) => {
          // Don't cache API POST requests
          if (event.request.method === 'GET') {
            cache.put(event.request, networkResponse.clone());
          }
          return networkResponse;
        });
      })
      .catch(() => {
        // Fallback to cache if completely offline
        return caches.match(event.request);
      })
  );
});
