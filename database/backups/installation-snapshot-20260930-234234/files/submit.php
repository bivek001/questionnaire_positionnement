<?php
require_once __DIR__ . "/includes/question_support.php";

session_start();

require_once 'config/database.php';
require_once __DIR__ . '/includes/trainee_language.php';


// ======================================================
// REQUIRE TRAINEE LOGIN
// ======================================================

if (!isset($_SESSION['trainee_id'])) {

    header('Location: trainee_login.php');
    exit;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    die(t('h150_invalid_request'));
}


$traineeId =
    (int)$_SESSION['trainee_id'];


// ======================================================
// GET SUBMITTED DATA
// ======================================================

$questionnaireMode =
    $_POST['questionnaire_mode'] ?? '';

$assignmentId = filter_input(
    INPUT_POST,
    'assignment_id',
    FILTER_VALIDATE_INT
);

$submittedThemeId = filter_input(
    INPUT_POST,
    'theme_id',
    FILTER_VALIDATE_INT
);

$answers =
    $_POST['answers'] ?? [];


if (!is_array($answers)) {
    $answers = [];
}


// ======================================================
// VALIDATE MODE
// ======================================================

if (
    !in_array(
        $questionnaireMode,
        ['assigned', 'available'],
        true
    )
) {

    die(t('h150_invalid_questionnaire_mode'));
}


if (
    $questionnaireMode === 'assigned' &&
    !$assignmentId
) {

    die(t('h150_invalid_questionnaire_assignment'));
}


if (
    $questionnaireMode === 'available' &&
    !$submittedThemeId
) {

    die(t('h150_invalid_questionnaire'));
}


