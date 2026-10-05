<?php
// Returns public answer controls only. Corrections and review criteria are never selected.
function pu_word_fields(PDO $pdo, $questionId) {
 try {$s=$pdo->prepare('SELECT fields_json FROM word_question_specs WHERE question_id=?');$s->execute([(int)$questionId]);$json=$s->fetchColumn();return $json ? (json_decode($json,true) ?: []) : [];}
 catch(PDOException $e) {if(($e->errorInfo[1] ?? null)!==1146)throw $e;return [];}
}
function pu_type_label($type){return ['open'=>pu('Open answer','Réponse ouverte'),'single_choice'=>pu('Single choice','Choix unique'),'multiple_choice'=>pu('Multiple choices','Choix multiples')][$type] ?? $type;}
function pu_word_controls($id,array $fields,$saved,$traineeView=false) {
 echo '<div class="pu-word-fields" data-answer-target="answer_'.(int)$id.'">';
 foreach($fields as $n=>$f) {
  $fid='word_'.(int)$id.'_'.preg_replace('/[^a-z0-9]/i','',$f['id']);
  echo '<fieldset data-field-id="'.qp_h($f['id']).'" data-field-label="'.qp_h($f['label']).'"><legend>'.($traineeView?'<span class="pu-subquestion-number">'.($n+1).'.</span> ':'').qp_h($f['label']).'</legend>'.($traineeView?'':'<p class="badge">'.qp_h(pu_type_label($f['type'])).'</p>');
  if($f['type']==='open') echo '<label class="sr-only" for="'.$fid.'">'.qp_h($f['label']).'</label><textarea id="'.$fid.'" rows="4"></textarea>';
  else foreach($f['choices'] as $n=>$choice) echo '<label class="pu-choice"><input type="'.($f['type']==='single_choice'?'radio':'checkbox').'" name="'.$fid.'" value="'.qp_h($choice).'"> <span>'.qp_h($choice).'</span></label>';
  echo '</fieldset>';
 }
 echo '</div><label for="answer_'.(int)$id.'">'.qp_h($traineeView?t('qpx_answers'):pu('Your answers','Vos réponses')).'</label><textarea class="pu-word-fallback" id="answer_'.(int)$id.'" name="answers['.(int)$id.']" rows="6">'.qp_h(is_string($saved)?$saved:'').'</textarea>';
}

// Staff caller must already have authorized the question through course/chapter scope.
function pu_staff_word_correction(PDO $pdo,$id) {
 $s=$pdo->prepare('SELECT review_json FROM word_question_specs WHERE question_id=?');$s->execute([(int)$id]);$json=$s->fetchColumn();if(!$json)return;
 $r=json_decode($json,true) ?: [];if(!isset($r['type_revision']))return;
 echo '<details class="pu-staff-correction"><summary>'.qp_h(pu('Staff correction — green answers in the Word source','Correction équipe — réponses vertes du document Word')).'</summary>';
 foreach($r['choice_corrections'] ?? [] as $field=>$c){echo '<p><strong>'.qp_h($field.' · '.$c['type']).'</strong> '.qp_h($c['label']).'</p>';if(!$c['correct'])echo '<p>'.qp_h(pu('No unambiguous green answer extracted: human review required.','Aucune réponse verte non ambiguë extraite : vérification humaine requise.')).'</p>';foreach($c['correct'] as $answer)echo '<p class="pu-correct-answer">'.qp_h($answer).'</p>';}
 if(!empty($r['source_green']))echo '<h6>'.qp_h(pu('Green source excerpts / expected answers','Extraits verts source / réponses attendues')).'</h6><p class="pu-correct-answer">'.nl2br(qp_h($r['source_green'])).'</p>';
 foreach($r['issues'] ?? [] as $issue)echo '<p class="alert">'.qp_h($issue).'</p>';
 echo '</details>';
}

function pu_staff_question_label(PDO $pdo,$id,$type){$fields=pu_word_fields($pdo,$id);return count($fields)>1?pu('Multipart exercise','Exercice composé').' · '.implode(' / ',array_map('pu_type_label',array_unique(array_column($fields,'type')))):pu_type_label($type);}
