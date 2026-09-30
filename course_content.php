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


$traineeId =
    (int)$_SESSION['trainee_id'];


// ======================================================
// GET TRAINEE INFORMATION
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
// GET COURSE CONTENT
//
// Structure:
//
// Theme
//   Chapter
//     Lesson
//       Topic
//         Paragraph
//
// Only active content is shown to trainees.
// ======================================================

$stmt = $pdo->query(
    "SELECT

        t.id AS theme_id,
        t.name AS theme_name,
        t.description AS theme_description,

        c.id AS chapter_id,
        c.title AS chapter_title,
        c.display_order AS chapter_order,

        l.id AS lesson_id,
        l.title AS lesson_title,
        l.display_order AS lesson_order,

        tp.id AS topic_id,
        tp.title AS topic_title,
        tp.display_order AS topic_order,

        p.id AS paragraph_id,
        p.title AS paragraph_title,
        p.content AS paragraph_content,
        p.media_type AS paragraph_media_type,
        p.media_path AS paragraph_media_path,
        p.display_order AS paragraph_order

     FROM themes t

     INNER JOIN chapters c
        ON c.theme_id = t.id
       AND c.is_active = 1

     INNER JOIN lessons l
        ON l.chapter_id = c.id
       AND l.is_active = 1

     INNER JOIN topics tp
        ON tp.lesson_id = l.id
       AND tp.is_active = 1

     INNER JOIN paragraphs p
        ON p.topic_id = tp.id
       AND p.is_active = 1

     WHERE t.is_active = 1

     ORDER BY
        t.id ASC,
        c.display_order ASC,
        c.id ASC,
        l.display_order ASC,
        l.id ASC,
        tp.display_order ASC,
        tp.id ASC,
        p.display_order ASC,
        p.id ASC"
);

$contentRows =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// BUILD NESTED COURSE STRUCTURE
// ======================================================

$courseContent = [];


foreach ($contentRows as $row) {

    $themeId =
        (int)$row['theme_id'];

    $chapterId =
        (int)$row['chapter_id'];

    $lessonId =
        (int)$row['lesson_id'];

    $topicId =
        (int)$row['topic_id'];

    $paragraphId =
        (int)$row['paragraph_id'];


    // --------------------------------------------------
    // THEME
    // --------------------------------------------------

    if (!isset(
        $courseContent[$themeId]
    )) {

        $courseContent[$themeId] = [

            'id' =>
                $themeId,

            'name' =>
                $row['theme_name'],

            'description' =>
                $row['theme_description'],

            'chapters' =>
                []

        ];
    }


    // --------------------------------------------------
    // CHAPTER
    // --------------------------------------------------

    if (!isset(
        $courseContent[$themeId]
        ['chapters'][$chapterId]
    )) {

        $courseContent[$themeId]
        ['chapters'][$chapterId] = [

            'id' =>
                $chapterId,

            'title' =>
                $row['chapter_title'],

            'lessons' =>
                []

        ];
    }


    // --------------------------------------------------
    // LESSON
    // --------------------------------------------------

    if (!isset(
        $courseContent[$themeId]
        ['chapters'][$chapterId]
        ['lessons'][$lessonId]
    )) {

        $courseContent[$themeId]
        ['chapters'][$chapterId]
        ['lessons'][$lessonId] = [

            'id' =>
                $lessonId,

            'title' =>
                $row['lesson_title'],

            'topics' =>
                []

        ];
    }


    // --------------------------------------------------
    // TOPIC
    // --------------------------------------------------

    if (!isset(
        $courseContent[$themeId]
        ['chapters'][$chapterId]
        ['lessons'][$lessonId]
        ['topics'][$topicId]
    )) {

        $courseContent[$themeId]
        ['chapters'][$chapterId]
        ['lessons'][$lessonId]
        ['topics'][$topicId] = [

            'id' =>
                $topicId,

            'title' =>
                $row['topic_title'],

            'paragraphs' =>
                []

        ];
    }


    // --------------------------------------------------
    // PARAGRAPH
    // --------------------------------------------------

    $courseContent[$themeId]
    ['chapters'][$chapterId]
    ['lessons'][$lessonId]
    ['topics'][$topicId]
    ['paragraphs'][$paragraphId] = [

        'id' =>
            $paragraphId,

        'title' =>
            $row['paragraph_title'],

        'content' =>
            $row['paragraph_content'],

        'media_type' =>
            $row['paragraph_media_type'],

        'media_path' =>
            $row['paragraph_media_path']

    ];
}

