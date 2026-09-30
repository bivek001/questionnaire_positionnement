<?php
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/admin_language.php';

require_once 'auth.php';
require_once '../config/database.php';

require_once __DIR__ . '/../includes/account_security.php';
ux_csrf_check();
$message = '';
$error = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = filter_var($_POST['professor_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if (!$id) $error = t('ux_professor_missing');
    elseif (($_POST['confirm_delete'] ?? '') !== '1') $error = t('ux_confirm_required');
    else {
        try {
            ux_delete_professor($pdo, $id);
            $message = t('ux_delete_success');
        } catch (Throwable $e) {
            $error = $e instanceof PDOException ? t('ux_delete_failed') : $e->getMessage();
        }
    }
}

// ======================================================
// CREATE PROFESSOR
// ======================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'create'
) {

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirmPassword =
        $_POST['confirm_password'] ?? '';


    // --------------------------------------------------
    // VALIDATION
    // --------------------------------------------------

    if (
        $firstName === ''
        || $lastName === ''
        || $email === ''
        || $username === ''
        || $password === ''
    ) {

        $error = t('please_complete_required_fields');

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = t('valid_email_required');

    } elseif (strlen($password) < 8) {

        $error =
            t('a150j_da9544e3897c');

    } elseif ($password !== $confirmPassword) {

        $error =
            t('new_password_confirmation_error');

    } else {

        // --------------------------------------------------
        // CHECK EMAIL / USERNAME
        // --------------------------------------------------

        $stmt = $pdo->prepare(
            "SELECT id
             FROM professors
             WHERE email = ?
                OR username = ?
             LIMIT 1"
        );

        $stmt->execute([
            $email,
            $username
        ]);

        if ($stmt->fetch()) {

            $error =
                'A professor with this email address or username already exists.';

        } else {

            // --------------------------------------------------
            // CREATE ACCOUNT
            // --------------------------------------------------

            try {

                $hashedPassword =
                    password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                $stmt = $pdo->prepare(
                    "INSERT INTO professors (
                        first_name,
                        last_name,
                        email,
                        username,
                        password,
                        is_active
                    )
                    VALUES (?, ?, ?, ?, ?, 1)"
                );

                $stmt->execute([
                    $firstName,
                    $lastName,
                    $email,
                    $username,
                    $hashedPassword
                ]);

                $message =
                    t('create_professor_success');

                // Clear fields after successful creation.
                $firstName = '';
                $lastName = '';
                $email = '';
                $username = '';

            } catch (PDOException $e) {

                $error =
                    t('a150j_529ecd768bf0');
            }
        }
    }
}


// ======================================================
// ACTIVATE / DEACTIVATE PROFESSOR
// ======================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'toggle_status'
) {

    $professorId =
        filter_input(
            INPUT_POST,
            'professor_id',
            FILTER_VALIDATE_INT
        );

    if (!$professorId) {

        $error = t('a150j_d1ab88fbc510');

    } else {

        $stmt = $pdo->prepare(
            "SELECT
                id,
                is_active
             FROM professors
             WHERE id = ?
             LIMIT 1"
        );

        $stmt->execute([$professorId]);

        $professor =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$professor) {

            $error =
                t('p150i_701d8d518286');

        } else {

            $newStatus =
                (int)$professor['is_active'] === 1
                    ? 0
                    : 1;

            $stmt = $pdo->prepare(
                "UPDATE professors
                 SET is_active = ?
                 WHERE id = ?"
            );

            $stmt->execute([
                $newStatus,
                $professorId
            ]);

            if ($newStatus === 1) {

                $message =
                    t('a150j_67c325d22a10');

            } else {

                $message =
                    t('a150j_bbf4edf39d70');
            }
        }
    }
}


// ======================================================
// LOAD PROFESSORS
// ======================================================

$stmt = $pdo->query(
    "SELECT
        p.id,
        p.first_name,
        p.last_name,
        p.email,
        p.username,
        p.is_active,
        p.created_at,

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
        p.last_name ASC,
        p.first_name ASC,
        p.id ASC"
);

