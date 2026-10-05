<?php
require_once __DIR__ . "/includes/safe_rich_content.php";

session_start();

require_once 'config/database.php';
require_once __DIR__ . '/includes/trainee_language.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__.'/includes/account_security.php';
ux_csrf_check();


// ======================================================
// REQUIRE TRAINEE LOGIN
// ======================================================

if (!isset($_SESSION['trainee_id'])) {

    header('Location: trainee_login.php');
    exit;
}

$traineeId = (int)$_SESSION['trainee_id'];


// ======================================================
// DETERMINE QUESTIONNAIRE MODE
// ======================================================

$assignmentId = filter_input(
    INPUT_GET,
    'assignment_id',
    FILTER_VALIDATE_INT
);

$requestedThemeId = filter_input(
    INPUT_GET,
    'theme_id',
    FILTER_VALIDATE_INT
);


// We support exactly one of these modes:
//
// 1. assignment_id = trainer-assigned questionnaire
// 2. theme_id      = generally available questionnaire

if ($assignmentId) {

    $questionnaireMode = 'assigned';

} elseif ($requestedThemeId) {

    $questionnaireMode = 'available';

} else {

    die(t('h150_invalid_questionnaire_request'));
}


// ======================================================
// DEFAULT QUESTIONNAIRE INFORMATION
// ======================================================

$themeId = null;
$themeName = '';
$themeDescription = '';
$passationType = '';
$assignedAt = null;


// ======================================================
// ASSIGNED QUESTIONNAIRE
// ======================================================

if ($questionnaireMode === 'assigned') {

    $stmt = $pdo->prepare(
        "SELECT
            qa.id,
            qa.trainee_id,
            qa.theme_id,
            qa.passation_type,
            qa.status,
            qa.assigned_at,

            themes.name AS theme_name,
            themes.description AS theme_description,
            themes.is_active AS theme_is_active

         FROM questionnaire_assignments qa

         INNER JOIN themes
            ON themes.id = qa.theme_id

         WHERE qa.id = ?
           AND qa.trainee_id = ?

         LIMIT 1"
    );

    $stmt->execute([
        $assignmentId,
        $traineeId
    ]);

    $assignment =
        $stmt->fetch(PDO::FETCH_ASSOC);


    // --------------------------------------------------
    // VALIDATE ASSIGNMENT
    // --------------------------------------------------

    if (!$assignment) {

        die(
            t('h150_this_questionnaire_assignment_does_not_exist_or_does_not_belong_to_your_account')
        );
    }


    if ($assignment['status'] !== 'pending') {

        die(
            t('h150_this_questionnaire_has_already_been_completed')
        );
    }


    if (
        (int)$assignment['theme_is_active']
        !== 1
    ) {

        die(
            t('h150_this_questionnaire_is_currently_not_available')
        );
    }


    $themeId =
        (int)$assignment['theme_id'];

    $themeName =
        $assignment['theme_name'];

    $themeDescription =
        $assignment['theme_description'] ?? '';

    $passationType =
        $assignment['passation_type'];

    $assignedAt =
        $assignment['assigned_at'];
}


// ======================================================
// AVAILABLE QUESTIONNAIRE
// ======================================================

if ($questionnaireMode === 'available') {

    $themeId =
        (int)$requestedThemeId;


    // --------------------------------------------------
    // VALIDATE THEME
    // --------------------------------------------------

    $stmt = $pdo->prepare(
        "SELECT
            id,
            name,
            description,
            is_active

         FROM themes

         WHERE id = ?

         LIMIT 1"
    );

    $stmt->execute([
        $themeId
    ]);

    $theme =
        $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$theme) {

        die(
            t('h150_this_questionnaire_does_not_exist')
        );
    }


    if ((int)$theme['is_active'] !== 1) {

        die(
            t('h150_this_questionnaire_is_currently_not_available')
        );
    }


    $themeName =
        $theme['name'];

    $themeDescription =
        $theme['description'] ?? '';


    // Available questionnaires are always Initial.
    // Final and Sortie remain controlled by trainer
    // assignments.

    $passationType = 'initial';
}


// ======================================================
// GET ACTIVE QUESTIONS WITH HIERARCHY
// ======================================================

