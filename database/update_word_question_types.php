<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/config/database.php';
if(isset($argv[1])&&preg_match('/^codex_ux_[a-f0-9]{8}$/D',$argv[1]))$pdo->exec('USE `'.$argv[1].'`');
$data=require dirname(__DIR__).'/includes/word_question_types.php';$source=require dirname(__DIR__).'/includes/word_course_data.php';$greens=[];foreach($source['exercises'] as $e)$greens[$e['key']]=$e['source_marked_answers'];
$s=$pdo->prepare('SELECT theme_id FROM word_course_imports WHERE source_hash=?');$s->execute([$data['source_hash']]);$theme=$s->fetchColumn();if(!$theme)throw new RuntimeException('Matching Word course not found.');
$pdo->beginTransaction();$updated=0;$skipped=0;
try {
 foreach($data['items'] as $i){
  $s=$pdo->prepare('SELECT q.*,w.review_json FROM questions q JOIN word_question_specs w ON w.question_id=q.id WHERE q.theme_id=? AND w.source_key=? FOR UPDATE');$s->execute([$theme,$i['key']]);$q=$s->fetch(PDO::FETCH_ASSOC);$old=json_decode($q['review_json']??'[]',true);
  if(!$q||($old['type_revision']??0)>=2||$q['model_answer']!==$i['original_model']||$q['question_type']!=='open'){$skipped++;continue;}
  $s=$pdo->prepare('SELECT COUNT(*) FROM responses WHERE question_id=?');$s->execute([$q['id']]);if($s->fetchColumn()){$skipped++;continue;}
  $s=$pdo->prepare('SELECT COUNT(*) FROM choices WHERE question_id=?');$s->execute([$q['id']]);if($s->fetchColumn()){$skipped++;continue;}
  $s=$pdo->prepare('UPDATE questions SET question_type=?,question_text=? WHERE id=?');$s->execute([$i['type'],$i['type']==='open'?$q['question_text']:$i['fields'][0]['label'],$q['id']]);
  foreach($i['choices'] as $n=>$c){$s=$pdo->prepare('INSERT INTO choices(question_id,choice_text,is_correct,display_order) VALUES(?,?,?,?)');$s->execute([$q['id'],$c['text'],$c['correct']?1:0,$n+1]);}
  $review=['type_revision'=>2,'previous_review'=>$old,'choice_corrections'=>$i['corrections'],'source_green'=>$greens[$i['key']]??'','issues'=>$i['issues']];
  $s=$pdo->prepare('UPDATE word_question_specs SET fields_json=?,review_json=? WHERE question_id=?');$s->execute([json_encode($i['type']==='open'?$i['fields']:[],JSON_UNESCAPED_UNICODE),json_encode($review,JSON_UNESCAPED_UNICODE),$q['id']]);$updated++;
 }
 $pdo->commit();echo 'Course '.$theme.': '.$updated.' exercise types/corrections updated; '.$skipped." existing, edited or attempted exercises preserved.\n";
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
