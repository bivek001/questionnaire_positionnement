<?php
// Optional cleanup; default is a read-only count. No transformation of plain-text rows.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../../config/database.php';require __DIR__.'/../../includes/safe_rich_content.php';
$apply=in_array('--apply',$argv,true);$count=0;
if($apply)$pdo->beginTransaction();
try{
 foreach($pdo->query('SELECT id,content FROM paragraphs') as $row){
  $original=(string)$row['content'];
  if(!preg_match('~&(?:amp;)*lt;(?:p|div|span|font|h[1-6]|ul|ol|blockquote|strong|b|em|i)\b~i',$original))continue;
  $clean=qp_rich_html($original);if($clean===$original)continue;$count++;
  if($apply){
   // Compare original value to avoid overwriting a concurrent editor save.
   $s=$pdo->prepare('UPDATE paragraphs SET content=? WHERE id=? AND BINARY content=BINARY ?');$s->execute([$clean,$row['id'],$original]);
  }
 }
 if($apply)$pdo->commit();echo ($apply?'Updated ':'Would update ').$count." encoded paragraph rows.\n";
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
