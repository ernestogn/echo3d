// sw.js - Service Worker DESACTIVADO (autodesinstalacion).
// Motivo: los caches del SW servian versiones viejas de la app aunque el
// usuario recargara. Ahora la app siempre se carga de la red; los assets
// llevan ?v=YYYYMMDD para invalidar el cache HTTP normal del navegador.
// Cualquier SW viejo ya instalado: al chequear este archivo, borra TODOS los
// caches y se desregistra solo. Sin fetch handler: no intercepta nada.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.map((k) => caches.delete(k))))
      .then(() => self.registration.unregister())
  );
});
