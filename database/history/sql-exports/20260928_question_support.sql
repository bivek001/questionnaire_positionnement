-- MySQL / MariaDB. Back up first; run ONCE before deploying replacement files.
-- Additive only: existing grades, timestamps, attempts and historical totals stay unchanged.
ALTER TABLE questions
    ADD COLUMN pre_question_content MEDIUMTEXT NULL,
    ADD COLUMN model_answer TEXT NULL,
    ADD COLUMN grading_rubric TEXT NULL,
    ADD COLUMN grading_keywords TEXT NULL;

ALTER TABLE responses
    ADD COLUMN suggested_points DECIMAL(10,2) NULL,
    ADD COLUMN suggestion_details MEDIUMTEXT NULL,
    ADD COLUMN suggested_at DATETIME NULL;
