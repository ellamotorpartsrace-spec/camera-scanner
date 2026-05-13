const CACHE_NAME = 'ella-scanner-v1';
const ASSETS = [
  'index.php',
  'css/bootstrap-5.3.8-dist/css/bootstrap.min.css',
  'icon-512.png',
  'manifest.json'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS);
    })
  );
});

self.addEventListener('fetch', (event) => {
  event.respondWith(
    caches.match(event.request).then((response) => {
      return response || fetch(event.request);
    })
  );
});
