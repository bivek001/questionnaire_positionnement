/*
 * ============================================================
 * SurveyJS Universal Questionnaire Support
 * ============================================================
 *
 * This migration extends the existing application without
 * removing or replacing the legacy question system.
 *
 * Existing tables such as:
 *   questions
 *   choices
 *   attempts
 *   responses
 *   response_choices
 *
 * remain intact.
 */


/*
 * ------------------------------------------------------------
 * 1. SURVEY DEFINITIONS
 * ------------------------------------------------------------
 *
 * Stores the complete JSON produced by Survey Creator.
 *
 * One theme may have one or more SurveyJS questionnaire
 * definitions in the future.
 */

CREATE TABLE IF NOT EXISTS `survey_definitions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,

    `theme_id` INT UNSIGNED NOT NULL,

    `title` VARCHAR(255) NOT NULL,

    `description` TEXT DEFAULT NULL,

    `survey_json` LONGTEXT NOT NULL,

    `status` ENUM(
        'draft',
        'published',
        'archived'
    ) NOT NULL DEFAULT 'draft',

    `created_by_type` ENUM(
        'admin',
        'professor'
    ) NOT NULL,

    `created_by_id` INT UNSIGNED NOT NULL,

    `created_at` TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    `updated_at` TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    KEY `idx_survey_definitions_theme`
        (`theme_id`),

    KEY `idx_survey_definitions_status`
        (`status`),

    CONSTRAINT `fk_survey_definitions_theme`
        FOREIGN KEY (`theme_id`)
        REFERENCES `themes` (`id`)
        ON DELETE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


/*
 * ------------------------------------------------------------
 * 2. SURVEY ATTEMPTS
 * ------------------------------------------------------------
 *
 * Connects an existing application attempt to the exact
 * SurveyJS questionnaire definition used by the trainee.
 *
 * response_json stores the complete SurveyJS response data.
 *
 * scoring_json can store detailed scoring information,
 * including competency totals.
 */

CREATE TABLE IF NOT EXISTS `survey_attempt_data` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,

    `attempt_id` INT UNSIGNED NOT NULL,

    `survey_definition_id` INT UNSIGNED NOT NULL,

    `response_json` LONGTEXT DEFAULT NULL,

    `scoring_json` LONGTEXT DEFAULT NULL,

    `created_at` TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    `updated_at` TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    UNIQUE KEY `uq_survey_attempt`
        (`attempt_id`),

    KEY `idx_survey_attempt_definition`
        (`survey_definition_id`),

    CONSTRAINT `fk_survey_attempt_attempt`
        FOREIGN KEY (`attempt_id`)
        REFERENCES `attempts` (`id`)
        ON DELETE CASCADE,

    CONSTRAINT `fk_survey_attempt_definition`
        FOREIGN KEY (`survey_definition_id`)
        REFERENCES `survey_definitions` (`id`)
        ON DELETE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


/*
 * ------------------------------------------------------------
 * 3. INDIVIDUAL SURVEY QUESTION RESULTS
 * ------------------------------------------------------------
 *
 * Stores the result of each SurveyJS question.
 *
 * This is useful for:
 *   - reports
 *   - professor corrections
 *   - competency analysis
 *   - statistics
 *   - manual grading
 *
 * answer_json allows any SurveyJS answer structure:
 * string, array, object, ranking, matrix, etc.
 */

CREATE TABLE IF NOT EXISTS `survey_question_results` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,

    `attempt_id` INT UNSIGNED NOT NULL,

    `survey_definition_id` INT UNSIGNED NOT NULL,

    `question_name` VARCHAR(191) NOT NULL,

    `question_code` VARCHAR(100) DEFAULT NULL,

    `competency` VARCHAR(100) DEFAULT NULL,

    `answer_json` LONGTEXT DEFAULT NULL,

    `automatic_score` DECIMAL(10,2)
        NOT NULL DEFAULT 0.00,

    `manual_score` DECIMAL(10,2)
        DEFAULT NULL,

    `final_score` DECIMAL(10,2)
        NOT NULL DEFAULT 0.00,

    `maximum_score` DECIMAL(10,2)
        NOT NULL DEFAULT 0.00,

    `trainer_comment` TEXT DEFAULT NULL,

    `requires_manual_grading` TINYINT(1)
        NOT NULL DEFAULT 0,

    `graded_at` TIMESTAMP NULL DEFAULT NULL,

    `created_at` TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    `updated_at` TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    UNIQUE KEY `uq_survey_question_attempt`
        (`attempt_id`, `question_name`),

    KEY `idx_survey_question_definition`
        (`survey_definition_id`),

    KEY `idx_survey_question_competency`
        (`competency`),

    CONSTRAINT `fk_survey_question_attempt`
        FOREIGN KEY (`attempt_id`)
        REFERENCES `attempts` (`id`)
        ON DELETE CASCADE,

    CONSTRAINT `fk_survey_question_definition`
        FOREIGN KEY (`survey_definition_id`)
        REFERENCES `survey_definitions` (`id`)
        ON DELETE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


/*
 * ------------------------------------------------------------
 * 4. COMPETENCY RESULTS
 * ------------------------------------------------------------
 *
 * Stores the calculated result for each competency in an
 * attempt.
 */

CREATE TABLE IF NOT EXISTS `survey_competency_results` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,

    `attempt_id` INT UNSIGNED NOT NULL,

    `competency` VARCHAR(100) NOT NULL,

    `score` DECIMAL(10,2)
        NOT NULL DEFAULT 0.00,

    `maximum_score` DECIMAL(10,2)
        NOT NULL DEFAULT 0.00,

    `percentage` DECIMAL(6,2)
        NOT NULL DEFAULT 0.00,

    `level` VARCHAR(100) DEFAULT NULL,

    `created_at` TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    `updated_at` TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    UNIQUE KEY `uq_attempt_competency`
        (`attempt_id`, `competency`),

    KEY `idx_competency`
        (`competency`),

    CONSTRAINT `fk_survey_competency_attempt`
        FOREIGN KEY (`attempt_id`)
        REFERENCES `attempts` (`id`)
        ON DELETE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;