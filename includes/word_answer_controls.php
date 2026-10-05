<?php
// Returns public answer controls only. Corrections and review criteria are never selected.
function pu_word_fields(PDO $pdo, $questionId) {
 try {$s=$pdo->prepare('SELECT fields_json FROM word_question_specs WHERE question_id=?');$s->execute([(int)$questionId]);$json=$s->fetchColumn();return $json ? (json_decode($json,true) ?: []) : [];}
 catch(PDOException $e) {if(($e->errorInfo[1] ?? null)!==1146)throw $e;return [];}
}
function pu_word_controls($id,array $fields,$saved) {
 echo '<div class="pu-word-fields" data-answer-target="answer_'.(int)$id.'">';
 foreach($fields as $f) {
  $fid='word_'.(int)$id.'_'.preg_replace('/[^a-z0-9]/i','',$f['id']);
  echo '<fieldset data-field-id="'.qp_h($f['id']).'"><legend>'.qp_h($f['label']).'</legend>';
  if($f['type']==='open') echo '<label class="sr-only" for="'.$fid.'">'.qp_h($f['label']).'</label><textarea id="'.$fid.'" rows="4"></textarea>';
  else foreach($f['choices'] as $n=>$choice) echo '<label class="pu-choice"><input type="'.($f['type']==='single_choice'?'radio':'checkbox').'" name="'.$fid.'" value="'.qp_h($choice).'"> <span>'.qp_h($choice).'</span></label>';
  echo '</fieldset>';
 }
 echo '</div><label for="answer_'.(int)$id.'">'.qp_h(pu('Your answers','Vos réponses')).'</label><textarea class="pu-word-fallback" id="answer_'.(int)$id.'" name="answers['.(int)$id.']" rows="6">'.qp_h(is_string($saved)?$saved:'').'</textarea>';
}