$stmt = $pdo->prepare(
    "SELECT

        q.id,
        q.theme_id,
        q.chapter_id,
        q.lesson_id,
        q.topic_id,
        q.paragraph_id,
        q.pre_question_content,
        q.question_text,
        q.media_type,
        q.media_path,
        q.question_type,
        q.points,
        q.display_order,

        c.title AS chapter_title,
        c.display_order AS chapter_order,

        l.title AS lesson_title,
        l.display_order AS lesson_order,

        tp.title AS topic_title,
        tp.display_order AS topic_order,

        p.title AS paragraph_title,
        p.content AS paragraph_content,
        p.media_type AS paragraph_media_type,
        p.media_path AS paragraph_media_path,
        p.display_order AS paragraph_order

     FROM questions q

     LEFT JOIN chapters c
        ON c.id = q.chapter_id

     LEFT JOIN lessons l
        ON l.id = q.lesson_id

     LEFT JOIN topics tp
        ON tp.id = q.topic_id

     LEFT JOIN paragraphs p
        ON p.id = q.paragraph_id

     WHERE q.theme_id = ?
       AND q.is_active = 1

     ORDER BY

        CASE
            WHEN q.chapter_id IS NULL THEN 1
            ELSE 0
        END ASC,

        COALESCE(c.display_order, 999999) ASC,
        COALESCE(c.id, 999999) ASC,

        CASE
            WHEN q.lesson_id IS NULL THEN 1
            ELSE 0
        END ASC,

        COALESCE(l.display_order, 999999) ASC,
        COALESCE(l.id, 999999) ASC,

        CASE
            WHEN q.topic_id IS NULL THEN 1
            ELSE 0
        END ASC,

        COALESCE(tp.display_order, 999999) ASC,
        COALESCE(tp.id, 999999) ASC,

        CASE
            WHEN q.paragraph_id IS NULL THEN 1
            ELSE 0
        END ASC,

        COALESCE(p.display_order, 999999) ASC,
        COALESCE(p.id, 999999) ASC,

        q.display_order ASC,
        q.id ASC"
);

$stmt->execute([
    $themeId
]);

$questions =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// NO QUESTIONS
// ======================================================

if (empty($questions)) {

    die(
        t('h150_there_are_currently_no_active_questions_in_this_questionnaire')
    );
}


// ======================================================
// GET CHOICES FOR EACH QUESTION
// ======================================================

foreach ($questions as &$question) {

    $question['choices'] = [];

    if (
        $question['question_type']
            === 'single_choice' ||
        $question['question_type']
            === 'multiple_choice'
    ) {

        $stmt = $pdo->prepare(
            "SELECT
                id,
                choice_text,
                display_order

             FROM choices

             WHERE question_id = ?

             ORDER BY
                display_order ASC,
                id ASC"
        );

        $stmt->execute([
            $question['id']
        ]);

        $question['choices'] =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );
    }
}

unset($question);
require_once __DIR__.'/includes/positioning_ux.php';
require __DIR__.'/includes/trainee_questionnaire_view.php';
header('Cache-Control: no-store');

?>

<!DOCTYPE html>

<html lang="<?= htmlspecialchars(htmlLanguage(), ENT_QUOTES, 'UTF-8') ?>">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars($themeName) ?>
    </title>

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

    
    <link rel="stylesheet" href="assets/css/trainee_language.css">
<link rel="stylesheet" href="assets/css/ui.css">
<script src="assets/js/ui.js" defer></script>
<script src="assets/js/positioning.js" defer></script>
</head>

<body class="trainee-course">

<div class="container">
<?php require __DIR__ . '/includes/trainee_language_switcher.php'; ?>


<p>

    <a href="index.php">
        <?= htmlspecialchars(t('h150_back_to_dashboard'), ENT_QUOTES, 'UTF-8') ?>
    </a>

</p>


<hr>


<!-- ================================================== -->
<!-- QUESTIONNAIRE INFORMATION -->
<!-- ================================================== -->

