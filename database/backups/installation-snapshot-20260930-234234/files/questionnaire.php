<?php
require_once __DIR__ . "/includes/safe_rich_content.php";

session_start();

require_once 'config/database.php';
require_once __DIR__ . '/includes/trainee_language.php';


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

    <style>
        body.trainee-course { margin:0; background:#f3f7f8; color:#20343e; font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; line-height:1.65; }
        .trainee-course *, .trainee-course *::before, .trainee-course *::after { box-sizing:border-box; }
        .trainee-course .container { width:calc(100% - 2rem); max-width:960px; margin:0 auto; padding:2rem 0; background:transparent; border:0; box-shadow:none; }
        .trainee-course h1, .trainee-course h2, .trainee-course h3, .trainee-course h4 { color:#173b45; line-height:1.3; overflow-wrap:anywhere; }
        .trainee-course h1 { font-size:clamp(1.8rem,4vw,2.7rem); margin:.4rem 0 1rem; }
        .trainee-course h2 { font-size:1.6rem; }
        .trainee-course h3 { font-size:1.3rem; }
        .trainee-course h4 { font-size:1.15rem; }
        .trainee-course p { margin:0 0 1rem; }
        .trainee-course a { color:#09665f; text-underline-offset:.2em; }
        .trainee-course :is(a,button,input,textarea):focus-visible { outline:3px solid #9b4c00; outline-offset:4px; }
        .trainee-course .questionnaire-header, .trainee-course .question-block, .trainee-course .submit-area { padding:clamp(1.25rem,3vw,2rem); margin:1.5rem 0; background:#fff; border:1px solid #d8e4e7; border-radius:18px; box-shadow:0 6px 22px rgba(23,59,69,.04); }
        .trainee-course .questionnaire-header { text-align:left; border-top:5px solid #09665f; }
        .trainee-course .eyebrow, .trainee-course :is(.questionnaire-chapter,.questionnaire-lesson,.questionnaire-topic) > p { color:#09665f; font-size:.8rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; margin:0 0 .35rem; }
        .trainee-course .mode-badge, .trainee-course .points-badge { display:table; max-width:100%; padding:.35rem .8rem; border-radius:999px; background:#e5f3ef; color:#14584f; font-size:.9rem; overflow-wrap:anywhere; }
        .trainee-course .questionnaire-header > p:last-child { margin:1.25rem 0 0; padding-top:1rem; border-top:1px solid #d8e4e7; color:#536772; }
        .trainee-course .questionnaire-chapter { margin:2.5rem 0 1.5rem; padding:1.3rem 1.5rem; background:#e5f3ef; border-left:5px solid #09665f; border-radius:0 14px 14px 0; }
        .trainee-course .questionnaire-lesson { margin:2rem 0 1rem; padding-left:1rem; border-left:3px solid #70a69c; }
        .trainee-course .questionnaire-topic { margin:1.5rem 0 1rem; padding-left:1rem; border-left:3px solid #c0d8d2; }
        .trainee-course :is(.questionnaire-chapter,.questionnaire-lesson,.questionnaire-topic) :is(h2,h3,h4) { margin:0; }
        .trainee-course .questionnaire-paragraph { margin:1rem 0 1.5rem; padding:1.25rem; background:#edf2f4; border:1px solid #d8e4e7; border-radius:12px; overflow-wrap:anywhere; }
        .trainee-course .questionnaire-paragraph h4 { margin:.4rem 0 1rem; }
        .trainee-course .question-block { min-width:0; overflow-wrap:anywhere; }
        .trainee-course .question-block h3 { margin:0 0 1rem; color:#09665f; }
        .trainee-course .question-media { margin:1.5rem 0; }
        .trainee-course .question-media :is(img,audio,video) { display:block; width:100%; max-width:700px; border-radius:10px; }
        .trainee-course .question-media :is(img,video) { height:auto; }
        .trainee-course .question-block label:has(input) { display:flex; align-items:flex-start; gap:.8rem; width:100%; min-height:48px; padding:1rem; background:#f8fafb; border:1px solid #ccdce0; border-radius:10px; cursor:pointer; }
        .trainee-course .question-block label:has(input):hover { border-color:#09665f; background:#eff7f4; }
        .trainee-course .question-block label:has(input:checked) { border-color:#09665f; background:#e5f3ef; }
        .trainee-course .question-block label:has(input:focus-visible) { outline:3px solid #9b4c00; outline-offset:3px; }
        .trainee-course input:is([type=radio],[type=checkbox]) { flex:0 0 auto; width:1.2rem; height:1.2rem; margin:.2rem 0 0; accent-color:#09665f; }
        .trainee-course textarea { display:block; width:100%; max-width:48rem; min-height:160px; padding:1rem; border:1px solid #a9bfc5; border-radius:10px; resize:vertical; font:inherit; color:inherit; background:#fff; }
        .trainee-course .submit-area { border-top:4px solid #09665f; }
        .trainee-course .submit-area h2 { margin:0 0 .5rem; }
        .trainee-course .submit-area button { width:auto; min-height:48px; padding:.85rem 1.5rem; border:1px solid #09665f; border-radius:10px; background:#09665f; color:#fff; font:inherit; font-weight:700; cursor:pointer; }
        .trainee-course .submit-area button:hover { background:#074f49; }
        .trainee-course .container > hr, .trainee-course form > hr { display:none; }
        @media (max-width:600px) { .trainee-course .container { width:calc(100% - 1rem); padding:1rem 0; } .trainee-course .submit-area button { width:100%; } }
    </style>
    <link rel="stylesheet" href="assets/css/trainee_language.css">
<link rel="stylesheet" href="assets/css/ui.css">
<script src="assets/js/ui.js" defer></script>
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
>


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


    <?php

    // ==================================================
    // TRACK PREVIOUS HIERARCHY
    // ==================================================

    $previousChapterId = null;
    $previousLessonId = null;
    $previousTopicId = null;
    $previousParagraphId = null;

    $questionNumber = 0;

    ?>


    <?php foreach ($questions as $question): ?>


        <?php

        $currentChapterId =
            $question['chapter_id'] !== null
                ? (int)$question['chapter_id']
                : null;

        $currentLessonId =
            $question['lesson_id'] !== null
                ? (int)$question['lesson_id']
                : null;

        $currentTopicId =
            $question['topic_id'] !== null
                ? (int)$question['topic_id']
                : null;

        $currentParagraphId =
            $question['paragraph_id'] !== null
                ? (int)$question['paragraph_id']
                : null;


        // ==============================================
        // NEW CHAPTER
        // ==============================================

        if (
            $currentChapterId !== null &&
            $currentChapterId !==
                $previousChapterId
        ):

        ?>

            <section
                class="questionnaire-chapter"
            >

                <p>
                    <strong>
                        <?= htmlspecialchars(t('h150_chapter'), ENT_QUOTES, 'UTF-8') ?>
                    </strong>
                </p>

                <h2>

                    <?= htmlspecialchars(
                        $question['chapter_title']
                    ) ?>

                </h2>

            </section>

        <?php

            $previousLessonId = null;
            $previousTopicId = null;
            $previousParagraphId = null;

        endif;


        // ==============================================
        // NEW LESSON
        // ==============================================

        if (
            $currentLessonId !== null &&
            $currentLessonId !==
                $previousLessonId
        ):

        ?>

            <section
                class="questionnaire-lesson"
            >

                <p>
                    <strong>
                        <?= htmlspecialchars(t('h150_lesson'), ENT_QUOTES, 'UTF-8') ?>
                    </strong>
                </p>

                <h3>

                    <?= htmlspecialchars(
                        $question['lesson_title']
                    ) ?>

                </h3>

            </section>

        <?php

            $previousTopicId = null;
            $previousParagraphId = null;

        endif;


        // ==============================================
        // NEW TOPIC
        // ==============================================

        if (
            $currentTopicId !== null &&
            $currentTopicId !==
                $previousTopicId
        ):

        ?>

            <section
                class="questionnaire-topic"
            >

                <p>
                    <strong>
                        <?= htmlspecialchars(t('h150_topic'), ENT_QUOTES, 'UTF-8') ?>
                    </strong>
                </p>

                <h4>

                    <?= htmlspecialchars(
                        $question['topic_title']
                    ) ?>

                </h4>

            </section>

        <?php

            $previousParagraphId = null;

        endif;


        // ==============================================
        // NEW PARAGRAPH
        // ==============================================

        if (
            $currentParagraphId !== null &&
            $currentParagraphId !==
                $previousParagraphId
        ):

        ?>

            <section
                class="questionnaire-paragraph"
            >


                <?php if (
                    !empty(
                        $question[
                            'paragraph_title'
                        ]
                    )
                ): ?>

                    <h4>

                        <?= htmlspecialchars(
                            $question[
                                'paragraph_title'
                            ]
                        ) ?>

                    </h4>

                <?php endif; ?>


                <?php if (
                    !empty(
                        $question[
                            'paragraph_content'
                        ]
                    )
                ): ?>

                    <div>

                        <?= qp_rich_html($question['paragraph_content'] ?? '') ?>

                    </div>

                <?php endif; ?>


            </section>

        <?php endif; ?>


        <?php

        // ==============================================
        // UPDATE HIERARCHY TRACKING
        // ==============================================

        $previousChapterId =
            $currentChapterId;

        $previousLessonId =
            $currentLessonId;

        $previousTopicId =
            $currentTopicId;

        $previousParagraphId =
            $currentParagraphId;

        $questionNumber++;

        ?>


        <!-- ========================================== -->
        <!-- QUESTION -->
        <!-- ========================================== -->

        <section
            class="question-block" role="group" aria-labelledby="question_<?= (int)$question['id'] ?>"
        >


            <h3>

                <?= htmlspecialchars(t('h150_question'), ENT_QUOTES, 'UTF-8') ?>
                <?= $questionNumber ?>

            </h3>


            <?php if (!empty($question['pre_question_content'])): ?>
                <div class="questionnaire-paragraph qp-question-context"><?= qp_rich_html($question['pre_question_content']) ?></div>
            <?php endif; ?>

            <p id="question_<?= (int)$question['id'] ?>">

                <strong>

                    <?= nl2br(
                        htmlspecialchars(
                            $question[
                                'question_text'
                            ]
                        )
                    ) ?>

                </strong>

            </p>


            <p class="points-badge">

                <?= htmlspecialchars(t('h150_points'), ENT_QUOTES, 'UTF-8') ?>

                <strong>

                    <?= htmlspecialchars(
                        $question['points']
                    ) ?>

                </strong>

            </p>


            <!-- ====================================== -->
            <!-- MEDIA -->
            <!-- ====================================== -->

            <?php if (
                !empty(
                    $question['media_path']
                )
            ): ?>


                <?php

                $mediaPath =
                    $question['media_path'];

                ?>


                <div
                    class="question-media"
                >


                    <?php if (
                        $question['media_type']
                        === 'image'
                    ): ?>


                        <img
                            src="<?= htmlspecialchars(
                                $mediaPath
                            ) ?>"
                            alt="<?= htmlspecialchars(t('h150_question_image'), ENT_QUOTES, 'UTF-8') ?>"
                        >


                    <?php elseif (
                        $question['media_type']
                        === 'audio'
                    ): ?>


                        <audio
                            controls
                            preload="metadata"
                        >

                            <source
                                src="<?= htmlspecialchars(
                                    $mediaPath
                                ) ?>"
                            >

                            <?= htmlspecialchars(t('h150_your_browser_does_not_support_audio_playback'), ENT_QUOTES, 'UTF-8') ?>

                        </audio>


                    <?php elseif (
                        $question['media_type']
                        === 'video'
                    ): ?>


                        <video
                            controls
                            preload="metadata"
                        >

                            <source
                                src="<?= htmlspecialchars(
                                    $mediaPath
                                ) ?>"
                            >

                            <?= htmlspecialchars(t('h150_your_browser_does_not_support_video_playback'), ENT_QUOTES, 'UTF-8') ?>

                        </video>


                    <?php endif; ?>


                </div>


            <?php endif; ?>


            <!-- ====================================== -->
            <!-- SINGLE CHOICE -->
            <!-- ====================================== -->

            <?php if (
                $question['question_type']
                === 'single_choice'
            ): ?>


                <div>


                    <?php foreach (
                        $question['choices']
                        as $choice
                    ): ?>


                        <p>

                            <label>


                                <input
                                    type="radio"
                                    name="answers[<?= (int)$question['id'] ?>]"
                                    value="<?= (int)$choice['id'] ?>" <?= traineeChoiceSelected((int)$question['id'], (int)$choice['id']) ? 'checked' : '' ?>
                                >


                                <?= htmlspecialchars(
                                    $choice[
                                        'choice_text'
                                    ]
                                ) ?>


                            </label>

                        </p>


                    <?php endforeach; ?>


                </div>


            <!-- ====================================== -->
            <!-- MULTIPLE CHOICE -->
            <!-- ====================================== -->

            <?php elseif (
                $question['question_type']
                === 'multiple_choice'
            ): ?>


                <p>

                    <em>
                        <?= htmlspecialchars(t('h150_select_all_correct_answers'), ENT_QUOTES, 'UTF-8') ?>
                    </em>

                </p>


                <div>


                    <?php foreach (
                        $question['choices']
                        as $choice
                    ): ?>


                        <p>

                            <label>


                                <input
                                    type="checkbox"
                                    name="answers[<?= (int)$question['id'] ?>][]"
                                    value="<?= (int)$choice['id'] ?>" <?= traineeChoiceSelected((int)$question['id'], (int)$choice['id']) ? 'checked' : '' ?>
                                >


                                <?= htmlspecialchars(
                                    $choice[
                                        'choice_text'
                                    ]
                                ) ?>


                            </label>

                        </p>


                    <?php endforeach; ?>


                </div>


            <!-- ====================================== -->
            <!-- OPEN QUESTION -->
            <!-- ====================================== -->

            <?php elseif (
                $question['question_type']
                === 'open'
            ): ?>


                <div>

                    <label
                        id="answer_label_<?= (int)$question['id'] ?>" for="answer_<?= (int)$question['id'] ?>"
                    >
                        <?= htmlspecialchars(t('h150_your_answer'), ENT_QUOTES, 'UTF-8') ?>
                    </label>


                    


                    <textarea
                        id="answer_<?= (int)$question['id'] ?>"
                        name="answers[<?= (int)$question['id'] ?>]"
                        aria-labelledby="question_<?= (int)$question['id'] ?> answer_label_<?= (int)$question['id'] ?>"
                        rows="6"
                        cols="70"
                    ><?= htmlspecialchars(traineeAnswerText((int)$question['id']), ENT_QUOTES, 'UTF-8') ?></textarea>

                </div>


            <?php endif; ?>


        </section>


    <?php endforeach; ?>


    <hr>


    <!-- ============================================== -->
    <!-- SUBMIT -->
    <!-- ============================================== -->

    <section class="submit-area" aria-labelledby="submit-heading"><h2 id="submit-heading"><?= htmlspecialchars(t('h150_ready_to_submit'), ENT_QUOTES, 'UTF-8') ?></h2><p><?= htmlspecialchars(t('h150_review_your_answers_before_submitting_your_questionnaire'), ENT_QUOTES, 'UTF-8') ?></p><p>

        <button
            type="submit"
            data-confirm="<?= htmlspecialchars(t('h150_are_you_sure_you_want_to_submit_this_questionnaire'), ENT_QUOTES, 'UTF-8') ?>" onclick="return confirm(this.dataset.confirm);"
        >

            <?= htmlspecialchars(t('h150_submit_questionnaire'), ENT_QUOTES, 'UTF-8') ?>

        </button>

    </p>


</section>
</form>
</main>


</div>

</body>

</html>