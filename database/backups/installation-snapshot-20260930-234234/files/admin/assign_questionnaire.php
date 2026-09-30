<?php
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/admin_language.php';

require_once 'auth.php';

require_once '../config/database.php';

$message = '';

$error = '';

// =====================================

// DELETE PENDING ASSIGNMENT

// =====================================

if (

    $_SERVER['REQUEST_METHOD'] === 'POST' &&

    isset($_POST['delete_assignment'])

) {

    $assignmentId = filter_input(

        INPUT_POST,

        'assignment_id',

        FILTER_VALIDATE_INT

    );

    if (!$assignmentId) {

        $error = t('a150j_50869adb6778');

    } else {

        $stmt = $pdo->prepare(

            "DELETE FROM questionnaire_assignments

             WHERE id = ?

               AND status = 'pending'"

        );

        $stmt->execute([

            $assignmentId

        ]);

        if ($stmt->rowCount() === 1) {

            $message =

                t('a150j_03b55ed6e469');

        } else {

            $error =

                t('a150j_6fca4afb88b6');

        }

    }

}

// =====================================

// GET TRAINEES

// =====================================

$stmt = $pdo->query(

    "SELECT

        id,

        first_name,

        last_name,

        email

     FROM trainees

     ORDER BY last_name ASC,

              first_name ASC"

);

$trainees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================================

// GET ACTIVE THEMES

// =====================================

$stmt = $pdo->query(

    "SELECT

        id,

        name

     FROM themes

     WHERE is_active = 1

     ORDER BY name ASC"

);

$themes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================================

// CREATE ASSIGNMENT

// =====================================

if (

    $_SERVER['REQUEST_METHOD'] === 'POST' &&

    isset($_POST['assign_questionnaire'])

) {

    $traineeId = filter_input(

        INPUT_POST,

        'trainee_id',

        FILTER_VALIDATE_INT

    );

    $themeId = filter_input(

        INPUT_POST,

        'theme_id',

        FILTER_VALIDATE_INT

    );

    $passationType =

        $_POST['passation_type'] ?? '';

    $allowedTypes = [

        'initial',

        'final',

        'sortie'

    ];

    // ---------------------------------

    // Basic validation

    // ---------------------------------

    if (!$traineeId) {

        $error = t('a150j_b91585e97517');

    } elseif (!$themeId) {

        $error = t('a150j_5cb69899697f');

    } elseif (

        !in_array(

            $passationType,

            $allowedTypes,

            true

        )

    ) {

        $error = t('a150j_524a362a4ec0');

    } else {

        // ---------------------------------

        // Verify trainee exists

        // ---------------------------------

        $stmt = $pdo->prepare(

            "SELECT id

             FROM trainees

             WHERE id = ?

             LIMIT 1"

        );

        $stmt->execute([$traineeId]);

        if (!$stmt->fetchColumn()) {

            $error = t('h150_trainee_not_found');

        } else {

            // ---------------------------------

            // Verify active theme exists

            // ---------------------------------

            $stmt = $pdo->prepare(

                "SELECT id

                 FROM themes

                 WHERE id = ?

                   AND is_active = 1

                 LIMIT 1"

            );

            $stmt->execute([$themeId]);

            if (!$stmt->fetchColumn()) {

                $error =

                    t('a150j_7834d581b249');

            } else {

                // ---------------------------------

                // Prevent duplicate pending assignment

                // ---------------------------------

                $stmt = $pdo->prepare(

                    "SELECT id

                     FROM questionnaire_assignments

                     WHERE trainee_id = ?

                       AND theme_id = ?

                       AND passation_type = ?

                       AND status = 'pending'

                     LIMIT 1"

                );

                $stmt->execute([

                    $traineeId,

                    $themeId,

                    $passationType

                ]);

                if ($stmt->fetchColumn()) {

                    $error =

                        t('a150j_50644137d431');

                } else {

                    // ---------------------------------

                    // Create assignment

                    // ---------------------------------

                    $stmt = $pdo->prepare(

                        "INSERT INTO questionnaire_assignments

                        (

                            trainee_id,

                            theme_id,

                            passation_type,

                            status

                        )

                        VALUES (?, ?, ?, 'pending')"

                    );

                    $stmt->execute([

                        $traineeId,

                        $themeId,

                        $passationType

                    ]);

                    $message =

                        t('a150j_0af2247ef95b');

                }

            }

        }

    }

}

