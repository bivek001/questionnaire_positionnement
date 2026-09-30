<?php
require_once __DIR__.'/../includes/safe_rich_content.php';
require_once __DIR__ . '/professor_language.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once '../config/database.php';


// ======================================================
// PROFESSOR AUTHENTICATION
// ======================================================

if (!isset($_SESSION['professor_id'])) {
    header('Location: login.php?lang=' . rawurlencode(currentLanguage()));
    exit;
}

$professorId = (int)$_SESSION['professor_id'];


// ======================================================
// VERIFY PROFESSOR ACCOUNT
// ======================================================

$stmt = $pdo->prepare(
    "SELECT
        id,
        first_name,
        last_name,
        email,
        username,
        is_active
     FROM professors
     WHERE id = ?
     LIMIT 1"
);

$stmt->execute([$professorId]);

$professor = $stmt->fetch(PDO::FETCH_ASSOC);

if (
    !$professor
    || (int)$professor['is_active'] !== 1
) {
    $_SESSION = [];
    session_destroy();

    header('Location: login.php?lang=' . rawurlencode(currentLanguage()));
    exit;
}


// ======================================================
// UPDATE SESSION INFORMATION
// ======================================================

$_SESSION['professor_username'] =
    $professor['username'];

$_SESSION['professor_name'] =
    trim(
        $professor['first_name']
        . ' '
        . $professor['last_name']
    );


// ======================================================
// LOAD PROFESSOR CONTENT ASSIGNMENTS
// ======================================================

$stmt = $pdo->prepare(
    "SELECT
        pca.id AS assignment_id,
        pca.theme_id,
        pca.chapter_id,
        pca.created_at,

        t.name AS theme_name,
        t.description AS theme_description,
        t.display_order AS theme_order,

        c.title AS assigned_chapter_title

     FROM professor_content_assignments pca

     INNER JOIN themes t
        ON t.id = pca.theme_id

     LEFT JOIN chapters c
        ON c.id = pca.chapter_id

     WHERE pca.professor_id = ?
       AND t.is_active = 1

     ORDER BY
        t.display_order ASC,
        t.name ASC,
        CASE
            WHEN pca.chapter_id IS NULL THEN 0
            ELSE 1
        END ASC,
        c.display_order ASC,
        c.title ASC"
);

$stmt->execute([$professorId]);

$assignments =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// ORGANIZE PERMITTED THEMES
// ======================================================

$permittedThemes = [];

foreach ($assignments as $assignment) {

    $themeId = (int)$assignment['theme_id'];

    if (!isset($permittedThemes[$themeId])) {

        $permittedThemes[$themeId] = [
            'id' => $themeId,
            'name' => $assignment['theme_name'],
            'description' => $assignment['theme_description'],
            'entire_theme' => false,
            'chapter_ids' => []
        ];
    }

    if ($assignment['chapter_id'] === null) {

        $permittedThemes[$themeId]['entire_theme'] = true;
        $permittedThemes[$themeId]['chapter_ids'] = [];

    } elseif (
        !$permittedThemes[$themeId]['entire_theme']
    ) {

        $permittedThemes[$themeId]['chapter_ids'][] =
            (int)$assignment['chapter_id'];
    }
}


// ======================================================
// LOAD PERMITTED COURSE HIERARCHY
// ======================================================

