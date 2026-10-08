<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/survey_engine.php';
$theme=(int)($argv[1]??0);$admin=(int)($argv[2]??0);$publish=in_array('--publish',$argv,true);
if(!$theme||!$admin){fwrite(STDERR,"Usage: php tools/install_surveyjs.php THEME_ID ADMIN_ID [--publish]\nBack up files and database first.\n");exit(1);}
try{
    $s=$pdo->prepare('SELECT id FROM themes WHERE id=?');$s->execute([$theme]);if(!$s->fetchColumn())throw new RuntimeException('Unknown theme');
    $s=$pdo->prepare('SELECT id FROM admins WHERE id=?');$s->execute([$admin]);if(!$s->fetchColumn())throw new RuntimeException('Unknown administrator');
    $migration=file_get_contents(__DIR__.'/../database/migrations/20261006_surveyjs.sql');
    $migration=preg_replace('~/\*.*?\*/~s','',$migration);
    foreach(explode(';',$migration) as $sql)if(trim($sql)!=='')$pdo->exec($sql);
    $survey=json_decode(file_get_contents(__DIR__.'/../database/seeds/positionnement-source.json'),true,64,JSON_THROW_ON_ERROR);sv_validate($survey);
    $json=json_encode($survey,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $pdo->beginTransaction();$s=$pdo->prepare('SELECT id FROM themes WHERE id=? FOR UPDATE');$s->execute([$theme]);
    $s=$pdo->prepare('SELECT id FROM survey_definitions WHERE theme_id=? AND survey_json=? ORDER BY id DESC LIMIT 1');$s->execute([$theme,$json]);
    if($existing=$s->fetchColumn()){$pdo->commit();echo "Source already imported, definition $existing. No changes.\n";exit;}
    if($publish)$pdo->prepare("UPDATE survey_definitions SET status='archived' WHERE theme_id=? AND status='published'")->execute([$theme]);
    $pdo->prepare('INSERT INTO survey_definitions (theme_id,title,description,survey_json,status,created_by_type,created_by_id) VALUES (?,?,?,?,?,?,?)')->execute([$theme,$survey['title'],$survey['description'],$json,$publish?'published':'draft','admin',$admin]);
    $id=$pdo->lastInsertId();$pdo->commit();echo "Imported definition $id (".($publish?'published':'draft')."). Legacy rows preserved.\n";
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,$e->getMessage()."\n");exit(1);}
