<?php
require_once __DIR__.'/includes/survey_app.php';
header('Content-Type: application/json; charset=utf-8');
try{
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new InvalidArgumentException('POST requis');
    if(empty($_SESSION['trainee_id']))sv_fail('Connexion stagiaire requise',403);
    $raw=file_get_contents('php://input');if(strlen($raw)>5000000)throw new InvalidArgumentException('Réponses trop volumineuses');
    $d=json_decode($raw,true,64,JSON_THROW_ON_ERROR);sv_check_csrf($d['csrf_token']??null);
    $token=$d['run']??'';if(!is_string($token))throw new InvalidArgumentException('Passation invalide');
    $run=$_SESSION['survey_runs'][$token]??null;
    if(!$run||empty($run['draft_key'])||(int)$run['trainee_id']!==(int)$_SESSION['trainee_id']||isset($run['attempt_id']))throw new InvalidArgumentException('Passation indisponible');
    if(!sv_row('SELECT id FROM themes WHERE id=? AND is_active=1',[$run['theme_id']]))throw new RuntimeException('Questionnaire indisponible');
    if($run['assignment_id']&&!sv_row("SELECT id FROM questionnaire_assignments WHERE id=? AND trainee_id=? AND theme_id=? AND status='pending'",[$run['assignment_id'],$run['trainee_id'],$run['theme_id']]))throw new RuntimeException('Affectation indisponible');
    $def=sv_row('SELECT survey_json FROM survey_definitions WHERE id=? AND theme_id=?',[$run['definition_id'],$run['theme_id']]);if(!$def)throw new RuntimeException('Version introuvable');
    $survey=json_decode($def['survey_json'],true,64,JSON_THROW_ON_ERROR);$answers=$d['answers']??null;if(!is_array($answers))throw new InvalidArgumentException('Réponses invalides');
    $clean=[];foreach(sv_questions($survey) as $q)if(array_key_exists($q['name'],$answers))$clean[$q['name']]=$answers[$q['name']];
    $page=max(0,min(count($survey['pages']??[])-1,(int)($d['page']??0)));
    $_SESSION['survey_drafts'][$run['draft_key']]=['answers'=>$clean,'page'=>$page,'saved'=>time()];echo json_encode(['success'=>true]);
}catch(Throwable $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e instanceof PDOException?'Erreur de base de données':$e->getMessage()]);}
