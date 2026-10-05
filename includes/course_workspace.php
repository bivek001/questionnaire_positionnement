<?php
require_once __DIR__.'/positioning_ux.php';
header('Cache-Control: no-store');
$source=pu_source(); $error=''; $saved=false;
$scope=$puProfessor ? px_scope_sql('questions','q') : '1=1';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 $qid=filter_var($_POST['question_id']??null,FILTER_VALIDATE_INT);
 $codes=$_POST['competency_codes'] ?? [$_POST['competency_code'] ?? ''];
 if(!is_array($codes) || count($codes)>20) {http_response_code(400);exit;}
 foreach($codes as $code) if(!is_string($code) || !isset($source['competencies'][$code])) {http_response_code(400);exit;}
 $codes=array_values(array_unique($codes));
 if (!$qid || !$codes) {http_response_code(400);exit(pu('Invalid competency or question.','Compétence ou question invalide.'));}
 $s=$pdo->prepare("SELECT q.id FROM questions q WHERE q.id=? AND $scope");$s->execute([$qid]);
 if(!$s->fetchColumn()){http_response_code(403);exit;}
 // Mapping is pedagogical data: freeze it once responses exist, like question editing.
 $pdo->beginTransaction();
 try {
  $s=$pdo->prepare("SELECT q.id FROM questions q WHERE q.id=? AND $scope FOR UPDATE");$s->execute([$qid]);
  if(!$s->fetchColumn())throw new RuntimeException('permission');
  $s=$pdo->prepare('SELECT id FROM responses WHERE question_id=? LIMIT 1');$s->execute([$qid]);
  if($s->fetchColumn())throw new RuntimeException('history');
  $s=$pdo->prepare('DELETE FROM positioning_question_metadata WHERE question_id=?');$s->execute([$qid]);
  foreach($codes as $code){
   preg_match('/^(\d+)/',$code,$domainMatch);$domain=(int)$domainMatch[1];
   if(!isset($source['domains'][(string)$domain]))throw new RuntimeException('domain');
   $s=$pdo->prepare('INSERT INTO positioning_question_metadata(question_id,competency_code,domain_number,diagnostic) VALUES(?,?,?,?)');
   $s->execute([$qid,$code,$domain,$source['domains'][(string)$domain]['diagnostic']]);
  }
  $pdo->commit();$saved=true;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error=pu('Mapping could not be saved. Apply the UX migration first; attempted questions keep their historical mapping.','Association non enregistrée. Appliquez la migration UX ; les questions déjà passées conservent leur association historique.');}
}
$themes=$pdo->query('SELECT t.* FROM themes t WHERE '.($puProfessor?px_scope_sql('themes','t'):'1=1').' ORDER BY t.display_order,t.id')->fetchAll(PDO::FETCH_ASSOC);
$selected=filter_var($_GET['theme_id']??null,FILTER_VALIDATE_INT);
if(!$selected && $themes)$selected=(int)$themes[0]['id'];
$allowed=array_map('intval',array_column($themes,'id'));if($selected&&!in_array($selected,$allowed,true)){http_response_code(403);exit;}
$questions=[];$chapters=[];$lessons=[];$topics=[];$paragraphs=[];$meta=[];
if($selected){
 $s=$pdo->prepare("SELECT q.* FROM questions q WHERE q.theme_id=? AND $scope ORDER BY q.display_order,q.id");$s->execute([$selected]);$questions=$s->fetchAll(PDO::FETCH_ASSOC);$meta=pu_metadata($pdo,$selected);
 foreach(['chapters','lessons','topics','paragraphs'] as $table){
  $conditions=['chapters'=>'e.theme_id=?','lessons'=>'EXISTS(SELECT 1 FROM chapters c WHERE c.id=e.chapter_id AND c.theme_id=?)','topics'=>'EXISTS(SELECT 1 FROM lessons l JOIN chapters c ON c.id=l.chapter_id WHERE l.id=e.lesson_id AND c.theme_id=?)','paragraphs'=>'EXISTS(SELECT 1 FROM topics t JOIN lessons l ON l.id=t.lesson_id JOIN chapters c ON c.id=l.chapter_id WHERE t.id=e.topic_id AND c.theme_id=?)'];
  $s=$pdo->prepare("SELECT e.* FROM $table e WHERE ".$conditions[$table].' AND '.($puProfessor?px_scope_sql($table,'e'):'1=1').' ORDER BY e.display_order,e.id');$s->execute([$selected]);$$table=$s->fetchAll(PDO::FETCH_ASSOC);
 }
}
function pu_children($rows,$key,$id){return array_filter($rows,function($row)use($key,$id){return (int)$row[$key]===(int)$id;});}
function pu_author_question($q,$meta,$source,$puProfessor){
 echo '<article class="card"><p><span class="badge">'.qp_h($q['question_type']).'</span> · '.qp_h($q['points']).' '.qp_h(pu('points','points')).'</p><h5>'.qp_h($q['question_text']).'</h5><p>'.qp_h(pu('Question','Question')).' #'.(int)$q['id'].'</p><div class="pu-author-actions"><a href="edit_question.php?id='.(int)$q['id'].'">'.qp_h(pu('Edit question, choices, correction and media','Modifier question, choix, correction et média')).'</a></div>';
 $m=$meta[$q['id']]??null;
 if($m)echo '<p class="badge">'.qp_h($m['competency_code'].' · '.$source['domains'][(string)$m['domain_number']]['title']).'</p>';
 echo '<form method="post">';if($puProfessor)px_csrf_field();else ux_csrf_field();
 echo '<input type="hidden" name="question_id" value="'.(int)$q['id'].'"><label>'.qp_h(pu('Source competency code','Code de compétence du document')).'<select name="competency_codes[]" multiple size="6" required>';
 foreach($source['competencies'] as $code=>$entry){if(!isset($source['domains'][(string)(int)$code]))continue;echo '<option value="'.qp_h($code).'" '.(in_array($code,$m['codes']??[],true)?'selected':'').'>'.qp_h($code.' · '.($entry['titles'][0]??'')).'</option>';}
 echo '</select></label><p>'.qp_h(pu('Select each criterion assessed by this exercise. Shared exercises may assess several codes; do not duplicate their points.','Sélectionnez chaque critère évalué par cet exercice. Un exercice partagé peut évaluer plusieurs codes ; ne dupliquez pas ses points.')).'</p><button type="submit">'.qp_h(pu('Save mapping','Enregistrer l’association')).'</button></form>';
 if($m){echo '<details><summary>'.qp_h(pu('Source threshold excerpts — author review required','Extraits de seuils — vérification pédagogique requise')).'</summary>';foreach($m['codes'] as $sourceCode) foreach($source['competencies'][$sourceCode]['thresholds'] as $line)echo '<p>'.qp_h($line).'</p>';echo '<p>'.qp_h(pu('These verbatim excerpts are not automatic grading rules. Check the original table, grouped criteria and contradictions before configuring correction.','Ces extraits ne constituent pas des règles automatiques. Vérifiez le tableau original, les critères groupés et les contradictions avant de configurer la correction.')).'</p></details>';}
 echo '<details><summary>'.qp_h(pu('Trainee question preview — corrections hidden','Aperçu question stagiaire — correction masquée')).'</summary><div class="pu-rich">'.pu_rich($q['pre_question_content']??'').'</div><p>'.nl2br(qp_h($q['question_text'])).'</p>';
 pu_media($q['media_type'],$q['media_path'],pu('Exercise media','Média de l’exercice'));
 if($q['question_type']==='open') echo '<label>'.qp_h(pu('Your answer','Votre réponse')).'<textarea rows="6" disabled></textarea></label>';
 else {
  global $pdo;$s=$pdo->prepare('SELECT choice_text FROM choices WHERE question_id=? ORDER BY display_order,id');$s->execute([$q['id']]);
  foreach($s as $choice)echo '<label class="pu-choice"><input disabled type="'.($q['question_type']==='single_choice'?'radio':'checkbox').'">'.qp_h($choice['choice_text']).'</label>';
 }
 echo '</details></article>';
}
?>
<!doctype html><html lang="<?= qp_h(htmlLanguage()) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= qp_h(pu('Course workspace','Espace cours')) ?></title><link rel="stylesheet" href="../assets/css/style.css"><link rel="stylesheet" href="../assets/css/ui.css"><script src="../assets/js/ui.js" defer></script></head><body><div class="container">
<?php require $puProfessor?dirname(__DIR__).'/professor/professor_nav.php':dirname(__DIR__).'/admin/admin_nav.php'; ?>
<main><header><h1><?= qp_h(pu('Course workspace','Espace cours')) ?></h1><p><?= qp_h(pu('Theme → Chapter → Lesson → Topic → Paragraph → Question','Thème → Chapitre → Leçon → Sujet → Paragraphe → Question')) ?></p></header>
<?php if($saved):?><p class="alert-success alert" role="status"><?= qp_h(pu('Source competency saved.','Compétence source enregistrée.')) ?></p><?php endif;if($error):?><p class="alert-error alert" role="alert"><?= qp_h($error) ?></p><?php endif; ?>
<section class="pu-source-guide"><h2><?= qp_h(pu('Where the Word content belongs','Où placer le contenu Word')) ?></h2><p><?= qp_h(pu('Paragraph: authored reading passage, situation, instructions and shared tables/images. Question: the task to answer, choices and points. Correction: expected answer, key criteria and grading notes, visible only to staff.','Paragraphe : texte à lire, situation, consignes et tableaux/images partagés. Question : tâche à réaliser, choix et points. Correction : réponse attendue, critères clés et notes, visibles uniquement par l’équipe pédagogique.')) ?></p><p><?= qp_h(pu('Never paste an entire correction page into a trainee paragraph or image. Preview every exercise before publication.','Ne collez jamais une page complète de correction dans un paragraphe ou une image stagiaire. Prévisualisez chaque exercice avant publication.')) ?></p><p><?= qp_h(pu('Keep the original French wording, the two diagnostics and all 10 domains. Qualitative criteria and conflicting thresholds require human grading.','Conservez le texte français original, les deux diagnostics et les 10 domaines. Les critères qualitatifs et seuils contradictoires nécessitent une correction humaine.')) ?></p></section>
<div class="pu-author-actions"><a class="btn" href="manage_content.php"><?= qp_h(pu('Add / edit structure and source paragraphs','Ajouter / modifier la structure et les paragraphes source')) ?></a><a class="btn" href="questions.php"><?= qp_h(pu('Add questions','Ajouter des questions')) ?></a><a class="btn-secondary btn" href="<?= 'course_results.php' ?>"><?= qp_h(pu('Course results','Résultats du cours')) ?></a></div>
<form method="get"><label for="pu-course"><?= qp_h(pu('Course / test','Cours / test')) ?></label><select id="pu-course" name="theme_id"><?php foreach($themes as $theme):?><option value="<?= (int)$theme['id'] ?>" <?= (int)$theme['id']===$selected?'selected':'' ?>><?= qp_h($theme['name']) ?></option><?php endforeach; ?></select><button><?= qp_h(pu('Open course','Ouvrir le cours')) ?></button></form>
<?php if(!$themes):?><p class="alert"><?= qp_h(pu('No course is available in this database / authorization scope. Create or authorize a course first.','Aucun cours disponible dans cette base / ce périmètre d’autorisation. Créez ou autorisez un cours.')) ?></p><?php endif; ?>
<section class="pu-tree" aria-label="<?= qp_h(pu('Course hierarchy and author preview','Hiérarchie et aperçu auteur')) ?>">
<?php foreach($chapters as $chapter):?><details open><summary><?= qp_h(pu('Chapter','Chapitre').': '.$chapter['title']) ?></summary><div class="pu-tree">
<?php foreach(pu_children($lessons,'chapter_id',$chapter['id']) as $lesson):?><details><summary><?= qp_h(pu('Lesson','Leçon').': '.$lesson['title']) ?></summary><div class="pu-tree">
<?php foreach(pu_children($topics,'lesson_id',$lesson['id']) as $topic):?><details><summary><?= qp_h(pu('Topic','Sujet').': '.$topic['title']) ?></summary><div class="pu-tree">
<?php foreach(pu_children($paragraphs,'topic_id',$topic['id']) as $paragraph):?><details><summary><?= qp_h(pu('Paragraph / source','Paragraphe / source').': '.$paragraph['title']) ?></summary><article class="card pu-rich"><?= pu_rich($paragraph['content']??'') ?><?php pu_media($paragraph['media_type'],$paragraph['media_path'],$paragraph['title']); ?></article>
<?php foreach(pu_children($questions,'paragraph_id',$paragraph['id']) as $q)pu_author_question($q,$meta,$source,$puProfessor); ?></details><?php endforeach; ?></div></details><?php endforeach; ?></div></details><?php endforeach; ?></div></details><?php endforeach; ?>
<?php foreach($questions as $q)if(!$q['paragraph_id'])pu_author_question($q,$meta,$source,$puProfessor); ?>
</section></main></div></body></html>
