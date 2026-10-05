<?php
require_once __DIR__.'/../includes/positioning_ux.php';
require_once __DIR__ . "/../includes/course_access.php";
require_once __DIR__ . "/../includes/question_support.php";
require_once __DIR__ . '/professor_language.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once '../config/database.php';
require_once __DIR__.'/../includes/account_security.php';
ux_csrf_check();
require_once '../includes/functions.php';


// ======================================================
// PROFESSOR AUTHENTICATION
// ======================================================

if (!isset($_SESSION['professor_id'])) {
    header('Location: login.php?lang=' . rawurlencode(currentLanguage()));
    exit;
}

$professorId = (int)$_SESSION['professor_id'];


// ======================================================
// VERIFY PROFESSOR ACCOUNT
// ======================================================

$stmt = $pdo->prepare(
    "SELECT
        id,
        first_name,
        last_name,
        email,
        username,
        is_active
     FROM professors
     WHERE id = ?
     LIMIT 1"
);

$stmt->execute([$professorId]);

$professor = $stmt->fetch(PDO::FETCH_ASSOC);

if (
    !$professor
    || (int)$professor['is_active'] !== 1
) {
    $_SESSION = [];
    session_destroy();

    header('Location: login.php?lang=' . rawurlencode(currentLanguage()));
    exit;
}


// ======================================================
// UPDATE SESSION
// ======================================================

$_SESSION['professor_username'] =
    $professor['username'];

$_SESSION['professor_name'] =
    trim(
        $professor['first_name']
        . ' '
        . $professor['last_name']
    );


// ======================================================
// GET ATTEMPT ID
// ======================================================

$attemptId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$attemptId) {
    header('Location: trainees.php');
    exit;
}


// ======================================================
// SECURITY: LOAD ONLY AN AUTHORIZED ATTEMPT
//
// Professor must:
// 2. Have course-content access to the attempt theme.
//
// permission check later.
// ======================================================

$stmt = $pdo->prepare(
    "SELECT DISTINCT
        a.*,
        tr.first_name,
        tr.last_name,
        tr.email,
        t.name AS theme_name

     FROM attempts a

     INNER JOIN trainees tr
        ON tr.id = a.trainee_id

     INNER JOIN themes t
        ON t.id = a.theme_id

     INNER JOIN (SELECT DISTINCT professor_id, theme_id FROM professor_content_assignments) pta ON pta.theme_id = a.theme_id
       AND pta.professor_id = ?

     WHERE a.id = ?

       AND EXISTS (
            SELECT 1
            FROM professor_content_assignments pca
            WHERE pca.professor_id = ?
              AND pca.theme_id = a.theme_id
              AND (pca.chapter_id IS NULL OR EXISTS (SELECT 1 FROM responses sr JOIN questions sq ON sq.id=sr.question_id WHERE sr.attempt_id=a.id AND sq.theme_id=a.theme_id AND sq.chapter_id=pca.chapter_id))
       )

     LIMIT 1"
);

$stmt->execute([
    $professorId,
    $attemptId,
    $professorId
]);

$attempt = $stmt->fetch(PDO::FETCH_ASSOC);


// ======================================================
// ACCESS DENIED
// ======================================================

if (!$attempt) {

    http_response_code(403);

    ?>
    <!DOCTYPE html>
    <html lang="<?= professorH(htmlLanguage()) ?>">

    <head>

        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >

        <title><?= professorH(t('p150i_d134ca025a6c')) ?></title>

        <link
            rel="stylesheet"
            href="../assets/css/style.css"
        >

    <script src="../assets/js/professor_language.js" defer></script>
<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>

    <body>

    <div class="container">

        <?php require 'professor_nav.php'; ?>

        <main>

            <section class="card">

                <h1><?= professorH(t('p150i_d134ca025a6c')) ?></h1>

                <p>
                    <?= professorH(t('p150i_b6a0592fc0d6')) ?>
                </p>

                <p>
                    <?= professorH(t('p150i_b8982494ddd2')) ?>
                </p>

                <div class="actions">

                    <a
                        class="btn btn-secondary"
                        href="trainees.php"
                    >
                        <?= professorH(t('p150i_340d1c4ac7cc')) ?>
                    </a>

                </div>

            </section>

        </main>

    </div>

    <div class="container"><?php require_once __DIR__.'/../includes/competency_results.php'; pu_competency_results($pdo, $attempt, $professorId); ?></div>
</body>
    </html>

    <?php

    exit;
}


