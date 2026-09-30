<?php
require_once __DIR__ . "/../includes/question_support.php";
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/admin_language.php';

// =====================================

// REQUIRED FILES

// =====================================



require_once 'auth.php';

require_once '../config/database.php';
require_once __DIR__.'/../includes/account_security.php';
ux_csrf_check();

require_once '../includes/functions.php';





// =====================================

// GET ATTEMPT ID

// =====================================



$attemptId = filter_input(

    INPUT_GET,

    'id',

    FILTER_VALIDATE_INT

);



if (!$attemptId) {

    die(t('h150_invalid_attempt'));

}





// =====================================

// GET ATTEMPT

// =====================================



$stmt = $pdo->prepare(

    "SELECT

        attempts.*,

        trainees.first_name,

        trainees.last_name,

        trainees.email



     FROM attempts



     INNER JOIN trainees

        ON attempts.trainee_id = trainees.id



     WHERE attempts.id = ?"

);



$stmt->execute([$attemptId]);



$attempt = $stmt->fetch(PDO::FETCH_ASSOC);



if (!$attempt) {

    die(t('p150i_4a818c7a6ae4'));

}





// =====================================

// MESSAGES

// =====================================



$message = '';

$error = '';





// =====================================

// UPDATE PASSATION TYPE

// =====================================



if (

    $_SERVER['REQUEST_METHOD'] === 'POST'

    && isset($_POST['update_passation_type'])

) {



    $passationType =

        $_POST['passation_type'] ?? '';



    $allowedPassationTypes = [

        'initial',

        'final',

        'sortie'

    ];



    if (

        !in_array(

            $passationType,

            $allowedPassationTypes,

            true

        )

    ) {



        $error = t('a150j_524a362a4ec0');



    } else {



        $stmt = $pdo->prepare(

            "UPDATE attempts

             SET passation_type = ?

             WHERE id = ?"

        );



        $stmt->execute([

            $passationType,

            $attemptId

        ]);



        header(

            'Location: attempt_details.php?id='

            . $attemptId

            . '&passation_updated=1'

        );



        exit;

    }

}





// =====================================

// MANUAL GRADING

// =====================================



