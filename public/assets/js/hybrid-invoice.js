'use strict';
/* Online UI only. All quantities, prices, stock decisions and money come from the server. */
const HYBRID_POLICY = 'HYBRID_PROMOTION_V1';
function hybridRate(value, maximum = 50) {
  if (typeof value !== 'string' || !/^(0|[1-9][0-9]{0,2})(\.[0-9]{1,2})?$/.test(value)) throw Error(`Enter a percentage from 0 to ${maximum}, with at most two decimal places.`);
  const [whole, fraction = ''] = value.split('.');
  if (BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0')) > BigInt(maximum) * 100n) throw Error(`Discount cannot exceed ${maximum}%. Reduce the selection; it was not changed automatically.`);
  return value;
}
function hybridSelection(choice, custom, replacement = false) {
  if (!['0', '25', '30', '35', 'other'].includes(choice) && !replacement) throw Error('Select a listed product discount.');
  if (replacement) {
    if (!['25', '30', '35', 'custom'].includes(choice)) throw Error('Explicitly select a replacement rate.');
    return choice === 'custom' ? {choice, percent: hybridRate(custom, 100)} : {choice};
  }
  return choice === 'other' ? {choice, percent: hybridRate(custom)} : {choice};
}
function hybridPayload(values) {
  const id = value => { if (typeof value !== 'string' || !/^[1-9][0-9]{0,18}$/.test(value)) throw Error('Select an available customer and product.'); return value; };
  if (!/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/.test(values.requestId)) throw Error('Refresh this page to obtain a valid request identity.');
  if (!['CASH', 'CREDIT'].includes(values.payment)) throw Error('Select cash or credit.');
  if (typeof values.received !== 'string' || !/^(0|[1-9][0-9]{0,12})(\.[0-9]{1,2})?$/.test(values.received)) throw Error('Enter nonnegative cash received in LKR, with at most two decimal places.');
  if (!Array.isArray(values.lines) || !values.lines.length || values.lines.length > 100) throw Error('Add 1–100 different products.');
  const seen = new Set();
  const lines = values.lines.map(line => {
    const product = id(line.productId);
    if (seen.has(product)) throw Error('Use one aggregate line per product.'); seen.add(product);
    if (typeof line.quantity !== 'string' || !/^[1-9][0-9]{0,18}$/.test(line.quantity) || BigInt(line.quantity) > 9223372036854775807n) throw Error('Paid quantity must be a positive whole number within the supported range.');
    const result = {product_id: product, paid_quantity: line.quantity, product_discount: hybridSelection(line.choice, line.custom)};
    if (line.replace === true) result.replace_free_entitlement = true;
    return result;
  });
  const invoice = hybridSelection(values.invoiceChoice, values.invoiceCustom);
  const hybrid = {policy_version: HYBRID_POLICY, lines, invoice_discount_percent: invoice.choice === 'other' ? invoice.percent : invoice.choice};
  if (lines.some(line => line.replace_free_entitlement)) hybrid.replacement_discount = hybridSelection(values.replacementChoice, values.replacementCustom, true);
  return {request_id: values.requestId, customer_id: id(values.customerId), payment_type: values.payment, amount_received: values.received, hybrid};
}
function connectHybridInvoice(doc = document) {
  const form = doc.getElementById('hybrid-invoice-form'); if (!form) return null;
  const win = doc.defaultView;
  const byId = id => doc.getElementById(id);
  const el = (tag, text = '', cls = '') => { const node = doc.createElement(tag); node.textContent = text; node.className = cls; return node; };
  const editor = byId('hybrid-editor'), container = byId('hybrid-lines'), message = byId('hybrid-message');
  const preview = byId('hybrid-preview'), confirm = byId('hybrid-confirm'), reviewBox = byId('hybrid-review');
  const customer = byId('hybrid-customer'), picker = byId('hybrid-product');
  const state = {rows: [], epoch: 0, review: null, pending: null, busy: false, issued: false, timer: null, quoteRequest: null, eligibilityRequest: null};
  const online = () => win.navigator.onLine !== false;
  const posting = form.dataset.postingEnabled === 'true';
  const say = (text, error = false) => { message.textContent = text; message.className = 'alert ' + (error ? 'alert-danger' : 'alert-info'); message.setAttribute('role', error ? 'alert' : 'status'); };
  const money = value => value == null ? 'Review required' : 'LKR ' + value;
  function controls() {
    editor.disabled = state.issued || !!state.pending;
    preview.disabled = !online() || state.busy || !!state.pending || state.issued;
    confirm.disabled = !online() || state.busy || state.issued || (!state.pending && (!state.review || !posting));
    confirm.textContent = state.pending ? 'Check / retry this confirmation' : 'Confirm and issue invoice';
  }
  function facts(node, pairs) {
    node.replaceChildren();
    for (const [label, value] of pairs) node.append(el('dt', label), el('dd', value == null ? 'Review required' : String(value)));
  }
  function quantities(row, data, financial = false) {
    facts(row.facts, [
      ['Paid quantity', data?.paid_quantity], ['Promotion-qualified paid units', data?.qualifying_paid_quantity],
      ['Discount-eligible remainder', data?.discount_eligible_quantity], ['Free entitlement', data?.free_entitlement],
      ['Actual free delivery', financial ? data.actual_free_quantity : null],
      ['Substituted free entitlement', financial ? data.substituted_free_quantity : null],
      ['Product discount', money(financial ? data.product_discount_amount : null)],
      ['Replacement discount', money(financial ? data.replacement_discount_amount : null)],
      ['Allocated invoice discount', money(financial ? data.invoice_discount_amount : null)],
      ['Line payable', money(financial ? data.payable_amount : null)]
    ]);
  }
  function replacementControls() {
    const selected = state.rows.some(row => row.replace.checked);
    byId('hybrid-replacement').hidden = !selected;
    byId('hybrid-replacement-choice').disabled = !selected;
    const custom = selected && byId('hybrid-replacement-choice').value === 'custom';
    byId('hybrid-replacement-custom-wrap').hidden = !custom; byId('hybrid-replacement-custom').disabled = !custom;
  }
  function values() {
    return {requestId: form.elements.request_id.value, customerId: customer.value, payment: byId('hybrid-payment').value, received: byId('hybrid-received').value,
      lines: state.rows.map(row => ({productId: row.id, quantity: row.quantity.value, choice: row.choice.value, custom: row.custom.value, replace: row.replace.checked})),
      invoiceChoice: byId('hybrid-invoice-choice').value, invoiceCustom: byId('hybrid-invoice-custom').value,
      replacementChoice: byId('hybrid-replacement-choice').value, replacementCustom: byId('hybrid-replacement-custom').value};
  }
  async function send(url, payload, signal) {
    const target = new URL(url, win.location.href); if (target.origin !== win.location.origin) throw Error('A same-origin online server is required.');
    if (!online()) throw Error('Hybrid checkout is online only. Connect and obtain a new preview.');
    const response = await win.fetch(target, {method: 'POST', credentials: 'same-origin', cache: 'no-store', redirect: 'error', signal,
      headers: {'Content-Type': 'application/json', 'X-CSRF-Token': form.elements.csrf_token.value}, body: JSON.stringify(payload)});
    let data; try { data = await response.json(); } catch { const error = Error('The server response could not be verified.'); error.status = 0; throw error; }
    if (!response.ok) { const error = Error(Object.values(data.errors || {}).flat().join(' ') || `Server rejected the request (${response.status}).`); error.status = response.status; throw error; }
    return data;
  }
  function invalidate(text = 'Selections changed. Obtain a new server preview.', refresh = true) {
    state.epoch++; state.quoteRequest?.abort(); state.eligibilityRequest?.abort(); win.clearTimeout(state.timer);
    state.review = null; state.busy = false; reviewBox.hidden = true;
    for (const row of state.rows) { quantities(row, null); row.status.textContent = 'Awaiting current stock and promotion check.'; }
    if (text) say(text); controls();
    if (refresh && online() && !state.pending && customer.value && state.rows.length) state.timer = win.setTimeout(eligibility, 180);
  }
  async function eligibility() {
    const epoch = state.epoch; let payload;
    try {
      // Discovery needs no replacement selection/rate; it never produces a financial review.
      const input = values(); input.lines = input.lines.map(line => ({...line, replace: false})); payload = hybridPayload(input);
    } catch { return; }
    state.eligibilityRequest = new win.AbortController();
    try {
      const data = await send(form.dataset.eligibilityUrl, payload, state.eligibilityRequest.signal);
      if (epoch !== state.epoch || state.review || state.busy || state.pending) return;
      for (const row of state.rows) {
        const line = data.lines?.find(line => line.product_id === row.id); if (!line) continue;
        quantities(row, line); row.replacementWrap.hidden = !line.replacement_supported && !row.replace.checked;
        row.replace.disabled = !line.replacement_supported && !row.replace.checked;
        const labels = {AVAILABLE: line.buy_quantity ? `Offer: Buy ${line.buy_quantity}, Free ${line.free_per_group} per complete group. Delivery and money require review.` : 'No applicable free offer. Paid units are discount-eligible.',
          INSUFFICIENT_PAID_STOCK: 'Insufficient paid stock. Reduce quantity; replacement cannot cover paid stock.',
          PARTIAL_FREE_SHORTAGE: 'Partial free-entitlement shortage. Replacement is not supported; revise the quantity or contact an administrator.',
          REPLACEMENT_ELIGIBLE: 'No free stock remains after paid demand. You may explicitly replace the complete entitlement. Product discount must be zero.',
          REPLACEMENT_UNSUPPORTED: 'Free-stock shortage on a remainder-bearing line. Replacement is not supported. Revise the quantity or contact an administrator.'};
        row.status.textContent = labels[line.stock_status] || 'Request a new server review.';
      }
    } catch (error) { if (epoch === state.epoch && error.name !== 'AbortError') say(error.message + ' No invoice was queued.', true); }
  }
  function financialChange() {
    if (state.pending || state.issued) return;
    const custom = byId('hybrid-invoice-choice').value === 'other';
    byId('hybrid-invoice-custom-wrap').hidden = !custom; byId('hybrid-invoice-custom').disabled = !custom;
    replacementControls(); invalidate();
  }
  function addRow() {
    if (state.pending || state.issued) return;
    const option = picker.selectedOptions[0]; if (!option?.value) return say('Select a product first.', true);
    const existing = state.rows.find(row => row.id === option.value); if (existing) { existing.quantity.focus(); return say('This product already has a line. Edit its paid quantity.'); }
    if (state.rows.length >= 100) return say('At most 100 different products may be added.', true);
    byId('hybrid-empty')?.remove();
    const card = el('article', '', 'hybrid-line'); card.dataset.productId = option.value;
    const header = el('div', '', 'hybrid-line-header'), remove = el('button', 'Remove', 'btn btn-outline-secondary btn-sm'); remove.type = 'button'; remove.setAttribute('aria-label', 'Remove ' + option.textContent);
    header.append(el('strong', option.textContent), remove); card.append(header);
    const fields = el('div', '', 'hybrid-line-controls');
    const field = (label, input) => { const box = el('div'), l = el('label', label, 'form-label'); l.htmlFor = input.id; box.append(l, input); fields.append(box); return box; };
    const row = {id: option.value, card};
    row.quantity = el('input', '', 'form-control'); row.quantity.id = 'hybrid-qty-' + row.id; row.quantity.value = '1'; row.quantity.inputMode = 'numeric'; row.quantity.maxLength = 19; row.quantity.required = true;
    field('Paid quantity', row.quantity);
    row.choice = el('select', '', 'form-select'); row.choice.id = 'hybrid-discount-' + row.id;
    for (const [value, text] of [['0', 'No Discount (0%)'], ['25', '25%'], ['30', '30%'], ['35', '35%'], ['other', 'Other Discount']]) { const o = el('option', text); o.value = value; row.choice.append(o); }
    field('Product discount — eligible remainder only', row.choice);
    row.custom = el('input', '', 'form-control'); row.custom.id = 'hybrid-custom-' + row.id; row.custom.inputMode = 'decimal'; row.custom.maxLength = 5; row.custom.disabled = true;
    row.customWrap = field('Other percentage (0–50%)', row.custom); row.customWrap.hidden = true;
    card.append(fields); row.facts = el('dl', '', 'hybrid-line-facts'); card.append(row.facts);
    row.status = el('p', '', 'hybrid-line-status small text-secondary'); row.status.setAttribute('role', 'status'); card.append(row.status);
    row.replacementWrap = el('div', '', 'form-check mt-2'); row.replacementWrap.hidden = true;
    row.replace = el('input', '', 'form-check-input'); row.replace.type = 'checkbox'; row.replace.id = 'hybrid-replace-' + row.id;
    const replacementLabel = el('label', 'Replace this complete free entitlement with an approved monetary discount', 'form-check-label'); replacementLabel.htmlFor = row.replace.id;
    row.replacementWrap.append(row.replace, replacementLabel); card.append(row.replacementWrap); state.rows.push(row); container.append(card);
    row.quantity.addEventListener('input', financialChange);
    row.custom.addEventListener('input', financialChange);
    row.choice.addEventListener('change', () => { row.customWrap.hidden = row.choice.value !== 'other'; row.custom.disabled = row.choice.value !== 'other'; financialChange(); });
    row.replace.addEventListener('change', financialChange);
    remove.addEventListener('click', () => { state.rows = state.rows.filter(item => item !== row); card.remove(); financialChange(); });
    picker.value = ''; financialChange(); row.quantity.focus();
  }
  function renderReview(data) {
    const c = data.calculation; reviewBox.hidden = false;
    byId('hybrid-review-context').textContent = `${data.customer.shop_name} · ${data.rep_name} · ${data.business_date}`;
    const list = byId('hybrid-review-lines'); list.replaceChildren();
    for (const line of c.lines) {
      const row = state.rows.find(row => row.id === line.product_id); quantities(row, line, true); row.status.textContent = 'Server-verified benefits for this review.';
      const block = el('div', '', 'hybrid-review-line'); block.append(el('strong', row.card.querySelector('strong').textContent));
      const dl = el('dl', '', 'hybrid-line-facts'); quantities({facts: dl}, line, true); block.append(dl); list.append(block);
    }
    facts(byId('hybrid-totals'), [['Original paid subtotal', money(c.original_paid_subtotal)], ['Product discounts', money(c.product_discount_total)],
      ['Approved replacement discounts', money(c.replacement_discount_total)], ['Sequential invoice discount (' + c.invoice_discount_percent + '%)', money(c.invoice_discount_amount)],
      ['Combined monetary discount', money(c.combined_monetary_discount)], ['Final payable', money(c.final_payable_total)],
      ['Cash received', money(data.amount_received)], ['Cash applied', money(data.applied_amount)], ['Change', money(data.change_amount)], ['Outstanding', money(data.outstanding)]]);
    byId('hybrid-cap').textContent = 'Strict 50% monetary cap: passed. Maximum ' + money(c.monetary_cap_limit) + '. Actual free-unit value is excluded.';
  }
  form.addEventListener('submit', async event => {
    event.preventDefault(); if (state.pending || state.issued || state.busy || !online()) return;
    let payload; try { payload = hybridPayload(values()); } catch (error) { invalidate(error.message, false); say(error.message, true); message.focus(); return; }
    invalidate('Obtaining a fresh server review…', false); const epoch = state.epoch; state.busy = true; controls(); state.quoteRequest = new win.AbortController();
    try {
      const data = await send(form.dataset.previewUrl, payload, state.quoteRequest.signal);
      if (epoch !== state.epoch || !online()) return;
      if (!/^[a-f0-9]{64}$/.test(data.review_hash || '') || data.request_id !== payload.request_id || data.calculation?.policy_version !== HYBRID_POLICY
        || data.calculation.lines.length !== payload.hybrid.lines.length || data.calculation.lines.some((line, i) => line.product_id !== payload.hybrid.lines[i].product_id || line.paid_quantity !== payload.hybrid.lines[i].paid_quantity)) throw Error('The returned review does not match these selections. Review again.');
      state.review = {payload, hash: data.review_hash}; renderReview(data); say(posting ? 'Server review verified. Check the quantities, discounts and payment before confirming.' : 'Server review verified. Invoice posting is currently disabled.');
    } catch (error) { if (epoch === state.epoch && error.name !== 'AbortError') { state.review = null; reviewBox.hidden = true; say(error.message + ' Reduce invalid selections or refresh the review. No invoice was issued by preview.', true); message.focus(); state.timer = win.setTimeout(eligibility, 0); } }
    finally { if (epoch === state.epoch) { state.busy = false; controls(); } }
  });
  confirm.addEventListener('click', async () => {
    if (state.issued || state.busy || !online() || (!state.pending && (!state.review || !posting))) return;
    if (!state.pending) state.pending = {...state.review.payload, review_hash: state.review.hash};
    state.busy = true; state.review = null; controls(); say('Checking this exact confirmation with the server…');
    try {
      const result = await send(form.dataset.issueUrl, state.pending);
      if (!/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/.test(result.invoice_id || '')) throw Error('The server receipt could not be verified.');
      state.issued = true; state.pending = null;
      const link = el('a', 'Open invoice ' + (result.invoice_number || result.invoice_id), 'btn btn-outline-primary'); link.href = form.dataset.invoiceBase + result.invoice_id;
      byId('hybrid-result').replaceChildren(link); say(result.replayed ? 'Previously accepted invoice found. No duplicate posting occurred.' : 'Invoice issued. The server recorded the stock and payment.');
    } catch (error) {
      if (error.status && error.status < 500 && ![401, 403, 408].includes(error.status)) { state.pending = null; invalidate(error.message + ' Obtain a new server preview before confirming again.', false); say(error.message + ' Obtain a new server preview before confirming again.', true); }
      else say(error.message + ' Acceptance is unresolved. Keep this page open and use Check / retry to resend the same request identity. Nothing is queued offline.', true);
    } finally { state.busy = false; controls(); }
  });
  byId('hybrid-add').addEventListener('click', addRow);
  byId('hybrid-product-search').addEventListener('input', event => { const q = event.target.value.trim().toLocaleLowerCase(); for (const option of picker.options) option.hidden = !!option.value && !option.selected && !option.textContent.toLocaleLowerCase().includes(q); });
  for (const id of ['hybrid-customer', 'hybrid-payment', 'hybrid-invoice-choice', 'hybrid-replacement-choice']) byId(id).addEventListener('change', financialChange);
  for (const id of ['hybrid-received', 'hybrid-invoice-custom', 'hybrid-replacement-custom']) byId(id).addEventListener('input', financialChange);
  win.addEventListener('offline', () => {
    if (state.issued) { say('Invoice already issued. Reconnect to open its server record.'); controls(); return; }
    state.quoteRequest?.abort(); state.eligibilityRequest?.abort(); win.clearTimeout(state.timer); state.epoch++; state.review = null; reviewBox.hidden = true;
    if (!state.pending) state.busy = false;
    say('Offline. Hybrid checkout cannot be reviewed or confirmed. Reconnect and request a fresh preview. No offline draft or synchronization entry was created.', true); controls();
  });
  win.addEventListener('online', () => { say(state.issued ? 'Invoice is issued. You can open its server record.' : (state.pending ? 'Online. Check / retry the unresolved confirmation using its original request identity.' : 'Online. Request a new server preview.')); controls(); });
  win.addEventListener('beforeunload', event => { if (state.pending && !state.issued) { event.preventDefault(); event.returnValue = ''; } });
  controls(); if (!online()) { say('Hybrid checkout is online only. Reconnect before requesting a review.', true); }
  return {invalidate, state};
}
if (typeof module !== 'undefined') module.exports = {hybridRate, hybridSelection, hybridPayload, connectHybridInvoice};
if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => connectHybridInvoice(document));
  else connectHybridInvoice(document);
}
