<?php

session_start();

require_once 'config/database.php';
require_once __DIR__ . '/includes/trainee_language.php';
require_once 'includes/functions.php';

// Require the trainee session, preserving the existing redirect.
if (!isset($_SESSION['trainee_id'])) {
    header('Location: register.php');
    exit;
}

$traineeId = (int)$_SESSION['trainee_id'];
$attemptId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$attemptId) {
    die(t('h150_invalid_attempt'));
}

// Only the trainee who owns this attempt may view its result.
$stmt = $pdo->prepare(
    "SELECT
        attempts.id,
        attempts.trainee_id,
        attempts.theme_id,
        attempts.started_at,
        attempts.completed_at,
        attempts.total_score,
        attempts.maximum_score,
        themes.name AS theme_name,
        trainees.first_name,
        trainees.last_name
     FROM attempts
     INNER JOIN trainees
        ON trainees.id = attempts.trainee_id
     LEFT JOIN themes
        ON themes.id = attempts.theme_id
     WHERE attempts.id = ?
       AND attempts.trainee_id = ?"
);
$stmt->execute([$attemptId, $traineeId]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    die(t('h150_result_not_found_or_access_denied'));
}

$stmt = $pdo->prepare(
    "SELECT
        responses.id AS response_id,
        responses.text_answer,
        responses.awarded_points,
        responses.trainer_comment,
        responses.graded_at,
        questions.question_text,
        questions.question_type,
        questions.points,
        questions.display_order
     FROM responses
     LEFT JOIN questions
        ON questions.id = responses.question_id
     WHERE responses.attempt_id = ?
     ORDER BY
        questions.display_order ASC,
        responses.id ASC"
);
$stmt->execute([$attemptId]);
$responses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Use the stored attempt scores.
$totalScore = (float)$attempt['total_score'];
$maximumScore = (float)$attempt['maximum_score'];

if ($maximumScore > 0) {
    $percentage = ($totalScore / $maximumScore) * 100;
} else {
    $percentage = 0;
}

$hasPendingGrading = false;
foreach ($responses as $response) {
    if (
        $response['question_type'] === 'open' &&
        $response['graded_at'] === null
    ) {
        $hasPendingGrading = true;
        break;
    }
}

