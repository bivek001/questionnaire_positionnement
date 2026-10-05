<?php
// The caller has authenticated and authorized this questionnaire before saving drafts.
$metadata=pu_metadata($pdo,$themeId);
$draftKey=pu_draft_key($traineeId,$questionnaireMode,$assignmentId,$themeId);
$draftUrl='questionnaire.php?'.http_build_query($questionnaireMode==='assigned'?['assignment_id'=>$assignmentId,'lang'=>currentLanguage()]:['theme_id'=>$themeId,'lang'=>currentLanguage()]);
$posted=($_SERVER['REQUEST_METHOD']==='POST');
if($posted) {
 $_SESSION['positioning_drafts'][$draftKey]=['answers'=>pu_draft_answers($questions,is_array($_POST['answers'] ?? null)?$_POST['answers']:[]),'saved'=>date('c')];
}
$draft=$_SESSION['positioning_drafts'][$draftKey] ?? ['answers'=>[]];
$currentPage=max(0,min(count($questions)-1,(int)($_POST['pu_page'] ?? $_GET['page'] ?? 0)));
$answeredCount=0; $firstUnanswered=null;
foreach($questions as $index=>$q) {
 $value=$draft['answers'][$q['id']] ?? '';
 $hasAnswer=is_array($value)?count($value)>0:(trim((string)$value)!=='');
 if($hasAnswer) $answeredCount++; elseif($firstUnanswered===null) $firstUnanswered=$index;
}
$reviewing=$posted && isset($_POST['pu_review']);
if($posted && (isset($_POST['pu_save']) || isset($_POST['pu_incomplete']))) {
 if(isset($_POST['pu_incomplete']) && $firstUnanswered!==null) $currentPage=$firstUnanswered;
 header('Location: '.$draftUrl.'&saved=1&page='.$currentPage); exit;
}
