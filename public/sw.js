/*
 * UDTRMS service worker — offline page ONLY.
 *
 * What it does: when a full page load (a "navigation") fails because the
 * device has no connection, it answers with a branded offline page instead
 * of the browser's own "No internet" screen.
 *
 * What it deliberately does NOT do: cache the app's pages, scripts, API
 * responses or data. Every request still goes straight to the network as
 * normal, so a deploy is picked up immediately and nobody can get stuck on
 * an outdated version. Server responses (including 404/500/503 pages) are
 * always passed through untouched — only a *network failure* triggers the
 * offline page.
 *
 * Updating: the offline page's files refresh themselves in the background
 * (see refreshOfflineAssets); bump CACHE_VERSION only to force every device
 * to re-download them immediately.
 * Removing: replace this file with one that calls self.registration.unregister().
 */
const CACHE_VERSION = 'udtrms-offline-v1';
const OFFLINE_URL   = '/offline.html';

// Everything the offline page itself needs, available with no connection.
const OFFLINE_ASSETS = [OFFLINE_URL, '/offline.js', '/theme-init.js', '/nemsu.png'];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION)
      .then((cache) => cache.addAll(OFFLINE_ASSETS.map((url) => new Request(url, { cache: 'reload' }))))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_VERSION).map((key) => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

// Keep the cached offline page current without needing a CACHE_VERSION bump
// for every wording or style tweak: after a successful page load (i.e. we're
// online), re-download its few small files in the background, at most once
// every few hours. Failures are ignored — the existing copy stays.
const REFRESH_EVERY_MS = 6 * 60 * 60 * 1000;
let lastRefresh = 0;

async function refreshOfflineAssets() {
  if (Date.now() - lastRefresh < REFRESH_EVERY_MS) return;
  lastRefresh = Date.now();
  try {
    const cache = await caches.open(CACHE_VERSION);
    await cache.addAll(OFFLINE_ASSETS.map((url) => new Request(url, { cache: 'reload' })));
  } catch {
    // Offline again or a file failed — keep what's cached.
  }
}

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') return;

  // Full page loads: network as normal; offline page only if the network fails.
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          event.waitUntil(refreshOfflineAssets());
          return response;
        })
        .catch(() => caches.match(OFFLINE_URL))
    );
    return;
  }

  // The offline page's own files (logo, theme script): network as normal,
  // cached copy only as a fallback so the offline page can still render.
  const url = new URL(request.url);
  if (url.origin === self.location.origin && OFFLINE_ASSETS.includes(url.pathname)) {
    event.respondWith(
      fetch(request).catch(() => caches.match(url.pathname))
    );
  }
  // Everything else is not intercepted at all.
});
