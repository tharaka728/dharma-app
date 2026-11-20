'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict'),D=require('../public/assets/js/offline-domain.js');
const rule={id:'1',revision_id:'1',name:'Mixed oil',type:'BUY_X_GET_Y',buy_quantity:'6',free_quantity:'1',mixed_policy:'MIXED_OIL_V1',mixed_product_ids:['1','2','3'],start_date:null,end_date:null};
const products=new Map(['100.00','220.00','50.00'].map((selling_price,i)=>[String(i+1),{id:String(i+1),sku:'Oil'+i,name:'Oil '+i,selling_price,version:'1',rule}]));
test('repeated six-unit groups match independent expanded-unit oracle across 100 baskets',()=>{
 for(let seed=0;seed<100;seed++){
  const items=[...products.values()].map((p,i)=>({product_id:p.id,quantity:String((seed*(i+3)+i*7)%19+1),unit_price:p.selling_price,required_stock:String((seed*(i+3)+i*7)%19+1)}));
  const units=items.flatMap(l=>Array(Number(l.quantity)).fill(l.unit_price)).sort((a,b)=>Number(a)-Number(b));
  const expected=[];for(let i=0;i+6<=units.length;i+=6)expected.push(units[i]);
  const plan=D.mixedPlan(rule,items,products,{'1':'100','2':'100','3':'100'},{});
  assert.equal(plan.earned,String(expected.length));assert.deepEqual(plan.groups.flatMap(b=>Array(Number(b.quantity)).fill(b.unit_price)),expected);
  assert.deepEqual(D.mixedPlan(rule,[...items].reverse(),products,{'1':'100','2':'100','3':'100'},{}).groups,plan.groups);
 }
});
test('large quantities use bounded bands and exact integers',()=>{
 const q='9223372036854775806',plan=D.mixedPlan(rule,[{product_id:'1',quantity:q,unit_price:'100.00',required_stock:q}],products,{'1':q},{});
 assert.equal(plan.earned,String(BigInt(q)/6n));assert.equal(plan.groups.length,1);
});
test('reward choices reflect both price bands and residual physical stock',()=>{
 const lines=[{product_id:'1',quantity:'3',unit_price:'100.00',required_stock:'3'},{product_id:'2',quantity:'3',unit_price:'220.00',required_stock:'3'}];
 const plan=D.mixedPlan(rule,lines,products,{'1':'3','2':'20','3':'1'},{});
 assert.equal(plan.choices.find(c=>c.product_id==='1').additional_quantity,'0');
 assert.equal(plan.choices.find(c=>c.product_id==='2').additional_quantity,'0');
 assert.equal(plan.choices.find(c=>c.product_id==='3').additional_quantity,'1');
 assert.throws(()=>D.mixedPlan(rule,lines,products,{'1':'20','2':'20','3':'20'},{'2':'1'}),/cheapest/);
});
