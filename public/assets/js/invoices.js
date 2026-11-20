'use strict';
/* Sinhala display bindings */
const tr_invoices_js=(key,values={})=>globalThis.DharmaI18n?.t(key,values)??key.replace(/\{([0-9]+)\}/g,(m,k)=>Object.hasOwn(values,k)?String(values[k]):m);

function filterInvoiceOptions(search, select) {
  const value = search.value.trim().toLocaleLowerCase();
  for (const option of select.options) option.hidden = !!option.value && !option.selected && !option.textContent.toLocaleLowerCase().includes(value);
}
function connectInvoiceForm(document) {
  const form = document.getElementById('invoice-form');
  if (!form) return;
  const lines = document.getElementById('invoice-lines');
  let index = Math.max(0, ...Array.from(lines.querySelectorAll('select')).map(s => Number(s.name.match(/\[(\d+)\]/)?.[1] || 0))) + 1;
  const filter = filterInvoiceOptions;
  document.getElementById('add-invoice-line').addEventListener('click', event => {
    event.preventDefault();
    if (lines.children.length >= 100) return;
    const template = document.getElementById('invoice-line-template');
    const fragment = template.content.cloneNode(true);
    for (const node of fragment.querySelectorAll('[id],[name]')) {
      for (const attr of ['id', 'name']) if (node.hasAttribute(attr)) node.setAttribute(attr, node.getAttribute(attr).replaceAll('__INDEX__', String(index)));
    }
    for (const label of fragment.querySelectorAll('[for]')) label.htmlFor = label.htmlFor.replaceAll('__INDEX__', String(index));
    index++; lines.append(fragment); lines.lastElementChild.querySelector('input').focus();
  });
  lines.addEventListener('click', event => { if (event.target.classList.contains('remove-invoice-line') && lines.children.length > 1) event.target.closest('fieldset').remove(); });
  lines.addEventListener('input', event => { if (event.target.classList.contains('product-search')) filter(event.target, event.target.closest('fieldset').querySelector('select')); });
  const customerSearch = document.getElementById('customer-search');
  customerSearch.addEventListener('input', () => filter(customerSearch, document.getElementById('customer_id')));
  const source = document.getElementById('stock_source');
  if (source) source.addEventListener('change', () => { const button = form.querySelector('button[value="source"]'); form.requestSubmit(button); });
  form.addEventListener('submit', event => {
    if (event.submitter?.value !== 'issue') return;
    event.submitter.disabled = true;
    // Preserve the intended action when the submit button is disabled.
    const intent = document.createElement('input'); intent.type = 'hidden'; intent.name = 'intent'; intent.value = 'issue'; form.append(intent);
  });
}
if (typeof document !== 'undefined') connectInvoiceForm(document);
if (typeof module !== 'undefined') module.exports = {filterInvoiceOptions, connectInvoiceForm};
