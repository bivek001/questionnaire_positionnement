<?php
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/admin_language.php';

require_once 'auth.php';
require_once '../config/database.php';


// ======================================================
// GET PROFESSOR ID
// ======================================================

$professorId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$professorId) {
    http_response_code(400);
    die(t('invalid_professor'));
}


// ======================================================
// LOAD PROFESSOR
// ======================================================

$stmt = $pdo->prepare(
    "SELECT
        id,
        first_name,
        last_name,
        email,
        username,
        is_active,
        created_at,
        updated_at
     FROM professors
     WHERE id = ?
     LIMIT 1"
);

$stmt->execute([$professorId]);

$professor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$professor) {
    http_response_code(404);
    die(t('professor_not_found'));
}


// ======================================================
// PROFESSOR NAME
// ======================================================

$professorName = trim(
    $professor['first_name']
    . ' '
    . $professor['last_name']
);


// ======================================================
// LOAD CONTENT ASSIGNMENTS
// ======================================================

$stmt = $pdo->prepare(
    "SELECT
        pca.id,
        pca.theme_id,
        pca.chapter_id,
        pca.created_at,

        t.name AS theme_name,

        c.title AS chapter_title

     FROM professor_content_assignments pca

     INNER JOIN themes t
        ON t.id = pca.theme_id

     LEFT JOIN chapters c
        ON c.id = pca.chapter_id

     WHERE pca.professor_id = ?

     ORDER BY
        t.display_order ASC,
        t.name ASC,
        c.display_order ASC,
        c.title ASC"
);

$stmt->execute([$professorId]);

$contentAssignments =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// LOAD ASSIGNED TRAINEES
// ======================================================

$stmt = $pdo->prepare(
    "SELECT
        pta.id AS assignment_id,
        pta.created_at AS assigned_at,

        tr.id,
        tr.first_name,
        tr.last_name,
        tr.email,

        (
            SELECT COUNT(*)
            FROM attempts a
            WHERE a.trainee_id = tr.id
        ) AS total_attempts,

        (
            SELECT COUNT(*)
            FROM questionnaire_assignments qa
            WHERE qa.trainee_id = tr.id
              AND qa.status = 'pending'
        ) AS pending_questionnaires,

        (
            SELECT COUNT(*)
            FROM questionnaire_assignments qa
            WHERE qa.trainee_id = tr.id
              AND qa.status = 'completed'
        ) AS completed_questionnaires

     FROM professor_trainee_assignments pta

     INNER JOIN trainees tr
        ON tr.id = pta.trainee_id

     WHERE pta.professor_id = ?

     ORDER BY
        tr.last_name ASC,
        tr.first_name ASC,
        tr.id ASC"
);

$stmt->execute([$professorId]);

$assignedTrainees =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// COUNT PERMITTED QUESTIONS
// ======================================================

$stmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT q.id)

     FROM questions q

     WHERE EXISTS (

        SELECT 1

        FROM professor_content_assignments pca

        WHERE pca.professor_id = ?

          AND pca.theme_id = q.theme_id

          AND (
                pca.chapter_id IS NULL

                OR

                pca.chapter_id = q.chapter_id
          )
     )"
);

$stmt->execute([$professorId]);

$permittedQuestions =
    (int)$stmt->fetchColumn();


// ======================================================
// COUNT ACTIVE PERMITTED QUESTIONS
// ======================================================

$stmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT q.id)

     FROM questions q

     WHERE q.is_active = 1

       AND EXISTS (

            SELECT 1

            FROM professor_content_assignments pca

            WHERE pca.professor_id = ?

              AND pca.theme_id = q.theme_id

              AND (
                    pca.chapter_id IS NULL

                    OR

                    pca.chapter_id = q.chapter_id
              )
       )"
);

$stmt->execute([$professorId]);

$activePermittedQuestions =
    (int)$stmt->fetchColumn();


// ======================================================
// COUNT ASSIGNED TRAINEES
// ======================================================

$totalAssignedTrainees =
    count($assignedTrainees);


// ======================================================
// COUNT CONTENT ASSIGNMENTS
// ======================================================

$totalContentAssignments =
    count($contentAssignments);


// ======================================================
// COUNT PERMITTED TRAINEE ATTEMPTS
// ======================================================

$stmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT a.id)

     FROM attempts a

     INNER JOIN professor_trainee_assignments pta
        ON pta.trainee_id = a.trainee_id
       AND pta.professor_id = ?

     WHERE EXISTS (

        SELECT 1

        FROM professor_content_assignments pca

        WHERE pca.professor_id = ?

          AND pca.theme_id = a.theme_id
     )"
);

$stmt->execute([
    $professorId,
    $professorId
]);

$totalPermittedAttempts =
    (int)$stmt->fetchColumn();


// ======================================================
// COUNT COMPLETED PERMITTED ATTEMPTS
// ======================================================

$stmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT a.id)

     FROM attempts a

     INNER JOIN professor_trainee_assignments pta
        ON pta.trainee_id = a.trainee_id
       AND pta.professor_id = ?

     WHERE a.completed_at IS NOT NULL

       AND EXISTS (

            SELECT 1

            FROM professor_content_assignments pca

            WHERE pca.professor_id = ?

              AND pca.theme_id = a.theme_id
       )"
);

$stmt->execute([
    $professorId,
    $professorId
]);

$completedPermittedAttempts =
    (int)$stmt->fetchColumn();


// ======================================================
// COUNT PROFESSOR-PERMITTED PENDING OPEN ANSWERS
// ======================================================

$stmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT r.id)

     FROM responses r

     INNER JOIN attempts a
        ON a.id = r.attempt_id

     INNER JOIN questions q
        ON q.id = r.question_id

     INNER JOIN professor_trainee_assignments pta
        ON pta.trainee_id = a.trainee_id
       AND pta.professor_id = ?

     WHERE q.question_type = 'open'

       AND r.graded_at IS NULL

       AND EXISTS (

            SELECT 1

            FROM professor_content_assignments pca

            WHERE pca.professor_id = ?

              AND pca.theme_id = q.theme_id

              AND (
                    pca.chapter_id IS NULL

                    OR

                    pca.chapter_id = q.chapter_id
              )
       )"
);

$stmt->execute([
    $professorId,
    $professorId
]);

$pendingOpenAnswers =
    (int)$stmt->fetchColumn();


// ======================================================
// RECENT PERMITTED ATTEMPTS
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
        a.total_score,
        a.maximum_score,

        tr.first_name,
        tr.last_name,

        t.name AS theme_name

     FROM attempts a

     INNER JOIN trainees tr
        ON tr.id = a.trainee_id

     INNER JOIN themes t
        ON t.id = a.theme_id

     INNER JOIN professor_trainee_assignments pta
        ON pta.trainee_id = a.trainee_id
       AND pta.professor_id = ?

     WHERE EXISTS (

        SELECT 1

        FROM professor_content_assignments pca

        WHERE pca.professor_id = ?

          AND pca.theme_id = a.theme_id
     )

     ORDER BY a.id DESC

     LIMIT 15"
);

$stmt->execute([
    $professorId,
    $professorId
]);

$recentAttempts =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// LOAD QUESTIONNAIRE STATUS FOR ASSIGNED TRAINEES
// ======================================================

$questionnaireStatuses = [];

