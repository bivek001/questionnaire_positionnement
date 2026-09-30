<?php
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/admin_language.php';

require_once 'auth.php';
require_once '../config/database.php';


// ======================================================
// DASHBOARD STATISTICS
// ======================================================


// ------------------------------------------------------
// THEMES
// ------------------------------------------------------

$stmt = $pdo->query(
    "SELECT COUNT(*)
     FROM themes"
);

$totalThemes = (int)$stmt->fetchColumn();


$stmt = $pdo->query(
    "SELECT COUNT(*)
     FROM themes
     WHERE is_active = 1"
);

$activeThemes = (int)$stmt->fetchColumn();


// ------------------------------------------------------
// QUESTIONS
// ------------------------------------------------------

$stmt = $pdo->query(
    "SELECT COUNT(*)
     FROM questions"
);

$totalQuestions = (int)$stmt->fetchColumn();


$stmt = $pdo->query(
    "SELECT COUNT(*)
     FROM questions
     WHERE is_active = 1"
);

$activeQuestions = (int)$stmt->fetchColumn();


// ------------------------------------------------------
// TRAINEES
// ------------------------------------------------------

$stmt = $pdo->query(
    "SELECT COUNT(*)
     FROM trainees"
);

$totalTrainees = (int)$stmt->fetchColumn();


// ------------------------------------------------------
// PROFESSORS
// ------------------------------------------------------

$stmt = $pdo->query(
    "SELECT COUNT(*)
     FROM professors"
);

$totalProfessors = (int)$stmt->fetchColumn();


$stmt = $pdo->query(
    "SELECT COUNT(*)
     FROM professors
     WHERE is_active = 1"
);

$activeProfessors = (int)$stmt->fetchColumn();


$stmt = $pdo->query(
    "SELECT COUNT(*)
     FROM professors
     WHERE is_active = 0"
);

$inactiveProfessors = (int)$stmt->fetchColumn();


// ------------------------------------------------------
// PROFESSOR CONTENT ASSIGNMENTS
// ------------------------------------------------------

$stmt = $pdo->query(
    "SELECT COUNT(*)
     FROM professor_content_assignments"
);

$totalProfessorContentAssignments =
    (int)$stmt->fetchColumn();


// ------------------------------------------------------
// PROFESSOR / TRAINEE ASSIGNMENTS
// ------------------------------------------------------

$stmt = $pdo->query(
    "SELECT COUNT(*)
     FROM professor_trainee_assignments"
);

$totalProfessorTraineeAssignments =
    (int)$stmt->fetchColumn();


// ------------------------------------------------------
// ATTEMPTS
// ------------------------------------------------------

$stmt = $pdo->query(
    "SELECT COUNT(*)
     FROM attempts"
);

$totalAttempts = (int)$stmt->fetchColumn();


// ------------------------------------------------------
// PENDING QUESTIONNAIRE ASSIGNMENTS
// ------------------------------------------------------

$stmt = $pdo->query(
    "SELECT COUNT(*)
     FROM questionnaire_assignments
     WHERE status = 'pending'"
);

$pendingAssignments = (int)$stmt->fetchColumn();


// ------------------------------------------------------
// COMPLETED QUESTIONNAIRE ASSIGNMENTS
// ------------------------------------------------------

$stmt = $pdo->query(
    "SELECT COUNT(*)
     FROM questionnaire_assignments
     WHERE status = 'completed'"
);

$completedAssignments = (int)$stmt->fetchColumn();


// ------------------------------------------------------
// OPEN ANSWERS WAITING FOR GRADING
// ------------------------------------------------------

$stmt = $pdo->query(
    "SELECT COUNT(*)

     FROM responses

     INNER JOIN questions
        ON questions.id = responses.question_id

     WHERE questions.question_type = 'open'

       AND responses.graded_at IS NULL"
);

$pendingOpenAnswers =
    (int)$stmt->fetchColumn();


