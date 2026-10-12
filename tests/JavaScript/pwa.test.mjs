import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { it } from 'node:test';
import vm from 'node:vm';

const source = await readFile(new URL('../../public/sw.js', import.meta.url), 'utf8');
const origin = 'https://couple.example';

function worker() {
    const handlers = new Map();
    const stores = new Map();
    const requests = [];
    let network = async () => new Response('public', { headers: { 'Content-Type': 'text/javascript' } });
    let claimed = false;
    let skipped = false;
    const caches = {
        async open(name) {
            if (!stores.has(name)) { stores.set(name, new Map()); }
            const store = stores.get(name);
            const key = (request) => new URL(typeof request === 'string' ? request : request.url, origin).href;
            return {
                async put(request, response) { store.set(key(request), response.clone()); },
                async match(request) { return store.get(key(request))?.clone(); },
                async keys() { return [...store.keys()].map((url) => new Request(url)); },
                async delete(request) { return store.delete(key(request)); },
            };
        },
        async keys() { return [...stores.keys()]; },
        async delete(name) { return stores.delete(name); },
    };
    vm.runInNewContext(source, {
        Request, Response, URL, caches,
        console: { warn() {} },
        fetch: async (request) => { requests.push(request); return network(request); },
        self: {
            location: { origin },
            addEventListener(name, handler) { handlers.set(name, handler); },
            async skipWaiting() { skipped = true; },
            clients: { async claim() { claimed = true; } },
        },
    });
    return {
        stores, requests, caches,
        setNetwork(callback) { network = callback; },
        async lifecycle(name) {
            let promise;
            handlers.get(name)({ waitUntil(value) { promise = value; } });
            await promise;
        },
        dispatch(path, { method = 'GET', mode = 'cors', headers = {} } = {}) {
            let response;
            const request = new Request(new URL(path, origin), { method, headers });
            Object.defineProperty(request, 'mode', { value: mode });
            handlers.get('fetch')({ request, respondWith(value) { response = value; } });
            return response;
        },
        get claimed() { return claimed; },
        get skipped() { return skipped; },
    };
}

it('failed personal navigation returns a 503 without storing private pages', async () => {
    const app = worker();
    app.setNetwork(async () => { throw new Error('offline'); });
    const response = await app.dispatch('/profile', { mode: 'navigate' });
    assert.equal(response.status, 503);
    assert.equal(response.headers.get('Cache-Control'), 'no-store');
    assert.match(await response.text(), /Нет соединения/);
    assert.equal([...app.stores.values()].flatMap((store) => [...store.keys()]).length, 0);
});

it('online personal HTML remains network-only and server errors are not replaced by success', async () => {
    const app = worker();
    app.setNetwork(async () => new Response('private balance', { status: 403 }));
    assert.equal((await app.dispatch('/hearts', { mode: 'navigate' })).status, 403);
    assert.equal(app.stores.size, 0);
});

it('private JSON photos mutations query strings and other origins are never intercepted', () => {
    const app = worker();
    for (const path of ['/api/balance', '/photos/requests/2', '/telegram/login/secret', '/build/assets/app-abcdefgh.js?private=1', 'https://other.example/build/assets/app-abcdefgh.js']) {
        assert.equal(app.dispatch(path), undefined);
    }
    for (const method of ['POST', 'PUT', 'PATCH', 'DELETE']) {
        assert.equal(app.dispatch('/couples/1/money', { method }), undefined);
    }
});

it('hashed public assets use an anonymous bounded cache and remain available offline', async () => {
    const app = worker();
    await app.dispatch('/build/assets/app-abcdefgh.js');
    assert.equal(app.requests[0].credentials, 'omit');
    app.setNetwork(async () => { throw new Error('offline'); });
    assert.equal(await (await app.dispatch('/build/assets/app-abcdefgh.js')).text(), 'public');
    assert.equal(app.requests.length, 1);
});

it('cache rejects private responses HTML redirects and failures at an allowed asset path', async () => {
    for (const response of [
        new Response('private', { headers: { 'Content-Type': 'text/javascript', 'Cache-Control': 'private' } }),
        new Response('private', { headers: { 'Content-Type': 'text/javascript', 'Cache-Control': 'no-store' } }),
        new Response('login', { headers: { 'Content-Type': 'text/html' } }),
        new Response('failed', { status: 500 }),
        new Response('redirect', { status: 302, headers: { Location: '/login' } }),
    ]) {
        const app = worker();
        app.setNetwork(async () => response);
        await app.dispatch('/build/assets/app-abcdefgh.js');
        assert.equal([...app.stores.values()][0].size, 0);
    }
});

it('installation caches only versioned public files and takes control without reloading forms', async () => {
    const app = worker();
    app.setNetwork(async (request) => new Response('public', { headers: { 'Content-Type': request.url.endsWith('.png') ? 'image/png' : 'text/html' } }));
    await app.lifecycle('install');
    assert.equal(app.skipped, true);
    assert.equal(app.requests.length, 4);
    assert.ok(app.requests.every((request) => request.credentials === 'omit'));
    assert.ok(app.requests.every((request) => /-v[23]/.test(request.url)));
    await app.caches.open('other-app-cache');
    await app.caches.open('our-couple-public-old');
    await app.lifecycle('activate');
    assert.equal(app.claimed, true);
    assert.equal(app.stores.has('our-couple-public-old'), false);
    assert.equal(app.stores.has('other-app-cache'), true);
});

