'use strict';
const {spawn}=require('node:child_process'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const input=JSON.parse(fs.readFileSync(0,'utf8'));const profile=path.resolve('storage','promo-choice-offline-'+crypto.randomUUID());fs.mkdirSync(profile,{recursive:true});
const browser=spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--remote-debugging-port=0','--remote-debugging-address=127.0.0.1','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:['ignore','ignore','pipe']});
let ws,seq=0,sessionId,count=0;const waiting=new Map();
const check=(ok,label)=>{assert.ok(ok,label);count++;};
async function connect(){const endpoint=await new Promise((resolve,reject)=>{let buffer='';const timer=setTimeout(()=>reject(Error('Headless browser did not start.')),20000);browser.stderr.on('data',chunk=>{buffer+=chunk;const found=buffer.match(/DevTools listening on (ws:\/\/[^\s]+)/);if(found){clearTimeout(timer);resolve(found[1]);}});browser.on('exit',()=>reject(Error('Headless browser exited.')));});ws=new WebSocket(endpoint);await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject;});ws.onmessage=event=>{const data=JSON.parse(event.data);if(data.id){const p=waiting.get(data.id);if(p){waiting.delete(data.id);data.error?p.reject(Error(data.error.message)):p.resolve(data.result);}}};const target=await send('Target.createTarget',{url:'about:blank'},false);sessionId=(await send('Target.attachToTarget',{targetId:target.targetId,flatten:true},false)).sessionId;await send('Page.enable');await send('Runtime.enable');await send('Network.enable');}
function send(method,params={},session=true){const id=++seq;return new Promise((resolve,reject)=>{waiting.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params,...(session?{sessionId}:{})}));});}
async function evaluate(expression){const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error('Browser evaluation failed: '+(r.exceptionDetails.exception?.description||r.exceptionDetails.text));return r.result.value;}
async function until(expression,label,timeout=15000){const start=Date.now();while(Date.now()-start<timeout){if(await evaluate(expression))return;await new Promise(r=>setTimeout(r,100));}throw Error('Timed out: '+label+'; '+await evaluate('document.getElementById("invoice-live-summary")?.textContent'));}
async function navigate(url){await send('Page.navigate',{url});await until(`document.readyState==='complete'&&location.href===${JSON.stringify(url)}`,'document loaded '+url);}
async function offline(on){await send('Network.emulateNetworkConditions',{offline:on,latency:0,downloadThroughput:on?0:-1,uploadThroughput:on?0:-1});}
async function fillFirstProduct(quantity){
 await evaluate('document.getElementById("pos-search").value='+JSON.stringify(input.checkout_skus[0])+';document.getElementById("pos-search").dispatchEvent(new Event("input"))');
 await until('!!document.querySelector('+JSON.stringify('.pos-product[data-product-id="'+input.products[0]+'"] button')+')','catalogue product');
 await evaluate('document.querySelector('+JSON.stringify('.pos-product[data-product-id="'+input.products[0]+'"] button')+').click()');
 await evaluate('document.querySelector(".pos-stepper input").value='+JSON.stringify(quantity)+';document.querySelector(".pos-stepper input").dispatchEvent(new Event("input",{bubbles:true}))');
}
const ONLINE_MSG_EN='This promotion benefit mode requires an online connection to complete checkout.';
(async()=>{
 await connect();await navigate(input.base+'/login');
 await evaluate(`(async()=>{const h=await(await fetch('/login')).text();const csrf=h.match(/name="csrf_token" value="([a-f0-9]{64})"/)[1];await fetch('/login',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf_token:csrf,username:${JSON.stringify(input.actors.rep.username)},password:${JSON.stringify(input.password)}})});})()`);

 // Connection lost before review: exact bilingual message, no posting possible.
 await navigate(input.base+'/rep/invoices/new?language=en');
 await evaluate('document.getElementById("customer_id").value='+JSON.stringify(input.customer)+';document.getElementById("customer_id").dispatchEvent(new Event("change",{bubbles:true}));');
 await fillFirstProduct('4');
 await evaluate('document.getElementById("benefit_mode").value="PROMOTION_DISCOUNT_INSTEAD";document.getElementById("benefit_mode").dispatchEvent(new Event("change",{bubbles:true}));');
 await evaluate('document.getElementById("promotion_discount_choice").value="25";document.getElementById("promotion_discount_choice").dispatchEvent(new Event("change",{bubbles:true}));');
 await until('document.getElementById("invoice-live-summary").textContent.includes("Promotion discount instead")','online benefit preview');
 await offline(true);
 await evaluate('document.querySelector(".pos-stepper input").value="6";document.querySelector(".pos-stepper input").dispatchEvent(new Event("input",{bubbles:true}))');
 await until('document.getElementById("invoice-live-summary").textContent==='+JSON.stringify(ONLINE_MSG_EN),'exact connectivity message');
 check(true,'Exact connectivity-loss message shown');
 check(await evaluate('!document.querySelector("[data-invoice-confirmation]")'),'No stale review survives the connection loss');
 check(await evaluate('document.getElementById("customer_id").value==='+JSON.stringify(input.customer)),'Customer selection preserved as UI state');
 check(await evaluate('document.getElementById("benefit_mode").value==="PROMOTION_DISCOUNT_INSTEAD"'),'Benefit mode preserved as nonfinancial UI state');
 const forged=await evaluate(`(async()=>{try{const f=new FormData();f.set('benefit_policy','PROMO_CHOICE_V1');f.set('benefit_mode','FREE_PROMOTION');const r=await fetch('/api/v1/rep/invoices/preview',{method:'POST',body:f});return r.status;}catch(e){return 'offline';}})()`);
 check(forged==='offline','No financial authorization cached for offline posting');

 // Reconnection requires a renewed authoritative review, then issue succeeds once.
 await offline(false);
 await evaluate('document.querySelector(".pos-stepper input").dispatchEvent(new Event("input",{bubbles:true}))');
 await until('document.getElementById("invoice-live-summary").textContent.includes("Promotion discount instead")','renewed preview after reconnection');
 check(await evaluate('document.getElementById("invoice-live-summary").textContent.includes("753.00")'),'Recalculated totals after reconnection');
 await evaluate('document.querySelector(".pos-pay").click()');
 await until('!!document.querySelector("input[name=review_hash]")','review postback after reconnection');
 await evaluate('document.querySelector("button[value=issue]").click()');
 await until('location.href.split("/").pop().match(/^[0-9a-f-]{36}$/)&&document.readyState==="complete"','issued after reconnection');
 const invoiceId=await evaluate('location.href.split("/").pop()');
 check(/^[0-9a-f-]{36}$/.test(invoiceId),'Exactly one invoice issued after recovery');

 // Sinhala connectivity message.
 await navigate(input.base+'/rep/invoices/new?language=si-LK');
 await evaluate('document.getElementById("customer_id").value='+JSON.stringify(input.customer)+';document.getElementById("customer_id").dispatchEvent(new Event("change",{bubbles:true}));');
 await fillFirstProduct('4');
 await evaluate('document.getElementById("benefit_mode").value="FREE_PROMOTION";document.getElementById("benefit_mode").dispatchEvent(new Event("change",{bubbles:true}));');
 await until('document.getElementById("invoice-live-summary").textContent.includes("නොමිලේ")','Sinhala online preview');
 await offline(true);
 await evaluate('document.querySelector(".pos-stepper input").dispatchEvent(new Event("input",{bubbles:true}))');
 await until('document.getElementById("invoice-live-summary").textContent.includes("සේවාදායක සම්බන්ධතාවයක් අවශ්‍ය වේ")','Sinhala connectivity message');
 check(true,'Sinhala connectivity-loss message shown');
 await offline(false);
 console.log(count+' benefit connectivity-loss checks passed.');
})().catch(e=>{console.error(e.message);process.exitCode=1;}).finally(async()=>{try{await offline(false);}catch{}if(ws?.readyState===WebSocket.OPEN){try{await send('Browser.close',{},false);}catch{}ws.close();}browser.kill();await new Promise(r=>setTimeout(r,600));if(path.dirname(profile)===path.resolve('storage')&&/^promo-choice-offline-[a-f0-9-]+$/.test(path.basename(profile)))await fs.promises.rm(profile,{recursive:true,force:true,maxRetries:12,retryDelay:300});setTimeout(()=>process.exit(process.exitCode||0),500).unref();});
