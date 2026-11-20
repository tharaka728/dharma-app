(() => {
  'use strict';
  const root = document.getElementById('historical-return');
  if (!root) return;
  const panel = root.querySelector('[data-return-panel]');
  const intent = root.querySelector('[data-return-intent]');
  const status = root.querySelector('[data-return-status]');
  const invoiceLink = root.querySelector('[data-return-invoice]');
  const customer = document.getElementById('customer_id');
  const storageKey = 'dharma_historical_return_intent';
  // Server-side recent intents remain available when browser storage is blocked.
  const remember = value => { try { sessionStorage.setItem(storageKey,value); } catch (_) {} };
  const recalled = () => { try { return sessionStorage.getItem(storageKey); } catch (_) { return null; } };
  const text = (english, sinhala) => globalThis.DharmaI18n?.locale === 'en' ? english : sinhala;
  // A successful connectivity probe does not imply this authenticated API succeeded.
  async function checkedJson(response) {
    if (response.redirected || response.status === 401) throw new Error('session');
    if (!response.ok) {
      let body; try { body = await response.json(); } catch (_) { body = null; }
      if (response.status === 403) throw new Error(body?.errors?.csrf || body?.error?.code === 'CSRF_FAILED' ? 'csrf' : 'forbidden');
      if (response.status === 409) throw new Error('stale');
      if (response.status === 503) throw new Error('unavailable');
      if (response.status === 404 || response.status === 422) throw new Error('invalid');
      throw new Error('server');
    }
    if (!(response.headers.get('content-type') || '').includes('application/json')) throw new Error('session');
    try { return await response.json(); } catch (_) { throw new Error('server'); }
  }
  function failure(error) {
    if (!navigator.onLine) return text('No network connection. Reconnect and try again.','\u0d85\u0db1\u0dca\u0dad\u0dbb\u0dca\u0da2\u0dcf\u0dbd \u0dc3\u0db8\u0dca\u0db6\u0db1\u0dca\u0db0\u0dad\u0dcf\u0dc0 \u0db1\u0dd0\u0dad. \u0db1\u0dd0\u0dc0\u0dad \u0dc3\u0db8\u0dca\u0db6\u0db1\u0dca\u0db0 \u0dc0\u0db1\u0dca\u0db1.');
    if (error instanceof TypeError) return text('Cannot reach the return service. Check the connection and try again.','\u0dc3\u0dda\u0dc0\u0dcf\u0daf\u0dcf\u0dba\u0d9a\u0dba\u0da7 \u0dc3\u0db8\u0dca\u0db6\u0db1\u0dca\u0db0 \u0dc0\u0dd2\u0dba \u0db1\u0ddc\u0dc4\u0dd0\u0d9a. \u0db1\u0dd0\u0dc0\u0dad \u0d8b\u0dad\u0dca\u0dc3\u0dcf\u0dc4 \u0d9a\u0dbb\u0db1\u0dca\u0db1.');
    switch(error?.message) {
      case 'session': return text('Your session has expired. Sign in again.','\u0db4\u0dd2\u0dc0\u0dd2\u0dc3\u0dd4\u0db8 \u0d9a\u0dbd\u0dca \u0d89\u0d9a\u0dd4\u0dad\u0dca \u0dc0\u0dd3 \u0d87\u0dad. \u0db1\u0dd0\u0dc0\u0dad \u0db4\u0dd2\u0dc0\u0dd2\u0dc3\u0dd9\u0db1\u0dca\u0db1.');
      case 'csrf': return text('Security check failed. Refresh the page and try again.','\u0d86\u0dbb\u0d9a\u0dca\u0dc2\u0d9a \u0db4\u0dbb\u0dd3\u0d9a\u0dca\u0dc2\u0dcf\u0dc0 \u0d85\u0dc3\u0dcf\u0dbb\u0dca\u0dae\u0d9a\u0dba\u0dd2. \u0db4\u0dd2\u0da7\u0dd4\u0dc0 \u0dba\u0dc5\u0dd2 \u0db4\u0dd6\u0dbb\u0dab\u0dba \u0d9a\u0dbb\u0db1\u0dca\u0db1.');
      case 'forbidden': return text('You do not have access to this return or customer.','\u0db8\u0dd9\u0db8 \u0d86\u0db4\u0dc3\u0dd4 \u0db7\u0dcf\u0dbb\u0d9c\u0dd0\u0db1\u0dd3\u0db8\u0da7 \u0dc4\u0ddd \u0db4\u0dcf\u0dbb\u0dd2\u0db7\u0ddd\u0d9c\u0dd2\u0d9a\u0dba\u0dcf\u0da7 \u0d85\u0dc0\u0dc3\u0dbb \u0db1\u0dd0\u0dad.');
      case 'stale': return text('Return details or price changed. Review again.','\u0d86\u0db4\u0dc3\u0dd4 \u0db7\u0dcf\u0dbb\u0d9c\u0dd0\u0db1\u0dd3\u0db8\u0dda \u0dad\u0ddc\u0dbb\u0dad\u0dd4\u0dbb\u0dd4 \u0dc4\u0ddd \u0db8\u0dd2\u0dbd \u0dc0\u0dd9\u0db1\u0dc3\u0dca \u0dc0\u0dd3 \u0d87\u0dad. \u0db1\u0dd0\u0dc0\u0dad \u0db4\u0dbb\u0dd3\u0d9a\u0dca\u0dc2\u0dcf \u0d9a\u0dbb\u0db1\u0dca\u0db1.');
      case 'unavailable': return text('Historical returns are currently unavailable. Contact the administrator.','\u0db4\u0dd0\u0dbb\u0dab\u0dd2 \u0db7\u0dcf\u0dab\u0dca\u0da9 \u0d86\u0db4\u0dc3\u0dd4 \u0db7\u0dcf\u0dbb\u0d9c\u0dd0\u0db1\u0dd3\u0db8 \u0daf\u0dd0\u0db1\u0da7 \u0dbd\u0db6\u0dcf \u0d9c\u0dad \u0db1\u0ddc\u0dc4\u0dd0\u0d9a. \u0db4\u0dbb\u0dd2\u0db4\u0dcf\u0dbd\u0d9a \u0d85\u0db8\u0dad\u0db1\u0dca\u0db1.');
      case 'invalid': return text('The selected return or product is unavailable. Check your selection.','\u0dad\u0ddd\u0dbb\u0dcf\u0d9c\u0dad\u0dca \u0d86\u0db4\u0dc3\u0dd4 \u0db7\u0dcf\u0dbb\u0d9c\u0dd0\u0db1\u0dd3\u0db8 \u0dc4\u0ddd \u0db7\u0dcf\u0dab\u0dca\u0da9\u0dba \u0db1\u0ddc\u0db8\u0dd0\u0dad. \u0db1\u0dd0\u0dc0\u0dad \u0db4\u0dbb\u0dd3\u0d9a\u0dca\u0dc2\u0dcf \u0d9a\u0dbb\u0db1\u0dca\u0db1.');
      default: return text('Temporary return service error. Try again or contact the administrator.','\u0d86\u0db4\u0dc3\u0dd4 \u0db7\u0dcf\u0dbb\u0d9c\u0dd0\u0db1\u0dd3\u0db8\u0dda \u0dc3\u0dda\u0dc0\u0dcf\u0dc0 \u0dbd\u0db6\u0dcf \u0d9c\u0dad \u0db1\u0ddc\u0dc4\u0dd0\u0d9a. \u0db1\u0dd0\u0dc0\u0dad \u0d8b\u0dad\u0dca\u0dc3\u0dcf\u0dc4 \u0d9a\u0dbb\u0db1\u0dca\u0db1.');
    }
  }
  const lines = [...root.querySelectorAll('[data-return-line]')];
  const issue = root.closest('form').querySelector('button[value="issue"]');
  let pendingRestores = 0, recoveryEpoch = 0;
  function syncPriceReadiness() {
    const unresolved = pendingRestores > 0 || lines.some(item => Boolean(item.querySelector('[data-return-product]').dataset.selected));
    root.dataset.pricesReady = unresolved ? '0' : '1';
    if (unresolved) {
      const review = root.closest('form').querySelector('button[value="review"]');
      if (review) review.disabled = true;
      if (issue) issue.disabled = true;
    }
    return !unresolved;
  }
  let active = root.dataset.active === '1';
  const format = cents => `${cents / 100n}.${String(cents % 100n).padStart(2, '0')}`;
  function update() {
    let total = 0n;
    lines.forEach(line => {
      const product = line.querySelector('[data-return-product]');
      const qty = line.querySelector('[data-return-quantity]');
      const chosen = active && product.value !== '';
      line.querySelector('[data-return-year]').disabled = !active;
      product.disabled = !active;
      qty.disabled = !active;
      line.querySelectorAll('[data-return-name]').forEach(input => {
        if (chosen) input.name = input.dataset.returnName;
        else input.removeAttribute('name');
      });
      const price = product.selectedOptions[0]?.dataset.price || '';
      if (chosen && /^\d+\.\d{2}$/.test(price) && /^[1-9]\d*$/.test(qty.value)) {
        const value = BigInt(price.replace('.', '')) * BigInt(qty.value);
        total += value;
        line.querySelector('[data-return-value]').textContent = `LKR ${format(value)}`;
      } else line.querySelector('[data-return-value]').textContent = '';
    });
    root.querySelector('[data-return-total]').textContent = format(total);
    root.querySelectorAll('[data-return-common]').forEach(el => { el.disabled = !active; });
    panel.hidden = !active;
  }
  async function search(line) {
    const year = line.querySelector('[data-return-year]').value;
    const query = line.querySelector('[data-return-search]').value;
    const select = line.querySelector('[data-return-product]');
    const selected = select.value || select.dataset.selected || '';
    const restoring = Boolean(select.dataset.selected);
    if (restoring) { pendingRestores++; syncPriceReadiness(); }
    try {
      const url = new URL(root.dataset.priceUrl, location.href);
      url.searchParams.set('year', year); url.searchParams.set('q', query);
      if (restoring) url.searchParams.set('product_id', selected);
      const response = await fetch(url, {credentials: 'same-origin', cache: 'no-store'});
      const data = await checkedJson(response);
      select.replaceChildren(new Option('—', ''));
      data.prices.forEach(price => { const option = new Option(`${price.sku} — ${globalThis.DharmaI18n?.product(price)||price.name} · LKR ${price.unit_price}`, price.id); option.dataset.price = price.unit_price; select.add(option); });
      if (selected) select.value = selected;
      if (selected && !select.value) { line.querySelector('[data-return-value]').textContent = text('The approved historical price is no longer available. Review again.','අනුමත පැරණි මිල තවදුරටත් නොමැත. නැවත පරීක්ෂා කරන්න.'); return; }
      select.dataset.selected = '';
      update();
      if (!data.prices.length) line.querySelector('[data-return-value]').textContent = text('No approved historical price found for this search and year.','මෙම සෙවුමට සහ වර්ෂයට අනුමත පැරණි මිලක් හමු නොවීය.');
    } catch (error) { line.querySelector('[data-return-value]').textContent = failure(error); }
    finally { if (restoring) { pendingRestores--; const ready=syncPriceReadiness(); if (issue) issue.disabled = !ready; else if (ready) root.closest('form').dispatchEvent(new Event('input',{bubbles:true})); } }
  }
  async function recover(id) {
    const epoch=++recoveryEpoch;
    try {
      const url = new URL(root.dataset.intentUrl, location.href);url.searchParams.set('id', id);
      const response = await fetch(url,{credentials:'same-origin',cache:'no-store'});
      const data = await checkedJson(response);
      if(epoch!==recoveryEpoch)return;
      if (data.status === 'FINALIZED') {
        active = false;status.textContent = text('Return already completed. Open the linked invoice.','ආපසු භාරගැනීම දැනටමත් සම්පූර්ණයි. අදාළ බිල්පත විවෘත කරන්න.');
        invoiceLink.href = root.dataset.invoiceBase + '/' + encodeURIComponent(data.invoice_id);
        invoiceLink.textContent = data.invoice_number;invoiceLink.hidden = false;
      } else if (data.status === 'EXPIRED') {
        active = false;status.textContent = text('This return expired. Start a new return.','මෙම ආපසු භාරගැනීම කල් ඉකුත් වී ඇත. නව එකක් ආරම්භ කරන්න.');invoiceLink.hidden = true;
      } else {
        if (![...customer.options].some(option=>option.value===data.customer_id)) throw new Error('Customer is no longer available.');
        customer.value=data.customer_id;intent.value=data.return_intent_id;active=true;invoiceLink.hidden=true;
        status.textContent=text('Return recovered. Re-enter any unsubmitted products and quantities.','ආපසු භාරගැනීම නැවත ලබාගත්තා. යොමු නොකළ භාණ්ඩ සහ ප්‍රමාණ නැවත ඇතුළත් කරන්න.');
      }
    } catch (error) { if(epoch!==recoveryEpoch)return;active=false;status.textContent=failure(error); }
    update();
  }
  root.querySelector('[data-return-toggle]').addEventListener('click', async () => {
    if (!customer.value) { status.textContent=text('Select a customer before starting a return.','ආපසු භාරගැනීමට පෙර පාරිභෝගිකයෙකු තෝරන්න.');return; }
    recoveryEpoch++;
    const form = new FormData();form.set('customer_id',customer.value);form.set('csrf_token',root.closest('form').querySelector('[name=csrf_token]').value);
    try {
      const response=await fetch(root.dataset.intentUrl,{method:'POST',body:form,credentials:'same-origin',cache:'no-store'});
      const data=await checkedJson(response);intent.value=data.return_intent_id;remember(data.return_intent_id);
      lines.forEach(line=>{line.querySelector('[data-return-product]').replaceChildren(new Option('—',''));line.querySelector('[data-return-quantity]').value='1';line.querySelector('[data-return-search]').value='';});
      root.querySelectorAll('[data-return-common]').forEach(field=>{if(field.type==='checkbox')field.checked=false;else if(field!==intent)field.value='';});
      active=true;invoiceLink.hidden=true;status.textContent=text('New return started.','නව ආපසු භාරගැනීම ආරම්භ කළා.');update();root.closest('form').dispatchEvent(new Event('input',{bubbles:true}));
    }catch(error){status.textContent=failure(error);}
  });
  root.closest('form').addEventListener('submit', () => {
    lines.forEach(line => {
      if (!active || !line.querySelector('[data-return-product]').value)
        line.querySelectorAll('[name]').forEach(input => { input.disabled = true; });
    });
  });
  lines.forEach(line => {
    line.querySelectorAll('[name]').forEach(input => { input.dataset.returnName = input.name; });
    line.querySelector('[data-return-search-button]').addEventListener('click', () => search(line));
    line.querySelector('[data-return-year]').addEventListener('change', () => { line.querySelector('[data-return-product]').value = ''; search(line); });
    line.querySelector('[data-return-product]').addEventListener('change', update);
    line.querySelector('[data-return-quantity]').addEventListener('input', update);
    if (line.querySelector('[data-return-product]').dataset.selected) search(line);
  });
  syncPriceReadiness();
  update();
  const saved=intent.value||recalled();
  if(saved){remember(saved);recover(saved);}
  const recent=root.querySelector('[data-return-recent]');
  fetch(root.dataset.intentUrl,{credentials:'same-origin',cache:'no-store'}).then(response=>{if(!response.ok)throw new Error();return response.json();}).then(data=>{data.intents.forEach(item=>recent.add(new Option(`${item.customer_code} · ${item.shop_name} · ${item.created_at_utc} · ${item.status}`,item.return_intent_id)));}).catch(()=>{});
  root.querySelector('[data-return-recover]').addEventListener('click',()=>{if(recent.value){remember(recent.value);recover(recent.value);}});
})();
