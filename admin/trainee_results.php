<?php
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/admin_language.php';

require_once 'auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';


// =====================================
// GET TRAINEE ID
// =====================================

$traineeId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$traineeId) {
    die(t('a150j_2c18b0d14a06'));
}


// =====================================
// GET TRAINEE INFORMATION
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
// GET TRAINEE ATTEMPTS
// =====================================

$stmt = $pdo->prepare(
    "SELECT
        attempts.id,
        attempts.started_at,
        attempts.completed_at,
        attempts.total_score,
        attempts.maximum_score,

        themes.id AS theme_id,
        themes.name AS theme_name

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

<html lang="<?= adminH(htmlLanguage()) ?>">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title><?= adminH(t('p150i_bfcc39a6a126')) ?></title>

<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>

<body>
<?php require __DIR__ . '/admin_language_switcher.php'; ?>


<p>
    <a href="results.php?lang=<?= adminH(currentLanguage()) ?>">
        <?= adminH(t('a150j_e26c94ea8254')) ?>
    </a>
</p>


<h1><?= adminH(t('p150i_bfcc39a6a126')) ?></h1>


<h2>
    <?= htmlspecialchars($trainee['first_name']) ?>

    <?= htmlspecialchars($trainee['last_name']) ?>
</h2>


<p>
    <?= adminH(t('h150_email')) ?>
    <?= htmlspecialchars($trainee['email']) ?>
</p>


<hr>


<h2><?= adminH(t('a150j_26de1a50ab9c')) ?></h2>


<?php if (empty($attempts)): ?>

    <p>
        <?= adminH(t('a150j_08991001d74e')) ?>
    </p>

<?php else: ?>


<table border="1" cellpadding="10">

    <thead>

        <tr>

            <th><?= adminH(t('h150_theme')) ?></th>
            <th><?= adminH(t('h150_score')) ?></th>
            <th><?= adminH(t('h150_maximum')) ?></th>
            <th><?= adminH(t('h150_percentage')) ?></th>
            <th><?= adminH(t('h150_level')) ?></th>
            <th><?= adminH(t('p150i_ecbc89cd37a0')) ?></th>
            <th><?= adminH(t('h150_completed')) ?></th>
            <th><?= adminH(t('h150_action')) ?></th>

        </tr>

    </thead>


    <tbody>

    <?php foreach ($attempts as $attempt): ?>

    <?php

    $score = (float)$attempt['total_score'];
    $maximum = (float)$attempt['maximum_score'];

    if ($maximum > 0) {

        $percentage =
            ($score / $maximum) * 100;

    } else {

        $percentage = 0;
    }


    // Positioning level

        $level = getPositioningLevel($pdo, $percentage, t('h150_not_defined'));

    ?>

        <tr>

            <td>

                <?= htmlspecialchars(
                    $attempt['theme_name']
                    ?? t('h150_unknown')
                ) ?>

            </td>


            <td>

                <?= htmlspecialchars(
                    $attempt['total_score']
                ) ?>

            </td>


            <td>

                <?= htmlspecialchars(
                    $attempt['maximum_score']
                ) ?>

            </td>

            <td>

                <?= number_format(
                    $percentage,
                    1
                ) ?>%

             </td>


            <td>

                <?= htmlspecialchars($level) ?>

            </td>


            <td>

                <?= htmlspecialchars(
                    $attempt['started_at']
                ) ?>

            </td>


            <td>

                <?= htmlspecialchars(
                    $attempt['completed_at']
                    ?? t('a150j_0192502471c4')
                ) ?>

            </td>


            <td>

                <a
                    href="attempt_details.php?lang=<?= adminH(currentLanguage()) ?>&amp;id=<?= (int)$attempt['id'] ?>"
                >
                    <?= adminH(t('a150j_90789c12d073')) ?>
                </a>

            </td>

        </tr>

    <?php endforeach; ?>

    </tbody>

</table>


<?php endif; ?>


</body>

</html>