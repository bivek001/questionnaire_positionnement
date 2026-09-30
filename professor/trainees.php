<?php
require_once __DIR__ . '/professor_language.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once '../config/database.php';


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
// UPDATE SESSION INFORMATION
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
// LOAD ASSIGNED TRAINEES
//
// SECURITY:
// are returned.
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

     ORDER BY
        tr.last_name ASC,
        tr.first_name ASC,
        tr.id ASC"
);

$stmt->execute([$professorId]);

$trainees =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// LOAD PERMITTED ATTEMPT STATISTICS
//
// IMPORTANT:
// An attempt is counted only when:
//
// 2. The professor has access to its theme.
//
// A theme-level assignment gives access to the theme.
// to that theme, but individual answers will later be
// filtered again by chapter.
// ======================================================

$stmt = $pdo->prepare(
    "SELECT
        a.trainee_id,

        COUNT(DISTINCT a.id)
            AS attempt_count,

        COUNT(
            DISTINCT CASE
                WHEN a.completed_at IS NOT NULL
                THEN a.id
            END
        ) AS completed_count,

        COUNT(
            DISTINCT CASE
                WHEN a.passation_type = 'final'
                 AND a.completed_at IS NOT NULL
                THEN a.id
            END
        ) AS final_count

     FROM attempts a

     INNER JOIN (SELECT DISTINCT professor_id, theme_id FROM professor_content_assignments) pta ON pta.theme_id = a.theme_id
       AND pta.professor_id = ?

     WHERE a.theme_id IS NOT NULL

       AND EXISTS (

            SELECT 1

            FROM professor_content_assignments pca

            WHERE pca.professor_id = ?

              AND pca.theme_id = a.theme_id
              AND (pca.chapter_id IS NULL OR EXISTS (SELECT 1 FROM responses sr JOIN questions sq ON sq.id=sr.question_id WHERE sr.attempt_id=a.id AND sq.theme_id=a.theme_id AND sq.chapter_id=pca.chapter_id))
       )

     GROUP BY a.trainee_id"
);

$stmt->execute([
    $professorId,
    $professorId
]);

$attemptStatisticsRows =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

$attemptStatistics = [];

foreach ($attemptStatisticsRows as $row) {

    $attemptStatistics[
        (int)$row['trainee_id']
    ] = [
        'attempt_count' =>
            (int)$row['attempt_count'],

        'completed_count' =>
            (int)$row['completed_count'],

        'final_count' =>
            (int)$row['final_count']
    ];
}


// ======================================================
// LOAD PENDING OPEN ANSWER COUNTS PER TRAINEE
//
// Both trainee permission and content permission
// are required.
// ======================================================

$stmt = $pdo->prepare(
    "SELECT
        a.trainee_id,
        COUNT(DISTINCT r.id) AS pending_count

     FROM responses r

     INNER JOIN attempts a
        ON a.id = r.attempt_id

     INNER JOIN questions q
        ON q.id = r.question_id

     INNER JOIN (SELECT DISTINCT professor_id, theme_id FROM professor_content_assignments) pta ON pta.theme_id = a.theme_id
       AND pta.professor_id = ?

     WHERE q.question_type = 'open'

       AND r.graded_at IS NULL

       AND EXISTS (

            SELECT 1

            FROM professor_content_assignments pca

            WHERE pca.professor_id = ?

              AND pca.theme_id = q.theme_id AND q.theme_id = a.theme_id AND (pca.chapter_id IS NULL OR pca.chapter_id = q.chapter_id)

              
       )

     GROUP BY a.trainee_id"
);

$stmt->execute([
    $professorId,
    $professorId
]);

$pendingRows =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

$pendingByTrainee = [];

foreach ($pendingRows as $row) {

    $pendingByTrainee[
        (int)$row['trainee_id']
    ] = (int)$row['pending_count'];
}


// ======================================================
// GLOBAL STATISTICS
// ======================================================

$totalTrainees = count($trainees);

$totalAttempts = 0;
$totalCompleted = 0;
$totalFinal = 0;
$totalPending = 0;