if (

    $_SERVER['REQUEST_METHOD'] === 'POST'

    && isset($_POST['grade_response'])

) {



    $responseId = filter_input(

        INPUT_POST,

        'response_id',

        FILTER_VALIDATE_INT

    );



    $awardedPoints = filter_input(

        INPUT_POST,

        'awarded_points',

        FILTER_VALIDATE_FLOAT

    );



    $trainerComment = trim(

        $_POST['trainer_comment'] ?? ''

    );





    if (!$responseId) {



        $error = t('p150i_5fe62018765f');



    } elseif (

        $awardedPoints === false

        || $awardedPoints === null

    ) {



        $error = t('p150i_91f039ed17b8');



    } else {



        try {



            $pdo->beginTransaction();
            $lock = $pdo->prepare('SELECT id FROM attempts WHERE id=? FOR UPDATE');
            $lock->execute([$attemptId]);





            // =====================================

            // GET RESPONSE

            // =====================================



            $stmt = $pdo->prepare(

                "SELECT

                    responses.id,

                    questions.points,

                    questions.question_type



                 FROM responses



                 INNER JOIN questions

                    ON responses.question_id = questions.id



                 WHERE responses.id = ?

                 AND responses.attempt_id = ?"

            );



            $stmt->execute([

                $responseId,

                $attemptId

            ]);



            $gradeResponse =

                $stmt->fetch(PDO::FETCH_ASSOC);





            if (!$gradeResponse) {



                throw new Exception(

                    t('a150j_df2ef3b092f9')

                );

            }





            // =====================================

            // ONLY OPEN QUESTIONS

            // =====================================



            if (

                $gradeResponse['question_type']

                !== 'open'

            ) {



                throw new Exception(

                    t('a150j_3df3aa7ee872')

                );

            }





            $maximumPoints =

                (float)$gradeResponse['points'];





            if (

                $awardedPoints < 0

                || $awardedPoints > $maximumPoints

            ) {



                throw new Exception(

                    t('p150i_b89248e9284e') . ' '

                    . $maximumPoints

                    . '.'

                );

            }





            // =====================================

            // SAVE GRADE

            // =====================================



            $stmt = $pdo->prepare(

                "UPDATE responses



                 SET

                    awarded_points = ?,

                    trainer_comment = ?,

                    graded_at = CURRENT_TIMESTAMP



                 WHERE id = ?"

            );



            $stmt->execute([

                $awardedPoints,

                $trainerComment,

                $responseId

            ]);





            // =====================================

            // RECALCULATE TOTAL SCORE

            // =====================================



            $stmt = $pdo->prepare(

                "SELECT

                    COALESCE(

                        SUM(awarded_points),

                        0

                    )



                 FROM responses



                 WHERE attempt_id = ?"

            );



            $stmt->execute([$attemptId]);



            $newTotalScore =

                (float)$stmt->fetchColumn();





            // =====================================

            // UPDATE ATTEMPT SCORE

            // =====================================



            $stmt = $pdo->prepare(

                "UPDATE attempts

                 SET total_score = ?

                 WHERE id = ?"

            );



            $stmt->execute([

                $newTotalScore,

                $attemptId

            ]);





            // =====================================

            // CHECK PENDING OPEN ANSWERS

            // =====================================



            $stmt = $pdo->prepare(

                "SELECT COUNT(*)



                 FROM responses



                 INNER JOIN questions

                    ON questions.id = responses.question_id



                 WHERE responses.attempt_id = ?

                 AND questions.question_type = 'open'

                 AND responses.graded_at IS NULL"

            );



            $stmt->execute([$attemptId]);



            $pendingOpenAnswers =

                (int)$stmt->fetchColumn();





            // =====================================

            // SAVE CORRECTION DATE

            // =====================================



            if ($pendingOpenAnswers === 0) {



                $stmt = $pdo->prepare(

                    "UPDATE attempts



                     SET corrected_at = CURRENT_TIMESTAMP



                     WHERE id = ?

                     AND corrected_at IS NULL AND completed_at IS NOT NULL"

                );



                $stmt->execute([$attemptId]);

            }





            $pdo->commit();





            header(

                'Location: attempt_details.php?id='

                . $attemptId

                . '&graded=1'

            );



            exit;





        } catch (Throwable $e) {



            if ($pdo->inTransaction()) {

                $pdo->rollBack();

            }



            $validationMessages = [
                t('a150j_df2ef3b092f9'),
                t('a150j_3df3aa7ee872'),
                t('p150i_b89248e9284e') . ' ' . ($maximumPoints ?? '') . '.'
            ];

            $error = get_class($e) === Exception::class
                && in_array($e->getMessage(), $validationMessages, true)
                ? $e->getMessage()
                : t('p150i_f81f50d0557d');

        }

    }

}





// =====================================

// SUCCESS MESSAGES

// =====================================



if (isset($_GET['graded'])) {



    $message =

        t('p150i_ed6b7cf52ae4');

}



if (isset($_GET['passation_updated'])) {



    $message =

        t('a150j_95860765fe9a');

}





// =====================================

// RELOAD ATTEMPT

// =====================================



$stmt = $pdo->prepare(

    "SELECT

        attempts.*,

        trainees.first_name,

        trainees.last_name,

        trainees.email



     FROM attempts



     INNER JOIN trainees

        ON attempts.trainee_id = trainees.id



     WHERE attempts.id = ?"

);



$stmt->execute([$attemptId]);



$attempt = $stmt->fetch(PDO::FETCH_ASSOC);





// =====================================

// GET RESPONSES

// =====================================



$stmt = $pdo->prepare(

    "SELECT

        responses.id AS response_id,

        responses.text_answer,

        responses.awarded_points,

        responses.trainer_comment,

        responses.graded_at,



        questions.id AS question_id,

        questions.question_text,

        questions.question_type,

        questions.points,

        questions.display_order,



        themes.name AS theme_name



     FROM responses



     LEFT JOIN questions

        ON responses.question_id = questions.id



     LEFT JOIN themes

        ON questions.theme_id = themes.id



     WHERE responses.attempt_id = ?



     ORDER BY

        questions.display_order ASC,

        responses.id ASC"

);



$stmt->execute([$attemptId]);



$responses =

    $stmt->fetchAll(PDO::FETCH_ASSOC);





// =====================================

// CHECK PENDING MANUAL GRADING

// =====================================



$hasPendingGrading = false;



