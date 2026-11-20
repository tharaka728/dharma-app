'use strict';
const fs=require('fs'),assert=require('node:assert/strict'),D=require('../public/assets/js/offline-domain.js');
const {snapshot,cases}=JSON.parse(fs.readFileSync(process.argv[2],'utf8'));
const now=Date.now(),state={snapshot,rep_id:snapshot.rep.id,device_id:snapshot.device_id,local_anchor:now,last_local_time:now,sales:[]};
const results=cases.map(({input,expected})=>{
 const r=D.preview(state,input,now);
 for(const key of ['subtotal','discount_total','grand_total','invoice_discount_amount'])assert.equal(r[key],expected[key],key);
 assert.deepEqual(r.oil_offers[0].groups,expected.oil_offers[0].groups);
 for(const line of r.items){const e=expected.lines.find(e=>e.product_id===line.product_id);assert.ok(e);for(const key of ['unit_price','free_quantity','required_stock','payable_amount','discount_amount','invoice_discount_amount'])assert.equal(line[key],e[key],key);assert.equal(line.quantity,e.paid_quantity);}
 return r;
});
assert.throws(()=>D.preview(state,{...cases[0].input,oil_rewards:[{product_id:cases[0].input.items[0].product_id,quantity:'1'}]},now),/cheapest/);
assert.throws(()=>D.preview(state,{...cases[0].input,oil_rewards:[{product_id:cases[0].input.oil_rewards[0].product_id,quantity:'2'}]},now),/entitlement/);
process.stdout.write(JSON.stringify(results));
