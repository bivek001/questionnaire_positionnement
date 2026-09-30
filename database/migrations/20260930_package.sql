-- Corrective package. Back up first; select the existing questionnaire database.
SET NAMES utf8mb4;
SET @pkg_ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='questions' AND COLUMN_NAME='pre_question_content'),'SELECT 1', 'ALTER TABLE `questions` ADD COLUMN `pre_question_content` MEDIUMTEXT NULL');
PREPARE pkg_stmt FROM @pkg_ddl; EXECUTE pkg_stmt; DEALLOCATE PREPARE pkg_stmt;
SET @pkg_ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='questions' AND COLUMN_NAME='model_answer'),'SELECT 1', 'ALTER TABLE `questions` ADD COLUMN `model_answer` TEXT NULL');
PREPARE pkg_stmt FROM @pkg_ddl; EXECUTE pkg_stmt; DEALLOCATE PREPARE pkg_stmt;
SET @pkg_ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='questions' AND COLUMN_NAME='grading_rubric'),'SELECT 1', 'ALTER TABLE `questions` ADD COLUMN `grading_rubric` TEXT NULL');
PREPARE pkg_stmt FROM @pkg_ddl; EXECUTE pkg_stmt; DEALLOCATE PREPARE pkg_stmt;
SET @pkg_ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='questions' AND COLUMN_NAME='grading_keywords'),'SELECT 1', 'ALTER TABLE `questions` ADD COLUMN `grading_keywords` TEXT NULL');
PREPARE pkg_stmt FROM @pkg_ddl; EXECUTE pkg_stmt; DEALLOCATE PREPARE pkg_stmt;
SET @pkg_ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='responses' AND COLUMN_NAME='suggested_points'),'SELECT 1', 'ALTER TABLE `responses` ADD COLUMN `suggested_points` DECIMAL(10,2) NULL');
PREPARE pkg_stmt FROM @pkg_ddl; EXECUTE pkg_stmt; DEALLOCATE PREPARE pkg_stmt;
SET @pkg_ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='responses' AND COLUMN_NAME='suggestion_details'),'SELECT 1', 'ALTER TABLE `responses` ADD COLUMN `suggestion_details` MEDIUMTEXT NULL');
PREPARE pkg_stmt FROM @pkg_ddl; EXECUTE pkg_stmt; DEALLOCATE PREPARE pkg_stmt;
SET @pkg_ddl = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='responses' AND COLUMN_NAME='suggested_at'),'SELECT 1', 'ALTER TABLE `responses` ADD COLUMN `suggested_at` DATETIME NULL');
PREPARE pkg_stmt FROM @pkg_ddl; EXECUTE pkg_stmt; DEALLOCATE PREPARE pkg_stmt;
SET @pkg_ddl = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='professor_content_assignments' AND INDEX_NAME='pkg_course_scope'),'SELECT 1', 'ALTER TABLE `professor_content_assignments` ADD INDEX `pkg_course_scope` (professor_id,theme_id,chapter_id)');
PREPARE pkg_stmt FROM @pkg_ddl; EXECUTE pkg_stmt; DEALLOCATE PREPARE pkg_stmt;
SET @pkg_ddl = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='responses' AND INDEX_NAME='pkg_attempt_question'),'SELECT 1', 'ALTER TABLE `responses` ADD INDEX `pkg_attempt_question` (attempt_id,question_id)');
PREPARE pkg_stmt FROM @pkg_ddl; EXECUTE pkg_stmt; DEALLOCATE PREPARE pkg_stmt;
SET @pkg_ddl = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='attempts' AND INDEX_NAME='pkg_theme_trainee'),'SELECT 1', 'ALTER TABLE `attempts` ADD INDEX `pkg_theme_trainee` (theme_id,trainee_id)');
PREPARE pkg_stmt FROM @pkg_ddl; EXECUTE pkg_stmt; DEALLOCATE PREPARE pkg_stmt;
CREATE TABLE IF NOT EXISTS notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, recipient_type ENUM('admin','professor','trainee') NOT NULL,
 recipient_id INT UNSIGNED NOT NULL, type VARCHAR(60) NOT NULL,title VARCHAR(100) NOT NULL,message VARCHAR(255) NOT NULL,
 url VARCHAR(255) NULL,read_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX recipient_unread(recipient_type,recipient_id,read_at,id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
DELIMITER $$
DROP TRIGGER IF EXISTS `pkg_trainee_updated`$$
CREATE TRIGGER `pkg_trainee_updated` AFTER UPDATE ON trainees FOR EACH ROW BEGIN IF NOT(OLD.first_name <=> NEW.first_name) OR NOT(OLD.last_name <=> NEW.last_name) OR NOT(OLD.email <=> NEW.email) OR NOT(OLD.password <=> NEW.password)  OR NOT(OLD.date_of_birth <=> NEW.date_of_birth) THEN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('trainee',NEW.id,'account','pkg_event_account','pkg_message_account');INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'account','pkg_event_account','pkg_message_account'); END IF; END$$
DROP TRIGGER IF EXISTS `pkg_professor_updated`$$
CREATE TRIGGER `pkg_professor_updated` AFTER UPDATE ON professors FOR EACH ROW BEGIN IF NOT(OLD.first_name <=> NEW.first_name) OR NOT(OLD.last_name <=> NEW.last_name) OR NOT(OLD.email <=> NEW.email) OR NOT(OLD.password <=> NEW.password)  OR NOT(OLD.username <=> NEW.username) OR NOT(OLD.is_active <=> NEW.is_active) THEN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('professor',NEW.id,'account','pkg_event_account','pkg_message_account');INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'account','pkg_event_account','pkg_message_account'); END IF; END$$
DROP TRIGGER IF EXISTS `pkg_assignment_created`$$
CREATE TRIGGER `pkg_assignment_created` AFTER INSERT ON questionnaire_assignments FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('trainee',NEW.trainee_id,'assignment','pkg_event_assignment','pkg_message_assignment');INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'assignment','pkg_event_assignment','pkg_message_assignment');
 INSERT INTO notifications(recipient_type,recipient_id,type,title,message) SELECT DISTINCT 'professor',p.professor_id,'assignment','pkg_event_assignment','pkg_message_assignment' FROM professor_content_assignments p JOIN professors pr ON pr.id=p.professor_id AND pr.is_active=1 WHERE p.theme_id=NEW.theme_id AND p.chapter_id IS NULL; END$$
