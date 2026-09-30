<?php

session_start();

require_once 'config/database.php';
require_once __DIR__ . '/includes/trainee_language.php';
require_once 'includes/functions.php';


// ======================================================
// REQUIRE TRAINEE LOGIN
// ======================================================

if (!isset($_SESSION['trainee_id'])) {

    header('Location: trainee_login.php');
    exit;
}

$traineeId = (int)$_SESSION['trainee_id'];


// ======================================================
// GET TRAINEE
// ======================================================

$stmt = $pdo->prepare(
    "SELECT
        id,
        first_name,
        last_name,
        email

     FROM trainees

     WHERE id = ?

     LIMIT 1"
);

$stmt->execute([
    $traineeId
]);

$trainee =
    $stmt->fetch(PDO::FETCH_ASSOC);


if (!$trainee) {

    session_unset();
    session_destroy();

    header('Location: trainee_login.php');
    exit;
}


// ======================================================
// GET QUESTIONNAIRE HISTORY
// ======================================================

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

            FROM responses

            INNER JOIN questions
                ON questions.id =
                   responses.question_id

            WHERE responses.attempt_id =
                  attempts.id

              AND questions.question_type =
                  'open'

              AND responses.graded_at IS NULL

        ) AS pending_open_answers

     FROM attempts

     LEFT JOIN themes
        ON themes.id = attempts.theme_id

     WHERE attempts.trainee_id = ?

     ORDER BY

        COALESCE(
            attempts.completed_at,
            attempts.started_at
        ) DESC,

        attempts.id DESC"
);

$stmt->execute([
    $traineeId
]);

$attempts =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// COUNT COMPLETED ATTEMPTS
// ======================================================

$completedAttemptCount = 0;

