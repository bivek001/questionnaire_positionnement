document.addEventListener('DOMContentLoaded', () => {
 const form=document.getElementById('h150-questionnaire'); if(!form) return;
 const fr=document.documentElement.lang.startsWith('fr'), pages=[...form.querySelectorAll('.pu-question-page')];
 if(!pages.length) return;
 const progress=document.getElementById('pu-progress'), text=document.getElementById('pu-progress-text'), jump=document.getElementById('pu-jump'), nav=document.getElementById('pu-question-nav');
 const prev=document.getElementById('pu-previous'), next=document.getElementById('pu-next'); let current=0,dirty=false,confirmed=false;
 // Multi-part source exercises retain one shared score, with readable staff answers.
 form.querySelectorAll('.pu-word-fields').forEach(group=>{
  const target=document.getElementById(group.dataset.answerTarget), fields=[...group.querySelectorAll('fieldset')];
  const stored=target.value;
  fields.forEach(field=>{
   const marker='['+field.dataset.fieldId+'] ', pos=stored.indexOf(marker);
   if(pos<0)return;
   const line=stored.indexOf('\n',pos), end=stored.indexOf('\n\n[',line);
   const answer=stored.slice(line+1,end<0?undefined:end).trim();
   const area=field.querySelector('textarea');if(area)area.value=answer;
   else field.querySelectorAll('input').forEach(input=>input.checked=answer.split('\n').includes(input.value));
  });
  const sync=()=>{target.value=fields.map(field=>{
   const area=field.querySelector('textarea'), value=area?area.value.trim():[...field.querySelectorAll('input:checked')].map(i=>i.value).join('\n');
   return value?'['+field.dataset.fieldId+'] '+field.querySelector('legend').textContent+'\n'+value:'';
  }).filter(Boolean).join('\n\n');};
  group.addEventListener('input',sync);group.addEventListener('change',sync);sync();
  target.hidden=true;target.previousElementSibling.hidden=true;
 });
 const answered=p=>[...p.querySelectorAll('textarea,input[type=radio],input[type=checkbox]')].some(f=>f.tagName==='TEXTAREA'?f.value.trim().length>0:f.checked);
 const buttons=[];
 const show=(i,focus=true)=>{current=i;pages.forEach((p,n)=>p.hidden=n!==i);buttons.forEach((b,n)=>{if(n===i)b.setAttribute('aria-current','step');else b.removeAttribute('aria-current');});jump.value=String(i);prev.disabled=i===0;next.disabled=i===pages.length-1;document.getElementById('pu-page-position').textContent=`${i+1} / ${pages.length}`;if(focus){const h=pages[i].querySelector('h2,h3');h.tabIndex=-1;h.focus();h.scrollIntoView({block:'start'});}};
 pages.forEach((p,i)=>{const o=new Option(p.dataset.title,String(i));jump.add(o);const li=document.createElement('li'),b=document.createElement('button');b.type='button';b.className='btn-secondary';b.textContent=p.dataset.title;b.addEventListener('click',()=>show(i));li.append(b);nav.append(li);buttons.push(b);});
 function update(){const count=pages.filter(answered).length;progress.value=count;text.textContent=fr?`${count} / ${pages.length} réponses · ${Math.round(count/pages.length*100)} %`:`${count} / ${pages.length} answered · ${Math.round(count/pages.length*100)}%`;buttons.forEach((b,i)=>b.dataset.answered=String(answered(pages[i])));const missing=pages.length-count;document.getElementById('pu-unanswered').textContent=missing?(fr?`${missing} question(s) sans réponse.`:`${missing} unanswered question(s).`):(fr?'Toutes les questions ont une réponse.':'All questions have an answer.');}
 form.querySelector('.pu-page-controls').hidden=false;show(0,false);update();
 jump.addEventListener('change',()=>show(Number(jump.value)));prev.addEventListener('click',()=>show(current-1));next.addEventListener('click',()=>show(current+1));
 form.addEventListener('input',()=>{dirty=true;update();});form.addEventListener('change',update);
 form.addEventListener('submit',e=>{const submitter=e.submitter;const saving=submitter?.name==='pu_save'||submitter?.name==='h150_language_switch';if(saving){confirmed=true;return;}const missing=pages.filter(p=>!answered(p));let message=fr?'Confirmer l’envoi définitif de vos réponses ?':'Confirm final submission of your answers?';if(missing.length)message=(fr?`${missing.length} question(s) sans réponse. Envoyer quand même ?`:`${missing.length} unanswered question(s). Submit anyway?`);if(!window.confirm(message)){e.preventDefault();if(missing.length)show(pages.indexOf(missing[0]));return;}confirmed=true;});
 window.addEventListener('beforeunload',e=>{if(dirty&&!confirmed){e.preventDefault();e.returnValue='';}});
});
