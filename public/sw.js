'use strict';
// Static allowlist only. Authenticated HTML, APIs, sessions and sales never enter Cache Storage.
const VERSION = 'dharma-static-ui-v14';
const ROOT = new URL('./', self.registration.scope);
const FILES = ['assets/i18n/catalogue.js?v=si-v2','assets/js/localization.js?v=si-v3','assets/css/localization.css?v=si-v2','offline/index.html','assets/vendor/bootstrap/bootstrap.min.css','assets/css/app.css','assets/css/workspace.css?v=ui-refresh-9','assets/css/pos.css?v=pos-ui-v7','assets/js/pos.js?v=pos-checkout-v5','assets/js/workspace.js','assets/js/offline-domain.js?v=si-v2','assets/js/offline-store.js','assets/js/offline-app.js?v=si-v2','manifest.webmanifest','assets/icons/app-192.png','assets/icons/app-512.png'];
const ALLOWED = new Set(FILES.map(path => new URL(path, ROOT).href));
self.addEventListener('install', event => event.waitUntil(caches.open(VERSION).then(cache => cache.addAll([...ALLOWED]))));
// Never skipWaiting: an update waits for existing tabs to close. IndexedDB is never erased here.
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', event => {
  const request = event.request;
  if (request.method !== 'GET') return;
  const url = new URL(request.url);
  if (url.origin !== ROOT.origin) return;
  if (ALLOWED.has(url.href)) {
    event.respondWith(caches.open(VERSION).then(async cache => (await cache.match(url.href)) || fetch(request)));
    return;
  }
  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(() => Response.redirect(new URL('offline/index.html', ROOT).href, 302)));
  }
});
