-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 02, 2026 at 11:03 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `questionnaire_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `created_at`) VALUES
(1, 'admin', '$2y$10$2b4I9Si.XFxBSiPBSta3hO3DdFfrHQW8zWAKr8kJV60PrffNWec.a', '2026-09-18 13:16:07');

-- --------------------------------------------------------

--
-- Table structure for table `attempts`
--

CREATE TABLE `attempts` (
  `id` int(10) UNSIGNED NOT NULL,
  `trainee_id` int(10) UNSIGNED NOT NULL,
  `theme_id` int(10) UNSIGNED DEFAULT NULL,
  `passation_type` enum('initial','final','sortie') NOT NULL DEFAULT 'initial',
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `corrected_at` timestamp NULL DEFAULT NULL,
  `result_sent_at` timestamp NULL DEFAULT NULL,
  `result_sent_to` varchar(255) DEFAULT NULL,
  `total_score` decimal(8,2) DEFAULT 0.00,
  `maximum_score` decimal(8,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `attempts`
--
DELIMITER $$
CREATE TRIGGER `pkg_attempt_completed` AFTER UPDATE ON `attempts` FOR EACH ROW BEGIN IF OLD.completed_at IS NULL AND NEW.completed_at IS NOT NULL THEN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('trainee',NEW.trainee_id,'completion','pkg_event_completion','pkg_message_completion');INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'completion','pkg_event_completion','pkg_message_completion');INSERT INTO notifications(recipient_type,recipient_id,type,title,message,url)
 SELECT DISTINCT 'professor',p.professor_id,'completion','pkg_event_completion','pkg_message_completion',CONCAT('professor/attempt_details.php?id=',a.id)
 FROM attempts a JOIN professor_content_assignments p ON p.theme_id=a.theme_id
 JOIN professors pr ON pr.id=p.professor_id AND pr.is_active=1 WHERE a.id=NEW.id AND (p.chapter_id IS NULL OR EXISTS (SELECT 1 FROM responses nr JOIN questions nq ON nq.id=nr.question_id WHERE nr.attempt_id=NEW.id AND nq.theme_id=a.theme_id AND nq.chapter_id=p.chapter_id)); END IF; END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `chapters`
--

CREATE TABLE `chapters` (
  `id` int(10) UNSIGNED NOT NULL,
  `theme_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `chapters`
--
DELIMITER $$
CREATE TRIGGER `pkg_content_chapters` AFTER INSERT ON `chapters` FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'content','pkg_event_content','pkg_message_content'); END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `choices`
--

