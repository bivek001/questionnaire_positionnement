<?php
require_once __DIR__.'/includes/survey_app.php';
$theme=(int)($_GET['theme_id']??0);
$learnerQuestionnaire=($_GET['kind']??'')==='questionnaire'&&!empty($_SESSION['trainee_id'])&&sv_row('SELECT id FROM themes WHERE id=? AND is_active=1',[$theme]);
if(!$learnerQuestionnaire)sv_theme_access($theme);
$kind=($_GET['kind']??'')==='correction'?'D05-3-PP - Positionnement Initial correction .docx':'D04-3-PP - Positionnement Initial .docx';
$file=__DIR__.'/database/source/'.$kind;
if(!is_file($file))sv_fail('Document indisponible',404);
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');header('Content-Disposition: attachment; filename="'.rawurlencode($kind).'"');header('X-Content-Type-Options: nosniff');readfile($file);
