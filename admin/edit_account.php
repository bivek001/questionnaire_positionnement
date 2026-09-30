<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/account_security.php';
require_once __DIR__.'/../includes/safe_rich_content.php';
ux_csrf_check();
$role=$_GET['role']??'trainee';$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if(!is_string($role)||!in_array($role,['trainee','professor'],true)||!$id){http_response_code(400);exit;}
$table=$role==='professor'?'professors':'trainees';
$s=$pdo->prepare("SELECT * FROM $table WHERE id=?");$s->execute([$id]);$account=$s->fetch(PDO::FETCH_ASSOC);
if(!$account){http_response_code(404);exit;}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  $values=[];
  foreach(['first_name','last_name','email'] as $field){$value=$_POST[$field]??null;if(!is_string($value)||trim($value)===''||mb_strlen($value)>($field==='email'?255:100))throw new InvalidArgumentException();$values[$field]=trim($value);}
  if(!filter_var($values['email'],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException();
  if($role==='professor'){
   $username=$_POST['username']??null;if(!is_string($username)||!preg_match('/^[A-Za-z0-9_.-]{3,100}$/D',$username))throw new InvalidArgumentException();
   $values['username']=$username;$values['is_active']=isset($_POST['is_active'])?1:0;
  }else{
   $dob=$_POST['date_of_birth']??'';if(!is_string($dob))throw new InvalidArgumentException();
   if($dob!==''){ $date=DateTimeImmutable::createFromFormat('!Y-m-d',$dob);if(!$date||$date->format('Y-m-d')!==$dob||$dob>date('Y-m-d'))throw new InvalidArgumentException();}
   $values['date_of_birth']=$dob?:null;
  }
  $password=$_POST['new_password']??'';$confirm=$_POST['confirm_password']??'';
  if(!is_string($password)||!is_string($confirm))throw new InvalidArgumentException();
  if($password!==''){
   if(strlen($password)<12||strlen($password)>72||$password!==$confirm)throw new InvalidArgumentException();
   $values['password']=password_hash($password,PASSWORD_DEFAULT);
  }elseif($confirm!=='')throw new InvalidArgumentException();
  $pdo->beginTransaction();
  $fields=implode(',',array_map(static fn($k)=>"`$k`=?",array_keys($values)));
  $s=$pdo->prepare("UPDATE $table SET $fields WHERE id=?");$s->execute([...array_values($values),$id]);
  // Invalidate outstanding password-reset tokens when changing a trainee password.
  if($password!==''){
   $s=$pdo->prepare('DELETE FROM password_reset_tokens WHERE account_type=? AND account_id=?');$s->execute([$role,$id]);
  }
  $pdo->commit();header('Location: edit_account.php?role='.$role.'&id='.$id.'&saved=1',true,303);exit;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error=t('pkg_account_error');}
}
?>
<!doctype html><html lang="<?=qp_h(htmlLanguage())?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=qp_h(t('pkg_edit_account'))?></title><link rel="stylesheet" href="../assets/css/style.css"><link rel="stylesheet" href="../assets/css/ui.css"><script src="../assets/js/ui.js" defer></script></head><body><div class="container">
<?php require __DIR__.'/admin_nav.php';?><main><h1><?=qp_h(t('pkg_edit_account'))?></h1>
<?php if($error):?><p role="alert"><?=qp_h($error)?></p><?php endif;?>
<?php if(isset($_GET['saved'])):?><p role="status"><?=qp_h(t('ux_saved'))?></p><?php endif;?>
<form method="post" class="card"><?php ux_csrf_field(); ?><input type="hidden" name="lang" value="<?= adminH(currentLanguage()) ?>"><?php ux_csrf_field();?>
<?php foreach(['first_name','last_name','email'] as $field):?><label><?=qp_h(t($field))?><input name="<?=$field?>" type="<?=$field==='email'?'email':'text'?>" required maxlength="<?=$field==='email'?255:100?>" value="<?=qp_h($account[$field])?>"></label><?php endforeach;?>
<?php if($role==='professor'):?><label><?=qp_h(t('username'))?><input name="username" required value="<?=qp_h($account['username'])?>"></label><label><input type="checkbox" name="is_active" <?=$account['is_active']?'checked':''?>><?=qp_h(t('active'))?></label>
<?php else:?><label><?=qp_h(t('date_of_birth'))?><input type="date" name="date_of_birth" value="<?=qp_h($account['date_of_birth'])?>"></label><?php endif;?>
<p><?=qp_h(t('pkg_password_notice'))?></p><label><?=qp_h(t('pkg_new_password'))?><input type="password" name="new_password" autocomplete="new-password" minlength="12" maxlength="72"></label><label><?=qp_h(t('pkg_confirm_password'))?><input type="password" name="confirm_password" autocomplete="new-password" minlength="12" maxlength="72"></label>
<button><?=qp_h(t('save'))?></button></form></main></div></body></html>
