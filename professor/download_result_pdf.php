<?php
require_once __DIR__ . '/professor_language.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/result_pdf.php';


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

$professor =
    $stmt->fetch(PDO::FETCH_ASSOC);


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
// UPDATE PROFESSOR SESSION INFORMATION
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

    http_response_code(400);

    die(t('h150_invalid_attempt'));
}


// ======================================================
// MAIN PDF PROCESS
// ======================================================

try {


    // ==================================================
    // SECURITY: LOAD ONLY AN AUTHORIZED FINAL ATTEMPT
    //
    // Required:
    //
    // 1. Professor must be active.
    //
    //
    // 3. Attempt must belong to that trainee.
    //
    // 4. Attempt must be FINAL.
    //
    // 5. Attempt must be completed.
    //
    //
    // IMPORTANT:
    //
    // chapter_id IS NULL means the professor has access
    // to the complete theme.
    //
    // download a PDF containing the complete result.
    // ==================================================

    $stmt = $pdo->prepare(
        "SELECT DISTINCT

            a.id,
            a.trainee_id,
            a.theme_id,
            a.passation_type,
            a.started_at,
            a.completed_at,
            a.corrected_at,
            a.total_score,
            a.maximum_score,

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

           AND a.passation_type = 'final'

           AND a.completed_at IS NOT NULL

           AND EXISTS (

                SELECT 1

                FROM professor_content_assignments pca

                WHERE pca.professor_id = ?

                  AND pca.theme_id = a.theme_id AND pca.chapter_id IS NULL

                  
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


    // ==================================================
    // AUTHORIZATION FAILED
    // ==================================================

    if (!$attempt) {

        throw new Exception(
            t('p150i_8d737d2e0cfe')
        );
    }


    // ==================================================
    // SECURITY: CHECK ALL OPEN ANSWERS
    //
    // The PDF must not be generated while an open
    // question is still waiting for correction.
    //
    // We intentionally check the COMPLETE attempt.
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

    $pendingOpenAnswers =
        (int)$stmt->fetchColumn();


    if ($pendingOpenAnswers > 0) {

        throw new Exception(
            t('p150i_abb6def5fd31')
        );
    }


    // ==================================================
    // ADDITIONAL CORRECTION CHECK
    //
    // Normally corrected_at should already exist when
    // every open answer has been graded.
    //
    // We require it as an additional safety condition.
    // ==================================================

    if (empty($attempt['corrected_at'])) {

        throw new Exception(
            t('p150i_24b4681a378f')
        );
    }


    // ==================================================
    // GENERATE PDF
    //
    // We reuse the SAME generator as the Admin area.
    // ==================================================

    $pdfContent = generateResultPdf($pdo, $attemptId, static fn(string $key, string $fallback): string => t($key));


    // ==================================================
    // SAFE FILENAME
    // ==================================================

    $firstName = preg_replace(
        '/[^a-zA-Z0-9_-]/',
        '_',
        $attempt['first_name']
    );

    $lastName = preg_replace(
        '/[^a-zA-Z0-9_-]/',
        '_',
        $attempt['last_name']
    );


    $filename =
        'final_result_'
        . $firstName
        . '_'
        . $lastName
        . '_attempt_'
        . $attemptId
        . '.pdf';


    // ==================================================
    // SEND PDF
    // ==================================================

    header('Content-Type: application/pdf');


    header(
        'Content-Disposition: attachment; filename="'
        . $filename
        . '"'
    );


    header(
        'Content-Length: '
        . strlen($pdfContent)
    );


    echo $pdfContent;

    exit;


// ======================================================
// ERROR
// ======================================================

} catch (Throwable $e) {

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

        <title>
            <?= professorH(t('p150i_d961d64b433c')) ?>
        </title>

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

                <h1>
                    <?= professorH(t('p150i_3fc8d3fe52c9')) ?>
                </h1>


                <div
                    class="alert alert-error"
                    role="alert"
                >

                    <?= htmlspecialchars(
                        $e->getMessage(),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>


                <p>
                    <?= professorH(t('p150i_167a1eb8a4cd')) ?>
                </p>


                <div class="actions">

                    <a
                        class="btn btn-secondary"
                        href="attempt_details.php?id=<?= (int)$attemptId ?>"
                    >
                        <?= professorH(t('p150i_e4dc18b8a69c')) ?>
                    </a>

                    <a
                        class="btn btn-secondary"
                        href="trainees.php"
                    >
                        <?= professorH(t('assigned_trainees')) ?>
                    </a>

                </div>

            </section>


        </main>


    </div>

    </body>
    </html>

    <?php
}