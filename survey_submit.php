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
    if(!$run||(int)$run['trainee_id']!==(int)$_SESSION['trainee_id'])throw new InvalidArgumentException('Passation expirée. Rouvrez le questionnaire.');
    if(isset($run['attempt_id'])){echo json_encode(['success'=>true,'attempt_id'=>$run['attempt_id']]);exit;}
    $answers=$d['answers']??null;if(!is_array($answers))throw new InvalidArgumentException('Réponses invalides');
    $pdo->beginTransaction();
    $theme=sv_row('SELECT id,is_active FROM themes WHERE id=? FOR UPDATE',[$run['theme_id']]);
    if(!$theme||(int)$theme['is_active']!==1)throw new RuntimeException('Questionnaire indisponible');
    if($run['assignment_id']){
        $a=sv_row('SELECT * FROM questionnaire_assignments WHERE id=? AND trainee_id=? FOR UPDATE',[$run['assignment_id'],$run['trainee_id']]);
        if(!$a||(int)$a['theme_id']!==$run['theme_id']||$a['status']!=='pending')throw new RuntimeException('Affectation indisponible ou déjà terminée');
        $run['passation_type']=$a['passation_type'];
    }else $run['passation_type']='initial';
    $def=sv_row('SELECT * FROM survey_definitions WHERE id=? AND theme_id=?',[$run['definition_id'],$run['theme_id']]);
    if(!$def)throw new RuntimeException('Version introuvable');
    $survey=json_decode($def['survey_json'],true,64,JSON_THROW_ON_ERROR);$allowed=[];
    foreach(sv_questions($survey) as $q){$name=$q['name'];if(array_key_exists($name,$answers))$allowed[$name]=$answers[$name];
        if(($q['isRequired']??false)&&!isset($q['visibleIf'])&&(!array_key_exists($name,$answers)||$answers[$name]===null||$answers[$name]===''||$answers[$name]===[]))throw new InvalidArgumentException('Réponse obligatoire manquante : '.$name);}
    $rows=sv_score($survey,$allowed);
    $pdo->prepare('INSERT INTO attempts (trainee_id,theme_id,passation_type,started_at,completed_at,total_score,maximum_score) VALUES (?,?,?,?,CURRENT_TIMESTAMP,0,0)')->execute([$run['trainee_id'],$run['theme_id'],$run['passation_type'],date('Y-m-d H:i:s',$run['created'])]);
    $id=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO survey_attempt_data (attempt_id,survey_definition_id,response_json) VALUES (?,?,?)')->execute([$id,$run['definition_id'],json_encode($allowed,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
    $insert=$pdo->prepare('INSERT INTO survey_question_results (attempt_id,survey_definition_id,question_name,question_code,competency,answer_json,automatic_score,final_score,maximum_score,requires_manual_grading) VALUES (?,?,?,?,?,?,?,?,?,?)');
    foreach($rows as $r)$insert->execute([$id,$run['definition_id'],$r['question_name'],$r['question_code'],$r['competency'],json_encode($r['answer'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$r['automatic_score'],$r['final_score'],$r['maximum_score'],$r['requires_manual_grading']]);
    sv_recalculate($id);
    if($run['assignment_id'])$pdo->prepare("UPDATE questionnaire_assignments SET status='completed',completed_at=CURRENT_TIMESTAMP,attempt_id=? WHERE id=?")->execute([$id,$run['assignment_id']]);
    $pdo->commit();if(isset($run['draft_key']))unset($_SESSION['survey_drafts'][$run['draft_key']]);$_SESSION['survey_runs'][$token]['attempt_id']=$id;echo json_encode(['success'=>true,'attempt_id'=>$id]);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();http_response_code($e instanceof InvalidArgumentException?400:409);echo json_encode(['success'=>false,'message'=>$e instanceof PDOException?'Erreur de base de données : réessayez.':$e->getMessage()]);}
