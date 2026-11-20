'use strict';
/* Sinhala display bindings */
const tr_offline_lock_js=(key,values={})=>globalThis.DharmaI18n?.t(key,values)??key.replace(/\{([0-9]+)\}/g,(m,k)=>Object.hasOwn(values,k)?String(values[k]):m);

// Login/explicit logout landing locks other offline tabs, without clearing pending records.
if (typeof BroadcastChannel === 'function') { const channel = new BroadcastChannel('dharma-offline-lock'); channel.postMessage('REAUTH'); channel.close(); }
