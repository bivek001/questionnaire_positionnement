<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/config/database.php';
if(isset($argv[1])&&preg_match('/^codex_ux_[a-f0-9]{8}$/D',$argv[1]))$pdo->exec('USE `'.$argv[1].'`');
$data=require dirname(__DIR__).'/includes/word_key_criteria.php';
$s=$pdo->prepare('SELECT theme_id FROM word_course_imports WHERE source_hash=?');$s->execute([$data['source_hash']]);$theme=$s->fetchColumn();if(!$theme)throw new RuntimeException('Matching Word course was not imported.');
$pdo->beginTransaction();$updated=0;$skipped=0;
try{
 foreach($data['items'] as $item){
  $s=$pdo->prepare('SELECT q.id,q.model_answer,q.grading_keywords,q.grading_rubric FROM questions q JOIN word_question_specs w ON w.question_id=q.id WHERE q.theme_id=? AND w.source_key=? FOR UPDATE');$s->execute([$theme,$item['key']]);$q=$s->fetch(PDO::FETCH_ASSOC);
  if(!$q||$q['model_answer']!==$item['original_model']||trim($q['grading_keywords']??'')!==''||strpos($q['grading_rubric']??'','CRITÈRES CLÉS — CORRIGÉ D05')!==false){$skipped++;continue;}
  $rubric=($q['grading_rubric']??'')."\n\n".$item['criteria'];
  $s=$pdo->prepare('UPDATE questions SET grading_keywords=?,grading_rubric=? WHERE id=?');$s->execute([$item['keywords']?:null,$rubric,$q['id']]);$updated++;
 }
 $pdo->commit();echo 'Course '.$theme.': '.$updated.' key-criteria records updated; '.$skipped." existing/edited records preserved. Existing response suggestions and human grades are unchanged.\n";
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