$professors =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// STATISTICS
// ======================================================

$totalProfessors =
    count($professors);

$activeProfessors = 0;
$inactiveProfessors = 0;

foreach ($professors as $professor) {

    if ((int)$professor['is_active'] === 1) {

        $activeProfessors++;

    } else {

        $inactiveProfessors++;
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
        <?= adminH(t('professor_management')) ?>
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <style>

        .professor-management-grid {
            display: grid;
            grid-template-columns:
                minmax(300px, 420px)
                minmax(0, 1fr);
            gap: 24px;
            align-items: start;
        }

        .professor-form-card,
        .professor-list-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow:
                0 8px 24px rgba(15, 23, 42, 0.05);
        }

        .professor-form-card h2,
        .professor-list-card h2 {
            margin-top: 0;
        }

        .professor-form-card form {
            margin: 0;
            padding: 0;
            border: 0;
            box-shadow: none;
            background: transparent;
        }

        .professor-form-group {
            margin-bottom: 18px;
        }

        .professor-form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: 700;
        }

        .professor-form-group input {
            width: 100%;
        }

        .professor-help {
            margin-top: 6px;
            color: #64748b;
            font-size: 0.9rem;
        }

        .professor-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .professor-status-active {
            color: #166534;
            background: #dcfce7;
        }

        .professor-status-inactive {
            color: #991b1b;
            background: #fee2e2;
        }

        .professor-table-wrapper {
            overflow-x: auto;
        }

        .professor-table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
        }

        .professor-table th,
        .professor-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: middle;
        }

        .professor-table th {
            color: #334155;
            background: #f8fafc;
        }

        .professor-inline-form {
            display: inline;
            margin: 0;
            padding: 0;
            border: 0;
            background: transparent;
            box-shadow: none;
        }

        .professor-small-button {
            padding: 7px 11px;
            border: 0;
            border-radius: 8px;
            font: inherit;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
        }

        .professor-activate-button {
            color: #ffffff;
            background: #15803d;
        }

        .professor-deactivate-button {
            color: #ffffff;
            background: #b91c1c;
        }

        .professor-stats {
            margin-bottom: 24px;
        }

        .professor-empty {
            padding: 24px;
            text-align: center;
            color: #64748b;
            background: #f8fafc;
            border-radius: 12px;
        }

        @media (max-width: 1050px) {

            .professor-management-grid {
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
            <?= adminH(t('professor_management')) ?>
        </h1>

        <p>
            <?= adminH(t('a150j_3cc5d068fa2f')) ?>
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
        <!-- PAGE INTRODUCTION -->
        <!-- ================================================= -->

        <section>

            <h2>
                <?= adminH(t('professors')) ?>
            </h2>

            <p>
                <?= adminH(t('a150j_23bb96606fc1')) ?>
            </p>

        </section>


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
        <!-- STATISTICS -->
        <!-- ================================================= -->

        <section class="professor-stats">

            <div class="stats-grid">

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

            </div>

        </section>


        <!-- ================================================= -->
        <!-- MANAGEMENT AREA -->
        <!-- ================================================= -->

        <div class="professor-management-grid">


            <!-- ============================================= -->
            <!-- CREATE PROFESSOR -->
            <!-- ============================================= -->

            <section class="professor-form-card">

                <h2>
                    <?= adminH(t('create_professor')) ?>
                </h2>

                <p class="text-muted">
                    <?= adminH(t('a150j_100fc445babc')) ?>
                </p>


                <form method="POST"><?php ux_csrf_field(); ?>
<input type="hidden" name="lang" value="<?= adminH(currentLanguage()) ?>">

                    <input
                        type="hidden"
                        name="action"
                        value="create"
                    >


                    <div class="professor-form-group">

                        <label for="first_name">
                            <?= adminH(t('a150j_7ca57be57e8d')) ?>
                        </label>

                        <input
                            type="text"
                            id="first_name"
                            name="first_name"
                            maxlength="100"
                            value="<?= htmlspecialchars(
                                $firstName ?? ''
                            ) ?>"
                            required
                        >

                    </div>


                    <div class="professor-form-group">

                        <label for="last_name">
                            <?= adminH(t('a150j_b330e414a35b')) ?>
                        </label>

                        <input
                            type="text"
                            id="last_name"
                            name="last_name"
                            maxlength="100"
                            value="<?= htmlspecialchars(
                                $lastName ?? ''
                            ) ?>"
                            required
                        >

                    </div>


                    <div class="professor-form-group">

                        <label for="email">
                            <?= adminH(t('a150j_d652d515eb87')) ?>
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            maxlength="255"
                            value="<?= htmlspecialchars(
                                $email ?? ''
                            ) ?>"
                            required
                        >

                    </div>


                    <div class="professor-form-group">

                        <label for="username">
                            <?= adminH(t('a150j_9041ca608c0d')) ?>
                        </label>

                        <input
                            type="text"
                            id="username"
                            name="username"
                            maxlength="100"
                            value="<?= htmlspecialchars(
                                $username ?? ''
                            ) ?>"
                            autocomplete="off"
                            required
                        >

                    </div>


                    <div class="professor-form-group">

                        <label for="password">
                            <?= adminH(t('a150j_7c6dc119843f')) ?>
                        </label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >

                        <div class="professor-help">
                            <?= adminH(t('password_minimum')) ?>
                        </div>

                    </div>


                    <div class="professor-form-group">

                        <label for="confirm_password">
                            <?= adminH(t('a150j_a931c70efc5d')) ?>
                        </label>

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >

                    </div>


                    <button
                        type="submit"
                        class="btn"
                    >
                        <?= adminH(t('create_professor')) ?>
                    </button>

                </form>

            </section>


            <!-- ============================================= -->
            <!-- PROFESSOR LIST -->
            <!-- ============================================= -->

            <section class="professor-list-card">

                <h2>
                    <?= adminH(t('professor_accounts')) ?>
                </h2>

                <p class="text-muted">
                    <?= adminH(t('a150j_37e01a975572')) ?>
                </p>


                <?php if (empty($professors)): ?>

                    <div class="professor-empty">

                        <?= adminH(t('no_professors_found')) ?>

                    </div>

                <?php else: ?>

                    <div class="professor-table-wrapper">

                        <table class="professor-table">

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
                                    <?= adminH(t('content')) ?>
                                </th>

                                <th>
                                    <?= adminH(t('trainees')) ?>
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
                                $professors as $professor
                            ): ?>

                                <tr>

                                    <td>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $professor[
                                                    'first_name'
                                                ]
                                                . ' '
                                                . $professor[
                                                    'last_name'
                                                ]
                                            ) ?>

                                        </strong>

                                        <br>

                                        <small class="text-muted">

                                            <?= adminH(t('a150j_22d435a37118')) ?>
                                            <?= htmlspecialchars(
                                                date(
                                                    'd/m/Y',
                                                    strtotime(
                                                        $professor[
                                                            'created_at'
                                                        ]
                                                    )
                                                )
                                            ) ?>

                                        </small>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $professor[
                                                'username'
                                            ]
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $professor[
                                                'email'
                                            ]
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= (int)$professor[
                                            'content_assignment_count'
                                        ] ?>

                                        <?= adminH(t('a150j_9150b88e3eba')) ?>

                                    </td>


                                    <td>

                                        <?= (int)$professor[
                                            'trainee_assignment_count'
                                        ] ?>

                                        <?= adminH(t('a150j_31c0528b7cc4')) ?>

                                    </td>


                                    <td>

                                        <?php if (
                                            (int)$professor[
                                                'is_active'
                                            ] === 1
                                        ): ?>

                                            <span
                                                class="
                                                    professor-status
                                                    professor-status-active
                                                "
                                            >
                                                <?= adminH(t('active')) ?>
                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="
                                                    professor-status
                                                    professor-status-inactive
                                                "
                                            >
                                                <?= adminH(t('inactive')) ?>
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <div
                                            style="
                                                display: flex;
                                                flex-wrap: wrap;
                                                gap: 6px;
                                                align-items: center;
                                            "
                                        >

                                            <!-- ========================================= -->
                                            <!-- PROFESSOR SUPERVISION -->
                                            <!-- ========================================= -->

                                            <a
                                                href="professor_details.php?lang=<?= adminH(currentLanguage()) ?>&amp;id=<?= (int)$professor['id'] ?>"
                                                class="btn"
                                                style="
                                                    padding: 7px 11px;
                                                    font-size: 0.85rem;
                                                "
                                            >
                                                <?= adminH(t('supervise')) ?>
                                            </a>


                                            <!-- ========================================= -->
                                            <!-- COURSE ACCESS -->
                                            <!-- ========================================= -->

                                            <a
                                                href="professor_content.php?lang=<?= adminH(currentLanguage()) ?>&amp;professor_id=<?= (int)$professor['id'] ?>"
                                                class="btn btn-secondary"
                                                style="
                                                    padding: 7px 11px;
                                                    font-size: 0.85rem;
                                                "
                                            >
                                                <?= adminH(t('course_access')) ?>
                                            </a>


                                            <!-- ========================================= -->
                                            <!-- TRAINEE ACCESS -->
                                            <!-- ========================================= -->

                                            <a
                                                href="professor_trainees.php?lang=<?= adminH(currentLanguage()) ?>&amp;professor_id=<?= (int)$professor['id'] ?>"
                                                class="btn btn-secondary"
                                                style="
                                                    padding: 7px 11px;
                                                    font-size: 0.85rem;
                                                "
                                            >
                                                <?= adminH(t('trainees')) ?>
                                            </a>


                                            <!-- ========================================= -->