// ======================================================
// MESSAGES
// ======================================================

$message = '';
$error = '';


// ======================================================
// MANUAL GRADING
// ======================================================

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


            // ==================================================
            // SECURITY: GET ONLY A RESPONSE THIS PROFESSOR
            // IS ALLOWED TO GRADE
            //
            // Required:
            // - response belongs to current attempt
            // - open question
            // - matching professor content assignment
            // ==================================================

            $stmt = $pdo->prepare(
                "SELECT
                    r.id,
                    q.id AS question_id,
                    q.points,
                    q.question_type,
                    q.theme_id,
                    q.chapter_id

                 FROM responses r

                 INNER JOIN questions q
                    ON q.id = r.question_id

                 WHERE r.id = ?
                   AND r.attempt_id = ?
                   AND q.question_type = 'open'

                   AND EXISTS (
                        SELECT 1
                        FROM professor_content_assignments pca
                        WHERE pca.professor_id = ?
                          AND pca.theme_id = q.theme_id AND q.theme_id = (SELECT theme_id FROM attempts WHERE id=r.attempt_id) AND (pca.chapter_id IS NULL OR pca.chapter_id = q.chapter_id)
                          
                   )

                 LIMIT 1"
            );

            $stmt->execute([
                $responseId,
                $attemptId,
                $professorId
            ]);

            $gradeResponse =
                $stmt->fetch(PDO::FETCH_ASSOC);


            if (!$gradeResponse) {

                throw new Exception(
                    t('p150i_e5f96445b5b0')
                );
            }


            // ==================================================
            // VALIDATE SCORE
            // ==================================================

            $maximumPoints =
                (float)$gradeResponse['points'];

            if (
                $awardedPoints < 0
                || $awardedPoints > $maximumPoints
            ) {

                throw new Exception(
                    t('p150i_b89248e9284e')
                    . $maximumPoints
                    . '.'
                );
            }


            // ==================================================
            // SAVE GRADE
            // ==================================================

            $stmt = $pdo->prepare(
                "UPDATE responses

                 SET
                    awarded_points = ?,
                    trainer_comment = ?,
                    graded_at = CURRENT_TIMESTAMP

                 WHERE id = ?
                   AND attempt_id = ?"
            );

            $stmt->execute([
                $awardedPoints,
                $trainerComment,
                $responseId,
                $attemptId
            ]);


            // ==================================================
            // RECALCULATE COMPLETE ATTEMPT SCORE
            //
            // IMPORTANT:
            // We recalculate from ALL responses in the attempt,
            // not only responses visible to this professor.
            // ==================================================

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


            $stmt = $pdo->prepare(
                "UPDATE attempts
                 SET total_score = ?
                 WHERE id = ?"
            );

            $stmt->execute([
                $newTotalScore,
                $attemptId
            ]);


            // ==================================================
            // CHECK ALL OPEN ANSWERS IN THE ATTEMPT
            //
            // This intentionally checks ALL open answers,
            // including answers outside a chapter-specific
            // professor assignment.
            //
            // Therefore a professor cannot incorrectly mark
            // the whole attempt corrected while another open
            // answer still needs grading.
            // ==================================================

            $stmt = $pdo->prepare(
                "SELECT COUNT(*)

                 FROM responses r

                 INNER JOIN questions q
                    ON q.id = r.question_id

                 WHERE r.attempt_id = ?
                   AND q.question_type = 'open'
                   AND r.graded_at IS NULL"
            );

            $stmt->execute([$attemptId]);

            $allPendingOpenAnswers =
                (int)$stmt->fetchColumn();


            // ==================================================
            // SAVE CORRECTION DATE
            // ==================================================

            if ($allPendingOpenAnswers === 0) {

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
                t('p150i_e5f96445b5b0'),
                t('p150i_b89248e9284e')
                    . ($maximumPoints ?? '')
                    . '.'
            ];

            $error =
                get_class($e) === Exception::class
                && in_array(
                    $e->getMessage(),
                    $validationMessages,
                    true
                )
                    ? $e->getMessage()
                    : t('p150i_f81f50d0557d');
        }
    }
}


