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
// LOAD ALL PROFESSORS
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
// CHECK SELECTED PROFESSOR
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
// CREATE ASSIGNMENT
// ======================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'assign'
    && $selectedProfessor
) {

    $themeId = filter_input(
        INPUT_POST,
        'theme_id',
        FILTER_VALIDATE_INT
    );

    $chapterValue =
        trim($_POST['chapter_id'] ?? '');

    $chapterId = null;


    if (!$themeId) {

        $error = t('p150i_c67c4c3d81c5');

    } else {

        // --------------------------------------------------
        // CHECK THEME
        // --------------------------------------------------

        $stmt = $pdo->prepare(
            "SELECT id, name
             FROM themes
             WHERE id = ?
             LIMIT 1"
        );

        $stmt->execute([$themeId]);

        $theme =
            $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$theme) {

            $error = t('a150j_d91777449728');

        } else {

            // --------------------------------------------------
            // CHECK CHAPTER IF ONE WAS SELECTED
            // --------------------------------------------------

            if ($chapterValue !== '') {

                $chapterId =
                    filter_var(
                        $chapterValue,
                        FILTER_VALIDATE_INT
                    );

                if (!$chapterId) {

                    $error =
                        t('a150j_4b159c693ad5');

                } else {

                    $stmt = $pdo->prepare(
                        "SELECT id, title
                         FROM chapters
                         WHERE id = ?
                           AND theme_id = ?
                         LIMIT 1"
                    );

                    $stmt->execute([
                        $chapterId,
                        $themeId
                    ]);

                    $chapter =
                        $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$chapter) {

                        $error =
                            t('p150i_9291a485ce2a');
                    }
                }
            }


            // --------------------------------------------------
            // CHECK FOR EXISTING ACCESS
            // --------------------------------------------------

            if ($error === '') {

                /*
                 * If assigning the entire theme:
                 * prevent another entire-theme assignment.
                 *
                 * If assigning one chapter:
                 * prevent duplicate chapter assignment AND
                 * prevent it when the professor already has
                 * access to the entire theme.
                 */

                if ($chapterId === null) {

                    $stmt = $pdo->prepare(
                        "SELECT id
                         FROM professor_content_assignments
                         WHERE professor_id = ?
                           AND theme_id = ?
                           AND chapter_id IS NULL
                         LIMIT 1"
                    );

                    $stmt->execute([
                        $professorId,
                        $themeId
                    ]);

                } else {

                    $stmt = $pdo->prepare(
                        "SELECT id
                         FROM professor_content_assignments
                         WHERE professor_id = ?
                           AND theme_id = ?
                           AND (
                                chapter_id IS NULL
                                OR chapter_id = ?
                           )
                         LIMIT 1"
                    );

                    $stmt->execute([
                        $professorId,
                        $themeId,
                        $chapterId
                    ]);
                }

                $existingAssignment =
                    $stmt->fetch(PDO::FETCH_ASSOC);


                if ($existingAssignment) {

                    $error =
                        t('a150j_2e9ebe285f67');

                } else {

                    // ------------------------------------------
                    // ENTIRE THEME REPLACES CHAPTER ASSIGNMENTS
                    // ------------------------------------------

                    try {

                        $pdo->beginTransaction();


                        if ($chapterId === null) {

                            /*
                             * Entire-theme access makes existing
                             * chapter-specific assignments for
                             * this same theme unnecessary.
                             */

                            $stmt = $pdo->prepare(
                                "DELETE FROM
                                    professor_content_assignments
                                 WHERE professor_id = ?
                                   AND theme_id = ?"
                            );

                            $stmt->execute([
                                $professorId,
                                $themeId
                            ]);
                        }


                        // --------------------------------------
                        // INSERT ASSIGNMENT
                        // --------------------------------------

                        $stmt = $pdo->prepare(
                            "INSERT INTO
                                professor_content_assignments
                                (
                                    professor_id,
                                    theme_id,
                                    chapter_id
                                )
                             VALUES (?, ?, ?)"
                        );

                        $stmt->execute([
                            $professorId,
                            $themeId,
                            $chapterId
                        ]);

                        $pdo->commit();

                        if ($chapterId === null) {

                            $message =
                                t('a150j_0da94c7c7ec3');

                        } else {

                            $message =
                                t('a150j_22698eedeb38');
                        }

                    } catch (PDOException $e) {

                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }

                        $error =
                            t('a150j_2912fd41094e');
                    }
                }
            }
        }
    }
}


