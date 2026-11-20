'use strict';
(()=>{
 const valid=v=>v==='en'||v==='si-LK',readCookie=()=>document.cookie.split('; ').find(v=>v.startsWith('dharma_language='))?.split('=')[1];
 let locale=document.body.classList.contains('document')?document.documentElement.lang:(valid(readCookie())?readCookie():'si-LK');
 const t=(key,values={})=>{const translated=locale==='si-LK'?(globalThis.DharmaTranslations?.[key]??key):key;return String(translated).replace(/\{([A-Za-z0-9_]+)\}/g,(m,k)=>Object.hasOwn(values,k)?String(values[k]):m);};
 const product=p=>{const en=String(p.product_name_snapshot??p.product_name??p.name??''),si=String(p.name_si??p.product_name_si??'');const first=locale==='si-LK'?si:en,second=locale==='si-LK'?en:si;return first.trim()?first:(second.trim()?second:'');};
 globalThis.DharmaI18n={t,product,get locale(){return locale;}};
 document.documentElement.lang=locale;
 const header=document.querySelector('.workspace-header');if(header){const measure=()=>document.documentElement.style.setProperty('--dharma-header-height',Math.ceil(header.getBoundingClientRect().height)+'px');measure();if(typeof ResizeObserver==='function')new ResizeObserver(measure).observe(header);}
 for(const node of document.querySelectorAll('[data-i18n]'))node.textContent=t(node.dataset.i18n);
 for(const node of document.querySelectorAll('[data-i18n-placeholder]'))node.placeholder=t(node.dataset.i18nPlaceholder);
 document.addEventListener('click',event=>{const link=event.target.closest('[data-language]');if(!link)return;event.preventDefault();const next=link.dataset.language;if(!valid(next)||next===locale)return;
  const unsaved=[...document.querySelectorAll('#invoice-lines select, #lines select')].some(select=>select.value);
  if(unsaved){let warning=document.getElementById('language-draft-warning');if(!warning){warning=document.createElement('p');warning.id='language-draft-warning';warning.className='alert alert-warning m-2';warning.setAttribute('role','status');(document.querySelector('.workspace-header')||document.querySelector('header'))?.after(warning);}warning.textContent=locale==='si-LK'?'භාෂාව වෙනස් කිරීමට පෙර මෙම බිල්පත සම්පූර්ණ කරන්න හෝ භාණ්ඩ ඉවත් කරන්න.':'Complete this invoice or clear the cart before changing language.';return;}
  document.cookie='dharma_language='+next+'; Path=/; Max-Age=31536000; SameSite=Lax'+(location.protocol==='https:'?'; Secure':'');
  // This preference is public UI metadata. No vault, signature, session or business data is rewritten.
  try{localStorage.setItem('dharma_language',next);}catch{}
  const target=new URL(location.href);target.searchParams.delete('language');if(target.href===location.href)location.reload();else location.assign(target.href);
 });
 for(const link of document.querySelectorAll('[data-language]')){const target=new URL(location.href);target.searchParams.set('language',link.dataset.language);link.href=target.href;link.setAttribute('aria-current',String(link.dataset.language===locale));}
 // Browser validity messages otherwise follow the operating system's language.
 document.addEventListener('invalid',event=>{const input=event.target;if(typeof input.setCustomValidity!=='function')return;if(input.validity.valueMissing)input.setCustomValidity(t('Complete this required field.'));else if(input.validity.patternMismatch||input.validity.typeMismatch)input.setCustomValidity(t('Enter a valid value.'));},true);
 const clearValidity=event=>{const form=event.target.closest?.('form');if(form)for(const control of form.elements)control.setCustomValidity?.('');else event.target.setCustomValidity?.('');};
 document.addEventListener('input',clearValidity,true);document.addEventListener('change',clearValidity,true);
})();