foreach ($permittedThemes as $themeId => &$theme) {

    /*
     * SECURITY:
     *
     * We do not trust a theme/chapter ID supplied by
     * the browser. The query is built from assignments
     * already loaded for the authenticated professor.
     */

    if ($theme['entire_theme']) {

        $stmt = $pdo->prepare(
            "SELECT
                id,
                theme_id,
                title,
                display_order
             FROM chapters
             WHERE theme_id = ?
               AND is_active = 1
             ORDER BY
                display_order ASC,
                title ASC"
        );

        $stmt->execute([$themeId]);

    } else {

        if (empty($theme['chapter_ids'])) {
            $theme['chapters'] = [];
            continue;
        }

        $placeholders =
            implode(
                ',',
                array_fill(
                    0,
                    count($theme['chapter_ids']),
                    '?'
                )
            );

        $parameters = array_merge(
            [$themeId],
            $theme['chapter_ids']
        );

        $stmt = $pdo->prepare(
            "SELECT
                id,
                theme_id,
                title,
                display_order
             FROM chapters
             WHERE theme_id = ?
               AND id IN ($placeholders)
               AND is_active = 1
             ORDER BY
                display_order ASC,
                title ASC"
        );

        $stmt->execute($parameters);
    }

    $theme['chapters'] =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


    // ==================================================
    // LOAD LESSONS FOR EACH PERMITTED CHAPTER
    // ==================================================

    foreach ($theme['chapters'] as &$chapter) {

        $stmt = $pdo->prepare(
            "SELECT
                id,
                chapter_id,
                title,
                display_order
             FROM lessons
             WHERE chapter_id = ?
               AND is_active = 1
             ORDER BY
                display_order ASC,
                title ASC"
        );

        $stmt->execute([
            (int)$chapter['id']
        ]);

        $chapter['lessons'] =
            $stmt->fetchAll(PDO::FETCH_ASSOC);


        // ==============================================
        // LOAD TOPICS FOR EACH LESSON
        // ==============================================

        foreach ($chapter['lessons'] as &$lesson) {

            $stmt = $pdo->prepare(
                "SELECT
                    id,
                    lesson_id,
                    title,
                    display_order
                 FROM topics
                 WHERE lesson_id = ?
                   AND is_active = 1
                 ORDER BY
                    display_order ASC,
                    title ASC"
            );

            $stmt->execute([
                (int)$lesson['id']
            ]);

            $lesson['topics'] =
                $stmt->fetchAll(PDO::FETCH_ASSOC);


            // ==========================================
            // LOAD PARAGRAPHS FOR EACH TOPIC
            // ==========================================

            foreach ($lesson['topics'] as &$topic) {

                $stmt = $pdo->prepare(
                    "SELECT
                        id,
                        topic_id,
                        title,
                        content,
                        media_type,
                        media_path,
                        display_order
                     FROM paragraphs
                     WHERE topic_id = ?
                       AND is_active = 1
                     ORDER BY
                        display_order ASC,
                        id ASC"
                );

                $stmt->execute([
                    (int)$topic['id']
                ]);

                $topic['paragraphs'] =
                    $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            unset($topic);
        }

        unset($lesson);
    }

    unset($chapter);
}

unset($theme);


// ======================================================
// STATISTICS
// ======================================================

$themeCount = count($permittedThemes);

$chapterCount = 0;
$lessonCount = 0;
$topicCount = 0;
$paragraphCount = 0;

foreach ($permittedThemes as $theme) {

    $chapterCount += count($theme['chapters']);

    foreach ($theme['chapters'] as $chapter) {

        $lessonCount += count($chapter['lessons']);

        foreach ($chapter['lessons'] as $lesson) {

            $topicCount += count($lesson['topics']);

            foreach ($lesson['topics'] as $topic) {

                $paragraphCount +=
                    count($topic['paragraphs']);
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="<?= professorH(htmlLanguage()) ?>">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= professorH(t('p150i_58f1a9a11b51')) ?>
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <style>

        .professor-header {
            margin-bottom: 24px;
            padding: 28px;
            color: #ffffff;
            background: #1e3a8a;
            border-radius: 18px;
        }

        .professor-header h1 {
            margin-top: 0;
            color: #ffffff;
        }

        .content-stat-grid {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(150px, 1fr)
                );
            gap: 14px;
            margin: 24px 0;
        }

        .content-stat {
            padding: 18px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            text-align: center;
        }

        .content-stat strong {
            display: block;
            margin-bottom: 5px;
            color: #1d4ed8;
            font-size: 1.7rem;
        }

        .theme-card {
            margin-bottom: 26px;
            padding: 24px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow:
                0 6px 20px rgba(15, 23, 42, 0.05);
        }

        .theme-card h2 {
            margin-top: 0;
        }

        .permission-badge {
            display: inline-block;
            margin-bottom: 14px;
            padding: 5px 10px;
            color: #166534;
            background: #dcfce7;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .chapter-card {
            margin-top: 20px;
            padding: 20px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }

        .chapter-card h3 {
            margin-top: 0;
            color: #1e3a8a;
        }

        .lesson-card {
            margin-top: 16px;
            padding: 16px;
            background: #ffffff;
            border-left: 4px solid #2563eb;
            border-radius: 8px;
        }

        .lesson-card h4 {
            margin-top: 0;
        }

        .topic-card {
            margin-top: 14px;
            padding: 14px;
            background: #f8fafc;
            border-radius: 8px;
        }

        .topic-card h5 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1rem;
        }

        .paragraph-card {
            margin-top: 12px;
            padding: 14px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }

        .paragraph-card h6 {
            margin: 0 0 8px;
            font-size: 0.95rem;
        }

        .paragraph-content {
            line-height: 1.65;
        }

        .course-media {
            margin-top: 14px;
        }

        .course-media img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
        }

        .course-media audio {
            width: 100%;
        }

        .course-media video {
            width: 100%;
            max-height: 500px;
            border-radius: 8px;
        }

        .empty-content {
            padding: 28px;
            color: #64748b;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            text-align: center;
        }

        .read-only-note {
            margin-bottom: 24px;
            padding: 16px;
            background: #eff6ff;
            border-left: 4px solid #2563eb;
            border-radius: 8px;
        }

    </style>

<script src="../assets/js/professor_language.js" defer></script>
<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>

<body>

<div class="container">


    <!-- ================================================= -->
    <!-- HEADER -->
    <!-- ================================================= -->

    <header class="professor-header">

        <h1>
            <?= professorH(t('course_content')) ?>
        </h1>

        <p>
            <?= professorH(t('p150i_2309f990d265')) ?>
        </p>

    </header>


    <!-- ================================================= -->
    <!-- PROFESSOR NAVIGATION -->
    <!-- ================================================= -->

    <?php require 'professor_nav.php'; ?>


    <main><p class="actions"><a class="btn" href="manage_content.php"><?= professorH(t('manage_course_content')) ?></a> <a class="btn" href="questions.php"><?= professorH(t('manage_questions')) ?></a> <a class="btn" href="trainee_question_status.php"><?= professorH(t('trainee_questionnaire_status')) ?></a></p>


        <!-- ================================================= -->
        <!-- READ ONLY INFORMATION -->
        <!-- ================================================= -->

        <div class="read-only-note"><p><?= professorH(t('p150i_316e64c8bc43')) ?> <a href="manage_content.php"><?= professorH(t('p150i_844717d6e9c8')) ?></a> <?= professorH(t('p150i_6201111b83a0')) ?> <a href="structure.php"><?= professorH(t('p150i_b6b7c4908249')) ?></a> <?= professorH(t('p150i_8a02dbaf6c7f')) ?></p></div>


        <!-- ================================================= -->
        <!-- STATISTICS -->
        <!-- ================================================= -->

        <div class="content-stat-grid">

            <div class="content-stat">
                <strong><?= $themeCount ?></strong>
                <?= professorH(t('themes')) ?>
            </div>

            <div class="content-stat">
                <strong><?= $chapterCount ?></strong>
                <?= professorH(t('chapters')) ?>
            </div>

            <div class="content-stat">
                <strong><?= $lessonCount ?></strong>
                <?= professorH(t('lessons')) ?>
            </div>

            <div class="content-stat">
                <strong><?= $topicCount ?></strong>
                <?= professorH(t('topics')) ?>
            </div>

            <div class="content-stat">
                <strong><?= $paragraphCount ?></strong>
                <?= professorH(t('paragraphs')) ?>
            </div>

        </div>


        <!-- ================================================= -->
        <!-- CONTENT -->
        <!-- ================================================= -->

        <?php if (empty($permittedThemes)): ?>

            <div class="empty-content">

                <h2>
                    <?= professorH(t('p150i_8a7668bab457')) ?>
                </h2>

                <p>
                    <?= professorH(t('p150i_8ebdb9247bdf')) ?>
                </p>

            </div>

        <?php else: ?>


            <?php foreach (
                $permittedThemes as $theme
            ): ?>

                <section class="theme-card">

                    <h2>
                        <?= htmlspecialchars(
                            $theme['name']
                        ) ?>
                    </h2>


                    <?php if ($theme['entire_theme']): ?>

                        <span class="permission-badge">
                            <?= professorH(t('p150i_577fa91e500c')) ?>
                        </span>

                    <?php else: ?>

                        <span class="permission-badge">
                            <?= professorH(t('p150i_e21416382b87')) ?>
                        </span>

                    <?php endif; ?>


                    <?php if (
                        !empty($theme['description'])
                    ): ?>

                        <p>
                            <?= nl2br(
                                htmlspecialchars(
                                    $theme['description']
                                )
                            ) ?>
                        </p>

                    <?php endif; ?>


                    <?php if (
                        empty($theme['chapters'])
                    ): ?>

                        <p class="text-muted">
                            <?= professorH(t('p150i_bb89ab32fe10')) ?>
                        </p>

                    <?php else: ?>


                        <?php foreach (
                            $theme['chapters'] as $chapter
                        ): ?>

                            <div class="chapter-card">

                                <h3>
                                    <?= professorH(t('h150_chapter_label')) ?>
                                    <?= htmlspecialchars(
                                        $chapter['title']
                                    ) ?>
                                </h3>


                                <?php if (
                                    empty($chapter['lessons'])
                                ): ?>

                                    <p class="text-muted">
                                        <?= professorH(t('p150i_93f081c6c522')) ?>
                                    </p>

                                <?php else: ?>


                                    <?php foreach (
                                        $chapter['lessons'] as $lesson
                                    ): ?>

                                        <div class="lesson-card">

                                            <h4>
                                                <?= professorH(t('h150_lesson_label')) ?>
                                                <?= htmlspecialchars(
                                                    $lesson['title']
                                                ) ?>
                                            </h4>


                                            <?php if (
                                                empty($lesson['topics'])
                                            ): ?>

                                                <p class="text-muted">
                                                    <?= professorH(t('p150i_619c12be5bf7')) ?>
                                                </p>

                                            <?php else: ?>


                                                <?php foreach (
                                                    $lesson['topics'] as $topic
                                                ): ?>

                                                    <div class="topic-card">

                                                        <h5>
                                                            <?= professorH(t('h150_topic_label')) ?>
                                                            <?= htmlspecialchars(
                                                                $topic['title']
                                                            ) ?>
                                                        </h5>


                                                        <?php if (
                                                            empty(
                                                                $topic['paragraphs']
                                                            )
                                                        ): ?>

                                                            <p class="text-muted">
                                                                <?= professorH(t('p150i_a16923038a6c')) ?>
                                                            </p>

                                                        <?php else: ?>


                                                            <?php foreach (
                                                                $topic['paragraphs']
                                                                as $paragraph
                                                            ): ?>

                                                                <div class="paragraph-card">


                                                                    <?php if (
                                                                        !empty(
                                                                            $paragraph['title']
                                                                        )
                                                                    ): ?>

                                                                        <h6>
                                                                            <?= htmlspecialchars(
                                                                                $paragraph['title']
                                                                            ) ?>
                                                                        </h6>

                                                                    <?php endif; ?>


                                                                    <?php if (
                                                                        !empty(
                                                                            $paragraph['content']
                                                                        )
                                                                    ): ?>

                                                                        <div class="paragraph-content">
                                                                            <?= qp_rich_html($paragraph['content'] ?? '') ?>
                                                                        </div>

                                                                    <?php endif; ?>


                                                                    <?php
                                                                    $mediaType =
                                                                        $paragraph['media_type']
                                                                        ?? null;

                                                                    $mediaPath =
                                                                        $paragraph['media_path']
                                                                        ?? null;
                                                                    ?>


                                                                    <?php if (
                                                                        $mediaType
                                                                        && $mediaPath
                                                                    ): ?>

                                                                        <div class="course-media">


                                                                            <?php if (
                                                                                $mediaType === 'image'
                                                                            ): ?>

                                                                                <img
                                                                                    src="../<?= htmlspecialchars(
                                                                                        $mediaPath
                                                                                    ) ?>"
                                                                                    alt="<?= professorH(t('p150i_22bd16ad883e')) ?>"
                                                                                >


                                                                            <?php elseif (
                                                                                $mediaType === 'audio'
                                                                            ): ?>

                                                                                <audio controls>
                                                                                    <source
                                                                                        src="../<?= htmlspecialchars(
                                                                                            $mediaPath
                                                                                        ) ?>"
                                                                                    >
                                                                                    <?= professorH(t('p150i_422c62d5cb77')) ?>
                                                                                </audio>


                                                                            <?php elseif (
                                                                                $mediaType === 'video'
                                                                            ): ?>

                                                                                <video controls>
                                                                                    <source
                                                                                        src="../<?= htmlspecialchars(
                                                                                            $mediaPath
                                                                                        ) ?>"
                                                                                    >
                                                                                    <?= professorH(t('p150i_6bcfe6b4a855')) ?>
                                                                                </video>

                                                                            <?php endif; ?>


                                                                        </div>

                                                                    <?php endif; ?>


                                                                </div>

                                                            <?php endforeach; ?>


                                                        <?php endif; ?>


                                                    </div>

                                                <?php endforeach; ?>


                                            <?php endif; ?>


                                        </div>

                                    <?php endforeach; ?>


                                <?php endif; ?>


                            </div>

                        <?php endforeach; ?>


                    <?php endif; ?>


                </section>

            <?php endforeach; ?>


        <?php endif; ?>


    </main>


    <footer>

        <p class="actions">

            <a
                class="btn btn-secondary"
                href="index.php"
            >
                <?= professorH(t('p150i_2c5f7ede2b1b')) ?>
            </a>

        </p>

    </footer>


</div>

</body>
</html>