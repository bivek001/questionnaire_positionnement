<?php

session_start();

require_once 'config/database.php';
require_once __DIR__ . '/includes/trainee_language.php';
require_once 'includes/functions.php';


// =====================================
// CHECK TRAINEE SESSION
// =====================================

if (!isset($_SESSION['trainee_id'])) {

    header('Location: register.php');
    exit;
}

$traineeId = (int)$_SESSION['trainee_id'];


// =====================================
// GET TRAINEE
// =====================================

$stmt = $pdo->prepare(
    "SELECT
        id,
        first_name,
        last_name,
        email
     FROM trainees
     WHERE id = ?"
);

$stmt->execute([$traineeId]);

$trainee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trainee) {
    die(t('h150_trainee_not_found'));
}


// =====================================
// GET ALL ATTEMPTS
// =====================================

$stmt = $pdo->prepare(
    "SELECT
        attempts.id,
        attempts.theme_id,
        attempts.started_at,
        attempts.completed_at,
        attempts.total_score,
        attempts.maximum_score,

        themes.name AS theme_name,

        (
            SELECT COUNT(*)
            FROM responses r
            INNER JOIN questions q
                ON q.id = r.question_id
            WHERE r.attempt_id = attempts.id
              AND q.question_type = 'open'
              AND r.graded_at IS NULL
        ) AS pending_open_questions

     FROM attempts

     LEFT JOIN themes
        ON themes.id = attempts.theme_id

     WHERE attempts.trainee_id = ?

     ORDER BY attempts.started_at DESC"
);

$stmt->execute([$traineeId]);

$attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars(htmlLanguage(), ENT_QUOTES, 'UTF-8') ?>">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title><?= htmlspecialchars(t('h150_my_results'), ENT_QUOTES, 'UTF-8') ?></title>

    <link rel="stylesheet" href="assets/css/trainee_language.css">
<link rel="stylesheet" href="assets/css/ui.css">
<script src="assets/js/ui.js" defer></script>
</head>

<body>
<?php require __DIR__ . '/includes/trainee_language_switcher.php'; ?>


<p>
    <a href="index.php">
        <?= htmlspecialchars(t('h150_back_to_questionnaires'), ENT_QUOTES, 'UTF-8') ?>
    </a>
</p>


<h1><?= htmlspecialchars(t('h150_my_results'), ENT_QUOTES, 'UTF-8') ?></h1>


<h2>
    <?= htmlspecialchars($trainee['first_name']) ?>
    <?= htmlspecialchars($trainee['last_name']) ?>
</h2>


<p>
    <?= htmlspecialchars(t('h150_email'), ENT_QUOTES, 'UTF-8') ?>
    <?= htmlspecialchars($trainee['email']) ?>
</p>


<hr>


<?php if (empty($attempts)): ?>

    <p>
        <?= htmlspecialchars(t('h150_you_have_not_completed_any_questionnaires_yet'), ENT_QUOTES, 'UTF-8') ?>
    </p>

<?php else: ?>


    <table
        border="1"
        cellpadding="10"
        cellspacing="0"
    >

        <thead>

            <tr>

                <th><?= htmlspecialchars(t('h150_theme'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(t('h150_score'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(t('h150_maximum'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(t('h150_percentage'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(t('h150_level'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(t('h150_status'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(t('h150_date'), ENT_QUOTES, 'UTF-8') ?></th>
                <th><?= htmlspecialchars(t('h150_action'), ENT_QUOTES, 'UTF-8') ?></th>

            </tr>

        </thead>


        <tbody>

        <?php foreach ($attempts as $attempt): ?>

            <?php

            $score =
                (float)$attempt['total_score'];

            $maximum =
                (float)$attempt['maximum_score'];


            if ($maximum > 0) {

                $percentage =
                    ($score / $maximum) * 100;

            } else {

                $percentage = 0;
            }


            $pendingCount =
    (int)$attempt['pending_open_questions'];


            if ($pendingCount > 0) {

                $level = t('h150_provisional_label');

            } else {

                $level = getPositioningLevel($pdo, $percentage, t('h150_not_defined'));
            }
        ?>


            <tr>

                <td>
                    <?= htmlspecialchars(
                        $attempt['theme_name']
                        ?? t('h150_unknown')
                    ) ?>
                </td>


                <td>
                    <?= number_format(
                        $score,
                        2
                    ) ?>
                </td>


                <td>
                    <?= number_format(
                        $maximum,
                        2
                    ) ?>
                </td>


                <td>

                    <?= number_format(
                        $percentage,
                        1
                    ) ?>%

                    <?php if ($pendingCount > 0): ?>

                        <small>
                            <?= htmlspecialchars(t('h150_provisional'), ENT_QUOTES, 'UTF-8') ?>
                        </small>

                    <?php endif; ?>

                </td>


                <td>
                    <?= htmlspecialchars($level) ?>
                </td>


                <td>

                    <?php if ($pendingCount > 0): ?>

                        <?= htmlspecialchars(t('h150_pending_review'), ENT_QUOTES, 'UTF-8') ?>

                    <?php else: ?>

                        <?= htmlspecialchars(t('h150_completed'), ENT_QUOTES, 'UTF-8') ?>

                    <?php endif; ?>

                </td>


                <td>
                    <?= htmlspecialchars(
                        $attempt['completed_at']
                        ?? $attempt['started_at']
                    ) ?>
                </td>


                <td>

                    <a
                        href="result.php?id=<?= (int)$attempt['id'] ?>"
                    >
                        <?= htmlspecialchars(t('h150_view_result'), ENT_QUOTES, 'UTF-8') ?>
                    </a>

                </td>

            </tr>


        <?php endforeach; ?>

        </tbody>

    </table>


<?php endif; ?>


</body>

</html>