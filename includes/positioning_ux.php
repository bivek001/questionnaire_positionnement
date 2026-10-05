<?php
require_once __DIR__.'/safe_rich_content.php';
function pu($en, $fr) { return currentLanguage() === 'fr' ? $fr : $en; }
function pu_source() { static $source; if ($source === null) $source = require __DIR__.'/positioning_source.php'; return $source; }
function pu_metadata(PDO $pdo, $theme) {
 try { $s=$pdo->prepare('SELECT m.* FROM positioning_question_metadata m JOIN questions q ON q.id=m.question_id WHERE q.theme_id=?'); $s->execute([$theme]); $result=[]; foreach($s->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $id=$row['question_id']; if(!isset($result[$id])) { $result[$id]=$row; $result[$id]['codes']=[]; }
  $result[$id]['codes'][]=$row['competency_code'];
 } foreach($result as &$row) $row['competency_code']=implode(', ', $row['codes']); unset($row); return $result; }
 catch (PDOException $e) { if (($e->errorInfo[1] ?? null) !== 1146) throw $e; return []; }
}
function pu_media($type, $path, $title) {
 // Only application uploads and HTTPS media; reject active schemes and traversal.
 $path=(string)$path;
 if (!qp_safe_media_path($path)) return;
 $rolePage=preg_match('~/(admin|professor)/~', $_SERVER['SCRIPT_NAME'] ?? '')===1;
 $url=qp_h(($rolePage && strpos($path,'uploads/')===0?'../':'').$path);
 if ($type==='image') echo '<figure class="pu-media"><a href="'.$url.'" target="_blank" rel="noopener"><img src="'.$url.'" alt="'.qp_h($title).'" loading="lazy"></a><figcaption>'.qp_h(pu('Open image at full size','Ouvrir l’image en taille réelle')).'</figcaption></figure>';
 elseif (in_array($type,['audio','video'],true)) echo '<'.$type.' controls preload="metadata" src="'.$url.'"></'.$type.'>';
}
function pu_draft_key($trainee,$mode,$assignment,$theme) { return $trainee.':'.$mode.':'.($mode==='assigned'?$assignment:$theme); }
function pu_draft_answers(array $questions, array $answers) {
 $clean=[];
 foreach($questions as $q) {
  $v=$answers[$q['id']] ?? null;
  if($q['question_type']==='open') { if(is_string($v) && strlen($v)<=60000) $clean[$q['id']]=$v; }
  else { $valid=array_map('strval',array_column($q['choices'],'id')); $values=is_array($v)?$v:[$v]; $kept=[];
   foreach($values as $id) if(is_scalar($id) && in_array((string)$id,$valid,true)) $kept[]=(string)$id;
   $kept=array_values(array_unique($kept)); $clean[$q['id']]=$q['question_type']==='single_choice'?($kept[0]??''):$kept;
  }
 }
 return $clean;
}

function pu_rich($html) {
 $safe=qp_rich_html((string)$html);
 if(preg_match('~/(admin|professor)/~', $_SERVER['SCRIPT_NAME'] ?? '')) $safe=str_replace('src="uploads/', 'src="../uploads/', $safe);
 return $safe;
}