it('failed installation does not activate an incomplete worker', async () => {
    const app = worker();
    app.setNetwork(async () => new Response('missing', { status: 404 }));
    await assert.rejects(app.lifecycle('install'));
    assert.equal(app.skipped, false);
});

it('asset eviction preserves the offline page and the most recent fifty files', async () => {
    const app = worker();
    app.setNetwork(async (request) => new Response('public', { headers: { 'Content-Type': request.url.endsWith('.png') ? 'image/png' : request.url.endsWith('.html') ? 'text/html' : 'text/javascript' } }));
    await app.lifecycle('install');
    for (let index = 0; index < 55; index++) { await app.dispatch(`/build/assets/app-abcdefgh${index}.js`); }
    const urls = [...app.stores.values()][0];
    assert.equal(urls.size, 54);
    assert.equal(urls.has(`${origin}/offline-v2.html`), true);
    assert.equal(urls.has(`${origin}/build/assets/app-abcdefgh0.js`), false);
    assert.equal(urls.has(`${origin}/build/assets/app-abcdefgh54.js`), true);
});

const moduleSource = (await readFile(new URL('../../resources/js/modules/pwa.js', import.meta.url), 'utf8')).replaceAll('export function', 'function').replaceAll('import.meta.env.DEV', 'false');

function page({ online = true, authenticated = false, standalone = false } = {}) {
    const events = new Map();
    const formEvents = new Map();
    const buttonEvents = new Map();
    let reloads = 0;
    const banner = { hidden: true, scrollIntoView() {}, focus() {} };
    const button = { hidden: true, disabled: false, addEventListener(name, handler) { buttonEvents.set(name, handler); } };
    const help = { hidden: false };
    const status = { textContent: '', dataset: { accepted: 'accepted', dismissed: 'dismissed', failed: 'failed' } };
    const body = { hidden: false, dataset: { authenticated: String(authenticated) } };
    const root = {
        body,
        querySelector(selector) { return { '[data-connection-status]': banner, '[data-pwa-help]': help, '[data-pwa-install]': button, '[data-pwa-status]': status }[selector]; },
        querySelectorAll() { return [{ addEventListener(name, handler) { formEvents.set(name, handler); } }]; },
    };
    const navigator = { onLine: online };
    const window = {
        addEventListener(name, handler) { events.set(name, handler); },
        matchMedia() { return { matches: standalone }; },
        location: { reload() { reloads++; } },
        isSecureContext: false,
    };
    vm.runInNewContext(`${moduleSource}\ninitializePwa(root);`, { root, navigator, window, console: { warn() {} } });
    return { events, formEvents, buttonEvents, banner, button, help, status, body, navigator, get reloads() { return reloads; } };
}

it('offline submission is blocked and can be submitted normally after reconnection', () => {
    const app = page({ online: false });
    assert.equal(app.banner.hidden, false);
    let prevented = false;
    app.formEvents.get('submit')({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    app.navigator.onLine = true;
    app.events.get('online')();
    assert.equal(app.banner.hidden, true);
    app.formEvents.get('submit')({ preventDefault() { assert.fail('online submission must proceed'); } });
});

it('authenticated back-cache pages stay hidden until the server checks the session again', () => {
    const app = page({ authenticated: true });
    app.events.get('pagehide')();
    assert.equal(app.body.hidden, true);
    app.events.get('pageshow')({ persisted: true });
    assert.equal(app.reloads, 1);
    assert.equal(app.body.hidden, true);
});

it('installation is user-triggered and dismissal does not claim successful installation', async () => {
    const app = page();
    let prompts = 0;
    app.events.get('beforeinstallprompt')({ preventDefault() {}, async prompt() { prompts++; }, userChoice: Promise.resolve({ outcome: 'dismissed' }) });
    assert.equal(prompts, 0);
    assert.equal(app.button.hidden, false);
    await app.buttonEvents.get('click')();
    assert.equal(prompts, 1);
    assert.equal(app.status.textContent, 'dismissed');
    assert.equal(app.button.hidden, true);
    assert.equal(app.button.disabled, false);
});

it('standalone layout hides installation help', () => {
    assert.equal(page({ standalone: true }).help.hidden, true);
});


it('unavailable cache storage preserves online assets and still explains a failed navigation', async () => {
    const app = worker();
    app.caches.open = async () => { throw new Error('storage unavailable'); };
    assert.equal(await (await app.dispatch('/build/assets/app-abcdefgh.js')).text(), 'public');
    app.setNetwork(async () => { throw new Error('offline'); });
    const response = await app.dispatch('/profile', { mode: 'navigate' });
    assert.equal(response.status, 503);
    assert.match(await response.text(), /Нет соединения/);
});
