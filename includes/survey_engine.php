<?php
declare(strict_types=1);
/** Server-owned scoring. No score, maximum, competency or answer key is accepted from a respondent. */
function sv_questions(array $survey): array {
    $out=[];
    $walk=function(array $nodes) use (&$walk,&$out): void {
        foreach($nodes as $q) {
            if (!is_array($q)) throw new InvalidArgumentException('Invalid element');
            if (isset($q['elements'])) $walk($q['elements']);
            // A dynamic panel is one composite answer; templates are not independent answers.
            if (!in_array($q['type']??'', ['panel','html','image'],true)) $out[]=$q;
        }
    };
    $walk($survey['elements']??[]);
    foreach($survey['pages']??[] as $p) $walk($p['elements']??[]);
    return $out;
}
function sv_number($value): float {
    if (!is_numeric($value) || !is_finite((float)$value)) throw new InvalidArgumentException('A finite numeric mark is required');
    return (float)$value;
}
function sv_config($v) {
    if (!is_string($v)) return $v;
    $decoded=json_decode($v,true);
    return json_last_error()===JSON_ERROR_NONE?$decoded:$v;
}
function sv_same($a,$b): bool {
    if (is_array($a)&&is_array($b)) {
        if (array_is_list($a)&&array_is_list($b)) return $a===$b;
        ksort($a);ksort($b);
    }
    return $a===$b;
}
function sv_validate(array $s): void {
    $questions=sv_questions($s);$names=[];
    if (!$questions || count($questions)>1000) throw new InvalidArgumentException('Questionnaire vide ou trop volumineux');
    foreach($questions as $q) {
        $name=$q['name']??'';
        if (!is_string($name)||!preg_match('/^[a-zA-Z0-9_.-]{1,191}$/D',$name)||isset($names[$name])) throw new InvalidArgumentException('Nom de question absent, invalide ou dupliqué');
        $names[$name]=true;
        $max=sv_number($q['maxScore']??0);
        if ($max<0||$max>99999) throw new InvalidArgumentException('Barème invalide');
        if (strlen((string)($q['competency']??''))>100||strlen((string)($q['questionCode']??''))>100) throw new InvalidArgumentException('Code trop long');
        $mode=$q['scoringMode']??'none';
        if(($q['type']??'')==='paneldynamic'&&!in_array($mode,['none','manual'],true))throw new InvalidArgumentException('Un panneau dynamique nécessite une correction manuelle');
        if (!in_array($mode,['none','manual','choice','sumChoices','exactMatch','rating','ranking','matching','keywords'],true)) throw new InvalidArgumentException('Méthode de correction inconnue');
        if (in_array($mode,['choice','sumChoices'],true)) foreach($q['choices']??[] as $c) if (is_array($c)&&isset($c['score'])) sv_number($c['score']);
        if ($mode==='exactMatch'&&!array_key_exists('correctAnswer',$q)) throw new InvalidArgumentException('Réponse exacte manquante');
        if ($mode==='ranking'&&!is_array(sv_config($q['correctOrder']??null))) throw new InvalidArgumentException('Ordre attendu : tableau JSON');
        if ($mode==='matching'&&!is_array(sv_config($q['matchingAnswer']??null))) throw new InvalidArgumentException('Correspondances attendues : objet JSON');
        // Dynamic templates need teacher review; never claim unsupported automatic grading.
        $fields=(sv_config($q['answerFields']??[]) ?: []);
        if(!is_array($fields))throw new InvalidArgumentException('Champs de réponse invalides');
        if(($q['assessmentOnly']??false)&&($q['scoringMode']??'none')!=='manual')throw new InvalidArgumentException('Un critère partagé nécessite une correction manuelle');
        if (isset($q['valueName'])) throw new InvalidArgumentException('valueName non pris en charge : conserver le nom de question');
    }
    foreach($questions as $q)foreach((sv_config($q['answerFields']??[]) ?: []) as $field)if(!is_string($field)||!isset($names[$field]))throw new InvalidArgumentException('Réponse liée introuvable');
}
function sv_public(array $s): array {
    $private=['correctAnswer','correctOrder','matchingAnswer','gradingKeywords','modelAnswer','teacherExplanation','sourceRubric','score','feedback','scoringMode','maxScore','competency','questionCode','unscored','answerFields','assessmentOnly','responseOnly'];
    $walk=function($v) use (&$walk,$private) {
        if (!is_array($v)) return $v;
        foreach($v as $k=>$x) {if(is_array($x)&&($x['assessmentOnly']??false)){unset($v[$k]);continue;}if(in_array($k,$private,true)) unset($v[$k]);else $v[$k]=$walk($x);}
        return array_is_list($v)||is_int(array_key_first($v)??'')?array_values($v):$v;
    };
    return $walk($s);
}
function sv_score(array $s,array $answers): array {
    $results=[];
    foreach(sv_questions($s) as $q) {
        if($q['responseOnly']??false)continue;
        $name=$q['name'];$answer=$answers[$name]??null;
        $fields=(sv_config($q['answerFields']??[]) ?: []);
        if(is_array($fields)&&$fields){$answer=[];foreach($fields as $field)$answer[$field]=$answers[$field]??null;}$max=(float)($q['maxScore']??0);$mode=$q['scoringMode']??'none';$score=0.;$pending=false;
        if ($mode==='manual') $pending=true;
        elseif ($mode==='choice'||$mode==='sumChoices') {
            $selected=$mode==='sumChoices'?(is_array($answer)?array_values(array_unique($answer,SORT_REGULAR)):[]):[$answer];
            foreach($q['choices']??[] as $c) {
                $v=is_array($c)?($c['value']??null):$c;
                if (in_array($v,$selected,true)) $score+=is_array($c)?(float)($c['score']??0):0;
            }
        } elseif ($mode==='exactMatch') {
            $expected=$q['correctAnswer']??null;
            if (($q['type']??'')==='checkbox'&&is_array($answer)&&is_array($expected)) {sort($answer);sort($expected);}
            if($answer!==null&&sv_same($answer,$expected))$score=$max;
        } elseif($mode==='rating') $score=is_numeric($answer)?(float)$answer:0;
        elseif($mode==='ranking') {
            $expected=sv_config($q['correctOrder']??[]);$hits=0;
            if(is_array($answer)&&is_array($expected)&&count($expected)) {
                foreach($expected as $i=>$v) if(($answer[$i]??null)===$v)$hits++;
                $score=($q['partialCredit']??false)?$max*$hits/count($expected):($hits===count($expected)?$max:0);
            }
        } elseif($mode==='matching') {
            $expected=sv_config($q['matchingAnswer']??[]);$hits=0;
            if(is_array($answer)&&is_array($expected)&&count($expected)) {
                foreach($expected as $row=>$v) if(sv_same($answer[$row]??null,$v))$hits++;
                $score=($q['partialCredit']??false)?$max*$hits/count($expected):($hits===count($expected)?$max:0);
            }
        } elseif($mode==='keywords') {
            // Keywords are assistance only: human review remains necessary.
            $pending=true;$keys=array_filter(array_map('trim',explode(',',(string)($q['gradingKeywords']??''))));$hits=0;
            foreach($keys as $key)if(is_string($answer)&&mb_stripos($answer,$key)!==false)$hits++;
            if(count($keys))$score=$max*$hits/count($keys);
        }
        $score=round(max(0,min($max,$score)),2);
        $results[]=['question_name'=>$name,'question_code'=>$q['questionCode']??null,'competency'=>$q['competency']??null,'answer'=>$answer,'automatic_score'=>$score,'manual_score'=>null,'final_score'=>$score,'maximum_score'=>$max,'requires_manual_grading'=>$pending?1:0,'trainer_comment'=>null];
    }
    return $results;
}
function sv_totals(array $rows): array {
    $total=0.;$max=0.;$pending=0;$competencies=[];
    foreach($rows as $r){$total+=(float)$r['final_score'];$max+=(float)$r['maximum_score'];$pending+=(int)$r['requires_manual_grading'];$code=$r['competency']??'';
        if($code!==''){$c=$competencies[$code]??['score'=>0.,'maximum_score'=>0.,'pending'=>0];$c['score']+=(float)$r['final_score'];$c['maximum_score']+=(float)$r['maximum_score'];$c['pending']+=(int)$r['requires_manual_grading'];$competencies[$code]=$c;}}
    foreach($competencies as &$c){$c['percentage']=$c['maximum_score']>0?round(100*$c['score']/$c['maximum_score'],2):0;$c['level']=$c['pending']?'À corriger':($c['maximum_score']<=0?'Évaluation qualitative':($c['score']>=$c['maximum_score']?'Maitrisé':'A optimiser'));}unset($c);
    return ['score'=>round($total,2),'maximum'=>round($max,2),'pending'=>$pending,'competencies'=>$competencies];
}
