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
// VERIFY PROFESSOR STILL EXISTS AND IS ACTIVE
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
// ASSIGNED COURSE CONTENT COUNT
// ======================================================

$stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM professor_content_assignments
     WHERE professor_id = ?"
);

$stmt->execute([$professorId]);

$contentAssignmentCount =
    (int)$stmt->fetchColumn();


// ======================================================
// ASSIGNED TRAINEE COUNT
// ======================================================

$stmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT a.trainee_id) FROM attempts a JOIN professor_content_assignments p ON p.theme_id=a.theme_id WHERE p.professor_id = ?"
);

$stmt->execute([$professorId]);

$traineeCount =
    (int)$stmt->fetchColumn();


// ======================================================
// ATTEMPTS FROM ASSIGNED TRAINEES
// ======================================================

$stmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT a.id)

     FROM attempts a

     INNER JOIN (SELECT DISTINCT professor_id, theme_id FROM professor_content_assignments) pta ON pta.theme_id = a.theme_id

     WHERE pta.professor_id = ?"
);

$stmt->execute([$professorId]);

$attemptCount =
    (int)$stmt->fetchColumn();


// ======================================================
// OPEN ANSWERS WAITING FOR GRADING
//
// Professor must:
// 2. Have access to the question's theme/chapter.
// ======================================================

$stmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT r.id)

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

              AND pca.theme_id = q.theme_id

              
       )"
);

$stmt->execute([
    $professorId,
    $professorId
]);

