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
// GET TRAINEE
// =====================================
$stmt = $pdo->prepare(
    "SELECT
        id,
        first_name,
        last_name,
        date_of_birth,
        email
     FROM trainees
     WHERE id = ?
     LIMIT 1"
);
$stmt->execute([$traineeId]);
$trainee = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$trainee) {
    die(t('h150_trainee_not_found'));
}
// =====================================
// GET ATTEMPT HISTORY
// =====================================
$stmt = $pdo->prepare(
    "SELECT
        attempts.id,
        attempts.theme_id,
        attempts.passation_type,
        attempts.started_at,
        attempts.completed_at,
        attempts.corrected_at,
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
     ORDER BY
        attempts.started_at DESC,
        attempts.id DESC"
);
$stmt->execute([$traineeId]);
$attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);
// =====================================
// CALCULATE SUMMARY
// =====================================
$totalAttempts = count($attempts);
$initialCount = 0;
$finalCount = 0;
$sortieCount = 0;
$pendingAttempts = 0;
foreach ($attempts as $attempt) {
    if ($attempt['passation_type'] === 'initial') {
        $initialCount++;
    }
    elseif ($attempt['passation_type'] === 'final') {
        $finalCount++;
    }
    elseif ($attempt['passation_type'] === 'sortie') {
        $sortieCount++;
    }
    if (
        (int)$attempt['pending_open_questions'] > 0
    ) {
        $pendingAttempts++;
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
    <title><?= adminH(t('a150j_8083b9b58bba')) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .trainee-details-page .personal-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.25rem; margin: 0 0 1.5rem; }
        .trainee-details-page .personal-details dt { font-weight: 600; margin-bottom: .35rem; }
        .trainee-details-page .personal-details dd { margin: 0; overflow-wrap: anywhere; }
        .trainee-details-page .stats-grid { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
        .trainee-details-page .history-scroll { max-width: 100%; overflow-x: auto; }
        .trainee-details-page .history-scroll table { width: 100%; min-width: 1050px; border-collapse: collapse; }
        .trainee-details-page .history-scroll th, .trainee-details-page .history-scroll td { padding: .85rem; text-align: left; vertical-align: top; border-bottom: 1px solid #e2e8f0; }
        .trainee-details-page .history-scroll th { background: #f8fafc; }
        .trainee-details-page .history-scroll .btn, .trainee-details-page .status { white-space: nowrap; }
        .trainee-details-page .actions, .trainee-details-page .workflow-navigation { display: flex; flex-wrap: wrap; gap: .75rem; }
        .trainee-details-page .card { min-width: 0; }
    </style>
<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>
<body>
<?php require __DIR__ . '/admin_language_switcher.php'; ?>
<div class="container trainee-details-page">
    <header class="admin-header">
    <h1><?= adminH(t('p150i_7b3acab7d31d')) ?></h1>
    <p><?= adminH(t('h150_logged_in_as')) ?> <?= htmlspecialchars($_SESSION['admin_username'] ?? t('admin')) ?></p>
    </header>
    <?php require 'admin_nav.php'; ?>
<h1><?= adminH(t('a150j_8083b9b58bba')) ?></h1>
<!-- =================================
     PERSONAL INFORMATION
================================== -->
<section class="card">
<h2><?= adminH(t('a150j_c4b37e84b553')) ?></h2>
<dl class="personal-details">
    <div>
        <dt class="text-muted">
            <?= adminH(t('trainee_id')) ?>
        </dt>
        <dd>
            <?= (int)$trainee['id'] ?>
        </dd>
    </div>
    <div>
        <dt class="text-muted">
            <?= adminH(t('first_name')) ?>
        </dt>
        <dd>
            <?= htmlspecialchars(
                $trainee['first_name']
            ) ?>
        </dd>
    </div>
    <div>
        <dt class="text-muted">
            <?= adminH(t('last_name')) ?>
        </dt>
        <dd>
            <?= htmlspecialchars(
                $trainee['last_name']
            ) ?>
        </dd>
    </div>
    <div>
        <dt class="text-muted">
            <?= adminH(t('date_of_birth')) ?>
        </dt>
        <dd>
            <?php if (
                !empty($trainee['date_of_birth'])
            ): ?>
                <?= htmlspecialchars(
                    date(
                        'd/m/Y',
                        strtotime(
                            $trainee['date_of_birth']
                        )
                    )
                ) ?>
            <?php else: ?>
                —
            <?php endif; ?>
        </dd>
    </div>
    <div>
        <dt class="text-muted">
            <?= adminH(t('email')) ?>
        </dt>
        <dd>
            <?= htmlspecialchars(
                $trainee['email']
            ) ?>
        </dd>
    </div>
</dl>
<div class="actions">
<a class="btn btn-secondary" href="edit_trainee.php?lang=<?= adminH(currentLanguage()) ?>&amp;id=<?= (int)$trainee['id'] ?>"
>
    <?= adminH(t('a150j_b2c8c53ae56c')) ?>
</a>
</div></section>
<!-- =================================
     SUMMARY
================================== -->
<section class="card">
<h2><?= adminH(t('a150j_5ec20e9528ca')) ?></h2>
<div class="stats-grid">
    <div class="stat-card"><div class="stat-number"><?= $totalAttempts ?></div><div class="text-muted"><?= adminH(t('total_attempts')) ?></div></div>
    <div class="stat-card"><div class="stat-number"><?= $initialCount ?></div><div class="text-muted"><?= adminH(t('h150_initial')) ?></div></div>
    <div class="stat-card"><div class="stat-number"><?= $finalCount ?></div><div class="text-muted"><?= adminH(t('h150_final')) ?></div></div>
    <div class="stat-card"><div class="stat-number"><?= $sortieCount ?></div><div class="text-muted"><?= adminH(t('exit')) ?></div></div>
    <div class="stat-card"><div class="stat-number"><?= $pendingAttempts ?></div><div class="text-muted"><?= adminH(t('h150_pending_correction')) ?></div></div>
</div>
</section>

<!-- =================================
     PASSATION HISTORY
================================== -->
<section class="card">
<h2 id="passation-history"><?= adminH(t('a150j_07eb81b7845e')) ?></h2>
<?php if (empty($attempts)): ?>
    <p>
        <?= adminH(t('a150j_a3fa761dbc0c')) ?>
    </p>
<?php else: ?>
<div class="history-scroll" role="region" aria-labelledby="passation-history" tabindex="0">
<table>
    <thead>
        <tr>
            <th scope="col">
                <?= adminH(t('a150j_d8754cb0c1f2')) ?>
            </th>
            <th scope="col">
                <?= adminH(t('h150_passation')) ?>
            </th>
            <th scope="col">
                <?= adminH(t('h150_theme')) ?>
            </th>
            <th scope="col">
                <?= adminH(t('p150i_66df757e0889')) ?>
            </th>
            <th scope="col">
                <?= adminH(t('p150i_4ab141c17cd9')) ?>
            </th>
            <th scope="col">
                <?= adminH(t('h150_score')) ?>
            </th>
            <th scope="col">
                <?= adminH(t('h150_percentage')) ?>
            </th>
            <th scope="col">
                <?= adminH(t('h150_level')) ?>
            </th>
            <th scope="col">
                <?= adminH(t('h150_status')) ?>
            </th>
            <th scope="col">
                <?= adminH(t('h150_action')) ?>
            </th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($attempts as $attempt): ?>
        <?php
        $maximumScore =
            (float)$attempt['maximum_score'];
        $totalScore =
            (float)$attempt['total_score'];
        if ($maximumScore > 0) {
            $percentage =
                ($totalScore / $maximumScore) * 100;
        } else {
            $percentage = 0;
        }
        $hasPendingGrading =
            (int)$attempt['pending_open_questions'] > 0;
        if ($hasPendingGrading) {
            $level = t('h150_provisional_label');
            $statusText = t('h150_pending_correction');
        } else {
            $level = getPositioningLevel($pdo, $percentage, t('h150_not_defined'));
            $statusText = t('h150_completed');
        }
        ?>
        <tr>
            <!-- ATTEMPT ID -->
            <td>
                <?= (int)$attempt['id'] ?>
            </td>
            <!-- PASSATION TYPE -->
            <td>
                <?= htmlspecialchars(
                    adminEnumLabel($attempt['passation_type'])
                ) ?>
            </td>
            <!-- THEME -->
            <td>
                <?= htmlspecialchars(
                    $attempt['theme_name']
                    ?? t('a150j_029a6fc14450')
                ) ?>
            </td>
            <!-- DATE DE PASSATION -->
            <td>
                <?php
                $passationDate =
                    $attempt['completed_at']
                    ?? $attempt['started_at'];
                ?>
                <?php if ($passationDate): ?>
                    <?= htmlspecialchars(
                        date(
                            'd/m/Y H:i',
                            strtotime($passationDate)
                        )
                    ) ?>
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>
            <!-- DATE DE CORRECTION -->
            <td>
                <?php if (
                    !empty($attempt['corrected_at'])
                ): ?>
                    <?= htmlspecialchars(
                        date(
                            'd/m/Y H:i',
                            strtotime(
                                $attempt['corrected_at']
                            )
                        )
                    ) ?>
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>
            <!-- SCORE -->
            <td>
                <?= htmlspecialchars(
                    (string)$totalScore
                ) ?>
                /
                <?= htmlspecialchars(
                    (string)$maximumScore
                ) ?>
            </td>
            <!-- PERCENTAGE -->
            <td>
                <?= number_format(
                    $percentage,
                    1
                ) ?>%
            </td>
            <!-- LEVEL -->
            <td>
                <span class="<?= $hasPendingGrading ? 'status status-inactive' : '' ?>"><?= htmlspecialchars($level) ?></span>
            </td>
            <!-- STATUS -->
            <td>
                <span class="status <?= $hasPendingGrading ? 'status-inactive' : 'status-active' ?>"><?= htmlspecialchars(
                    $statusText
                ) ?></span>
            </td>
            <!-- ACTION -->
            <td>
                <a class="btn" href="attempt_details.php?lang=<?= adminH(currentLanguage()) ?>&amp;id=<?= (int)$attempt['id'] ?>"
                >
                    <?= adminH(t('view_attempt')) ?>
                </a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
</section>
<div class="actions">
    <a class="btn btn-secondary" href="edit_trainee.php?lang=<?= adminH(currentLanguage()) ?>&amp;id=<?= (int)$trainee['id'] ?>"
    >
        <?= adminH(t('a150j_b2c8c53ae56c')) ?>
    </a>

    <a class="btn btn-danger" href="delete_trainee.php?lang=<?= adminH(currentLanguage()) ?>&amp;id=<?= (int)$trainee['id'] ?>"
    >
        <?= adminH(t('a150j_65391c355b4a')) ?>
    </a>
</div>
    <nav class="workflow-navigation" aria-label="<?= adminH(t('a150j_024148d6099b')) ?>">
        <a class="btn btn-secondary" href="results.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('a150j_709c4d89c475')) ?></a>

        <a class="btn btn-secondary" href="index.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('admin_dashboard_link')) ?></a>

        <a class="btn btn-secondary" href="../index.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('p150i_ad2638a9d1dd')) ?></a>

        <a class="btn btn-secondary" href="logout.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('h150_logout')) ?></a>
    </nav>
</div>
</body>
</html>