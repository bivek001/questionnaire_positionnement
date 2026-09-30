<?php
// CLI only. --sql prints the equivalent idempotent SQL for phpMyAdmin.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$columns = [
 'questions'=>['pre_question_content'=>'MEDIUMTEXT NULL','model_answer'=>'TEXT NULL','grading_rubric'=>'TEXT NULL','grading_keywords'=>'TEXT NULL'],
 'responses'=>['suggested_points'=>'DECIMAL(10,2) NULL','suggestion_details'=>'MEDIUMTEXT NULL','suggested_at'=>'DATETIME NULL']
];
$indexes = ['professor_content_assignments'=>['pkg_course_scope'=>'professor_id,theme_id,chapter_id'],
 'responses'=>['pkg_attempt_question'=>'attempt_id,question_id'], 'attempts'=>['pkg_theme_trainee'=>'theme_id,trainee_id']];
$create = "CREATE TABLE IF NOT EXISTS notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, recipient_type ENUM('admin','professor','trainee') NOT NULL,
 recipient_id INT UNSIGNED NOT NULL, type VARCHAR(60) NOT NULL,title VARCHAR(100) NOT NULL,message VARCHAR(255) NOT NULL,
 url VARCHAR(255) NULL,read_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX recipient_unread(recipient_type,recipient_id,read_at,id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
// Polymorphic recipients cannot have one FK to three account tables. Admin 0 is a shared inbox.
function pkg_note(string $role,string $id,string $event): string {
 return "INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('$role',$id,'$event','pkg_event_$event','pkg_message_$event');";
}
function pkg_course_note(string $attempt, string $event, ?string $question = null): string {
 $scope = $question === null
 ? "(p.chapter_id IS NULL OR EXISTS (SELECT 1 FROM responses nr JOIN questions nq ON nq.id=nr.question_id WHERE nr.attempt_id=$attempt AND nq.theme_id=a.theme_id AND nq.chapter_id=p.chapter_id))"
 : "EXISTS (SELECT 1 FROM questions nq WHERE nq.id=$question AND nq.theme_id=a.theme_id AND (p.chapter_id IS NULL OR p.chapter_id=nq.chapter_id))";
 return "INSERT INTO notifications(recipient_type,recipient_id,type,title,message,url)
 SELECT DISTINCT 'professor',p.professor_id,'$event','pkg_event_$event','pkg_message_$event',CONCAT('professor/attempt_details.php?id=',a.id)
 FROM attempts a JOIN professor_content_assignments p ON p.theme_id=a.theme_id
 JOIN professors pr ON pr.id=p.professor_id AND pr.is_active=1 WHERE a.id=$attempt AND $scope;";
}
$triggers=[];
foreach (['trainees'=>'trainee','professors'=>'professor'] as $table=>$role) {
 $extra = $role==='professor' ? ' OR NOT(OLD.username <=> NEW.username) OR NOT(OLD.is_active <=> NEW.is_active)' : ' OR NOT(OLD.date_of_birth <=> NEW.date_of_birth)';
 $triggers['pkg_'.$role.'_updated']="AFTER UPDATE ON $table FOR EACH ROW BEGIN IF NOT(OLD.first_name <=> NEW.first_name) OR NOT(OLD.last_name <=> NEW.last_name) OR NOT(OLD.email <=> NEW.email) OR NOT(OLD.password <=> NEW.password) $extra THEN ".pkg_note($role,'NEW.id','account').pkg_note('admin','0','account')." END IF; END";
}
$triggers['pkg_assignment_created']="AFTER INSERT ON questionnaire_assignments FOR EACH ROW BEGIN ".pkg_note('trainee','NEW.trainee_id','assignment').pkg_note('admin','0','assignment')."
 INSERT INTO notifications(recipient_type,recipient_id,type,title,message) SELECT DISTINCT 'professor',p.professor_id,'assignment','pkg_event_assignment','pkg_message_assignment' FROM professor_content_assignments p JOIN professors pr ON pr.id=p.professor_id AND pr.is_active=1 WHERE p.theme_id=NEW.theme_id AND p.chapter_id IS NULL; END";
$triggers['pkg_attempt_completed']="AFTER UPDATE ON attempts FOR EACH ROW BEGIN IF OLD.completed_at IS NULL AND NEW.completed_at IS NOT NULL THEN ".pkg_note('trainee','NEW.trainee_id','completion').pkg_note('admin','0','completion').pkg_course_note('NEW.id','completion')." END IF; END";
$triggers['pkg_response_graded']="AFTER UPDATE ON responses FOR EACH ROW BEGIN IF NEW.graded_at IS NOT NULL AND (OLD.graded_at IS NULL OR NOT(OLD.awarded_points <=> NEW.awarded_points) OR NOT(OLD.trainer_comment <=> NEW.trainer_comment)) THEN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) SELECT 'trainee',a.trainee_id,'grading','pkg_event_grading','pkg_message_grading' FROM attempts a WHERE a.id=NEW.attempt_id; ".pkg_note('admin','0','grading').pkg_course_note('NEW.attempt_id','grading','NEW.question_id')." END IF; END";
foreach (['INSERT'=>'NEW','DELETE'=>'OLD','UPDATE'=>'NEW'] as $event=>$row) {
 $body=pkg_note('professor',"$row.professor_id",'access').pkg_note('admin','0','access');
 if ($event==='UPDATE') $body="IF NOT(OLD.professor_id <=> NEW.professor_id) OR NOT(OLD.theme_id <=> NEW.theme_id) OR NOT(OLD.chapter_id <=> NEW.chapter_id) THEN ".pkg_note('professor','OLD.professor_id','access').$body." END IF;";
 $triggers['pkg_access_'.strtolower($event)]="AFTER $event ON professor_content_assignments FOR EACH ROW BEGIN $body END";
}
foreach (['themes','chapters','lessons','topics','paragraphs','questions'] as $table) {
 $triggers['pkg_content_'.$table]="AFTER INSERT ON $table FOR EACH ROW BEGIN ".pkg_note('admin','0','content')." END";
}
if (in_array('--sql',$argv,true)) {
 echo "-- Corrective package. Back up first; select the existing questionnaire database.\nSET NAMES utf8mb4;\n";
 foreach ($columns as $table=>$fields) foreach ($fields as $name=>$definition) {
  $ddl="ALTER TABLE `$table` ADD COLUMN `$name` $definition";
  echo "SET @pkg_ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$table' AND COLUMN_NAME='$name'),'SELECT 1', '$ddl');\nPREPARE pkg_stmt FROM @pkg_ddl; EXECUTE pkg_stmt; DEALLOCATE PREPARE pkg_stmt;\n";
 }
 foreach ($indexes as $table=>$fields) foreach ($fields as $name=>$definition) {
  $ddl="ALTER TABLE `$table` ADD INDEX `$name` ($definition)";
  echo "SET @pkg_ddl = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$table' AND INDEX_NAME='$name'),'SELECT 1', '$ddl');\nPREPARE pkg_stmt FROM @pkg_ddl; EXECUTE pkg_stmt; DEALLOCATE PREPARE pkg_stmt;\n";
 }
 echo "$create;\nDELIMITER $$\n";
 foreach ($triggers as $name=>$body) echo "DROP TRIGGER IF EXISTS `$name`$$\nCREATE TRIGGER `$name` $body$$\n";
 echo "DELIMITER ;\n";
 exit;
}
$databaseConfig = __DIR__.'/../../config/database.php';
foreach ($argv as $argument) if (str_starts_with($argument,'--database-config=')) $databaseConfig=substr($argument,18);
if (!is_file($databaseConfig)) throw new RuntimeException('Database configuration was not found.');
require $databaseConfig;
$required = ['professors'=>['id','first_name','last_name','email','username','password','is_active'],
 'trainees'=>['id','first_name','last_name','email','password','date_of_birth'],
 'professor_content_assignments'=>['professor_id','theme_id','chapter_id'],
 'attempts'=>['id','theme_id','trainee_id','completed_at'], 'responses'=>['id','attempt_id','question_id','graded_at','awarded_points','trainer_comment'],
 'questions'=>['id','theme_id','chapter_id'], 'questionnaire_assignments'=>['trainee_id','theme_id'],
 'themes'=>['id'],'chapters'=>['id'],'lessons'=>['id'],'topics'=>['id'],'paragraphs'=>['id']];
foreach ($required as $table=>$fields) {
 $s=$pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'); $s->execute([$table]);
 if (array_diff($fields,$s->fetchAll(PDO::FETCH_COLUMN))) throw new RuntimeException("Schema preflight failed: $table. No migration changes applied.");
}
foreach ($columns as $table=>$fields) foreach ($fields as $name=>$definition) {
 $s=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
 $s->execute([$table,$name]); if (!$s->fetchColumn()) $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$name` $definition");
}
foreach ($indexes as $table=>$fields) foreach ($fields as $name=>$definition) {
 $s=$pdo->prepare('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
 $s->execute([$table,$name]); if (!$s->fetchColumn()) $pdo->exec("ALTER TABLE `$table` ADD INDEX `$name` ($definition)");
}
$pdo->exec($create);
foreach ($triggers as $name=>$body) { $pdo->exec("DROP TRIGGER IF EXISTS `$name`"); $pdo->exec("CREATE TRIGGER `$name` $body"); }
echo "Corrective schema and activity triggers installed. Existing scores and content preserved.\n";