$pendingOpenAnswers =
    (int)$stmt->fetchColumn();

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
        <?= professorH(t('professor_dashboard')) ?>
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

        .professor-header p {
            margin-bottom: 6px;
        }

        .professor-dashboard-grid {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(210px, 1fr)
                );
            gap: 18px;
            margin: 24px 0 32px;
        }

        .professor-stat-card {
            padding: 22px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow:
                0 6px 20px rgba(15, 23, 42, 0.05);
        }

        .professor-stat-card h3 {
            margin-top: 0;
            color: #475569;
            font-size: 1rem;
        }

        .professor-stat-number {
            margin-top: 10px;
            color: #1d4ed8;
            font-size: 2rem;
            font-weight: 700;
        }

        .professor-section {
            margin-bottom: 22px;
            padding: 24px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
        }

        .professor-section h2 {
            margin-top: 0;
        }

        .dashboard-actions {
            margin-top: 16px;
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
            <?= professorH(t('professor_dashboard')) ?>
        </h1>

        <p>
            <?= professorH(t('p150i_b1371e9b0601')) ?>
            <strong>
                <?= htmlspecialchars(
                    $professor['first_name']
                    . ' '
                    . $professor['last_name']
                ) ?>
            </strong>
        </p>

        <p>
            <?= htmlspecialchars(
                $professor['email']
            ) ?>
        </p>

    </header>


    <!-- ================================================= -->
    <!-- SHARED PROFESSOR NAVIGATION -->
    <!-- ================================================= -->

    <?php require 'professor_nav.php'; ?>


    <main><p class="actions"><a class="btn" href="themes.php"><?= professorH(t('ux_create_theme')) ?></a><a class="btn" href="manage_content.php"><?= professorH(t('manage_course_content')) ?></a> <a class="btn" href="questions.php"><?= professorH(t('manage_questions')) ?></a> <a class="btn" href="trainee_question_status.php"><?= professorH(t('trainee_questionnaire_status')) ?></a></p>


        <!-- ================================================= -->
        <!-- INTRODUCTION -->
        <!-- ================================================= -->

        <section>

            <h2>
                <?= professorH(t('p150i_d4b1ea5708dd')) ?>
            </h2>

            <p>
                <?= professorH(t('p150i_d8bbb72d754b')) ?>
            </p>

        </section>


        <!-- ================================================= -->
        <!-- STATISTICS -->
        <!-- ================================================= -->

        <div class="professor-dashboard-grid">


            <div class="professor-stat-card">

                <h3>
                    <?= professorH(t('content_assignments')) ?>
                </h3>

                <div class="professor-stat-number">
                    <?= $contentAssignmentCount ?>
                </div>

            </div>


            <div class="professor-stat-card">

                <h3>
                    <?= professorH(t('assigned_trainees')) ?>
                </h3>

                <div class="professor-stat-number">
                    <?= $traineeCount ?>
                </div>

            </div>


            <div class="professor-stat-card">

                <h3>
                    <?= professorH(t('p150i_d1fb6ea6ea5b')) ?>
                </h3>

                <div class="professor-stat-number">
                    <?= $attemptCount ?>
                </div>

            </div>


            <div class="professor-stat-card">

                <h3>
                    <?= professorH(t('p150i_3e3d8aa70755')) ?>
                </h3>

                <div class="professor-stat-number">
                    <?= $pendingOpenAnswers ?>
                </div>

            </div>


        </div>


        <!-- ================================================= -->
        <!-- COURSE CONTENT -->
        <!-- ================================================= -->

        <section class="professor-section">

            <h2>
                <?= professorH(t('course_content')) ?>
            </h2>

            <?php if ($contentAssignmentCount > 0): ?>

                <p>
                    <?= professorH(t('p150i_e01002934b94')) ?>
                    <strong>
                        <?= $contentAssignmentCount ?>
                    </strong>
                    <?= professorH(t('p150i_165ba087aefb')) ?>
                </p>

                <p>
                    <?= professorH(t('p150i_fe211eafdf55')) ?>
                </p>

                <p class="dashboard-actions">

                    <a
                        class="btn"
                        href="content.php"
                    >
                        <?= professorH(t('p150i_51f0c569d116')) ?>
                    </a>

                </p>

            <?php else: ?>

                <p>
                    <?= professorH(t('p150i_ab3204876c58')) ?>
                </p>

            <?php endif; ?>

        </section>


        <!-- ================================================= -->
        <!-- TRAINEES -->
        <!-- ================================================= -->

        <section class="professor-section">

            <h2>
                <?= professorH(t('assigned_trainees')) ?>
            </h2>

            <?php if ($traineeCount > 0): ?>

                <p>
                    <?= professorH(t('p150i_e01002934b94')) ?>
                    <strong>
                        <?= $traineeCount ?>
                    </strong>
                    <?= professorH(t('p150i_eb61f51bd92c')) ?>
                </p>

                <p>
                    <?= professorH(t('p150i_0d1dd872603a')) ?>
                </p>

                <p class="dashboard-actions">

                    <a
                        class="btn"
                        href="trainees.php"
                    >
                        <?= professorH(t('p150i_585b34fc0afc')) ?>
                    </a>

                </p>

            <?php else: ?>

                <p>
                    <?= professorH(t('p150i_b2df287c7904')) ?>
                </p>

            <?php endif; ?>

        </section>


        <!-- ================================================= -->
        <!-- OPEN ANSWERS -->
        <!-- ================================================= -->

        <section class="professor-section">

            <h2>
                <?= professorH(t('p150i_88aa722bf6d3')) ?>
            </h2>

            <?php if ($pendingOpenAnswers > 0): ?>

                <p>
                    <?= professorH(t('p150i_af3f12e161fd')) ?>
                    <strong>
                        <?= $pendingOpenAnswers ?>
                    </strong>
                    <?= professorH(t('p150i_7e8f71734c28')) ?>
                </p>

                <p>
                    <?= professorH(t('p150i_687613de5ad9')) ?>
                </p>

            <?php else: ?>

                <p>
                    <?= professorH(t('p150i_6926798fdb96')) ?>
                </p>

            <?php endif; ?>

        </section>


        <!-- ================================================= -->
        <!-- ACCESS CONTROL -->
        <!-- ================================================= -->

        <section class="professor-section">

            <h2>
                <?= professorH(t('p150i_c3ceee351e98')) ?>
            </h2>

            <p>
                <?= professorH(t('p150i_d1d93e601c3c')) ?>
            </p>

            <p>
                <?= professorH(t('p150i_66b1becef34c')) ?>
            </p>

            <p>
                <?= professorH(t('p150i_6a3cc10b3e81')) ?>
            </p>

        </section>


    </main>


    <footer>

        <p class="actions">

            <a
                class="btn btn-secondary"
                href="logout.php"
            >
                <?= professorH(t('logout')) ?>
            </a>

        </p>

    </footer>


</div>

</body>
</html>