-- Apply once to an existing database after backup; never replaces content or scores.
CREATE TABLE IF NOT EXISTS positioning_question_metadata (
 question_id INT UNSIGNED NOT NULL,
 competency_code VARCHAR(24) NOT NULL,
 domain_number TINYINT UNSIGNED NOT NULL,
 diagnostic VARCHAR(16) NOT NULL,
 PRIMARY KEY(question_id, competency_code),
 FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
