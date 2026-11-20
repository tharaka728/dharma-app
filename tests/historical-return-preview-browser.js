'use strict';
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),{spawn}=require('node:child_process');
const input=JSON.parse(fs.readFileSync(0,'utf8')),profile=fs.mkdtempSync(path.join(os.tmpdir(),'dharma-preview-'));
const browser=spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',['--headless=new','--disable-gpu','--no-first-run','--disable-background-networking','--remote-debugging-port=0','--remote-debugging-address=127.0.0.1','--user-data-dir='+profile,'about:blank'],{windowsHide:true,stdio:['ignore','ignore','pipe']});
let ws,seq=0,session,count=0;const pending=new Map(),errors=[];
function send(method,params={},attached=true){const id=++seq;return new Promise((resolve,reject)=>{pending.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params,...(attached?{sessionId:session}:{})}));});}
async function run(expression){const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.text);return r.result.value;}
async function until(expression,label){for(let i=0;i<120;i++){if(await run(expression))return;await new Promise(r=>setTimeout(r,100));}throw Error('Timeout: '+label);}
function check(ok,label){if(!ok)throw Error(label);count++;}
async function main(){
 const endpoint=await new Promise((resolve,reject)=>{let data='';const timer=setTimeout(()=>reject(Error('Browser startup timeout')),20000);browser.stderr.on('data',b=>{data+=b;const m=data.match(/DevTools listening on (ws:\/\/127\.0\.0\.1:[^\s]+)/);if(m){clearTimeout(timer);resolve(m[1]);}});browser.once('exit',()=>reject(Error('Browser exited')));});
 ws=new WebSocket(endpoint);await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject;});ws.onmessage=event=>{const d=JSON.parse(event.data);if(d.id){const p=pending.get(d.id);if(p){pending.delete(d.id);d.error?p.reject(Error(d.error.message)):p.resolve(d.result);}}else if(d.method==='Runtime.exceptionThrown')errors.push(d.params.exceptionDetails.text);};
 const target=await send('Target.createTarget',{url:'about:blank'},false);session=(await send('Target.attachToTarget',{targetId:target.targetId,flatten:true},false)).sessionId;await send('Page.enable');await send('Runtime.enable');
 await send('Page.navigate',{url:input.base+'/login'});await until('!!document.querySelector("[name=csrf_token]")','login');
 await run(`document.querySelector('[name=username]').value=${JSON.stringify(input.username)};document.querySelector('[name=password]').value=${JSON.stringify(input.password)};document.querySelector('form').requestSubmit()`);await until('location.pathname==="/rep"','authenticated');
 for(const lang of ['en','si-LK'])for(const width of [393,768,1366,1920]){
  await send('Emulation.setDeviceMetricsOverride',{width,height:850,deviceScaleFactor:1,mobile:width<768});
  await send('Page.navigate',{url:input.base+'/rep/invoices/new?language='+lang});await until('document.readyState==="complete"&&!!document.querySelector("#historical-return-preview")','preview page');
  await run('document.querySelector("[data-preview-toggle]").click()');check(await run('!document.querySelector("[data-preview-panel]").hidden'),'Panel opens');
  check(await run('document.documentElement.scrollWidth<=innerWidth+1'),'No page overflow '+lang+' '+width);
  check(await run('[...document.querySelectorAll("[data-preview-year]")].every(s=>s.options.length===2&&s.options[0].value==="2024"&&s.options[1].value==="2025")'),'Year selectors');
  check(await run('!document.querySelector("#historical-return-preview [name]")'),'Preview has no submitted return fields');
  check(await run('(()=>{const q=document.querySelector("[data-preview-quantity]");q.value="0";return q.validity.rangeUnderflow&&q.form===null})()'),'Quantity validation does not block invoice form');
  await run('document.querySelector("[data-preview-quantity]").value="1"');
  await run(`document.querySelector("[data-preview-search]").value=${JSON.stringify(input.products.P012.sku)};document.querySelector("[data-preview-search]").dispatchEvent(new Event("input",{bubbles:true}))`);
  await until(`!!document.querySelector('[data-preview-products] button[data-product-id="${input.products.P012.id}"]')`,'P012 search');
  check(await run('!document.querySelector("[data-preview-products]").dataset.selectedId&&document.querySelector("[data-preview-selected]").textContent.length>0'),'Typed text is not a selection');
  await run(`document.querySelector('[data-preview-products] button[data-product-id="${input.products.P012.id}"]').click()`);
  check(await run('document.querySelector("[data-preview-status]").textContent.includes("75.00")'),'Approved 2024 price');
  await run('document.querySelector("[data-preview-year]").value="2025";document.querySelector("[data-preview-year]").dispatchEvent(new Event("change",{bubbles:true}))');
  await until('document.querySelector("[data-preview-status]").textContent.includes("not configured")||document.querySelector("[data-preview-status]").textContent.includes("සකසා නැත")','missing price');
  check(await run(`document.querySelector("[data-preview-products]").dataset.selectedId===${JSON.stringify(input.products.P012.id)}`),'Selected product retained when year changes');
  const top=await run('Math.max(0,document.querySelector("#historical-return-preview").getBoundingClientRect().top+scrollY-20)');
  fs.writeFileSync(path.resolve('storage','return-preview-'+width+'-'+lang+'.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true,clip:{x:0,y:top,width,height:850,scale:1}})).data,'base64'));
  check(await run('!!document.querySelector("#invoice-form")&&!!document.querySelector("button[value=review]")'),'Normal POS controls remain');
 }
 for(const [term,id] of [['P012',input.products.P012.id],['P013',input.products.P013.id],['Preview Herbal Oil',input.products.P012.id],['පරීක්ෂණ බාම්',input.products.P013.id]]){
  await run(`document.querySelector("[data-preview-search]").value=${JSON.stringify(term)};document.querySelector("[data-preview-search]").dispatchEvent(new Event("input",{bubbles:true}))`);
  await until(`!!document.querySelector('[data-preview-products] button[data-product-id="${id}"]')`,'search '+term);check(true,'Search '+term);
 }
 await run('document.querySelector("[data-preview-search]").focus()');await send('Input.dispatchKeyEvent',{type:'keyDown',key:'ArrowDown',code:'ArrowDown',windowsVirtualKeyCode:40});await send('Input.dispatchKeyEvent',{type:'keyUp',key:'ArrowDown',code:'ArrowDown',windowsVirtualKeyCode:40});check(await run('document.activeElement.matches("[data-preview-products] button")'),'Arrow key focuses result');await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});check(await run('!!document.querySelector("[data-preview-products]").dataset.selectedId'),'Keyboard can select a result');
 await send('Emulation.setDeviceMetricsOverride',{width:393,height:850,deviceScaleFactor:1,mobile:true});await send('Emulation.setTouchEmulationEnabled',{enabled:true});
 await run(`document.querySelector("[data-preview-search]").value=${JSON.stringify(input.products.P013.sku)};document.querySelector("[data-preview-search]").dispatchEvent(new Event("input",{bubbles:true}))`);
 await until(`!!document.querySelector('[data-preview-products] button[data-product-id="${input.products.P013.id}"]')`,'touch search');
 await run('(()=>{const b=document.querySelector("[data-preview-products] button");window.scrollTo({top:scrollY+b.getBoundingClientRect().top-300,behavior:"instant"})})()');await until('(()=>{const r=document.querySelector("[data-preview-products] button").getBoundingClientRect();return r.top>=150&&r.bottom<innerHeight-100})()','touch result visible');
 const point=await run('(()=>{const r=document.querySelector("[data-preview-products] button").getBoundingClientRect();return {x:r.left+r.width/2,y:r.top+r.height/2}})()');
 await send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[point]});await send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});
 check(await run(`document.querySelector("[data-preview-products]").dataset.selectedId===${JSON.stringify(input.products.P013.id)}`),'Touch selects a result '+JSON.stringify(point)+' '+JSON.stringify(await run(`(()=>{const e=document.elementFromPoint(${point.x},${point.y});return {element:e?.outerHTML?.slice(0,200),scrollY,viewport:innerHeight}})()`)));
 check(errors.length===0,'Unexpected JavaScript error: '+errors.join(', '));
 console.log(count+' isolated preview browser interaction and responsive checks passed.');
}
main().catch(e=>{console.error(e.message);process.exitCode=1;}).finally(async()=>{if(ws?.readyState===WebSocket.OPEN){try{await send('Browser.close',{},false);}catch{}ws.close();}browser.kill();setTimeout(()=>{try{fs.rmSync(profile,{recursive:true,force:true,maxRetries:5,retryDelay:200});}catch{}process.exit(process.exitCode||0);},600);});
