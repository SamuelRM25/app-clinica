/**
 * sw.js - Service Worker
 * ClinicApp - PWA
 *
 * Estrategia de cache:
 *  - Stale-while-revalidate para estáticos (CSS, JS, imágenes, HTML)
 *  - Network-first con fallback a cache para APIs (no guardar respuestas)
 *  - Auto-actualización silenciosa: skipWaiting() + clients.claim() + postMessage
 */

const CACHE_NAME = 'clinicapp-v1.0.0';

const STATIC_ASSETS = [
    '/',
    '/manifest.json',
    '/assets/img/cmrs.png',
    '/assets/img/icon-192.png',
    '/assets/img/icon-512.png',
    '/assets/css/global_dashboard.css',
    '/assets/css/print_thermal.css',
    '/assets/js/install-prompt.js'
];

/* ===========================
   INSTALL: pre-cachear assets críticos
   =========================== */
self.addEventListener('install', (event) => {
    console.log('[SW] Installing version:', CACHE_NAME);
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[SW] Pre-caching static assets');
                return cache.addAll(STATIC_ASSETS).catch((err) => {
                    // Algunos assets pueden no existir en el primer install — log y continuar
                    console.warn('[SW] Algunos assets no se pudieron pre-cachear:', err);
                });
            })
    );
    self.skipWaiting();
});

/* ===========================
   ACTIVATE: limpiar caches antiguos
   =========================== */
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating version:', CACHE_NAME);
    event.waitUntil(
        caches.keys()
            .then((keys) => {
                return Promise.all(
                    keys.filter((key) => key !== CACHE_NAME)
                        .map((key) => {
                            console.log('[SW] Eliminando cache antiguo:', key);
                            return caches.delete(key);
                        })
                );
            })
            .then(() => self.clients.claim())
            .then(() => {
                // Notificar a todas las pestañas abiertas que el SW se actualizó
                return self.clients.matchAll({ type: 'window' });
            })
            .then((clients) => {
                clients.forEach((client) => {
                    client.postMessage({
                        type: 'SW_UPDATED',
                        version: CACHE_NAME
                    });
                });
            })
    );
});

/* ===========================
   FETCH: servir desde cache, actualizar en background
   =========================== */
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Solo GET
    if (request.method !== 'GET') return;

    // Ignorar requests fuera de scope
    if (url.origin !== location.origin) return;

    // Ignorar chrome-extension y otros protocolos no-http(s)
    if (!url.protocol.startsWith('http')) return;

    // Network-first para endpoints dinámicos (no cachear)
    if (
        url.pathname.includes('/api/') ||
        url.pathname.includes('save_') ||
        url.pathname.includes('update_') ||
        url.pathname.includes('delete_') ||
        url.pathname.includes('process_') ||
        url.pathname.includes('balance_') ||
        url.pathname.includes('autocomplete_') ||
        url.searchParams.has('id') ||           // URLs con query params dinámicos
        url.searchParams.has('id_paciente') ||
        url.pathname.endsWith('.php') && request.headers.get('X-Requested-With') === 'fetch'
    ) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    // Solo cachear respuestas exitosas y de tipo basic
                    if (response && response.status === 200 && response.type === 'basic') {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                    }
                    return response;
                })
                .catch(() => {
                    // Si falla la red, intentar desde cache (offline fallback)
                    return caches.match(request);
                })
        );
        return;
    }

    // Stale-while-revalidate para el resto (HTML, CSS, JS, imágenes)
    event.respondWith(
        caches.match(request).then((cached) => {
            const networkFetch = fetch(request)
                .then((response) => {
                    // Solo cachear respuestas válidas
                    if (response && response.status === 200 && response.type === 'basic') {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                    }
                    return response;
                })
                .catch(() => cached);  // Si no hay red, devolver el cache

            return cached || networkFetch;
        })
    );
});

/* ===========================
   MESSAGE: comunicación con clientes
   =========================== */
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        console.log('[SW] Skip waiting solicitado por cliente');
        self.skipWaiting();
    }

    if (event.data && event.data.type === 'CLEAR_CACHE') {
        event.waitUntil(
            caches.keys().then((keys) =>
                Promise.all(keys.map((key) => caches.delete(key)))
            ).then(() => {
                if (event.source) {
                    event.source.postMessage({ type: 'CACHE_CLEARED' });
                }
            })
        );
    }
});