foreach ($trainees as $trainee) {

    $traineeId =
        (int)$trainee['id'];

    $statistics =
        $attemptStatistics[$traineeId]
        ?? [
            'attempt_count' => 0,
            'completed_count' => 0,
            'final_count' => 0
        ];

    $totalAttempts +=
        $statistics['attempt_count'];

    $totalCompleted +=
        $statistics['completed_count'];

    $totalFinal +=
        $statistics['final_count'];

    $totalPending +=
        $pendingByTrainee[$traineeId]
        ?? 0;
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
        <?= professorH(t('p150i_5ce887dee9f6')) ?>
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

        .access-note {
            margin-bottom: 24px;
            padding: 16px;
            background: #eff6ff;
            border-left: 4px solid #2563eb;
            border-radius: 8px;
        }

        .trainee-card {
            margin-bottom: 20px;
            padding: 22px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow:
                0 5px 16px rgba(15, 23, 42, 0.04);
        }

        .trainee-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
        }

        .trainee-header h2 {
            margin-top: 0;
            margin-bottom: 5px;
        }

        .trainee-id {
            color: #64748b;
            font-size: 0.9rem;
        }

        .trainee-details {
            margin: 16px 0;
            padding: 14px;
            background: #f8fafc;
            border-radius: 8px;
        }

        .trainee-details p {
            margin: 6px 0;
        }

        .trainee-stats {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(130px, 1fr)
                );
            gap: 10px;
            margin-top: 16px;
        }

        .trainee-stat {
            padding: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            text-align: center;
        }

        .trainee-stat strong {
            display: block;
            color: #1d4ed8;
            font-size: 1.3rem;
        }

        .pending-stat strong {
            color: #b45309;
        }

        .trainee-actions {
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

        @media (max-width: 700px) {

            .trainee-header {
                display: block;
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
            <?= professorH(t('trainees_results')) ?>
        </h1>

        <p>
            <?= professorH(t('p150i_02efe2c7a445')) ?>
        </p>

    </header>


    <!-- ================================================= -->
    <!-- NAVIGATION -->
    <!-- ================================================= -->

    <?php require 'professor_nav.php'; ?>


    <main><p class="actions"><a class="btn" href="manage_content.php"><?= professorH(t('manage_course_content')) ?></a> <a class="btn" href="questions.php"><?= professorH(t('manage_questions')) ?></a> <a class="btn" href="trainee_question_status.php"><?= professorH(t('trainee_questionnaire_status')) ?></a></p>


        <!-- ================================================= -->
        <!-- ACCESS NOTE -->
        <!-- ================================================= -->

        <div class="access-note">

            <strong>
                <?= professorH(t('p150i_3485cf368af6')) ?>
            </strong>

            <p>
                <?= professorH(t('p150i_a132200b4d2f')) ?>
            </p>

            <p>
                <?= professorH(t('p150i_b644e86abed9')) ?>
            </p>

        </div>


        <!-- ================================================= -->
        <!-- STATISTICS -->
        <!-- ================================================= -->

        <div class="stat-grid">


            <div class="stat-card">

                <span class="stat-number">
                    <?= $totalTrainees ?>
                </span>

                <?= professorH(t('assigned_trainees')) ?>

            </div>


            <div class="stat-card">

                <span class="stat-number">
                    <?= $totalAttempts ?>
                </span>

                <?= professorH(t('permitted_attempts')) ?>

            </div>


            <div class="stat-card">

                <span class="stat-number">
                    <?= $totalCompleted ?>
                </span>

                <?= professorH(t('completed_attempts')) ?>

            </div>


            <div class="stat-card">

                <span class="stat-number">
                    <?= $totalFinal ?>
                </span>

                <?= professorH(t('p150i_360a5c1086f1')) ?>

            </div>


            <div class="stat-card">

                <span class="stat-number">
                    <?= $totalPending ?>
                </span>

                <?= professorH(t('p150i_3e3d8aa70755')) ?>

            </div>


        </div>


        <!-- ================================================= -->
        <!-- TRAINEES -->
        <!-- ================================================= -->

        <?php if (empty($trainees)): ?>


            <div class="empty-state">

                <h2>
                    <?= professorH(t('p150i_991854817e9a')) ?>
                </h2>

                <p>
                    <?= professorH(t('p150i_5abfb1648628')) ?>
                </p>

            </div>


        <?php else: ?>


            <?php foreach ($trainees as $trainee): ?>


                <?php

                $traineeId =
                    (int)$trainee['id'];

                $statistics =
                    $attemptStatistics[$traineeId]
                    ?? [
                        'attempt_count' => 0,
                        'completed_count' => 0,
                        'final_count' => 0
                    ];

                $pendingCount =
                    $pendingByTrainee[$traineeId]
                    ?? 0;

                ?>


                <article class="trainee-card">


                    <div class="trainee-header">

                        <div>

                            <h2>
                                <?= htmlspecialchars(
                                    $trainee['first_name']
                                    . ' '
                                    . $trainee['last_name']
                                ) ?>
                            </h2>

                            <div class="trainee-id">

                                <?= professorH(t('p150i_5518894aaecc')) ?>
                                <?= $traineeId ?>

                            </div>

                        </div>

                    </div>


                    <!-- ============================= -->
                    <!-- TRAINEE INFORMATION -->
                    <!-- ============================= -->

                    <div class="trainee-details">

                        <p>

                            <strong>
                                <?= professorH(t('h150_email')) ?>
                            </strong>

                            <?= htmlspecialchars(
                                $trainee['email']
                            ) ?>

                        </p>


                        <?php if (
                            !empty(
                                $trainee['date_of_birth']
                            )
                        ): ?>

                            <p>

                                <strong>
                                    <?= professorH(t('p150i_992d2fa8bf7e')) ?>
                                </strong>

                                <?= htmlspecialchars(
                                    $trainee['date_of_birth']
                                ) ?>

                            </p>

                        <?php endif; ?>


                        <p>

                            <strong>
                                <?= professorH(t('h150_assigned_label')) ?>
                            </strong>

                            <?= htmlspecialchars(
                                $trainee['assigned_at']
                            ) ?>

                        </p>

                    </div>


                    <!-- ============================= -->
                    <!-- TRAINEE STATISTICS -->
                    <!-- ============================= -->

                    <div class="trainee-stats">


                        <div class="trainee-stat">

                            <strong>
                                <?= $statistics[
                                    'attempt_count'
                                ] ?>
                            </strong>

                            <?= professorH(t('attempts')) ?>

                        </div>


                        <div class="trainee-stat">

                            <strong>
                                <?= $statistics[
                                    'completed_count'
                                ] ?>
                            </strong>

                            <?= professorH(t('completed')) ?>

                        </div>


                        <div class="trainee-stat">

                            <strong>
                                <?= $statistics[
                                    'final_count'
                                ] ?>
                            </strong>

                            <?= professorH(t('final')) ?>

                        </div>


                        <div
                            class="trainee-stat
                            pending-stat"
                        >

                            <strong>
                                <?= $pendingCount ?>
                            </strong>

                            <?= professorH(t('p150i_5aace1f8a0ba')) ?>

                        </div>


                    </div>


                    <!-- ============================= -->
                    <!-- ACTION -->
                    <!-- ============================= -->

                    <div class="trainee-actions">

                        <?php if (
                            $statistics[
                                'attempt_count'
                            ] > 0
                        ): ?>

                            <a
                                class="btn"
                                href="trainee_results.php?trainee_id=<?= $traineeId ?>"
                            >
                                <?= professorH(t('p150i_17165cca344e')) ?>
                            </a>

                        <?php else: ?>

                            <span class="text-muted">
                                <?= professorH(t('p150i_505bab432145')) ?>
                            </span>

                        <?php endif; ?>

                    </div>


                </article>


            <?php endforeach; ?>


        <?php endif; ?>


    </main>


    <footer>

        <p class="actions">

            <a
                class="btn btn-secondary"
                href="index.php"
            >
                <?= professorH(t('p150i_2c5f7ede2b1b')) ?>
            </a>

        </p>

    </footer>


</div>

</body>
</html>