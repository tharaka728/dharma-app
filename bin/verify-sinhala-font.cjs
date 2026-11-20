'use strict';
const fs=require('fs'),file='C:/Windows/Fonts/Nirmala.ttc',data=fs.readFileSync(file);
const offsets=data.toString('ascii',0,4)==='ttcf'?Array.from({length:data.readUInt32BE(8)},(_,i)=>data.readUInt32BE(12+i*4)):[0];
const faces=offsets.map(offset=>{const tables={};for(let i=0;i<data.readUInt16BE(offset+4);i++){const at=offset+12+i*16;tables[data.toString('ascii',at,at+4)]={offset:data.readUInt32BE(at+8),length:data.readUInt32BE(at+12)};}
 const scripts=tag=>{if(!tables[tag])return [];const base=tables[tag].offset,at=base+data.readUInt16BE(base+4);return Array.from({length:data.readUInt16BE(at)},(_,i)=>data.toString('ascii',at+2+i*6,at+6+i*6));};
 return {embedding_fsType:data.readUInt16BE(tables['OS/2'].offset+8),substitution_scripts:scripts('GSUB').filter(s=>s==='sinh'||s==='snh2'),positioning_scripts:scripts('GPOS').filter(s=>s==='sinh'||s==='snh2')};});
const report={font:file,distribution:'Installed operating-system font only; no font files copied into the app',faces};
if(!faces.some(face=>face.substitution_scripts.length&&face.positioning_scripts.length))throw Error('Sinhala shaping tables unavailable');if(faces.some(face=>(face.embedding_fsType&2)!==0))throw Error('Restricted embedding font');
fs.writeFileSync('storage/sinhala-font-verification.json',JSON.stringify(report,null,2));console.log(JSON.stringify(report,null,2));
