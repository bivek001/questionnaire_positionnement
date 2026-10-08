/* Existing trainee workflow around the SurveyJS data model. */
(()=>{'use strict';
const config=window.questionnaireUX;if(!config)return;
const labels=config.labels,survey=new Survey.Model(config.survey),byId=id=>document.getElementById(id);
survey.locale=config.locale;survey.showTitle=false;survey.showProgressBar='off';survey.completeText=labels.review;survey.pageNextText=labels.next;survey.pagePrevText=labels.previous;
survey.applyTheme({cssVariables:{'--sjs-primary-backcolor':'#245bc0','--sjs-primary-backcolor-dark':'#174693','--sjs-primary-forecolor':'#ffffff','--sjs-general-backcolor':'#ffffff','--sjs-general-backcolor-dim':'#ffffff','--sjs-corner-radius':'8px'}});
survey.data=config.draft.answers||{};survey.currentPageNo=Math.max(0,Math.min(survey.pages.length-1,Number(config.draft.page)||0));
let dirty=false,sending=false,saveQueue=Promise.resolve(),pageBefore=survey.currentPageNo;
const usable=()=>survey.getAllQuestions().filter(q=>!['html','image'].includes(q.getType())&&q.isVisible);
const fields=q=>q.getType()==='multipletext'?q.items.map(item=>({name:q.name+'.'+item.name,empty:()=>{const v=q.value&&q.value[item.name];return v==null||String(v).trim()==='';}})):[{name:q.name,empty:()=>q.isEmpty()}];
const allFields=()=>usable().flatMap(fields);
function update(){const all=allFields(),done=all.filter(f=>!f.empty()).length;byId('ux-progress').max=Math.max(1,all.length);byId('ux-progress').value=done;byId('ux-progress-text').textContent=`${labels.progress}: ${done} / ${all.length} ${labels.answered} · ${all.length?Math.round(done/all.length*100):0}%`;
const current=survey.currentPage,part=current?current.questions.filter(q=>!['html','image'].includes(q.getType())&&q.isVisible).flatMap(fields):[],answered=part.filter(f=>!f.empty()).length;byId('ux-competency').textContent=current?(current.title||current.name):'';byId('ux-domain-text').textContent=`${labels.competency}: ${answered} / ${part.length}`;byId('ux-domain-progress').max=Math.max(1,part.length);byId('ux-domain-progress').value=answered;byId('exercise-nav').value=String(survey.currentPageNo);}
async function request(path,data){const response=await fetch(path,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf_token:config.csrf,run:config.run,...data})});let result;try{result=await response.json();}catch(e){throw Error(labels.error);}if(!response.ok||!result.success)throw Error(result.message||labels.error);return result;}
function save(){const snapshot={answers:JSON.parse(JSON.stringify(survey.data)),page:survey.currentPageNo};byId('save-status').textContent=labels.saving;saveQueue=saveQueue.catch(()=>{}).then(()=>request('survey_draft.php',snapshot)).then(()=>{if(JSON.stringify(snapshot.answers)===JSON.stringify(survey.data))dirty=false;byId('save-status').textContent=labels.saved;}).catch(e=>{byId('save-status').textContent=e.message;throw e;});return saveQueue;}
function review(){byId('ux-summary').textContent=byId('ux-progress-text').textContent+' · '+allFields().filter(f=>f.empty()).length+' '+labels.unanswered;byId('ux-incomplete').hidden=!allFields().some(f=>f.empty());byId('ux-confirmation').hidden=false;byId('ux-confirmation').focus();byId('ux-confirmation').scrollIntoView({block:'center'});}
byId('ux-save').onclick=()=>save().catch(()=>{});
byId('ux-review').onclick=async()=>{try{await save();review();}catch(e){}};
byId('ux-return').onclick=()=>{byId('ux-confirmation').hidden=true;byId('survey').scrollIntoView({block:'start'});};
byId('ux-incomplete').onclick=()=>{const q=usable().find(q=>fields(q).some(f=>f.empty()));if(q){survey.currentPage=survey.getPageByQuestion(q);byId('ux-confirmation').hidden=true;q.focus();}};
byId('ux-confirm').onclick=async()=>{if(sending)return;sending=true;byId('ux-confirm').disabled=true;byId('save-status').textContent=labels.saving;try{await saveQueue.catch(()=>{});const result=await request('survey_submit.php',{answers:survey.data});dirty=false;location.href='survey_result.php?id='+result.attempt_id;}catch(e){sending=false;byId('ux-confirm').disabled=false;byId('save-status').textContent=e.message;}};
byId('ux-toggle').onclick=()=>{const menu=byId('exercise-nav');menu.hidden=!menu.hidden;byId('ux-toggle').setAttribute('aria-expanded',String(!menu.hidden));if(!menu.hidden)menu.focus();};
byId('exercise-nav').onchange=e=>{survey.currentPageNo=Number(e.target.value);};
survey.onValueChanged.add(()=>{dirty=true;update();byId('ux-confirmation').hidden=true;});
survey.onCurrentPageChanged.add(()=>{update();if(survey.currentPageNo!==pageBefore){pageBefore=survey.currentPageNo;save().catch(()=>{});}});
survey.onCompleting.add((sender,options)=>{options.allowComplete=false;save().then(review).catch(()=>{});});
document.querySelectorAll('a').forEach(a=>{if(a.href.startsWith(location.origin)&&!a.target)a.addEventListener('click',async e=>{if(!dirty)return;e.preventDefault();try{await save();location.href=a.href;}catch(err){}});});
document.querySelectorAll('.h150-language button[formaction]').forEach(button=>button.addEventListener('click',async e=>{e.preventDefault();try{await save();location.href=button.formAction;}catch(err){}}));
window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
survey.render(byId('survey'));update();
})();