if (!empty($assignedTrainees)) {

    foreach ($assignedTrainees as $trainee) {

        $traineeId = (int)$trainee['id'];

        /*
         * Themes visible to this professor.
         *
         * DISTINCT is important because a professor may
         * have several chapter assignments in one theme.
         */
        $stmt = $pdo->prepare(
            "SELECT DISTINCT
                t.id,
                t.name,
                t.display_order

             FROM themes t

             INNER JOIN professor_content_assignments pca
                ON pca.theme_id = t.id

             WHERE pca.professor_id = ?

             ORDER BY
                t.display_order ASC,
                t.name ASC"
        );

        $stmt->execute([$professorId]);

        $permittedThemes =
            $stmt->fetchAll(PDO::FETCH_ASSOC);


        foreach ($permittedThemes as $theme) {

            $themeId = (int)$theme['id'];

            // ------------------------------------------
            // LOAD ALL ASSIGNMENTS FOR THIS
            // TRAINEE + THEME
            // ------------------------------------------

            $stmt = $pdo->prepare(
                "SELECT
                    id,
                    passation_type,
                    status,
                    assigned_at,
                    completed_at,
                    attempt_id

                 FROM questionnaire_assignments

                 WHERE trainee_id = ?
                   AND theme_id = ?

                 ORDER BY id DESC"
            );

            $stmt->execute([
                $traineeId,
                $themeId
            ]);

            $assignments =
                $stmt->fetchAll(PDO::FETCH_ASSOC);


            // ------------------------------------------
            // DETERMINE SUMMARY STATUS
            // ------------------------------------------

            $status = 'not_assigned';

            $hasPending = false;
            $hasCompleted = false;

            foreach ($assignments as $assignment) {

                if ($assignment['status'] === 'pending') {
                    $hasPending = true;
                }

                if ($assignment['status'] === 'completed') {
                    $hasCompleted = true;
                }
            }


            if ($hasPending) {

                $status = 'available';

            } elseif ($hasCompleted) {

                $status = 'done';
            }


            $questionnaireStatuses[] = [

                'trainee_id' =>
                    $traineeId,

                'trainee_name' =>
                    trim(
                        $trainee['first_name']
                        . ' '
                        . $trainee['last_name']
                    ),

                'theme_id' =>
                    $themeId,

                'theme_name' =>
                    $theme['name'],

                'status' =>
                    $status,

                'assignments' =>
                    $assignments
            ];
        }
    }
}


// ======================================================
// STATUS COUNTS
// ======================================================

$doneStatusCount = 0;
$availableStatusCount = 0;
$notAssignedStatusCount = 0;

foreach ($questionnaireStatuses as $statusRow) {

    if ($statusRow['status'] === 'done') {

        $doneStatusCount++;

    } elseif ($statusRow['status'] === 'available') {

        $availableStatusCount++;

    } else {

        $notAssignedStatusCount++;
    }
}

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
        <?= adminH(t('professor_supervision')) ?>
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <style>

        .professor-supervision .detail-header {

            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;

        }


        .professor-supervision .status-badge {

            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 700;

        }


        .professor-supervision .status-active,
        .professor-supervision .status-done {

            color: #166534;
            background: #dcfce7;

        }


        .professor-supervision .status-inactive {

            color: #991b1b;
            background: #fee2e2;

        }


        .professor-supervision .status-available {

            color: #92400e;
            background: #fef3c7;

        }


        .professor-supervision .status-not-assigned {

            color: #475569;
            background: #e2e8f0;

        }


        .professor-supervision .table-wrapper {

            width: 100%;
            overflow-x: auto;

        }


        .professor-supervision table {

            width: 100%;
            border-collapse: collapse;

        }


        .professor-supervision th,
        .professor-supervision td {

            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: top;

        }


        .professor-supervision th {

            background: #f8fafc;

        }


        .professor-supervision .supervision-actions {

            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1rem;

        }


        .professor-supervision .info-grid {

            display: grid;

            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(220px, 1fr)
                );

            gap: 1rem;

        }


        .professor-supervision .info-box {

            padding: 1rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;

        }


        .professor-supervision .info-box strong {

            display: block;
            margin-bottom: 0.35rem;

        }


        .professor-supervision section {

            margin-bottom: 1.5rem;

        }

    </style>

<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>


<body class="professor-supervision">
<?php require __DIR__ . '/admin_language_switcher.php'; ?>

