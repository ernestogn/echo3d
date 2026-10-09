// sw.js - Service Worker SRCI
// Estrategia: network-first para recursos propios (siempre trae lo ultimo),
// con fallback a cache cuando no hay conexion (soporte offline basico).

const CACHE = 'srci-v2';

const APP_SHELL = [
  '/srci/index.php',
  '/srci/assets/css/estilos.css',
  '/srci/assets/js/mapa.js',
  '/srci/assets/js/reporte.js',
  '/srci/assets/js/login.js',
];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE).then((c) => c.addAll(APP_SHELL)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);

  // No interceptar CDN / tiles / fuentes externas
  if (url.origin !== self.location.origin) return;

  // La API siempre va a la red (datos vivos)
  if (url.pathname.startsWith('/srci/api/')) return;

  // Network-first: intenta la red, cachea la respuesta y actualiza el cache.
  // Si falla (offline), usa lo cacheado.
  e.respondWith(
    fetch(req)
      .then((res) => {
        const copia = res.clone();
        caches.open(CACHE).then((c) => c.put(req, copia));
        return res;
      })
      .catch(() => caches.match(req))
  );
});
