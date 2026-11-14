/*
 * Service worker de Onda.
 *
 * Hace dos cosas: que la app se pueda instalar y que funcione sin conexión.
 * Al instalarse guarda la pantalla del modo sin conexión (/offline) con sus
 * estilos, su script y las fuentes; cuando una página no se puede cargar por
 * falta de red, enseña esa pantalla, que reproduce las canciones guardadas
 * en el dispositivo.
 *
 * No guarda páginas con datos de la cuenta ni audio: el streaming (peticiones
 * por rangos) y todo lo demás va directo a la red.
 */
const VERSION = new URL(self.location.href).searchParams.get('v') || 'dev';
const SHELL = `onda-shell-${VERSION}`;
const OFFLINE_URL = '/offline';
const ASSET = /\/build\/assets\/[\w.-]+/g;

async function precache() {
    const cache = await caches.open(SHELL);
    const response = await fetch(OFFLINE_URL, { cache: 'reload', credentials: 'same-origin' });

    if (!response.ok) throw new Error(`No se pudo guardar la pantalla sin conexión (${response.status})`);

    const html = await response.clone().text();
    await cache.put(OFFLINE_URL, response);

    // Los archivos compilados llevan un hash en el nombre: se leen del propio HTML.
    const assets = new Set(['/favicon.svg', '/icons/icon-192.png', ...(html.match(ASSET) || [])]);

    await Promise.all([...assets].map((url) => cache.add(url).catch(() => {})));
}

self.addEventListener('install', (event) => {
    // Si falla (sin red en ese momento), el service worker se instala igual y lo reintenta al activarse otra versión.
    event.waitUntil(precache().catch(() => {}));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key.startsWith('onda-shell-') && key !== SHELL).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

function unavailable() {
    return new Response(
        '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sin conexión · Onda</title>' +
            '<body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#0d0e10;color:#edece8;font-family:system-ui,sans-serif;text-align:center;padding:24px">' +
            '<main><h1 style="font-size:22px;margin:0 0 8px">Sin conexión</h1><p style="margin:0 0 20px;opacity:.65">Onda necesita internet para cargar. Revisa tu conexión.</p>' +
            '<button onclick="location.reload()" style="padding:10px 22px;border:0;border-radius:999px;background:#edece8;color:#0d0e10;font:600 14px system-ui;cursor:pointer">Reintentar</button></main>',
        { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } },
    );
}

async function offlinePage() {
    return (await caches.match(OFFLINE_URL)) || unavailable();
}

async function cacheFirst(request) {
    const cached = await caches.match(request, { ignoreVary: true });
    if (cached) return cached;

    const response = await fetch(request);

    if (response.ok) {
        const cache = await caches.open(SHELL);
        cache.put(request, response.clone()).catch(() => {});
    }

    return response;
}

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Páginas: siempre de la red; sin red, el modo sin conexión.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(offlinePage));

        return;
    }

    // Archivos compilados e iconos: no cambian (llevan hash), así que se sirven del dispositivo.
    if (url.pathname.startsWith('/build/assets/') || url.pathname.startsWith('/icons/') || url.pathname === '/favicon.svg') {
        event.respondWith(cacheFirst(request));
    }
});