// ======================================================
// SUCCESS MESSAGE
// ======================================================

if (isset($_GET['graded'])) {
    $message = t('p150i_ed6b7cf52ae4');
}


// ======================================================
// RELOAD AUTHORIZED ATTEMPT
// ======================================================

$stmt = $pdo->prepare(
    "SELECT DISTINCT
        a.*,
        tr.first_name,
        tr.last_name,
        tr.email,
        t.name AS theme_name

     FROM attempts a

     INNER JOIN trainees tr
        ON tr.id = a.trainee_id

     INNER JOIN themes t
        ON t.id = a.theme_id

     INNER JOIN (SELECT DISTINCT professor_id, theme_id FROM professor_content_assignments) pta ON pta.theme_id = a.theme_id
       AND pta.professor_id = ?

     WHERE a.id = ?

       AND EXISTS (
            SELECT 1
            FROM professor_content_assignments pca
            WHERE pca.professor_id = ?
              AND pca.theme_id = a.theme_id
              AND (pca.chapter_id IS NULL OR EXISTS (SELECT 1 FROM responses sr JOIN questions sq ON sq.id=sr.question_id WHERE sr.attempt_id=a.id AND sq.theme_id=a.theme_id AND sq.chapter_id=pca.chapter_id))
       )

     LIMIT 1"
);

$stmt->execute([
    $professorId,
    $attemptId,
    $professorId
]);

$attempt =
    $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {

    http_response_code(403);
    die(t('p150i_278f003df0c2'));
}


// ======================================================
// LOAD ONLY PERMITTED RESPONSES
//
//
// Entire-theme assignment:
//     all questions in the theme.
//
// Chapter assignment:
//     only questions in that chapter.
// ======================================================

$stmt = $pdo->prepare(
    "SELECT DISTINCT

        r.id AS response_id,
        r.text_answer,
        r.awarded_points,
        r.trainer_comment,
        r.graded_at,

        q.id AS question_id,
        q.question_text,
        q.question_type,
        q.points,
        q.display_order,
        q.theme_id,
        q.chapter_id,

        t.name AS theme_name,

        c.title AS chapter_title

     FROM responses r

     INNER JOIN questions q
        ON q.id = r.question_id

     INNER JOIN themes t
        ON t.id = q.theme_id

     LEFT JOIN chapters c
        ON c.id = q.chapter_id

     WHERE r.attempt_id = ?

       AND EXISTS (
            SELECT 1
            FROM professor_content_assignments pca
            WHERE pca.professor_id = ?
              AND pca.theme_id = q.theme_id AND q.theme_id = (SELECT theme_id FROM attempts WHERE id=r.attempt_id) AND (pca.chapter_id IS NULL OR pca.chapter_id = q.chapter_id)
              
       )

     ORDER BY
        q.display_order ASC,
        r.id ASC"
);

$stmt->execute([
    $attemptId,
    $professorId
]);

$responses =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// LOAD SELECTED CHOICES FOR PERMITTED RESPONSES
// ======================================================

$selectedChoicesByResponse = [];

if (!empty($responses)) {

    $responseIds = array_map(
        static function ($response) {
            return (int)$response['response_id'];
        },
        $responses
    );

    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($responseIds),
                '?'
            )
        );

    $stmt = $pdo->prepare(
        "SELECT
            rc.response_id,
            ch.choice_text,
            ch.is_correct

         FROM response_choices rc

         INNER JOIN choices ch
            ON ch.id = rc.choice_id

         WHERE rc.response_id IN ($placeholders)

         ORDER BY
            rc.response_id ASC,
            ch.display_order ASC,
            ch.id ASC"
    );

    $stmt->execute($responseIds);

    $choiceRows =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($choiceRows as $choice) {

        $responseIdKey =
            (int)$choice['response_id'];

        if (!isset(
            $selectedChoicesByResponse[
                $responseIdKey
            ]
        )) {

            $selectedChoicesByResponse[
                $responseIdKey
            ] = [];
        }

        $selectedChoicesByResponse[
            $responseIdKey
        ][] = $choice;
    }
}


