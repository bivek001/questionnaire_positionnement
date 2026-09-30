<?php
require_once __DIR__ . "/../includes/course_access.php";
require_once __DIR__ . '/professor_language.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once '../config/database.php';
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
// GET TRAINEE ID
// ======================================================

$traineeId =
    isset($_GET['trainee_id'])
        ? (int)$_GET['trainee_id']
        : 0;

if ($traineeId <= 0) {
    header('Location: trainees.php');
    exit;
}


// ======================================================
// SECURITY: VERIFY TRAINEE ASSIGNMENT
//
// We do NOT load a trainee simply because their ID was
// supplied in the URL.
//
// currently logged-in professor.
// ======================================================

$stmt = $pdo->prepare(
    "SELECT
        tr.id,
        tr.first_name,
        tr.last_name,
        tr.email,
        tr.date_of_birth,
        tr.created_at AS assigned_at
 FROM trainees tr WHERE EXISTS (SELECT 1 FROM attempts ca JOIN professor_content_assignments cp ON cp.theme_id=ca.theme_id WHERE ca.trainee_id=tr.id AND cp.professor_id=? AND (cp.chapter_id IS NULL OR EXISTS (SELECT 1 FROM responses sr JOIN questions sq ON sq.id=sr.question_id WHERE sr.attempt_id=ca.id AND sq.theme_id=ca.theme_id AND sq.chapter_id=cp.chapter_id)))
       AND tr.id = ?

     LIMIT 1"
);

$stmt->execute([
    $professorId,
    $traineeId
]);

$trainee =
    $stmt->fetch(PDO::FETCH_ASSOC);


// ======================================================
// ACCESS DENIED IF TRAINEE IS NOT ASSIGNED
// ======================================================

if (!$trainee) {

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
            <?= professorH(t('p150i_d134ca025a6c')) ?>
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

            <section>

                <h1>
                    <?= professorH(t('p150i_d134ca025a6c')) ?>
                </h1>

                <p>
                    <?= professorH(t('p150i_6c431d0a2d59')) ?>
                </p>

                <p>
                    <a
                        class="btn btn-secondary"
                        href="trainees.php"
                    >
                        <?= professorH(t('p150i_8a540c682640')) ?>
                    </a>
                </p>

            </section>

        </main>

    </div>

    </body>

    </html>

    <?php

    exit;
}


// ======================================================
// LOAD PERMITTED ATTEMPTS
//
// SECURITY:
// Two permissions are required:
//
// 2. The professor has content permission for the
//    attempt's theme.
//
// Detailed question/answer access will be checked again
// at question/chapter level on attempt_details.php.
// ======================================================

$stmt = $pdo->prepare(
    "SELECT DISTINCT

        a.id,
        a.trainee_id,
        a.theme_id,
        a.passation_type,
        a.started_at,
        a.completed_at,
        a.corrected_at,
        a.result_sent_at,
        a.result_sent_to,
        a.total_score,
        a.maximum_score,

        t.name AS theme_name

     FROM attempts a

     INNER JOIN themes t
        ON t.id = a.theme_id

     INNER JOIN (SELECT DISTINCT professor_id, theme_id FROM professor_content_assignments) pta ON pta.theme_id = a.theme_id
       AND pta.professor_id = ?

     WHERE a.trainee_id = ?

       AND EXISTS (

            SELECT 1

            FROM professor_content_assignments pca

            WHERE pca.professor_id = ?

              AND pca.theme_id = a.theme_id
              AND (pca.chapter_id IS NULL OR EXISTS (SELECT 1 FROM responses sr JOIN questions sq ON sq.id=sr.question_id WHERE sr.attempt_id=a.id AND sq.theme_id=a.theme_id AND sq.chapter_id=pca.chapter_id))
       )

     ORDER BY
        a.started_at DESC,
        a.id DESC"
);

$stmt->execute([
    $professorId,
    $traineeId,
    $professorId
]);

$attempts =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// PREPARE ATTEMPT INFORMATION
// ======================================================

foreach ($attempts as &$attempt) {

    $attempt = qp_scope_attempt($pdo, $professorId, $attempt);
    $attempt['percentage'] = null;
    $attempt['positioning_level'] = null;

    if (
        $attempt['total_score'] !== null
        && $attempt['maximum_score'] !== null
        && (float)$attempt['maximum_score'] > 0
    ) {

        $percentage =
            (
                (float)$attempt['total_score']
                /
                (float)$attempt['maximum_score']
            ) * 100;

        $attempt['percentage'] =
            round($percentage, 2);

        /*
         * Existing project helper.
         *
         * The helper is used only when the attempt has
         * a calculable percentage.
         */
        $attempt['positioning_level'] =
            ($attempt['whole_theme_access'] ? getPositioningLevel($pdo, $percentage, t('h150_not_defined')) : t('pkg_scope_result'));
    }
}

