'use strict';
const {spawn}=require('node:child_process'),fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const input=JSON.parse(fs.readFileSync(0,'utf8')),f=input.fixtures;
const profile=path.join(input.directory,'browser-profile');fs.mkdirSync(profile);
const browser=spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--remote-debugging-port=0','--remote-debugging-address=127.0.0.1','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:['ignore','ignore','pipe']});
browser.stderr.on('data',chunk=>fs.appendFileSync(path.join(input.directory,'edge-stderr.log'),chunk));
let ws,seq=0,sessionId,checks=0;const waiting=new Map();
const check=(ok,label)=>{assert.ok(ok,label);checks++;};
async function connect(){const endpoint=await new Promise((resolve,reject)=>{let buffer='';const timer=setTimeout(()=>reject(Error('Headless Edge did not start.')),20000);browser.stderr.on('data',chunk=>{buffer+=chunk;const match=buffer.match(/DevTools listening on (ws:\/\/[^\s]+)/);if(match){clearTimeout(timer);resolve(match[1]);}});browser.on('exit',()=>reject(Error('Browser exited.')));});ws=new WebSocket(endpoint);await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject;});ws.onmessage=e=>{const d=JSON.parse(e.data);if(d.id){const p=waiting.get(d.id);if(p){waiting.delete(d.id);d.error?p.reject(Error(d.error.message)):p.resolve(d.result);}}};const target=await send('Target.createTarget',{url:'about:blank'},false);sessionId=(await send('Target.attachToTarget',{targetId:target.targetId,flatten:true},false)).sessionId;await send('Page.enable');await send('Runtime.enable');await send('Network.enable');}
function send(method,params={},session=true){const id=++seq;return new Promise((resolve,reject)=>{waiting.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params,...(session?{sessionId}:{})}));});}
async function evaluate(expression){const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;}
async function until(expression,label,timeout=20000){const start=Date.now();while(Date.now()-start<timeout){if(await evaluate(expression))return;await new Promise(r=>setTimeout(r,80));}throw Error('Timed out: '+label+'; '+await evaluate('document.getElementById("return-message")?.textContent||document.body.textContent.slice(0,400)'));}
async function navigate(route){await send('Page.navigate',{url:input.base+route});await until("document.readyState==='complete'",'document loaded');}
async function login(actor='rep1',mode='enabled'){await evaluate(`fetch('/test/session',{method:'POST',headers:{'Content-Type':'application/json','X-Isolation-Test-Token':${JSON.stringify(input.key)}},body:JSON.stringify(${JSON.stringify({actor,mode})})}).then(r=>r.json())`);}
async function page(actor='rep1',mode='enabled'){await login(actor,mode);await navigate('/rep/invoices/hybrid/new');if(actor!=='admin'&&actor!=='anonymous'&&mode!=='feature-disabled'){await until('document.getElementById("hybrid-editor")?.disabled===false','Hybrid initialized');await set('hybrid-customer',f.customers[actor]);}}
async function set(id,value,event='change'){await evaluate(`(()=>{const e=document.getElementById(${JSON.stringify(id)});e.value=${JSON.stringify(value)};e.dispatchEvent(new Event(${JSON.stringify(event)},{bubbles:true}));})()`);}
async function click(id){await evaluate(`document.getElementById(${JSON.stringify(id)}).click()`);}
async function add(product,quantity='1'){await set('hybrid-product',f.products[product]);await click('hybrid-add');await set('hybrid-qty-'+f.products[product],quantity,'input');}
async function preview(){await click('hybrid-preview');await until('!document.getElementById("hybrid-review").hidden||document.getElementById("hybrid-message").getAttribute("role")==="alert"','preview outcome');}
async function total(label){return evaluate(`(()=>{const n=[...document.querySelectorAll('#hybrid-totals dt')].find(e=>e.textContent===${JSON.stringify(label)});return n?.nextElementSibling.textContent;})()`);}
async function lineFact(product,label){return evaluate(`(()=>{const n=[...document.querySelectorAll('[data-product-id="${f.products[product]}"] dt')].find(e=>e.textContent===${JSON.stringify(label)});return n?.nextElementSibling.textContent;})()`);}
async function realLogin(actor){
 await navigate('/login?language=en');
 if(await evaluate('!document.querySelector("[name=username]")')){await evaluate(`(()=>{const form=document.querySelector('form[action="/logout"]');return fetch('/logout',{method:'POST',body:new URLSearchParams(new FormData(form))});})()`);await navigate('/login?language=en');}
 await until('!!document.querySelector("[name=username]")','login form');
 await evaluate(`(()=>{document.querySelector('[name=username]').value=${JSON.stringify(input.logins[actor].username)};document.querySelector('[name=password]').value=${JSON.stringify(input.logins[actor].password)};document.querySelector('[name=password]').form.requestSubmit();})()`);
 await until('!!document.querySelector(".dashboard-main")','authenticated dashboard');
}
(async()=>{
 await connect();const residuals=[];
 for(const viewport of [{width:1365,height:900},{width:390,height:844}]){
  await send('Emulation.setDeviceMetricsOverride',{...viewport,deviceScaleFactor:1,mobile:viewport.width<500});
  await realLogin('rep1');await navigate('/rep/invoices/hybrid/new?language=si-LK');await until('document.getElementById("hybrid-editor")?.disabled===false','Hybrid editor ready');
  await until('document.getElementById("hybrid-preview").textContent.includes("පරීක්ෂා")','scoped Sinhala catalogue');
  await set('hybrid-customer',f.customers.rep1);await add('glucose','8');await set('hybrid-discount-'+f.products.glucose,'25');await set('hybrid-received','10000.00','input');await preview();
  await until('document.querySelector("#hybrid-review-lines dt")?.textContent.includes("ගෙවනු")','dynamic Sinhala review');
  check(await evaluate('document.getElementById("hybrid-confirm").disabled'),'posting hold preserved');
  check(await evaluate('document.getElementById("hybrid-discount-'+f.products.glucose+'").value')==='25','discount protocol unchanged');
  const shown=await evaluate('document.getElementById("hybrid-review-lines").innerText');check(shown.includes('750.00')&&shown.includes('50.00'),'original monetary components retained');
  check(await evaluate('document.documentElement.scrollWidth<=innerWidth+2'),'checkout no page overflow '+viewport.width);
  let shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(input.directory,'sinhala-checkout-'+viewport.width+'.png'),Buffer.from(shot.data,'base64'));
  residuals.push({screen:'checkout',width:viewport.width,text:await evaluate(`(()=>{const w=document.createTreeWalker(document.querySelector('main'),NodeFilter.SHOW_TEXT),a=[];while(w.nextNode()){const n=w.currentNode,t=n.nodeValue.trim();if(/[A-Za-z]{3}/.test(t)&&!n.parentElement.closest('script,style,pre,code,option')&&!/[\u0D80-\u0DFF]/.test(t)&&!/SYNTHETIC|^LKR [0-9, .-]+$/.test(t))a.push(t);}return [...new Set(a)];})()`)});
  await realLogin('admin');await navigate('/admin/invoices/hybrid/returns?language=si-LK');await until('document.getElementById("return-preview")?.textContent.includes("පරීක්ෂා")','Sinhala returns');
  check(await evaluate('document.getElementById("return-manager-action").value')==='REVIEW','translated manager action retains enum');check(await evaluate('document.getElementById("return-manager-from").value')==='QUARANTINE','translated condition retains enum');
  check(await evaluate('document.documentElement.scrollWidth<=innerWidth+2'),'returns no page overflow '+viewport.width);
  shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(input.directory,'sinhala-returns-'+viewport.width+'.png'),Buffer.from(shot.data,'base64'));
  residuals.push({screen:'returns',width:viewport.width,text:await evaluate(`(()=>{const w=document.createTreeWalker(document.querySelector('main'),NodeFilter.SHOW_TEXT),a=[];while(w.nextNode()){const n=w.currentNode,t=n.nodeValue.trim();if(/[A-Za-z]{3}/.test(t)&&!n.parentElement.closest('script,style,pre,code,option')&&!/[\u0D80-\u0DFF]/.test(t)&&!/SYNTHETIC|^LKR [0-9, .-]+$/.test(t))a.push(t);}return [...new Set(a)];})()`)});
 }
 // Additional authenticated report and printable-document layouts at both widths.
 for(const width of [1365,390]){
  await send('Emulation.setDeviceMetricsOverride',{width,height:844,deviceScaleFactor:1,mobile:width<500});
  await navigate('/admin/reports/hybridreturns?language=si-LK');await until('!!document.querySelector("[data-source-field=original_gross_reversed_lkr]")','scoped linked-credit report');
  check(await evaluate('document.querySelector("thead").textContent.includes("ආපසු හරවන මුල් දළ වටිනාකම") && document.querySelector("[data-source-field=original_gross_reversed_lkr]").getAttribute("data-label").includes("ආපසු හරවන මුල් දළ වටිනාකම")'),'Sinhala report headers '+width);check(await evaluate('document.documentElement.scrollWidth<=innerWidth+2'),'report responsive overflow '+width);
  let shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(input.directory,'sinhala-report-'+width+'.png'),Buffer.from(shot.data,'base64'));
  await navigate('/admin/invoices/'+input.document.invoice+'/receipt?paper=58&document_lang=si-LK');await until('!!document.querySelector(".hybrid-sheet")','stored Sinhala receipt');await evaluate('document.fonts.ready.then(()=>true)');
  check(await evaluate('document.documentElement.lang')==='si-LK','Sinhala stored document '+width);check(await evaluate('(()=>{const w=document.querySelector(".hybrid-sheet").getBoundingClientRect().width;return w>170&&w<200;})()'),'retained 58mm printable CSS '+width);
  shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(input.directory,'sinhala-receipt-'+width+'.png'),Buffer.from(shot.data,'base64'));
 }
 fs.writeFileSync(path.join(input.directory,'locale-residuals.json'),JSON.stringify({checks,residuals,native_review:'NOT PERFORMED'},null,2));console.log(`Stage 6C Sinhala Edge: ${checks} checks passed; desktop/mobile software checks only; native review pending.`);
})().catch(e=>{console.error(e.stack);process.exitCode=1;}).finally(async()=>{try{if(ws?.readyState===1)await send('Browser.close',{},false);}catch{}ws?.close();browser.kill();});