<?php
require_once __DIR__ . "/../includes/safe_rich_content.php";
require_once __DIR__ . '/professor_language.php';
require_once __DIR__ . '/expansion_bootstrap.php';
px_guard('manage_content.php');
require_once '../config/database.php';
$message = '';
$error = '';
// ======================================================
// PARAGRAPH MEDIA SETTINGS
// ======================================================
$uploadDirectory =
    dirname(__DIR__) . '/uploads/paragraph_media/';
$uploadWebDirectory =
    'uploads/paragraph_media/';
$allowedMedia = [
    'image/jpeg' => [
        'type' => 'image',
        'extension' => 'jpg'
    ],
    'image/png' => [
        'type' => 'image',
        'extension' => 'png'
    ],
    'image/gif' => [
        'type' => 'image',
        'extension' => 'gif'
    ],
    'image/webp' => [
        'type' => 'image',
        'extension' => 'webp'
    ],
    'audio/mpeg' => [
        'type' => 'audio',
        'extension' => 'mp3'
    ],
    'audio/wav' => [
        'type' => 'audio',
        'extension' => 'wav'
    ],
    'audio/ogg' => [
        'type' => 'audio',
        'extension' => 'ogg'
    ],
    'video/mp4' => [
        'type' => 'video',
        'extension' => 'mp4'
    ],
    'video/webm' => [
        'type' => 'video',
        'extension' => 'webm'
    ],
    'video/ogg' => [
        'type' => 'video',
        'extension' => 'ogv'
    ]
];
// ======================================================
// SAFE RICH-TEXT SANITIZER
// ======================================================
function sanitizeParagraphHtml(string $html): string
{
    return qp_rich_html($html);
}
// ======================================================
// NORMALIZE EDITOR CONTENT FOR VALIDATION
// ======================================================
function richTextHasVisibleContent(
    string $html
): bool {
    $text =
        html_entity_decode(
            strip_tags($html),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
    $text =
        str_replace(
            "\xc2\xa0",
            ' ',
            $text
        );
    return trim($text) !== '';
}
// ======================================================
// DELETE A PARAGRAPH MEDIA FILE
// ======================================================
function deleteParagraphMediaFile(
    ?string $mediaPath
): void {
    if (!$mediaPath) {
        return;
    }
    $normalized =
        str_replace(
            '\\',
            '/',
            $mediaPath
        );
    if (
        !str_starts_with(
            $normalized,
            'uploads/paragraph_media/'
        )
    ) {
        return;
    }
    $file =
        dirname(__DIR__) .
        '/' .
        'uploads/paragraph_media/' . basename($normalized);
    if (is_file($file)) {
        @unlink($file);
    }
}
// ======================================================
// HANDLE PARAGRAPH MEDIA UPLOAD
// ======================================================
function uploadParagraphMedia(
    array $file,
    string $uploadDirectory,
    string $uploadWebDirectory,
    array $allowedMedia
): array {
    if (
        !isset($file['error']) ||
        $file['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return [
            'uploaded' => false,
            'media_type' => null,
            'media_path' => null
        ];
    }
    if (
        $file['error'] !== UPLOAD_ERR_OK
    ) {
        throw new RuntimeException(
            t('p150i_22551da49da9')
        );
    }
    if (
        !isset($file['size']) ||
        $file['size'] <= 0
    ) {
        throw new RuntimeException(
            t('p150i_6c63fc13e7b0')
        );
    }
    if (
        $file['size'] >
        50 * 1024 * 1024
    ) {
        throw new RuntimeException(
            t('p150i_c3800f7f7646')
        );
    }
    $finfo =
        new finfo(FILEINFO_MIME_TYPE);
    $mime =
        $finfo->file(
            $file['tmp_name']
        );
    if (
        !$mime ||
        !isset($allowedMedia[$mime])
    ) {
        throw new RuntimeException(
            t('p150i_154ab417314d')
        );
    }
    if (
        !is_dir($uploadDirectory) &&
        !mkdir(
            $uploadDirectory,
            0775,
            true
        ) &&
        !is_dir($uploadDirectory)
    ) {
        throw new RuntimeException(
            t('p150i_e895256f0a0e')
        );
    }
    $definition =
        $allowedMedia[$mime];
    $fileName =
        bin2hex(
            random_bytes(16)
        ) .
        '.' .
        $definition['extension'];
    $destination =
        $uploadDirectory .
        $fileName;
    if (
        !move_uploaded_file(
            $file['tmp_name'],
            $destination
        )
    ) {
        throw new RuntimeException(
            t('p150i_8e38afd1d987')
        );
    }
    return [
        'uploaded' => true,
        'media_type' =>
            $definition['type'],
        'media_path' =>
            $uploadWebDirectory .
            $fileName
    ];
}
// ======================================================
// CREATE CHAPTER
// ======================================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['create_chapter'])
) {
    $themeId = filter_input(
        INPUT_POST,
        'theme_id',
        FILTER_VALIDATE_INT
    );
    $title =
        trim(
            $_POST['chapter_title'] ?? ''
        );
    $displayOrder = filter_input(
        INPUT_POST,
        'display_order',
        FILTER_VALIDATE_INT
    );
    if (!$themeId) {
        $error =
            t('p150i_c67c4c3d81c5');
    } elseif ($title === '') {
        $error =
            t('p150i_166b2ac97fe5');
    } else {
        if ($displayOrder === false) {
            $displayOrder = 0;
        }
        $stmt = $pdo->prepare(
            "INSERT INTO chapters
                (
                    theme_id,
                    title,
                    display_order
                )
             VALUES (?, ?, ?)"
        );
        $stmt->execute([
            $themeId,
            $title,
            $displayOrder
        ]);
        $message =
            t('p150i_4a796a11c5b8');
    }
}
// ======================================================
// CREATE LESSON
// ======================================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['create_lesson'])
) {
    $chapterId = filter_input(
        INPUT_POST,
        'chapter_id',
        FILTER_VALIDATE_INT
    );
    $title =
        trim(
            $_POST['lesson_title'] ?? ''
        );
    $displayOrder = filter_input(
        INPUT_POST,
        'display_order',
        FILTER_VALIDATE_INT
    );
    if (!$chapterId) {
        $error =
            t('p150i_2d44391902d5');
    } elseif ($title === '') {
        $error =
            t('p150i_eb3462d368e6');
    } else {
        if ($displayOrder === false) {
            $displayOrder = 0;
        }
        $stmt = $pdo->prepare(
            "INSERT INTO lessons
                (
                    chapter_id,
                    title,
                    display_order
                )
             VALUES (?, ?, ?)"
        );
        $stmt->execute([
            $chapterId,
            $title,
            $displayOrder
        ]);
        $message =
            t('p150i_03bf2b1c232f');
    }
}
// ======================================================
// CREATE TOPIC
// ======================================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['create_topic'])
) {
    $lessonId = filter_input(
        INPUT_POST,
        'lesson_id',
        FILTER_VALIDATE_INT
    );
    $title =
        trim(
            $_POST['topic_title'] ?? ''
        );
    $displayOrder = filter_input(
        INPUT_POST,
        'display_order',
        FILTER_VALIDATE_INT
    );
    if (!$lessonId) {
        $error =
            t('p150i_1fb072662406');
    } elseif ($title === '') {
        $error =
            t('p150i_3d66ea1ef711');
    } else {
        if ($displayOrder === false) {
            $displayOrder = 0;
        }
        $stmt = $pdo->prepare(
            "INSERT INTO topics
                (
                    lesson_id,
                    title,
                    display_order
                )
             VALUES (?, ?, ?)"
        );
        $stmt->execute([
            $lessonId,
            $title,
            $displayOrder
        ]);
        $message =
            t('p150i_23f4de0b4364');
    }
}
// ======================================================
// CREATE PARAGRAPH
// ======================================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['create_paragraph'])
) {
    $topicId = filter_input(
        INPUT_POST,
        'topic_id',
        FILTER_VALIDATE_INT
    );
    $title =
        trim(
            $_POST['paragraph_title'] ?? ''
        );
    $content =
        sanitizeParagraphHtml(
            $_POST['paragraph_content'] ?? ''
        );
    $displayOrder = filter_input(
        INPUT_POST,
        'display_order',
        FILTER_VALIDATE_INT
    );
    if (!$topicId) {
        $error =
            t('p150i_d50c06f13932');
    } elseif (
        !richTextHasVisibleContent($content)
    ) {
        $error =
            t('p150i_ca92870a480b');
    } else {
        if ($displayOrder === false) {
            $displayOrder = 0;
        }
        try {
            $media = uploadParagraphMedia(
                $_FILES['paragraph_media'] ?? [],
                $uploadDirectory,
                $uploadWebDirectory,
                $allowedMedia
            );
            $stmt = $pdo->prepare(
                "INSERT INTO paragraphs
                    (
                        topic_id,
                        title,
                        content,
                        media_type,
                        media_path,
                        display_order
                    )
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $topicId,
                $title !== ''
                    ? $title
                    : null,
                $content,
                $media['media_type'],
                $media['media_path'],
                $displayOrder
            ]);
            $message =
                t('p150i_2724ddf334f0');
        } catch (Throwable $e) {
            $error = $e instanceof PDOException ? t('p150i_3fa1668cddaa') : $e->getMessage();
        }
    }
}
// ======================================================
// UPDATE PARAGRAPH
// ======================================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_paragraph'])
) {
    $paragraphId = filter_input(
        INPUT_POST,
        'paragraph_id',
        FILTER_VALIDATE_INT
    );
    $topicId = filter_input(
        INPUT_POST,
        'topic_id',
        FILTER_VALIDATE_INT
    );
    $title =
        trim(
            $_POST['paragraph_title'] ?? ''
        );
    $content =
        sanitizeParagraphHtml(
            $_POST['paragraph_content'] ?? ''
        );
    $displayOrder = filter_input(
        INPUT_POST,
        'display_order',
        FILTER_VALIDATE_INT
    );
    $isActive =
        isset($_POST['is_active'])
            ? 1
            : 0;
    $removeMedia =
        isset($_POST['remove_media']);
    if (!$paragraphId) {
        $error =
            t('p150i_205789d9006d');
    } elseif (!$topicId) {
        $error =
            t('p150i_d50c06f13932');
    } elseif (
        !richTextHasVisibleContent($content)
    ) {
        $error =
            t('p150i_ca92870a480b');
    } else {
        if ($displayOrder === false) {
            $displayOrder = 0;
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT
                    id,
                    media_type,
                    media_path
                 FROM paragraphs
                 WHERE id = ?
                 LIMIT 1"
            );
            $stmt->execute([
                $paragraphId
            ]);
            $existing =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );
            if (!$existing) {
                throw new RuntimeException(
                    t('p150i_7f718a51dd6d')
                );
            }
            $mediaType =
                $existing['media_type'];
            $mediaPath =
                $existing['media_path'];
            $newMedia =
                uploadParagraphMedia(
                    $_FILES['paragraph_media'] ?? [],
                    $uploadDirectory,
                    $uploadWebDirectory,
                    $allowedMedia
                );
            if ($newMedia['uploaded']) {
                
                $mediaType =
                    $newMedia['media_type'];
                $mediaPath =
                    $newMedia['media_path'];
            } elseif ($removeMedia) {
                
                $mediaType = null;
                $mediaPath = null;
            }
            $stmt = $pdo->prepare(
                "UPDATE paragraphs
                 SET
                    topic_id = ?,
                    title = ?,
                    content = ?,
                    media_type = ?,
                    media_path = ?,
                    display_order = ?,
                    is_active = ?
                 WHERE id = ?"
            );
            $stmt->execute([
                $topicId,
                $title !== ''
                    ? $title
                    : null,
                $content,
                $mediaType,
                $mediaPath,
                $displayOrder,
                $isActive,
                $paragraphId
            ]);
            if ($existing['media_path'] !== $mediaPath) deleteParagraphMediaFile($existing['media_path']);
            $message =
                t('p150i_ef91ba532358');
        } catch (Throwable $e) {
            $error = $e instanceof PDOException ? t('p150i_3fa1668cddaa') : $e->getMessage();
        }
    }
}
// ======================================================
// GET THEMES
// ======================================================
$stmt = $pdo->query("SELECT
        id,
        name
     FROM themes
     WHERE " . px_scope_sql('themes', 'themes') . " ORDER BY
        display_order ASC,
        name ASC");
$themes =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );
// ======================================================
// GET CHAPTERS
// ======================================================
$stmt = $pdo->query("SELECT
        chapters.id,
        chapters.theme_id,
        chapters.title,
        chapters.display_order,
        chapters.is_active,
        themes.name AS theme_name
     FROM chapters
     INNER JOIN themes
        ON themes.id =
           chapters.theme_id
     WHERE " . px_scope_sql('chapters', 'chapters') . " ORDER BY
        themes.display_order ASC,
        chapters.display_order ASC,
        chapters.id ASC");
$chapters =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );
// ======================================================
// GET LESSONS
// ======================================================
$stmt = $pdo->query("SELECT
        lessons.id,
        lessons.chapter_id,
        lessons.title,
        lessons.display_order,
        lessons.is_active,
        chapters.title AS chapter_title,
        themes.name AS theme_name
     FROM lessons
     INNER JOIN chapters
        ON chapters.id =
           lessons.chapter_id
     INNER JOIN themes
        ON themes.id =
           chapters.theme_id
     WHERE " . px_scope_sql('lessons', 'lessons') . " ORDER BY
        themes.display_order ASC,
        chapters.display_order ASC,
        lessons.display_order ASC,
        lessons.id ASC");
$lessons =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );
// ======================================================
// GET TOPICS
// ======================================================
$stmt = $pdo->query("SELECT
        topics.id,
        topics.lesson_id,
        topics.title,
        topics.display_order,
        topics.is_active,
        lessons.title AS lesson_title,
        chapters.title AS chapter_title,
        themes.name AS theme_name
     FROM topics
     INNER JOIN lessons
        ON lessons.id =
           topics.lesson_id
     INNER JOIN chapters
        ON chapters.id =
           lessons.chapter_id
     INNER JOIN themes
        ON themes.id =
           chapters.theme_id
     WHERE " . px_scope_sql('topics', 'topics') . " ORDER BY
        themes.display_order ASC,
        chapters.display_order ASC,
        lessons.display_order ASC,
        topics.display_order ASC,
        topics.id ASC");
$topics =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );
// ======================================================
// GET PARAGRAPHS
// ======================================================
$stmt = $pdo->query("SELECT
        paragraphs.id,
        paragraphs.topic_id,
        paragraphs.title,
        paragraphs.content,
        paragraphs.media_type,
        paragraphs.media_path,
        paragraphs.display_order,
        paragraphs.is_active,
        topics.title AS topic_title,
        lessons.title AS lesson_title,
        chapters.title AS chapter_title,
        themes.name AS theme_name
     FROM paragraphs
     INNER JOIN topics
        ON topics.id =
           paragraphs.topic_id
     INNER JOIN lessons
        ON lessons.id =
           topics.lesson_id
     INNER JOIN chapters
        ON chapters.id =
           lessons.chapter_id
     INNER JOIN themes
        ON themes.id =
           chapters.theme_id
     WHERE " . px_scope_sql('paragraphs', 'paragraphs') . " ORDER BY
        themes.display_order ASC,
        chapters.display_order ASC,
        lessons.display_order ASC,
        topics.display_order ASC,
        paragraphs.display_order ASC,
        paragraphs.id ASC");
$paragraphs =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );
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
        <?= professorH(t('p150i_64ef8cbdd085')) ?>
    </title>
    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >
    <style>
        .content-admin .card > h2:first-child { margin-top: 0; }
        .content-admin form p { margin: 0 0 16px; }
        .content-admin label + br { display: none; }
        .content-admin form input, .content-admin form select { max-width: 100%; }
        .structure-intro { border-left: 4px solid #2563eb; }
        .structure-intro p:last-child { margin-bottom: 0; }
        .hierarchy-chapter { border-top: 4px solid #2563eb; }
        .hierarchy-lesson, .hierarchy-topic {
            min-width: 0;
            margin: 20px 0 0;
            padding: 4px 0 4px 20px;
            border-left: 3px solid #cbd5e1;
        }
        .hierarchy-topic { border-left-color: #bfdbfe; }
        .hierarchy-lesson > h3, .hierarchy-topic > h4 { margin: 8px 0 16px; }
        .hierarchy-topic > h4 { font-size: 17px; color: #374151; }
        .paragraph-card {
            min-width: 0;
            margin: 16px 0;
            padding: 20px;
            border: 1px solid #dbe3ee;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .04);
            overflow-wrap: anywhere;
        }
        .paragraph-card > h5 { margin: 0 0 12px; font-size: 16px; color: #111827; }
        .paragraph-meta { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .paragraph-media {
            margin: 18px 0;
            padding: 14px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #f8fafc;
        }
        .paragraph-media p { margin-top: 0; }
        .paragraph-media img, .paragraph-media video, .paragraph-media audio {
            display: block;
            width: 100%;
            max-width: 700px;
        }
        .paragraph-media img, .paragraph-media video { height: auto; border-radius: 6px; }
        .rich-editor {
            min-height: 220px;
            padding: 16px;
            border: 1px solid #d1d5db;
            border-radius: 0 0 8px 8px;
            background: #fff;
            color: #111827;
            overflow-wrap: anywhere;
        }
        .rich-editor:focus { outline: 2px solid #2563eb; outline-offset: 2px; }
        .editor-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            padding: 12px;
            margin-bottom: 0;
            border: 1px solid #d1d5db;
            border-bottom: 0;
            border-radius: 8px 8px 0 0;
            background: #f3f4f6;
        }
        .editor-toolbar button, .editor-toolbar select, .editor-toolbar input {
            width: auto;
            max-width: 100%;
            min-height: 40px;
            margin: 0;
        }
        .editor-toolbar button { padding: 8px 12px; }
        .editor-toolbar select { flex: 0 1 180px; min-width: 0; padding: 8px; }
        .editor-toolbar label { display: inline-flex; align-items: center; gap: 6px; margin: 0; }
        .editor-toolbar input[type="color"] { width: 42px; padding: 3px; cursor: pointer; }
        .editor-toolbar button:focus-visible, .edit-paragraph-box summary:focus-visible {
            outline: 2px solid #2563eb;
            outline-offset: 3px;
        }
        .edit-paragraph-box { margin-top: 18px; border-top: 1px solid #e5e7eb; padding-top: 14px; }
        .edit-paragraph-box summary { cursor: pointer; font-weight: 600; color: #2563eb; padding: 8px 0; }
        .edit-paragraph-box[open] summary { margin-bottom: 16px; }
        .content-admin .workflow-navigation.actions { display: flex; flex-wrap: wrap; gap: 10px; }
        .content-admin .workflow-navigation a { margin: 0; }
        @media (max-width: 700px) {
            .content-admin .card { padding: 16px; }
            .hierarchy-lesson, .hierarchy-topic { padding-left: 10px; margin-top: 16px; }
            .content-admin .paragraph-card { padding: 12px; }
            .paragraph-media { padding: 8px; }
            .rich-editor { padding: 12px; }
            .editor-toolbar { gap: 6px; padding: 8px; }
            .editor-toolbar button { width: auto; flex: 1 1 auto; margin: 0; min-height: 44px; }
            .editor-toolbar select { flex: 1 1 130px; min-height: 44px; }
            .editor-toolbar label { flex: 1 1 110px; }
        }
    </style>
<script src="../assets/js/professor_language.js" defer></script>
<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>
<body>
<div class="container content-admin">
    <header class="admin-header">
        <h1><?= professorH(t('p150i_64ef8cbdd085')) ?></h1>
        <p><?= professorH(t('p150i_6feb88c91790')) ?></p>
    </header>
    <?php require_once 'professor_nav.php'; ?>
    <div class="card structure-intro">
        <h2><?= professorH(t('p150i_b4fdfa19c145')) ?></h2>
        <p><strong><?= professorH(t('p150i_e1a8f0432182')) ?></strong> <?= professorH(t('p150i_3134883cc9b4')) ?></p>
        <p class="text-muted"><?= professorH(t('p150i_24199603fcc8')) ?></p>
    </div>
    <?php if ($message !== ''): ?>
        <p class="alert alert-success">
            <strong>
                <?= htmlspecialchars($message) ?>
            </strong>
        </p>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <p class="alert alert-error">
            <strong>
                <?= htmlspecialchars($error) ?>
            </strong>
        </p>
    <?php endif; ?>
    <!-- ================================================= -->
    <!-- CREATE CHAPTER -->
    <!-- ================================================= -->
    <p class="alert"> <?= px_h(t('ux_creation_help')) ?> <a class="btn btn-secondary" href="themes.php"><?= px_h(t('ux_create_theme')) ?></a></p>
    <section class="card create-section">
    <h2><?= professorH(t('p150i_2bf904b63e04')) ?></h2>
    <form method="POST"><input type="hidden" name="create_chapter" value="1"><?php px_csrf_field(); ?>
        <p>
            <label for="chapter_theme_id">
                <?= professorH(t('theme')) ?>
            </label>
            <br>
            <select
                id="chapter_theme_id"
                name="theme_id"
                required
            >
                <option value="">
                    <?= professorH(t('p150i_3980d1266628')) ?>
                </option>
                <?php foreach (
                    $themes as $theme
                ): ?><?php if (!px_allowed((int)$theme['id'], null)) continue; ?>
                    <option
                        value="<?= (int)$theme['id'] ?>"
                    >
                        <?= htmlspecialchars(
                            $theme['name']
                        ) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="chapter_title">
                <?= professorH(t('p150i_bb03eeba83d1')) ?>
            </label>
            <br>
            <input
                type="text"
                id="chapter_title"
                name="chapter_title"
                required
            >
        </p>
        <p>
            <label for="chapter_order">
                <?= professorH(t('display_order')) ?>
            </label>
            <br>
            <input
                type="number"
                id="chapter_order"
                name="display_order"
                value="0"
                min="0"
            >
        </p>
        <button name="create_chapter" class="btn"
            type="submit"
        >
            <?= professorH(t('p150i_fa35d576a6ec')) ?>
        </button>
    </form>
    </section>
    <!-- ================================================= -->
    <!-- CREATE LESSON -->
    <!-- ================================================= -->
    <section class="card create-section">
    <h2><?= professorH(t('p150i_33891c51fcf5')) ?></h2>
    <?php if (empty($chapters)): ?>
        <p class="text-muted">
            <?= professorH(t('p150i_d7cf27700381')) ?>
        </p>
    <?php else: ?>
        <form method="POST"><input type="hidden" name="create_lesson" value="1"><?php px_csrf_field(); ?>
            <p>
                <label for="lesson_chapter_id">
                    <?= professorH(t('chapter')) ?>
                </label>
                <br>
                <select
                    id="lesson_chapter_id"
                    name="chapter_id"
                    required
                >
                    <option value="">
                        <?= professorH(t('p150i_b7c58bfd0cad')) ?>
                    </option>
                    <?php foreach (
                        $chapters as $chapter
                    ): ?>
                        <option
                            value="<?= (int)$chapter['id'] ?>"
                        >
                            <?= htmlspecialchars(
                                $chapter['theme_name']
                            ) ?>
                            →
                            <?= htmlspecialchars(
                                $chapter['title']
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label for="lesson_title">
                    <?= professorH(t('p150i_fa1c2a055c34')) ?>
                </label>
                <br>
                <input
                    type="text"
                    id="lesson_title"
                    name="lesson_title"
                    required
                >
            </p>
            <p>
                <label for="lesson_order">
                    <?= professorH(t('display_order')) ?>
                </label>
                <br>
                <input
                    type="number"
                    id="lesson_order"
                    name="display_order"
                    value="0"
                    min="0"
                >
            </p>
            <button name="create_lesson" class="btn"
                type="submit"
            >
                <?= professorH(t('p150i_7a80a67f5de3')) ?>
            </button>
        </form>
    <?php endif; ?>
    </section>
    <!-- ================================================= -->
    <!-- CREATE TOPIC -->
    <!-- ================================================= -->
    <section class="card create-section">
    <h2><?= professorH(t('p150i_69c6c4983620')) ?></h2>
    <?php if (empty($lessons)): ?>
        <p class="text-muted">
            <?= professorH(t('p150i_7cb515421214')) ?>
        </p>
    <?php else: ?>
        <form method="POST"><input type="hidden" name="create_topic" value="1"><?php px_csrf_field(); ?>
            <p>
                <label for="topic_lesson_id">
                    <?= professorH(t('lesson')) ?>
                </label>
                <br>
                <select
                    id="topic_lesson_id"
                    name="lesson_id"
                    required
                >
                    <option value="">
                        <?= professorH(t('p150i_06e978f59e8c')) ?>
                    </option>
                    <?php foreach (
                        $lessons as $lesson
                    ): ?>
                        <option
                            value="<?= (int)$lesson['id'] ?>"
                        >
                            <?= htmlspecialchars(
                                $lesson['theme_name']
                            ) ?>
                            →
                            <?= htmlspecialchars(
                                $lesson['chapter_title']
                            ) ?>
                            →
                            <?= htmlspecialchars(
                                $lesson['title']
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label for="topic_title">
                    <?= professorH(t('p150i_3dd88fbde99b')) ?>
                </label>
                <br>
                <input
                    type="text"
                    id="topic_title"
                    name="topic_title"
                    required
                >
            </p>
            <p>
                <label for="topic_order">
                    <?= professorH(t('display_order')) ?>
                </label>
                <br>
                <input
                    type="number"
                    id="topic_order"
                    name="display_order"
                    value="0"
                    min="0"
                >
            </p>
            <button name="create_topic" class="btn"
                type="submit"
            >
                <?= professorH(t('p150i_15c75a495c91')) ?>
            </button>
        </form>
    <?php endif; ?>
    </section>
    <!-- ================================================= -->
    <!-- CREATE PARAGRAPH -->
    <!-- ================================================= -->
    <section class="card create-section">
    <h2><?= professorH(t('p150i_f6caacfd1255')) ?></h2>
    <?php if (empty($topics)): ?>
        <p class="text-muted">
            <?= professorH(t('p150i_3ba4af36db9b')) ?>
        </p>
    <?php else: ?>
        <form
            method="POST"
            enctype="multipart/form-data"
            class="rich-text-form"
        ><input type="hidden" name="create_paragraph" value="1"><?php px_csrf_field(); ?>
            <p>
                <label for="paragraph_topic_id">
                    <?= professorH(t('topic')) ?>
                </label>
                <br>
                <select
                    id="paragraph_topic_id"
                    name="topic_id"
                    required
                >
                    <option value="">
                        <?= professorH(t('p150i_a25fa62853d4')) ?>
                    </option>
                    <?php foreach (
                        $topics as $topic
                    ): ?>
                        <option
                            value="<?= (int)$topic['id'] ?>"
                        >
                            <?= htmlspecialchars(
                                $topic['theme_name']
                            ) ?>
                            →
                            <?= htmlspecialchars(
                                $topic['chapter_title']
                            ) ?>
                            →
                            <?= htmlspecialchars(
                                $topic['lesson_title']
                            ) ?>
                            →
                            <?= htmlspecialchars(
                                $topic['title']
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label for="paragraph_title">
                    <?= professorH(t('p150i_8f1c0091198c')) ?>
                </label>
                <br>
                <input
                    type="text"
                    id="paragraph_title"
                    name="paragraph_title"
                >
            </p>
            <p>
                <strong>
                    <?= professorH(t('p150i_b43d0f9e96a8')) ?>
                </strong>
            </p>
            <div
                class="editor-toolbar"
                data-editor-toolbar
            >
                <select
                    data-command="formatBlock"
                    aria-label="<?= professorH(t('p150i_b34f17f02ecf')) ?>"
                >
                    <option value="p">
                        <?= professorH(t('paragraph')) ?>
                    </option>
                    <option value="h2">
                        <?= professorH(t('p150i_3291955e9005')) ?>
                    </option>
                    <option value="h3">
                        <?= professorH(t('p150i_c6845f11c2d3')) ?>
                    </option>
                    <option value="h4">
                        <?= professorH(t('p150i_df78f14225f4')) ?>
                    </option>
                </select>
                <select
                    data-font-family
                    aria-label="<?= professorH(t('p150i_119ef3fa607e')) ?>"
                >
                    <option value="">
                        <?= professorH(t('p150i_64d0b3adcd2d')) ?>
                    </option>
                    <option value="Arial">
                        Arial
                    </option>
                    <option value="Verdana">
                        Verdana
                    </option>
                    <option value="Georgia">
                        Georgia
                    </option>
                    <option value="Tahoma">
                        Tahoma
                    </option>
                    <option value="Trebuchet MS">
                        Trebuchet MS
                    </option>
                    <option value="Times New Roman">
                        Times New Roman
                    </option>
                    <option value="Courier New">
                        Courier New
                    </option>
                </select>
                <select
                    data-font-size
                    aria-label="<?= professorH(t('p150i_6d784d95c638')) ?>"
                >
                    <option value="">
                        <?= professorH(t('p150i_1af851907331')) ?>
                    </option>
                    <option value="12px">
                        12
                    </option>
                    <option value="14px">
                        14
                    </option>
                    <option value="16px">
                        16
                    </option>
                    <option value="18px">
                        18
                    </option>
                    <option value="20px">
                        20
                    </option>
                    <option value="24px">
                        24
                    </option>
                    <option value="28px">
                        28
                    </option>
                    <option value="32px">
                        32
                    </option>
                </select>
                <button
                    type="button"
                    data-command="bold"
                >
                    <?= professorH(t('p150i_94fee62e68e2')) ?>
                </button>
                <button
                    type="button"
                    data-command="italic"
                >
                    <?= professorH(t('p150i_9bf37cb58a69')) ?>
                </button>
                <button
                    type="button"
                    data-command="underline"
                >
                    <?= professorH(t('p150i_02f843261112')) ?>
                </button>
                <button
                    type="button"
                    data-command="strikeThrough"
                >
                    <?= professorH(t('p150i_92c872eb9eff')) ?>
                </button>
                <button
                    type="button"
                    data-command="justifyLeft"
                >
                    <?= professorH(t('p150i_58eb9032e3bb')) ?>
                </button>
                <button
                    type="button"
                    data-command="justifyCenter"
                >
                    <?= professorH(t('p150i_d946067427e9')) ?>
                </button>
                <button
                    type="button"
                    data-command="justifyRight"
                >
                    <?= professorH(t('p150i_883361d5d682')) ?>
                </button>
                <button
                    type="button"
                    data-command="insertUnorderedList"
                >
                    <?= professorH(t('p150i_6165d09e6279')) ?>
                </button>
                <button
                    type="button"
                    data-command="insertOrderedList"
                >
                    <?= professorH(t('p150i_2916c317bf4a')) ?>
                </button>
                <label>
                    <?= professorH(t('p150i_71988c4d8e08')) ?>
                    <input
                        type="color"
                        data-fore-color
                        value="#000000"
                    >
                </label>
                <label>
                    <?= professorH(t('p150i_07ccd15df32a')) ?>
                    <input
                        type="color"
                        data-back-color
                        value="#ffffff"
                    >
                </label>
                <button
                    type="button"
                    data-create-link
                >
                    <?= professorH(t('p150i_b8c42654c8ce')) ?>
                </button>
                <button
                    type="button"
                    data-command="unlink"
                >
                    <?= professorH(t('p150i_e3e0591b816f')) ?>
                </button>
                <button
                    type="button"
                    data-command="removeFormat"
                >
                    <?= professorH(t('p150i_804cf3352c42')) ?>
                </button>
            </div>
            <div
                class="rich-editor"
                contenteditable="true"
                data-rich-editor
            ></div>
            <textarea
                name="paragraph_content"
                data-rich-input
                hidden
            ></textarea>
            <p>
                <label for="paragraph_media">
                    <?= professorH(t('p150i_31fec33bd2a1')) ?>
                </label>
                <br>
                <input
                    type="file"
                    id="paragraph_media"
                    name="paragraph_media"
                    accept="image/jpeg,image/png,image/gif,image/webp,audio/mpeg,audio/wav,audio/ogg,video/mp4,video/webm,video/ogg"
                >
            </p>
            <p>
                <?= professorH(t('p150i_d888de2da4f3')) ?>
            </p>
            <p>
                <label for="paragraph_order">
                    <?= professorH(t('display_order')) ?>
                </label>
                <br>
                <input
                    type="number"
                    id="paragraph_order"
                    name="display_order"
                    value="0"
                    min="0"
                >
            </p>
            <button name="create_paragraph" class="btn"
                type="submit"
            >
                <?= professorH(t('p150i_4a7679276a34')) ?>
            </button>
        </form>
    <?php endif; ?>
    </section>
    <!-- ================================================= -->
    <!-- EXISTING STRUCTURE -->
    <!-- ================================================= -->
    <h2>
        <?= professorH(t('p150i_9fd1ac278888')) ?>
    </h2>
    <?php if (empty($chapters)): ?>
        <p class="text-muted">
            <?= professorH(t('p150i_292eb615a38d')) ?>
        </p>
    <?php else: ?>
        <?php foreach (
            $chapters as $chapter
        ): ?>
            <section class="card hierarchy-chapter">
                <h2>
                    <?= professorH(t('h150_chapter_label')) ?>
                    <?= htmlspecialchars(
                        $chapter['title']
                    ) ?>
                </h2>
                <p>
                    <strong>
                        <?= professorH(t('h150_theme_label')) ?>
                    </strong>
                    <?= htmlspecialchars(
                        $chapter['theme_name']
                    ) ?>
                </p>
                <?php
                $chapterHasLessons = false;
                foreach (
                    $lessons as $lesson
                ):
                    if (
                        (int)$lesson['chapter_id']
                        !==
                        (int)$chapter['id']
                    ) {
                        continue;
                    }
                    $chapterHasLessons = true;
                ?>
                    <div
                        class="hierarchy-lesson"
                    >
                        <h3>
                            <?= professorH(t('h150_lesson_label')) ?>
                            <?= htmlspecialchars(
                                $lesson['title']
                            ) ?>
                        </h3>
                        <?php
                        $lessonHasTopics = false;
                        foreach (
                            $topics as $topic
                        ):
                            if (
                                (int)$topic['lesson_id']
                                !==
                                (int)$lesson['id']
                            ) {
                                continue;
                            }
                            $lessonHasTopics = true;
                        ?>
                            <div
                                class="hierarchy-topic"
                            >
                                <h4>
                                    <?= professorH(t('h150_topic_label')) ?>
                                    <?= htmlspecialchars(
                                        $topic['title']
                                    ) ?>
                                </h4>
                                <?php
                                $topicHasParagraphs =
                                    false;
                                foreach (
                                    $paragraphs
                                    as $paragraph
                                ):
                                    if (
                                        (int)$paragraph[
                                            'topic_id'
                                        ]
                                        !==
                                        (int)$topic['id']
                                    ) {
                                        continue;
                                    }
                                    $topicHasParagraphs =
                                        true;
                                ?>
                                    <div
                                        class="card paragraph-card"
                                    >
                                        <?php if (
                                            !empty(
                                                $paragraph[
                                                    'title'
                                                ]
                                            )
                                        ): ?>
                                            <h5>
                                                <?= htmlspecialchars(
                                                    $paragraph[
                                                        'title'
                                                    ]
                                                ) ?>
                                            </h5>
                                        <?php endif; ?>
                                        <div>
                                            <?= qp_rich_html($paragraph['content'] ?? '') ?>
                                        </div>
                                        <?php if (
                                            !empty(
                                                $paragraph[
                                                    'media_path'
                                                ]
                                            )
                                        ): ?>
                                            <div
                                                class="paragraph-media"
                                            >
                                                <p>
                                                    <strong>
                                                        <?= professorH(t('p150i_f69bf9665462')) ?>
                                                    </strong>
                                                </p>
                                                <?php
                                                $mediaUrl =
                                                    '../' .
                                                    ltrim(
                                                        $paragraph[
                                                            'media_path'
                                                        ],
                                                        '/'
                                                    );
                                                ?>
                                                <?php if (
                                                    $paragraph[
                                                        'media_type'
                                                    ] === 'image'
                                                ): ?>
                                                    <img
                                                        src="<?= htmlspecialchars($mediaUrl) ?>"
                                                        alt="<?= htmlspecialchars($paragraph['title'] ?: t('p150i_30b625bf8377')) ?>"
                                                    >
                                                <?php elseif (
                                                    $paragraph[
                                                        'media_type'
                                                    ] === 'audio'
                                                ): ?>
                                                    <audio
                                                        controls
                                                        preload="metadata"
                                                    >
                                                        <source
                                                            src="<?= htmlspecialchars($mediaUrl) ?>"
                                                        >
                                                    </audio>
                                                <?php elseif (
                                                    $paragraph[
                                                        'media_type'
                                                    ] === 'video'
                                                ): ?>
                                                    <video
                                                        controls
                                                        preload="metadata"
                                                    >
                                                        <source
                                                            src="<?= htmlspecialchars($mediaUrl) ?>"
                                                        >
                                                    </video>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <p class="paragraph-meta text-muted">
                                            <strong>
                                                <?= professorH(t('p150i_dd9b208f7728')) ?>
                                            </strong>
                                            <?= (int)$paragraph[
                                                'display_order'
                                            ] ?>
                                            &nbsp; | &nbsp;
                                            <strong>
                                                <?= professorH(t('p150i_755c8b2a9fb1')) ?>
                                            </strong>
                                            <span class="status status-<?= (int)$paragraph['is_active'] === 1 ? 'active' : 'inactive' ?>"><?= (int)$paragraph[
                                                'is_active'
                                            ] === 1
                                                ? t('active')
                                                : t('inactive') ?></span>
                                        </p>
                                        <details
                                            class="edit-paragraph-box"
                                        >
                                            <summary>
                                                <?= professorH(t('p150i_00ac74dd2b3e')) ?>
                                            </summary>
                                            <form
                                                method="POST"
                                                enctype="multipart/form-data"
                                                class="rich-text-form"
                                            ><input type="hidden" name="update_paragraph" value="1"><?php px_csrf_field(); ?>
                                                <input
                                                    type="hidden"
                                                    name="paragraph_id"
                                                    value="<?= (int)$paragraph['id'] ?>"
                                                >
                                                <p>
                                                    <label>
                                                        <?= professorH(t('topic')) ?>
                                                    </label>
                                                    <br>
                                                    <select
                                                        name="topic_id"
                                                        required
                                                    >
                                                        <?php foreach (
                                                            $topics
                                                            as $editTopic
                                                        ): ?>
                                                            <option
                                                                value="<?= (int)$editTopic['id'] ?>"
                                                                <?= (int)$editTopic['id'] === (int)$paragraph['topic_id'] ? 'selected' : '' ?>
                                                            >
                                                                <?= htmlspecialchars(
                                                                    $editTopic[
                                                                        'theme_name'
                                                                    ]
                                                                ) ?>
                                                                →
                                                                <?= htmlspecialchars(
                                                                    $editTopic[
                                                                        'chapter_title'
                                                                    ]
                                                                ) ?>
                                                                →
                                                                <?= htmlspecialchars(
                                                                    $editTopic[
                                                                        'lesson_title'
                                                                    ]
                                                                ) ?>
                                                                →
                                                                <?= htmlspecialchars(
                                                                    $editTopic[
                                                                        'title'
                                                                    ]
                                                                ) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </p>
                                                <p>
                                                    <label>
                                                        <?= professorH(t('p150i_3aedc5a12e30')) ?>
                                                    </label>
                                                    <br>
                                                    <input
                                                        type="text"
                                                        name="paragraph_title"
                                                        value="<?= htmlspecialchars($paragraph['title'] ?? '') ?>"
                                                    >
                                                </p>
                                                <p>
                                                    <strong>
                                                        <?= professorH(t('p150i_b43d0f9e96a8')) ?>
                                                    </strong>
                                                </p>
                                                <div
                                                    class="editor-toolbar"
                                                    data-editor-toolbar
                                                >
                                                    <select
                                                        data-command="formatBlock"
                                                    >
                                                        <option value="p">
                                                            <?= professorH(t('paragraph')) ?>
                                                        </option>
                                                        <option value="h2">
                                                            <?= professorH(t('p150i_3291955e9005')) ?>
                                                        </option>
                                                        <option value="h3">
                                                            <?= professorH(t('p150i_c6845f11c2d3')) ?>
                                                        </option>
                                                        <option value="h4">
                                                            <?= professorH(t('p150i_df78f14225f4')) ?>
                                                        </option>
                                                    </select>
                                                    <select
                                                        data-font-family
                                                    >
                                                        <option value="">
                                                            <?= professorH(t('p150i_64d0b3adcd2d')) ?>
                                                        </option>
                                                        <option value="Arial">
                                                            Arial
                                                        </option>
                                                        <option value="Verdana">
                                                            Verdana
                                                        </option>
                                                        <option value="Georgia">
                                                            Georgia
                                                        </option>
                                                        <option value="Tahoma">
                                                            Tahoma
                                                        </option>
                                                        <option value="Trebuchet MS">
                                                            Trebuchet MS
                                                        </option>
                                                        <option value="Times New Roman">
                                                            Times New Roman
                                                        </option>
                                                        <option value="Courier New">
                                                            Courier New
                                                        </option>
                                                    </select>
                                                    <select
                                                        data-font-size
                                                    >
                                                        <option value="">
                                                            <?= professorH(t('p150i_1af851907331')) ?>
                                                        </option>
                                                        <option value="12px">
                                                            12
                                                        </option>
                                                        <option value="14px">
                                                            14
                                                        </option>
                                                        <option value="16px">
                                                            16
                                                        </option>
                                                        <option value="18px">
                                                            18
                                                        </option>
                                                        <option value="20px">
                                                            20
                                                        </option>
                                                        <option value="24px">
                                                            24
                                                        </option>
                                                        <option value="28px">
                                                            28
                                                        </option>
                                                        <option value="32px">
                                                            32
                                                        </option>
                                                    </select>
                                                    <button
                                                        type="button"
                                                        data-command="bold"
                                                    >
                                                        <?= professorH(t('p150i_94fee62e68e2')) ?>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        data-command="italic"
                                                    >
                                                        <?= professorH(t('p150i_9bf37cb58a69')) ?>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        data-command="underline"
                                                    >
                                                        <?= professorH(t('p150i_02f843261112')) ?>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        data-command="strikeThrough"
                                                    >
                                                        <?= professorH(t('p150i_92c872eb9eff')) ?>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        data-command="justifyLeft"
                                                    >
                                                        <?= professorH(t('p150i_58eb9032e3bb')) ?>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        data-command="justifyCenter"
                                                    >
                                                        <?= professorH(t('p150i_d946067427e9')) ?>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        data-command="justifyRight"
                                                    >
                                                        <?= professorH(t('p150i_883361d5d682')) ?>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        data-command="insertUnorderedList"
                                                    >
                                                        <?= professorH(t('p150i_6165d09e6279')) ?>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        data-command="insertOrderedList"
                                                    >
                                                        <?= professorH(t('p150i_2916c317bf4a')) ?>
                                                    </button>
                                                    <label>
                                                        <?= professorH(t('p150i_71988c4d8e08')) ?>
                                                        <input
                                                            type="color"
                                                            data-fore-color
                                                            value="#000000"
                                                        >
                                                    </label>
                                                    <label>
                                                        <?= professorH(t('p150i_07ccd15df32a')) ?>
                                                        <input
                                                            type="color"
                                                            data-back-color
                                                            value="#ffffff"
                                                        >
                                                    </label>
                                                    <button
                                                        type="button"
                                                        data-create-link
                                                    >
                                                        <?= professorH(t('p150i_b8c42654c8ce')) ?>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        data-command="unlink"
                                                    >
                                                        <?= professorH(t('p150i_e3e0591b816f')) ?>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        data-command="removeFormat"
                                                    >
                                                        <?= professorH(t('p150i_804cf3352c42')) ?>
                                                    </button>
                                                </div>
                                                <div
                                                    class="rich-editor"
                                                    contenteditable="true"
                                                    data-rich-editor
                                                ><?= qp_rich_html($paragraph['content'] ?? '') ?></div>
                                                <textarea
                                                    name="paragraph_content"
                                                    data-rich-input
                                                    hidden
                                                ></textarea>
                                                <p>
                                                    <label>
                                                        <?= professorH(t('p150i_93964ead5762')) ?>
                                                    </label>
                                                    <br>
                                                    <input
                                                        type="file"
                                                        name="paragraph_media"
                                                        accept="image/jpeg,image/png,image/gif,image/webp,audio/mpeg,audio/wav,audio/ogg,video/mp4,video/webm,video/ogg"
                                                    >
                                                </p>
                                                <?php if (
                                                    !empty(
                                                        $paragraph[
                                                            'media_path'
                                                        ]
                                                    )
                                                ): ?>
                                                    <p>
                                                        <label>
                                                            <input
                                                                type="checkbox"
                                                                name="remove_media"
                                                                value="1"
                                                            >
                                                            <?= professorH(t('p150i_f39ff7b2cc2f')) ?>
                                                        </label>
                                                    </p>
                                                <?php endif; ?>
                                                <p>
                                                    <label>
                                                        <?= professorH(t('display_order')) ?>
                                                    </label>
                                                    <br>
                                                    <input
                                                        type="number"
                                                        name="display_order"
                                                        value="<?= (int)$paragraph['display_order'] ?>"
                                                        min="0"
                                                    >
                                                </p>
                                                <p>
                                                    <label>
                                                        <input
                                                            type="checkbox"
                                                            name="is_active"
                                                            value="1"
                                                            <?= (int)$paragraph['is_active'] === 1 ? 'checked' : '' ?>
                                                        >
                                                        <?= professorH(t('active')) ?>
                                                    </label>
                                                </p>
                                                <button name="update_paragraph" class="btn"
                                                    type="submit"
                                                >
                                                    <?= professorH(t('p150i_c191e80bb5f7')) ?>
                                                </button>
                                            </form>
                                        </details>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (
                                    !$topicHasParagraphs
                                ): ?>
                                    <p class="text-muted">
                                        <?= professorH(t('p150i_0960f26b899a')) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (
                            !$lessonHasTopics
                        ): ?>
                            <p class="text-muted">
                                <?= professorH(t('p150i_007e40d61d6d')) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (
                    !$chapterHasLessons
                ): ?>
                    <p class="text-muted">
                        <?= professorH(t('p150i_68491866e79d')) ?>
                    </p>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
    <nav class="workflow-navigation actions" aria-label="<?= professorH(t('p150i_62be74ef43cd')) ?>">
        <a class="btn btn-secondary" href="structure.php"><?= professorH(t('p150i_b6b7c4908249')) ?></a>
        <a class="btn" href="questions.php"><?= professorH(t('p150i_fa08f64ecdf6')) ?></a>
        <a class="btn btn-secondary" href="index.php"><?= professorH(t('dashboard')) ?></a>
        <a class="btn btn-secondary" href="../index.php"><?= professorH(t('p150i_ad2638a9d1dd')) ?></a>
        <a class="btn btn-secondary" href="logout.php"><?= professorH(t('logout')) ?></a>
    </nav>
</div>
<script>
(function () {
    function getEditorFromToolbar(toolbar) {
        const form =
            toolbar.closest(
                '.rich-text-form'
            );
        if (!form) {
            return null;
        }
        return form.querySelector(
            '[data-rich-editor]'
        );
    }
    function focusEditor(editor) {
        if (editor) {
            editor.focus();
        }
    }
    function executeCommand(
        editor,
        command,
        value = null
    ) {
        focusEditor(editor);
        document.execCommand(
            command,
            false,
            value
        );
    }
    function applyStyleToSelection(
        editor,
        property,
        value
    ) {
        focusEditor(editor);
        const selection =
            window.getSelection();
        if (
            !selection ||
            selection.rangeCount === 0
        ) {
            return;
        }
        const range =
            selection.getRangeAt(0);
        if (
            !editor.contains(
                range.commonAncestorContainer
            )
        ) {
            return;
        }
        if (range.collapsed) {
            const span =
                document.createElement(
                    'span'
                );
            span.style[property] =
                value;
            span.appendChild(
                document.createTextNode(
                    '\u200B'
                )
            );
            range.insertNode(span);
            range.setStart(
                span.firstChild,
                1
            );
            range.collapse(true);
            selection.removeAllRanges();
            selection.addRange(range);
            return;
        }
        const span =
            document.createElement(
                'span'
            );
        span.style[property] =
            value;
        try {
            range.surroundContents(
                span
            );
        } catch (error) {
            const fragment =
                range.extractContents();
            span.appendChild(
                fragment
            );
            range.insertNode(
                span
            );
        }
        selection.removeAllRanges();
        const newRange =
            document.createRange();
        newRange.selectNodeContents(
            span
        );
        selection.addRange(
            newRange
        );
    }
    document.querySelectorAll(
        '[data-editor-toolbar]'
    ).forEach(function (toolbar) {
        toolbar.querySelectorAll(
            '[data-command]'
        ).forEach(function (control) {
            const eventName =
                control.tagName === 'SELECT'
                    ? 'change'
                    : 'click';
            control.addEventListener(
                eventName,
                function () {
                    const editor =
                        getEditorFromToolbar(
                            toolbar
                        );
                    if (!editor) {
                        return;
                    }
                    const command =
                        control.dataset.command;
                    let value = null;
                    if (
                        control.tagName ===
                        'SELECT'
                    ) {
                        value =
                            control.value;
                        if (!value) {
                            return;
                        }
                    }
                    executeCommand(
                        editor,
                        command,
                        value
                    );
                    if (
                        control.tagName ===
                        'SELECT'
                    ) {
                        control.selectedIndex =
                            0;
                    }
                }
            );
        });
        const fontFamily =
            toolbar.querySelector(
                '[data-font-family]'
            );
        if (fontFamily) {
            fontFamily.addEventListener(
                'change',
                function () {
                    if (!fontFamily.value) {
                        return;
                    }
                    applyStyleToSelection(
                        getEditorFromToolbar(
                            toolbar
                        ),
                        'fontFamily',
                        fontFamily.value
                    );
                    fontFamily.selectedIndex =
                        0;
                }
            );
        }
        const fontSize =
            toolbar.querySelector(
                '[data-font-size]'
            );
        if (fontSize) {
            fontSize.addEventListener(
                'change',
                function () {
                    if (!fontSize.value) {
                        return;
                    }
                    applyStyleToSelection(
                        getEditorFromToolbar(
                            toolbar
                        ),
                        'fontSize',
                        fontSize.value
                    );
                    fontSize.selectedIndex =
                        0;
                }
            );
        }
        const foreColor =
            toolbar.querySelector(
                '[data-fore-color]'
            );
        if (foreColor) {
            foreColor.addEventListener(
                'input',
                function () {
                    executeCommand(
                        getEditorFromToolbar(
                            toolbar
                        ),
                        'foreColor',
                        foreColor.value
                    );
                }
            );
        }
        const backColor =
            toolbar.querySelector(
                '[data-back-color]'
            );
        if (backColor) {
            backColor.addEventListener(
                'input',
                function () {
                    executeCommand(
                        getEditorFromToolbar(
                            toolbar
                        ),
                        'hiliteColor',
                        backColor.value
                    );
                }
            );
        }
        const createLink =
            toolbar.querySelector(
                '[data-create-link]'
            );
        if (createLink) {
            createLink.addEventListener(
                'click',
                function () {
                    const url =
                        window.prompt(
                            <?= json_encode(t('p150i_834d44109d77'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
                        );
                    if (!url) {
                        return;
                    }
                    if (
                        !/^(https?:\/\/|mailto:|#)/i
                            .test(url)
                    ) {
                        window.alert(
                            <?= json_encode(t('p150i_fa8943f5ef37'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
                        );
                        return;
                    }
                    executeCommand(
                        getEditorFromToolbar(
                            toolbar
                        ),
                        'createLink',
                        url
                    );
                }
            );
        }
    });
    document.querySelectorAll(
        '.rich-text-form'
    ).forEach(function (form) {
        form.addEventListener(
            'submit',
            function (event) {
                const editor =
                    form.querySelector(
                        '[data-rich-editor]'
                    );
                const input =
                    form.querySelector(
                        '[data-rich-input]'
                    );
                if (
                    !editor ||
                    !input
                ) {
                    return;
                }
                input.value =
                    editor.innerHTML.trim();
                const visibleText =
                    editor.innerText
                        .replace(/\u200B/g, '')
                        .trim();
                if (visibleText === '') {
                    event.preventDefault();
                    window.alert(
                        <?= json_encode(t('p150i_ca92870a480b'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
                    );
                    editor.focus();
                }
            }
        );
    });
})();
</script>
</body>
</html>
