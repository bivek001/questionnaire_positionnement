<?php
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/admin_language.php';

require_once 'auth.php';
require_once '../config/database.php';

require_once __DIR__ . '/../includes/account_security.php';
ux_csrf_check();
$message = '';
$error = '';


// ======================================================
// GET PROFESSOR ID
// ======================================================

$professorId = filter_input(
    INPUT_GET,
    'professor_id',
    FILTER_VALIDATE_INT
);

if (!$professorId) {
    $professorId = filter_input(
        INPUT_POST,
        'professor_id',
        FILTER_VALIDATE_INT
    );
}


// ======================================================
// LOAD PROFESSORS
// ======================================================

$stmt = $pdo->query(
    "SELECT
        id,
        first_name,
        last_name,
        username,
        is_active
     FROM professors
     ORDER BY last_name ASC, first_name ASC"
);

$professors = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// LOAD SELECTED PROFESSOR
// ======================================================

$selectedProfessor = null;

if ($professorId) {

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

    $selectedProfessor =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$selectedProfessor) {
        $error = t('p150i_701d8d518286');
        $professorId = null;
    }
}


// ======================================================
// ASSIGN TRAINEE
// ======================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'assign'
    && $selectedProfessor
) {

    $traineeId = filter_input(
        INPUT_POST,
        'trainee_id',
        FILTER_VALIDATE_INT
    );

    if (!$traineeId) {

        $error = t('a150j_b91585e97517');

    } else {

        // Check trainee exists.

        $stmt = $pdo->prepare(
            "SELECT
                id,
                first_name,
                last_name
             FROM trainees
             WHERE id = ?
             LIMIT 1"
        );

        $stmt->execute([$traineeId]);

        $trainee =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$trainee) {

            $error = t('a150j_96f126298d06');

        } else {

            // Check duplicate assignment.

            $stmt = $pdo->prepare(
                "SELECT id
                 FROM professor_trainee_assignments
                 WHERE professor_id = ?
                   AND trainee_id = ?
                 LIMIT 1"
            );

            $stmt->execute([
                $professorId,
                $traineeId
            ]);

            if ($stmt->fetch()) {

                $error =
                    t('a150j_2d1e7de2a5f8');

            } else {

                try {

                    $stmt = $pdo->prepare(
                        "INSERT INTO professor_trainee_assignments
                            (
                                professor_id,
                                trainee_id
                            )
                         VALUES (?, ?)"
                    );

                    $stmt->execute([
                        $professorId,
                        $traineeId
                    ]);

                    $message =
                        t('a150j_0560d0188c6b');

                } catch (PDOException $e) {

                    $error =
                        t('a150j_d1fc7bbbf151');
                }
            }
        }
    }
}


// ======================================================
// REMOVE TRAINEE ASSIGNMENT
// ======================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'remove'
    && $selectedProfessor
) {

    $assignmentId = filter_input(
        INPUT_POST,
        'assignment_id',
        FILTER_VALIDATE_INT
    );

    if (!$assignmentId) {

        $error = t('a150j_0cc5050ba21e');

    } else {

        /*
         * professor_id is included intentionally.
         * Changing the assignment ID in the browser
         * cannot remove another professor's assignment.
         */

        $stmt = $pdo->prepare(
            "DELETE FROM professor_trainee_assignments
             WHERE id = ?
               AND professor_id = ?"
        );

        $stmt->execute([
            $assignmentId,
            $professorId
        ]);

        if ($stmt->rowCount() > 0) {

            $message =
                t('a150j_8cc21eb52209');

        } else {

            $error =
                t('a150j_cff3a5e0c4d1');
        }
    }
}


// ======================================================
// LOAD ALL TRAINEES
// ======================================================

$stmt = $pdo->query(
    "SELECT
        id,
        first_name,
        last_name,
        email,
        date_of_birth
     FROM trainees
     ORDER BY
        last_name ASC,
        first_name ASC,
        id ASC"
);

$trainees =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// LOAD CURRENT TRAINEE ASSIGNMENTS
// ======================================================

$assignments = [];

