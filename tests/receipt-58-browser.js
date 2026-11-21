'use strict';
/* Optional fixture-only Chromium checks. No authentication or database is used.
   Set PHP_BINARY/BROWSER_BINARY and make playwright-core available via NODE_PATH. */
const {chromium}=require('playwright-core');
const {execFileSync}=require('node:child_process'),http=require('node:http'),fs=require('node:fs'),os=require('node:os'),path=require('node:path'),assert=require('node:assert/strict');
const root=path.resolve(__dirname,'..'),fixtures=fs.mkdtempSync(path.join(os.tmpdir(),'receipt58-'));
const output=path.resolve(process.argv[2]||path.join(root,'storage','receipt58-previews'));
let server,browser;
(async()=>{
 execFileSync(process.env.PHP_BINARY||'php',[path.join(__dirname,'receipt-58-fixture.php'),fixtures],{stdio:'inherit'});
 fs.mkdirSync(output,{recursive:true});
 server=http.createServer((req,res)=>{
  const pathname=new URL(req.url,'http://localhost').pathname;
  const base=pathname.startsWith('/assets/')?path.join(root,'public'):fixtures;
  const file=path.resolve(base,'.'+pathname);
  if(!file.startsWith(base+path.sep)||!fs.existsSync(file)||!fs.statSync(file).isFile()){res.writeHead(404);res.end();return;}
  const type={'.css':'text/css','.js':'text/javascript','.html':'text/html','.woff2':'font/woff2'}[path.extname(file)]||'application/octet-stream';
  res.writeHead(200,{'Content-Type':type});fs.createReadStream(file).pipe(res);
 });
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
 browser=await chromium.launch({executablePath:process.env.BROWSER_BINARY,headless:true,args:['--no-sandbox']});
 let checks=0;
 for(const locale of ['en','si-LK'])for(const format of ['58','80','a4']){
  // Android-size viewport is an approximation, NOT an Android print-service test.
  const page=await browser.newPage({viewport:{width:393,height:850},deviceScaleFactor:2,isMobile:true,hasTouch:true});
  await page.goto('http://127.0.0.1:'+server.address().port+'/'+format+'-'+locale+'.html');await page.evaluate(()=>document.fonts.ready);
  if(format==='58')await page.evaluate(()=>Promise.all([document.fonts.load('12px "Receipt Sinhala"','සිංහල'),document.fonts.load('bold 14px "Receipt Sinhala"','සිංහල')]));
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));checks++;
  await page.screenshot({path:path.join(output,format+'-'+locale+'-screen.png'),fullPage:true});
  await page.emulateMedia({media:'print'});await page.evaluate(()=>document.fonts.ready);
  const noOverflow=()=>[...document.querySelectorAll('.invoice-sheet *')].filter(e=>getComputedStyle(e).display!=='inline').every(e=>e.scrollWidth<=e.clientWidth+1);
  assert(await page.evaluate(noOverflow));checks++;
  await page.screenshot({path:path.join(output,format+'-'+locale+'-print-media-viewport.png'),fullPage:true});
  const pdf=await page.pdf({path:path.join(output,format+'-'+locale+'.pdf'),preferCSSPageSize:true,displayHeaderFooter:false,printBackground:true});
  if(format==='58'){
   // Screenshotting print media is NOT a paper preview: it omits @page
   // margins and retains the screen viewport. Rasterize the actual PDF
   // for review when Poppler is installed (not an app dependency).
   const pdfPath=path.join(output,format+'-'+locale+'.pdf');
   try{
    execFileSync('pdftotext',['-bbox',pdfPath,path.join(output,format+'-'+locale+'-bounds.html')]);
    const bounds=fs.readFileSync(path.join(output,format+'-'+locale+'-bounds.html'),'utf8');
    const words=[...bounds.matchAll(/<word xMin="([\d.]+)" yMin="[\d.]+" xMax="([\d.]+)"/g)];
    assert(words.length>0);assert(words.every(m=>Number(m[1])>=4.8*72/25.4&&Number(m[2])<=53.2*72/25.4));checks++;
    execFileSync('pdftoppm',['-r','180','-png',pdfPath,path.join(output,format+'-'+locale+'-actual-paper')]);
   }catch(error){if(error.code==='ENOENT')console.log('Poppler unavailable: PDF bounds/raster preview not run.');else throw error;}
   const box=pdf.toString('latin1').match(/\/MediaBox\s*\[0 0 ([\d.]+) ([\d.]+)\]/);assert(box&&Math.abs(Number(box[1])*25.4/72-58)<0.2);checks++;
   await page.evaluate(()=>document.fonts.load('14px "Receipt Sinhala"','සිංහල'));assert(await page.evaluate(()=>document.fonts.check('14px "Receipt Sinhala"','සිංහල')));checks++;
   assert(await page.evaluate(()=>Math.abs(document.querySelector('main').getBoundingClientRect().width*25.4/96-48)<0.1&&document.querySelector('h1').getBoundingClientRect().top===0));checks++;
   await page.evaluate(()=>{const section=document.querySelector('.receipt-item').parentNode;while(section.children.length<30)section.append(section.firstElementChild.cloneNode(true));});
   assert(await page.evaluate(noOverflow));checks++;
   const long=await page.pdf({path:path.join(output,'58-'+locale+'-30-items.pdf'),preferCSSPageSize:true,displayHeaderFooter:false});assert((long.toString('latin1').match(/\/Type \/Page\b/g)||[]).length>2);checks++;
   await page.evaluate(()=>document.querySelectorAll('.amount-row>:last-child,.receipt-unit-price').forEach(e=>e.textContent='9,999,999,999,999.99'));
   assert(await page.evaluate(noOverflow));checks++;
  }
  await page.close();
 }
 console.log(checks+' Chromium viewport/print/embedded-font/width/pagination/maximum-amount checks passed. Device/hardware NOT VERIFIED.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(async()=>{await browser?.close();server?.close();fs.rmSync(fixtures,{recursive:true,force:true});});