// ======================================================
// REMOVE ASSIGNMENT
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

        $error = t('a150j_50869adb6778');

    } else {

        /*
         * professor_id is included in the DELETE condition.
         * This prevents deleting another professor's
         * assignment by changing the form ID.
         */

        $stmt = $pdo->prepare(
            "DELETE FROM professor_content_assignments
             WHERE id = ?
               AND professor_id = ?"
        );

        $stmt->execute([
            $assignmentId,
            $professorId
        ]);

        if ($stmt->rowCount() > 0) {

            $message =
                t('a150j_70090d3a663d');

        } else {

            $error =
                t('a150j_05214e299c3a');
        }
    }
}


// ======================================================
// LOAD ACTIVE THEMES
// ======================================================

$stmt = $pdo->query(
    "SELECT
        id,
        name
     FROM themes
     WHERE is_active = 1
     ORDER BY display_order ASC, name ASC"
);

$themes =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// LOAD ACTIVE CHAPTERS
// ======================================================

$stmt = $pdo->query(
    "SELECT
        id,
        theme_id,
        title,
        display_order
     FROM chapters
     WHERE is_active = 1
     ORDER BY
        theme_id ASC,
        display_order ASC,
        title ASC"
);

$chapters =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// LOAD CURRENT ASSIGNMENTS
// ======================================================

$assignments = [];

