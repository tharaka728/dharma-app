(function () {
  'use strict';
  const script = document.currentScript;
  if ((script?.dataset.hybridLocale || document.documentElement.lang) !== 'si-LK') return;
  const root = document.querySelector('#hybrid-invoice-form, #hybrid-return-form')?.closest('main');
  if (!root || !script) return;
  fetch(new URL('../i18n/hybrid-si-LK.json', script.src), {credentials: 'omit', cache: 'no-cache'})
    .then(r => { if (!r.ok) throw Error('Catalogue unavailable'); return r.json(); })
    .then(scoped => {
      const dictionary = {...(globalThis.DharmaTranslations || {}), ...scoped};
      const patterns = Object.entries(dictionary).filter(([key]) => key.includes('{0}')).map(([key, value]) => [new RegExp('^' + key.split(/(\{\d+\})/).map(s => /^\{\d+\}$/.test(s) ? '(.+?)' : s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('') + '$', 'u'), value]);
      const translate = value => {
        const key = value.trim(); let text = dictionary[key];
        if (!text) for (const [pattern, template] of patterns) { const match = key.match(pattern); if (match) { text = template.replace(/\{(\d+)\}/g, (_, n) => match[Number(n) + 1]); break; } }
        return text ? value.replace(key, text) : value;
      };
      function visit(node) {
        if (node.nodeType === Node.TEXT_NODE) {
          if (!node.parentElement?.closest('script,style,pre,code,input,textarea,[data-no-translate]')) { const text = translate(node.nodeValue); if (text !== node.nodeValue) node.nodeValue = text; }
          return;
        }
        if (node.nodeType !== Node.ELEMENT_NODE) return;
        // An option without an explicit value derives its protocol value from its text.
        if (node.tagName === 'OPTION' && !node.hasAttribute('value')) node.setAttribute('value', node.value);
        for (const name of ['aria-label', 'title', 'placeholder']) if (node.hasAttribute(name)) { const value = node.getAttribute(name), text = translate(value); if (text !== value) node.setAttribute(name, text); }
        for (const child of [...node.childNodes]) visit(child);
      }
      visit(root);
      new MutationObserver(records => { for (const r of records) { if (r.type === 'characterData') visit(r.target); for (const node of r.addedNodes) visit(node); } }).observe(root, {childList: true, subtree: true, characterData: true});
    }).catch(() => { /* Readable English fallback; never change financial intent or queue an action. */ });
})();
