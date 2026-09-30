<?php
require_once __DIR__.'/includes/language.php';
require_once __DIR__.'/config/database.php';
if ($_SERVER['REQUEST_METHOD']!=='POST' || !is_string($_POST['csrf_token']??null) || empty($_SESSION['notification_csrf']) || !hash_equals($_SESSION['notification_csrf'],$_POST['csrf_token'])) {http_response_code(403);exit;}
$role=$_POST['role']??'';
if (!is_string($role) || !in_array($role,['admin','professor','trainee'],true) || empty($_SESSION[$role.'_id'])) {http_response_code(403);exit;}
$recipient=$role==='admin'?0:(int)$_SESSION[$role.'_id'];
$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
if ($id===false || $id<0) {http_response_code(400);exit;}
$s=$pdo->prepare('UPDATE notifications SET read_at=CURRENT_TIMESTAMP WHERE recipient_type=? AND recipient_id=? AND read_at IS NULL'.($id?' AND id=?':''));
$params=[$role,$recipient];if($id)$params[]=$id;$s->execute($params);
header('Location: '.($role==='trainee'?'index.php':$role.'/index.php'),true,303);
