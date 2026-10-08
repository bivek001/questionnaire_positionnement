<?php
require_once __DIR__.'/includes/survey_app.php';
header('Content-Type: application/json; charset=utf-8');
sv_staff();
if($_SERVER['REQUEST_METHOD']!=='POST')sv_fail('POST requis',405);
try{
 $data=json_decode(file_get_contents('php://input'),true,8,JSON_THROW_ON_ERROR);
 sv_check_csrf($data['csrf_token']??null);
 $language=$data['language']??null;
 if(!in_array($language,['fr','en'],true))throw new InvalidArgumentException('Langue invalide');
 $_SESSION['language']=$language;
 echo json_encode(['success'=>true]);
}catch(Throwable $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>'Langue invalide']);}