<form method="post" class="delete-professor-form">
<?php ux_csrf_field(); ?>
<input type="hidden" name="action" value="delete">
<input type="hidden" name="professor_id" value="<?= (int)$professor['id'] ?>">
<label><input type="checkbox" name="confirm_delete" value="1" required> <?= adminH(t('ux_confirm_checkbox')) ?></label>
<button type="submit" class="btn btn-danger" data-confirm="<?= adminH(t('ux_delete_confirm')) ?>" onclick="return confirm(this.dataset.confirm)"><?= adminH(t('ux_delete_professor')) ?></button>
</form>
                                            <!-- ACTIVATE / DEACTIVATE -->
                                            <!-- ========================================= -->

                                            <form
                                                method="POST"
                                                class="professor-inline-form"
                                            ><?php ux_csrf_field(); ?>
<input type="hidden" name="lang" value="<?= adminH(currentLanguage()) ?>">

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="toggle_status"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="professor_id"
                                                    value="<?= (int)$professor['id'] ?>"
                                                >


                                                <?php if ((int)$professor['is_active'] === 1): ?>

                                                    <button
                                                        type="submit"
                                                        class="
                                                            professor-small-button
                                                            professor-deactivate-button
                                                        "
                                                        data-confirm="<?= adminH(t('deactivate_professor_confirm')) ?>" onclick="return confirm(this.dataset.confirm);"
                                                    >
                                                        <?= adminH(t('deactivate')) ?>
                                                    </button>

                                                <?php else: ?>

                                                    <button
                                                        type="submit"
                                                        class="
                                                            professor-small-button
                                                            professor-activate-button
                                                        "
                                                    >
                                                        <?= adminH(t('activate')) ?>
                                                    </button>

                                                <?php endif; ?>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </section>

        </div>

    </main>


    <footer>

        <p class="actions">

            <a
                class="btn btn-secondary"
                href="index.php?lang=<?= adminH(currentLanguage()) ?>"
            >
                <?= adminH(t('a150j_1e865a5a6da9')) ?>
            </a>

            <a
                class="btn btn-secondary"
                href="../professor/login.php?lang=<?= adminH(currentLanguage()) ?>"
            >
                <?= adminH(t('professor_login_page')) ?>
            </a>

        </p>

    </footer>

</div>

</body>
</html>