CREATE TABLE `choices` (
  `id` int(10) UNSIGNED NOT NULL,
  `question_id` int(10) UNSIGNED NOT NULL,
  `choice_text` text NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `display_order` int(10) UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `content_pages`
--

CREATE TABLE `content_pages` (
  `id` int(10) UNSIGNED NOT NULL,
  `page_key` varchar(50) NOT NULL,
  `title` varchar(150) NOT NULL,
  `content` longtext NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `content_pages`
--

INSERT INTO `content_pages` (`id`, `page_key`, `title`, `content`, `updated_at`) VALUES
(1, 'sommaire', 'Sommaire', 'Welcome to the questionnaire summary page. The trainer can edit this content from the administration area.', '2026-09-22 12:54:03'),
(2, 'introduction', 'Introduction', 'Welcome to the positioning questionnaire. Please read this introduction carefully before starting your questionnaires.', '2026-09-22 12:54:03');

-- --------------------------------------------------------

--
-- Table structure for table `corrections_stagiaires`
--

CREATE TABLE `corrections_stagiaires` (
  `id` int(11) NOT NULL,
  `stagiaire_id` int(11) NOT NULL,
  `page_code` varchar(20) NOT NULL,
  `note` int(11) NOT NULL,
  `commentaire` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `corrections_stagiaires`
--

INSERT INTO `corrections_stagiaires` (`id`, `stagiaire_id`, `page_code`, `note`, `commentaire`, `created_at`) VALUES
(1, 1, 'TEST', 3, 'OK', '2026-06-22 09:29:33'),
(2, 1, 'TEST', 3, 'OK', '2026-06-22 09:43:16'),
(3, 1, 'TEST', 3, 'OK', '2026-06-22 09:43:18');

-- --------------------------------------------------------

--
-- Table structure for table `evenement`
--

CREATE TABLE `evenement` (
  `id` int(11) NOT NULL,
  `stagiaire_id` int(11) NOT NULL,
  `date_passation` date DEFAULT NULL,
  `date_correction` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `evenement`
--

INSERT INTO `evenement` (`id`, `stagiaire_id`, `date_passation`, `date_correction`) VALUES
(1, 3, '2026-06-23', NULL),
(2, 3, '2026-06-23', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `lessons`
--

CREATE TABLE `lessons` (
  `id` int(10) UNSIGNED NOT NULL,
  `chapter_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `lessons`
--
DELIMITER $$
CREATE TRIGGER `pkg_content_lessons` AFTER INSERT ON `lessons` FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'content','pkg_event_content','pkg_message_content'); END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `recipient_type` enum('admin','professor','trainee') NOT NULL,
  `recipient_id` int(10) UNSIGNED NOT NULL,
  `type` varchar(60) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` varchar(255) NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `paragraphs`
--

CREATE TABLE `paragraphs` (
  `id` int(10) UNSIGNED NOT NULL,
  `topic_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `media_type` enum('image','audio','video') DEFAULT NULL,
  `media_path` varchar(255) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `paragraphs`
--
DELIMITER $$
CREATE TRIGGER `pkg_content_paragraphs` AFTER INSERT ON `paragraphs` FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'content','pkg_event_content','pkg_message_content'); END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(10) UNSIGNED NOT NULL,
  `account_type` enum('trainee','admin','professor') NOT NULL,
  `account_id` int(10) UNSIGNED NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_reset_tokens`
--

INSERT INTO `password_reset_tokens` (`id`, `account_type`, `account_id`, `token_hash`, `expires_at`, `used_at`, `created_at`) VALUES
(1, 'trainee', 8, 'b63eb9a4574a1c9b5f4a2b106add15cc689c6f0147608bcb76906efd7c32e291', '2026-09-22 21:01:56', '2026-09-22 20:33:29', '2026-09-22 18:31:56'),
(2, 'admin', 1, '53ccc691d09ffb9ce631e1a1c6cc1e8e21454c3dd6ca0a45b7ffb844633cc2d6', '2026-09-22 21:16:37', '2026-09-22 20:47:10', '2026-09-22 18:46:37'),
(3, 'admin', 1, '0bf1361b5e6637e0cdad48df1f661201f3622983de98f3f268b3b9cb17e082e8', '2026-09-22 21:17:10', '2026-09-22 20:47:14', '2026-09-22 18:47:10'),
(4, 'admin', 1, '7b30d26f93e6ebad2c59315a526ef3a7f7330331819b56b4d5bdbd889521d222', '2026-09-22 21:17:14', '2026-09-22 20:48:43', '2026-09-22 18:47:14'),
(5, 'admin', 1, 'b580154a9a92de3a71506dbf7496c99e526fdf8a6d6773fa0e81ab9f7e8e5ea3', '2026-09-22 21:18:43', '2026-09-22 20:50:00', '2026-09-22 18:48:43'),
(6, 'trainee', 8, '0e5e0939c6f9dc1fba08a626605fcddc189c5899baa6f610dde2a91a4ae7c051', '2026-09-24 10:05:10', '2026-09-25 11:19:36', '2026-09-24 07:35:10'),
(7, 'admin', 1, '9679ec4f00389b36d156e2a8d0304918455a9db6ae6ae1e128dd0ff32ac7cdcf', '2026-09-24 14:19:00', '2026-10-01 01:36:27', '2026-09-24 11:49:00'),
(8, 'professor', 1, '8192fa656e067e207cc10a3f0a45f04e6a7e0e87527c1223b36b88fbbd1cfbf3', '2026-09-25 10:26:13', '2026-09-25 09:57:47', '2026-09-25 07:56:13'),
(9, 'trainee', 8, 'ce7c6bbcd27941896fd14d5e239d051d9a1fe032035b296978dc62cbbf916a27', '2026-09-25 11:49:36', NULL, '2026-09-25 09:19:36'),
(10, 'admin', 1, '2e814eacd19f4dfd75abe952d8c01fa5c2d30def234177f1e8d47c3627c575c0', '2026-10-01 02:06:27', NULL, '2026-09-30 23:36:27'),
(11, 'trainee', 11, '82648b8f34cc7b63777608b450e82d4e83bd7a90f97d4fa0f10dcf72135c0715', '2026-10-01 02:11:49', NULL, '2026-09-30 23:41:49');

-- --------------------------------------------------------

--
-- Table structure for table `positioning_levels`
--

CREATE TABLE `positioning_levels` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `min_percentage` decimal(5,2) NOT NULL,
  `max_percentage` decimal(5,2) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `positioning_levels`
--

INSERT INTO `positioning_levels` (`id`, `name`, `min_percentage`, `max_percentage`, `display_order`, `created_at`) VALUES
(1, 'Beginner Level', 0.00, 39.99, 1, '2026-09-21 12:00:27'),
(2, 'Intermediate', 40.00, 69.99, 2, '2026-09-21 12:00:27'),
(3, 'Advanced', 70.00, 100.00, 3, '2026-09-21 12:00:27');

-- --------------------------------------------------------

--
-- Table structure for table `professors`
--

CREATE TABLE `professors` (
  `id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `professors`
--

INSERT INTO `professors` (`id`, `first_name`, `last_name`, `email`, `username`, `password`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Test', 'Professor', 'professor@test.com', 'professor1', '$2y$10$lljo9Wg2JztGTxA8iAK16ur0Uw1J5702TsxnymLHRyc0dvK5WhZFy', 1, '2026-09-24 12:39:41', '2026-09-25 07:57:47');

--
-- Triggers `professors`
--
DELIMITER $$
CREATE TRIGGER `pkg_professor_updated` AFTER UPDATE ON `professors` FOR EACH ROW BEGIN IF NOT(OLD.first_name <=> NEW.first_name) OR NOT(OLD.last_name <=> NEW.last_name) OR NOT(OLD.email <=> NEW.email) OR NOT(OLD.password <=> NEW.password)  OR NOT(OLD.username <=> NEW.username) OR NOT(OLD.is_active <=> NEW.is_active) THEN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('professor',NEW.id,'account','pkg_event_account','pkg_message_account');INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'account','pkg_event_account','pkg_message_account'); END IF; END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `professor_content_assignments`
--

CREATE TABLE `professor_content_assignments` (
  `id` int(10) UNSIGNED NOT NULL,
  `professor_id` int(10) UNSIGNED NOT NULL,
  `theme_id` int(10) UNSIGNED NOT NULL,
  `chapter_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `professor_content_assignments`
--
DELIMITER $$
CREATE TRIGGER `pkg_access_delete` AFTER DELETE ON `professor_content_assignments` FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('professor',OLD.professor_id,'access','pkg_event_access','pkg_message_access');INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'access','pkg_event_access','pkg_message_access'); END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `pkg_access_insert` AFTER INSERT ON `professor_content_assignments` FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('professor',NEW.professor_id,'access','pkg_event_access','pkg_message_access');INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'access','pkg_event_access','pkg_message_access'); END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `pkg_access_update` AFTER UPDATE ON `professor_content_assignments` FOR EACH ROW BEGIN IF NOT(OLD.professor_id <=> NEW.professor_id) OR NOT(OLD.theme_id <=> NEW.theme_id) OR NOT(OLD.chapter_id <=> NEW.chapter_id) THEN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('professor',OLD.professor_id,'access','pkg_event_access','pkg_message_access');INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('professor',NEW.professor_id,'access','pkg_event_access','pkg_message_access');INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'access','pkg_event_access','pkg_message_access'); END IF; END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `professor_trainee_assignments`
--

CREATE TABLE `professor_trainee_assignments` (
  `id` int(10) UNSIGNED NOT NULL,
  `professor_id` int(10) UNSIGNED NOT NULL,
  `trainee_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `questionnaire`
--

CREATE TABLE `questionnaire` (
  `id` int(11) NOT NULL,
  `page_code` varchar(10) NOT NULL,
  `categorie` varchar(100) NOT NULL,
  `question` text NOT NULL,
  `type` varchar(20) DEFAULT 'texte',
  `ordre` int(11) DEFAULT 1,
  `date_creation` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `questionnaire_assignments`
--

CREATE TABLE `questionnaire_assignments` (
  `id` int(10) UNSIGNED NOT NULL,
  `trainee_id` int(10) UNSIGNED NOT NULL,
  `theme_id` int(10) UNSIGNED NOT NULL,
  `passation_type` enum('initial','final','sortie') NOT NULL DEFAULT 'initial',
  `status` enum('pending','completed') NOT NULL DEFAULT 'pending',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `attempt_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `questionnaire_assignments`
--
DELIMITER $$
CREATE TRIGGER `pkg_assignment_created` AFTER INSERT ON `questionnaire_assignments` FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('trainee',NEW.trainee_id,'assignment','pkg_event_assignment','pkg_message_assignment');INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'assignment','pkg_event_assignment','pkg_message_assignment');
 INSERT INTO notifications(recipient_type,recipient_id,type,title,message) SELECT DISTINCT 'professor',p.professor_id,'assignment','pkg_event_assignment','pkg_message_assignment' FROM professor_content_assignments p JOIN professors pr ON pr.id=p.professor_id AND pr.is_active=1 WHERE p.theme_id=NEW.theme_id AND p.chapter_id IS NULL; END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `id` int(10) UNSIGNED NOT NULL,
  `theme_id` int(10) UNSIGNED NOT NULL,
  `chapter_id` int(10) UNSIGNED DEFAULT NULL,
  `lesson_id` int(10) UNSIGNED DEFAULT NULL,
  `topic_id` int(10) UNSIGNED DEFAULT NULL,
  `paragraph_id` int(10) UNSIGNED DEFAULT NULL,
  `question_text` text NOT NULL,
  `media_type` enum('image','audio','video') DEFAULT NULL,
  `media_path` varchar(255) DEFAULT NULL,
  `question_type` enum('single_choice','multiple_choice','open') NOT NULL,
  `points` decimal(6,2) DEFAULT 1.00,
  `display_order` int(10) UNSIGNED DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `pre_question_content` mediumtext DEFAULT NULL,
  `model_answer` text DEFAULT NULL,
  `grading_rubric` text DEFAULT NULL,
  `grading_keywords` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `questions`
--
DELIMITER $$
CREATE TRIGGER `pkg_content_questions` AFTER INSERT ON `questions` FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'content','pkg_event_content','pkg_message_content'); END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `reponses_corrections`
--

CREATE TABLE `reponses_corrections` (
  `id` int(11) NOT NULL,
  `stagiaire_id` int(11) NOT NULL,
  `page_code` varchar(20) NOT NULL,
  `question_id` int(11) DEFAULT NULL,
  `reponse` text DEFAULT NULL,
  `note` int(11) DEFAULT 0,
  `commentaire` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reponses_corrections`
--

INSERT INTO `reponses_corrections` (`id`, `stagiaire_id`, `page_code`, `question_id`, `reponse`, `note`, `commentaire`, `created_at`) VALUES
(1, 3, '1a2', NULL, 'latifa', 2, 'À optimiser', '2026-06-22 10:09:24');

-- --------------------------------------------------------

--
-- Table structure for table `responses`
--

CREATE TABLE `responses` (
  `id` int(10) UNSIGNED NOT NULL,
  `attempt_id` int(10) UNSIGNED NOT NULL,
  `question_id` int(10) UNSIGNED NOT NULL,
  `text_answer` text DEFAULT NULL,
  `awarded_points` decimal(6,2) DEFAULT 0.00,
  `trainer_comment` text DEFAULT NULL,
  `graded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `suggested_points` decimal(10,2) DEFAULT NULL,
  `suggestion_details` mediumtext DEFAULT NULL,
  `suggested_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `responses`
--
DELIMITER $$
CREATE TRIGGER `pkg_response_graded` AFTER UPDATE ON `responses` FOR EACH ROW BEGIN IF NEW.graded_at IS NOT NULL AND (OLD.graded_at IS NULL OR NOT(OLD.awarded_points <=> NEW.awarded_points) OR NOT(OLD.trainer_comment <=> NEW.trainer_comment)) THEN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) SELECT 'trainee',a.trainee_id,'grading','pkg_event_grading','pkg_message_grading' FROM attempts a WHERE a.id=NEW.attempt_id; INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'grading','pkg_event_grading','pkg_message_grading');INSERT INTO notifications(recipient_type,recipient_id,type,title,message,url)
 SELECT DISTINCT 'professor',p.professor_id,'grading','pkg_event_grading','pkg_message_grading',CONCAT('professor/attempt_details.php?id=',a.id)
 FROM attempts a JOIN professor_content_assignments p ON p.theme_id=a.theme_id
 JOIN professors pr ON pr.id=p.professor_id AND pr.is_active=1 WHERE a.id=NEW.attempt_id AND EXISTS (SELECT 1 FROM questions nq WHERE nq.id=NEW.question_id AND nq.theme_id=a.theme_id AND (p.chapter_id IS NULL OR p.chapter_id=nq.chapter_id)); END IF; END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `response_choices`
--

CREATE TABLE `response_choices` (
  `id` int(10) UNSIGNED NOT NULL,
  `response_id` int(10) UNSIGNED NOT NULL,
  `choice_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stagiaires`
--

CREATE TABLE `stagiaires` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `date_naissance` date NOT NULL,
  `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` varchar(50) NOT NULL DEFAULT 'stagiaire',
  `password_hash` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stagiaires`
--

INSERT INTO `stagiaires` (`id`, `nom`, `prenom`, `date_naissance`, `date_creation`, `role`, `password_hash`) VALUES
(3, 'Boughlal', 'Latifa', '1976-06-01', '2026-06-22 10:09:03', 'stagiaire', '$2y$10$FGAmyoDMG9ZmU/4Nz4bdD.CN7SNNqXNRdgMK0Gu.5/J0pCc0wKzDW');

-- --------------------------------------------------------

--
-- Table structure for table `themes`
--

CREATE TABLE `themes` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `display_order` int(10) UNSIGNED DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `themes`
--
DELIMITER $$
CREATE TRIGGER `pkg_content_themes` AFTER INSERT ON `themes` FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'content','pkg_event_content','pkg_message_content'); END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `topics`
--

CREATE TABLE `topics` (
  `id` int(10) UNSIGNED NOT NULL,
  `lesson_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `topics`
--
DELIMITER $$
CREATE TRIGGER `pkg_content_topics` AFTER INSERT ON `topics` FOR EACH ROW BEGIN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'content','pkg_event_content','pkg_message_content'); END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `trainees`
--

CREATE TABLE `trainees` (
  `id` int(10) UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `trainees`
--

INSERT INTO `trainees` (`id`, `first_name`, `last_name`, `email`, `password`, `date_of_birth`, `created_at`) VALUES
(11, 'Bivek', 'Chaudhary', 'bivek001.chaudhary@gmail.com', '$2y$10$lKvbclaHEC7Ig/IlRX5irOBSQa6U7ooHP.zl484JD/2Nh4LPmPNDy', '2001-12-13', '2026-09-30 23:41:02');

--
-- Triggers `trainees`
--
DELIMITER $$
CREATE TRIGGER `pkg_trainee_updated` AFTER UPDATE ON `trainees` FOR EACH ROW BEGIN IF NOT(OLD.first_name <=> NEW.first_name) OR NOT(OLD.last_name <=> NEW.last_name) OR NOT(OLD.email <=> NEW.email) OR NOT(OLD.password <=> NEW.password)  OR NOT(OLD.date_of_birth <=> NEW.date_of_birth) THEN INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('trainee',NEW.id,'account','pkg_event_account','pkg_message_account');INSERT INTO notifications(recipient_type,recipient_id,type,title,message) VALUES('admin',0,'account','pkg_event_account','pkg_message_account'); END IF; END
$$
DELIMITER ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `attempts`
--
ALTER TABLE `attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_attempts_trainee` (`trainee_id`),
  ADD KEY `fk_attempts_theme` (`theme_id`),
  ADD KEY `pkg_theme_trainee` (`theme_id`,`trainee_id`);

--
-- Indexes for table `chapters`
--
ALTER TABLE `chapters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_chapters_theme` (`theme_id`);

--
-- Indexes for table `choices`
--
ALTER TABLE `choices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_choices_question` (`question_id`);

--
-- Indexes for table `content_pages`
--
ALTER TABLE `content_pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `page_key` (`page_key`);

--
-- Indexes for table `corrections_stagiaires`
--
ALTER TABLE `corrections_stagiaires`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `evenement`
--
ALTER TABLE `evenement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stagiaire_id` (`stagiaire_id`);

--
-- Indexes for table `lessons`
--
ALTER TABLE `lessons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_lessons_chapter` (`chapter_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recipient_unread` (`recipient_type`,`recipient_id`,`read_at`,`id`);

--
-- Indexes for table `paragraphs`
--
ALTER TABLE `paragraphs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_paragraphs_topic` (`topic_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_password_reset_token_hash` (`token_hash`),
  ADD KEY `idx_password_reset_account` (`account_type`,`account_id`),
  ADD KEY `idx_password_reset_expiry` (`expires_at`);

--
-- Indexes for table `positioning_levels`
--
ALTER TABLE `positioning_levels`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `professors`
--
ALTER TABLE `professors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `professor_content_assignments`
--
ALTER TABLE `professor_content_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_professor_content_assignment` (`professor_id`,`theme_id`,`chapter_id`),
  ADD KEY `fk_professor_assignment_theme` (`theme_id`),
  ADD KEY `fk_professor_assignment_chapter` (`chapter_id`),
  ADD KEY `pkg_course_scope` (`professor_id`,`theme_id`,`chapter_id`);

--
-- Indexes for table `professor_trainee_assignments`
--
ALTER TABLE `professor_trainee_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_professor_trainee` (`professor_id`,`trainee_id`),
  ADD KEY `fk_professor_trainee_trainee` (`trainee_id`);

--
-- Indexes for table `questionnaire`
--
ALTER TABLE `questionnaire`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `questionnaire_assignments`
--
ALTER TABLE `questionnaire_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_assignment_trainee` (`trainee_id`),
  ADD KEY `fk_assignment_theme` (`theme_id`),
  ADD KEY `fk_assignment_attempt` (`attempt_id`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_questions_theme` (`theme_id`),
  ADD KEY `fk_questions_chapter` (`chapter_id`),
  ADD KEY `fk_questions_lesson` (`lesson_id`),
  ADD KEY `fk_questions_topic` (`topic_id`),
  ADD KEY `fk_questions_paragraph` (`paragraph_id`);

--
-- Indexes for table `reponses_corrections`
--
ALTER TABLE `reponses_corrections`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `responses`
--
ALTER TABLE `responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_responses_attempt` (`attempt_id`),
  ADD KEY `fk_responses_question` (`question_id`),
  ADD KEY `pkg_attempt_question` (`attempt_id`,`question_id`);

--
-- Indexes for table `response_choices`
--
ALTER TABLE `response_choices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_response_choice` (`response_id`,`choice_id`),
  ADD KEY `fk_response_choices_choice` (`choice_id`);

--
-- Indexes for table `stagiaires`
--
ALTER TABLE `stagiaires`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `themes`
--
ALTER TABLE `themes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `topics`
--
ALTER TABLE `topics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_topics_lesson` (`lesson_id`);

--
-- Indexes for table `trainees`
--
ALTER TABLE `trainees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attempts`
--
ALTER TABLE `attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `chapters`
--
ALTER TABLE `chapters`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `choices`
--
ALTER TABLE `choices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `content_pages`
--
ALTER TABLE `content_pages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `corrections_stagiaires`
--
ALTER TABLE `corrections_stagiaires`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `evenement`
--
ALTER TABLE `evenement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `lessons`
--
ALTER TABLE `lessons`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `paragraphs`
--
ALTER TABLE `paragraphs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `positioning_levels`
--
ALTER TABLE `positioning_levels`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `professors`
--
ALTER TABLE `professors`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `professor_content_assignments`
--
ALTER TABLE `professor_content_assignments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `professor_trainee_assignments`
--
ALTER TABLE `professor_trainee_assignments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `questionnaire`
--
ALTER TABLE `questionnaire`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `questionnaire_assignments`
--
ALTER TABLE `questionnaire_assignments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `reponses_corrections`
--
ALTER TABLE `reponses_corrections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `responses`
--
ALTER TABLE `responses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `response_choices`
--
ALTER TABLE `response_choices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `stagiaires`
--
ALTER TABLE `stagiaires`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `themes`
--
ALTER TABLE `themes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `topics`
--
ALTER TABLE `topics`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `trainees`
--
ALTER TABLE `trainees`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attempts`
--
ALTER TABLE `attempts`
  ADD CONSTRAINT `fk_attempts_theme` FOREIGN KEY (`theme_id`) REFERENCES `themes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_attempts_trainee` FOREIGN KEY (`trainee_id`) REFERENCES `trainees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chapters`
--
ALTER TABLE `chapters`
  ADD CONSTRAINT `fk_chapters_theme` FOREIGN KEY (`theme_id`) REFERENCES `themes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `choices`
--
ALTER TABLE `choices`
  ADD CONSTRAINT `fk_choices_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `evenement`
--
ALTER TABLE `evenement`
  ADD CONSTRAINT `evenement_ibfk_1` FOREIGN KEY (`stagiaire_id`) REFERENCES `stagiaires` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lessons`
--
ALTER TABLE `lessons`
  ADD CONSTRAINT `fk_lessons_chapter` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `paragraphs`
--
ALTER TABLE `paragraphs`
  ADD CONSTRAINT `fk_paragraphs_topic` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `professor_content_assignments`
--
ALTER TABLE `professor_content_assignments`
  ADD CONSTRAINT `fk_professor_assignment_chapter` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_professor_assignment_professor` FOREIGN KEY (`professor_id`) REFERENCES `professors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_professor_assignment_theme` FOREIGN KEY (`theme_id`) REFERENCES `themes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `professor_trainee_assignments`
--
ALTER TABLE `professor_trainee_assignments`
  ADD CONSTRAINT `fk_professor_trainee_professor` FOREIGN KEY (`professor_id`) REFERENCES `professors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_professor_trainee_trainee` FOREIGN KEY (`trainee_id`) REFERENCES `trainees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `questionnaire_assignments`
--
ALTER TABLE `questionnaire_assignments`
  ADD CONSTRAINT `fk_assignment_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `attempts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_assignment_theme` FOREIGN KEY (`theme_id`) REFERENCES `themes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_assignment_trainee` FOREIGN KEY (`trainee_id`) REFERENCES `trainees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `fk_questions_chapter` FOREIGN KEY (`chapter_id`) REFERENCES `chapters` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_questions_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_questions_paragraph` FOREIGN KEY (`paragraph_id`) REFERENCES `paragraphs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_questions_theme` FOREIGN KEY (`theme_id`) REFERENCES `themes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_questions_topic` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `responses`
--
ALTER TABLE `responses`
  ADD CONSTRAINT `fk_responses_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_responses_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `response_choices`
--
ALTER TABLE `response_choices`
  ADD CONSTRAINT `fk_response_choices_choice` FOREIGN KEY (`choice_id`) REFERENCES `choices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_response_choices_response` FOREIGN KEY (`response_id`) REFERENCES `responses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `topics`
--
ALTER TABLE `topics`
  ADD CONSTRAINT `fk_topics_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
