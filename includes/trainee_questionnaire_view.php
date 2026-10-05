<?php
// Authenticated caller has already selected the course, active questions and safe choices.
$metadata=pu_metadata($pdo,$themeId); $draftKey=pu_draft_key($traineeId,$questionnaireMode,$assignmentId,$themeId);
$posted=($_SERVER['REQUEST_METHOD']==='POST' && is_array($_POST['answers'] ?? null));
if ($posted) {
 $_SESSION['positioning_drafts'][$draftKey]=['answers'=>pu_draft_answers($questions,$_POST['answers']),'saved'=>date('c')];
 if (isset($_POST['pu_save'])) { header('Location: questionnaire.php?'.http_build_query($questionnaireMode==='assigned'?['assignment_id'=>$assignmentId,'lang'=>currentLanguage(),'saved'=>1]:['theme_id'=>$themeId,'lang'=>currentLanguage(),'saved'=>1])); exit; }
}
$draft=$_SESSION['positioning_drafts'][$draftKey] ?? ['answers'=>[]];
?>