DROP TRIGGER IF EXISTS `pkg_attempt_completed`$$
CREATE TRIGGER `pkg_attempt_completed` AFTER UPDATE ON attempts FOR EACH ROW BEGIN IF OLD.completed_at IS NULL AND NEW.completed_at IS NOT NULL THEN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('trainee',NEW.trainee_id,'completion','pkg_event_completion','pkg_message_completion');INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'completion','pkg_event_completion','pkg_message_completion');INSERT INTO notifications(recipient_type,recipient_id,type,title,message,url)
 SELECT DISTINCT 'professor',p.professor_id,'completion','pkg_event_completion','pkg_message_completion',CONCAT('professor/attempt_details.php?id=',a.id)
 FROM attempts a JOIN professor_content_assignments p ON p.theme_id=a.theme_id
 JOIN professors pr ON pr.id=p.professor_id AND pr.is_active=1 WHERE a.id=NEW.id AND (p.chapter_id IS NULL OR EXISTS (SELECT 1 FROM responses nr JOIN questions nq ON nq.id=nr.question_id WHERE nr.attempt_id=NEW.id AND nq.theme_id=a.theme_id AND nq.chapter_id=p.chapter_id)); END IF; END$$
DROP TRIGGER IF EXISTS `pkg_response_graded`$$
CREATE TRIGGER `pkg_response_graded` AFTER UPDATE ON responses FOR EACH ROW BEGIN IF NEW.graded_at IS NOT NULL AND (OLD.graded_at IS NULL OR NOT(OLD.awarded_points <=> NEW.awarded_points) OR NOT(OLD.trainer_comment <=> NEW.trainer_comment)) THEN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) SELECT 'trainee',a.trainee_id,'grading','pkg_event_grading','pkg_message_grading' FROM attempts a WHERE a.id=NEW.attempt_id; INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'grading','pkg_event_grading','pkg_message_grading');INSERT INTO notifications(recipient_type,recipient_id,type,title,message,url)
 SELECT DISTINCT 'professor',p.professor_id,'grading','pkg_event_grading','pkg_message_grading',CONCAT('professor/attempt_details.php?id=',a.id)
 FROM attempts a JOIN professor_content_assignments p ON p.theme_id=a.theme_id
 JOIN professors pr ON pr.id=p.professor_id AND pr.is_active=1 WHERE a.id=NEW.attempt_id AND EXISTS (SELECT 1 FROM questions nq WHERE nq.id=NEW.question_id AND nq.theme_id=a.theme_id AND (p.chapter_id IS NULL OR p.chapter_id=nq.chapter_id)); END IF; END$$