if ($selectedProfessor) {

    $stmt = $pdo->prepare(
        "SELECT
            pta.id AS assignment_id,
            pta.trainee_id,
            pta.created_at,

            t.first_name,
            t.last_name,
            t.email,
            t.date_of_birth,

            (
                SELECT COUNT(*)
                FROM attempts a
                WHERE a.trainee_id = t.id
            ) AS attempt_count

         FROM professor_trainee_assignments pta

         INNER JOIN trainees t
            ON t.id = pta.trainee_id

         WHERE pta.professor_id = ?

         ORDER BY
            t.last_name ASC,
            t.first_name ASC,
            t.id ASC"
    );

    $stmt->execute([$professorId]);

    $assignments =
        $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ======================================================
// BUILD ASSIGNED TRAINEE ID LIST
// ======================================================

$assignedTraineeIds = [];

foreach ($assignments as $assignment) {
    $assignedTraineeIds[] =
        (int)$assignment['trainee_id'];
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
        <?= adminH(t('a150j_3d527ad0a775')) ?>
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <style>

        .professor-selection-card,
        .trainee-assignment-card {
            margin-bottom: 24px;
            padding: 24px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow:
                0 8px 24px rgba(15, 23, 42, 0.05);
        }

        .professor-selection-card h2,
        .trainee-assignment-card h2 {
            margin-top: 0;
        }

        .selected-professor-card {
            margin-bottom: 24px;
            padding: 20px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 14px;
        }

        .selected-professor-card h2 {
            margin: 0 0 8px;
        }

        .assignment-layout {
            display: grid;
            grid-template-columns:
                minmax(300px, 400px)
                minmax(0, 1fr);
            gap: 24px;
            align-items: start;
        }

        .simple-form {
            margin: 0;
            padding: 0;
            background: transparent;
            border: 0;
            box-shadow: none;
        }

        .form-field {
            margin-bottom: 18px;
        }

        .form-field label {
            display: block;
            margin-bottom: 7px;
            font-weight: 700;
        }

        .form-field select {
            width: 100%;
        }

        .trainee-table-wrapper {
            overflow-x: auto;
        }

        .trainee-table {
            width: 100%;
            min-width: 700px;
            border-collapse: collapse;
        }

        .trainee-table th,
        .trainee-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: middle;
        }

        .trainee-table th {
            background: #f8fafc;
        }

        .assigned-badge {
            display: inline-block;
            padding: 5px 10px;
            color: #166534;
            background: #dcfce7;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .remove-form {
            display: inline;
            margin: 0;
            padding: 0;
            border: 0;
            background: transparent;
            box-shadow: none;
        }

        .remove-button {
            padding: 7px 11px;
            color: #ffffff;
            background: #b91c1c;
            border: 0;
            border-radius: 8px;
            font: inherit;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
        }

        .empty-message {
            padding: 22px;
            color: #64748b;
            background: #f8fafc;
            border-radius: 12px;
            text-align: center;
        }

        .permission-note {
            margin-top: 20px;
            padding: 16px;
            background: #f8fafc;
            border-left: 4px solid #2563eb;
            border-radius: 8px;
        }

        @media (max-width: 950px) {

            .assignment-layout {
                grid-template-columns: 1fr;
            }
        }

    </style>

<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>

<body>
<?php require __DIR__ . '/admin_language_switcher.php'; ?>

<div class="container">


    <!-- ================================================= -->
    <!-- HEADER -->
    <!-- ================================================= -->

    <header class="admin-header">

        <h1>
            <?= adminH(t('a150j_3d527ad0a775')) ?>
        </h1>

        <p>
            <?= adminH(t('a150j_679c07022deb')) ?>
        </p>

        <p>
            <?= adminH(t('h150_logged_in_as')) ?>
            <strong>
                <?= htmlspecialchars(
                    $_SESSION['admin_username']
                    ?? t('admin')
                ) ?>
            </strong>
        </p>

    </header>


    <!-- ================================================= -->
    <!-- ADMIN NAVIGATION -->
    <!-- ================================================= -->

    <?php require 'admin_nav.php'; ?>


    <main>


        <!-- ================================================= -->
        <!-- MESSAGES -->
        <!-- ================================================= -->

        <?php if ($message !== ''): ?>

            <div class="alert alert-success">
                <?= htmlspecialchars($message) ?>
            </div>

        <?php endif; ?>


        <?php if ($error !== ''): ?>

            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <!-- ================================================= -->
        <!-- SELECT PROFESSOR -->
        <!-- ================================================= -->

        <section class="professor-selection-card">

            <h2>
                <?= adminH(t('a150j_130ef6615a22')) ?>
            </h2>


            <?php if (empty($professors)): ?>

                <p>
                    <?= adminH(t('a150j_69e1c732cf19')) ?>
                </p>

                <p>
                    <a
                        class="btn"
                        href="professors.php?lang=<?= adminH(currentLanguage()) ?>"
                    >
                        <?= adminH(t('create_professor')) ?>
                    </a>
                </p>

            <?php else: ?>

                <form
                    method="GET"
                    action="professor_trainees.php"
                    class="simple-form"
                >
<input type="hidden" name="lang" value="<?= adminH(currentLanguage()) ?>">

                    <div class="form-field">

                        <label for="professor_id">
                            <?= adminH(t('professor')) ?>
                        </label>

                        <select
                            id="professor_id"
                            name="professor_id"
                            required
                        >

                            <option value="">
                                <?= adminH(t('a150j_fd9cd6fcd142')) ?>
                            </option>

                            <?php foreach (
                                $professors as $professor
                            ): ?>

                                <option
                                    value="<?= (int)$professor['id'] ?>"
                                    <?php if (
                                        $professorId ===
                                        (int)$professor['id']
                                    ): ?>
                                        selected
                                    <?php endif; ?>
                                >

                                    <?= htmlspecialchars(
                                        $professor['first_name']
                                        . ' '
                                        . $professor['last_name']
                                        . ' ('
                                        . $professor['username']
                                        . ')'
                                        . (
                                            (int)$professor['is_active'] === 1
                                                ? ''
                                                : ' ' . t('a150j_90c7f2e6436c')
                                        )
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <button
                        type="submit"
                        class="btn"
                    >
                        <?= adminH(t('a150j_4900424acf99')) ?>
                    </button>

                </form>

            <?php endif; ?>

        </section>


        <?php if ($selectedProfessor): ?>


            <!-- ============================================= -->
            <!-- SELECTED PROFESSOR -->
            <!-- ============================================= -->

            <section class="selected-professor-card">

                <h2>
                    <?= htmlspecialchars(
                        $selectedProfessor['first_name']
                        . ' '
                        . $selectedProfessor['last_name']
                    ) ?>
                </h2>

                <p class="mb-0">

                    <?= adminH(t('a150j_3806d61c3406')) ?>
                    <strong>
                        <?= htmlspecialchars(
                            $selectedProfessor['username']
                        ) ?>
                    </strong>

                    &nbsp; | <?= adminH(t('p150i_755c8b2a9fb1')) ?>
                    <strong>
                        <?= (int)$selectedProfessor['is_active'] === 1
                            ? t('active')
                            : t('inactive') ?>
                    </strong>

                </p>

            </section>


            <div class="assignment-layout">


                <!-- ========================================= -->
                <!-- ASSIGN TRAINEE -->
                <!-- ========================================= -->

                <section class="trainee-assignment-card">

                    <h2>
                        <?= adminH(t('a150j_75e500fa8861')) ?>
                    </h2>

                    <p>
                        <?= adminH(t('a150j_70dcb35ca7dc')) ?>
                    </p>


                    <?php
                    $availableTrainees = [];

                    foreach ($trainees as $trainee) {

                        if (
                            !in_array(
                                (int)$trainee['id'],
                                $assignedTraineeIds,
                                true
                            )
                        ) {
                            $availableTrainees[] = $trainee;
                        }
                    }
                    ?>


                    <?php if (empty($availableTrainees)): ?>

                        <div class="empty-message">
                            <?= adminH(t('a150j_49a720117b42')) ?>
                        </div>

                    <?php else: ?>

                        <form
                            method="POST"
                            class="simple-form"
                        ><?php ux_csrf_field(); ?>
<input type="hidden" name="lang" value="<?= adminH(currentLanguage()) ?>">

                            <input
                                type="hidden"
                                name="action"
                                value="assign"
                            >

                            <input
                                type="hidden"
                                name="professor_id"
                                value="<?= (int)$professorId ?>"
                            >


                            <div class="form-field">

                                <label for="trainee_id">
                                    <?= adminH(t('a150j_37a34ba5a7a8')) ?>
                                </label>

                                <select
                                    id="trainee_id"
                                    name="trainee_id"
                                    required
                                >

                                    <option value="">
                                        <?= adminH(t('a150j_6e9df811f6c8')) ?>
                                    </option>


                                    <?php foreach (
                                        $availableTrainees as $trainee
                                    ): ?>

                                        <option
                                            value="<?= (int)$trainee['id'] ?>"
                                        >

                                            <?= htmlspecialchars(
                                                $trainee['first_name']
                                                . ' '
                                                . $trainee['last_name']
                                                . ' - '
                                                . $trainee['email']
                                            ) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <button
                                type="submit"
                                class="btn"
                            >
                                <?= adminH(t('a150j_75e500fa8861')) ?>
                            </button>

                        </form>

                    <?php endif; ?>


                    <div class="permission-note">

                        <strong>
                            <?= adminH(t('a150j_201b266c8e7b')) ?>
                        </strong>

                        <p class="mb-0">
                            <?= adminH(t('a150j_abc788eece0d')) ?>
                        </p>

                    </div>

                </section>


                <!-- ========================================= -->
                <!-- CURRENT ASSIGNMENTS -->
                <!-- ========================================= -->

                <section class="trainee-assignment-card">

                    <h2>
                        <?= adminH(t('assigned_trainees')) ?>
                    </h2>

                    <p>
                        <?= adminH(t('a150j_d28372b2309c')) ?>
                        <strong>
                            <?= count($assignments) ?>
                        </strong>
                    </p>


                    <?php if (empty($assignments)): ?>

                        <div class="empty-message">

                            <?= adminH(t('a150j_d614237c1e0a')) ?>

                        </div>

                    <?php else: ?>

                        <div class="trainee-table-wrapper">

                            <table class="trainee-table">

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
                                        <?= adminH(t('h150_status')) ?>
                                    </th>

                                    <th>
                                        <?= adminH(t('h150_action')) ?>
                                    </th>

                                </tr>

                                </thead>


                                <tbody>

                                <?php foreach (
                                    $assignments as $assignment
                                ): ?>

                                    <tr>

                                        <td>

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $assignment['first_name']
                                                    . ' '
                                                    . $assignment['last_name']
                                                ) ?>

                                            </strong>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $assignment['email']
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= (int)$assignment[
                                                'attempt_count'
                                            ] ?>

                                        </td>


                                        <td>

                                            <span class="assigned-badge">
                                                <?= adminH(t('h150_assigned')) ?>
                                            </span>

                                        </td>


                                        <td>

                                            <form
                                                method="POST"
                                                class="remove-form"
                                            ><?php ux_csrf_field(); ?>
<input type="hidden" name="lang" value="<?= adminH(currentLanguage()) ?>">

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="remove"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="professor_id"
                                                    value="<?= (int)$professorId ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="assignment_id"
                                                    value="<?= (int)$assignment[
                                                        'assignment_id'
                                                    ] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="remove-button"
                                                    data-confirm="<?= adminH(t('a150j_d217ad1d8ee4')) ?>" onclick="return confirm(this.dataset.confirm);"
                                                >
                                                    <?= adminH(t('a150j_c3812fc4acb8')) ?>
                                                </button>

                                            </form>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                    <?php endif; ?>

                </section>

            </div>


            <!-- ============================================= -->
            <!-- COURSE PERMISSION LINK -->
            <!-- ============================================= -->

            <section class="trainee-assignment-card">

                <h2>
                    <?= adminH(t('a150j_5184a6139b84')) ?>
                </h2>

                <p>
                    <?= adminH(t('a150j_5a0b41f7a259')) ?>
                </p>

                <p class="actions">

                    <a
                        class="btn btn-secondary"
                        href="professor_content.php?lang=<?= adminH(currentLanguage()) ?>&amp;professor_id=<?= (int)$professorId ?>"
                    >
                        <?= adminH(t('manage_course_content')) ?>
                    </a>

                </p>

            </section>


        <?php endif; ?>


    </main>


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