foreach ($attempts as $attempt) {

    if (!empty($attempt['completed_at'])) {

        $completedAttemptCount++;
    }
}

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(htmlLanguage(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(t('h150_questionnaire_history_results'), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
<style>
        /* Match the trainee design used in Steps 138A–138E. */
        body.trainee-course {
            margin: 0;
            background: #f3f7f8;
            color: #20343e;
            font-family: system-ui, -apple-system, BlinkMacSystemFont,
                         "Segoe UI", sans-serif;
            line-height: 1.65;
        }

        .trainee-course *,
        .trainee-course *::before,
        .trainee-course *::after {
            box-sizing: border-box;
        }

        .trainee-course .container {
            width: calc(100% - 2rem);
            max-width: 1120px;
            margin: 0 auto;
            padding: 2rem 0;
            background: transparent;
            border: 0;
            box-shadow: none;
        }

        .trainee-course h1,
        .trainee-course h2,
        .trainee-course h3 {
            color: #173b45;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .trainee-course h1 {
            margin: .35rem 0 .75rem;
            font-size: clamp(1.8rem, 4vw, 2.7rem);
        }

        .trainee-course h2 {
            margin: 0 0 .75rem;
            font-size: clamp(1.35rem, 3vw, 1.8rem);
        }

        .trainee-course h3 {
            margin: 0 0 .75rem;
            font-size: 1.35rem;
            line-height: 1.4;
        }

        .trainee-course p {
            margin: 0 0 1rem;
        }

        .trainee-course a {
            color: #09665f;
            text-underline-offset: .2em;
        }

        .trainee-course a:focus-visible {
            outline: 3px solid #9b4c00;
            outline-offset: 4px;
        }

        .trainee-course .text-muted {
            color: #536772;
        }

        .trainee-course .trainee-header,
        .trainee-course .trainee-account {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem 2rem;
            background: transparent;
            text-align: left;
        }

        .trainee-course .trainee-header {
            margin-bottom: 1.5rem;
            padding: 0;
            border: 0;
        }

        .trainee-course .trainee-header > * {
            min-width: 0;
        }

        .trainee-course .trainee-header p {
            margin-bottom: 0;
            overflow-wrap: anywhere;
        }

        .trainee-course .trainee-eyebrow {
            color: #09665f;
            font-size: .8rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .trainee-course .card {
            margin: 0 0 1.5rem;
            padding: clamp(1.25rem, 3vw, 2rem);
            background: #fff;
            border: 1px solid #d8e4e7;
            border-radius: 18px;
            box-shadow: 0 6px 22px rgba(23, 59, 69, .04);
        }

        .trainee-course .actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .75rem;
        }

        .trainee-course .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            max-width: 100%;
            padding: .7rem 1.25rem;
            background: #09665f;
            color: #fff;
            border: 1px solid #09665f;
            border-radius: 10px;
            font: inherit;
            font-weight: 700;
            line-height: 1.4;
            text-align: center;
            text-decoration: none;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .trainee-course .btn:hover {
            background: #064f49;
            border-color: #064f49;
        }

        .trainee-course .btn-secondary {
            background: #fff;
            color: #09665f;
            border-color: #8baea8;
        }

        .trainee-course .btn-secondary:hover {
            background: #edf7f4;
            color: #064f49;
            border-color: #09665f;
        }

        .trainee-course .trainee-flow {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: .65rem;
            margin: 1.25rem 0 0;
            padding: 0;
            list-style: none;
        }

        .trainee-course .trainee-flow li {
            min-width: 0;
            margin: 0;
            padding: 1rem .75rem;
            background: #f5f8f9;
            border: 1px solid #d8e4e7;
            border-radius: 12px;
            font-size: .85rem;
            font-weight: 600;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        .trainee-course .trainee-step-number {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            margin-bottom: .65rem;
            background: #e1e9ed;
            color: #29434e;
            border-radius: 50%;
            font-weight: 700;
        }

        .trainee-course .trainee-flow [aria-current="step"] {
            background: #e8f5f1;
            border: 2px solid #08756a;
        }

        .trainee-course [aria-current="step"] .trainee-step-number {
            background: #09665f;
            color: #fff;
        }

        .trainee-course .trainee-current {
            display: block;
            margin-top: .5rem;
            color: #09665f;
            font-size: .75rem;
            font-weight: 700;
        }

        .trainee-course .available-intro p:last-child {
            margin-bottom: 0;
        }

        .trainee-course .initial-notice {
            padding: 1rem 1.25rem;
            background: #e8f5f1;
            border-left: 4px solid #08756a;
            border-radius: 8px;
        }

        .trainee-course .questionnaire-card {
            overflow-wrap: anywhere;
        }

        .trainee-course .questionnaire-description {
            max-width: 75ch;
        }

        .trainee-course .questionnaire-metadata {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
            padding: 1.25rem 0;
            border-top: 1px solid #d8e4e7;
            border-bottom: 1px solid #d8e4e7;
        }

        .trainee-course .questionnaire-metadata > div {
            min-width: 0;
        }

        .trainee-course .questionnaire-metadata dt {
            margin-bottom: .45rem;
            color: #536772;
            font-size: .85rem;
            font-weight: 700;
        }

        .trainee-course .questionnaire-metadata dd {
            margin: 0;
            font-weight: 700;
        }

        .trainee-course .questionnaire-badge {
            display: inline-block;
            max-width: 100%;
            padding: .25rem .75rem;
            border: 1px solid #c4d9e8;
            border-radius: 999px;
            background: #edf4fa;
            color: #254f70;
            font-size: .9rem;
            font-weight: 700;
        }

        .trainee-course .empty-state {
            border-style: dashed;
        }

        .trainee-course .empty-state p:last-child {
            margin-bottom: 0;
        }

        .trainee-course .workflow-navigation {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            background: #e8f5f1;
        }

        .trainee-course .trainee-account {
            padding: .5rem 0 1rem;
        }

        .trainee-course .trainee-account p,
        .trainee-course .trainee-account .actions {
            margin: 0;
        }

        @media (max-width: 900px) {
            .trainee-course .trainee-flow {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            .trainee-course .container {
                padding-top: 1.25rem;
            }

            .trainee-course .trainee-flow {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .trainee-course .questionnaire-metadata {
                grid-template-columns: 1fr;
            }

            .trainee-course .trainee-account .actions,
            .trainee-course .actions .btn,
            .trainee-course .workflow-navigation .btn {
                width: 100%;
            }
        }
    </style>
<style>
.trainee-course .summary-stats { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem; margin:1.25rem 0 0; }
.trainee-course .summary-stats > div { padding:1.25rem; border:1px solid #d8e4e7; border-radius:12px; background:#f5f8f9; }
.trainee-course .summary-stats dt { color:#536772; font-weight:600; }
.trainee-course .summary-stats dd { margin:0; color:#173b45; font-size:2.25rem; font-weight:700; }
.trainee-course .history-scroll { overflow-x:auto; border:1px solid #d8e4e7; border-radius:12px; }
.trainee-course .history-scroll:focus-visible { outline:3px solid #9b4c00; outline-offset:4px; }
.trainee-course .history-table { width:100%; min-width:1080px; margin:0; border-collapse:collapse; text-align:left; font-size:.9rem; }
.trainee-course .history-table caption { padding:1rem; text-align:left; color:#536772; background:#fff; }
.trainee-course .history-table th { background:#edf4f4; color:#173b45; font-weight:700; }
.trainee-course .history-table th, .trainee-course .history-table td { padding:1rem .85rem; border:0; border-bottom:1px solid #d8e4e7; vertical-align:top; }
.trainee-course .history-table tbody tr:last-child td { border-bottom:0; }
.trainee-course .history-table tbody tr:nth-child(even) { background:#f8fafb; }
.trainee-course .history-table tbody tr:hover { background:#edf7f4; }
.trainee-course .history-theme { min-width:160px; max-width:260px; overflow-wrap:anywhere; font-weight:700; }
.trainee-course .history-number { white-space:nowrap; font-variant-numeric:tabular-nums; }
.trainee-course .history-table .btn { min-width:110px; padding:.65rem .85rem; }
.trainee-course .badge-pending { background:#fff3d6; color:#754600; border-color:#d6ad56; }
.trainee-course .badge-completed { background:#e8f5f1; color:#075b45; border-color:#98c5b7; }
.trainee-course .badge-level { background:#f0f3f6; color:#344957; border-color:#c8d3da; }
@media (max-width:600px) { .trainee-course .summary-stats { grid-template-columns:1fr; } }
</style>
    <link rel="stylesheet" href="assets/css/trainee_language.css">
<link rel="stylesheet" href="assets/css/ui.css">
<script src="assets/js/ui.js" defer></script>
</head>
<body class="trainee-course">
<div class="container">
<?php require __DIR__ . '/includes/trainee_language_switcher.php'; ?>
<header class="trainee-header">
        <div>
            <p class="trainee-eyebrow"><?= htmlspecialchars(t('h150_positioning_questionnaire'), ENT_QUOTES, 'UTF-8') ?></p>
            <h1><?= htmlspecialchars(t('h150_questionnaire_history_results'), ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="text-muted">
                <?= htmlspecialchars(t('h150_review_your_questionnaire_results'), ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>

        <p class="text-muted">
            <?= htmlspecialchars(t('h150_logged_in_as'), ENT_QUOTES, 'UTF-8') ?>
            <strong>
                <?= htmlspecialchars($trainee['first_name']) ?>
                <?= htmlspecialchars($trainee['last_name']) ?>
            </strong>
        </p>
    </header>
<nav class="card" aria-labelledby="progress-title">
        <h2 id="progress-title"><?= htmlspecialchars(t('h150_course_progress'), ENT_QUOTES, 'UTF-8') ?></h2>

        <ol class="trainee-flow" role="list">
            <li>
                <span class="trainee-step-number" aria-hidden="true">1</span>
                <?= htmlspecialchars(t('h150_course_information'), ENT_QUOTES, 'UTF-8') ?>
            </li>
            <li>
                <span class="trainee-step-number" aria-hidden="true">2</span>
                <?= htmlspecialchars(t('h150_sommaire'), ENT_QUOTES, 'UTF-8') ?>
            </li>
            <li>
                <span class="trainee-step-number" aria-hidden="true">3</span>
                <?= htmlspecialchars(t('h150_introduction'), ENT_QUOTES, 'UTF-8') ?>
            </li>
            <li>
                <span class="trainee-step-number" aria-hidden="true">4</span>
                <?= htmlspecialchars(t('h150_course_content'), ENT_QUOTES, 'UTF-8') ?>
            </li>
            <li>
                <span class="trainee-step-number" aria-hidden="true">5</span>
                <?= htmlspecialchars(t('h150_assigned'), ENT_QUOTES, 'UTF-8') ?>
            </li>
            <li>
                <span class="trainee-step-number" aria-hidden="true">6</span>
                <?= htmlspecialchars(t('h150_available'), ENT_QUOTES, 'UTF-8') ?>

            </li>
            <li aria-current="step">
                <span class="trainee-step-number" aria-hidden="true">7</span>
                <?= htmlspecialchars(t('h150_history_results'), ENT_QUOTES, 'UTF-8') ?><span class="trainee-current"><?= htmlspecialchars(t('h150_current_step'), ENT_QUOTES, 'UTF-8') ?></span>
            </li>
        </ol>
    </nav>
    <main>
        <section class="card" aria-labelledby="summary-title">
            <p class="trainee-eyebrow"><?= htmlspecialchars(t('h150_step_7_of_7'), ENT_QUOTES, 'UTF-8') ?></p>
            <h2 id="summary-title"><?= htmlspecialchars(t('h150_assessment_summary'), ENT_QUOTES, 'UTF-8') ?></h2>
            <dl class="summary-stats">
                <div><dt><?= htmlspecialchars(t('h150_total_attempts'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= count($attempts) ?></dd></div>
                <div><dt><?= htmlspecialchars(t('h150_completed_questionnaires'), ENT_QUOTES, 'UTF-8') ?></dt><dd><?= $completedAttemptCount ?></dd></div>
            </dl>
        </section>
        <section class="card" aria-labelledby="history-title">
            <h2 id="history-title"><?= htmlspecialchars(t('h150_questionnaire_history'), ENT_QUOTES, 'UTF-8') ?></h2>
            <?php if (empty($attempts)): ?>
                <div class="card empty-state">
                    <h3><?= htmlspecialchars(t('h150_no_questionnaire_history_yet'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="text-muted"><?= htmlspecialchars(t('h150_you_do_not_have_any_questionnaire_history_yet'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            <?php else: ?>
                <p id="history-help" class="text-muted"><?= htmlspecialchars(t('h150_review_each_attempt_below_on_smaller_screens_scroll_the_table_horizontally_to_see_all_results_and_actions'), ENT_QUOTES, 'UTF-8') ?></p>
                <div class="history-scroll" role="region" aria-labelledby="history-title" aria-describedby="history-help" tabindex="0">
                    <table class="history-table">
                        <caption><?= htmlspecialchars(t('h150_questionnaire_attempts_and_results'), ENT_QUOTES, 'UTF-8') ?></caption>
                        <thead><tr>
                            <th scope="col"><?= htmlspecialchars(t('h150_passation'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th scope="col"><?= htmlspecialchars(t('h150_questionnaire'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th scope="col"><?= htmlspecialchars(t('h150_date_de_passation'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th scope="col"><?= htmlspecialchars(t('h150_date_de_correction'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th scope="col"><?= htmlspecialchars(t('h150_score'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th scope="col"><?= htmlspecialchars(t('h150_percentage'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th scope="col"><?= htmlspecialchars(t('h150_level'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th scope="col"><?= htmlspecialchars(t('h150_status'), ENT_QUOTES, 'UTF-8') ?></th>
                            <th scope="col"><?= htmlspecialchars(t('h150_result'), ENT_QUOTES, 'UTF-8') ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($attempts as $attempt): ?>
<?php

                            // =================================
                            // MAXIMUM SCORE
                            // =================================

                            $maximumScore =
                                (float)$attempt[
                                    'maximum_score'
                                ];


                            // =================================
                            // TOTAL SCORE
                            // =================================

                            $totalScore =
                                (float)$attempt[
                                    'total_score'
                                ];


                            // =================================
                            // PERCENTAGE
                            // =================================

                            if ($maximumScore > 0) {

                                $percentage =
                                    (
                                        $totalScore /
                                        $maximumScore
                                    ) * 100;

                            } else {

                                $percentage = 0;
                            }


                            // =================================
                            // PENDING MANUAL GRADING
                            // =================================

                            $hasPendingGrading =
                                (int)$attempt[
                                    'pending_open_answers'
                                ] > 0;


                            // =================================
                            // LEVEL / STATUS
                            // =================================

                            if ($hasPendingGrading) {

                                $level =
                                    t('h150_provisional_label');

                                $status =
                                    t('h150_pending_correction');

                            } else {

                                $level =
                                    getPositioningLevel($pdo, $percentage, t('h150_not_defined'));

                                $status =
                                    t('h150_completed');
                            }


                            // =================================
                            // PASSATION DATE
                            // =================================

                            $passationDate =
                                $attempt['completed_at']
                                ??
                                $attempt['started_at'];


                            // =================================
                            // FORMAT PASSATION DATE
                            // =================================

                            $formattedPassationDate = '—';

                            if (!empty($passationDate)) {

                                $timestamp =
                                    strtotime(
                                        $passationDate
                                    );

                                if ($timestamp !== false) {

                                    $formattedPassationDate =
                                        date(
                                            'd/m/Y H:i',
                                            $timestamp
                                        );
                                }
                            }


                            // =================================
                            // FORMAT CORRECTION DATE
                            // =================================

                            $formattedCorrectionDate = '—';

                            if (
                                !empty(
                                    $attempt[
                                        'corrected_at'
                                    ]
                                )
                            ) {

                                $timestamp =
                                    strtotime(
                                        $attempt[
                                            'corrected_at'
                                        ]
                                    );

                                if ($timestamp !== false) {

                                    $formattedCorrectionDate =
                                        date(
                                            'd/m/Y H:i',
                                            $timestamp
                                        );
                                }
                            }

                            ?>
                            <tr>
                                <td><span class="questionnaire-badge"><?= htmlspecialchars(traineePassationLabel($attempt['passation_type'])) ?></span></td>
                                <td class="history-theme"><?= htmlspecialchars($attempt['theme_name'] ?? t('h150_deleted_theme')) ?></td>
                                <td class="history-number"><?= htmlspecialchars($formattedPassationDate) ?></td>
                                <td class="history-number"><?= htmlspecialchars($formattedCorrectionDate) ?></td>
                                <td class="history-number"><?= htmlspecialchars((string)$totalScore) ?> / <?= htmlspecialchars((string)$maximumScore) ?></td>
                                <td class="history-number"><?= number_format($percentage, 2) ?>%</td>
                                <td><span class="questionnaire-badge <?= $hasPendingGrading ? 'badge-pending' : 'badge-level' ?>"><?= htmlspecialchars($level) ?></span></td>
                                <td><span class="questionnaire-badge <?= $hasPendingGrading ? 'badge-pending' : 'badge-completed' ?>"><?= htmlspecialchars($status) ?></span></td>
                                <td><a class="btn" href="result.php?id=<?= (int)$attempt['id'] ?>"><?= htmlspecialchars(t('h150_view_result'), ENT_QUOTES, 'UTF-8') ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <nav class="card workflow-navigation" aria-label="<?= htmlspecialchars(t('h150_previous_and_finish_course_navigation'), ENT_QUOTES, 'UTF-8') ?>">
            <a class="btn btn-secondary" href="available_questionnaires.php"><?= htmlspecialchars(t('h150_previous_available_questionnaires'), ENT_QUOTES, 'UTF-8') ?></a>
            <a class="btn" href="index.php"><?= htmlspecialchars(t('h150_return_to_course_information'), ENT_QUOTES, 'UTF-8') ?></a>
        </nav>
    </main>
<footer class="trainee-account">
        <p class="text-muted"><?= htmlspecialchars(t('h150_your_trainee_account'), ENT_QUOTES, 'UTF-8') ?></p>

        <nav class="actions" aria-label="<?= htmlspecialchars(t('h150_account'), ENT_QUOTES, 'UTF-8') ?>">
            <a class="btn btn-secondary" href="change_password.php">
                <?= htmlspecialchars(t('h150_change_password'), ENT_QUOTES, 'UTF-8') ?>
            </a>
            <a class="btn btn-secondary" href="trainee_logout.php">
                <?= htmlspecialchars(t('h150_logout'), ENT_QUOTES, 'UTF-8') ?>
            </a>
        </nav>
    </footer>
</div>
</body>
</html>