// ------------------------------------------------------
// COMPLETED / FULLY CORRECTED ATTEMPTS
// ------------------------------------------------------

$stmt = $pdo->query(
    "SELECT COUNT(*)

     FROM attempts

     WHERE completed_at IS NOT NULL

       AND NOT EXISTS (

            SELECT 1

            FROM responses

            INNER JOIN questions
                ON questions.id =
                   responses.question_id

            WHERE responses.attempt_id =
                  attempts.id

              AND questions.question_type =
                  'open'

              AND responses.graded_at IS NULL
       )"
);

$completedAttempts =
    (int)$stmt->fetchColumn();


// ======================================================
// PROFESSOR SUPERVISION LIST
// ======================================================

$stmt = $pdo->query(
    "SELECT

        p.id,
        p.first_name,
        p.last_name,
        p.email,
        p.username,
        p.is_active,

        (
            SELECT COUNT(*)

            FROM professor_content_assignments pca

            WHERE pca.professor_id = p.id

        ) AS content_assignment_count,

        (
            SELECT COUNT(*)

            FROM professor_trainee_assignments pta

            WHERE pta.professor_id = p.id

        ) AS trainee_assignment_count

     FROM professors p

     ORDER BY
        p.is_active DESC,
        p.last_name ASC,
        p.first_name ASC,
        p.id ASC"
);

$professors =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// RECENT QUESTIONNAIRE ASSIGNMENTS
// ======================================================

$stmt = $pdo->query(
    "SELECT

        qa.id,
        qa.passation_type,
        qa.status,
        qa.assigned_at,
        qa.completed_at,
        qa.attempt_id,

        tr.id AS trainee_id,
        tr.first_name,
        tr.last_name,

        t.id AS theme_id,
        t.name AS theme_name

     FROM questionnaire_assignments qa

     INNER JOIN trainees tr
        ON tr.id = qa.trainee_id

     INNER JOIN themes t
        ON t.id = qa.theme_id

     ORDER BY qa.id DESC

     LIMIT 10"
);

$recentAssignments =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// RECENT ATTEMPTS
// ======================================================

$stmt = $pdo->query(
    "SELECT

        a.id,
        a.passation_type,
        a.started_at,
        a.completed_at,
        a.corrected_at,
        a.total_score,
        a.maximum_score,

        tr.id AS trainee_id,
        tr.first_name,
        tr.last_name,

        t.name AS theme_name

     FROM attempts a

     INNER JOIN trainees tr
        ON tr.id = a.trainee_id

     LEFT JOIN themes t
        ON t.id = a.theme_id

     ORDER BY a.id DESC

     LIMIT 10"
);

$recentAttempts =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="<?= adminH(htmlLanguage()) ?>">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= adminH(t('admin_dashboard_link')) ?>
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <style>

        .admin-dashboard .dashboard-grid {

            display: grid;

            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(280px, 1fr)
                );

            gap: 1rem;

        }


        .admin-dashboard .supervision-table {

            width: 100%;

            border-collapse: collapse;

            margin-top: 1rem;

        }


        .admin-dashboard .supervision-table th,
        .admin-dashboard .supervision-table td {

            padding: 0.75rem;

            border-bottom: 1px solid #dbe3ea;

            text-align: left;

            vertical-align: top;

        }


        .admin-dashboard .supervision-table th {

            font-weight: 700;

        }


        .admin-dashboard .table-wrapper {

            width: 100%;

            overflow-x: auto;

        }


        .admin-dashboard .status-active {

            font-weight: 700;

            color: #166534;

        }


        .admin-dashboard .status-inactive {

            font-weight: 700;

            color: #991b1b;

        }


        .admin-dashboard .status-pending {

            font-weight: 700;

            color: #92400e;

        }


        .admin-dashboard .status-completed {

            font-weight: 700;

            color: #166534;

        }


        .admin-dashboard .quick-actions {

            display: flex;

            flex-wrap: wrap;

            gap: 0.75rem;

            margin-top: 1rem;

        }


        .admin-dashboard .dashboard-section {

            margin-bottom: 1.5rem;

        }


        .admin-dashboard .small-text {

            font-size: 0.9rem;

        }


        .admin-dashboard .muted {

            color: #64748b;

        }

    </style>

