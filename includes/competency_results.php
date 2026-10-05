<?php
require_once __DIR__.'/positioning_ux.php';
require_once __DIR__.'/course_access.php';
/** Staff-only breakdown; caller has already authenticated and authorized this attempt. */
function pu_competency_results(PDO $pdo, array $attempt, $professor = null) {
    $meta=pu_metadata($pdo,(int)$attempt['theme_id']);
    if (!$meta) return;
    $scope='1=1';$params=[(int)$attempt['id']];
    if ($professor !== null) {
        $scope='EXISTS(SELECT 1 FROM professor_content_assignments p WHERE p.professor_id=? AND p.theme_id=q.theme_id AND (p.chapter_id IS NULL OR p.chapter_id=q.chapter_id))';
        $params[]=(int)$professor;
    }
    $s=$pdo->prepare("SELECT q.id,q.points,q.question_type,r.awarded_points,r.graded_at FROM responses r JOIN questions q ON q.id=r.question_id AND q.theme_id=? WHERE r.attempt_id=? AND $scope");
    array_unshift($params,(int)$attempt['theme_id']);$s->execute($params);
    $groups=[];
    foreach($s as $row) {
        $m=$meta[$row['id']] ?? null;if(!$m)continue;
        foreach($m['codes'] as $code) {
            if(!isset($groups[$code]))$groups[$code]=['score'=>0,'maximum'=>0,'pending'=>false,'shared'=>false];
            $groups[$code]['score']+=(float)$row['awarded_points'];$groups[$code]['maximum']+=(float)$row['points'];
            $groups[$code]['pending']=$groups[$code]['pending'] || ($row['question_type']==='open' && !$row['graded_at']);
            $groups[$code]['shared']=$groups[$code]['shared'] || count($m['codes'])>1;
        }
    }
    if(!$groups)return;
    echo '<section class="card"><h2>'.qp_h(pu('Competency diagnostics','Diagnostic par compétence')).'</h2><p>'.qp_h(pu('Source thresholds apply to each competency, not a universal course percentage. Shared exercise scores are shown for context and are not added again to the attempt total.','Les seuils du document s’appliquent par compétence, pas à un pourcentage global. Les notes des exercices partagés sont contextuelles et ne sont pas ajoutées une seconde fois au total.')).'</p><table><thead><tr><th>'.qp_h(pu('Competency','Compétence')).'</th><th>'.qp_h(pu('Mapped exercise score','Note des exercices associés')).'</th><th>'.qp_h(pu('Source thresholds','Seuils du document')).'</th><th>'.qp_h(pu('Diagnostic','Diagnostic')).'</th></tr></thead><tbody>';
    foreach($groups as $code=>$g) {
        $entry=pu_source()['competencies'][$code]??['thresholds'=>[]];$ranges=[];
        foreach($entry['thresholds'] as $line) {
            if(preg_match('/Ma[iî]tris[^:0-9]*:?\s*(\d+)\s*\/\s*(\d+)/iu',$line,$m))$ranges['mastered'][$m[1].'/'.$m[2]]=[(int)$m[1],(int)$m[2]];
            if(preg_match('/optimiser\s*:\s*0(?:\s*-\s*(\d+))?\s*\/\s*(\d+)/iu',$line,$m))$ranges['optimiser'][($m[1]?:'0').'/'.$m[2]]=[(int)($m[1]?:0),(int)$m[2]];
        }
        $label=pu('Human source review required','Vérification humaine du document requise');
        $whole=$professor===null || qp_whole_theme($pdo,(int)$professor,(int)$attempt['theme_id']);
        if($g['pending'])$label=pu('Provisional — pending human grading','Provisoire — correction humaine en attente');
        elseif(count($entry['thresholds'])===2 && $whole && !$g['shared'] && count($ranges['mastered']??[])===1 && count($ranges['optimiser']??[])===1) {
            $mastered=reset($ranges['mastered']);$optimiser=reset($ranges['optimiser']);
            if($mastered[1]===$optimiser[1] && abs($g['maximum']-$mastered[1])<.001 && $optimiser[0]<$mastered[0]) {
                if($g['score']>=$mastered[0])$label='Maîtrisé';
                elseif($g['score']<=$optimiser[0])$label='A optimiser';
            }
        }
        echo '<tr><th scope="row">'.qp_h($code).'</th><td>'.qp_h($g['score'].' / '.$g['maximum']).'</td><td>'.implode('<br>',array_map('qp_h',$entry['thresholds'])).'</td><td>'.qp_h($label).'</td></tr>';
    }
    echo '</tbody></table></section>';
}