<div class="container">


    <!-- ================================================= -->
    <!-- HEADER -->
    <!-- ================================================= -->

    <header class="admin-header">

        <h1>
            <?= adminH(t('professor_supervision')) ?>
        </h1>

        <p>
            <?= adminH(t('a150j_a90ef17263c2')) ?>
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
    <!-- ADMIN NAVIGATION -->
    <!-- ================================================= -->

    <?php require 'admin_nav.php'; ?><p><a class="btn" href="edit_account.php?role=professor&amp;id=<?= (int)$professorId ?>"><?= adminH(t('pkg_edit_account')) ?></a></p>


    <main>


        <!-- ================================================= -->
        <!-- PROFESSOR INFORMATION -->
        <!-- ================================================= -->

        <section class="card">

            <div class="detail-header">

                <div>

                    <h2>

                        <?= htmlspecialchars(
                            $professorName,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </h2>

                    <p>
                        <?= adminH(t('a150j_3924dd34e054')) ?>
                        <strong>
                            #<?= (int)$professor['id'] ?>
                        </strong>
                    </p>

                </div>


                <div>

                    <?php if ((int)$professor['is_active'] === 1): ?>

                        <span
                            class="
                                status-badge
                                status-active
                            "
                        >
                            <?= adminH(t('a150j_e870069d78fc')) ?>
                        </span>

                    <?php else: ?>

                        <span
                            class="
                                status-badge
                                status-inactive
                            "
                        >
                            <?= adminH(t('a150j_0002ed3757e9')) ?>
                        </span>

                    <?php endif; ?>

                </div>

            </div>


            <div class="info-grid">

                <div class="info-box">

                    <strong>
                        <?= adminH(t('username')) ?>
                    </strong>

                    <?= htmlspecialchars(
                        $professor['username'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>


                <div class="info-box">

                    <strong>
                        <?= adminH(t('email')) ?>
                    </strong>

                    <?= htmlspecialchars(
                        $professor['email'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>


                <div class="info-box">

                    <strong>
                        <?= adminH(t('a150j_9966be402136')) ?>
                    </strong>

                    <?= htmlspecialchars(
                        $professor['created_at'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>

            </div>


            <div class="supervision-actions">

                <a
                    class="btn"
                    href="professor_content.php?lang=<?= adminH(currentLanguage()) ?>&amp;professor_id=<?= $professorId ?>"
                >
                    <?= adminH(t('manage_course_access')) ?>
                </a>

                <a
                    class="btn"
                    href="professor_trainees.php?lang=<?= adminH(currentLanguage()) ?>&amp;professor_id=<?= $professorId ?>"
                >
                    <?= adminH(t('manage_assigned_trainees')) ?>
                </a>

                <a
                    class="btn btn-secondary"
                    href="professors.php?lang=<?= adminH(currentLanguage()) ?>"
                >
                    <?= adminH(t('a150j_d1c182de8289')) ?>
                </a>

            </div>

        </section>


        <!-- ================================================= -->
        <!-- STATISTICS -->
        <!-- ================================================= -->

        <section>

            <h2>
                <?= adminH(t('a150j_92accb7afa9d')) ?>
            </h2>

            <div class="stats-grid">


                <div class="stat-card">

                    <h3>
                        <?= adminH(t('content_assignments')) ?>
                    </h3>

                    <div class="stat-number">
                        <?= $totalContentAssignments ?>
                    </div>

                </div>


                <div class="stat-card">

                    <h3>
                        <?= adminH(t('assigned_trainees')) ?>
                    </h3>

                    <div class="stat-number">
                        <?= $totalAssignedTrainees ?>
                    </div>

                </div>


                <div class="stat-card">

                    <h3>
                        <?= adminH(t('permitted_questions')) ?>
                    </h3>

                    <div class="stat-number">
                        <?= $permittedQuestions ?>
                    </div>

                </div>


                <div class="stat-card">

                    <h3>
                        <?= adminH(t('active_questions')) ?>
                    </h3>

                    <div class="stat-number">
                        <?= $activePermittedQuestions ?>
                    </div>

                </div>


                <div class="stat-card">

                    <h3>
                        <?= adminH(t('permitted_attempts')) ?>
                    </h3>

                    <div class="stat-number">
                        <?= $totalPermittedAttempts ?>
                    </div>

                </div>


                <div class="stat-card">

                    <h3>
                        <?= adminH(t('completed_attempts')) ?>
                    </h3>

                    <div class="stat-number">
                        <?= $completedPermittedAttempts ?>
                    </div>

                </div>


                <div class="stat-card">

                    <h3>
                        <?= adminH(t('pending_open_answers')) ?>
                    </h3>

                    <div class="stat-number">
                        <?= $pendingOpenAnswers ?>
                    </div>

                </div>


                <div class="stat-card">

                    <h3>
                        <?= adminH(t('h150_available')) ?>
                    </h3>

                    <div class="stat-number">
                        <?= $availableStatusCount ?>
                    </div>

                </div>


                <div class="stat-card">

                    <h3>
                        <?= adminH(t('done')) ?>
                    </h3>

                    <div class="stat-number">
                        <?= $doneStatusCount ?>
                    </div>

                </div>


                <div class="stat-card">

                    <h3>
                        <?= adminH(t('not_assigned')) ?>
                    </h3>

                    <div class="stat-number">
                        <?= $notAssignedStatusCount ?>
                    </div>

                </div>


            </div>

        </section>


        <!-- ================================================= -->
        <!-- COURSE ACCESS -->
        <!-- ================================================= -->

        <section class="card">

            <h2>
                <?= adminH(t('course_access')) ?>
            </h2>

            <p>
                <?= adminH(t('a150j_cac975e555bf')) ?>
            </p>


            <?php if (empty($contentAssignments)): ?>

                <p>
                    <?= adminH(t('no_course_content_assigned')) ?>
                </p>

            <?php else: ?>

                <div class="table-wrapper">

                    <table>

                        <thead>

                        <tr>

                            <th>
                                <?= adminH(t('h150_theme')) ?>
                            </th>

                            <th>
                                <?= adminH(t('a150j_229efc8f5263')) ?>
                            </th>

                            <th>
                                <?= adminH(t('h150_chapter')) ?>
                            </th>

                            <th>
                                <?= adminH(t('h150_assigned')) ?>
                            </th>

                        </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($contentAssignments as $assignment): ?>

                            <tr>

                                <td>

                                    <?= htmlspecialchars(
                                        $assignment['theme_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </td>


                                <td>

                                    <?php if ($assignment['chapter_id'] === null): ?>

                                        <strong>
                                            <?= adminH(t('entire_theme')) ?>
                                        </strong>

                                    <?php else: ?>

                                        <?= adminH(t('chapter_only')) ?>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $assignment['chapter_title']
                                        ?? t('all_chapters'),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $assignment['created_at'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>


            <div class="supervision-actions">

                <a
                    class="btn"
                    href="professor_content.php?lang=<?= adminH(currentLanguage()) ?>&amp;professor_id=<?= $professorId ?>"
                >
                    <?= adminH(t('change_course_access')) ?>
                </a>

            </div>

        </section>


        <!-- ================================================= -->
        <!-- ASSIGNED TRAINEES -->
        <!-- ================================================= -->

        <section class="card">

            <h2>
                <?= adminH(t('assigned_trainees')) ?>
            </h2>


            <?php if (empty($assignedTrainees)): ?>

                <p>
                    <?= adminH(t('no_trainees_assigned')) ?>
                </p>

            <?php else: ?>

                <div class="table-wrapper">

                    <table>

                        <thead>

                        <tr>

                            <th>
                                <?= adminH(t('trainee')) ?>
                            </th>

                            <th>
                                <?= adminH(t('email')) ?>
                            </th>

                            <th>
                                <?= adminH(t('attempts')) ?>
                            </th>

                            <th>
                                <?= adminH(t('h150_available')) ?>
                            </th>

                            <th>
                                <?= adminH(t('h150_completed')) ?>
                            </th>

                            <th>
                                <?= adminH(t('h150_assigned')) ?>
                            </th>

                        </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($assignedTrainees as $trainee): ?>

                            <tr>

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            trim(
                                                $trainee['first_name']
                                                . ' '
                                                . $trainee['last_name']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </strong>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $trainee['email'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </td>


                                <td>
                                    <?= (int)$trainee['total_attempts'] ?>
                                </td>


                                <td>
                                    <?= (int)$trainee['pending_questionnaires'] ?>
                                </td>


                                <td>
                                    <?= (int)$trainee['completed_questionnaires'] ?>
                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $trainee['assigned_at'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>


            <div class="supervision-actions">

                <a
                    class="btn"
                    href="professor_trainees.php?lang=<?= adminH(currentLanguage()) ?>&amp;professor_id=<?= $professorId ?>"
                >
                    <?= adminH(t('change_trainee_access')) ?>
                </a>

                <a
                    class="btn btn-secondary"
                    href="results.php?lang=<?= adminH(currentLanguage()) ?>"
                >
                    <?= adminH(t('all_trainee_results')) ?>
                </a>

            </div>

        </section>


        <!-- ================================================= -->
        <!-- QUESTIONNAIRE STATUS -->
        <!-- ================================================= -->

        <section class="card">

            <h2>
                <?= adminH(t('trainee_questionnaire_status')) ?>
            </h2>

            <p>
                <?= adminH(t('a150j_8651fd933ff1')) ?>
            </p>


            <?php if (empty($questionnaireStatuses)): ?>

                <p>
                    <?= adminH(t('a150j_0fe8c8711c11')) ?>
                </p>

            <?php else: ?>

                <div class="table-wrapper">

                    <table>

                        <thead>

                        <tr>

                            <th>
                                <?= adminH(t('trainee')) ?>
                            </th>

                            <th>
                                <?= adminH(t('h150_questionnaire')) ?>
                            </th>

                            <th>
                                <?= adminH(t('h150_status')) ?>
                            </th>

                            <th>
                                <?= adminH(t('assignment_history')) ?>
                            </th>

                        </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($questionnaireStatuses as $statusRow): ?>

                            <tr>

                                <td>

                                    <?= htmlspecialchars(
                                        $statusRow['trainee_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $statusRow['theme_name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </td>


                                <td>

                                    <?php if ($statusRow['status'] === 'done'): ?>

                                        <span
                                            class="
                                                status-badge
                                                status-done
                                            "
                                        >
                                            <?= adminH(t('done')) ?>
                                        </span>

                                    <?php elseif ($statusRow['status'] === 'available'): ?>

                                        <span
                                            class="
                                                status-badge
                                                status-available
                                            "
                                        >
                                            <?= adminH(t('h150_available')) ?>
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="
                                                status-badge
                                                status-not-assigned
                                            "
                                        >
                                            <?= adminH(t('not_assigned')) ?>
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?php if (empty($statusRow['assignments'])): ?>

                                        <?= adminH(t('a150j_a623cfc12936')) ?>

                                    <?php else: ?>

                                        <?php foreach ($statusRow['assignments'] as $assignment): ?>

                                            <div>

                                                <strong>

                                                    <?= htmlspecialchars(
                                                        adminEnumLabel($assignment[
                                                                'passation_type'
                                                            ]),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>

                                                </strong>

                                                —

                                                <?= htmlspecialchars(
                                                    adminEnumLabel($assignment['status']),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                                <?php if (!empty($assignment['attempt_id'])): ?>

                                                    —

                                                    <a
                                                        href="attempt_details.php?lang=<?= adminH(currentLanguage()) ?>&amp;id=<?= (int)$assignment['attempt_id'] ?>"
                                                    >
                                                        <?= adminH(t('p150i_513c2cb7aff4')) ?><?= (int)$assignment['attempt_id'] ?>
                                                    </a>

                                                <?php endif; ?>

                                            </div>

                                        <?php endforeach; ?>

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

        <section class="card">

            <h2>
                <?= adminH(t('recent_permitted_attempts')) ?>
            </h2>

            <p>
                <?= adminH(t('a150j_933104d57efc')) ?>
            </p>


            <?php if (empty($recentAttempts)): ?>

                <p>
                    <?= adminH(t('no_attempts_found')) ?>
                </p>

            <?php else: ?>

                <div class="table-wrapper">

                    <table>

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
                                        $attempt['theme_name'],
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

                                    <?= !empty($attempt['completed_at'])
                                        ? t('h150_completed')
                                        : t('in_progress') ?>

                                </td>


                                <td>

                                    <?php if (!empty($attempt['corrected_at'])): ?>

                                        <?= adminH(t('corrected')) ?>

                                    <?php elseif (!empty($attempt['completed_at'])): ?>

                                        <?= adminH(t('h150_pending')) ?>

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


    </main>


    <!-- ================================================= -->
    <!-- FOOTER -->
    <!-- ================================================= -->

    <footer>

        <p class="actions">

            <a
                class="btn btn-secondary"
                href="professors.php?lang=<?= adminH(currentLanguage()) ?>"
            >
                <?= adminH(t('a150j_a241688345bb')) ?>
            </a>

            <a
                class="btn btn-secondary"
                href="index.php?lang=<?= adminH(currentLanguage()) ?>"
            >
                <?= adminH(t('admin_dashboard_link')) ?>
            </a>

        </p>

    </footer>


</div>

</body>

</html>