// =====================================

// GET RECENT ASSIGNMENTS

// =====================================

$stmt = $pdo->query(

    "SELECT

        qa.id,

        qa.passation_type,

        qa.status,

        qa.assigned_at,

        qa.completed_at,

        qa.attempt_id,

        trainees.first_name,

        trainees.last_name,

        themes.name AS theme_name

     FROM questionnaire_assignments qa

     INNER JOIN trainees

        ON qa.trainee_id = trainees.id

     INNER JOIN themes

        ON qa.theme_id = themes.id

     ORDER BY qa.id DESC"

);

$assignments =

    $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="<?= adminH(htmlLanguage()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= adminH(t('a150j_00027c880854')) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .assignments-page .assignment-fields {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(0, 2fr) minmax(0, 1fr);
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        .assignments-page .assignment-fields > div { min-width: 0; }
        .assignments-page .assignment-fields label {
            display: block;
            margin-bottom: .5rem;
            font-weight: 600;
        }
        .assignments-page .assignment-fields select { width: 100%; box-sizing: border-box; }
        .assignments-page .actions { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
        .assignments-page .assignments-table-wrap { max-width: 100%; overflow-x: auto; }
        .assignments-page .assignments-table { width: 100%; min-width: 1050px; border-collapse: collapse; }
        .assignments-page .assignments-table th,
        .assignments-page .assignments-table td {
            padding: .85rem 1rem;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid #e2e8f0;
        }
        .assignments-page .assignments-table th { background: #f8fafc; }
        .assignments-page .assignment-copy { max-width: 24rem; overflow-wrap: anywhere; }
        .assignments-page .assignment-date,
        .assignments-page .assignments-table .btn,
        .assignments-page .status { white-space: nowrap; }
        .assignments-page .assignments-table form { margin: 0; }
        .assignments-page .status-pending { background: #fef3c7; color: #92400e; }
        @media (max-width: 800px) {
            .assignments-page .assignment-fields { grid-template-columns: minmax(0, 1fr); }
        }
    </style>
<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>
<body>
<?php require __DIR__ . '/admin_language_switcher.php'; ?>
<div class="container assignments-page">
    <header class="admin-header">
        <h1><?= adminH(t('p150i_7b3acab7d31d')) ?></h1>
        <p class="text-muted"><?= adminH(t('h150_logged_in_as')) ?> <strong><?= htmlspecialchars($_SESSION['admin_username'] ?? t('admin')) ?></strong></p>
    </header>
    <?php require 'admin_nav.php'; ?>
    <main>
        <h1><?= adminH(t('a150j_00027c880854')) ?></h1>
        <?php if ($message): ?>
            <div class="alert alert-success" role="status"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <section class="card" aria-labelledby="new-assignment-heading">
            <h2 id="new-assignment-heading"><?= adminH(t('a150j_ea7031adfe2e')) ?></h2>
            <form method="POST">
<input type="hidden" name="lang" value="<?= adminH(currentLanguage()) ?>">
                <div class="assignment-fields">
                    <div>
                        <label for="trainee_id"><?= adminH(t('trainee')) ?></label>
                        <select name="trainee_id" id="trainee_id" required>
                            <option value=""><?= adminH(t('a150j_6e9df811f6c8')) ?></option>
                            <?php foreach ($trainees as $trainee): ?>
                                <option value="<?= (int)$trainee['id'] ?>">
                                    #<?= (int)$trainee['id'] ?> - <?= htmlspecialchars($trainee['first_name']) ?> <?= htmlspecialchars($trainee['last_name']) ?> (<?= htmlspecialchars($trainee['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="theme_id"><?= adminH(t('h150_questionnaire')) ?></label>
                        <select name="theme_id" id="theme_id" required>
                            <option value=""><?= adminH(t('a150j_a3f7b93f5046')) ?></option>
                            <?php foreach ($themes as $theme): ?>
                                <option value="<?= (int)$theme['id'] ?>"><?= htmlspecialchars($theme['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="passation_type"><?= adminH(t('passation_type')) ?></label>
                        <select name="passation_type" id="passation_type" required>
                            <option value="initial"><?= adminH(t('h150_initial')) ?></option>
                            <option value="final"><?= adminH(t('h150_final')) ?></option>
                            <option value="sortie"><?= adminH(t('exit')) ?></option>
                        </select>
                    </div>
                </div>
                <div class="actions">
                    <button class="btn" type="submit" name="assign_questionnaire" value="1"><?= adminH(t('a150j_00027c880854')) ?></button>
                </div>
            </form>
        </section>

        <section class="card" aria-labelledby="assignments-heading">
            <h2 id="assignments-heading"><?= adminH(t('a150j_2c7e21f63a47')) ?></h2>
            <?php if (empty($assignments)): ?>
                <p class="text-muted"><?= adminH(t('a150j_a2b9f330ab99')) ?></p>
            <?php else: ?>
                <div class="assignments-table-wrap" role="region" aria-labelledby="assignments-heading" tabindex="0">
                    <table class="assignments-table">
                        <thead>
                            <tr>
                                <th scope="col"><?= adminH(t('p150i_3843971dcfde')) ?></th>
                                <th scope="col"><?= adminH(t('trainee')) ?></th>
                                <th scope="col"><?= adminH(t('h150_questionnaire')) ?></th>
                                <th scope="col"><?= adminH(t('h150_passation')) ?></th>
                                <th scope="col"><?= adminH(t('h150_status')) ?></th>
                                <th scope="col"><?= adminH(t('h150_assigned')) ?></th>
                                <th scope="col"><?= adminH(t('h150_completed')) ?></th>
                                <th scope="col"><?= adminH(t('attempt')) ?></th>
                                <th scope="col"><?= adminH(t('h150_action')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($assignments as $assignment): ?>
                            <tr>
                                <td><?= (int)$assignment['id'] ?></td>
                                <td class="assignment-copy"><?= htmlspecialchars($assignment['first_name']) ?> <?= htmlspecialchars($assignment['last_name']) ?></td>
                                <td class="assignment-copy"><?= htmlspecialchars($assignment['theme_name']) ?></td>
                                <td><?= htmlspecialchars(adminEnumLabel($assignment['passation_type'])) ?></td>
                                <td>
                                    <span class="status <?= $assignment['status'] === 'completed' ? 'status-active' : 'status-inactive' ?><?= $assignment['status'] === 'pending' ? ' status-pending' : '' ?>"><?= htmlspecialchars(adminEnumLabel($assignment['status'])) ?></span>
                                </td>
                                <td class="assignment-date"><?= htmlspecialchars($assignment['assigned_at']) ?></td>
                                <td class="assignment-date">
                                    <?php if ($assignment['completed_at']): ?>
                                        <?= htmlspecialchars($assignment['completed_at']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($assignment['attempt_id']): ?>
                                        <a class="btn btn-secondary" href="attempt_details.php?lang=<?= adminH(currentLanguage()) ?>&amp;id=<?= (int)$assignment['attempt_id'] ?>"><?= adminH(t('view_attempt')) ?></a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($assignment['status'] === 'pending'): ?>
                                        <form method="POST" data-confirm="<?= adminH(t('a150j_12f34b3ce2fa')) ?>
<input type="hidden" name="lang" value="<?= adminH(currentLanguage()) ?>">" onsubmit="return confirm(this.dataset.confirm);">
                                            <input type="hidden" name="assignment_id" value="<?= (int)$assignment['id'] ?>">
                                            <button class="btn btn-danger" type="submit" name="delete_assignment" value="1"><?= adminH(t('a150j_df7d968bb03c')) ?></button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
    <nav class="workflow-navigation" aria-label="<?= adminH(t('a150j_81498e271e7e')) ?>">
        <p class="actions">
            <a class="btn btn-secondary" href="questions.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('a150j_b264dcffd9fc')) ?></a>
            <a class="btn" href="results.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('a150j_c78e4e5cf7f4')) ?></a>
        </p>
        <p class="actions">
            <a class="btn btn-secondary" href="index.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('admin_dashboard_link')) ?></a>
            <a class="btn btn-secondary" href="../index.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('p150i_ad2638a9d1dd')) ?></a>
            <a class="btn btn-secondary" href="logout.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('h150_logout')) ?></a>
        </p>
    </nav>
</div>
</body>
</html>