<header class="questionnaire-header">
    <p class="eyebrow"><?= htmlspecialchars(t('h150_your_questionnaire'), ENT_QUOTES, 'UTF-8') ?></p>


    <h1>

        <?= htmlspecialchars(
            $themeName
        ) ?>

    </h1>


    <?php if (
        !empty($themeDescription)
    ): ?>

        <p>

            <?= nl2br(
                htmlspecialchars(
                    $themeDescription
                )
            ) ?>

        </p>

    <?php endif; ?>


    <p class="mode-badge">

        <strong>
            <?= htmlspecialchars(t('h150_questionnaire_label'), ENT_QUOTES, 'UTF-8') ?>
        </strong>

        <?php if (
            $questionnaireMode === 'assigned'
        ): ?>

            <?= htmlspecialchars(t('h150_assigned_by_trainer'), ENT_QUOTES, 'UTF-8') ?>

        <?php else: ?>

            <?= htmlspecialchars(t('h150_available_questionnaire'), ENT_QUOTES, 'UTF-8') ?>

        <?php endif; ?>

    </p>


    <p>

        <strong>
            <?= htmlspecialchars(t('h150_passation_label'), ENT_QUOTES, 'UTF-8') ?>
        </strong>

        <?= htmlspecialchars(
            traineePassationLabel($passationType)
        ) ?>

    </p>


    <?php if (
        $questionnaireMode === 'assigned' &&
        !empty($assignedAt)
    ): ?>

        <p>

            <strong>
                <?= htmlspecialchars(t('h150_assigned_label'), ENT_QUOTES, 'UTF-8') ?>
            </strong>

            <?php

            $assignedTimestamp =
                strtotime($assignedAt);

            ?>

            <?php if (
                $assignedTimestamp !== false
            ): ?>

                <?= htmlspecialchars(
                    date(
                        'd/m/Y H:i',
                        $assignedTimestamp
                    )
                ) ?>

            <?php else: ?>

                —

            <?php endif; ?>

        </p>

    <?php endif; ?>


    <p>

        <strong>
            <?= htmlspecialchars(t('h150_number_of_questions'), ENT_QUOTES, 'UTF-8') ?>
        </strong>

        <?= count($questions) ?>

    </p>


    <p>
        <?= htmlspecialchars(t('h150_please_read_the_learning_content_carefully_and_answer_all_questions_before_submitting_your_questionnaire'), ENT_QUOTES, 'UTF-8') ?>
    </p>


</header>


<hr>


<!-- ================================================== -->
<!-- QUESTIONNAIRE FORM -->
<!-- ================================================== -->

<main id="questionnaire-content">
<form id="h150-questionnaire"
    method="POST"
    action="submit.php?lang=<?= htmlspecialchars(currentLanguage(), ENT_QUOTES, 'UTF-8') ?>"
><?php ux_csrf_field(); ?>


    <?php if (
        $questionnaireMode === 'assigned'
    ): ?>

        <!--
            Assigned questionnaire.

            submit.php will validate this assignment
            again before accepting the answers.
        -->

        <input
            type="hidden"
            name="questionnaire_mode"
            value="assigned"
        >

        <input
            type="hidden"
            name="assignment_id"
            value="<?= (int)$assignmentId ?>"
        >


    <?php else: ?>


        <!--
            Available questionnaire.

            IMPORTANT:
            submit.php must validate this theme again.
            It must not trust the hidden passation value
            from the browser.

            Available questionnaires are always Initial.
        -->

        <input
            type="hidden"
            name="questionnaire_mode"
            value="available"
        >

        <input
            type="hidden"
            name="theme_id"
            value="<?= (int)$themeId ?>"
        >


    <?php endif; ?>


<section class="pu-progress" aria-label="<?= qp_h(pu('Questionnaire progress','Progression du questionnaire')) ?>">
 <p id="pu-progress-text" aria-live="polite"></p><progress id="pu-progress" value="0" max="<?= count($questions) ?>"></progress>
 <div class="actions"><button type="submit" name="pu_save" value="1" formaction="<?= qp_h('questionnaire.php?'.http_build_query($questionnaireMode==='assigned'?['assignment_id'=>$assignmentId,'lang'=>currentLanguage()]:['theme_id'=>$themeId,'lang'=>currentLanguage()])) ?>" formnovalidate><?= qp_h(pu('Save and continue later','Enregistrer et reprendre plus tard')) ?></button><span><?= qp_h(pu('Saved for this signed-in session.','Enregistré pour cette session connectée.')) ?></span></div>
 <?php if(isset($_GET['saved'])): ?><p role="status"><?= qp_h(pu('Answers saved. You can continue.','Réponses enregistrées. Vous pouvez continuer.')) ?></p><?php endif; ?>
