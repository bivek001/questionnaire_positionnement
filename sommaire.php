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

// Get trainee information.
$stmt = $pdo->prepare(
    "SELECT id, first_name, last_name, email
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

// Get Sommaire content.
$stmt = $pdo->prepare(
    "SELECT title, content, updated_at
     FROM content_pages
     WHERE page_key = ?
     LIMIT 1"
);
$stmt->execute(['sommaire']);
$page = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$page) {
    http_response_code(404);
    die(t('h150_sommaire_page_not_found'));
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(htmlLanguage(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page['title']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Reuse the trainee design embedded in the Step 138A homepage. */
        body.trainee-sommaire {
            margin: 0;
            background: #f3f7f8;
            color: #20343e;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.65;
        }
        .trainee-sommaire *,
        .trainee-sommaire *::before,
        .trainee-sommaire *::after { box-sizing: border-box; }
        .trainee-sommaire .container {
            width: calc(100% - 2rem);
            max-width: 1120px;
            margin: 0 auto;
            padding: 2rem 0;
            background: transparent;
            border: 0;
            box-shadow: none;
        }
        .trainee-sommaire h1, .trainee-sommaire h2 {
            color: #173b45;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }
        .trainee-sommaire h1 { margin: .35rem 0 .75rem; font-size: clamp(1.8rem, 4vw, 2.7rem); }
        .trainee-sommaire h2 { margin: 0 0 .75rem; font-size: clamp(1.35rem, 3vw, 1.8rem); }
        .trainee-sommaire p { margin: 0 0 1rem; }
        .trainee-sommaire a { color: #09665f; text-underline-offset: .2em; }
        .trainee-sommaire a:focus-visible { outline: 3px solid #9b4c00; outline-offset: 4px; }
        .trainee-sommaire .text-muted { color: #536772; }
        .trainee-sommaire .trainee-header,
        .trainee-sommaire .trainee-account {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem 2rem;
            background: transparent;
            text-align: left;
        }
        .trainee-sommaire .trainee-header { margin-bottom: 1.5rem; padding: 0; border: 0; }
        .trainee-sommaire .trainee-header > * { min-width: 0; }
        .trainee-sommaire .trainee-header p { margin-bottom: 0; overflow-wrap: anywhere; }
        .trainee-sommaire .trainee-eyebrow {
            color: #09665f;
            font-size: .8rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
        }
        .trainee-sommaire .card {
            margin: 0 0 1.5rem;
            padding: clamp(1.25rem, 3vw, 2rem);
            background: #fff;
            border: 1px solid #d8e4e7;
            border-radius: 18px;
            box-shadow: 0 6px 22px rgba(23, 59, 69, .04);
        }
        .trainee-sommaire .actions { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; }
        .trainee-sommaire .btn {
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
        }
        .trainee-sommaire .btn:hover { background: #064f49; border-color: #064f49; }
        .trainee-sommaire .btn-secondary { background: #fff; color: #09665f; border-color: #8baea8; }
        .trainee-sommaire .btn-secondary:hover { background: #edf7f4; color: #064f49; border-color: #09665f; }
        .trainee-sommaire .trainee-flow {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: .65rem;
            margin: 1.25rem 0 0;
            padding: 0;
            list-style: none;
        }
        .trainee-sommaire .trainee-flow li {
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
        .trainee-sommaire .trainee-step-number {
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
        .trainee-sommaire .trainee-flow [aria-current="step"] { background: #e8f5f1; border: 2px solid #08756a; }
        .trainee-sommaire [aria-current="step"] .trainee-step-number { background: #09665f; color: #fff; }
        .trainee-sommaire .trainee-current { display: block; margin-top: .5rem; color: #09665f; font-size: .75rem; font-weight: 700; }
        .trainee-sommaire .sommaire-content { max-width: 75ch; line-height: 1.8; overflow-wrap: anywhere; }
        .trainee-sommaire .sommaire-updated { margin: 1.5rem 0 0; padding-top: 1rem; border-top: 1px solid #d8e4e7; }
        .trainee-sommaire .workflow-navigation {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            background: #e8f5f1;
        }
        .trainee-sommaire .trainee-account { padding: .5rem 0 1rem; }
        .trainee-sommaire .trainee-account p,
        .trainee-sommaire .trainee-account .actions { margin: 0; }
        @media (max-width: 900px) {
            .trainee-sommaire .trainee-flow { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        }
        @media (max-width: 600px) {
            .trainee-sommaire .container { padding-top: 1.25rem; }
            .trainee-sommaire .trainee-flow { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .trainee-sommaire .trainee-account .actions,
            .trainee-sommaire .actions .btn,
            .trainee-sommaire .workflow-navigation .btn { width: 100%; }
        }
    </style>
    <link rel="stylesheet" href="assets/css/trainee_language.css">
<link rel="stylesheet" href="assets/css/ui.css">
<script src="assets/js/ui.js" defer></script>
</head>
<body class="trainee-sommaire">
<div class="container">
<?php require __DIR__ . '/includes/trainee_language_switcher.php'; ?>
    <header class="trainee-header">
        <div>
            <p class="trainee-eyebrow"><?= htmlspecialchars(t('h150_positioning_questionnaire'), ENT_QUOTES, 'UTF-8') ?></p>
            <h1><?= htmlspecialchars($page['title']) ?></h1>
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
            <li aria-current="step">
                <span class="trainee-step-number" aria-hidden="true">2</span>
                <?= htmlspecialchars(t('h150_sommaire'), ENT_QUOTES, 'UTF-8') ?>
                <span class="trainee-current"><?= htmlspecialchars(t('h150_current_step'), ENT_QUOTES, 'UTF-8') ?></span>
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
            <li>
                <span class="trainee-step-number" aria-hidden="true">7</span>
                <?= htmlspecialchars(t('h150_history_results'), ENT_QUOTES, 'UTF-8') ?>
            </li>
        </ol>
    </nav>

    <main>
        <section class="card" aria-labelledby="sommaire-title">
            <h2 id="sommaire-title"><?= htmlspecialchars($page['title']) ?></h2>
            <div class="sommaire-content"><?= nl2br(htmlspecialchars($page['content'])) ?></div>

            <?php if (!empty($page['updated_at'])): ?>
                <p class="text-muted sommaire-updated">
                    <small>
                        <?= htmlspecialchars(t('h150_last_updated'), ENT_QUOTES, 'UTF-8') ?>
                        <?php $updatedTimestamp = strtotime($page['updated_at']); ?>
                        <?php if ($updatedTimestamp !== false): ?>
                            <?= htmlspecialchars(date('d/m/Y H:i', $updatedTimestamp)) ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </small>
                </p>
            <?php endif; ?>
        </section>

        <nav class="card workflow-navigation" aria-label="<?= htmlspecialchars(t('h150_previous_and_next_course_pages'), ENT_QUOTES, 'UTF-8') ?>">
            <a class="btn btn-secondary" href="index.php">
                <?= htmlspecialchars(t('h150_previous_course_information'), ENT_QUOTES, 'UTF-8') ?>
            </a>
            <a class="btn" href="introduction.php">
                <?= htmlspecialchars(t('h150_next_introduction'), ENT_QUOTES, 'UTF-8') ?>
            </a>
        </nav>
    </main>

    <footer class="trainee-account">
        <p class="text-muted"><?= htmlspecialchars(t('h150_your_trainee_account'), ENT_QUOTES, 'UTF-8') ?></p>
        <nav class="actions" aria-label="<?= htmlspecialchars(t('h150_account'), ENT_QUOTES, 'UTF-8') ?>">
            <a class="btn btn-secondary" href="change_password.php"><?= htmlspecialchars(t('h150_change_password'), ENT_QUOTES, 'UTF-8') ?></a>
            <a class="btn btn-secondary" href="trainee_logout.php"><?= htmlspecialchars(t('h150_logout'), ENT_QUOTES, 'UTF-8') ?></a>
        </nav>
    </footer>
</div>
</body>
</html>
