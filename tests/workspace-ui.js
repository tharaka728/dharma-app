'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict'),vm=require('node:vm'),fs=require('node:fs');
function setup(mobile=true){
 const media={matches:mobile,addEventListener:(name,fn)=>media.changed=fn},events={},classes=new Set();let doc;
 const node=()=>({hidden:true,attrs:{},handlers:{},addEventListener(name,fn){this.handlers[name]=fn;},setAttribute(k,v){this.attrs[k]=v;},focus(){doc.activeElement=this;}});
 const menu=node(),drawer=node(),close=node(),last=node(),backdrop=node(),content=node();drawer.querySelector=()=>close;drawer.querySelectorAll=selector=>selector==='a'?[last]:[close,last];
 doc={activeElement:null,body:{classList:{toggle:(key,on)=>on?classes.add(key):classes.delete(key)}},getElementById:id=>({'workspace-menu':menu,'workspace-sidebar':drawer,'workspace-close':close,'workspace-backdrop':backdrop,'workspace-content':content}[id]||null),querySelectorAll:()=>[],addEventListener:(key,fn)=>events[key]=fn};
 vm.runInNewContext(fs.readFileSync('public/assets/js/workspace.js','utf8'),{document:doc,window:{addEventListener(){}},matchMedia:()=>media,setTimeout,clearTimeout});return {media,menu,drawer,close,last,backdrop,content,events,doc,classes};
}
test('Mobile menu locks content, closes outside and restores focus',()=>{const s=setup();s.menu.handlers.click();assert.equal(s.menu.attrs['aria-expanded'],'true');assert.equal(s.content.inert,true);assert.equal(s.doc.activeElement,s.close);s.backdrop.handlers.click();assert.equal(s.content.inert,false);assert.equal(s.drawer.hidden,true);assert.equal(s.doc.activeElement,s.menu);});
test('Escape closes navigation and Shift-Tab traps focus inside mobile drawer',()=>{const s=setup();s.menu.handlers.click();s.events.keydown({key:'Tab',shiftKey:true,preventDefault(){}});assert.equal(s.doc.activeElement,s.last);s.events.keydown({key:'Escape',preventDefault(){}});assert.equal(s.drawer.hidden,true);});
test('Queued breakpoint notification cannot cancel a new menu click',()=>{const s=setup(false);s.media.matches=true;s.menu.handlers.click();s.media.changed();assert.equal(s.drawer.hidden,false);assert.equal(s.content.inert,true);});
test('Changing layout closes an existing drawer; navigation selection also closes',()=>{const s=setup(false);s.menu.handlers.click();s.media.matches=true;s.media.changed();assert.equal(s.drawer.hidden,true);s.menu.handlers.click();s.last.handlers.click();assert.equal(s.drawer.hidden,true);assert.equal(s.content.inert,false);});