unset($attempt);


// ======================================================
// STATISTICS
// ======================================================

$totalAttempts = count($attempts);

$completedAttempts = 0;
$correctedAttempts = 0;
$finalAttempts = 0;

foreach ($attempts as $attempt) {

    if (!empty($attempt['completed_at'])) {
        $completedAttempts++;
    }

    if (!empty($attempt['corrected_at'])) {
        $correctedAttempts++;
    }

    if ($attempt['passation_type'] === 'final') {
        $finalAttempts++;
    }
}

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
        <?= professorH(t('p150i_bfcc39a6a126')) ?>
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

        .trainee-summary {
            margin-bottom: 24px;
            padding: 20px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
        }

        .trainee-summary h2 {
            margin-top: 0;
        }

        .stat-grid {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(160px, 1fr)
                );
            gap: 14px;
            margin: 24px 0;
        }

        .stat-card {
            padding: 18px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            text-align: center;
        }

        .stat-number {
            display: block;
            margin-bottom: 5px;
            color: #1d4ed8;
            font-size: 1.7rem;
            font-weight: 700;
        }

        .attempt-card {
            margin-bottom: 20px;
            padding: 22px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow:
                0 5px 16px rgba(15, 23, 42, 0.04);
        }

        .attempt-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
        }

        .attempt-header h3 {
            margin-top: 0;
            margin-bottom: 6px;
        }

        .attempt-type {
            display: inline-block;
            padding: 5px 10px;
            background: #dbeafe;
            color: #1d4ed8;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .attempt-details {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(190px, 1fr)
                );
            gap: 12px;
            margin-top: 18px;
        }

        .attempt-detail {
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
        }

        .attempt-detail strong {
            display: block;
            margin-bottom: 4px;
            color: #475569;
            font-size: 0.85rem;
        }

        .status-completed {
            color: #166534;
            font-weight: 700;
        }

        .status-pending {
            color: #b45309;
            font-weight: 700;
        }

        .attempt-actions {
            margin-top: 18px;
        }

        .empty-state {
            padding: 30px;
            color: #64748b;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            text-align: center;
        }

        .security-note {
            margin-bottom: 24px;
            padding: 16px;
            background: #eff6ff;
            border-left: 4px solid #2563eb;
            border-radius: 8px;
        }

        @media (max-width: 700px) {

            .attempt-header {
                display: block;
            }

            .attempt-type {
                margin-top: 10px;
            }
        }

    </style>

<script src="../assets/js/professor_language.js" defer></script>
<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>

<body>