try {

    // ==================================================
    // START TRANSACTION
    // ==================================================

    $pdo->beginTransaction();


    $themeId = null;
    $passationType = null;


    // ==================================================
    // ASSIGNED QUESTIONNAIRE
    // ==================================================

    if ($questionnaireMode === 'assigned') {

        /*
         * Lock the assignment so the same pending
         * assignment cannot be submitted twice
         * simultaneously.
         */

        $stmt = $pdo->prepare(
            "SELECT
                qa.id,
                qa.trainee_id,
                qa.theme_id,
                qa.passation_type,
                qa.status,

                themes.name AS theme_name,
                themes.is_active

             FROM questionnaire_assignments qa

             INNER JOIN themes
                ON qa.theme_id = themes.id

             WHERE qa.id = ?
               AND qa.trainee_id = ?

             LIMIT 1

             FOR UPDATE"
        );


        $stmt->execute([
            $assignmentId,
            $traineeId
        ]);


        $assignment =
            $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$assignment) {

            throw new Exception(
                t('h150_questionnaire_assignment_not_found_or_access_denied')
            );
        }


        if (
            $assignment['status']
            !== 'pending'
        ) {

            throw new Exception(
                t('h150_this_questionnaire_has_already_been_submitted')
            );
        }


        if (
            (int)$assignment['is_active']
            !== 1
        ) {

            throw new Exception(
                t('h150_this_questionnaire_is_currently_unavailable')
            );
        }


        /*
         * Never trust a submitted theme or
         * passation type for assigned questionnaires.
         *
         * Both values come directly from the
         * assignment stored in the database.
         */

        $themeId =
            (int)$assignment['theme_id'];

        $passationType =
            $assignment['passation_type'];
    }


    // ==================================================
    // AVAILABLE QUESTIONNAIRE
    // ==================================================

    elseif (
        $questionnaireMode === 'available'
    ) {

        /*
         * The browser sends theme_id, but we must
         * validate that the theme really exists and
         * is currently active.
         */

        $stmt = $pdo->prepare(
            "SELECT
                id,
                name,
                is_active

             FROM themes

             WHERE id = ?

             LIMIT 1"
        );


        $stmt->execute([
            $submittedThemeId
        ]);


        $theme =
            $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$theme) {

            throw new Exception(
                t('h150_questionnaire_not_found')
            );
        }


        if (
            (int)$theme['is_active']
            !== 1
        ) {

            throw new Exception(
                t('h150_this_questionnaire_is_currently_unavailable')
            );
        }


        $themeId =
            (int)$theme['id'];


        /*
         * IMPORTANT:
         *
         * Available questionnaires are always
         * Initial.
         *
         * We do not accept passation_type from
         * the browser.
         */

        $passationType = 'initial';
    }


    // ==================================================
    // GET ACTIVE QUESTIONS
    // ==================================================

    /*
     * We use the same active-question set that
     * questionnaire.php displays.
     *
     * Hierarchy does not change scoring, so only
     * the question information is required here.
     */

    $stmt = $pdo->prepare(
        "SELECT
            id,
            theme_id,
            question_text,
            question_type,
            points,
            display_order

         FROM questions

         WHERE theme_id = ?
           AND is_active = 1

         ORDER BY
            display_order ASC,
            id ASC"
    );


    $stmt->execute([
        $themeId
    ]);


    $questions =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    if (empty($questions)) {

        throw new Exception(
            t('h150_this_questionnaire_has_no_active_questions')
        );
    }


    // ==================================================
    // CALCULATE MAXIMUM SCORE
    // ==================================================

    $maximumScore = 0;


    foreach ($questions as $question) {

        $maximumScore +=
            (float)$question['points'];
    }


    // ==================================================
    // CREATE ATTEMPT
    // ==================================================

    $stmt = $pdo->prepare(
        "INSERT INTO attempts
        (
            trainee_id,
            theme_id,
            passation_type,
            maximum_score
        )
        VALUES (?, ?, ?, ?)"
    );


    $stmt->execute([
        $traineeId,
        $themeId,
        $passationType,
        $maximumScore
    ]);


    $attemptId =
        (int)$pdo->lastInsertId();


    $totalScore = 0;


    // ==================================================
    // PROCESS EVERY QUESTION
    // ==================================================

    foreach ($questions as $question) {

        $questionId =
            (int)$question['id'];

        $questionType =
            $question['question_type'];

        $questionPoints =
            (float)$question['points'];

        $answer =
            $answers[$questionId] ?? null;

        $awardedPoints = 0;


        // ==============================================
        // OPEN QUESTION
        // ==============================================

        if ($questionType === 'open') {

            /*
             * Open questions initially receive
             * zero points.
             *
             * The trainer can manually grade them
             * later.
             */

            if (is_array($answer)) {

                $textAnswer = '';

            } else {

                $textAnswer =
                    trim((string)$answer);
            }


            $stmt = $pdo->prepare(
                "INSERT INTO responses
                (
                    attempt_id,
                    question_id,
                    text_answer,
                    awarded_points
                )
                VALUES (?, ?, ?, 0)"
            );


            $stmt->execute([
                $attemptId,
                $questionId,
                $textAnswer
            ]);
            $awardedPoints = qp_record_suggestion($pdo, (int)$pdo->lastInsertId(), $questionId, $textAnswer);
        }


        // ==============================================
        // SINGLE CHOICE
        // ==============================================

        elseif (
            $questionType === 'single_choice'
        ) {

            $choiceId = filter_var(
                $answer,
                FILTER_VALIDATE_INT
            );


            $validChoiceId = null;


            if ($choiceId) {

                /*
                 * Never trust a submitted choice ID.
                 *
                 * Verify that the choice actually
                 * belongs to this exact question.
                 */

                $stmt = $pdo->prepare(
                    "SELECT
                        id,
                        is_correct

                     FROM choices

                     WHERE id = ?
                       AND question_id = ?

                     LIMIT 1"
                );


                $stmt->execute([
                    $choiceId,
                    $questionId
                ]);


                $choice =
                    $stmt->fetch(PDO::FETCH_ASSOC);


                if ($choice) {

                    $validChoiceId =
                        (int)$choice['id'];


                    if (
                        (int)$choice['is_correct']
                        === 1
                    ) {

                        $awardedPoints =
                            $questionPoints;
                    }
                }
            }


            /*
             * Always create a response,
             * including when the trainee
             * selected nothing.
             */

            $stmt = $pdo->prepare(
                "INSERT INTO responses
                (
                    attempt_id,
                    question_id,
                    awarded_points
                )
                VALUES (?, ?, ?)"
            );


            $stmt->execute([
                $attemptId,
                $questionId,
                $awardedPoints
            ]);


            $responseId =
                (int)$pdo->lastInsertId();


            /*
             * Save only a choice that was
             * validated for this question.
             */

            if ($validChoiceId !== null) {

                $stmt = $pdo->prepare(
                    "INSERT INTO response_choices
                    (
                        response_id,
                        choice_id
                    )
                    VALUES (?, ?)"
                );


                $stmt->execute([
                    $responseId,
                    $validChoiceId
                ]);
            }
        }


        // ==============================================
        // MULTIPLE CHOICE
        // ==============================================

        elseif (
            $questionType === 'multiple_choice'
        ) {

            $selectedChoices =
                is_array($answer)
                    ? $answer
                    : [];


            /*
             * Convert submitted values to integers
             * and remove duplicates.
             */

            $selectedChoices =
                array_map(
                    'intval',
                    $selectedChoices
                );


            $selectedChoices =
                array_values(
                    array_unique(
                        $selectedChoices
                    )
                );


            // ------------------------------------------
            // GET ALL VALID CHOICES
            // ------------------------------------------

            $stmt = $pdo->prepare(
                "SELECT
                    id,
                    is_correct

                 FROM choices

                 WHERE question_id = ?"
            );


            $stmt->execute([
                $questionId
            ]);


            $choices =
                $stmt->fetchAll(
                    PDO::FETCH_ASSOC
                );


            $validIds = [];
            $correctIds = [];


            foreach ($choices as $choice) {

                $id =
                    (int)$choice['id'];

                $validIds[] = $id;


                if (
                    (int)$choice['is_correct']
                    === 1
                ) {

                    $correctIds[] = $id;
                }
            }


            /*
             * Remove manipulated choice IDs that
             * do not belong to this question.
             */

            $selectedChoices =
                array_values(
                    array_intersect(
                        $selectedChoices,
                        $validIds
                    )
                );


            sort($selectedChoices);
            sort($correctIds);


            /*
             * Exact-match scoring:
             *
             * The trainee receives full points only
             * when the selected choices exactly equal
             * all correct choices.
             */

            if (
                !empty($correctIds) &&
                $selectedChoices === $correctIds
            ) {

                $awardedPoints =
                    $questionPoints;
            }


            // ------------------------------------------
            // CREATE RESPONSE
            // ------------------------------------------

            $stmt = $pdo->prepare(
                "INSERT INTO responses
                (
                    attempt_id,
                    question_id,
                    awarded_points
                )
                VALUES (?, ?, ?)"
            );


            $stmt->execute([
                $attemptId,
                $questionId,
                $awardedPoints
            ]);


            $responseId =
                (int)$pdo->lastInsertId();


            // ------------------------------------------
            // SAVE SELECTED CHOICES
            // ------------------------------------------

            foreach (
                $selectedChoices
                as $choiceId
            ) {

                $stmt = $pdo->prepare(
                    "INSERT INTO response_choices
                    (
                        response_id,
                        choice_id
                    )
                    VALUES (?, ?)"
                );


                $stmt->execute([
                    $responseId,
                    $choiceId
                ]);
            }
        }


        // ==============================================
        // UNKNOWN QUESTION TYPE
        // ==============================================

        else {

            throw new Exception(
                t('h150_unsupported_question_type')
            );
        }


        // ==============================================
        // ADD SCORE
        // ==============================================

        $totalScore +=
            $awardedPoints;
    }


    // ==================================================
    // COMPLETE ATTEMPT
    // ==================================================

    $stmt = $pdo->prepare(
        "UPDATE attempts

         SET
            completed_at = CURRENT_TIMESTAMP,
            total_score = ?

         WHERE id = ?"
    );


    $stmt->execute([
        $totalScore,
        $attemptId
    ]);


    // ==================================================
    // COMPLETE ASSIGNMENT
    // ==================================================

    /*
     * Only an assigned questionnaire has an
     * questionnaire_assignments row.
     *
     * Available questionnaires skip this section.
     */

    if ($questionnaireMode === 'assigned') {

        $stmt = $pdo->prepare(
            "UPDATE questionnaire_assignments

             SET
                status = 'completed',
                completed_at = CURRENT_TIMESTAMP,
                attempt_id = ?

             WHERE id = ?
               AND trainee_id = ?
               AND status = 'pending'"
        );


        $stmt->execute([
            $attemptId,
            $assignmentId,
            $traineeId
        ]);


        /*
         * Exactly one assignment must have been
         * completed.
         */

        if ($stmt->rowCount() !== 1) {

            throw new Exception(
                t('h150_the_questionnaire_assignment_could_not_be_completed')
            );
        }
    }


    // ==================================================
    // EVERYTHING SUCCESSFUL
    // ==================================================

    $pdo->prepare("UPDATE attempts SET corrected_at=CURRENT_TIMESTAMP WHERE id=? AND completed_at IS NOT NULL AND corrected_at IS NULL AND NOT EXISTS (SELECT 1 FROM responses r JOIN questions q ON q.id=r.question_id WHERE r.attempt_id=attempts.id AND q.question_type='open' AND r.graded_at IS NULL)")->execute([$attemptId]);
    $pdo->commit();


    header(
        'Location: result.php?id='
        . $attemptId
    );

    exit;


} catch (Throwable $e) {

    // ==================================================
    // ROLLBACK ON ANY FAILURE
    // ==================================================

    if ($pdo->inTransaction()) {

        $pdo->rollBack();
    }


    http_response_code(400);
    exit(t('h150_error_while_saving_questionnaire'));
}