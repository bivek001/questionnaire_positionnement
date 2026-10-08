<?php
require_once __DIR__.'/includes/survey_app.php';
require_once __DIR__.'/includes/language.php';
require_once __DIR__.'/includes/survey_editor_labels.php';
[$role,$staff]=sv_staff();$theme=(int)($_GET['theme_id']??0);
if(!$theme){header('Location: '.$role.'/questions.php');exit;}
sv_theme_access($theme);
$definition=sv_row('SELECT * FROM survey_definitions WHERE theme_id=? ORDER BY id DESC LIMIT 1',[$theme]);
$themeRow=sv_row('SELECT name FROM themes WHERE id=?',[$theme]);
$schema=$definition?json_decode($definition['survey_json'],true,64,JSON_THROW_ON_ERROR):['title'=>$themeRow['name'],'pages'=>[['name'=>'page1','elements'=>[]]]];
$locale=currentLanguage()==='fr'?'fr':'en';$labels=$editorLabels[$locale];
$nav=['dashboard'=>$role.'/index.php','questions'=>$role.'/questions.php?theme_id='.$theme,'course'=>$role.'/course.php','themes'=>$role.'/themes.php','trainees'=>$role.'/trainees.php','results'=>'survey_results.php'];
?><!doctype html><html lang="<?= qp_h($locale) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= qp_h($labels['edit']) ?></title>
<link rel="stylesheet" href="assets/survey/vendor/survey-core.min.css"><link rel="stylesheet" href="assets/survey/vendor/survey-creator-core.min.css"><link rel="stylesheet" href="assets/survey/staff.css"></head>
<body><?php $staffRoot='';require __DIR__.'/includes/survey_staff_header.php'; ?><header class="staff-shell editor-shell"><h1><?= qp_h($themeRow['name']) ?></h1>
<p class="staff-help" data-ui="help"><?= qp_h($labels['help']) ?></p>
<div class="staff-card"><div class="staff-actions"><button id="save-draft" class="secondary" type="button" data-ui="draft"><?= qp_h($labels['draft']) ?></button><button id="publish-survey" type="button" data-ui="publish"><?= qp_h($labels['publish']) ?></button><button id="preview-survey" class="secondary" type="button" data-ui="preview"><?= qp_h($labels['preview']) ?></button><button id="translate-questions" class="secondary" type="button" data-ui="translate"><?= qp_h($labels['translate']) ?></button><label><input id="advanced-tools" type="checkbox"> <span data-ui="advanced"><?= qp_h($labels['advanced']) ?></span></label><span id="definition-status" class="staff-status"><?= ($definition['status']??'')==='published'?qp_h($labels['published']):qp_h($labels['draftStatus']) ?></span></div><p id="save-message" role="status" aria-live="polite"></p><small data-ui="note"><?= qp_h($labels['note']) ?></small><p class="staff-help" data-ui="translationHelp"><?= qp_h($labels['translationHelp']) ?></p></div></header><div id="creator"></div>
<script src="assets/survey/vendor/survey.core.min.js"></script><script src="assets/survey/vendor/survey.i18n.min.js"></script><script src="assets/survey/vendor/survey-js-ui.min.js"></script><script src="assets/survey/vendor/survey-creator-core.min.js"></script><script src="assets/survey/vendor/survey-creator-core.i18n.min.js"></script><script src="assets/survey/vendor/survey-creator-js.min.js"></script><script src="assets/survey/properties.js"></script>
<script>window.questionEditor=<?= sv_json(['survey'=>$schema,'revision'=>(int)($definition['id']??0),'theme'=>$theme,'csrf'=>sv_csrf(),'question'=>is_string($_GET['question']??null)?$_GET['question']:'','preview'=>isset($_GET['preview']),'locale'=>$locale,'labels'=>$editorLabels,'status'=>$definition['status']??'draft']) ?>;</script><script src="assets/survey/staff-editor.js"></script></body></html>