DROP TRIGGER IF EXISTS `pkg_access_insert`$$
CREATE TRIGGER `pkg_access_insert` AFTER INSERT ON professor_content_assignments FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('professor',NEW.professor_id,'access','pkg_event_access','pkg_message_access');INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'access','pkg_event_access','pkg_message_access'); END$$
DROP TRIGGER IF EXISTS `pkg_access_delete`$$
CREATE TRIGGER `pkg_access_delete` AFTER DELETE ON professor_content_assignments FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('professor',OLD.professor_id,'access','pkg_event_access','pkg_message_access');INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'access','pkg_event_access','pkg_message_access'); END$$
DROP TRIGGER IF EXISTS `pkg_access_update`$$
CREATE TRIGGER `pkg_access_update` AFTER UPDATE ON professor_content_assignments FOR EACH ROW BEGIN IF NOT(OLD.professor_id <=> NEW.professor_id) OR NOT(OLD.theme_id <=> NEW.theme_id) OR NOT(OLD.chapter_id <=> NEW.chapter_id) THEN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('professor',OLD.professor_id,'access','pkg_event_access','pkg_message_access');INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('professor',NEW.professor_id,'access','pkg_event_access','pkg_message_access');INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'access','pkg_event_access','pkg_message_access'); END IF; END$$
DROP TRIGGER IF EXISTS `pkg_content_themes`$$
CREATE TRIGGER `pkg_content_themes` AFTER INSERT ON themes FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'content','pkg_event_content','pkg_message_content'); END$$
DROP TRIGGER IF EXISTS `pkg_content_chapters`$$
CREATE TRIGGER `pkg_content_chapters` AFTER INSERT ON chapters FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'content','pkg_event_content','pkg_message_content'); END$$
DROP TRIGGER IF EXISTS `pkg_content_lessons`$$
CREATE TRIGGER `pkg_content_lessons` AFTER INSERT ON lessons FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'content','pkg_event_content','pkg_message_content'); END$$
DROP TRIGGER IF EXISTS `pkg_content_topics`$$
CREATE TRIGGER `pkg_content_topics` AFTER INSERT ON topics FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'content','pkg_event_content','pkg_message_content'); END$$
DROP TRIGGER IF EXISTS `pkg_content_paragraphs`$$
CREATE TRIGGER `pkg_content_paragraphs` AFTER INSERT ON paragraphs FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'content','pkg_event_content','pkg_message_content'); END$$
DROP TRIGGER IF EXISTS `pkg_content_questions`$$
CREATE TRIGGER `pkg_content_questions` AFTER INSERT ON questions FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'content','pkg_event_content','pkg_message_content'); END$$
DELIMITER ;