// ======================================================
// CHECK PENDING GRADING VISIBLE TO PROFESSOR
// ======================================================

$professorPendingGrading = false;

foreach ($responses as $response) {

    if (
        $response['question_type'] === 'open'
        && $response['graded_at'] === null
    ) {

        $professorPendingGrading = true;
        break;
    }
}


// ======================================================
// CHECK ALL PENDING OPEN ANSWERS IN ATTEMPT
//
// This may include questions outside this professor's
// chapter permissions.
// ======================================================

$attempt = qp_scope_attempt($pdo, $professorId, $attempt);
$wholeThemeAccess = $attempt['whole_theme_access'];

$stmt = $pdo->prepare(
    "SELECT COUNT(*)

     FROM responses r

     INNER JOIN questions q
        ON q.id = r.question_id

     WHERE r.attempt_id = ?
       AND q.question_type = 'open'
       AND r.graded_at IS NULL"
);

$stmt->execute([$attemptId]);

$allPendingOpenAnswers =
    (int)$stmt->fetchColumn();

$attemptHasPendingGrading =
    ($wholeThemeAccess ? $allPendingOpenAnswers > 0 : $professorPendingGrading);


// ======================================================
// RESULT
// ======================================================

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


// ======================================================
// POSITIONING LEVEL
// ======================================================

if ($attemptHasPendingGrading) {

    $level = t('h150_provisional_label');

} else {

    $level = getPositioningLevel($pdo, $percentage, t('h150_not_defined'));
}


// ======================================================
// FINAL PDF ELIGIBILITY
//
// The actual professor PDF download endpoint will be
// created later with its own authorization check.
// ======================================================

$canGeneratePdf =
    $wholeThemeAccess
    && $attempt['passation_type'] === 'final'
    && !empty($attempt['completed_at'])
    && !empty($attempt['corrected_at'])
    && !$attemptHasPendingGrading;


// ======================================================
// PASSATION LABEL
// ======================================================

$passationLabel =
    match ($attempt['passation_type']) {

        'initial' => t('initial'),
        'final' => t('final'),
        'sortie' => t('exit'),

        default =>
            ucfirst(
                (string)$attempt['passation_type']
            )
    };

?>

