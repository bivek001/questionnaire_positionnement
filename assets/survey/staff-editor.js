(()=>{'use strict';const config=window.questionEditor,el=id=>document.getElementById(id);
let locale=config.locale,labels=config.labels[locale],status=config.status;
const creator=new SurveyCreator.SurveyCreator({showLogicTab:false,showTranslationTab:true,showJSONEditorTab:false,showThemeTab:false,showSaveButton:false,showPreviewTab:true});
const gradingButton=document.createElement('button');gradingButton.type='button';gradingButton.className='secondary';gradingButton.textContent=locale==='fr'?'Barème de la réponse':'Response grading guide';document.querySelector('.staff-actions').appendChild(gradingButton);gradingButton.onclick=()=>{const selected=creator.selectedElement;if(!selected)return;const criteria=creator.survey.getAllQuestions().filter(q=>{let fields=q.answerFields;try{if(typeof fields==='string')fields=JSON.parse(fields||'[]');}catch(e){fields=[];}return q.assessmentOnly&&Array.isArray(fields)&&fields.includes(selected.name);});if(criteria.length){creator.selectedElement=criteria[0];el('save-message').textContent=locale==='fr'?'Critère partagé : modifiez la note maximale et la correction dans Notation. Les critères suivants sont en bas de la même page.':'Shared criterion: edit maximum marks and reviewer guidance in Scoring. Other criteria are at the end of this page.';}else{el('save-message').textContent=locale==='fr'?'Sélectionnez une réponse liée à un barème partagé.':'Select a response linked to shared grading.';}};
creator.locale=locale;creator.JSON=config.survey;creator.render('creator');let revision=config.revision,dirty=false,busy=false,change=0;
creator.onModified.add(()=>{dirty=true;change++;el('save-message').textContent=labels.unsaved;});
if(config.question){const question=creator.survey.getQuestionByName(config.question);if(question)creator.selectedElement=question;}
if(config.preview)creator.activeTab='test';
function previewLabel(){el('preview-survey').textContent=creator.activeTab==='test'?labels.return:labels.preview;}
function applyLanguage(language){locale=language;labels=config.labels[locale];gradingButton.textContent=locale==='fr'?'Barème de la réponse':'Response grading guide';creator.locale=locale;document.documentElement.lang=locale;document.title=labels.edit;
 document.querySelectorAll('[data-ui]').forEach(node=>{node.textContent=labels[node.dataset.ui];});
 document.querySelectorAll('[data-dashboard-language]').forEach(node=>{if(node.dataset.dashboardLanguage===locale)node.setAttribute('aria-current','true');else node.removeAttribute('aria-current');});
 document.querySelectorAll('[data-header-en]').forEach(node=>{node.textContent=locale==='fr'?node.dataset.headerFr:node.dataset.headerEn;});
 document.querySelectorAll('[data-editor-nav]').forEach(node=>{const url=new URL(node.href);url.searchParams.set('lang',locale);node.href=url.href;});
 el('definition-status').textContent=status==='published'?labels.published:labels.draftStatus;
 el('save-message').textContent=dirty?labels.unsaved:'';previewLabel();
}
let languageBusy=false;
document.querySelectorAll('[data-dashboard-language]').forEach(button=>button.onclick=async event=>{event.preventDefault();const selectedLanguage=button.dataset.dashboardLanguage;if(languageBusy||selectedLanguage===locale)return;languageBusy=true;
 try{const response=await fetch('survey_editor_language.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf_token:config.csrf,language:selectedLanguage})});const result=await response.json();if(!response.ok||!result.success)throw Error();applyLanguage(selectedLanguage);}catch(e){el('save-message').textContent=labels.languageError;}finally{languageBusy=false;}
});
el('preview-survey').onclick=()=>{creator.activeTab=creator.activeTab==='test'?'designer':'test';previewLabel();};
el('translate-questions').onclick=()=>{creator.activeTab=creator.activeTab==='translation'?'designer':'translation';previewLabel();};
el('advanced-tools').onchange=e=>{creator.showLogicTab=e.target.checked;creator.showJSONEditorTab=e.target.checked;};
async function save(nextStatus){if(busy)return;busy=true;const snapshot=change;el('save-draft').disabled=true;el('publish-survey').disabled=true;el('save-message').textContent=labels.saving;try{
const response=await fetch('save_survey.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf_token:config.csrf,theme_id:config.theme,base_id:revision,status:nextStatus,survey:creator.JSON})});
let result;try{result=await response.json();}catch(e){throw Error(labels.sessionError);}
if(!response.ok||!result.success)throw Error(result.message||labels.saveError);revision=result.survey_definition_id;if(snapshot===change)dirty=false;status=nextStatus;
el('definition-status').textContent=status==='published'?labels.published:labels.draftStatus;el('save-message').textContent=(status==='published'?labels.publishedMessage:labels.draftMessage)+(dirty?labels.remaining:'');
}catch(e){el('save-message').textContent=e.message;}finally{busy=false;el('save-draft').disabled=false;el('publish-survey').disabled=false;}}
el('save-draft').onclick=()=>save('draft');el('publish-survey').onclick=()=>save('published');
window.addEventListener('beforeunload',e=>{if(dirty||busy){e.preventDefault();e.returnValue='';}});previewLabel();
})();
