<?php
require_once __DIR__.'/positioning_ux.php';require_once __DIR__.'/functions.php';require_once __DIR__.'/course_access.php';
header('Cache-Control: no-store');
$themes=$pdo->query('SELECT t.id,t.name FROM themes t WHERE '.($puProfessor?px_scope_sql('themes','t'):'1=1').' ORDER BY t.display_order,t.id')->fetchAll(PDO::FETCH_ASSOC);
$selected=filter_var($_GET['theme_id']??null,FILTER_VALIDATE_INT);if(!$selected&&$themes)$selected=(int)$themes[0]['id'];
if($selected&&!in_array($selected,array_map('intval',array_column($themes,'id')),true)){http_response_code(403);exit;}
$rows=[];$whole=$selected&&(!$puProfessor||qp_whole_theme($pdo,$professorId,$selected));
if($selected){
 $scope=$puProfessor?px_scope_sql('questions','q'):'1=1';
 $s=$pdo->prepare("SELECT a.id,a.passation_type,a.completed_at,a.corrected_at,tr.first_name,tr.last_name,
 SUM(r.awarded_points) score,SUM(q.points) maximum,
 SUM(CASE WHEN q.question_type='open' THEN r.suggested_points ELSE NULL END) auto_score,
 SUM(CASE WHEN q.question_type='open' AND r.graded_at IS NOT NULL THEN r.awarded_points ELSE NULL END) human_score,
 SUM(CASE WHEN q.question_type='open' AND r.graded_at IS NULL THEN 1 ELSE 0 END) pending
 FROM attempts a JOIN trainees tr ON tr.id=a.trainee_id JOIN responses r ON r.attempt_id=a.id JOIN questions q ON q.id=r.question_id AND q.theme_id=a.theme_id
 WHERE a.theme_id=? AND $scope GROUP BY a.id,a.passation_type,a.completed_at,a.corrected_at,tr.first_name,tr.last_name ORDER BY a.id DESC");
 $s->execute([$selected]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html><html lang="<?= qp_h(htmlLanguage()) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= qp_h(pu('Course results','Résultats par cours')) ?></title><link rel="stylesheet" href="../assets/css/style.css"><link rel="stylesheet" href="../assets/css/ui.css"><script src="../assets/js/ui.js" defer></script></head><body><div class="container">
<?php require $puProfessor?dirname(__DIR__).'/professor/professor_nav.php':dirname(__DIR__).'/admin/admin_nav.php'; ?>
<main><header><h1><?= qp_h(pu('Course results','Résultats par cours')) ?></h1><p><?= qp_h(pu('Review attempts and grade responses within your course access. Trainee assignment is not required.','Consultez les tentatives et corrigez les réponses dans votre périmètre de cours. L’affectation du stagiaire n’est pas requise.')) ?></p></header>
<form method="get"><label for="pu-result-course"><?= qp_h(pu('Course','Cours')) ?></label><select id="pu-result-course" name="theme_id"><?php foreach($themes as $theme):?><option value="<?= (int)$theme['id'] ?>" <?= (int)$theme['id']===$selected?'selected':'' ?>><?= qp_h($theme['name']) ?></option><?php endforeach; ?></select><button><?= qp_h(pu('View attempts','Voir les tentatives')) ?></button></form>
<?php if($selected&&!$whole):?><p class="alert"><?= qp_h(pu('Chapter access: scores and responses cover your authorized chapters only. Whole-theme access is required for a full-attempt PDF.','Accès par chapitre : notes et réponses limitées aux chapitres autorisés. Un accès au thème entier est requis pour le PDF complet.')) ?></p><?php endif; ?>
<table><caption><?= count($rows) ?> <?= qp_h(pu('attempts in this course','tentatives dans ce cours')) ?></caption><thead><tr><?php foreach([pu('Attempt / trainee','Tentative / stagiaire'),pu('Score','Note'),pu('Level','Niveau'),pu('Automatic open score','Note automatique ouverte'),pu('Human awarded open score','Note humaine ouverte'),pu('Grading status','État de correction'),pu('Actions','Actions')] as $label):?><th scope="col"><?= qp_h($label) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach($rows as $r):$pending=(int)$r['pending'];$percent=(float)$r['maximum']>0?100*(float)$r['score']/(float)$r['maximum']:0;?><tr><td>#<?= (int)$r['id'] ?> · <?= qp_h($r['first_name'].' '.$r['last_name']) ?><br><?= qp_h($r['passation_type']) ?><br><?= qp_h($r['completed_at']) ?></td><td><?= qp_h($r['score']) ?> / <?= qp_h($r['maximum']) ?> (<?= round($percent,1) ?> %)</td><td><?= qp_h($pending?pu('Provisional','Provisoire'):($whole?getPositioningLevel($pdo,$percent,t('h150_not_defined')):pu('Authorized chapter scope','Périmètre des chapitres autorisés'))) ?></td><td><?= qp_h($r['auto_score']??pu('Not configured','Non configurée')) ?></td><td><?= qp_h($r['human_score']??pu('Not reviewed','Non corrigée')) ?></td><td><span class="badge"><?= qp_h($pending?$pending.' '.pu('pending','en attente'):pu('Reviewed','Corrigé')) ?></span></td><td><a href="attempt_details.php?id=<?= (int)$r['id'] ?>"><?= qp_h(pu('View / grade','Voir / corriger')) ?></a><?php if($whole&&!$pending&&$r['corrected_at']&&$r['completed_at']&&$r['passation_type']==='final'):?><br><a class="btn" href="download_result_pdf.php?id=<?= (int)$r['id'] ?>"><?= qp_h(pu('Print / PDF','Imprimer / PDF')) ?></a><?php endif; ?></td></tr><?php endforeach; ?></tbody></table>
<?php if(!$rows):?><p><?= qp_h(pu('No attempts for this course in your access scope.','Aucune tentative pour ce cours dans votre périmètre.')) ?></p><?php endif; ?></main></div></body></html>
