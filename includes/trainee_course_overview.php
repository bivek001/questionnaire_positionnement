<?php
// Authenticated trainee callers only. Match Course Content's published hierarchy.
require_once __DIR__.'/safe_rich_content.php';
function traineeOverview(PDO $pdo): array {
 $rows=$pdo->query("SELECT t.id AS theme_id,t.name,c.id AS chapter_id,c.title AS chapter_title,l.id AS lesson_id,l.title AS lesson_title FROM themes t JOIN chapters c ON c.theme_id=t.id AND c.is_active=1 JOIN lessons l ON l.chapter_id=c.id AND l.is_active=1 WHERE t.is_active=1 AND EXISTS (SELECT 1 FROM topics tp JOIN paragraphs p ON p.topic_id=tp.id AND p.is_active=1 WHERE tp.lesson_id=l.id AND tp.is_active=1) ORDER BY t.id,c.display_order,c.id,l.display_order,l.id")->fetchAll(PDO::FETCH_ASSOC);
 $themes=[];
 foreach($rows as $row) {
  $tid=(int)$row['theme_id'];$cid=(int)$row['chapter_id'];
  $themes[$tid]['name']=$row['name'];
  $themes[$tid]['chapters'][$cid]['title']=$row['chapter_title'];
  $themes[$tid]['chapters'][$cid]['lessons'][]=['id'=>(int)$row['lesson_id'],'title'=>$row['lesson_title']];
 }
 return $themes;
}
function traineeImportedIntroduction(PDO $pdo): array {
 $public=require __DIR__.'/trainee_imported_introduction.php';
 try {$s=$pdo->prepare('SELECT t.name FROM word_course_imports w JOIN themes t ON t.id=w.theme_id WHERE w.source_hash=? AND t.is_active=1');$s->execute([$public['source_hash']]);$name=$s->fetchColumn();}
 catch(PDOException $e){if(($e->errorInfo[1]??null)!==1146)throw $e;return [];}
 return $name?['name'=>$name,'content'=>$public['content']]:[];
}
