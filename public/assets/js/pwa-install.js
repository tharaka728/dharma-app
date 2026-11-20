'use strict';
/* Sinhala display bindings */
const tr_pwa_install_js=(key,values={})=>globalThis.DharmaI18n?.t(key,values)??key.replace(/\{([0-9]+)\}/g,(m,k)=>Object.hasOwn(values,k)?String(values[k]):m);

// Install only the existing public-static service worker. No session/vault data.
if ('serviceWorker' in navigator && window.isSecureContext) {
  const root = new URL('../../', document.currentScript.src);
  navigator.serviceWorker.register(new URL('sw.js', root).href, {scope: root.pathname})
    .catch(() => { /* Online access remains usable; offline setup reports its own errors. */ });
}