?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(htmlLanguage(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(t('h150_course_content'), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>

        /* Reuse the trainee design embedded in the Step 138A homepage. */
        body.trainee-course {
            margin: 0;
            background: #f3f7f8;
            color: #20343e;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.65;
        }
        .trainee-course *,
        .trainee-course *::before,
        .trainee-course *::after { box-sizing: border-box; }
        .trainee-course .container {
            width: calc(100% - 2rem);
            max-width: 1120px;
            margin: 0 auto;
            padding: 2rem 0;
            background: transparent;
            border: 0;
            box-shadow: none;
        }
        .trainee-course h1, .trainee-course h2 {
            color: #173b45;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }
        .trainee-course h1 { margin: .35rem 0 .75rem; font-size: clamp(1.8rem, 4vw, 2.7rem); }
        .trainee-course h2 { margin: 0 0 .75rem; font-size: clamp(1.35rem, 3vw, 1.8rem); }
        .trainee-course p { margin: 0 0 1rem; }
        .trainee-course a { color: #09665f; text-underline-offset: .2em; }
        .trainee-course a:focus-visible { outline: 3px solid #9b4c00; outline-offset: 4px; }
        .trainee-course .text-muted { color: #536772; }
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
        .trainee-course .trainee-header { margin-bottom: 1.5rem; padding: 0; border: 0; }
        .trainee-course .trainee-header > * { min-width: 0; }
        .trainee-course .trainee-header p { margin-bottom: 0; overflow-wrap: anywhere; }
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
        .trainee-course .actions { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; }
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
        }
        .trainee-course .btn:hover { background: #064f49; border-color: #064f49; }
        .trainee-course .btn-secondary { background: #fff; color: #09665f; border-color: #8baea8; }
        .trainee-course .btn-secondary:hover { background: #edf7f4; color: #064f49; border-color: #09665f; }
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
        .trainee-course .trainee-flow [aria-current="step"] { background: #e8f5f1; border: 2px solid #08756a; }
        .trainee-course [aria-current="step"] .trainee-step-number { background: #09665f; color: #fff; }
        .trainee-course .trainee-current { display: block; margin-top: .5rem; color: #09665f; font-size: .75rem; font-weight: 700; }
        .trainee-course .introduction-content { max-width: 75ch; line-height: 1.8; overflow-wrap: anywhere; }
        .trainee-course .introduction-updated { margin: 1.5rem 0 0; padding-top: 1rem; border-top: 1px solid #d8e4e7; }
        .trainee-course .workflow-navigation {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            background: #e8f5f1;
        }
        .trainee-course .trainee-account { padding: .5rem 0 1rem; }
        .trainee-course .trainee-account p,
        .trainee-course .trainee-account .actions { margin: 0; }
        @media (max-width: 900px) {
            .trainee-course .trainee-flow { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        }
        @media (max-width: 600px) {
            .trainee-course .container { padding-top: 1.25rem; }
            .trainee-course .trainee-flow { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .trainee-course .trainee-account .actions,
            .trainee-course .actions .btn,
            .trainee-course .workflow-navigation .btn { width: 100%; }
        }
            .trainee-course .course-theme { overflow-wrap: anywhere; }
        .trainee-course .theme-heading { padding-bottom: 1.25rem; border-bottom: 3px solid #09665f; }
        .trainee-course .theme-heading p:last-child { margin-bottom: 0; }
        .trainee-course .course-chapter { margin-top: 2rem; }
        .trainee-course .course-chapter > h3 { margin: 0 0 1.5rem; padding: 1rem; background: #e8f5f1; border-left: 4px solid #09665f; border-radius: 0 10px 10px 0; color: #173b45; font-size: 1.35rem; line-height: 1.4; }
        .trainee-course .course-lesson { margin: 1.5rem 0; }
        .trainee-course .course-lesson > h4 { margin: 0 0 1rem; color: #173b45; font-size: 1.2rem; line-height: 1.4; }
        .trainee-course .course-topic { margin: 1.25rem 0 2rem; padding-left: clamp(.75rem, 2vw, 1.5rem); border-left: 2px solid #d8e4e7; }
        .trainee-course .course-topic > h5 { margin: 0 0 1rem; color: #435d68; font-size: 1.05rem; line-height: 1.5; }
        .trainee-course .course-paragraph { min-width: 0; margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px solid #d8e4e7; }
        .trainee-course .course-paragraph > h6 { margin: 0 0 .75rem; color: #20343e; font-size: 1rem; line-height: 1.5; }
        .trainee-course .paragraph-content { max-width: 80ch; overflow-x: auto; overflow-wrap: anywhere; line-height: 1.8; }
        .trainee-course .paragraph-content img,
        .trainee-course .paragraph-content video { max-width: 100%; height: auto; }
        .trainee-course .paragraph-content audio,
        .trainee-course .paragraph-content iframe { max-width: 100%; }
        .trainee-course .paragraph-content figure { max-width: 100%; margin: 1rem 0; }
        .trainee-course .paragraph-content pre { max-width: 100%; overflow-x: auto; padding: 1rem; background: #f5f8f9; border-radius: 8px; }
        .trainee-course .paragraph-content blockquote { margin: 1rem 0; padding: .5rem 1rem; border-left: 3px solid #8baea8; background: #f5f8f9; }
        .trainee-course .paragraph-content table { border-collapse: collapse; }
        .trainee-course .paragraph-content th,
        .trainee-course .paragraph-content td { padding: .65rem; border: 1px solid #d8e4e7; text-align: left; }
        .trainee-course .paragraph-media { margin-top: 1rem; }
        .trainee-course .paragraph-media img { display: block; max-width: 100%; height: auto; border-radius: 10px; }
        .trainee-course .paragraph-media audio { display: block; width: 100%; max-width: 700px; }
        .trainee-course .paragraph-media video { display: block; width: 100%; max-width: 800px; height: auto; border-radius: 10px; }
        .trainee-course .empty-state { border-style: dashed; }
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
            <h1><?= htmlspecialchars(t('h150_course_content'), ENT_QUOTES, 'UTF-8') ?></h1>
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
            <li aria-current="step">
                <span class="trainee-step-number" aria-hidden="true">4</span>
                <?= htmlspecialchars(t('h150_course_content'), ENT_QUOTES, 'UTF-8') ?>
                <span class="trainee-current"><?= htmlspecialchars(t('h150_current_step'), ENT_QUOTES, 'UTF-8') ?></span>
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
        <section class="card" aria-labelledby="learning-title">
            <p class="trainee-eyebrow"><?= htmlspecialchars(t('h150_step_4_of_7'), ENT_QUOTES, 'UTF-8') ?></p>
            <h2 id="learning-title"><?= htmlspecialchars(t('h150_learning_content'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p><?= htmlspecialchars(t('h150_read_the_course_content_below_before_continuing_to_your_questionnaires'), ENT_QUOTES, 'UTF-8') ?></p>
            <p><?= htmlspecialchars(t('h150_the_content_is_organised_as'), ENT_QUOTES, 'UTF-8') ?></p>
            <p><strong><?= htmlspecialchars(t('h150_theme_chapter_lesson_topic_paragraph'), ENT_QUOTES, 'UTF-8') ?></strong></p>
        </section>

        <?php if (empty($courseContent)): ?>
            <section class="card empty-state">
                <h2><?= htmlspecialchars(t('h150_no_course_content'), ENT_QUOTES, 'UTF-8') ?></h2>
                <p><?= htmlspecialchars(t('h150_no_course_chapters_lessons_topics_or_paragraphs_are_currently_available'), ENT_QUOTES, 'UTF-8') ?></p>
            </section>
        <?php else: ?>
            <?php foreach ($courseContent as $theme): ?>
                <section class="card course-theme">
                    <div class="theme-heading">
                        <p class="trainee-eyebrow"><?= htmlspecialchars(t('h150_theme'), ENT_QUOTES, 'UTF-8') ?></p>
                        <h2><?= htmlspecialchars($theme['name']) ?></h2>
                        <?php if (!empty($theme['description'])): ?>
                            <p><?= nl2br(htmlspecialchars($theme['description'])) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php foreach ($theme['chapters'] as $chapter): ?>
                        <article class="course-chapter">
                            <h3><?= htmlspecialchars(t('h150_chapter_label'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($chapter['title']) ?></h3>
                            <?php foreach ($chapter['lessons'] as $lesson): ?>
                                <div class="course-lesson">
                                    <h4><?= htmlspecialchars(t('h150_lesson_label'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($lesson['title']) ?></h4>
                                    <?php foreach ($lesson['topics'] as $topic): ?>
                                        <div class="course-topic">
                                            <h5><?= htmlspecialchars(t('h150_topic_label'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($topic['title']) ?></h5>
                                            <?php foreach ($topic['paragraphs'] as $paragraph): ?>
                                                <div class="course-paragraph">
                                                    <?php if (!empty($paragraph['title'])): ?>
                                                        <h6><?= htmlspecialchars($paragraph['title']) ?></h6>
                                                    <?php endif; ?>
                                                    <div class="paragraph-content">
                                                        <?php
                                                        // Paragraph HTML is sanitized by the admin content
                                                        // manager before it is stored in the database.
                                                        ?>
                                                        <?= qp_rich_html($paragraph['content'] ?? '') ?>
                                                    </div>
                                                    <?php if (!empty($paragraph['media_path'])): ?>
                                                        <?php $mediaPath = ltrim($paragraph['media_path'], '/'); ?>
                                                        <div class="paragraph-media">
                                                            <?php if ($paragraph['media_type'] === 'image'): ?>
                                                                <img src="<?= htmlspecialchars($mediaPath) ?>"
                                                                     alt="<?= htmlspecialchars($paragraph['title'] ?: t('h150_course_image')) ?>">
                                                            <?php elseif ($paragraph['media_type'] === 'audio'): ?>
                                                                <audio controls preload="metadata">
                                                                    <source src="<?= htmlspecialchars($mediaPath) ?>">
                                                                    <?= htmlspecialchars(t('h150_your_browser_does_not_support_audio_playback'), ENT_QUOTES, 'UTF-8') ?>
                                                                </audio>
                                                            <?php elseif ($paragraph['media_type'] === 'video'): ?>
                                                                <video controls preload="metadata">
                                                                    <source src="<?= htmlspecialchars($mediaPath) ?>">
                                                                    <?= htmlspecialchars(t('h150_your_browser_does_not_support_video_playback'), ENT_QUOTES, 'UTF-8') ?>
                                                                </video>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>

        <nav class="card workflow-navigation" aria-label="<?= htmlspecialchars(t('h150_previous_and_next_course_pages'), ENT_QUOTES, 'UTF-8') ?>">
            <a class="btn btn-secondary" href="introduction.php"><?= htmlspecialchars(t('h150_previous_introduction'), ENT_QUOTES, 'UTF-8') ?></a>
            <a class="btn" href="assigned_questionnaires.php"><?= htmlspecialchars(t('h150_next_assigned_questionnaires'), ENT_QUOTES, 'UTF-8') ?></a>
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