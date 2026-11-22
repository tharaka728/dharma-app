'use strict';
const {spawn}=require('node:child_process'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const input=JSON.parse(fs.readFileSync(0,'utf8'));const profile=path.resolve('storage','promo-choice-browser-'+crypto.randomUUID());fs.mkdirSync(profile,{recursive:true});
const browser=spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--remote-debugging-port=0','--remote-debugging-address=127.0.0.1','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:['ignore','ignore','pipe']});
let ws,seq=0,sessionId,count=0;const waiting=new Map();
const check=(ok,label)=>{assert.ok(ok,label);count++;};
async function connect(){const endpoint=await new Promise((resolve,reject)=>{let buffer='';const timer=setTimeout(()=>reject(Error('Headless browser did not start.')),20000);browser.stderr.on('data',chunk=>{buffer+=chunk;const found=buffer.match(/DevTools listening on (ws:\/\/[^\s]+)/);if(found){clearTimeout(timer);resolve(found[1]);}});browser.on('exit',()=>reject(Error('Headless browser exited.')));});ws=new WebSocket(endpoint);await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject;});ws.onmessage=event=>{const data=JSON.parse(event.data);if(data.id){const p=waiting.get(data.id);if(p){waiting.delete(data.id);data.error?p.reject(Error(data.error.message)):p.resolve(data.result);}}};const target=await send('Target.createTarget',{url:'about:blank'},false);sessionId=(await send('Target.attachToTarget',{targetId:target.targetId,flatten:true},false)).sessionId;await send('Page.enable');await send('Runtime.enable');await send('Network.enable');}
function send(method,params={},session=true){const id=++seq;return new Promise((resolve,reject)=>{waiting.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params,...(session?{sessionId}:{})}));});}
async function evaluate(expression){const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error('Browser evaluation failed: '+(r.exceptionDetails.exception?.description||r.exceptionDetails.text));return r.result.value;}
async function until(expression,label,timeout=15000){const start=Date.now();while(Date.now()-start<timeout){if(await evaluate(expression))return;await new Promise(r=>setTimeout(r,100));}throw Error('Timed out: '+label+'; '+await evaluate('JSON.stringify({summary:document.getElementById("invoice-live-summary")?.textContent,invalid:[...document.querySelectorAll(":invalid")].map(n=>({id:n.id,name:n.name,value:n.value,message:n.validationMessage})),title:document.title})'));}
async function navigate(url){await send('Page.navigate',{url});await until(`document.readyState==='complete'&&location.href===${JSON.stringify(url)}`,'document loaded '+url);}
async function shot(name){fs.writeFileSync(path.resolve('storage',name),Buffer.from((await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true})).data,'base64'));}
async function fillFirstProduct(quantity){
 await evaluate('document.getElementById("pos-search").value='+JSON.stringify(input.checkout_skus[0])+';document.getElementById("pos-search").dispatchEvent(new Event("input"))');
 await until('!!document.querySelector('+JSON.stringify('.pos-product[data-product-id="'+input.products[0]+'"] button')+')','catalogue product');
 await evaluate('document.querySelector('+JSON.stringify('.pos-product[data-product-id="'+input.products[0]+'"] button')+').click()');
 await evaluate('document.querySelector(".pos-stepper input").value='+JSON.stringify(quantity)+';document.querySelector(".pos-stepper input").dispatchEvent(new Event("input",{bubbles:true}))');
}
(async()=>{
 await connect();await navigate(input.base+'/login');
 await evaluate(`(async()=>{const h=await(await fetch('/login')).text();const csrf=h.match(/name="csrf_token" value="([a-f0-9]{64})"/)[1];await fetch('/login',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf_token:csrf,username:${JSON.stringify(input.actors.rep.username)},password:${JSON.stringify(input.password)}})});})()`);

 // Gate-open checkout UI: real benefit controls replace the simulation.
 await navigate(input.base+'/rep/invoices/new?language=en');
 check(await evaluate('document.querySelectorAll("#invoice-benefit-fields input[name=benefit_mode]").length===3&&!document.getElementById("benefit-simulation-mode")'),'Benefit controls replace simulation when the gate is open');
 check(await evaluate('!!document.querySelector("#pos-checkout-panel #invoice-benefit-fields")'),'Benefit controls sit inside the checkout panel');
 check(await evaluate('document.getElementById("benefit_mode_free_promotion").checked&&![...document.querySelectorAll("input[name=benefit_mode]")].some(r=>r.value==="")'),'Pilot representative must choose a benefit mode');
 check(await evaluate('document.getElementById("promotion_discount_choice_25").disabled&&document.getElementById("promotion-discount-rate").hidden&&!document.querySelector("input[name=benefit_policy]").disabled'),'Rate choice rests until a discount mode is chosen');
 await evaluate('document.getElementById("customer_id").value='+JSON.stringify(input.customer)+';document.getElementById("customer_id").dispatchEvent(new Event("change",{bubbles:true}));');
 await fillFirstProduct('4');
 await until('!document.querySelector(".pos-pay").disabled&&!!document.querySelector(".pos-free-units")','baseline preview');
 check(await evaluate('document.querySelector(".pos-free-units").textContent.endsWith("2")'),'Baseline keeps free units');
 check(await evaluate('document.getElementById("invoice-live-summary").textContent.includes("Free promotion")'),'Baseline preview is the free-promotion mode');
 await shot('promo-choice-form-baseline.png');

 // Select PROMOTION_DISCOUNT_INSTEAD at 25%: legacy fieldset leaves the form, suppression + discount preview.
 await evaluate('document.getElementById("benefit_mode_promotion_discount_instead").click()');
 check(await evaluate('document.querySelector("#invoice_discount_choice").disabled&&!document.getElementById("promotion_discount_choice_25").disabled&&!document.getElementById("promotion-discount-rate").hidden'),'Benefit and legacy discount controls are mutually exclusive');
 check(await evaluate("document.querySelector('.rate-chip[data-rate=\"0\"]').hidden"),'Zero-percent choice stays hidden outside the ordinary mode');
 await evaluate('document.getElementById("promotion_discount_choice_25").click()');
 await until('document.getElementById("invoice-live-summary").textContent.includes("Promotion discount instead")','benefit preview summary');
 const summary=await evaluate('document.getElementById("invoice-live-summary").textContent');
 check(summary.includes('502.00')&&summary.includes('125.50')&&summary.includes('376.50'),'Server preview: eligible 502.00, discount 125.50, total 376.50: '+summary);
 check(await evaluate('document.querySelector(".pos-free-units").textContent.endsWith("0")'),'Free units suppressed in preview');
 check(await evaluate('(()=>{const keys=[...new FormData(document.getElementById("invoice-form"))].map(([k])=>k);return keys.includes("benefit_mode")&&keys.includes("benefit_policy")&&!keys.includes("discount_policy")&&!keys.includes("invoice_discount_choice")})()'),'Benefit fields post without legacy discount fields');
 await shot('promo-choice-form-promo-discount.png');

 // Review postback binds the benefit decision, issue persists it.
 await evaluate('document.querySelector(".pos-pay").click()');
 await until('!!document.querySelector("input[name=review_hash]")','review postback');
 const reviewText=await evaluate('document.querySelector("[data-invoice-confirmation]").textContent');
 check(reviewText.includes('Benefit mode')&&reviewText.includes('Promotion discount instead')&&reviewText.includes('125.50'),'Review shows benefit decision: '+reviewText.slice(0,400));
 await shot('promo-choice-review-promo-discount.png');
 await evaluate('document.querySelector("button[value=issue]").click()');
 await until('location.href.includes("/rep/invoices/")&&document.readyState==="complete"&&!!document.querySelector("main")','issued invoice page');
 const invoiceUrl=await evaluate('location.href');
 const invoiceId=invoiceUrl.split('/').pop();
 check(/^[0-9a-f-]{36}$/.test(invoiceId),'Issued invoice id');
 check(await evaluate('document.body.textContent.includes("Promotion discount instead")||document.body.textContent.includes("Benefit")'),'Invoice details mention benefit mode');
 await shot('promo-choice-invoice-details.png');

 // Receipt output distinguishes the mode.
 await navigate(input.base+'/rep/invoices/'+invoiceId+'/receipt?document_lang=en');
 const receiptText=await evaluate('document.querySelector(".totals").textContent');
 check(receiptText.includes('Benefit mode')&&receiptText.includes('Promotion discount instead')&&receiptText.includes('25.00% on eligible LKR 502.00: LKR 125.50'),'Receipt shows benefit mode and allocation: '+receiptText.slice(0,300));
 await shot('promo-choice-receipt-promo-discount.png');
 await navigate(input.base+'/rep/invoices/'+invoiceId+'/receipt?document_lang=si-LK');
 check(await evaluate('document.querySelector(".totals").textContent.includes("ප්‍රතිලාභ ක්‍රමය")'),'Sinhala receipt label');

 // FREE_PROMOTION keeps free units through issue.
 await navigate(input.base+'/rep/invoices/new?language=en');
 await evaluate('document.getElementById("customer_id").value='+JSON.stringify(input.customer)+';document.getElementById("customer_id").dispatchEvent(new Event("change",{bubbles:true}));');
 await fillFirstProduct('4');
 await evaluate('document.getElementById("benefit_mode_free_promotion").click()');
 check(await evaluate('!document.getElementById("free-promotion-info").hidden&&document.getElementById("promotion-discount-rate").hidden'),'Free mode shows the eligibility note and hides the rate choices');
 await until('document.getElementById("invoice-live-summary").textContent.includes("Free promotion")','free mode preview');
 check(await evaluate('document.querySelector(".pos-free-units").textContent.endsWith("2")'),'Free mode keeps free units in preview');
 await evaluate('document.querySelector(".pos-pay").click()');
 await until('!!document.querySelector("input[name=review_hash]")','free mode review');
 await evaluate('document.querySelector("button[value=issue]").click()');
 await until('location.href.split("/").pop().match(/^[0-9a-f-]{36}$/)&&document.readyState==="complete"','free mode issued');
 const freeId=await evaluate('location.href.split("/").pop()');
 await navigate(input.base+'/rep/invoices/'+freeId+'/receipt?document_lang=en');
 check(await evaluate('document.querySelector(".totals").textContent.includes("Free promotion")'),'Free mode receipt');

 // Forgery with the gate open is still refused.
 const forged=await evaluate(`(async()=>{const f=new FormData();f.set('benefit_policy','INVOICE_PERCENT_V1');f.set('benefit_mode','FREE_PROMOTION');const r=await fetch('/api/v1/rep/invoices/preview',{method:'POST',body:f});const b=await r.json().catch(()=>({}));return r.status+':'+JSON.stringify(b)})()`);
 check(forged.startsWith('409')||forged.startsWith('403'),'Unknown policy refused with the gate open: '+forged);

 // Sinhala UI labels.
 await navigate(input.base+'/rep/invoices/new?language=si-LK');
 check(await evaluate('document.querySelector("#invoice-benefit-fields legend").textContent==="ප්‍රවර්ධනය සහ වට්ටම"'),'Sinhala benefit fieldset legend');
 check(await evaluate('[...document.querySelectorAll(".benefit-card strong")].some(n=>n.textContent.includes("නොමිලේ භාණ්ඩ වෙනුවට"))'),'Sinhala benefit mode options');
 await shot('promo-choice-form-sinhala.png');

 console.log(count+' benefit-mode browser checks passed.');
})().catch(e=>{console.error(e.message);process.exitCode=1;}).finally(async()=>{if(ws?.readyState===WebSocket.OPEN){try{await send('Browser.close',{},false);}catch{}ws.close();}browser.kill();await new Promise(r=>setTimeout(r,600));if(path.dirname(profile)===path.resolve('storage')&&/^promo-choice-browser-[a-f0-9-]+$/.test(path.basename(profile)))await fs.promises.rm(profile,{recursive:true,force:true,maxRetries:12,retryDelay:300});setTimeout(()=>process.exit(process.exitCode||0),500).unref();});
