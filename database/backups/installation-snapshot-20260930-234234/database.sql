-- Corrective installation safety snapshot. Restore into an empty database.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
CREATE TABLE `admins` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `admins` (`id`,`username`,`password`,`created_at`) VALUES ('1','admin','$2y$10$2b4I9Si.XFxBSiPBSta3hO3DdFfrHQW8zWAKr8kJV60PrffNWec.a','2026-09-18 15:16:07');
CREATE TABLE `attempts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `trainee_id` int(10) unsigned NOT NULL,
  `theme_id` int(10) unsigned DEFAULT NULL,
  `passation_type` enum('initial','final','sortie') NOT NULL DEFAULT 'initial',
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `corrected_at` timestamp NULL DEFAULT NULL,
  `result_sent_at` timestamp NULL DEFAULT NULL,
  `result_sent_to` varchar(255) DEFAULT NULL,
  `total_score` decimal(8,2) DEFAULT 0.00,
  `maximum_score` decimal(8,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `fk_attempts_trainee` (`trainee_id`),
  KEY `fk_attempts_theme` (`theme_id`),
  CONSTRAINT `fk_attempts_theme` FOREIGN KEY (`theme_id`) REFERENCES `themes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_attempts_trainee` FOREIGN KEY (`trainee_id`) REFERENCES `trainees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `chapters` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `theme_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_chapters_theme` (`theme_id`),
  CONSTRAINT `fk_chapters_theme` FOREIGN KEY (`theme_id`) REFERENCES `themes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `choices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `question_id` int(10) unsigned NOT NULL,
  `choice_text` text NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `display_order` int(10) unsigned DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_choices_question` (`question_id`),
  CONSTRAINT `fk_choices_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `content_pages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `page_key` varchar(50) NOT NULL,
  `title` varchar(150) NOT NULL,
  `content` longtext NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_key` (`page_key`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `content_pages` (`id`,`page_key`,`title`,`content`,`updated_at`) VALUES ('1','sommaire','Sommaire','Welcome to the questionnaire summary page. The trainer can edit this content from the administration area.','2026-09-22 14:54:03');
INSERT INTO `content_pages` (`id`,`page_key`,`title`,`content`,`updated_at`) VALUES ('2','introduction','Introduction','Welcome to the positioning questionnaire. Please read this introduction carefully before starting your questionnaires.','2026-09-22 14:54:03');
CREATE TABLE `corrections_stagiaires` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stagiaire_id` int(11) NOT NULL,
  `page_code` varchar(20) NOT NULL,
  `note` int(11) NOT NULL,
  `commentaire` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `corrections_stagiaires` (`id`,`stagiaire_id`,`page_code`,`note`,`commentaire`,`created_at`) VALUES ('1','1','TEST','3','OK','2026-06-22 11:29:33');
INSERT INTO `corrections_stagiaires` (`id`,`stagiaire_id`,`page_code`,`note`,`commentaire`,`created_at`) VALUES ('2','1','TEST','3','OK','2026-06-22 11:43:16');
INSERT INTO `corrections_stagiaires` (`id`,`stagiaire_id`,`page_code`,`note`,`commentaire`,`created_at`) VALUES ('3','1','TEST','3','OK','2026-06-22 11:43:18');
CREATE TABLE `evenement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stagiaire_id` int(11) NOT NULL,
  `date_passation` date DEFAULT NULL,
  `date_correction` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stagiaire_id` (`stagiaire_id`),
  CONSTRAINT `evenement_ibfk_1` FOREIGN KEY (`stagiaire_id`) REFERENCES `stagiaires` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `evenement` (`id`,`stagiaire_id`,`date_passation`,`date_correction`) VALUES ('1','3','2026-06-23',NULL);
INSERT INTO `evenement` (`id`,`stagiaire_id`,`date_passation`,`date_correction`) VALUES ('2','3','2026-06-23',NULL);
CREATE TABLE `lessons` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `chapter_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_lessons_chapter` (`chapter_id`),
  CONSTRAINT `fk_lessons_chapter` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `recipient_type` enum('admin','professor','trainee') NOT NULL,
  `recipient_id` int(10) unsigned NOT NULL,
  `type` varchar(60) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` varchar(255) NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `recipient_unread` (`recipient_type`,`recipient_id`,`read_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `paragraphs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `topic_id` int(10) unsigned NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `media_type` enum('image','audio','video') DEFAULT NULL,
  `media_path` varchar(255) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_paragraphs_topic` (`topic_id`),
  CONSTRAINT `fk_paragraphs_topic` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `password_reset_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_type` enum('trainee','admin','professor') NOT NULL,
  `account_id` int(10) unsigned NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_password_reset_token_hash` (`token_hash`),
  KEY `idx_password_reset_account` (`account_type`,`account_id`),
  KEY `idx_password_reset_expiry` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `password_reset_tokens` (`id`,`account_type`,`account_id`,`token_hash`,`expires_at`,`used_at`,`created_at`) VALUES ('1','trainee','8','b63eb9a4574a1c9b5f4a2b106add15cc689c6f0147608bcb76906efd7c32e291','2026-09-22 21:01:56','2026-09-22 20:33:29','2026-09-22 20:31:56');
INSERT INTO `password_reset_tokens` (`id`,`account_type`,`account_id`,`token_hash`,`expires_at`,`used_at`,`created_at`) VALUES ('2','admin','1','53ccc691d09ffb9ce631e1a1c6cc1e8e21454c3dd6ca0a45b7ffb844633cc2d6','2026-09-22 21:16:37','2026-09-22 20:47:10','2026-09-22 20:46:37');
INSERT INTO `password_reset_tokens` (`id`,`account_type`,`account_id`,`token_hash`,`expires_at`,`used_at`,`created_at`) VALUES ('3','admin','1','0bf1361b5e6637e0cdad48df1f661201f3622983de98f3f268b3b9cb17e082e8','2026-09-22 21:17:10','2026-09-22 20:47:14','2026-09-22 20:47:10');
INSERT INTO `password_reset_tokens` (`id`,`account_type`,`account_id`,`token_hash`,`expires_at`,`used_at`,`created_at`) VALUES ('4','admin','1','7b30d26f93e6ebad2c59315a526ef3a7f7330331819b56b4d5bdbd889521d222','2026-09-22 21:17:14','2026-09-22 20:48:43','2026-09-22 20:47:14');
INSERT INTO `password_reset_tokens` (`id`,`account_type`,`account_id`,`token_hash`,`expires_at`,`used_at`,`created_at`) VALUES ('5','admin','1','b580154a9a92de3a71506dbf7496c99e526fdf8a6d6773fa0e81ab9f7e8e5ea3','2026-09-22 21:18:43','2026-09-22 20:50:00','2026-09-22 20:48:43');
INSERT INTO `password_reset_tokens` (`id`,`account_type`,`account_id`,`token_hash`,`expires_at`,`used_at`,`created_at`) VALUES ('6','trainee','8','0e5e0939c6f9dc1fba08a626605fcddc189c5899baa6f610dde2a91a4ae7c051','2026-09-24 10:05:10','2026-09-25 11:19:36','2026-09-24 09:35:10');
INSERT INTO `password_reset_tokens` (`id`,`account_type`,`account_id`,`token_hash`,`expires_at`,`used_at`,`created_at`) VALUES ('7','admin','1','9679ec4f00389b36d156e2a8d0304918455a9db6ae6ae1e128dd0ff32ac7cdcf','2026-09-24 14:19:00',NULL,'2026-09-24 13:49:00');
INSERT INTO `password_reset_tokens` (`id`,`account_type`,`account_id`,`token_hash`,`expires_at`,`used_at`,`created_at`) VALUES ('8','professor','1','8192fa656e067e207cc10a3f0a45f04e6a7e0e87527c1223b36b88fbbd1cfbf3','2026-09-25 10:26:13','2026-09-25 09:57:47','2026-09-25 09:56:13');
INSERT INTO `password_reset_tokens` (`id`,`account_type`,`account_id`,`token_hash`,`expires_at`,`used_at`,`created_at`) VALUES ('9','trainee','8','ce7c6bbcd27941896fd14d5e239d051d9a1fe032035b296978dc62cbbf916a27','2026-09-25 11:49:36',NULL,'2026-09-25 11:19:36');
CREATE TABLE `positioning_levels` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `min_percentage` decimal(5,2) NOT NULL,
  `max_percentage` decimal(5,2) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `positioning_levels` (`id`,`name`,`min_percentage`,`max_percentage`,`display_order`,`created_at`) VALUES ('1','Beginner Level','0.00','39.99','1','2026-09-21 14:00:27');
INSERT INTO `positioning_levels` (`id`,`name`,`min_percentage`,`max_percentage`,`display_order`,`created_at`) VALUES ('2','Intermediate','40.00','69.99','2','2026-09-21 14:00:27');
INSERT INTO `positioning_levels` (`id`,`name`,`min_percentage`,`max_percentage`,`display_order`,`created_at`) VALUES ('3','Advanced','70.00','100.00','3','2026-09-21 14:00:27');
CREATE TABLE `professor_content_assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `professor_id` int(10) unsigned NOT NULL,
  `theme_id` int(10) unsigned NOT NULL,
  `chapter_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_professor_content_assignment` (`professor_id`,`theme_id`,`chapter_id`),
  KEY `fk_professor_assignment_theme` (`theme_id`),
  KEY `fk_professor_assignment_chapter` (`chapter_id`),
  CONSTRAINT `fk_professor_assignment_chapter` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_professor_assignment_professor` FOREIGN KEY (`professor_id`) REFERENCES `professors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_professor_assignment_theme` FOREIGN KEY (`theme_id`) REFERENCES `themes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `professor_trainee_assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `professor_id` int(10) unsigned NOT NULL,
  `trainee_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_professor_trainee` (`professor_id`,`trainee_id`),
  KEY `fk_professor_trainee_trainee` (`trainee_id`),
  CONSTRAINT `fk_professor_trainee_professor` FOREIGN KEY (`professor_id`) REFERENCES `professors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_professor_trainee_trainee` FOREIGN KEY (`trainee_id`) REFERENCES `trainees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `professors` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `professors` (`id`,`first_name`,`last_name`,`email`,`username`,`password`,`is_active`,`created_at`,`updated_at`) VALUES ('1','Test','Professor','professor@test.com','professor1','$2y$10$lljo9Wg2JztGTxA8iAK16ur0Uw1J5702TsxnymLHRyc0dvK5WhZFy','1','2026-09-24 14:39:41','2026-09-25 09:57:47');
CREATE TABLE `questionnaire` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_code` varchar(10) NOT NULL,
  `categorie` varchar(100) NOT NULL,
  `question` text NOT NULL,
  `type` varchar(20) DEFAULT 'texte',
  `ordre` int(11) DEFAULT 1,
  `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `questionnaire_assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `trainee_id` int(10) unsigned NOT NULL,
  `theme_id` int(10) unsigned NOT NULL,
  `passation_type` enum('initial','final','sortie') NOT NULL DEFAULT 'initial',
  `status` enum('pending','completed') NOT NULL DEFAULT 'pending',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `attempt_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_assignment_trainee` (`trainee_id`),
  KEY `fk_assignment_theme` (`theme_id`),
  KEY `fk_assignment_attempt` (`attempt_id`),
  CONSTRAINT `fk_assignment_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `attempts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_assignment_theme` FOREIGN KEY (`theme_id`) REFERENCES `themes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignment_trainee` FOREIGN KEY (`trainee_id`) REFERENCES `trainees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `questions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `theme_id` int(10) unsigned NOT NULL,
  `chapter_id` int(10) unsigned DEFAULT NULL,
  `lesson_id` int(10) unsigned DEFAULT NULL,
  `topic_id` int(10) unsigned DEFAULT NULL,
  `paragraph_id` int(10) unsigned DEFAULT NULL,
  `question_text` text NOT NULL,
  `media_type` enum('image','audio','video') DEFAULT NULL,
  `media_path` varchar(255) DEFAULT NULL,
  `question_type` enum('single_choice','multiple_choice','open') NOT NULL,
  `points` decimal(6,2) DEFAULT 1.00,
  `display_order` int(10) unsigned DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `pre_question_content` mediumtext DEFAULT NULL,
  `model_answer` text DEFAULT NULL,
  `grading_rubric` text DEFAULT NULL,
  `grading_keywords` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_questions_theme` (`theme_id`),
  KEY `fk_questions_chapter` (`chapter_id`),
  KEY `fk_questions_lesson` (`lesson_id`),
  KEY `fk_questions_topic` (`topic_id`),
  KEY `fk_questions_paragraph` (`paragraph_id`),
  CONSTRAINT `fk_questions_chapter` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_questions_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_questions_paragraph` FOREIGN KEY (`paragraph_id`) REFERENCES `paragraphs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_questions_theme` FOREIGN KEY (`theme_id`) REFERENCES `themes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_questions_topic` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `reponses_corrections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stagiaire_id` int(11) NOT NULL,
  `page_code` varchar(20) NOT NULL,
  `question_id` int(11) DEFAULT NULL,
  `reponse` text DEFAULT NULL,
  `note` int(11) DEFAULT 0,
  `commentaire` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `reponses_corrections` (`id`,`stagiaire_id`,`page_code`,`question_id`,`reponse`,`note`,`commentaire`,`created_at`) VALUES ('1','3','1a2',NULL,'latifa','2','À optimiser','2026-06-22 12:09:24');
CREATE TABLE `response_choices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `response_id` int(10) unsigned NOT NULL,
  `choice_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_response_choice` (`response_id`,`choice_id`),
  KEY `fk_response_choices_choice` (`choice_id`),
  CONSTRAINT `fk_response_choices_choice` FOREIGN KEY (`choice_id`) REFERENCES `choices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_response_choices_response` FOREIGN KEY (`response_id`) REFERENCES `responses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `responses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `attempt_id` int(10) unsigned NOT NULL,
  `question_id` int(10) unsigned NOT NULL,
  `text_answer` text DEFAULT NULL,
  `awarded_points` decimal(6,2) DEFAULT 0.00,
  `trainer_comment` text DEFAULT NULL,
  `graded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `suggested_points` decimal(10,2) DEFAULT NULL,
  `suggestion_details` mediumtext DEFAULT NULL,
  `suggested_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_responses_attempt` (`attempt_id`),
  KEY `fk_responses_question` (`question_id`),
  CONSTRAINT `fk_responses_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_responses_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `stagiaires` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `date_naissance` date NOT NULL,
  `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` varchar(50) NOT NULL DEFAULT 'stagiaire',
  `password_hash` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `stagiaires` (`id`,`nom`,`prenom`,`date_naissance`,`date_creation`,`role`,`password_hash`) VALUES ('3','Boughlal','Latifa','1976-06-01','2026-06-22 12:09:03','stagiaire','$2y$10$FGAmyoDMG9ZmU/4Nz4bdD.CN7SNNqXNRdgMK0Gu.5/J0pCc0wKzDW');
CREATE TABLE `themes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `display_order` int(10) unsigned DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `topics` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lesson_id` int(10) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_topics_lesson` (`lesson_id`),
  CONSTRAINT `fk_topics_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `trainees` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DELIMITER $$
CREATE TRIGGER `pkg_attempt_completed` AFTER UPDATE ON attempts FOR EACH ROW BEGIN IF OLD.completed_at IS NULL AND NEW.completed_at IS NOT NULL THEN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('trainee',NEW.trainee_id,'completion','pkg_event_completion','pkg_message_completion');INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'completion','pkg_event_completion','pkg_message_completion'); INSERT INTO notifications(recipient_type,recipient_id,type,title,message) SELECT DISTINCT 'professor',p.professor_id,'completion','pkg_event_completion','pkg_message_completion' FROM professor_content_assignments p WHERE p.theme_id=NEW.theme_id; END IF; END$$
CREATE TRIGGER `pkg_content_chapters` AFTER INSERT ON chapters FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'content','pkg_event_content','pkg_message_content'); END$$
CREATE TRIGGER `pkg_content_lessons` AFTER INSERT ON lessons FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'content','pkg_event_content','pkg_message_content'); END$$
CREATE TRIGGER `pkg_content_paragraphs` AFTER INSERT ON paragraphs FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'content','pkg_event_content','pkg_message_content'); END$$
CREATE TRIGGER `pkg_access_insert` AFTER INSERT ON professor_content_assignments FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('professor',NEW.professor_id,'access','pkg_event_access','pkg_message_access');INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'access','pkg_event_access','pkg_message_access'); END$$
CREATE TRIGGER `pkg_access_delete` AFTER DELETE ON professor_content_assignments FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('professor',OLD.professor_id,'access','pkg_event_access','pkg_message_access');INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'access','pkg_event_access','pkg_message_access'); END$$
CREATE TRIGGER `pkg_professor_updated` AFTER UPDATE ON professors FOR EACH ROW BEGIN IF NOT(OLD.first_name <=> NEW.first_name) OR NOT(OLD.last_name <=> NEW.last_name) OR NOT(OLD.email <=> NEW.email) OR NOT(OLD.password <=> NEW.password)  OR NOT(OLD.username <=> NEW.username) OR OLD.is_active<>NEW.is_active THEN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('professor',NEW.id,'account','pkg_event_account','pkg_message_account');INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'account','pkg_event_account','pkg_message_account'); END IF; END$$
CREATE TRIGGER `pkg_assignment_created` AFTER INSERT ON questionnaire_assignments FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('trainee',NEW.trainee_id,'assignment','pkg_event_assignment','pkg_message_assignment'); END$$
CREATE TRIGGER `pkg_content_questions` AFTER INSERT ON questions FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'content','pkg_event_content','pkg_message_content'); END$$
CREATE TRIGGER `pkg_response_graded` AFTER UPDATE ON responses FOR EACH ROW BEGIN IF NEW.graded_at IS NOT NULL AND (OLD.graded_at IS NULL OR NOT(OLD.awarded_points <=> NEW.awarded_points) OR NOT(OLD.trainer_comment <=> NEW.trainer_comment)) THEN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) SELECT 'trainee',a.trainee_id,'grading','pkg_event_grading','pkg_message_grading' FROM attempts a WHERE a.id=NEW.attempt_id; INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'grading','pkg_event_grading','pkg_message_grading'); END IF; END$$
CREATE TRIGGER `pkg_content_themes` AFTER INSERT ON themes FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'content','pkg_event_content','pkg_message_content'); END$$
CREATE TRIGGER `pkg_content_topics` AFTER INSERT ON topics FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'content','pkg_event_content','pkg_message_content'); END$$
CREATE TRIGGER `pkg_trainee_updated` AFTER UPDATE ON trainees FOR EACH ROW BEGIN IF NOT(OLD.first_name <=> NEW.first_name) OR NOT(OLD.last_name <=> NEW.last_name) OR NOT(OLD.email <=> NEW.email) OR NOT(OLD.password <=> NEW.password)  OR NOT(OLD.date_of_birth <=> NEW.date_of_birth) THEN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('trainee',NEW.id,'account','pkg_event_account','pkg_message_account');INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'account','pkg_event_account','pkg_message_account'); END IF; END$$
DELIMITER ;
SET FOREIGN_KEY_CHECKS=1;
