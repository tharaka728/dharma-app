'use strict';
const {spawn}=require('node:child_process'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const input=JSON.parse(fs.readFileSync(0,'utf8'));const profile=path.resolve('storage','phase11-browser-'+crypto.randomUUID());fs.mkdirSync(profile,{recursive:true});
const browser=spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--remote-debugging-port=0','--remote-debugging-address=127.0.0.1','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:['ignore','ignore','pipe']});
let ws,seq=0,sessionId,count=0;const waiting=new Map();
const check=(ok,label)=>{assert.ok(ok,label);count++;};
async function connect(){const endpoint=await new Promise((resolve,reject)=>{let buffer='';const timer=setTimeout(()=>reject(Error('Headless browser did not start.')),20000);browser.stderr.on('data',chunk=>{buffer+=chunk;const found=buffer.match(/DevTools listening on (ws:\/\/[^\s]+)/);if(found){clearTimeout(timer);resolve(found[1]);}});browser.on('exit',()=>reject(Error('Headless browser exited.')));});ws=new WebSocket(endpoint);await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject;});ws.onmessage=event=>{const data=JSON.parse(event.data);if(data.id){const p=waiting.get(data.id);if(p){waiting.delete(data.id);data.error?p.reject(Error(data.error.message)):p.resolve(data.result);}}};const target=await send('Target.createTarget',{url:'about:blank'},false);sessionId=(await send('Target.attachToTarget',{targetId:target.targetId,flatten:true},false)).sessionId;await send('Page.enable');await send('Runtime.enable');await send('Network.enable');}
function send(method,params={},session=true){const id=++seq;return new Promise((resolve,reject)=>{waiting.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params,...(session?{sessionId}:{})}));});}
async function evaluate(expression){if(/^(?:await\b|\(await\b|OfflineDomain\.available\(await\b)/.test(expression))expression='(async()=>('+expression+'))()';const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error('Browser evaluation failed: '+(r.exceptionDetails.exception?.description||r.exceptionDetails.text));return r.result.value;}
async function until(expression,label,timeout=15000){const start=Date.now();while(Date.now()-start<timeout){if(await evaluate(expression))return;await new Promise(r=>setTimeout(r,100));}throw Error('Timed out: '+label+'; '+await evaluate('document.getElementById("message")?.textContent||document.title'));}
async function login(actor){await evaluate(`(async()=>{const h=await(await fetch('/login')).text();const csrf=h.match(/name="csrf_token" value="([a-f0-9]{64})"/)[1];await fetch('/login',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf_token:csrf,username:${JSON.stringify(actor.username)},password:${JSON.stringify(input.password)}})});})()`);}
async function logout(){await evaluate(`(async()=>{const h=await(await fetch('/rep')).text();const csrf=h.match(/name="csrf_token" value="([a-f0-9]{64})"/)[1];await fetch('/logout',{method:'POST',body:new URLSearchParams({csrf_token:csrf})});})()`);}
async function navigate(url){await send('Page.navigate',{url});await until(`document.readyState==='complete'&&location.href===${JSON.stringify(url)}`,'document loaded');}
(async()=>{
 await connect();await navigate(input.base+'/login');
 check(await evaluate(`fetch('/admin/reports',{redirect:'manual'}).then(r=>r.status===0||r.status===302)`),'Anonymous report denied');
 await login(input.actors.rep);await navigate(input.base+'/rep/reports');
 check(await evaluate(`document.querySelector('h1').textContent.includes('Reports')&&document.body.textContent.includes('Unavailable — refund posting not implemented.')`),'Representative reports and refund limitation');
 for(const type of ['overview','sales','products','customers','locations','representatives','collections','outstanding','refunds','inventory','movements','offline']){
  check(await evaluate(`fetch('/rep/reports/${type}').then(r=>r.status===200)`),'Own report '+type);
  check(await evaluate(`fetch('/rep/reports/${type}/csv').then(async r=>r.status===200&&r.headers.get('Content-Type').includes('text/csv')&&r.headers.get('Cache-Control').includes('no-store')&&(await r.text()).length>5)`),'Private scoped CSV '+type);
 }
 check(await evaluate(`fetch('/rep/reports/sales?rep_id=${input.other_id}').then(r=>r.status===403)`),'Tampered rep report denied');
 check(await evaluate(`fetch('/rep/reports/sales/csv?rep_id=${input.other_id}').then(r=>r.status===403)`),'Tampered rep CSV denied');
 check(await evaluate(`fetch('/rep/reports/reconciliation').then(r=>r.status===403)`),'Rep denied company reconciliation');
 check(await evaluate(`fetch('/admin/reports/sales/csv').then(r=>r.status===403)`),'Rep denied admin exports');
 check(await evaluate(`fetch('/rep/reports',{method:'POST'}).then(r=>r.status===405)`),'No report POST routes');
 check(await evaluate(`fetch('/rep/reports/sales?period=custom&from=2026-02-30&to=2026-03-01').then(r=>r.status===422)`),'Invalid dates rejected');
 await navigate(input.base+'/rep/reports/products?period=today&product_id='+input.product_id);
 check(await evaluate(`document.getElementById('product_id').value===${JSON.stringify(input.product_id)}&&document.body.textContent.includes('Whole-invoice totals')`),'Filter preservation and cohort explanation');
 await evaluate(`document.getElementById('report-table-search').value='nonexistent-row';document.getElementById('report-table-search').dispatchEvent(new Event('input'))`);
 check(await evaluate(`[...document.querySelectorAll('#report-table tbody tr')].every(r=>r.hidden)`),'Visible table search');
 await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
 check(await evaluate(`document.documentElement.scrollWidth<=innerWidth`),'Mobile report fits screen');
 await navigate(input.base+'/rep/reports');
 check(await evaluate(`document.querySelectorAll('#filter-district option').length===26`),'All 25 district options reused');
 await evaluate(`document.getElementById('filter-district').selectedIndex=1;document.getElementById('filter-district').dispatchEvent(new Event('change'))`);
 await until(`document.getElementById('filter-town-status').textContent!=='Loading towns…'`,'dependent towns');
 check(await evaluate(`[...document.querySelectorAll('#filter-town option')].filter(o=>o.value).every(o=>o.dataset.districtId===document.getElementById('filter-district').value)`),'Representative dependent town lookup');
 await logout();await login(input.actors.admin);await navigate(input.base+'/admin/reports/reconciliation');
 check(await evaluate(`document.body.textContent.includes('PASS')&&!document.body.textContent.includes('INVESTIGATE')`),'Admin reconciliation page');
 await navigate(input.base+'/admin');check(await evaluate(`!!document.querySelector('a[href="/admin/reports"]')`),'Admin dashboard Reports navigation');
 await navigate(input.base+'/admin/reports/customers?customer_id='+input.customer_id);
 check(await evaluate(`!!document.querySelector('a[href="/admin/invoices?customer_id=${input.customer_id}"]')`),'Customer purchase-history integration');
 check(await evaluate(`fetch('/admin/reports/customers/csv?customer_id=${input.customer_id}').then(r=>r.text()).then(csv=>csv.includes("'=SUM(1,1)"))`),'Real CSV export neutralizes customer formula');
 const reset=await evaluate(`[...document.querySelectorAll('a')].find(a=>a.textContent==='Reset Filters').getAttribute('href')`);check(reset==='/admin/reports/customers','Reset clears every query filter');
 console.log(`${count} real browser report, export, scope, navigation, mobile and lookup checks passed.`);
})().catch(e=>{console.error(e.message);process.exitCode=1;}).finally(async()=>{
 if(ws?.readyState===WebSocket.OPEN){try{await send('Browser.close',{},false);}catch{}ws.close();}browser.kill();await new Promise(r=>setTimeout(r,600));
 if(path.dirname(profile)===path.resolve('storage')&&/^phase11-browser-[a-f0-9-]+$/.test(path.basename(profile)))fs.rmSync(profile,{recursive:true,force:true,maxRetries:5,retryDelay:300});
 setTimeout(()=>process.exit(process.exitCode||0),500).unref();
});
