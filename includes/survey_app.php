<?php
declare(strict_types=1);
require_once __DIR__.'/survey_engine.php';
require_once __DIR__.'/safe_rich_content.php';
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
require_once __DIR__.'/../config/database.php';
header('Cache-Control: no-store');
function sv_fail(string $message,int $status=400): never {http_response_code($status);exit(htmlspecialchars($message,ENT_QUOTES,'UTF-8'));}
function sv_csrf(): string {return $_SESSION['survey_csrf']??=bin2hex(random_bytes(32));}
function sv_check_csrf($token): void {if(!is_string($token)||!hash_equals(sv_csrf(),$token))sv_fail('Session expirée. Rechargez la page.',403);}
function sv_row(string $sql,array $params=[]): ?array {global $pdo;$s=$pdo->prepare($sql);$s->execute($params);return $s->fetch(PDO::FETCH_ASSOC)?:null;}
function sv_staff(): array {
    if(!empty($_SESSION['admin_id'])&&sv_row('SELECT id FROM admins WHERE id=?',[(int)$_SESSION['admin_id']]))return ['admin',(int)$_SESSION['admin_id']];
    if(!empty($_SESSION['professor_id'])&&sv_row('SELECT id FROM professors WHERE id=? AND is_active=1',[(int)$_SESSION['professor_id']]))return ['professor',(int)$_SESSION['professor_id']];
    sv_fail('Connexion administrateur ou formateur requise.',403);
}
function sv_theme_access(int $theme): array {
    [$role,$id]=sv_staff();
    if(!sv_row('SELECT id FROM themes WHERE id=?',[$theme]))sv_fail('Thème introuvable',404);
    if($role==='professor'&&!sv_row('SELECT id FROM professor_content_assignments WHERE professor_id=? AND theme_id=? AND chapter_id IS NULL',[$id,$theme]))sv_fail('Ce questionnaire couvre le thème entier. Un accès au thème entier est requis.',403);
    return [$role,$id];
}
function sv_attempt_access(int $id): array {
    $a=sv_row('SELECT a.*,d.survey_json,d.title,d.id AS definition_id FROM attempts a JOIN survey_attempt_data x ON x.attempt_id=a.id JOIN survey_definitions d ON d.id=x.survey_definition_id WHERE a.id=?',[$id]);
    if(!$a)sv_fail('Résultat introuvable',404);
    if(!empty($_SESSION['trainee_id'])&&(int)$_SESSION['trainee_id']===(int)$a['trainee_id']&&!isset($_SESSION['admin_id'])&&!isset($_SESSION['professor_id']))return [$a,false];
    [$role,$staff]=sv_theme_access((int)$a['theme_id']);
    if($role==='professor'&&!sv_row('SELECT id FROM professor_trainee_assignments WHERE professor_id=? AND trainee_id=?',[$staff,$a['trainee_id']]))sv_fail('Stagiaire hors de votre périmètre',403);
    return [$a,true];
}
function sv_recalculate(int $attempt): array {
    global $pdo;$s=$pdo->prepare('SELECT * FROM survey_question_results WHERE attempt_id=?');$s->execute([$attempt]);$tot=sv_totals($s->fetchAll(PDO::FETCH_ASSOC));
    $pdo->prepare('UPDATE attempts SET total_score=?, maximum_score=?, corrected_at=? WHERE id=?')->execute([$tot['score'],$tot['maximum'],$tot['pending']?null:date('Y-m-d H:i:s'),$attempt]);
    foreach($tot['competencies'] as $code=>$c)$pdo->prepare('INSERT INTO survey_competency_results (attempt_id,competency,score,maximum_score,percentage,level) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE score=VALUES(score),maximum_score=VALUES(maximum_score),percentage=VALUES(percentage),level=VALUES(level)')->execute([$attempt,$code,$c['score'],$c['maximum_score'],$c['percentage'],$c['level']]);
    $pdo->prepare('UPDATE survey_attempt_data SET scoring_json=? WHERE attempt_id=?')->execute([json_encode($tot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$attempt]);
    return $tot;
}
function sv_json($v): string {return json_encode($v,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
function sv_clean(array $s): array {
    $walk=function($v) use (&$walk){if(!is_array($v))return $v;foreach($v as $k=>$x){if($k==='html'&&is_string($x))$v[$k]=qp_rich_html($x);elseif(in_array($k,['title','description','text'],true)&&is_string($x))$v[$k]=strip_tags($x);else $v[$k]=$walk($x);}return $v;};
    $s=$walk($s);unset($s['completedHtml'],$s['completedHtmlOnCondition'],$s['navigateToUrl'],$s['navigateToUrlOnCondition'],$s['triggers'],$s['calculatedValues']);
    return $s;
}