foreach ($responses as $response) {



    if (

        $response['question_type'] === 'open'

        && $response['graded_at'] === null

    ) {



        $hasPendingGrading = true;

        break;

    }

}





// =====================================

// CALCULATE RESULT

// =====================================



$maximumScore =

    (float)$attempt['maximum_score'];



$totalScore =

    (float)$attempt['total_score'];





if ($maximumScore > 0) {



    $percentage =

        ($totalScore / $maximumScore) * 100;



} else {



    $percentage = 0;

}





// =====================================

// POSITIONING LEVEL

// =====================================



if ($hasPendingGrading) {



    $level = t('h150_provisional_label');



} else {



    $level = getPositioningLevel($pdo, $percentage, t('h150_not_defined'));

}





// =====================================

// CHECK IF FINAL PDF IS AVAILABLE

// =====================================



$canGeneratePdf =

    $attempt['passation_type'] === 'final'

    && !empty($attempt['completed_at'])
    && !empty($attempt['corrected_at'])

    && !$hasPendingGrading;



?>



<!DOCTYPE html>
<html lang="<?= adminH(htmlLanguage()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= adminH(t('attempt_details')) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .attempt-details .card > h2,
        .attempt-details .question-card > h3 { margin-top: 0; }
        .attempt-details .card,
        .attempt-details .question-card,
        .attempt-details .stat-card { min-width: 0; overflow-wrap: anywhere; }
        .attempt-details .trainee-answer {
            margin: 12px 0 24px; padding: 16px;
            border-left: 4px solid #cbd5e1; background: #f8fafc;
            line-height: 1.7; white-space: pre-wrap;
        }
        .attempt-details .grading-form { max-width: 720px; }
        .attempt-details .passation-form { max-width: 480px; }
        .attempt-details .selected-answers { padding-left: 24px; }
        .attempt-details .selected-answers li { padding: 8px 0; }
        .attempt-details .response-score { border-top: 1px solid #e5e7eb; padding-top: 16px; }
        .attempt-details .btn { white-space: normal; overflow-wrap: anywhere; }
    </style>
<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>
<body>
<?php require __DIR__ . '/admin_language_switcher.php'; ?>
<div class="container attempt-details">
    <header class="admin-header">
        <h1><?= adminH(t('p150i_7b3acab7d31d')) ?></h1>
        <p><?= adminH(t('h150_logged_in_as')) ?> <strong><?= htmlspecialchars($_SESSION['admin_username'] ?? t('admin'), ENT_QUOTES, 'UTF-8') ?></strong></p>
    </header>
    <?php require 'admin_nav.php'; ?>

    <section class="card">
        <h2><?= adminH(t('attempt_details')) ?></h2>
        <nav class="actions" aria-label="<?= adminH(t('back_navigation')) ?>">
            <a class="btn btn-secondary" href="trainee_details.php?lang=<?= adminH(currentLanguage()) ?>&amp;id=<?= (int)$attempt['trainee_id'] ?>"><?= adminH(t('a150j_88fff108e20a')) ?></a>
            <a class="btn btn-secondary" href="results.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('a150j_709c4d89c475')) ?></a>
        </nav>
    </section>

    <?php if ($message): ?>
        <div class="alert alert-success" role="status"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <section class="card">
        <h2><?= adminH(t('p150i_a3b8345b1173')) ?></h2>
        <h3><?= htmlspecialchars($attempt['first_name']) ?> <?= htmlspecialchars($attempt['last_name']) ?></h3>
        <p class="text-muted"><?= adminH(t('h150_email')) ?> <?= htmlspecialchars($attempt['email']) ?></p>
    </section>

    <section class="card">
        <h2><?= adminH(t('passation_type')) ?></h2>
        <form method="POST" class="passation-form"><?php ux_csrf_field(); ?>
<input type="hidden" name="lang" value="<?= adminH(currentLanguage()) ?>">
            <label for="passation_type"><?= adminH(t('p150i_6cc5ad2e47e3')) ?></label>
            <select id="passation_type" name="passation_type" required>
                <option value="initial" <?= $attempt['passation_type'] === 'initial' ? 'selected' : '' ?>><?= adminH(t('h150_initial')) ?></option>
                <option value="final" <?= $attempt['passation_type'] === 'final' ? 'selected' : '' ?>><?= adminH(t('h150_final')) ?></option>
                <option value="sortie" <?= $attempt['passation_type'] === 'sortie' ? 'selected' : '' ?>><?= adminH(t('exit')) ?></option>
            </select>
            <div class="actions">
                <button class="btn" type="submit" name="update_passation_type" value="1"><?= adminH(t('a150j_5989d0180e75')) ?></button>
            </div>
        </form>
    </section>

    <section class="card">
        <h2><?= adminH(t('p150i_85cab10f9aa9')) ?></h2>
        <p><?= adminH(t('p150i_7c8579a78bc5')) ?> <strong><?= htmlspecialchars($attempt['completed_at'] ?? $attempt['started_at']) ?></strong></p>
        <p><?= adminH(t('p150i_473b928781d3')) ?> <strong><?php if ($attempt['corrected_at']): ?><?= htmlspecialchars($attempt['corrected_at']) ?><?php else: ?>—<?php endif; ?></strong></p>
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?= adminH(t('total_score')) ?></h3>
                <div class="stat-number"><?= htmlspecialchars((string)$totalScore) ?> / <?= htmlspecialchars((string)$maximumScore) ?></div>
            </div>
            <div class="stat-card">
                <h3><?= adminH(t('h150_percentage')) ?></h3>
                <div class="stat-number"><?= number_format($percentage, 1) ?>%</div>
            </div>
            <div class="stat-card">
                <h3><?= adminH(t('h150_positioning_level')) ?></h3>
                <div class="stat-number"><?= htmlspecialchars($level) ?></div>
            </div>
        </div>
        <p><?= adminH(t('a150j_277a52357ab0')) ?>
            <?php if ($hasPendingGrading): ?>
                <span class="status status-inactive"><?= adminH(t('h150_pending_correction')) ?></span>
            <?php else: ?>
                <span class="status status-active"><?= adminH(t('p150i_dd1c17c43141')) ?></span>
            <?php endif; ?>
        </p>
    </section>

    <p class="no-print"><button type="button" onclick="window.print()"><?= htmlspecialchars(t('pkg_print'), ENT_QUOTES, 'UTF-8') ?></button></p>
    <?php if ($canGeneratePdf): ?>
        <section class="card">
            <h2><?= adminH(t('p150i_9008f2890da0')) ?></h2>
            <p class="alert alert-success"><?= adminH(t('a150j_39f1c642d616')) ?></p>
            <div class="actions">
                <a class="btn btn-success" href="download_result_pdf.php?lang=<?= adminH(currentLanguage()) ?>&amp;id=<?= (int)$attemptId ?>"><?= adminH(t('p150i_c6b4e4f0b28f')) ?></a>
            </div>
        </section>
    <?php elseif ($attempt['passation_type'] === 'final' && $hasPendingGrading): ?>
        <section class="card">
            <h2><?= adminH(t('p150i_9008f2890da0')) ?></h2>
            <p class="text-muted"><?= adminH(t('a150j_db3ad4359e78')) ?></p>
        </section>
    <?php endif; ?>

    <section aria-labelledby="responses-heading">
        <h2 id="responses-heading"><?= adminH(t('a150j_9b4c6d73aac1')) ?></h2>
        <?php foreach ($responses as $index => $response): ?>
            <article class="question-card">
                <h3><?= adminH(t('h150_question')) ?> <?= $index + 1 ?></h3>
                <p><strong><?= htmlspecialchars($response['question_text']) ?></strong></p>
                <p class="text-muted"><?= adminH(t('p150i_6cc5ad2e47e3')) ?> <?= adminH(adminEnumLabel($response['question_type'])) ?></p>

                <?php if ($response['question_type'] === 'open'): ?>
                    <h4><?= adminH(t('p150i_a1803d4bcabd')) ?></h4>
                    <blockquote class="trainee-answer"><?= nl2br(htmlspecialchars($response['text_answer'] ?? '')) ?></blockquote>
                    <h4><?= adminH(t('a150j_89f20b0f61fa')) ?></h4>
                    <?php if ($response['graded_at'] === null): ?>
                        <p><strong><?= adminH(t('p150i_755c8b2a9fb1')) ?></strong> <span class="status status-inactive"><?= adminH(t('h150_pending_review')) ?></span></p>
                    <?php else: ?>
                        <p><strong><?= adminH(t('p150i_755c8b2a9fb1')) ?></strong> <span class="status status-active"><?= adminH(t('p150i_54bd179e950c')) ?></span></p>
                        <p class="text-muted"><?= adminH(t('p150i_15776ce1d415')) ?> <?= htmlspecialchars($response['graded_at']) ?></p>
                    <?php endif; ?>

                    <?php qp_review_suggestion($pdo, (int)$response['response_id']); ?>
                    <form method="POST" class="grading-form"><?php ux_csrf_field(); ?>
<input type="hidden" name="lang" value="<?= adminH(currentLanguage()) ?>">
                        <input type="hidden" name="response_id" value="<?= (int)$response['response_id'] ?>">
                        <label for="score-<?= (int)$response['response_id'] ?>"><?= adminH(t('a150j_bc9a64617ade')) ?> <?= htmlspecialchars($response['points']) ?></label>
                        <input type="number" id="score-<?= (int)$response['response_id'] ?>" name="awarded_points" min="0" max="<?= htmlspecialchars($response['points']) ?>" step="0.01" value="<?= htmlspecialchars($response['awarded_points']) ?>" required>
                        <label for="comment-<?= (int)$response['response_id'] ?>"><?= adminH(t('p150i_8fe035bcd5c5')) ?></label>
                        <textarea id="comment-<?= (int)$response['response_id'] ?>" name="trainer_comment" rows="4" cols="60" placeholder="<?= adminH(t('p150i_4bfefe0bd5fb')) ?>"><?= htmlspecialchars($response['trainer_comment'] ?? '') ?></textarea>
                        <div class="actions">
                            <button class="btn" type="submit" name="grade_response" value="1"><?= adminH(t('p150i_a6b41d41a08d')) ?></button>
                        </div>
                    </form>
                <?php else: ?>
                    <?php
$choiceStmt = $pdo->prepare(

                "SELECT

                    choices.choice_text,

                    choices.is_correct



                 FROM response_choices



                 INNER JOIN choices

                    ON response_choices.choice_id

                       = choices.id



                 WHERE response_choices.response_id = ?



                 ORDER BY

                    choices.display_order ASC,

                    choices.id ASC"

            );





            $choiceStmt->execute([

                $response['response_id']

            ]);





            $selectedChoices =

                $choiceStmt->fetchAll(

                    PDO::FETCH_ASSOC

                );



            
                    ?>
                    <h4><?= adminH(t('p150i_565d372b4dc2')) ?></h4>
                    <?php if (empty($selectedChoices)): ?>
                        <p class="text-muted"><?= adminH(t('h150_no_answer_selected')) ?></p>
                    <?php else: ?>
                        <ul class="selected-answers">
                            <?php foreach ($selectedChoices as $choice): ?>
                                <li>
                                    <?= htmlspecialchars($choice['choice_text']) ?>
                                    <?php if ((int)$choice['is_correct'] === 1): ?>
                                        <span class="status status-active"><?= adminH(t('p150i_2bd9457f65f8')) ?></span>
                                    <?php else: ?>
                                        <span class="status status-inactive"><?= adminH(t('p150i_ca1b91cf317b')) ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endif; ?>
                <p class="response-score"><?= adminH(t('h150_score_label')) ?> <strong><?= htmlspecialchars($response['awarded_points']) ?> / <?= htmlspecialchars($response['points']) ?></strong></p>
            </article>
        <?php endforeach; ?>
    </section>

    <nav class="workflow-navigation" aria-label="<?= adminH(t('p150i_72258823e495')) ?>">
        <a class="btn btn-secondary" href="trainee_details.php?lang=<?= adminH(currentLanguage()) ?>&amp;id=<?= (int)$attempt['trainee_id'] ?>"><?= adminH(t('a150j_6ea4d66804ca')) ?></a>
        <a class="btn btn-secondary" href="results.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('a150j_709c4d89c475')) ?></a>
        <a class="btn btn-secondary" href="index.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('admin_dashboard_link')) ?></a>
        <a class="btn btn-secondary" href="../index.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('p150i_ad2638a9d1dd')) ?></a>
        <a class="btn btn-secondary" href="logout.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('h150_logout')) ?></a>
    </nav>
</div>
</body>
</html>