if ($hasPendingGrading) {
    $level = t('h150_provisional_label');
} else {
    $level = getPositioningLevel($pdo, $percentage, t('h150_not_defined'));
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(htmlLanguage(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(t('h150_my_result'), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body.trainee-result { margin:0; background:#f3f7f8; color:#20343e; font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; line-height:1.65; }
        .trainee-result *, .trainee-result *::before, .trainee-result *::after { box-sizing:border-box; }
        .trainee-result .container { width:calc(100% - 2rem); max-width:960px; margin:0 auto; padding:2rem 0; background:transparent; border:0; box-shadow:none; }
        .trainee-result h1, .trainee-result h2, .trainee-result h3 { color:#173b45; line-height:1.3; overflow-wrap:anywhere; }
        .trainee-result h1 { font-size:clamp(1.8rem,4vw,2.7rem); margin:.4rem 0 1rem; }
        .trainee-result h2 { font-size:1.6rem; margin:0 0 1rem; }
        .trainee-result h3 { font-size:1.15rem; margin:0 0 1rem; color:#09665f; }
        .trainee-result p { margin:0 0 1rem; }
        .trainee-result a { color:#09665f; text-underline-offset:.2em; }
        .trainee-result a:focus-visible { outline:3px solid #9b4c00; outline-offset:4px; }
        .trainee-result .result-header, .trainee-result .answer-card { padding:clamp(1.25rem,3vw,2rem); margin:1.5rem 0; background:#fff; border:1px solid #d8e4e7; border-radius:18px; box-shadow:0 6px 22px rgba(23,59,69,.04); overflow-wrap:anywhere; }
        .trainee-result .result-header { text-align:left; border-top:5px solid #09665f; }
        .trainee-result .eyebrow { color:#09665f; font-size:.8rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; margin:0 0 .35rem; }
        .trainee-result .trainee-name { font-size:1.15rem; font-weight:700; }
        .trainee-result .theme { margin:0; padding-top:1rem; border-top:1px solid #d8e4e7; color:#536772; }
        .trainee-result .summary { margin:2rem 0; }
        .trainee-result .summary-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem; margin:0; }
        .trainee-result .summary-card { min-width:0; padding:1.4rem; background:#fff; border:1px solid #d8e4e7; border-radius:14px; overflow-wrap:anywhere; }
        .trainee-result .summary-card dt { color:#536772; font-size:.9rem; font-weight:600; margin-bottom:.5rem; }
        .trainee-result .summary-card dd { margin:0; font-size:clamp(1.3rem,3vw,1.8rem); font-weight:750; color:#173b45; line-height:1.4; font-variant-numeric:tabular-nums; }
        .trainee-result .summary-card.score { background:#e5f3ef; border-color:#bdd8cf; }
        .trainee-result .summary-card .provisional { display:block; margin-top:.35rem; font-size:.9rem; font-weight:600; color:#805000; }
        .trainee-result .status-label { display:inline-block; padding:.4rem .75rem; border-radius:10px; background:#e5f3ef; color:#14584f; font-size:1rem; }
        .trainee-result .status-label.pending { background:#fff2d9; color:#805000; }
        .trainee-result .pending-notice { margin-top:1rem; padding:1.25rem; background:#fff7e6; border:1px solid #e8cc92; border-left:5px solid #9b6500; border-radius:12px; color:#704700; }
        .trainee-result .pending-notice h3 { color:#704700; margin:0 0 .5rem; }
        .trainee-result .pending-notice p { margin:0; }
        .trainee-result .question-text { font-size:1.1rem; }
        .trainee-result .answer-text, .trainee-result .feedback-text { margin:0; padding:1rem 1.2rem; background:#f3f7f8; border:1px solid #d8e4e7; border-radius:10px; font-style:normal; color:#20343e; overflow-wrap:anywhere; }
        .trainee-result .answer-score { margin:1.25rem 0 0; padding-top:1rem; border-top:1px solid #d8e4e7; color:#536772; font-variant-numeric:tabular-nums; }
        .trainee-result .answer-score strong { color:#173b45; }
        .trainee-result .feedback { margin-top:1.25rem; }
        .trainee-result .feedback p { margin-bottom:.5rem; color:#14584f; }
        .trainee-result .feedback-text { background:#edf7f3; border-color:#bdd8cf; border-left:4px solid #09665f; }
        .trainee-result .choices { list-style:none; padding:0; margin:0; display:grid; gap:.75rem; }
        .trainee-result .choice { display:flex; align-items:flex-start; gap:1rem; justify-content:space-between; padding:1rem; border:1px solid #bdd8cf; border-radius:10px; background:#edf7f3; }
        .trainee-result .choice.incorrect { background:#fff3f2; border-color:#e6c5c1; }
        .trainee-result .choice-text { min-width:0; overflow-wrap:anywhere; }
        .trainee-result .choice-marker { flex-shrink:0; color:#14584f; font-size:.9rem; font-weight:700; }
        .trainee-result .incorrect .choice-marker { color:#9b3028; }
        .trainee-result .empty-state { padding:1rem; background:#f3f7f8; border:1px dashed #a9bfc5; border-radius:10px; color:#536772; }
        .trainee-result .result-navigation { margin:1.5rem 0; }
        .trainee-result .navigation-link { display:inline-flex; align-items:center; justify-content:center; min-height:48px; padding:.75rem 1.15rem; border:1px solid #a9bfc5; border-radius:10px; background:#fff; font-weight:700; text-decoration:none; text-align:center; }
        .trainee-result .navigation-link:hover { background:#e5f3ef; border-color:#09665f; }
        .trainee-result .navigation-link.primary { background:#09665f; border-color:#09665f; color:#fff; }
        .trainee-result .navigation-link.primary:hover { background:#074f49; }
        @media (max-width:600px) {
            .trainee-result .container { width:calc(100% - 1rem); padding:1rem 0; }
            .trainee-result .summary-grid { grid-template-columns:minmax(0,1fr); }
            .trainee-result .choice { flex-direction:column; gap:.5rem; }
            .trainee-result .navigation-link { width:100%; }
        }
    </style>
    <link rel="stylesheet" href="assets/css/trainee_language.css">
<link rel="stylesheet" href="assets/css/ui.css">
<script src="assets/js/ui.js" defer></script>
</head>
<body class="trainee-course trainee-result">
<div class="container">
<?php require __DIR__ . '/includes/trainee_language_switcher.php'; ?>
    <nav class="result-navigation" aria-label="<?= htmlspecialchars(t('h150_back_to_questionnaires_label'), ENT_QUOTES, 'UTF-8') ?>">
        <a class="navigation-link" href="index.php"><?= htmlspecialchars(t('h150_back_to_questionnaires'), ENT_QUOTES, 'UTF-8') ?></a>
    </nav>

    <header class="result-header">
        <p class="eyebrow"><?= htmlspecialchars(t('h150_your_questionnaire'), ENT_QUOTES, 'UTF-8') ?></p>
        <h1><?= htmlspecialchars(t('h150_questionnaire_result'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="trainee-name">
            <?= htmlspecialchars($attempt['first_name']) ?>
            <?= htmlspecialchars($attempt['last_name']) ?>
        </p>
        <p class="theme"><?= htmlspecialchars(t('h150_theme_label'), ENT_QUOTES, 'UTF-8') ?> <strong><?= htmlspecialchars($attempt['theme_name'] ?? t('h150_unknown')) ?></strong></p>
    </header>

    <main>
        <section class="summary" aria-labelledby="result-heading">
            <h2 id="result-heading"><?= htmlspecialchars(t('h150_your_result'), ENT_QUOTES, 'UTF-8') ?></h2>
            <dl class="summary-grid">
                <div class="summary-card score">
                    <dt><?= htmlspecialchars(t('h150_score'), ENT_QUOTES, 'UTF-8') ?></dt>
                    <dd><?= number_format($totalScore, 2) ?> / <?= number_format($maximumScore, 2) ?></dd>
                </div>
                <div class="summary-card">
                    <dt><?= $hasPendingGrading ? t('h150_current_percentage') : t('h150_final_percentage') ?></dt>
                    <dd>
                        <?= number_format($percentage, 1) ?>%
                        <?php if ($hasPendingGrading): ?>
                            <span class="provisional"><?= htmlspecialchars(t('h150_provisional'), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </dd>
                </div>
                <div class="summary-card">
                    <dt><?= htmlspecialchars(t('h150_positioning_level'), ENT_QUOTES, 'UTF-8') ?></dt>
                    <dd><?= htmlspecialchars($level) ?></dd>
                </div>
                <div class="summary-card">
                    <dt><?= htmlspecialchars(t('h150_status'), ENT_QUOTES, 'UTF-8') ?></dt>
                    <dd>
                        <?php if ($hasPendingGrading): ?>
                            <span class="status-label pending"><?= htmlspecialchars(t('h150_pending_trainer_review'), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php else: ?>
                            <span class="status-label"><?= htmlspecialchars(t('h150_completed'), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </dd>
                </div>
            </dl>
            <?php if ($hasPendingGrading): ?>
                <aside class="pending-notice" aria-labelledby="pending-heading">
                    <h3 id="pending-heading"><?= htmlspecialchars(t('h150_provisional_result_pending_trainer_review'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <p><?= htmlspecialchars(t('h150_one_or_more_open_questions_have_not_yet_been_graded_your_final_score_and_positioning_level_may_change_after_review'), ENT_QUOTES, 'UTF-8') ?></p>
                </aside>
            <?php endif; ?>
        </section>

        <section aria-labelledby="answers-heading">
            <h2 id="answers-heading"><?= htmlspecialchars(t('h150_your_answers'), ENT_QUOTES, 'UTF-8') ?></h2>
            <?php if (empty($responses)): ?>
                <p class="empty-state"><?= htmlspecialchars(t('h150_no_answers_found'), ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <?php foreach ($responses as $index => $response): ?>
                <section class="answer-card" aria-labelledby="question-<?= $index + 1 ?>">
                    <h3 id="question-<?= $index + 1 ?>"><?= htmlspecialchars(t('h150_question'), ENT_QUOTES, 'UTF-8') ?> <?= $index + 1 ?></h3>
                    <p class="question-text"><strong><?= htmlspecialchars($response['question_text'] ?? t('h150_question_unavailable')) ?></strong></p>

                    <?php if ($response['question_type'] === 'open'): ?>
                        <p><strong><?= htmlspecialchars(t('h150_your_answer'), ENT_QUOTES, 'UTF-8') ?></strong></p>
                        <blockquote class="answer-text"><?= nl2br(htmlspecialchars($response['text_answer'] ?? '')) ?></blockquote>
                        <p class="answer-score"><?= htmlspecialchars(t('h150_score_label'), ENT_QUOTES, 'UTF-8') ?>
                            <strong><?= number_format((float)$response['awarded_points'], 2) ?> / <?= number_format((float)$response['points'], 2) ?></strong>
                        </p>
                        <?php if (!empty($response['trainer_comment'])): ?>
                            <div class="feedback">
                                <p><strong><?= htmlspecialchars(t('h150_trainer_feedback'), ENT_QUOTES, 'UTF-8') ?></strong></p>
                                <blockquote class="feedback-text"><?= nl2br(htmlspecialchars($response['trainer_comment'])) ?></blockquote>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php
                        $choiceStmt = $pdo->prepare(
                            "SELECT
                                choices.choice_text,
                                choices.is_correct
                             FROM response_choices
                             INNER JOIN choices
                                ON choices.id =
                                   response_choices.choice_id
                             WHERE response_choices.response_id = ?"
                        );
                        $choiceStmt->execute([$response['response_id']]);
                        $selectedChoices = $choiceStmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <p><strong><?= htmlspecialchars(t('h150_your_selected_answer_s'), ENT_QUOTES, 'UTF-8') ?></strong></p>
                        <?php if (empty($selectedChoices)): ?>
                            <p class="empty-state"><?= htmlspecialchars(t('h150_no_answer_selected'), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php else: ?>
                            <ul class="choices">
                                <?php foreach ($selectedChoices as $choice): ?>
                                    <li class="choice <?= (int)$choice['is_correct'] === 1 ? 'correct' : 'incorrect' ?>">
                                        <span class="choice-text"><?= htmlspecialchars($choice['choice_text']) ?></span>
                                        <?php if ((int)$choice['is_correct'] === 1): ?>
                                            <span class="choice-marker"><span aria-hidden="true">✓</span> <?= htmlspecialchars(t('h150_correct'), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php else: ?>
                                            <span class="choice-marker"><span aria-hidden="true">✗</span> <?= htmlspecialchars(t('h150_incorrect'), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <p class="answer-score"><?= htmlspecialchars(t('h150_score_label'), ENT_QUOTES, 'UTF-8') ?>
                            <strong><?= number_format((float)$response['awarded_points'], 2) ?> / <?= number_format((float)$response['points'], 2) ?></strong>
                        </p>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </section>
    </main>

    <nav class="result-navigation" aria-label="<?= htmlspecialchars(t('h150_continue_to_questionnaires'), ENT_QUOTES, 'UTF-8') ?>">
        <a class="navigation-link primary" href="index.php"><?= htmlspecialchars(t('h150_take_another_questionnaire'), ENT_QUOTES, 'UTF-8') ?></a>
    </nav>
</div>
</body>
</html>