<!DOCTYPE html>
<html lang="<?= professorH(htmlLanguage()) ?>">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= professorH(t('p150i_9d6ff2f86a96')) ?>
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <style>

        .professor-header {
            margin-bottom: 24px;
            padding: 28px;
            color: #ffffff;
            background: #1e3a8a;
            border-radius: 18px;
        }

        .professor-header h1 {
            margin-top: 0;
            color: #ffffff;
        }

        .attempt-details .card,
        .attempt-details .question-card,
        .attempt-details .stat-card {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .attempt-details .card {
            margin-bottom: 22px;
            padding: 22px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
        }

        .attempt-details .card > h2,
        .attempt-details .question-card > h3 {
            margin-top: 0;
        }

        .security-note {
            margin-bottom: 22px;
            padding: 16px;
            background: #eff6ff;
            border-left: 4px solid #2563eb;
            border-radius: 8px;
        }

        .question-card {
            margin-bottom: 20px;
            padding: 22px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
        }

        .trainee-answer {
            margin: 12px 0 24px;
            padding: 16px;
            border-left: 4px solid #cbd5e1;
            background: #f8fafc;
            line-height: 1.7;
            white-space: pre-wrap;
        }

        .grading-form {
            max-width: 720px;
        }

        .selected-answers {
            padding-left: 24px;
        }

        .selected-answers li {
            padding: 8px 0;
        }

        .response-score {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }

        .question-location {
            margin: 12px 0;
            padding: 10px 12px;
            color: #475569;
            background: #f8fafc;
            border-radius: 8px;
        }

        .attempt-details .btn {
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .pdf-ready {
            padding: 14px;
            color: #166534;
            background: #f0fdf4;
            border: 1px solid #86efac;
            border-radius: 8px;
        }

        .pdf-pending {
            padding: 14px;
            color: #92400e;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
        }

    </style>

<script src="../assets/js/professor_language.js" defer></script>
<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>

<body>

<div class="container attempt-details">


    <!-- ================================================= -->
    <!-- HEADER -->
    <!-- ================================================= -->

    <header class="professor-header">

        <h1>
            <?= professorH(t('p150i_9d6ff2f86a96')) ?>
        </h1>

        <p>
            <?= professorH(t('h150_logged_in_as')) ?>
            <strong>
                <?= htmlspecialchars(
                    $_SESSION['professor_name']
                    ?? t('professor')
                ) ?>
            </strong>
        </p>

    </header>


    <!-- ================================================= -->
    <!-- NAVIGATION -->
    <!-- ================================================= -->

    <?php require 'professor_nav.php'; ?>


    <!-- ================================================= -->
    <!-- BACK NAVIGATION -->
    <!-- ================================================= -->

    <section class="card">

        <h2>
            <?= professorH(t('p150i_513c2cb7aff4')) ?><?= (int)$attemptId ?>
        </h2>

        <nav
            class="actions"
            aria-label="<?= professorH(t('back_navigation')) ?>"
        >

            <a
                class="btn btn-secondary"
                href="trainee_results.php?trainee_id=<?= (int)$attempt['trainee_id'] ?>"
            >
                <?= professorH(t('p150i_0f75468cc07a')) ?>
            </a>

            <a
                class="btn btn-secondary"
                href="trainees.php"
            >
                <?= professorH(t('assigned_trainees')) ?>
            </a>

        </nav>

    </section>


    <!-- ================================================= -->
    <!-- MESSAGES -->
    <!-- ================================================= -->

    <?php if ($message): ?>

        <div
            class="alert alert-success"
            role="status"
        >
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div
            class="alert alert-error"
            role="alert"
        >
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <!-- ================================================= -->
    <!-- SECURITY NOTE -->
    <!-- ================================================= -->

    <div class="security-note">

        <strong>
            <?= professorH(t('p150i_261aab5f7d44')) ?>
        </strong>

        <p>
            <?= professorH(t('p150i_f760810af1a8')) ?>
        </p>

        <p>
            <?= professorH(t('p150i_f138e6e09512')) ?>
        </p>

    </div>


    <!-- ================================================= -->
    <!-- TRAINEE IDENTITY -->
    <!-- ================================================= -->

    <section class="card">

        <h2>
            <?= professorH(t('p150i_a3b8345b1173')) ?>
        </h2>

        <h3>
            <?= htmlspecialchars(
                $attempt['first_name']
            ) ?>

            <?= htmlspecialchars(
                $attempt['last_name']
            ) ?>
        </h3>

        <p class="text-muted">

            <?= professorH(t('h150_email')) ?>
            <?= htmlspecialchars(
                $attempt['email']
            ) ?>

        </p>

        <p>

            <strong><?= professorH(t('h150_theme_label')) ?></strong>

            <?= htmlspecialchars(
                $attempt['theme_name']
            ) ?>

        </p>

        <p>

            <strong><?= professorH(t('p150i_7e85dd1181b3')) ?></strong>

            <?= htmlspecialchars(
                $passationLabel
            ) ?>

        </p>

    </section>


    <!-- ================================================= -->
    <!-- RESULT SUMMARY -->
    <!-- ================================================= -->

    <section class="card">

        <h2>
            <?= professorH(t('p150i_85cab10f9aa9')) ?>
        </h2>

        <p>

            <?= professorH(t('p150i_7c8579a78bc5')) ?>

            <strong>
                <?= htmlspecialchars(
                    $attempt['completed_at']
                    ?? $attempt['started_at']
                ) ?>
            </strong>

        </p>

        <p>

            <?= professorH(t('p150i_473b928781d3')) ?>

            <strong>

                <?php if (
                    $attempt['corrected_at']
                ): ?>

                    <?= htmlspecialchars(
                        $attempt['corrected_at']
                    ) ?>

                <?php else: ?>

                    —

                <?php endif; ?>

            </strong>

        </p>


        <div class="stats-grid">


            <div class="stat-card">

                <h3><?= professorH(t('total_score')) ?></h3>

                <div class="stat-number">

                    <?= htmlspecialchars(
                        (string)$totalScore
                    ) ?>

                    /

                    <?= htmlspecialchars(
                        (string)$maximumScore
                    ) ?>

                </div>

            </div>


            <div class="stat-card">

                <h3><?= professorH(t('percentage')) ?></h3>

                <div class="stat-number">

                    <?= number_format(
                        $percentage,
                        1
                    ) ?>%

                </div>

            </div>


            <div class="stat-card">

                <h3><?= professorH(t('positioning_level')) ?></h3>

                <div class="stat-number">

                    <?= htmlspecialchars($level) ?>

                </div>

            </div>


        </div>


        <p>

            <?= professorH(t('p150i_bd50f0f821c5')) ?>

            <?php if (
                $attemptHasPendingGrading
            ): ?>

                <span
                    class="status status-inactive"
                >
                    <?= professorH(t('h150_pending_correction')) ?>
                </span>

            <?php else: ?>

                <span
                    class="status status-active"
                >
                    <?= professorH(t('p150i_dd1c17c43141')) ?>
                </span>

            <?php endif; ?>

        </p>


        <?php if (
            !$professorPendingGrading
            && $attemptHasPendingGrading
        ): ?>

            <p class="text-muted">

                <?= professorH(t('p150i_8ff4f3e3a187')) ?>

            </p>

        <?php endif; ?>


    </section>


    <!-- ================================================= -->
<?php if ($canGeneratePdf): ?><p class="no-print"><button type="button" onclick="window.print()"><?= qp_h(t('pkg_print')) ?></button></p>
<?php else: ?><p role="status"><?= qp_h(t($wholeThemeAccess ? 'pkg_print_pending' : 'pkg_scope_result')) ?></p><?php endif; ?>
    <!-- FINAL PDF STATUS -->
    <!-- ================================================= -->

    <?php if (
        $attempt['passation_type'] === 'final'
    ): ?>

        <section class="card">

            <h2>
                <?= professorH(t('p150i_9008f2890da0')) ?>
            </h2>


    
    <?php if ($canGeneratePdf): ?>

        <div class="pdf-ready">

            <p>
                <strong>
                    <?= professorH(t('p150i_af2b4338a7ad')) ?>
                </strong>
            </p>

            <p>
                <?= professorH(t('p150i_7bc40e09fb1d')) ?>
            </p>

            <div class="actions">

                <a
                    class="btn btn-success"
                    href="download_result_pdf.php?id=<?= (int)$attemptId ?>"
                >
                    <?= professorH(t('p150i_c6b4e4f0b28f')) ?>
                </a>

            </div>

        </div>

            <?php elseif (
                $attemptHasPendingGrading
            ): ?>

                <div class="pdf-pending">

                    <?= professorH(t('p150i_853dce356bdc')) ?>

                </div>

            <?php else: ?>

                <p class="text-muted">
                    <?= professorH(t('p150i_a2a7c9dd4ab9')) ?>
                </p>

            <?php endif; ?>


        </section>

    <?php endif; ?>


    <!-- ================================================= -->
    <!-- RESPONSES -->
    <!-- ================================================= -->

    <section
        aria-labelledby="responses-heading"
    >

        <h2 id="responses-heading">
            <?= professorH(t('p150i_69aacc975e41')) ?>
        </h2>


        <?php if (empty($responses)): ?>

            <div class="card">

                <p>
                    <?= professorH(t('p150i_9bdd9281f721')) ?>
                </p>

            </div>

        <?php else: ?>


            <?php foreach (
                $responses as $index => $response
            ): ?>


                <?php

                $responseId =
                    (int)$response['response_id'];

                $selectedChoices =
                    $selectedChoicesByResponse[
                        $responseId
                    ]
                    ?? [];

                ?>


                <article class="question-card">


                    <h3>
                        <?= professorH(t('question')) ?> <?= $index + 1 ?>
                    </h3>


                    <p>
                        <strong>
                            <?= htmlspecialchars(
                                $response[
                                    'question_text'
                                ]
                            ) ?>
                        </strong>
                    </p>


                    <p class="text-muted">

                        <?= professorH(t('p150i_6cc5ad2e47e3')) ?>

                        <?= professorH(professorQuestionTypeLabel($response[
                                'question_type'
                            ])) ?>

                    </p>


                    <div class="question-location">

                        <strong><?= professorH(t('h150_theme_label')) ?></strong>

                        <?= htmlspecialchars(
                            $response['theme_name']
                        ) ?>


                        <?php if (
                            !empty(
                                $response[
                                    'chapter_title'
                                ]
                            )
                        ): ?>

                            &nbsp; → &nbsp;

                            <strong><?= professorH(t('h150_chapter_label')) ?></strong>

                            <?= htmlspecialchars(
                                $response[
                                    'chapter_title'
                                ]
                            ) ?>

                        <?php endif; ?>

                    </div>


                    <!-- ===================================== -->
                    <!-- OPEN QUESTION -->
                    <!-- ===================================== -->

                    <?php if (
                        $response['question_type']
                        === 'open'
                    ): ?>


                        <h4>
                            <?= professorH(t('p150i_a1803d4bcabd')) ?>
                        </h4>


                        <blockquote
                            class="trainee-answer"
                        >
                            <?= nl2br(
                                htmlspecialchars(
                                    $response[
                                        'text_answer'
                                    ]
                                    ?? ''
                                )
                            ) ?>
                        </blockquote>


                        <h4>
                            <?= professorH(t('p150i_b75947ea6246')) ?>
                        </h4>


                        <?php if (
                            $response['graded_at']
                            === null
                        ): ?>

                            <p>

                                <strong><?= professorH(t('p150i_755c8b2a9fb1')) ?></strong>

                                <span
                                    class="status
                                    status-inactive"
                                >
                                    <?= professorH(t('h150_pending_review')) ?>
                                </span>

                            </p>

                        <?php else: ?>

                            <p>

                                <strong><?= professorH(t('p150i_755c8b2a9fb1')) ?></strong>

                                <span
                                    class="status
                                    status-active"
                                >
                                    <?= professorH(t('p150i_54bd179e950c')) ?>
                                </span>

                            </p>

                            <p class="text-muted">

                                <?= professorH(t('p150i_15776ce1d415')) ?>

                                <?= htmlspecialchars(
                                    $response[
                                        'graded_at'
                                    ]
                                ) ?>

                            </p>

                        <?php endif; ?>


                        <?php qp_review_suggestion($pdo, (int)$response['response_id']); ?><div class="pu-review-status"><p><strong><?= qp_h($response['graded_at'] ? pu('Human awarded score','Note attribuée par un correcteur') : pu('Provisional automatic score — human review pending','Note automatique provisoire — correction humaine en attente')) ?>:</strong> <?= qp_h($response['awarded_points']) ?> / <?= qp_h($response['points']) ?></p><p><?= qp_h($response['graded_at'] ? pu('Reviewed','Corrigé') : pu('Pending review','À corriger')) ?></p></div>
                    <form
                            method="POST"
                            class="grading-form"
                        ><?php ux_csrf_field(); ?>


                            <input
                                type="hidden"
                                name="response_id"
                                value="<?= $responseId ?>"
                            >


                            <label
                                for="score-<?= $responseId ?>"
                            >
                                <?= professorH(t('h150_score_label')) ?> /
                                <?= htmlspecialchars(
                                    (string)$response[
                                        'points'
                                    ]
                                ) ?>
                            </label>


                            <input
                                type="number"
                                id="score-<?= $responseId ?>"
                                name="awarded_points"
                                min="0"
                                max="<?= htmlspecialchars(
                                    (string)$response[
                                        'points'
                                    ]
                                ) ?>"
                                step="0.01"
                                value="<?= htmlspecialchars(
                                    (string)(
                                        $response[
                                            'awarded_points'
                                        ]
                                        ?? ''
                                    )
                                ) ?>"
                                required
                            >


                            <label
                                for="comment-<?= $responseId ?>"
                            >
                                <?= professorH(t('p150i_174f4b51ea96')) ?>
                            </label>


                            <textarea
                                id="comment-<?= $responseId ?>"
                                name="trainer_comment"
                                rows="4"
                                cols="60"
                                placeholder="<?= professorH(t('p150i_4bfefe0bd5fb')) ?>"
                            ><?= htmlspecialchars(
                                $response[
                                    'trainer_comment'
                                ]
                                ?? ''
                            ) ?></textarea>


                            <div class="actions">

                                <button
                                    class="btn"
                                    type="submit"
                                    name="grade_response"
                                    value="1"
                                >
                                    <?= professorH(t('p150i_a6b41d41a08d')) ?>
                                </button>

                            </div>


                        </form>


                    <!-- ===================================== -->
                    <!-- QCM -->
                    <!-- ===================================== -->

                    <?php else: ?>


                        <h4>
                            <?= professorH(t('p150i_565d372b4dc2')) ?>
                        </h4>


                        <?php if (
                            empty($selectedChoices)
                        ): ?>

                            <p class="text-muted">
                                <?= professorH(t('h150_no_answer_selected')) ?>
                            </p>

                        <?php else: ?>


                            <ul class="selected-answers">


                                <?php foreach (
                                    $selectedChoices
                                    as $choice
                                ): ?>

                                    <li>

                                        <?= htmlspecialchars(
                                            $choice[
                                                'choice_text'
                                            ]
                                        ) ?>


                                        <?php if (
                                            (int)$choice[
                                                'is_correct'
                                            ] === 1
                                        ): ?>

                                            <span
                                                class="status
                                                status-active"
                                            >
                                                <?= professorH(t('p150i_2bd9457f65f8')) ?>
                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="status
                                                status-inactive"
                                            >
                                                <?= professorH(t('p150i_ca1b91cf317b')) ?>
                                            </span>

                                        <?php endif; ?>

                                    </li>

                                <?php endforeach; ?>


                            </ul>


                        <?php endif; ?>


                    <?php endif; ?>


                    <p class="response-score">

                        <?= professorH(t('h150_score_label')) ?>

                        <strong>

                            <?= htmlspecialchars(
                                (string)(
                                    $response[
                                        'awarded_points'
                                    ]
                                    ?? 0
                                )
                            ) ?>

                            /

                            <?= htmlspecialchars(
                                (string)$response[
                                    'points'
                                ]
                            ) ?>

                        </strong>

                    </p>


                </article>


            <?php endforeach; ?>


        <?php endif; ?>


    </section>


    <!-- ================================================= -->
    <!-- BOTTOM NAVIGATION -->
    <!-- ================================================= -->

    <nav
        class="workflow-navigation"
        aria-label="<?= professorH(t('p150i_72258823e495')) ?>"
    >

        <a
            class="btn btn-secondary"
            href="trainee_results.php?trainee_id=<?= (int)$attempt['trainee_id'] ?>"
        >
            <?= professorH(t('p150i_0a9ac1042a12')) ?>
        </a>

        <a
            class="btn btn-secondary"
            href="trainees.php"
        >
            <?= professorH(t('assigned_trainees')) ?>
        </a>

        <a
            class="btn btn-secondary"
            href="index.php"
        >
            <?= professorH(t('professor_dashboard')) ?>
        </a>

        <a
            class="btn btn-secondary"
            href="logout.php"
        >
            <?= professorH(t('logout')) ?>
        </a>

    </nav>


</div>

<div class="container"><?php require_once __DIR__.'/../includes/competency_results.php'; pu_competency_results($pdo, $attempt, $professorId); ?></div>
</body>
</html>