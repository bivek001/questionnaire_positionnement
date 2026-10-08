<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/survey_engine.php';
function check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function score(array $q,$answer):array{return sv_score(['elements'=>[['name'=>'q','maxScore'=>3]+$q]],['q'=>$answer])[0];}
foreach(['1'=>1.,'2'=>2.,'3'=>3.] as $answer=>$expected){$r=score(['type'=>'radiogroup','scoringMode'=>'choice','choices'=>[['value'=>(string)$answer,'score'=>$expected]]],(string)$answer);check($r['final_score']===$expected,'1b.2 choice marks '.$answer.'/3');}
$r=score(['scoringMode'=>'sumChoices','choices'=>[['value'=>'a','score'=>2],['value'=>'b','score'=>2]]],['a','a','b']);check($r['final_score']===3.,'sum deduplicates and clamps');
check(score(['scoringMode'=>'choice','choices'=>[['value'=>'a','score'=>3]]],'spoof')['final_score']===0.,'unknown choice has zero points');
check(score(['scoringMode'=>'exactMatch','type'=>'checkbox','correctAnswer'=>['a','b']],['b','a'])['final_score']===3.,'checkbox exact set');
check(score(['scoringMode'=>'ranking','correctOrder'=>'["a","b","c"]','partialCredit'=>true],['a','c','b'])['final_score']===1.,'ranking partial credit');
check(score(['scoringMode'=>'matching','matchingAnswer'=>'{"r1":"a","r2":"b"}','partialCredit'=>true],['r1'=>'a'])['final_score']===1.5,'matching partial credit');
check(score(['scoringMode'=>'manual'],'text')['requires_manual_grading']===1,'manual stays pending');
check(score(['scoringMode'=>'keywords','gradingKeywords'=>'formation, secours'],'formation')['requires_manual_grading']===1,'keywords require review');
$s=['elements'=>[['name'=>'x','type'=>'radiogroup','maxScore'=>3,'scoringMode'=>'choice','correctAnswer'=>'a','teacherExplanation'=>'secret','choices'=>[['value'=>'a','score'=>3,'feedback'=>'secret']]]]];
$public=json_encode(sv_public($s));check(!str_contains($public,'secret')&&!str_contains($public,'correctAnswer')&&!str_contains($public,'score'),'answer keys redacted recursively');
$source=json_decode(file_get_contents(__DIR__.'/../database/seeds/positionnement-source.json'),true,64,JSON_THROW_ON_ERROR);sv_validate($source);check(count(sv_questions($source))>=142,'source question schema');
$rows=sv_score($source,[]);$totals=sv_totals($rows);check($totals['pending']>100,'source manual criteria retained');
try{sv_validate(['elements'=>[['name'=>'a','maxScore'=>-1]]]);throw new RuntimeException('negative score accepted');}catch(InvalidArgumentException $e){echo "PASS invalid maximum rejected\n";}
echo "All scoring checks passed.\n";
