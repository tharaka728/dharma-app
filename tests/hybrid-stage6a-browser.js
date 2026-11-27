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
async function set(id,value,event='change'){await evaluate(`(()=>{const e=document.getElementById(${JSON.stringify(id)});e.value=${JSON.stringify(value)};e.dispatchEvent(new Event(${JSON.stringify(event)},{bubbles:true}));})()`);}
async function click(id){await evaluate(`document.getElementById(${JSON.stringify(id)}).click()`);}
(async()=>{
 await connect();await navigate('/admin');check(await evaluate('!document.querySelector(".dashboard-main")'),'anonymous dashboard denied');
 for(const actor of ['admin','rep1','rep2']){
  await login(actor,'feature-disabled');const base=actor==='admin'?'/admin':'/rep';await navigate(base);await until('!!document.querySelector(".dashboard-main")','dashboard');
  check(await evaluate('document.querySelectorAll(".dashboard-metrics > *").length')===4,'four approved dashboard metrics '+actor);
  check(await evaluate('!!document.querySelector(".dashboard-welcome")'),'approved welcome '+actor);
  check(await evaluate(`document.querySelector('.dashboard-primary-action').getAttribute('href')===${JSON.stringify(base+'/invoices/new')}`),'invoice navigation '+actor);
  check(await evaluate('!document.body.textContent.includes("Reports unavailable")'),'dashboard reads '+actor);
  check(await evaluate('[...document.querySelectorAll(".dashboard-metrics .stat-value")].every(e=>!e.textContent.includes("Unavailable"))'),'real dashboard metrics available '+actor);
  const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(input.directory,actor+'-dashboard.png'),Buffer.from(shot.data,'base64'));
 }
 await login('admin','feature-disabled');await navigate('/admin/customers/'+f.customers.rep1);await until('!!document.querySelector(".cp-page")','approved profile');
 check(await evaluate('document.querySelector(".cp-name").textContent.includes("SYNTHETIC")'),'real customer profile');check(await evaluate('document.querySelectorAll(".cp-actions a").length')===3,'profile action links retained');
 check(await evaluate(`document.querySelector('.cp-billing-form [name=customer_id]').value===${JSON.stringify(f.customers.rep1)}`),'billing identity retained');
 check(await evaluate('!!document.querySelector(".cp-billing-form [name=csrf_token]")'),'profile CSRF retained');
 let shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(input.directory,'admin-profile.png'),Buffer.from(shot.data,'base64'));
 await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});await navigate('/admin/customers/'+f.customers.rep1);await until('!!document.querySelector(".cp-page")','phone profile');check(await evaluate('document.documentElement.scrollWidth<=391'),'profile phone no overflow');
 shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});fs.writeFileSync(path.join(input.directory,'phone-profile.png'),Buffer.from(shot.data,'base64'));
 await navigate('/admin');await until('!!document.querySelector(".dashboard-main")','phone dashboard');check(await evaluate('document.documentElement.scrollWidth<=391'),'dashboard phone no overflow');
 await navigate('/admin/invoices/'+input.record.invoice);await until('document.body.textContent.includes("Linked Hybrid credits")','Hybrid invoice reads');check(await evaluate('document.body.textContent.includes("4308.75")'),'linked financial value visible');check(await evaluate('document.querySelectorAll("a[href*=hybrid-returns]").length>=8'),'return and residual documents linked');
 await navigate('/admin/invoices/'+input.record.invoice+'/receipt?paper=58&document_lang=si-LK');await until('!!document.querySelector(".hybrid-sheet")','receipt');check(await evaluate('document.body.textContent.includes("4308.75")'),'receipt liabilities consistent');
 await navigate('/admin/customers/'+f.customers.rep1);await login('rep2','feature-disabled');const denied=await evaluate(`fetch('/admin/customers/${f.customers.rep1}').then(r=>r.status)`);check(denied===403,'rep denied admin profile');const scope=await evaluate(`fetch('/rep/invoices/${input.record.invoice}').then(r=>r.status)`);check([403,404].includes(scope),'cross-rep invoice denial');
 await navigate('/rep');await until('!!document.querySelector(".dashboard-main")','rep after admin navigation');check(await evaluate('!document.body.textContent.includes("Admin dashboard")'),'role navigation remains scoped');
 console.log('Stage 6A combined Edge: '+checks+' checks passed; approved dashboards/profile, phone layouts, linked financial readers, receipt and authorization.');
})().catch(e=>{console.error(e.stack);process.exitCode=1;}).finally(async()=>{try{if(ws){await send('Browser.close',{},false);ws.close();}}catch{}browser.kill();});
