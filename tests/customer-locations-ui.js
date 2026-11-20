'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const {createCustomerLocationPicker} = require('../public/assets/js/customer-locations.js');
function setup(request) {
    const elements = new Map();
    class Element {
        constructor() { this.value=''; this.options=[]; this.listeners={}; this.dataset={}; this.hidden=false; }
        addEventListener(type, fn) { this.listeners[type]=fn; }
        async emit(type, event={}) { return this.listeners[type]?.(event); }
        replaceChildren(...items) { this.options=items; this.value=''; }
        appendChild(item) { this.options.push(item); }
        setAttribute(key,value) { this[key]=value; }
        focus() { this.focused=true; }
        reportValidity() { this.reported=true; }
        setCustomValidity(value) { this.validity=value; }
        closest() { return elements.get('form'); }
        querySelector() { return {value:'test-csrf'}; }
    }
    for(const id of ['location-picker','district_id','town_id','district-search','town-search','town-status','town-more','town-add-toggle','town-add-panel','new-town-name','town-add-save','town-add-cancel','town-add-status','new-town-district','form']) elements.set(id,new Element());
    const get=id=>elements.get(id);
    get('location-picker').dataset.townsUrl='/api/v1/admin/locations/towns';
    get('district_id').options=[{value:'5',textContent:'Colombo'},{value:'11',textContent:'Kandy'}];get('district_id').value='5';
    get('town_id').options=[{value:'1',textContent:'Colombo'}];get('town_id').value='1';
    const picker=createCustomerLocationPicker({getElementById:get,createElement:()=>new Element()},request);
    return {get,picker};
}
const response=data=>({ok:true,json:async()=>data});
test('district change clears incompatible town immediately and blocks submission while loading',async()=>{
    let finish; const {get}=setup(()=>new Promise(resolve=>finish=resolve));
    get('district_id').value='11';const pending=get('district_id').emit('change');
    assert.equal(get('town_id').value,'');assert.equal(get('town_id').options.length,1);
    let prevented=false;await get('form').emit('submit',{preventDefault(){prevented=true;}});assert.ok(prevented);
    finish(response({towns:[{id:'2',name:'Gampola'}],total:1,has_more:false}));await pending;
    assert.equal(get('town_id').value,'');assert.equal(get('town_id').options[1].textContent,'Gampola');
});
test('late responses from previous districts cannot replace current town options',async()=>{
    const pending=[];const {get}=setup(()=>new Promise(resolve=>pending.push(resolve)));
    get('district_id').value='11';const first=get('district_id').emit('change');
    get('district_id').value='5';const second=get('district_id').emit('change');
    pending[1](response({towns:[{id:'1',name:'Colombo'}],total:1,has_more:false}));await second;
    pending[0](response({towns:[{id:'2',name:'Gampola'}],total:1,has_more:false}));await first;
    assert.deepEqual(get('town_id').options.map(o=>o.value),['','1']);
});
test('new town sends district and CSRF to server and selects the persisted response',async()=>{
    const calls=[];const {get}=setup(async(url,config)=>{
        calls.push({url,config});return response(config.method==='POST'?{town:{id:'90',name:'Saved town'}}:{towns:[{id:'90',name:'Saved town'}],total:1,has_more:false});
    });
    get('new-town-name').value=' Saved town ';await get('town-add-save').emit('click');
    assert.equal(calls[0].config.headers['X-CSRF-Token'],'test-csrf');assert.equal(calls[0].config.credentials,'same-origin');
    assert.deepEqual(JSON.parse(calls[0].config.body),{district_id:'5',town_name:'Saved town'});
    assert.equal(get('town_id').value,'90');assert.ok(get('town-add-panel').hidden);
});
test('pagination appends server results and preserves existing edit selection',async()=>{
    const calls=[];const {get,picker}=setup(async url=>{calls.push(url);const page=new URL(url,'http://localhost').searchParams.get('page');return response({towns:[{id:page==='1'?'2':'3',name:'Town '+page}],total:3,has_more:page==='1'});});
    await picker.load();assert.equal(get('town_id').value,'1');assert.equal(get('town-more').hidden,false);
    await get('town-more').emit('click');assert.deepEqual(get('town_id').options.map(o=>o.value),['','2','1','3']);
    assert.equal(new URL(calls[1],'http://localhost').searchParams.get('page'),'2');
});
test('district search and Enter navigation remain keyboard accessible',async()=>{
    const {get}=setup(async()=>response({towns:[],total:0,has_more:false}));
    get('district-search').value='kandy';await get('district-search').emit('input');
    assert.equal(get('district_id').value,'5');assert.equal(get('district_id').options.length,3);
    let prevented=false;await get('town-search').emit('keydown',{key:'Enter',preventDefault(){prevented=true;}});
    assert.ok(prevented);assert.ok(get('town_id').focused);
});
