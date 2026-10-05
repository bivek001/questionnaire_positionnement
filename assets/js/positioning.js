document.addEventListener('DOMContentLoaded', () => {
 const form=document.getElementById('h150-questionnaire'); if(!form) return;
 const labels=JSON.parse(document.getElementById('pu-labels').textContent), pages=[...form.querySelectorAll('.pu-question-page')];
 if(!pages.length) return;
 const progress=document.getElementById('pu-progress'), text=document.getElementById('pu-progress-text'), jump=document.getElementById('pu-jump'), nav=document.getElementById('pu-question-nav');
 const prev=document.getElementById('pu-previous'), next=document.getElementById('pu-next'); let current=Number(document.getElementById("pu-page").value),dirty=false,confirmed=false;
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
   return value?'['+field.dataset.fieldId+'] '+field.dataset.fieldLabel.replace(/\s+/g,' ')+'\n'+value:'';
  }).filter(Boolean).join('\n\n');};
  group.addEventListener('input',sync);group.addEventListener('change',sync);sync();
  target.hidden=true;target.previousElementSibling.hidden=true;
 });

 const answered=p=>[...p.querySelectorAll('textarea,input[type=radio],input[type=checkbox]')].some(f=>f.tagName==='TEXTAREA'?f.value.trim().length>0:f.checked);
 const navigationToggle=document.getElementById('pu-navigation-toggle');
 if(navigationToggle){
  navigationToggle.hidden=false;jump.hidden=true;
  navigationToggle.addEventListener('click',()=>{
   const expanded=jump.hidden;jump.hidden=!expanded;navigationToggle.setAttribute('aria-expanded',String(expanded));
   if(expanded){jump.focus();if(typeof jump.showPicker==='function'){try{jump.showPicker();}catch(e){/* The visible select remains usable in browsers without picker support. */}}}
  });
  jump.addEventListener('keydown',e=>{if(e.key==='Escape'){jump.hidden=true;navigationToggle.setAttribute('aria-expanded','false');navigationToggle.focus();}});
 }
 const buttons=[],pageTarget=document.getElementById('pu-page');
 const saveTo=i=>{pageTarget.value=String(i);form.requestSubmit(document.getElementById('pu-save-navigation'));};
 const show=(i)=>{current=i;pages.forEach((p,n)=>p.hidden=n!==i);buttons.forEach((b,n)=>{if(n===i)b.setAttribute('aria-current','step');else b.removeAttribute('aria-current');});jump.value=String(i);prev.disabled=i===0;document.getElementById('pu-page-position').textContent=(i+1)+' / '+pages.length;pageTarget.value=String(i);if(i===pages.length-1)next.textContent=labels.review;};
 pages.forEach((p,i)=>{jump.add(new Option(p.dataset.title,String(i)));const li=document.createElement('li'),b=document.createElement('button');b.type='button';b.className='btn-secondary';b.textContent=p.dataset.title;b.addEventListener('click',()=>saveTo(i));li.append(b);if(nav)nav.append(li);buttons.push(b);});
 function update(){const count=pages.filter(answered).length;progress.value=count;text.textContent=count+' / '+pages.length+' '+labels.answered+' · '+Math.round(count/pages.length*100)+'%';pages.forEach((p,i)=>{const has=answered(p);buttons[i].dataset.answered=String(has);buttons[i].setAttribute('aria-label',p.dataset.title+' · '+(has?labels.answered:labels.unanswered));p.querySelector('.pu-answer-state').textContent=has?labels.answered:labels.unanswered;const domain=pages.filter(other=>other.dataset.competency===p.dataset.competency),done=domain.filter(answered).length;p.querySelector('.pu-competency-progress').textContent=labels.competency+': '+done+' / '+domain.length+' · '+Math.round(done/domain.length*100)+'%';const bar=p.querySelector('.pu-domain-progress');bar.max=domain.length;bar.value=done;});}
 form.querySelector('.pu-page-controls').hidden=false;show(current);update();
 jump.addEventListener('change',()=>saveTo(Number(jump.value)));prev.addEventListener('click',()=>saveTo(current-1));next.addEventListener('click',()=>{if(current<pages.length-1)saveTo(current+1);else form.requestSubmit(form.querySelector('[name="pu_review"]'));});
 form.addEventListener('input',()=>{dirty=true;update();});form.addEventListener('change',()=>{dirty=true;update();});
 form.addEventListener('submit',()=>{confirmed=true;});
 const panel=form.querySelector('.pu-confirmation');if(panel){panel.tabIndex=-1;panel.focus();panel.scrollIntoView({block:'center'});}
 window.addEventListener('beforeunload',e=>{if(dirty&&!confirmed){e.preventDefault();e.returnValue='';}});
});
