/* SUPERMM SYSO · Service Worker
 * Estrategia conservadora para una app con sesión:
 *  - Solo intercepta GET (nunca POST/PUT/DELETE).
 *  - Navegaciones (páginas): red primero; si no hay conexión, muestra offline.html.
 *    NO se cachea HTML, para no servir páginas viejas o de sesión cerrada.
 *  - Estáticos (css/js/imágenes/fuentes): cache-first con actualización en segundo plano.
 * Sube CACHE_VERSION para forzar limpieza de cachés viejas.
 */
const CACHE_VERSION = 'syso-v1';
const PRECACHE = [
  './offline.html',
  './assets/pwa/icon-192.png',
  './assets/pwa/icon-512.png'
];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE_VERSION).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE_VERSION).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

function esEstatico(url) {
  return /\.(css|js|png|jpe?g|gif|svg|webp|ico|woff2?|ttf|eot)$/i.test(url.pathname);
}

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return; // deja pasar POST y demás a la red normal

  let url;
  try { url = new URL(req.url); } catch (_) { return; }

  // Páginas: red primero, respaldo offline. Sin cachear HTML.
  if (req.mode === 'navigate') {
    e.respondWith(
      fetch(req).catch(() => caches.match('./offline.html'))
    );
    return;
  }

  // Estáticos: responde de caché y actualiza por detrás (stale-while-revalidate)
  if (esEstatico(url)) {
    e.respondWith(
      caches.match(req).then((cached) => {
        const red = fetch(req).then((res) => {
          if (res && (res.ok || res.type === 'opaque')) {
            const copia = res.clone();
            caches.open(CACHE_VERSION).then((c) => c.put(req, copia)).catch(() => {});
          }
          return res;
        }).catch(() => cached);
        return cached || red;
      })
    );
  }
  // El resto pasa directo a la red (comportamiento por defecto).
});