<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>

<body class="admin-dashboard">
<?php require __DIR__ . '/admin_language_switcher.php'; ?>

<div class="container">


    <!-- ================================================= -->
    <!-- HEADER -->
    <!-- ================================================= -->

    <header class="admin-header">

        <h1>
            <?= adminH(t('p150i_7b3acab7d31d')) ?>
        </h1>

        <p>
            <?= adminH(t('a150j_31cb025821f4')) ?>
        </p>

        <p>

            <?= adminH(t('h150_logged_in_as')) ?>

            <strong>

                <?= htmlspecialchars(
                    $_SESSION['admin_username']
                    ?? t('admin'),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </strong>

        </p>

    </header>


    <!-- ================================================= -->
    <!-- SHARED ADMIN NAVIGATION -->
    <!-- ================================================= -->

    <?php require 'admin_nav.php'; ?>


    <main>


        <!-- ================================================= -->
        <!-- DASHBOARD INTRODUCTION -->
        <!-- ================================================= -->

        <section>

            <h2>
                <?= adminH(t('a150j_90b9f01c8011')) ?>
            </h2>

            <p>
                <?= adminH(t('a150j_65ba27e886cd')) ?>
            </p>

            <p>
                <?= adminH(t('a150j_0ca55e86adf5')) ?>
            </p>

        </section>


        <!-- ================================================= -->
        <!-- SYSTEM STATISTICS -->
        <!-- ================================================= -->

        <section>

            <h2>
                <?= adminH(t('system_statistics')) ?>
            </h2>

            <div class="stats-grid">


                <div class="stat-card">

                    <h3>
                        <?= adminH(t('total_themes')) ?>
                    </h3>

                    <div class="stat-number">
                        <?= $totalThemes ?>
                    </div>

                </div>


                <div class="stat-card">

                    <h3>
                        <?= adminH(t('active_themes')) ?>
                    </h3>

                    <div class="stat-number">
                        <?= $activeThemes ?>
                    </div>

                </div>


                <div class="stat-card">

                    <h3>
                        <?= adminH(t('total_questions')) ?>
                    </h3>

                    <div class="stat-number">
                        <?= $totalQuestions ?>
                    </div>

                </div>


                <div class="stat-card">

                    <h3>
                        <?= adminH(t('active_questions')) ?>
                    </h3>

                    <div class="stat-number">
                        <?= $activeQuestions ?>
                    </div>

                </div>


                <div class="stat-card">

                    <h3>
                        <?= adminH(t('total_trainees')) ?>
                    </h3>

                    <div class="stat-number">
                        <?= $totalTrainees ?>
                    </div>

                </div>


                <div class="stat-card">

                    <h3>
                        <?= adminH(t('total_professors')) ?>
                    </h3>

                    <div class="stat-number">
                        <?= $totalProfessors ?>
                    </div>

                </div>


                <div class="stat-card">

                    <h3>
                        <?= adminH(t('active_professors')) ?>
                    </h3>

                    <div class="stat-number">
                        <?= $activeProfessors ?>
                    </div>

                </div>


                <div class="stat-card">

                    <h3>
                        <?= adminH(t('inactive_professors')) ?>
                    </h3>

                    <div class="stat-number">
                        <?= $inactiveProfessors ?>
                    </div>

                </div>


                <div class="stat-card">

                    <h3>
                        <?= adminH(t('a150j_36a75494b67a')) ?>
                    </h3>

                    <div class="stat-number">
                        <?= $totalProfessorContentAssignments ?>
                    </div>

                </div>


                <div class="stat-card">

                    <h3>
                        <?= adminH(t('a150j_5034c6bc2dcf')) ?>
                    </h3>

                    <div class="stat-number">
                        <?= $totalProfessorTraineeAssignments ?>
                    </div>

                </div>


                <div class="stat-card">

                    <h3>
                        <?= adminH(t('total_attempts')) ?>
                    </h3>

                    <div class="stat-number">
                        <?= $totalAttempts ?>
                    </div>

                </div>


                <div class="stat-card">

                    <h3>

                        <a href="assign_questionnaire.php?lang=<?= adminH(currentLanguage()) ?>">
                            <?= adminH(t('pending_assignments')) ?>
                        </a>

                    </h3>

                    <div class="stat-number">
                        <?= $pendingAssignments ?>
                    </div>

                </div>


                <div class="stat-card">

                    <h3>
                        <?= adminH(t('completed_assignments')) ?>
                    </h3>

                    <div class="stat-number">
                        <?= $completedAssignments ?>
                    </div>

                </div>


                <div class="stat-card">

                    <h3>
                        <?= adminH(t('a150j_3cc23836bd17')) ?>
                    </h3>

                    <div class="stat-number">
                        <?= $completedAttempts ?>
                    </div>

                </div>


                <div class="stat-card">

                    <h3>

                        <a href="results.php?lang=<?= adminH(currentLanguage()) ?>">
                            <?= adminH(t('a150j_f8b9d232a2bb')) ?>
                        </a>

                    </h3>

                    <div class="stat-number">
                        <?= $pendingOpenAnswers ?>
                    </div>

                </div>


            </div>

        </section>


        <!-- ================================================= -->
        <!-- PROFESSOR SUPERVISION -->
        <!-- ================================================= -->

        <section>

            <h2>
                <?= adminH(t('professor_supervision')) ?>
            </h2>

            <p>
                <?= adminH(t('a150j_80a20c09ebd9')) ?>
            </p>


            <div class="quick-actions">

                <a
                    class="btn"
                    href="professors.php?lang=<?= adminH(currentLanguage()) ?>"
                >
                    <?= adminH(t('manage_professors')) ?>
                </a>

            </div>


            <?php if (empty($professors)): ?>

                <p>
                    <?= adminH(t('a150j_25dd9ab59b5a')) ?>
                </p>

            <?php else: ?>

                <div class="table-wrapper">

                    <table class="supervision-table">

                        <thead>

                        <tr>

                            <th>
                                <?= adminH(t('professor')) ?>
                            </th>

                            <th>
                                <?= adminH(t('username')) ?>
                            </th>

                            <th>
                                <?= adminH(t('email')) ?>
                            </th>

                            <th>
                                <?= adminH(t('h150_status')) ?>
                            </th>

                            <th>
                                <?= adminH(t('a150j_a9ae239e45aa')) ?>
                            </th>

                            <th>
                                <?= adminH(t('assigned_trainees')) ?>
                            </th>

                            <th>
                                <?= adminH(t('administration')) ?>
                            </th>

                        </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($professors as $professor): ?>

                            <tr>

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            trim(
                                                $professor['first_name']
                                                . ' '
                                                . $professor['last_name']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </strong>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $professor['username'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $professor['email'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </td>


                                <td>

                                    <?php if ((int)$professor['is_active'] === 1): ?>

                                        <span class="status-active">
                                            <?= adminH(t('active')) ?>
                                        </span>

                                    <?php else: ?>

                                        <span class="status-inactive">
                                            <?= adminH(t('inactive')) ?>
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?= (int)$professor['content_assignment_count'] ?>

                                </td>


                                <td>

                                    <?= (int)$professor['trainee_assignment_count'] ?>

                                </td>


                                <td>

                                    <div class="actions">

                                        <a
                                            class="btn btn-secondary"
                                            href="professor_content.php?lang=<?= adminH(currentLanguage()) ?>&amp;professor_id=<?= (int)$professor['id'] ?>"
                                        >
                                            <?= adminH(t('course_access')) ?>
                                        </a>


                                        <a
                                            class="btn btn-secondary"
                                            href="professor_trainees.php?lang=<?= adminH(currentLanguage()) ?>&amp;professor_id=<?= (int)$professor['id'] ?>"
                                        >
                                            <?= adminH(t('trainees')) ?>
                                        </a>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>


        <!-- ================================================= -->
        <!-- QUESTIONNAIRE SUPERVISION -->
        <!-- ================================================= -->

        <section>

            <h2>
                <?= adminH(t('a150j_00d6ceb06a2a')) ?>
            </h2>

            <div class="dashboard-grid">


                <article class="dashboard-section">

                    <h3>
                        <?= adminH(t('pending_assignments')) ?>
                    </h3>

                    <p>

                        <strong>
                            <?= $pendingAssignments ?>
                        </strong>

                        <?= adminH($pendingAssignments === 1 ? t('a150j_caf9f03d8efe') : t('a150j_6e2faef65957')) ?>
                        <?= adminH(t('a150j_43650b46ac80')) ?>

                    </p>

                    <p class="actions">

                        <a
                            class="btn"
                            href="assign_questionnaire.php?lang=<?= adminH(currentLanguage()) ?>"
                        >
                            <?= adminH(t('a150j_06413f6b2e59')) ?>
                        </a>

                    </p>

                </article>


                <article class="dashboard-section">

                    <h3>
                        <?= adminH(t('a150j_e52daff84094')) ?>
                    </h3>

                    <?php if ($pendingOpenAnswers > 0): ?>

                        <p>

                            <strong>
                                <?= $pendingOpenAnswers ?>
                            </strong>

                            <?= adminH($pendingOpenAnswers === 1 ? t('a150j_fbac19706934') : t('a150j_3f379389c2f1')) ?>
                            <?= adminH(t('a150j_11e971aa501d')) ?>

                        </p>

                    <?php else: ?>

                        <p>
                            <?= adminH(t('a150j_893ecc27e6d3')) ?>
                        </p>

                    <?php endif; ?>

                    <p class="actions">

                        <a
                            class="btn"
                            href="results.php?lang=<?= adminH(currentLanguage()) ?>"
                        >
                            <?= adminH(t('a150j_9210b8cf46c5')) ?>
                        </a>

                    </p>

                </article>


            </div>

        </section>


        <!-- ================================================= -->
        <!-- RECENT ASSIGNMENTS -->
        <!-- ================================================= -->

        <section>

            <h2>
                <?= adminH(t('a150j_0a9eeeebd1b0')) ?>
            </h2>

            <?php if (empty($recentAssignments)): ?>

                <p>
                    <?= adminH(t('a150j_5de49c703920')) ?>
                </p>

            <?php else: ?>

                <div class="table-wrapper">

                    <table class="supervision-table">

                        <thead>

                        <tr>

                            <th>
                                <?= adminH(t('trainee')) ?>
                            </th>

                            <th>
                                <?= adminH(t('h150_questionnaire')) ?>
                            </th>

                            <th>
                                <?= adminH(t('type')) ?>
                            </th>

                            <th>
                                <?= adminH(t('h150_status')) ?>
                            </th>

                            <th>
                                <?= adminH(t('h150_assigned')) ?>
                            </th>

                            <th>
                                <?= adminH(t('h150_action')) ?>
                            </th>

                        </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($recentAssignments as $assignment): ?>

                            <tr>

                                <td>

                                    <?= htmlspecialchars(
                                        trim(
                                            $assignment['first_name']
                                            . ' '
                                            . $assignment['last_name']
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $assignment['theme_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        adminEnumLabel($assignment['passation_type']),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </td>


                                <td>

                                    <?php if ($assignment['status'] === 'completed'): ?>

                                        <span class="status-completed">
                                            <?= adminH(t('h150_completed')) ?>
                                        </span>

                                    <?php else: ?>

                                        <span class="status-pending">

                                            <?= htmlspecialchars(
                                                adminEnumLabel($assignment['status']),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $assignment['assigned_at']
                                        ?? '-',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </td>


                                <td>

                                    <?php if (!empty($assignment['attempt_id'])): ?>

                                        <a
                                            class="btn btn-secondary"
                                            href="attempt_details.php?lang=<?= adminH(currentLanguage()) ?>&amp;id=<?= (int)$assignment['attempt_id'] ?>"
                                        >
                                            <?= adminH(t('view_attempt')) ?>
                                        </a>

                                    <?php else: ?>

                                        <a
                                            class="btn btn-secondary"
                                            href="assign_questionnaire.php?lang=<?= adminH(currentLanguage()) ?>"
                                        >
                                            <?= adminH(t('a150j_1e1b9d607d57')) ?>
                                        </a>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>


        <!-- ================================================= -->
        <!-- RECENT ATTEMPTS -->
        <!-- ================================================= -->

        <section>

            <h2>
                <?= adminH(t('a150j_570895248003')) ?>
            </h2>

            <?php if (empty($recentAttempts)): ?>

                <p>
                    <?= adminH(t('a150j_8870860028f3')) ?>
                </p>

            <?php else: ?>

                <div class="table-wrapper">

                    <table class="supervision-table">

                        <thead>

                        <tr>

                            <th>
                                <?= adminH(t('attempt')) ?>
                            </th>

                            <th>
                                <?= adminH(t('trainee')) ?>
                            </th>

                            <th>
                                <?= adminH(t('h150_theme')) ?>
                            </th>

                            <th>
                                <?= adminH(t('type')) ?>
                            </th>

                            <th>
                                <?= adminH(t('p150i_557fe004f344')) ?>
                            </th>

                            <th>
                                <?= adminH(t('correction')) ?>
                            </th>

                            <th>
                                <?= adminH(t('h150_score')) ?>
                            </th>

                            <th>
                                <?= adminH(t('h150_action')) ?>
                            </th>

                        </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($recentAttempts as $attempt): ?>

                            <tr>

                                <td>
                                    #<?= (int)$attempt['id'] ?>
                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        trim(
                                            $attempt['first_name']
                                            . ' '
                                            . $attempt['last_name']
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $attempt['theme_name']
                                        ?? t('a150j_3d29cce4167b'),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        adminEnumLabel($attempt['passation_type']),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </td>


                                <td>

                                    <?php if (!empty($attempt['completed_at'])): ?>

                                        <span class="status-completed">
                                            <?= adminH(t('h150_completed')) ?>
                                        </span>

                                    <?php else: ?>

                                        <span class="status-pending">
                                            <?= adminH(t('in_progress')) ?>
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?php if (!empty($attempt['corrected_at'])): ?>

                                        <span class="status-completed">
                                            <?= adminH(t('corrected')) ?>
                                        </span>

                                    <?php elseif (!empty($attempt['completed_at'])): ?>

                                        <span class="status-pending">
                                            <?= adminH(t('h150_pending')) ?>
                                        </span>

                                    <?php else: ?>

                                        —

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?php if (
                                        $attempt['total_score'] !== null
                                        && $attempt['maximum_score'] !== null
                                    ): ?>

                                        <?= htmlspecialchars(
                                            (string)$attempt['total_score'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                        /

                                        <?= htmlspecialchars(
                                            (string)$attempt['maximum_score'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    <?php else: ?>

                                        —

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <a
                                        class="btn btn-secondary"
                                        href="attempt_details.php?lang=<?= adminH(currentLanguage()) ?>&amp;id=<?= (int)$attempt['id'] ?>"
                                    >
                                        <?= adminH(t('view_attempt')) ?>
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>


        <!-- ================================================= -->
        <!-- ADMINISTRATION AREAS -->
        <!-- ================================================= -->

        <section>

            <h2>
                <?= adminH(t('administration_areas')) ?>
            </h2>


            <!-- COURSE PAGES -->

            <article class="dashboard-section">

                <h3>
                    <?= adminH(t('a150j_5797fcd02757')) ?>
                </h3>

                <p>
                    <?= adminH(t('a150j_3a230a3020fb')) ?>
                </p>

                <ul>

                    <li>
                        <?= adminH(t('sommaire')) ?>
                    </li>

                    <li>
                        <?= adminH(t('h150_introduction')) ?>
                    </li>

                </ul>

                <p class="actions">

                    <a
                        class="btn"
                        href="manage_pages.php?lang=<?= adminH(currentLanguage()) ?>"
                    >
                        <?= adminH(t('a150j_c9ae39c16356')) ?>
                    </a>

                </p>

            </article>


            <!-- COURSE CONTENT -->

            <article class="dashboard-section">

                <h3>
                    <?= adminH(t('a150j_0ea7f22eaf0c')) ?>
                </h3>

                <p>
                    <?= adminH(t('a150j_86ed5fda0edb')) ?>
                </p>

                <p>

                    <strong>
                        <?= adminH(t('h150_theme_chapter_lesson_topic_paragraph')) ?>
                    </strong>

                </p>

                <p>
                    <?= adminH(t('a150j_6754757b9a7f')) ?>
                </p>

                <p class="actions">

                    <a
                        class="btn btn-secondary"
                        href="themes.php?lang=<?= adminH(currentLanguage()) ?>"
                    >
                        <?= adminH(t('a150j_d71da25aa9b5')) ?>
                    </a>

                    <a
                        class="btn"
                        href="manage_content.php?lang=<?= adminH(currentLanguage()) ?>"
                    >
                        <?= adminH(t('a150j_3ca0c692669b')) ?>
                    </a>

                </p>

            </article>


            <!-- QUESTIONS -->

            <article class="dashboard-section">

                <h3>
                    <?= adminH(t('a150j_8ce6ddbf0eff')) ?>
                </h3>

                <p>
                    <?= adminH(t('a150j_03d62c8c04f4')) ?>
                </p>

                <ul>

                    <li>
                        <?= adminH(t('a150j_3fc91f4e0ce5')) ?>
                    </li>

                    <li>
                        <?= adminH(t('a150j_415b0ef100b9')) ?>
                    </li>

                    <li>
                        <?= adminH(t('a150j_5e0a96ef86c2')) ?>
                    </li>

                </ul>

                <p class="actions">

                    <a
                        class="btn"
                        href="questions.php?lang=<?= adminH(currentLanguage()) ?>"
                    >
                        <?= adminH(t('a150j_17dca3934b1a')) ?>
                    </a>

                </p>

            </article>


            <!-- QUESTIONNAIRE ASSIGNMENTS -->

            <article class="dashboard-section">

                <h3>
                    <?= adminH(t('a150j_9215e4421061')) ?>
                </h3>

                <p>
                    <?= adminH(t('a150j_d8dd2b2741f2')) ?>
                </p>

                <ul>

                    <li>
                        <?= adminH(t('h150_initial')) ?>
                    </li>

                    <li>
                        <?= adminH(t('h150_final')) ?>
                    </li>

                    <li>
                        <?= adminH(t('exit')) ?>
                    </li>

                </ul>

                <p class="actions">

                    <a
                        class="btn"
                        href="assign_questionnaire.php?lang=<?= adminH(currentLanguage()) ?>"
                    >
                        <?= adminH(t('a150j_06413f6b2e59')) ?>
                    </a>

                </p>

            </article>


            <!-- PROFESSORS -->

            <article class="dashboard-section">

                <h3>
                    <?= adminH(t('a150j_57964c71c9c4')) ?>
                </h3>

                <p>
                    <?= adminH(t('a150j_3a743530ce35')) ?>
                </p>

                <p>

                    <strong>
                        <?= $activeProfessors ?>
                    </strong>

                    <?= adminH($activeProfessors === 1 ? t('a150j_9802a0d2d590') : t('a150j_0aec6b93aa48')) ?>

                    /

                    <strong>
                        <?= $totalProfessors ?>
                    </strong>

                    <?= adminH(t('a150j_9356136443fa')) ?>

                </p>

                <p class="actions">

                    <a
                        class="btn"
                        href="professors.php?lang=<?= adminH(currentLanguage()) ?>"
                    >
                        <?= adminH(t('a150j_43ac41ea7e00')) ?>
                    </a>

                </p>

            </article>


            <!-- TRAINEES -->

            <article class="dashboard-section">

                <h3>
                    <?= adminH(t('a150j_7a72bd193e81')) ?>
                </h3>

                <p>
                    <?= adminH(t('a150j_08b6eb559e11')) ?>
                </p>

                <p class="actions">

                    <a
                        class="btn"
                        href="results.php?lang=<?= adminH(currentLanguage()) ?>"
                    >
                        <?= adminH(t('a150j_e80d828f107c')) ?>
                    </a>

                </p>

            </article>


            <!-- RESULTS -->

            <article class="dashboard-section">

                <h3>
                    <?= adminH(t('a150j_d8f7cc1a55fb')) ?>
                </h3>

                <p>
                    <?= adminH(t('a150j_62f7418b0766')) ?>
                </p>

                <?php if ($pendingOpenAnswers > 0): ?>

                    <p>

                        <strong>

                            <?= $pendingOpenAnswers ?>

                            <?= adminH(t('a150j_fbac19706934')) ?><?= $pendingOpenAnswers === 1
                                ? ''
                                : 's' ?>

                            <?= adminH(t('a150j_11e971aa501d')) ?>

                        </strong>

                    </p>

                <?php else: ?>

                    <p>
                        <?= adminH(t('a150j_893ecc27e6d3')) ?>
                    </p>

                <?php endif; ?>

                <p class="actions">

                    <a
                        class="btn"
                        href="results.php?lang=<?= adminH(currentLanguage()) ?>"
                    >
                        <?= adminH(t('a150j_ee90c7a06214')) ?>
                    </a>

                </p>

            </article>


            <!-- POSITIONING LEVELS -->

            <article class="dashboard-section">

                <h3>
                    <?= adminH(t('a150j_94f3b55dac8e')) ?>
                </h3>

                <p>
                    <?= adminH(t('a150j_14dba2ecc98f')) ?>
                </p>

                <p class="actions">

                    <a
                        class="btn"
                        href="levels.php?lang=<?= adminH(currentLanguage()) ?>"
                    >
                        <?= adminH(t('a150j_4c54fa2aa14f')) ?>
                    </a>

                </p>

            </article>


        </section>


    </main>


    <!-- ================================================= -->
    <!-- FOOTER -->
    <!-- ================================================= -->

    <footer>

        <p class="actions">

            <a
                class="btn btn-secondary"
                href="../index.php?lang=<?= adminH(currentLanguage()) ?>"
            >
                <?= adminH(t('a150j_f219040fd0c2')) ?>
            </a>

            <a
                class="btn btn-secondary"
                href="../professor/login.php?lang=<?= adminH(currentLanguage()) ?>"
            >
                <?= adminH(t('professor_login')) ?>
            </a>

            <a
                class="btn btn-secondary"
                href="logout.php?lang=<?= adminH(currentLanguage()) ?>"
            >
                <?= adminH(t('h150_logout')) ?>
            </a>

        </p>

    </footer>


</div>

</body>

</html>