</section>
<div class="pu-test-layout"><aside class="pu-test-navigation"><label for="pu-jump"><?= qp_h(pu('Navigate competencies / questions','Naviguer entre compétences / questions')) ?></label><select id="pu-jump"></select><ol id="pu-question-nav"></ol></aside><div>
<?php foreach($questions as $i=>$question):
 $id=(int)$question['id']; $m=$metadata[$id] ?? null; $values=$draft['answers'][$id] ?? ''; $choiceValues=is_array($values)?$values:[$values];
 $title=$m ? ($i+1).' · '.$m['competency_code'].' · '.pu_source()['domains'][(string)$m['domain_number']]['title'] : ($question['topic_title'] ?: $question['chapter_title'] ?: pu('Question','Question')).' · '.($i+1);
?>
<section class="pu-question-page" data-title="<?= qp_h($title) ?>" data-question-id="<?= $id ?>" role="group" aria-labelledby="question_<?= $id ?>">
 <div class="pu-competency"><p><?= qp_h($themeName) ?></p>
 <?php if($m): ?><p class="badge"><?= qp_h($m['diagnostic']==='professional'?'Diagnostic des compétences professionnelles':'Diagnostic des compétences transversales') ?></p><h2><?= qp_h($title) ?></h2><?php endif; ?>
 <p><?= qp_h(implode(' › ',array_filter([$question['chapter_title'],$question['lesson_title'],$question['topic_title']]))) ?></p></div>
 <?php if($question['paragraph_content'] || $question['paragraph_media_path']): ?><article class="questionnaire-paragraph"><h3><?= qp_h($question['paragraph_title'] ?: pu('Read before answering','À lire avant de répondre')) ?></h3><div class="pu-rich"><?= qp_rich_html($question['paragraph_content'] ?? '') ?></div><?php pu_media($question['paragraph_media_type'],$question['paragraph_media_path'],$question['paragraph_title'] ?? ''); ?></article><?php endif; ?>
 <article class="question-block"><p class="points-badge"><?= qp_h(pu('Question','Question')) ?> <?= $i+1 ?> / <?= count($questions) ?> · <?= qp_h($question['points']) ?> <?= qp_h(pu('points','points')) ?></p>
 <?php if($question['pre_question_content']): ?><div class="pu-rich questionnaire-paragraph"><?= qp_rich_html($question['pre_question_content']) ?></div><?php endif; ?>
 <h3 id="question_<?= $id ?>"><?= nl2br(qp_h($question['question_text'])) ?></h3>
 <?php pu_media($question['media_type'],$question['media_path'],pu('Exercise image','Image de l’exercice')); ?>
 <?php if($question['question_type']==='open'): ?><label for="answer_<?= $id ?>"><?= qp_h(pu('Your answer','Votre réponse')) ?></label><textarea id="answer_<?= $id ?>" name="answers[<?= $id ?>]" rows="6" aria-labelledby="question_<?= $id ?>"><?= qp_h(is_string($values)?$values:'') ?></textarea>
 <?php else: ?><p><?= qp_h($question['question_type']==='multiple_choice'?pu('Select all applicable answers.','Sélectionnez toutes les réponses adaptées.'):pu('Select one answer.','Sélectionnez une réponse.')) ?></p>
 <?php foreach($question['choices'] as $choice): ?><label class="pu-choice"><input type="<?= $question['question_type']==='single_choice'?'radio':'checkbox' ?>" name="answers[<?= $id ?>]<?= $question['question_type']==='multiple_choice'?'[]':'' ?>" value="<?= (int)$choice['id'] ?>" <?= in_array((string)$choice['id'],array_map('strval',$choiceValues),true)?'checked':'' ?>> <span><?= qp_h($choice['choice_text']) ?></span></label><?php endforeach; endif; ?>
 </article>
</section>
<?php endforeach; ?>
<div class="actions pu-page-controls" hidden><button id="pu-previous" type="button" class="btn-secondary"><?= qp_h(pu('Previous','Précédent')) ?></button><span id="pu-page-position"></span><button id="pu-next" type="button"><?= qp_h(pu('Next','Suivant')) ?></button></div>
<section class="submit-area"><h2><?= qp_h(pu('Finish your positioning test','Terminer votre test de positionnement')) ?></h2><p><?= qp_h(pu('Review unanswered questions before confirming your submission.','Vérifiez les questions sans réponse avant de confirmer votre envoi.')) ?></p><p id="pu-unanswered" aria-live="polite"></p><button type="submit" id="pu-finish"><?= qp_h(pu('Submit / Finish','Envoyer / Terminer')) ?></button></section>
</div></div>
</form>
</main>


</div>

</body>

</html>