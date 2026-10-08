<?php
require_once __DIR__.'/includes/survey_app.php';
header('Content-Type: application/json; charset=utf-8');
try {
    if($_SERVER['REQUEST_METHOD']!=='POST')throw new InvalidArgumentException('POST requis');
    $raw=file_get_contents('php://input');if(strlen($raw)>5000000)throw new InvalidArgumentException('Questionnaire trop volumineux');
    $data=json_decode($raw,true,64,JSON_THROW_ON_ERROR);if(!is_array($data))throw new InvalidArgumentException('JSON invalide');
    sv_check_csrf($data['csrf_token']??null);$theme=(int)($data['theme_id']??0);[$role,$id]=sv_theme_access($theme);
    $survey=$data['survey']??null;if(!is_array($survey))throw new InvalidArgumentException('Questionnaire invalide');
    $survey=sv_clean($survey);sv_validate($survey);$state=$data['status']??'draft';
    if(!in_array($state,['draft','published','archived'],true))throw new InvalidArgumentException('État invalide');
    $title=$survey['title']??'Questionnaire';if(is_array($title))$title=$title['fr']??$title['default']??reset($title);
    if(!is_string($title)||strlen($title)>255)throw new InvalidArgumentException('Titre trop long');
    $description=$survey['description']??'';if(is_array($description))$description=$description['fr']??$description['default']??reset($description);
    if(!is_string($description))throw new InvalidArgumentException('Description invalide');
    $pdo->beginTransaction();sv_row('SELECT id FROM themes WHERE id=? FOR UPDATE',[$theme]);
    $latest=sv_row('SELECT id FROM survey_definitions WHERE theme_id=? ORDER BY id DESC LIMIT 1',[$theme]);
    if((int)($data['base_id']??0)!==(int)($latest['id']??0))throw new RuntimeException('Une autre version a été enregistrée. Rechargez la page avant de modifier.');
    if(in_array($state,['published','archived'],true))$pdo->prepare("UPDATE survey_definitions SET status='archived' WHERE theme_id=? AND status='published'")->execute([$theme]);
    $pdo->prepare('INSERT INTO survey_definitions (theme_id,title,description,survey_json,status,created_by_type,created_by_id) VALUES (?,?,?,?,?,?,?)')->execute([$theme,$title,$description,json_encode($survey,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$state,$role,$id]);
    $new=(int)$pdo->lastInsertId();$pdo->commit();echo json_encode(['success'=>true,'survey_definition_id'=>$new]);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();http_response_code($e instanceof InvalidArgumentException?400:409);echo json_encode(['success'=>false,'message'=>$e instanceof PDOException?'Erreur de base de données':$e->getMessage()]);}
