const fs=require('fs'),Module=require('module'),path=require('path');
const parser=new Module('dharma-localization-parser');parser._compile(process.binding('natives')['internal/deps/acorn/acorn/dist/acorn'],'acorn.js');const acorn=parser.exports;
const keys=new Set();
for(const file of fs.readdirSync('public/assets/js').filter(f=>f.endsWith('.js')&&f!=='localization.js')){
 const filename='public/assets/js/'+file,source=fs.readFileSync(filename,'utf8');
 const ast=acorn.parse(source,{ecmaVersion:'latest',sourceType:'script'}),changes=[],helper='tr_'+file.replace(/\W/g,'_');
 function translated(n){
  if(!n)return;
  if(n.type==='ConditionalExpression'){translated(n.consequent);translated(n.alternate);return;}
  if(n.type==='LogicalExpression'){translated(n.right);return;}
  if(n.type==='BinaryExpression'&&n.operator==='+'){translated(n.left);translated(n.right);return;}
  let key,args='';if(n.type==='Literal'&&typeof n.value==='string')key=n.value;
  else if(n.type==='TemplateLiteral'){key=n.quasis.map((q,i)=>q.value.cooked+(i<n.expressions.length?'{'+i+'}':'')).join('');args=','+'{'+n.expressions.map((e,i)=>JSON.stringify(i)+':('+source.slice(e.start,e.end)+')').join(',')+'}';}
  if(!key||!/[A-Za-z]/.test(key)||/^(https?:|\/|#|[a-z_-]+$)/.test(key))return;
  keys.add(key);if(changes.some(c=>c.start<=n.start&&c.end>=n.end))return;changes.push({start:n.start,end:n.end,text:helper+'('+JSON.stringify(key)+args+')'});
 }
 function visit(n){if(!n||typeof n!=='object')return;
  if(n.type==='NewExpression'&&n.callee.name==='Error')translated(n.arguments[0]);
  if(n.type==='AssignmentExpression'&&n.left.type==='MemberExpression'&&['textContent','placeholder','title'].includes(n.left.property.name))translated(n.right);
  if(n.type==='CallExpression'){
   const c=n.callee,name=c.name||c.property?.name;
   if(['el','node'].includes(name))translated(n.arguments[1]);
   if(name==='selectOptions')translated(n.arguments[3]);
   if(['button','message','confirm','alert','setCustomValidity','Error'].includes(name))translated(n.arguments[0]);
   if(name==='setAttribute'&&['aria-label','title','placeholder'].includes(n.arguments[0]?.value))translated(n.arguments[1]);
  }
  if(n.type==='NewExpression'&&n.callee.name==='Option')translated(n.arguments[0]);
  for(const [k,v] of Object.entries(n))if(!['start','end','loc'].includes(k)){if(Array.isArray(v))v.forEach(visit);else if(v&&typeof v==='object')visit(v);}
 }
 visit(ast);changes.sort((a,b)=>a.start-b.start||b.end-a.end);const safe=[];for(const c of changes)if(!safe.some(p=>p.end>c.start))safe.push(c);
 let output=source;for(const c of safe.reverse())output=output.slice(0,c.start)+c.text+output.slice(c.end);
 const declaration="\n/* Sinhala display bindings */\nconst "+helper+"=(key,values={})=>globalThis.DharmaI18n?.t(key,values)??key.replace(/\\{([0-9]+)\\}/g,(m,k)=>Object.hasOwn(values,k)?String(values[k]):m);\n";
 if(!source.includes('/* Sinhala display bindings */'))output=output.replace(/(['"]use strict['"];)/,'$1'+declaration);fs.writeFileSync(filename,output);
}
if(fs.existsSync('storage/localization-client-keys.json'))for(const key of JSON.parse(fs.readFileSync('storage/localization-client-keys.json','utf8')))keys.add(key);fs.writeFileSync('storage/localization-client-keys.json',JSON.stringify([...keys].sort(),null,2));console.log(keys.size+' client display messages centralized');
