const CACHE_NAME = 'ella-scanner-v12'; // v12: JS files excluded from cache so phones always get fresh code

const STATIC_ASSETS = [
  'css/bootstrap-5.3.8-dist/css/bootstrap.min.css',
  'icon-512.png',
  'manifest.json'
];

self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) =>
      Promise.all(
        cacheNames.map((name) => {
          if (name !== CACHE_NAME) return caches.delete(name);
        })
      )
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const url = event.request.url;

  // NEVER cache JS files, PHP files, or API calls — always fetch live
  if (
    url.includes('.js') ||
    url.includes('.php') ||
    url.includes('/api/')
  ) {
    event.respondWith(fetch(event.request));
    return;
  }

  // Network-first for everything else (CSS, images)
  event.respondWith(
    fetch(event.request, { cache: 'no-store' })
      .then((networkResponse) => {
        return caches.open(CACHE_NAME).then((cache) => {
          if (event.request.method === 'GET') {
            cache.put(event.request, networkResponse.clone());
          }
          return networkResponse;
        });
      })
      .catch(() => caches.match(event.request))
  );
});

