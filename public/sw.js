const CACHE_PREFIX = 'our-couple-public-';
const CACHE_NAME = `${CACHE_PREFIX}v1`;
const OFFLINE_PATH = '/offline-v1.html';
const STATIC_PATHS = [OFFLINE_PATH, '/icons/heart-v1-192.png', '/icons/heart-v1-512.png', '/icons/heart-v1-180.png'];
const MAXIMUM_ASSETS = 50;

function warn(code) {
    console.warn('pwa.worker', code);
}

function isPublicAsset(url) {
    if (url.origin !== self.location.origin || url.search !== '') { return false; }
    return STATIC_PATHS.includes(url.pathname) || /^\/build\/assets\/[A-Za-z0-9_-]+-[A-Za-z0-9_-]{8,}\.(css|js)$/.test(url.pathname);
}

function isCacheable(response, pathname) {
    if (!response.ok || response.redirected || response.type === 'opaque') { return false; }
    if (/private|no-store/i.test(response.headers.get('Cache-Control') || '')) { return false; }
    const type = response.headers.get('Content-Type') || '';
    if (pathname.endsWith('.js')) { return /javascript/i.test(type); }
    if (pathname.endsWith('.css')) { return type.startsWith('text/css'); }
    if (pathname.endsWith('.png')) { return type.startsWith('image/png'); }
    return pathname === OFFLINE_PATH && type.startsWith('text/html');
}

async function storePublicAsset(cache, path, response) {
    await cache.put(path, response);
    const keys = await cache.keys();
    const assets = keys.filter((key) => !STATIC_PATHS.includes(new URL(key.url).pathname));
    for (const key of assets.slice(0, Math.max(0, assets.length - MAXIMUM_ASSETS))) {
        await cache.delete(key);
    }
}

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(CACHE_NAME);
        for (const path of STATIC_PATHS) {
            const response = await fetch(new Request(new URL(path, self.location.origin), { credentials: 'omit', cache: 'reload' }));
            if (!isCacheable(response, path)) { throw new Error('public_asset_unavailable'); }
            await cache.put(path, response);
        }
        await self.skipWaiting();
    })().catch((error) => { warn('install_failed'); throw error; }));
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        for (const key of await caches.keys()) {
            if (key.startsWith(CACHE_PREFIX) && key !== CACHE_NAME) { await caches.delete(key); }
        }
        await self.clients.claim();
    })().catch((error) => { warn('activate_failed'); throw error; }));
});

async function publicAsset(request) {
    const url = new URL(request.url);
    let cache = null;
    try {
        cache = await caches.open(CACHE_NAME);
        const cached = await cache.match(url.pathname);
        if (cached) { return cached; }
    } catch { warn('cache_read_failed'); }
    const response = await fetch(new Request(request.url, { credentials: 'omit' }));
    if (cache && isCacheable(response, url.pathname)) {
        try { await storePublicAsset(cache, url.pathname, response.clone()); }
        catch { warn('cache_write_failed'); }
    }
    return response;
}

async function navigation(request) {
    try { return await fetch(request); }
    catch {
        let fallback = null;
        try { fallback = await (await caches.open(CACHE_NAME)).match(OFFLINE_PATH); }
        catch { warn('offline_cache_read_failed'); }
        const html = fallback ? await fallback.text() : '<html lang="ru"><title>Нет соединения</title><h1>Нет соединения</h1><p>Действия требуют интернета. Проверьте результат после подключения.</p><a href="/">Проверить соединение</a></html>';
        return new Response(html, { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' } });
    }
}

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) { return; }
    if (request.mode === 'navigate') { event.respondWith(navigation(request)); return; }
    if (isPublicAsset(url)) { event.respondWith(publicAsset(request)); }
});
