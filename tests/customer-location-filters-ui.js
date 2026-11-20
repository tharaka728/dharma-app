'use strict';
const test=require('node:test'),assert=require('node:assert/strict');
const {createCustomerLocationFilters}=require('../public/assets/js/customer-location-filters.js');
function setup(request){
    const elements=new Map();
    class Element{
        constructor(){this.value='';this.options=[];this.dataset={};this.listeners={};}
        addEventListener(type,fn){this.listeners[type]=fn;}
        emit(type,event={}){return this.listeners[type]?.(event);}
        replaceChildren(...items){this.options=items;this.value='';}
        appendChild(item){this.options.push(item);}
        closest(){return elements.get('form');}
        focus(){this.focused=true;}
    }
    for(const id of ['customer-location-filters','filter-district','filter-town','filter-district-search','filter-town-search','filter-town-more','filter-town-status','form'])elements.set(id,new Element());
    const get=id=>elements.get(id);
    get('customer-location-filters').dataset.townsUrl='/api/v1/admin/locations/towns';
    get('filter-district').options=[{value:'',textContent:'All districts'},{value:'5',textContent:'Colombo'},{value:'11',textContent:'Kandy'}];
    get('filter-town').options=[{value:'1',textContent:'Shared — Colombo',dataset:{districtId:'5'}}];get('filter-town').value='1';
    const picker=createCustomerLocationFilters({getElementById:get,createElement:()=>new Element()},request);return {get,picker};
}
const response=(towns,has_more=false)=>({ok:true,json:async()=>({towns,total:towns.length,has_more})});
test('district changes clear incompatible selections before loading',async()=>{
    let resolve;const {get}=setup(()=>new Promise(r=>resolve=r));get('filter-district').value='11';const pending=get('filter-district').emit('change');
    assert.equal(get('filter-town').value,'');let blocked=false;get('form').emit('submit',{preventDefault(){blocked=true;}});assert.ok(blocked);
    resolve(response([{id:'2',district_id:'11',name:'Shared',district_name:'Kandy'}]));await pending;assert.equal(get('filter-town').value,'');
});
test('compatible town survives district selection and returning to all districts',async()=>{
    const calls=[];const {get}=setup(async url=>{calls.push(new URL(url,'http://localhost'));return response([{id:'1',district_id:'5',name:'Shared',district_name:'Colombo'}]);});
    get('filter-district').value='5';await get('filter-district').emit('change');assert.equal(get('filter-town').value,'1');
    get('filter-district').value='';await get('filter-district').emit('change');assert.equal(get('filter-town').value,'1');
    assert.equal(calls[1].searchParams.get('scope'),'all');assert.equal(calls[1].searchParams.get('district_id'),'');
});
test('cross-district duplicate labels and paged towns retain unique IDs',async()=>{
    const {get,picker}=setup(async url=>{const page=new URL(url,'http://localhost').searchParams.get('page');return response([{id:page==='1'?'2':'3',district_id:'11',name:'Shared',district_name:'Kandy'}],page==='1');});
    await picker.load();assert.ok(get('filter-town').options.some(o=>o.textContent==='Shared — Kandy'));
    await get('filter-town-more').emit('click');assert.deepEqual(get('filter-town').options.map(o=>o.value),['','2','1','3']);assert.equal(get('filter-town').value,'1');
});
test('stale response cannot replace towns after a district switch',async()=>{
    const pending=[];const {get}=setup(()=>new Promise(r=>pending.push(r)));
    get('filter-district').value='5';const first=get('filter-district').emit('change');get('filter-district').value='11';const second=get('filter-district').emit('change');
    pending[1](response([{id:'2',district_id:'11',name:'Kandy',district_name:'Kandy'}]));await second;
    pending[0](response([{id:'1',district_id:'5',name:'Colombo',district_name:'Colombo'}]));await first;
    assert.deepEqual(get('filter-town').options.map(o=>o.value),['','2']);
});
test('typing searches the API and Enter moves focus without submitting',async()=>{
    const calls=[];const {get}=setup(async url=>{calls.push(url);return response([]);});get('filter-town-search').value='Missing';get('filter-town-search').emit('input');
    await new Promise(r=>setTimeout(r,300));assert.equal(new URL(calls[0],'http://localhost').searchParams.get('q'),'Missing');
    let blocked=false;get('filter-town-search').emit('keydown',{key:'Enter',preventDefault(){blocked=true;}});assert.ok(blocked&&get('filter-town').focused);
    get('filter-town').value='';blocked=false;get('form').emit('submit',{preventDefault(){blocked=true;}});assert.equal(blocked,false);
});
test('failed lookup reports errors and allows district-only filtering after clearing town',async()=>{
    const {get,picker}=setup(async()=>{throw new Error('Offline');});await picker.load();assert.match(get('filter-town-status').textContent,/Offline/);
    let blocked=false;get('form').emit('submit',{preventDefault(){blocked=true;}});assert.ok(blocked);
    get('filter-town').value='';blocked=false;get('form').emit('submit',{preventDefault(){blocked=true;}});assert.equal(blocked,false);
});
