<?php
// CLI only. Back up first. Existing grades and authored content are not rewritten.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../config/database.php';
function column(PDO $pdo,string $table,string $name,string $definition): void {
 $s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$s->execute([$table,$name]);
 if(!$s->fetchColumn())$pdo->exec("ALTER TABLE `$table` ADD COLUMN `$name` $definition");
}
foreach(['pre_question_content'=>'TEXT NULL','model_answer'=>'TEXT NULL','grading_rubric'=>'TEXT NULL','grading_keywords'=>'TEXT NULL'] as $name=>$type)column($pdo,'questions',$name,$type);
foreach(['suggested_points'=>'DECIMAL(10,2) NULL','suggestion_details'=>'MEDIUMTEXT NULL','suggested_at'=>'DATETIME NULL'] as $name=>$type)column($pdo,'responses',$name,$type);
$pdo->exec("CREATE TABLE IF NOT EXISTS notifications (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,recipient_type ENUM('admin','professor','trainee') NOT NULL,recipient_id INT UNSIGNED NOT NULL,type VARCHAR(60) NOT NULL,title VARCHAR(100) NOT NULL,message VARCHAR(255) NOT NULL,url VARCHAR(255) NULL,read_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX recipient_unread(recipient_type,recipient_id,read_at,id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
// Admin recipient 0 denotes a shared administrator inbox. Messages contain translation keys, never passwords or answers.
function note(string $role,string $id,string $event):string{return "INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('$role',$id,'$event','pkg_event_$event','pkg_message_$event');";}
$triggers=[];
foreach(['trainees'=>'trainee','professors'=>'professor'] as $table=>$role){
 $extra=$role==='professor'?" OR NOT(OLD.username <=> NEW.username) OR OLD.is_active<>NEW.is_active":" OR NOT(OLD.date_of_birth <=> NEW.date_of_birth)";
 $triggers['pkg_'.$role.'_updated']="AFTER UPDATE ON $table FOR EACH ROW BEGIN IF NOT(OLD.first_name <=> NEW.first_name) OR NOT(OLD.last_name <=> NEW.last_name) OR NOT(OLD.email <=> NEW.email) OR NOT(OLD.password <=> NEW.password) $extra THEN ".note($role,'NEW.id','account').note('admin','0','account')." END IF; END";
}
$triggers['pkg_assignment_created']="AFTER INSERT ON questionnaire_assignments FOR EACH ROW BEGIN ".note('trainee','NEW.trainee_id','assignment')." END";
$triggers['pkg_attempt_completed']="AFTER UPDATE ON attempts FOR EACH ROW BEGIN IF OLD.completed_at IS NULL AND NEW.completed_at IS NOT NULL THEN ".note('trainee','NEW.trainee_id','completion').note('admin','0','completion')." INSERT INTO notifications(recipient_type,recipient_id,type,title,message) SELECT DISTINCT 'professor',p.professor_id,'completion','pkg_event_completion','pkg_message_completion' FROM professor_content_assignments p WHERE p.theme_id=NEW.theme_id; END IF; END";
$triggers['pkg_response_graded']="AFTER UPDATE ON responses FOR EACH ROW BEGIN IF NEW.graded_at IS NOT NULL AND (OLD.graded_at IS NULL OR NOT(OLD.awarded_points <=> NEW.awarded_points) OR NOT(OLD.trainer_comment <=> NEW.trainer_comment)) THEN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) SELECT 'trainee',a.trainee_id,'grading','pkg_event_grading','pkg_message_grading' FROM attempts a WHERE a.id=NEW.attempt_id; ".note('admin','0','grading')." END IF; END";
foreach(['INSERT'=>'NEW','DELETE'=>'OLD'] as $event=>$row){
 $triggers['pkg_access_'.strtolower($event)]="AFTER $event ON professor_content_assignments FOR EACH ROW BEGIN ".note('professor',"$row.professor_id",'access').note('admin','0','access')." END";
}
foreach(['themes','chapters','lessons','topics','paragraphs','questions'] as $table){
 $triggers['pkg_content_'.$table]="AFTER INSERT ON $table FOR EACH ROW BEGIN ".note('admin','0','content')." END";
}
foreach($triggers as $name=>$sql){$pdo->exec("DROP TRIGGER IF EXISTS `$name`");$pdo->exec("CREATE TRIGGER `$name` $sql");}
echo "Package schema and activity triggers installed. Existing scores/content preserved.\n";
