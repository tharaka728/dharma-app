'use strict';
/* Sinhala display bindings */
const tr_customer_locations_js=(key,values={})=>globalThis.DharmaI18n?.t(key,values)??key.replace(/\{([0-9]+)\}/g,(m,k)=>Object.hasOwn(values,k)?String(values[k]):m);

function createCustomerLocationPicker(doc, request) {
    const get = id => doc.getElementById(id);
    const picker = get('location-picker');
    if (!picker) return;
    const district = get('district_id'), town = get('town_id');
    const districtSearch = get('district-search'), search = get('town-search');
    const status = get('town-status'), more = get('town-more');
    const toggle = get('town-add-toggle'), panel = get('town-add-panel');
    const name = get('new-town-name'), save = get('town-add-save'), cancel = get('town-add-cancel');
    const addStatus = get('town-add-status'), addDistrict = get('new-town-district');
    const form = picker.closest('form');
    const csrf = form.querySelector('[name="csrf_token"]').value;
    const districts = Array.from(district.options).filter(o => o.value).map(o => ({id:o.value,name:o.textContent,name_en:o.dataset.nameEn||o.textContent}));
    let rows = new Map(Array.from(town.options).filter(o => o.value).map(o => [o.value,{id:o.value,name:o.textContent}]));
    let page = 1, epoch = 0, controller = null, timer = null, loading = false, creating = false;
    function option(id, text) { const o = doc.createElement('option'); o.value = id; o.textContent = text; return o; }
    function cancelRead() { epoch++; if (controller) controller.abort(); }
    function clearTown() {
        rows = new Map(); town.replaceChildren(option('', tr_customer_locations_js('Choose town'))); town.value = '';
        more.hidden = true;
    }
    async function json(url, config = {}) {
        const response = await request(url, {credentials:'same-origin',headers:{'Accept':'application/json',...(config.headers || {})},...config});
        const data = await response.json();
        if (!response.ok) throw new Error(response.status === 401 ? tr_customer_locations_js("Your session expired. Sign in again and refresh this form.") : Object.values(data.errors || {}).join(' ') || tr_customer_locations_js("Unable to load locations. Please retry."));
        return data;
    }
    async function load(append = false, wanted = null) {
        cancelRead(); const run = epoch, selectedDistrict = district.value;
        if (!selectedDistrict) { clearTown(); town.disabled = true; search.disabled = true; loading = false; status.textContent = tr_customer_locations_js("Choose a district first."); return; }
        const selected = wanted ? String(wanted.id) : town.value;
        const selectedRow = wanted || rows.get(selected);
        const nextPage = append ? page + 1 : 1;
        controller = new AbortController(); loading = true; town.disabled = true; more.disabled = true;
        search.disabled = false; status.textContent = tr_customer_locations_js("Loading towns…");
        const query = new URLSearchParams({district_id:selectedDistrict,q:search.value.trim(),page:String(nextPage)});
        try {
            const data = await json(picker.dataset.townsUrl + '?' + query, {signal:controller.signal});
            if (run !== epoch || district.value !== selectedDistrict) return;
            const result = append ? new Map(rows) : new Map();
            for (const row of data.towns) result.set(String(row.id),row);
            if (selectedRow) result.set(String(selectedRow.id), selectedRow);
            rows = result; town.replaceChildren(option('', tr_customer_locations_js('Choose town')));
            for (const row of rows.values()) town.appendChild(option(String(row.id), row.name));
            town.value = rows.has(selected) ? selected : ''; town.disabled = false;
            page = nextPage; more.hidden = !data.has_more;
            status.textContent = tr_customer_locations_js("{0} matching towns. Choose a town; your current selection is retained while searching.",{0:(data.total)});
        } catch (error) {
            if (run !== epoch) return;
            status.textContent = error.message; town.disabled = false;
        } finally {
            if (run === epoch) { loading = false; more.disabled = false; }
        }
    }
    districtSearch.addEventListener('input', () => {
        const selected = district.value, query = districtSearch.value.trim().toLowerCase();
        district.replaceChildren(option('', tr_customer_locations_js('Choose district')));
        for (const row of districts) if ((row.name+' '+(row.name_en||'')).toLowerCase().includes(query) || row.id === selected) district.appendChild(option(row.id,row.name));
        district.value = selected;
    });
    district.addEventListener('change', async () => {
        clearTimeout(timer); cancelRead(); clearTown(); search.value = ''; panel.hidden = true;
        toggle.setAttribute('aria-expanded','false'); addStatus.textContent = ''; name.value = '';
        await load();
    });
    search.addEventListener('input', () => { clearTimeout(timer); cancelRead(); more.disabled = true; timer = setTimeout(() => load(),250); });
    more.addEventListener('click', () => load(true));
    town.addEventListener('change', () => town.setCustomValidity(''));
    for (const [input, select] of [[districtSearch,district],[search,town]]) input.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); select.focus(); } });
    toggle.addEventListener('click', () => {
        if (!district.value) { district.reportValidity(); status.textContent = tr_customer_locations_js("Choose a district before adding a town."); district.focus(); return; }
        panel.hidden = !panel.hidden; toggle.setAttribute('aria-expanded',String(!panel.hidden));
        addDistrict.textContent = tr_customer_locations_js("District: ") + districts.find(d => d.id === district.value).name;
        if (!panel.hidden) name.focus();
    });
    cancel.addEventListener('click', () => { panel.hidden = true; toggle.setAttribute('aria-expanded','false'); toggle.focus(); });
    async function addTown() {
        if (creating) return;
        const d = district.value, townName = name.value.trim();
        if (!d || !townName) { addStatus.textContent = tr_customer_locations_js("Select a district and enter a town name."); name.focus(); return; }
        creating = true; save.disabled = true; addStatus.textContent = tr_customer_locations_js("Saving town…");
        try {
            const data = await json(picker.dataset.townsUrl,{method:'POST',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({district_id:d,town_name:townName})});
            if (district.value !== d) { status.textContent = tr_customer_locations_js("Town saved in its original district. Choose that district to select it."); return; }
            rows.set(String(data.town.id),data.town);
            town.appendChild(option(String(data.town.id),data.town.name));town.value=String(data.town.id);town.disabled=false;
            search.value = ''; clearTimeout(timer); await load(false,data.town);
            addStatus.textContent = tr_customer_locations_js("Town saved permanently and selected."); panel.hidden = true; toggle.setAttribute('aria-expanded','false'); name.value = ''; town.focus();
        } catch (error) { addStatus.textContent = error.message; }
        finally { creating = false; save.disabled = false; }
    }
    save.addEventListener('click',addTown);
    name.addEventListener('keydown',event => { if(event.key === 'Enter') { event.preventDefault(); addTown(); } });
    form.addEventListener('submit',event => {
        if (loading || creating || !district.value || !town.value) {
            event.preventDefault(); status.textContent = loading || creating ? tr_customer_locations_js("Wait for the location request to finish.") : tr_customer_locations_js("Select a district and one of its towns before saving.");
            if (!loading) town.focus();
        }
    });
    return {load};
}
if (typeof module !== 'undefined' && module.exports) module.exports = {createCustomerLocationPicker};
if (typeof document !== 'undefined') createCustomerLocationPicker(document,fetch);