<div class="container">


    <!-- ================================================= -->
    <!-- HEADER -->
    <!-- ================================================= -->

    <header class="professor-header">

        <h1>
            <?= professorH(t('p150i_bfcc39a6a126')) ?>
        </h1>

        <p>
            <?= professorH(t('p150i_eecc1e83f3df')) ?>
        </p>

    </header>


    <!-- ================================================= -->
    <!-- NAVIGATION -->
    <!-- ================================================= -->

    <?php require 'professor_nav.php'; ?>


    <main>


        <!-- ================================================= -->
        <!-- TRAINEE -->
        <!-- ================================================= -->

        <section class="trainee-summary">

            <h2>
                <?= htmlspecialchars(
                    $trainee['first_name']
                    . ' '
                    . $trainee['last_name']
                ) ?>
            </h2>

            <p>
                <strong><?= professorH(t('h150_email')) ?></strong>
                <?= htmlspecialchars(
                    $trainee['email']
                ) ?>
            </p>

            <?php if (
                !empty($trainee['date_of_birth'])
            ): ?>

                <p>
                    <strong><?= professorH(t('p150i_992d2fa8bf7e')) ?></strong>
                    <?= htmlspecialchars(
                        $trainee['date_of_birth']
                    ) ?>
                </p>

            <?php endif; ?>

            <p>
                <strong><?= professorH(t('p150i_5518894aaecc')) ?></strong>
                <?= (int)$trainee['id'] ?>
            </p>

        </section>


        <!-- ================================================= -->
        <!-- SECURITY -->
        <!-- ================================================= -->

        <div class="security-note">

            <strong>
                <?= professorH(t('p150i_842636cf122d')) ?>
            </strong>

            <p>
                <?= professorH(t('p150i_aad976379920')) ?>
            </p>

            <p>
                <?= professorH(t('p150i_c8e4651712da')) ?>
            </p>

        </div>


        <!-- ================================================= -->
        <!-- STATISTICS -->
        <!-- ================================================= -->

        <div class="stat-grid">

            <div class="stat-card">

                <span class="stat-number">
                    <?= $totalAttempts ?>
                </span>

                <?= professorH(t('permitted_attempts')) ?>

            </div>


            <div class="stat-card">

                <span class="stat-number">
                    <?= $completedAttempts ?>
                </span>

                <?= professorH(t('completed')) ?>

            </div>


            <div class="stat-card">

                <span class="stat-number">
                    <?= $correctedAttempts ?>
                </span>

                <?= professorH(t('corrected')) ?>

            </div>


            <div class="stat-card">

                <span class="stat-number">
                    <?= $finalAttempts ?>
                </span>

                <?= professorH(t('final')) ?>

            </div>

        </div>


        <!-- ================================================= -->
        <!-- ATTEMPTS -->
        <!-- ================================================= -->

        <?php if (empty($attempts)): ?>


            <div class="empty-state">

                <h2>
                    <?= professorH(t('p150i_f4767c3d35e6')) ?>
                </h2>

                <p>
                    <?= professorH(t('p150i_6e317811a664')) ?>
                </p>

            </div>


        <?php else: ?>


            <?php foreach ($attempts as $attempt): ?>


                <?php

                $typeLabel =
                    match (
                        $attempt['passation_type']
                    ) {
                        'initial' => t('initial'),
                        'final' => t('final'),
                        'sortie' => t('exit'),
                        default =>
                            ucfirst(
                                $attempt['passation_type']
                            )
                    };

                ?>


                <article class="attempt-card">


                    <div class="attempt-header">

                        <div>

                            <h3>
                                <?= htmlspecialchars(
                                    $attempt['theme_name']
                                ) ?>
                            </h3>

                            <span class="attempt-type">

                                <?= htmlspecialchars(
                                    $typeLabel
                                ) ?>

                            </span>

                        </div>

                        <div>

                            <?= professorH(t('p150i_513c2cb7aff4')) ?><?= (int)$attempt['id'] ?>

                        </div>

                    </div>


                    <div class="attempt-details">


                        <div class="attempt-detail">

                            <strong>
                                <?= professorH(t('p150i_ecbc89cd37a0')) ?>
                            </strong>

                            <?= htmlspecialchars(
                                $attempt['started_at']
                                ?? '—'
                            ) ?>

                        </div>


                        <div class="attempt-detail">

                            <strong>
                                <?= professorH(t('p150i_557fe004f344')) ?>
                            </strong>

                            <?php if (
                                !empty(
                                    $attempt['completed_at']
                                )
                            ): ?>

                                <span class="status-completed">
                                    <?= professorH(t('completed')) ?>
                                </span>

                            <?php else: ?>

                                <span class="status-pending">
                                    <?= professorH(t('p150i_c1f88e9d6c41')) ?>
                                </span>

                            <?php endif; ?>

                        </div>


                        <div class="attempt-detail">

                            <strong>
                                <?= professorH(t('correction')) ?>
                            </strong>

                            <?php if (
                                !empty(
                                    $attempt['corrected_at']
                                )
                            ): ?>

                                <span class="status-completed">
                                    <?= professorH(t('corrected')) ?>
                                </span>

                            <?php else: ?>

                                <span class="status-pending">
                                    <?= professorH(t('pending')) ?>
                                </span>

                            <?php endif; ?>

                        </div>


                        <div class="attempt-detail">

                            <strong>
                                <?= professorH(t('score')) ?>
                            </strong>

                            <?php if (
                                $attempt['total_score']
                                !== null
                                && $attempt['maximum_score']
                                !== null
                            ): ?>

                                <?= htmlspecialchars(
                                    (string)$attempt[
                                        'total_score'
                                    ]
                                ) ?>

                                /

                                <?= htmlspecialchars(
                                    (string)$attempt[
                                        'maximum_score'
                                    ]
                                ) ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </div>


                        <div class="attempt-detail">

                            <strong>
                                <?= professorH(t('percentage')) ?>
                            </strong>

                            <?php if (
                                $attempt['percentage']
                                !== null
                            ): ?>

                                <?= number_format(
                                    $attempt['percentage'],
                                    2
                                ) ?>%

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </div>


                        <div class="attempt-detail">

                            <strong>
                                <?= professorH(t('positioning_level')) ?>
                            </strong>

                            <?php if (
                                !empty(
                                    $attempt[
                                        'positioning_level'
                                    ]
                                )
                            ): ?>

                                <?= htmlspecialchars(
                                    $attempt[
                                        'positioning_level'
                                    ]
                                ) ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </div>


                    </div>


                    <div class="attempt-actions">

                        <a
                            class="btn"
                            href="attempt_details.php?id=<?= (int)$attempt['id'] ?>"
                        >
                            <?= professorH(t('view_attempt')) ?>
                        </a>

                    </div>


                </article>


            <?php endforeach; ?>


        <?php endif; ?>


    </main>


    <footer>

        <p class="actions">

            <a
                class="btn btn-secondary"
                href="trainees.php"
            >
                <?= professorH(t('p150i_340d1c4ac7cc')) ?>
            </a>

        </p>

    </footer>


</div>

</body>
</html>