if ($selectedProfessor) {

    $stmt = $pdo->prepare(
        "SELECT
            pca.id,
            pca.theme_id,
            pca.chapter_id,
            pca.created_at,

            t.name AS theme_title,

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
            CASE
                WHEN pca.chapter_id IS NULL
                THEN 0
                ELSE 1
            END ASC,
            c.display_order ASC,
            c.title ASC"
    );

    $stmt->execute([$professorId]);

    $assignments =
        $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        <?= adminH(t('a150j_1efd7cf9c62f')) ?>
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <style>

        .assignment-layout {
            display: grid;
            grid-template-columns:
                minmax(300px, 420px)
                minmax(0, 1fr);
            gap: 24px;
            align-items: start;
        }

        .assignment-card {
            padding: 24px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow:
                0 8px 24px rgba(15, 23, 42, 0.05);
        }

        .assignment-card h2 {
            margin-top: 0;
        }

        .assignment-card form {
            margin: 0;
            padding: 0;
            background: transparent;
            border: 0;
            box-shadow: none;
        }

        .assignment-field {
            margin-bottom: 18px;
        }

        .assignment-field label {
            display: block;
            margin-bottom: 7px;
            font-weight: 700;
        }

        .assignment-field select {
            width: 100%;
        }

        .assignment-professor {
            margin-bottom: 24px;
            padding: 18px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 12px;
        }

        .assignment-professor h2 {
            margin: 0 0 6px;
        }

        .assignment-table-wrapper {
            overflow-x: auto;
        }

        .assignment-table {
            width: 100%;
            border-collapse: collapse;
        }

        .assignment-table th,
        .assignment-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: middle;
        }

        .assignment-table th {
            background: #f8fafc;
        }

        .access-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .access-theme {
            color: #166534;
            background: #dcfce7;
        }

        .access-chapter {
            color: #1d4ed8;
            background: #dbeafe;
        }

        .remove-assignment-form {
            display: inline;
            margin: 0;
            padding: 0;
            background: transparent;
            border: 0;
            box-shadow: none;
        }

        .remove-assignment-button {
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

        .empty-assignment {
            padding: 24px;
            color: #64748b;
            text-align: center;
            background: #f8fafc;
            border-radius: 12px;
        }

        .access-explanation {
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
            <?= adminH(t('a150j_1efd7cf9c62f')) ?>
        </h1>

        <p>
            <?= adminH(t('a150j_84c9356a18da')) ?>
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
        <!-- PROFESSOR SELECTOR -->
        <!-- ================================================= -->

        <section class="assignment-card">

            <h2>
                <?= adminH(t('a150j_130ef6615a22')) ?>
            </h2>

            <?php if (empty($professors)): ?>

                <p>
                    <?= adminH(t('a150j_3debe1223b87')) ?>
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
                    action="professor_content.php"
                >
<input type="hidden" name="lang" value="<?= adminH(currentLanguage()) ?>">

                    <div class="assignment-field">

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

            <section class="assignment-professor">

                <h2>
                    <?= htmlspecialchars(
                        $selectedProfessor['first_name']
                        . ' '
                        . $selectedProfessor['last_name']
                    ) ?>
                </h2>

                <div>
                    <?= adminH(t('a150j_3806d61c3406')) ?>
                    <strong>
                        <?= htmlspecialchars(
                            $selectedProfessor['username']
                        ) ?>
                    </strong>
                </div>

                <div>
                    <?= adminH(t('p150i_755c8b2a9fb1')) ?>
                    <strong>
                        <?= (int)$selectedProfessor['is_active'] === 1
                            ? t('active')
                            : t('inactive') ?>
                    </strong>
                </div>

            </section>


            <div class="assignment-layout">


                <!-- ========================================= -->
                <!-- ASSIGN CONTENT -->
                <!-- ========================================= -->

                <section class="assignment-card">

                    <h2>
                        <?= adminH(t('a150j_ba32b079f35c')) ?>
                    </h2>

                    <p>
                        <?= adminH(t('a150j_351fc7160b7d')) ?>
                    </p>


                    <?php if (empty($themes)): ?>

                        <p>
                            <?= adminH(t('a150j_e39587cff362')) ?>
                        </p>

                    <?php else: ?>

                        <form method="POST"><?php ux_csrf_field(); ?>
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


                            <div class="assignment-field">

                                <label for="theme_id">
                                    <?= adminH(t('p150i_a214cea88890')) ?>
                                </label>

                                <select
                                    id="theme_id"
                                    name="theme_id"
                                    required
                                >

                                    <option value="">
                                        <?= adminH(t('p150i_3980d1266628')) ?>
                                    </option>

                                    <?php foreach (
                                        $themes as $theme
                                    ): ?>

                                        <option
                                            value="<?= (int)$theme['id'] ?>"
                                        >
                                            <?= htmlspecialchars(
                                                $theme['name']
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <div class="assignment-field">

                                <label for="chapter_id">
                                    <?= adminH(t('h150_chapter')) ?>
                                </label>

                                <select
                                    id="chapter_id"
                                    name="chapter_id"
                                >

                                    <option value="">
                                        <?= adminH(t('entire_theme')) ?>
                                    </option>

                                    <?php foreach (
                                        $chapters as $chapter
                                    ): ?>

                                        <option
                                            value="<?= (int)$chapter['id'] ?>"
                                            data-theme-id="<?=
                                                (int)$chapter['theme_id']
                                            ?>"
                                        >
                                            <?= htmlspecialchars(
                                                $chapter['title']
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <button
                                type="submit"
                                class="btn"
                            >
                                <?= adminH(t('a150j_04ec3fc14c53')) ?>
                            </button>

                        </form>


                        <div class="access-explanation">

                            <strong>
                                <?= adminH(t('a150j_4cd7954002ce')) ?>
                            </strong>

                            <p>
                                <strong><?= adminH(t('a150j_4ab0e843eac8')) ?></strong>
                                <?= adminH(t('a150j_24744e38a974')) ?>
                            </p>

                            <p class="mb-0">
                                <strong><?= adminH(t('a150j_01f3515d2eac')) ?></strong>
                                <?= adminH(t('a150j_7c2dc6a0222c')) ?>
                            </p>

                        </div>

                    <?php endif; ?>

                </section>


                <!-- ========================================= -->
                <!-- CURRENT ASSIGNMENTS -->
                <!-- ========================================= -->

                <section class="assignment-card">

                    <h2>
                        <?= adminH(t('a150j_7f62b93de8d4')) ?>
                    </h2>


                    <?php if (empty($assignments)): ?>

                        <div class="empty-assignment">

                            <?= adminH(t('a150j_29a818f623d0')) ?>

                        </div>

                    <?php else: ?>

                        <div class="assignment-table-wrapper">

                            <table class="assignment-table">

                                <thead>

                                <tr>

                                    <th><?= adminH(t('h150_theme')) ?></th>

                                    <th><?= adminH(t('a150j_ec5ba0abb717')) ?></th>

                                    <th><?= adminH(t('h150_assigned')) ?></th>

                                    <th><?= adminH(t('h150_action')) ?></th>

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
                                                    $assignment[
                                                        'theme_title'
                                                    ]
                                                ) ?>
                                            </strong>

                                        </td>


                                        <td>

                                            <?php if (
                                                $assignment[
                                                    'chapter_id'
                                                ] === null
                                            ): ?>

                                                <span
                                                    class="
                                                        access-badge
                                                        access-theme
                                                    "
                                                >
                                                    <?= adminH(t('entire_theme')) ?>
                                                </span>

                                            <?php else: ?>

                                                <span
                                                    class="
                                                        access-badge
                                                        access-chapter
                                                    "
                                                >
                                                    <?= adminH(t('h150_chapter_label')) ?>
                                                    <?= htmlspecialchars(
                                                        $assignment[
                                                            'chapter_title'
                                                        ]
                                                    ) ?>
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                date(
                                                    'd/m/Y H:i',
                                                    strtotime(
                                                        $assignment[
                                                            'created_at'
                                                        ]
                                                    )
                                                )
                                            ) ?>

                                        </td>


                                        <td>

                                            <form
                                                method="POST"
                                                class="
                                                    remove-assignment-form
                                                "
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
                                                    value="<?=
                                                        (int)$professorId
                                                    ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="assignment_id"
                                                    value="<?=
                                                        (int)$assignment['id']
                                                    ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="
                                                        remove-assignment-button
                                                    "
                                                    data-confirm="<?= adminH(t('a150j_3ab16cfbcb30')) ?>" onclick="return confirm(this.dataset.confirm);"
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


<script>

/*
 * Only display chapters belonging to the
 * currently selected theme.
 */

const themeSelect =
    document.getElementById('theme_id');

const chapterSelect =
    document.getElementById('chapter_id');


if (themeSelect && chapterSelect) {

    const chapterOptions =
        Array.from(
            chapterSelect.querySelectorAll(
                'option[data-theme-id]'
            )
        );


    function updateChapterOptions() {

        const selectedTheme =
            themeSelect.value;


        chapterOptions.forEach(function (option) {

            const belongsToTheme =
                option.dataset.themeId ===
                selectedTheme;

            option.hidden = !belongsToTheme;
            option.disabled = !belongsToTheme;
        });


        const currentOption =
            chapterSelect.options[
                chapterSelect.selectedIndex
            ];


        if (
            currentOption
            && currentOption.value !== ''
            && currentOption.disabled
        ) {

            chapterSelect.value = '';
        }
    }


    themeSelect.addEventListener(
        'change',
        updateChapterOptions
    );


    updateChapterOptions();
}

</script>

</body>
</html>