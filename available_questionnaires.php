<?php
session_start();

require_once 'config/database.php';
require_once __DIR__ . '/includes/trainee_language.php';

// Require trainee login.
if (!isset($_SESSION['trainee_id'])) {
    header('Location: trainee_login.php');
    exit;
}

$traineeId = (int)$_SESSION['trainee_id'];

// Get trainee.
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

$stmt->execute([$traineeId]);
$trainee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trainee) {
    session_unset();
    session_destroy();

    header('Location: trainee_login.php');
    exit;
}

// Available questionnaires:
// - Active themes containing at least one active question.
// - No trainer assignment required.
// - Started using the Initial passation type.
$stmt = $pdo->query(
    "SELECT
        t.id AS theme_id,
        t.name AS theme_name,
        t.description AS theme_description,
        (
            SELECT COUNT(*)
            FROM questions q
            WHERE q.theme_id = t.id
              AND q.is_active = 1
        ) AS question_count
     FROM themes t
     WHERE t.is_active = 1
       AND EXISTS
       (
            SELECT 1
            FROM questions q2
            WHERE q2.theme_id = t.id
              AND q2.is_active = 1
       )
     ORDER BY
        t.display_order ASC,
        t.name ASC"
);

$availableQuestionnaires = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(htmlLanguage(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(t('h150_available_questionnaires'), ENT_QUOTES, 'UTF-8') ?></title>
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
            <h1><?= htmlspecialchars(t('h150_available_questionnaires'), ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="text-muted">
                <?= htmlspecialchars(t('h150_questionnaires_available_without_a_trainer_assignment'), ENT_QUOTES, 'UTF-8') ?>
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
            <li aria-current="step">
                <span class="trainee-step-number" aria-hidden="true">6</span>
                <?= htmlspecialchars(t('h150_available'), ENT_QUOTES, 'UTF-8') ?>
                <span class="trainee-current"><?= htmlspecialchars(t('h150_current_step'), ENT_QUOTES, 'UTF-8') ?></span>
            </li>
            <li>
                <span class="trainee-step-number" aria-hidden="true">7</span>
                <?= htmlspecialchars(t('h150_history_results'), ENT_QUOTES, 'UTF-8') ?>
            </li>
        </ol>
    </nav>

    <main>
        <section aria-labelledby="available-title">
            <div class="card available-intro">
                <p class="trainee-eyebrow"><?= htmlspecialchars(t('h150_step_6_of_7'), ENT_QUOTES, 'UTF-8') ?></p>
                <h2 id="available-title"><?= htmlspecialchars(t('h150_available_questionnaires'), ENT_QUOTES, 'UTF-8') ?></h2>

                <p>
                    <?= htmlspecialchars(t('h150_the_questionnaires_below_can_be_started_directly_without_a_trainer_assignment'), ENT_QUOTES, 'UTF-8') ?>
                </p>

                <p class="initial-notice">
                    <?= htmlspecialchars(t('h150_questionnaires_started_from_this_page_use_the_initial_passation_type_choose_a_questionnaire_below_to_begin'), ENT_QUOTES, 'UTF-8') ?>
                </p>

                <?php if (!empty($availableQuestionnaires)): ?>
                    <p>
                        <?= htmlspecialchars(t('h150_available_questionnaires_label'), ENT_QUOTES, 'UTF-8') ?>
                        <strong><?= count($availableQuestionnaires) ?></strong>
                    </p>
                <?php endif; ?>
            </div>

            <?php if (empty($availableQuestionnaires)): ?>
                <div class="card empty-state">
                    <h3><?= htmlspecialchars(t('h150_no_available_questionnaires'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="text-muted">
                        <?= htmlspecialchars(t('h150_there_are_currently_no_available_questionnaires'), ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($availableQuestionnaires as $available): ?>
                    <article
                        class="card questionnaire-card"
                        aria-labelledby="theme-<?= (int)$available['theme_id'] ?>-title"
                    >
                        <h3 id="theme-<?= (int)$available['theme_id'] ?>-title">
                            <?= htmlspecialchars($available['theme_name']) ?>
                        </h3>

                        <?php if (!empty($available['theme_description'])): ?>
                            <p class="questionnaire-description">
                                <?= nl2br(htmlspecialchars($available['theme_description'])) ?>
                            </p>
                        <?php endif; ?>

                        <dl class="questionnaire-metadata">
                            <div>
                                <dt><?= htmlspecialchars(t('h150_passation'), ENT_QUOTES, 'UTF-8') ?></dt>
                                <dd>
                                    <span class="questionnaire-badge"><?= htmlspecialchars(t('h150_initial'), ENT_QUOTES, 'UTF-8') ?></span>
                                </dd>
                            </div>
                            <div>
                                <dt><?= htmlspecialchars(t('h150_questions'), ENT_QUOTES, 'UTF-8') ?></dt>
                                <dd><?= (int)$available['question_count'] ?></dd>
                            </div>
                        </dl>

                        <div class="actions">
                            <a
                                class="btn"
                                href="questionnaire.php?theme_id=<?= (int)$available['theme_id'] ?>"
                                aria-label="<?= htmlspecialchars(t('h150_start_questionnaire_label'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($available['theme_name'], ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <?= htmlspecialchars(t('h150_start_questionnaire'), ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <nav
            class="card workflow-navigation"
            aria-label="<?= htmlspecialchars(t('h150_previous_and_next_course_pages'), ENT_QUOTES, 'UTF-8') ?>"
        >
            <a class="btn btn-secondary" href="assigned_questionnaires.php">
                <?= htmlspecialchars(t('h150_previous_assigned_questionnaires'), ENT_QUOTES, 'UTF-8') ?>
            </a>
            <a class="btn" href="questionnaire_history.php">
                <?= htmlspecialchars(t('h150_next_history_results'), ENT_QUOTES, 'UTF-8') ?>
            </a>
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