<?php
// CLI only: repeatable, transactional source import. Never publish automatically.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/config/database.php';
if (isset($argv[1]) && preg_match('/^codex_ux_[a-f0-9]{8}$/D',$argv[1])) $pdo->exec('USE `'.$argv[1].'`');
$data=require dirname(__DIR__).'/includes/word_course_data.php';
require_once dirname(__DIR__).'/includes/safe_rich_content.php';
$pdo->exec('CREATE TABLE IF NOT EXISTS word_course_imports (source_hash CHAR(64) PRIMARY KEY, theme_id INT UNSIGNED NOT NULL, imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(theme_id) REFERENCES themes(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$pdo->exec('CREATE TABLE IF NOT EXISTS word_question_specs (question_id INT UNSIGNED PRIMARY KEY, fields_json MEDIUMTEXT NOT NULL, review_json TEXT NOT NULL, source_key VARCHAR(180) NOT NULL, FOREIGN KEY(question_id) REFERENCES questions(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$s=$pdo->prepare('SELECT theme_id FROM word_course_imports WHERE source_hash=?');$s->execute([$data['source_sha256']]);
if ($existing=$s->fetchColumn()) { echo 'Already imported; draft course '.$existing."\n"; exit; }
function wi_insert($pdo,$sql,$values) {$s=$pdo->prepare($sql);$s->execute($values);return (int)$pdo->lastInsertId();}
$pdo->beginTransaction();
try {
 $theme=wi_insert($pdo,'INSERT INTO themes(name,description,is_active) VALUES(?,?,0)',[$data['title'],$data['description']."\n\nImported draft — source and visual review required before publication."]);
 $chapters=[];foreach(['transversal'=>'Diagnostic des compétences transversales','professional'=>'Diagnostic des compétences professionnelles'] as $key=>$label) $chapters[$key]=wi_insert($pdo,'INSERT INTO chapters(theme_id,title,display_order) VALUES(?,?,?)',[$theme,$label,count($chapters)+1]);
 $lessons=[];foreach($data['domains'] as $n=>$domain) $lessons[$n]=wi_insert($pdo,'INSERT INTO lessons(chapter_id,title,display_order) VALUES(?,?,?)',[$chapters[$domain['diagnostic']],$n.' — '.$domain['title'],(int)$n]);
 foreach($data['exercises'] as $i=>$e) {
  $domain=$data['domains'][(string)$e['domain']];$chapter=$chapters[$domain['diagnostic']];$lesson=$lessons[(string)$e['domain']];
  $title=mb_substr($e['title'],0,255,'UTF-8');
  $topic=wi_insert($pdo,'INSERT INTO topics(lesson_id,title,display_order) VALUES(?,?,?)',[$lesson,$title,$i+1]);
  $paragraph=wi_insert($pdo,'INSERT INTO paragraphs(topic_id,title,content,display_order) VALUES(?,?,?,1)',[$topic,implode(' / ',$e['codes']).' — Source / contexte',qp_rich_html($e['context'])]);
  $rubric=$e['rubric']."\n\nSOURCE MARKED ANSWERS (not configured automatic criteria):\n".$e['source_marked_answers']."\n\nREVIEW:\n".implode("\n",$e['review_flags']);
  $qid=wi_insert($pdo,"INSERT INTO questions(theme_id,chapter_id,lesson_id,topic_id,paragraph_id,question_text,question_type,points,display_order,model_answer,grading_rubric,grading_keywords) VALUES(?,?,?,?,?,?,'open',?,?,?,?,NULL)",[$theme,$chapter,$lesson,$topic,$paragraph,$e['title'],$e['points'],$i+1,$e['model_answer'],$rubric]);
  wi_insert($pdo,'INSERT INTO word_question_specs(question_id,fields_json,review_json,source_key) VALUES(?,?,?,?)',[$qid,json_encode($e['fields'],JSON_UNESCAPED_UNICODE),json_encode($e['review_flags'],JSON_UNESCAPED_UNICODE),$e['key']]);
  foreach($e['codes'] as $code) { $s=$pdo->prepare('INSERT INTO positioning_question_metadata(question_id,competency_code,domain_number,diagnostic) VALUES(?,?,?,?)');$s->execute([$qid,$code,$e['domain'],$domain['diagnostic']]); }
 }
 $s=$pdo->prepare('INSERT INTO word_course_imports(source_hash,theme_id) VALUES(?,?)');$s->execute([$data['source_sha256'],$theme]);$pdo->commit();
 echo 'Imported inactive course '.$theme.': '.count($data['exercises']).' exercise groups, '.count($data['domains'])." domains. Human review required.\n";
} catch(Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();throw $e;}
