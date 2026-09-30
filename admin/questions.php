<?php
require_once __DIR__ . "/../includes/question_support.php";
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/admin_language.php';

require_once 'auth.php';
require_once __DIR__.'/../includes/account_security.php';
ux_csrf_check();
require_once '../config/database.php';

$message = '';
$error = '';


// ======================================================
// MEDIA CONFIGURATION
// ======================================================

$uploadDirectory =
    dirname(__DIR__)
    . DIRECTORY_SEPARATOR
    . 'uploads'
    . DIRECTORY_SEPARATOR
    . 'question_media'
    . DIRECTORY_SEPARATOR;

$mediaDatabaseDirectory = 'uploads/question_media/';

$maximumUploadSize = 50 * 1024 * 1024;

$allowedMediaTypes = [

    // Images
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

    // Audio
    'audio/mpeg' => [
        'type' => 'audio',
        'extension' => 'mp3'
    ],

    'audio/wav' => [
        'type' => 'audio',
        'extension' => 'wav'
    ],

    'audio/x-wav' => [
        'type' => 'audio',
        'extension' => 'wav'
    ],

    'audio/ogg' => [
        'type' => 'audio',
        'extension' => 'ogg'
    ],

    // Video
    'video/mp4' => [
        'type' => 'video',
        'extension' => 'mp4'
    ],

    'video/webm' => [
        'type' => 'video',
        'extension' => 'webm'
    ]
];


// ======================================================
// CREATE MEDIA DIRECTORY
// ======================================================

if (!is_dir($uploadDirectory)) {

    if (!mkdir($uploadDirectory, 0755, true)) {

        $error =
            t('p150i_850d69e9e5b2');
    }
}


// ======================================================
// DELETE QUESTION
// ======================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['delete_question'])
) {

    $questionId = filter_input(
        INPUT_POST,
        'question_id',
        FILTER_VALIDATE_INT
    );

    if (!$questionId) {

        $error = t('p150i_d8c636d1e637');

    } else {

        $stmt = $pdo->prepare(
            "SELECT
                id,
                media_path
             FROM questions
             WHERE id = ?
             LIMIT 1"
        );

        $stmt->execute([$questionId]);

        $questionToDelete =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$questionToDelete) {

            $error = t('p150i_6c6476677e5b');

        } else {

            // Do not delete a question already used
            // in trainee results.

            $stmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM responses
                 WHERE question_id = ?"
            );

            $stmt->execute([$questionId]);

            $responseCount =
                (int)$stmt->fetchColumn();

            if ($responseCount > 0) {

                $error =
                    t('p150i_e9d224cb3c5b');

            } else {

                try {

                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare(
                        "DELETE FROM choices
                         WHERE question_id = ?"
                    );

                    $stmt->execute([$questionId]);

                    $stmt = $pdo->prepare(
                        "DELETE FROM questions
                         WHERE id = ?"
                    );

                    $stmt->execute([$questionId]);

                    $pdo->commit();


                    // Delete media only after DB commit.

                    if (!empty($questionToDelete['media_path'])) {

                        $mediaFilename =
                            basename(
                                $questionToDelete['media_path']
                            );

                        $physicalMediaPath =
                            $uploadDirectory
                            . $mediaFilename;

                        if (is_file($physicalMediaPath)) {

                            @unlink($physicalMediaPath);
                        }
                    }

                    $message =
                        t('p150i_c6bd142e09ee');

                } catch (Throwable $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $error =
                        t('p150i_cdc666eff038');
                }
            }
        }
    }
}


// ======================================================
// ACTIVATE / DEACTIVATE QUESTION
// ======================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['toggle_question'])
) {

    $questionId = filter_input(
        INPUT_POST,
        'question_id',
        FILTER_VALIDATE_INT
    );

    if (!$questionId) {

        $error = t('p150i_d8c636d1e637');

    } else {

        $stmt = $pdo->prepare(
            "UPDATE questions
             SET is_active =
                CASE
                    WHEN is_active = 1 THEN 0
                    ELSE 1
                END
             WHERE id = ?"
        );

        $stmt->execute([$questionId]);

        header('Location: questions.php?lang=' . rawurlencode(currentLanguage()));
        exit;
    }
}


// ======================================================
// GET THEMES
// ======================================================

$stmt = $pdo->query(
    "SELECT
        id,
        name
     FROM themes
     ORDER BY
        display_order ASC,
        name ASC"
);

$themes =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// GET CHAPTERS
// ======================================================

$stmt = $pdo->query(
    "SELECT
        id,
        theme_id,
        title,
        display_order
     FROM chapters
     WHERE is_active = 1
     ORDER BY
        display_order ASC,
        id ASC"
);

$chapters =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// GET LESSONS
// ======================================================

$stmt = $pdo->query(
    "SELECT
        id,
        chapter_id,
        title,
        display_order
     FROM lessons
     WHERE is_active = 1
     ORDER BY
        display_order ASC,
        id ASC"
);

$lessons =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// GET TOPICS
// ======================================================

$stmt = $pdo->query(
    "SELECT
        id,
        lesson_id,
        title,
        display_order
     FROM topics
     WHERE is_active = 1
     ORDER BY
        display_order ASC,
        id ASC"
);

$topics =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// GET PARAGRAPHS
// ======================================================

$stmt = $pdo->query(
    "SELECT
        id,
        topic_id,
        title,
        content,
        display_order
     FROM paragraphs
     WHERE is_active = 1
     ORDER BY
        display_order ASC,
        id ASC"
);

$paragraphs =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// CREATE QUESTION
// ======================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['create_question'])
) {

    // --------------------------------------------------
    // BASIC VALUES
    // --------------------------------------------------

    $themeId = filter_input(
        INPUT_POST,
        'theme_id',
        FILTER_VALIDATE_INT
    );

    $chapterId = filter_input(
        INPUT_POST,
        'chapter_id',
        FILTER_VALIDATE_INT
    );

    $lessonId = filter_input(
        INPUT_POST,
        'lesson_id',
        FILTER_VALIDATE_INT
    );

    $topicId = filter_input(
        INPUT_POST,
        'topic_id',
        FILTER_VALIDATE_INT
    );

    $paragraphId = filter_input(
        INPUT_POST,
        'paragraph_id',
        FILTER_VALIDATE_INT
    );


    // Optional hierarchy fields become NULL.

    $chapterId = $chapterId ?: null;
    $lessonId = $lessonId ?: null;
    $topicId = $topicId ?: null;
    $paragraphId = $paragraphId ?: null;


    $questionText =
        trim($_POST['question_text'] ?? '');

    $questionType =
        $_POST['question_type'] ?? '';

    $points = filter_input(
        INPUT_POST,
        'points',
        FILTER_VALIDATE_FLOAT
    );

    $displayOrder = filter_input(
        INPUT_POST,
        'display_order',
        FILTER_VALIDATE_INT
    );

    $allowedTypes = [
        'single_choice',
        'multiple_choice',
        'open'
    ];


    // ==================================================
    // BASIC VALIDATION
    // ==================================================

    if (!$themeId) {

        $error = t('p150i_c67c4c3d81c5');

    } elseif ($questionText === '') {

        $error = t('p150i_48f503d6bf45');

    } elseif (
        !in_array(
            $questionType,
            $allowedTypes,
            true
        )
    ) {

        $error = t('p150i_39a60726a069');

    } elseif (
        $points === false ||
        $points < 0
    ) {

        $error =
            t('p150i_b2c0caea545e');

    } else {

        if ($displayOrder === false) {
            $displayOrder = 0;
        }


        // ==============================================
        // VERIFY THEME
        // ==============================================

        $stmt = $pdo->prepare(
            "SELECT id
             FROM themes
             WHERE id = ?
             LIMIT 1"
        );

        $stmt->execute([$themeId]);

        if (!$stmt->fetchColumn()) {

            $error =
                t('p150i_fe1b34d392eb');
        }
    }


    // ==================================================
    // VERIFY HIERARCHY RELATIONSHIPS
    // ==================================================

    if ($error === '' && $chapterId !== null) {

        $stmt = $pdo->prepare(
            "SELECT id
             FROM chapters
             WHERE id = ?
               AND theme_id = ?
               AND is_active = 1
             LIMIT 1"
        );

        $stmt->execute([
            $chapterId,
            $themeId
        ]);

        if (!$stmt->fetchColumn()) {

            $error =
                t('p150i_9291a485ce2a');
        }
    }


    if ($error === '' && $lessonId !== null) {

        if ($chapterId === null) {

            $error =
                t('p150i_7eeb7a606a7f');

        } else {

            $stmt = $pdo->prepare(
                "SELECT id
                 FROM lessons
                 WHERE id = ?
                   AND chapter_id = ?
                   AND is_active = 1
                 LIMIT 1"
            );

            $stmt->execute([
                $lessonId,
                $chapterId
            ]);

            if (!$stmt->fetchColumn()) {

                $error =
                    t('p150i_845228c652ad');
            }
        }
    }


    if ($error === '' && $topicId !== null) {

        if ($lessonId === null) {

            $error =
                t('p150i_f3ff43dc3624');

        } else {

            $stmt = $pdo->prepare(
                "SELECT id
                 FROM topics
                 WHERE id = ?
                   AND lesson_id = ?
                   AND is_active = 1
                 LIMIT 1"
            );

            $stmt->execute([
                $topicId,
                $lessonId
            ]);

            if (!$stmt->fetchColumn()) {

                $error =
                    t('p150i_7815334ea1ba');
            }
        }
    }


    if ($error === '' && $paragraphId !== null) {

        if ($topicId === null) {

            $error =
                t('p150i_a46260cd78e2');

        } else {

            $stmt = $pdo->prepare(
                "SELECT id
                 FROM paragraphs
                 WHERE id = ?
                   AND topic_id = ?
                   AND is_active = 1
                 LIMIT 1"
            );

            $stmt->execute([
                $paragraphId,
                $topicId
            ]);

            if (!$stmt->fetchColumn()) {

                $error =
                    t('p150i_c7142137eb56');
            }
        }
    }


    // ==================================================
    // MEDIA UPLOAD
    // ==================================================

    $mediaType = null;
    $mediaPath = null;
    $uploadedPhysicalPath = null;


    if (
        $error === '' &&
        isset($_FILES['question_media']) &&
        $_FILES['question_media']['error']
            !== UPLOAD_ERR_NO_FILE
    ) {

        $uploadedFile =
            $_FILES['question_media'];


        if (
            $uploadedFile['error']
            !== UPLOAD_ERR_OK
        ) {

            $error =
                t('p150i_dff41aa34135');

        } elseif (
            (int)$uploadedFile['size'] <= 0
        ) {

            $error =
                t('p150i_6c63fc13e7b0');

        } elseif (
            (int)$uploadedFile['size']
            > $maximumUploadSize
        ) {

            $error =
                t('p150i_c3800f7f7646');

        } elseif (
            !is_uploaded_file(
                $uploadedFile['tmp_name']
            )
        ) {

            $error =
                t('p150i_f23a03e56980');

        } else {

            $finfo =
                new finfo(FILEINFO_MIME_TYPE);

            $detectedMimeType =
                $finfo->file(
                    $uploadedFile['tmp_name']
                );


            if (
                !isset(
                    $allowedMediaTypes[
                        $detectedMimeType
                    ]
                )
            ) {

                $error =
                    t('p150i_1b03fa16dfc9');

            } else {

                $mediaInformation =
                    $allowedMediaTypes[
                        $detectedMimeType
                    ];

                $mediaType =
                    $mediaInformation['type'];

                $extension =
                    $mediaInformation['extension'];


                try {

                    $randomName =
                        bin2hex(random_bytes(16));

                } catch (Throwable $e) {

                    $randomName =
                        str_replace(
                            '.',
                            '',
                            uniqid(
                                'question_',
                                true
                            )
                        );
                }


                $filename =
                    $randomName
                    . '.'
                    . $extension;

                $uploadedPhysicalPath =
                    $uploadDirectory
                    . $filename;

                $mediaPath =
                    $mediaDatabaseDirectory
                    . $filename;


                if (
                    !move_uploaded_file(
                        $uploadedFile['tmp_name'],
                        $uploadedPhysicalPath
                    )
                ) {

                    $error =
                        t('p150i_c04509cbec38');

                    $mediaType = null;
                    $mediaPath = null;
                    $uploadedPhysicalPath = null;
                }
            }
        }
    }


    // ==================================================
    // INSERT QUESTION
    // ==================================================

    if ($error === '') {

        try {

            $pdo->beginTransaction();


            qp_validate_choices($_POST, $questionType);
            $stmt = $pdo->prepare(
                "INSERT INTO questions
                (
                    theme_id,
                    chapter_id,
                    lesson_id,
                    topic_id,
                    paragraph_id,
                    question_text,
                    media_type,
                    media_path,
                    question_type,
                    points,
                    display_order
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )"
            );


            $stmt->execute([
                $themeId,
                $chapterId,
                $lessonId,
                $topicId,
                $paragraphId,
                $questionText,
                $mediaType,
                $mediaPath,
                $questionType,
                $points,
                $displayOrder
            ]);


            $questionId =
                (int)$pdo->lastInsertId();


            // ==========================================
            // INSERT ANSWER CHOICES
            // ==========================================

            if (
                $questionType === 'single_choice' ||
                $questionType === 'multiple_choice'
            ) {

                $choiceTexts =
                    $_POST['choices'] ?? [];

                $correctChoices =
                    $_POST['correct_choices'] ?? [];


                if (!is_array($choiceTexts)) {
                    $choiceTexts = [];
                }

                if (!is_array($correctChoices)) {
                    $correctChoices = [];
                }


                $validChoiceCount = 0;
                $correctCount = 0;


                foreach (
                    $choiceTexts
                    as $index => $choiceText
                ) {

                    if (is_array($choiceText)) {
                        continue;
                    }

                    $choiceText =
                        trim((string)$choiceText);

                    if ($choiceText === '') {
                        continue;
                    }


                    $isCorrect =
                        in_array(
                            (string)$index,
                            array_map(
                                'strval',
                                $correctChoices
                            ),
                            true
                        )
                        ? 1
                        : 0;


                    if ($isCorrect) {
                        $correctCount++;
                    }

                    $validChoiceCount++;


                    $stmt = $pdo->prepare(
                        "INSERT INTO choices
                        (
                            question_id,
                            choice_text,
                            is_correct,
                            display_order
                        )
                        VALUES (?, ?, ?, ?)"
                    );


                    $stmt->execute([
                        $questionId,
                        $choiceText,
                        $isCorrect,
                        $validChoiceCount
                    ]);
                }


                if ($validChoiceCount < 2) {

                    throw new Exception(
                        'A choice question needs '
                        . 'at least two answers.'
                    );
                }


                if ($correctCount === 0) {

                    throw new Exception(
                        t('p150i_96e5e1bb697f')
                    );
                }


                if (
                    $questionType ===
                        'single_choice' &&
                    $correctCount !== 1
                ) {

                    throw new Exception(
                        'A single-choice question '
                        . 'must have exactly one '
                        . 'correct answer.'
                    );
                }
            }


            qp_save_support($pdo, $questionId, $_POST);
            $pdo->commit();

            $message =
                t('p150i_8541999231f2');


        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if (
                $uploadedPhysicalPath !== null &&
                is_file($uploadedPhysicalPath)
            ) {

                @unlink($uploadedPhysicalPath);
            }

            $uploadedPhysicalPath = null;

            $error = $e->getMessage();
        }
    }


    // Remove uploaded file when validation
    // failed after it was moved.

    if (
        $error !== '' &&
        $uploadedPhysicalPath !== null &&
        is_file($uploadedPhysicalPath)
    ) {

        @unlink($uploadedPhysicalPath);
    }
}


// ======================================================
// GET EXISTING QUESTIONS
// ======================================================

$stmt = $pdo->query(
    "SELECT

        q.id,
        q.question_text,
        q.media_type,
        q.media_path,
        q.question_type,
        q.points,
        q.display_order,
        q.is_active,

        t.name AS theme_name,

        c.title AS chapter_title,

        l.title AS lesson_title,

        tp.title AS topic_title,

        p.title AS paragraph_title,
        p.content AS paragraph_content

     FROM questions q

     INNER JOIN themes t
        ON t.id = q.theme_id

     LEFT JOIN chapters c
        ON c.id = q.chapter_id

     LEFT JOIN lessons l
        ON l.id = q.lesson_id

     LEFT JOIN topics tp
        ON tp.id = q.topic_id

     LEFT JOIN paragraphs p
        ON p.id = q.paragraph_id

     ORDER BY
        t.display_order ASC,
        COALESCE(c.display_order, 999999) ASC,
        COALESCE(l.display_order, 999999) ASC,
        COALESCE(tp.display_order, 999999) ASC,
        COALESCE(p.display_order, 999999) ASC,
        q.display_order ASC,
        q.id ASC"
);

$questions =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>

<html lang="<?= adminH(htmlLanguage()) ?>">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= adminH(t('manage_questions')) ?>
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

<style>
/* Layout and media rules scoped to this page; shared components use style.css. */
.questions-page .question-group { min-width: 0; margin: 0 0 1.5rem; padding: 1.25rem; border: 1px solid #dbe2ea; border-radius: 10px; }
.questions-page .question-group legend { padding: 0 .5rem; font-weight: 700; }
.questions-page .question-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
.questions-page .question-grid > p { min-width: 0; margin: 0; }
.questions-page .question-form label { display: block; margin-bottom: .45rem; }
.questions-page .question-form select,
.questions-page .question-form textarea,
.questions-page .question-form input:not([type="checkbox"]):not([type="hidden"]) { width: 100%; min-width: 0; box-sizing: border-box; }
.questions-page .question-form textarea { resize: vertical; }
.questions-page .question-choice { display: grid; grid-template-columns: 5rem minmax(0, 1fr) auto; align-items: center; gap: .75rem; margin: .85rem 0; }
.questions-page .question-choice label { margin: 0; }
.questions-page .question-correct { display: inline-flex; align-items: center; gap: .4rem; white-space: nowrap; }
.questions-page .question-correct input { width: auto; margin: 0; }
.questions-page .paragraph-preview-box { margin-top: 1rem; padding: 1rem; border: 1px solid #dbe2ea; border-radius: 8px; background: #f8fafc; overflow-wrap: anywhere; }
.questions-page .paragraph-preview-content { white-space: pre-wrap; }
.questions-page .questions-table-wrap { width: 100%; overflow-x: auto; }
.questions-page .questions-table { width: 100%; min-width: 1180px; border-collapse: collapse; }
.questions-page .questions-table th,
.questions-page .questions-table td { padding: .85rem; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
.questions-page .questions-table th { background: #f1f5f9; color: #334155; white-space: nowrap; }
.questions-page .questions-table tbody tr:hover { background: #f8fafc; }
.questions-page .questions-table td { overflow-wrap: anywhere; }
.questions-page .questions-table td:nth-child(7) { min-width: 220px; }
.questions-page .question-image { display: block; max-width: 160px; max-height: 120px; object-fit: contain; border-radius: 6px; }
.questions-page .question-audio { display: block; width: 240px; max-width: 100%; }
.questions-page .question-video { display: block; max-width: 220px; max-height: 150px; border-radius: 6px; }
.questions-page .question-actions { display: flex; flex-wrap: wrap; gap: .5rem; min-width: 145px; }
.questions-page .question-action-form { margin: 0; padding: 0; }
.questions-page .question-actions .btn { margin: 0; white-space: nowrap; }
.questions-page .workflow-navigation .actions { display: flex; flex-wrap: wrap; gap: .75rem; }
@media (max-width: 600px) {
    .questions-page .question-group { padding: 1rem; }
    .questions-page .question-grid { grid-template-columns: minmax(0, 1fr); }
    .questions-page .question-choice { grid-template-columns: minmax(0, 1fr); gap: .4rem; }
}
</style>
<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>

<body>
<?php require __DIR__ . '/admin_language_switcher.php'; ?>

<div class="container questions-page">

<header class="admin-header">
<h1><?= adminH(t('p150i_7b3acab7d31d')) ?></h1>

<p>
    <?= adminH(t('h150_logged_in_as')) ?>
    <strong><?= htmlspecialchars($_SESSION['admin_username'] ?? t('admin'), ENT_QUOTES, 'UTF-8') ?></strong>
</p>

</header>

<?php require 'admin_nav.php'; ?>

<h1>
    <?= adminH(t('manage_questions')) ?>
</h1>

<?php if ($message !== ''): ?>

    <p class="alert alert-success" role="status">
        <strong>
            <?= htmlspecialchars($message) ?>
        </strong>
    </p>

<?php endif; ?>

<?php if ($error !== ''): ?>

    <p class="alert alert-error" role="alert">
        <strong>
            <?= htmlspecialchars($error) ?>
        </strong>
    </p>

<?php endif; ?>

<section class="card" aria-labelledby="create-question-title">
<h2 id="create-question-title"><?= adminH(t('p150i_182a50d65276')) ?></h2>

<p class="text-muted">
    <?= adminH(t('p150i_11286dc6e691')) ?>
</p>

<form
    method="POST"
    enctype="multipart/form-data"
    class="question-form"
><?php ux_csrf_field(); ?><input type="hidden" name="lang" value="<?= adminH(currentLanguage()) ?>">


<fieldset class="question-group">
<legend><?= adminH(t('p150i_3c82a7b13b53')) ?></legend>
<div class="question-grid">
    <!-- ============================================== -->
    <!-- THEME -->
    <!-- ============================================== -->

    <p>

        <label for="theme_id">
            <strong><?= adminH(t('p150i_a214cea88890')) ?></strong>
        </label>

        <select
            id="theme_id"
            name="theme_id"
            required
        >

            <option value="">
                <?= adminH(t('p150i_3980d1266628')) ?>
            </option>

            <?php foreach ($themes as $theme): ?>

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

    <!-- ============================================== -->
    <!-- CHAPTER -->
    <!-- ============================================== -->

    <p>

        <label for="chapter_id">
            <?= adminH(t('h150_chapter')) ?>
        </label>

        <select
            id="chapter_id"
            name="chapter_id"
        >

            <option value="">
                <?= adminH(t('p150i_5030572451bb')) ?>
            </option>

        </select>

    </p>

    <!-- ============================================== -->
    <!-- LESSON -->
    <!-- ============================================== -->

    <p>

        <label for="lesson_id">
            <?= adminH(t('h150_lesson')) ?>
        </label>

        <select
            id="lesson_id"
            name="lesson_id"
        >

            <option value="">
                <?= adminH(t('p150i_9e84b87daf8c')) ?>
            </option>

        </select>

    </p>

    <!-- ============================================== -->
    <!-- TOPIC -->
    <!-- ============================================== -->

    <p>

        <label for="topic_id">
            <?= adminH(t('h150_topic')) ?>
        </label>

        <select
            id="topic_id"
            name="topic_id"
        >

            <option value="">
                <?= adminH(t('p150i_2d47c8701817')) ?>
            </option>

        </select>

    </p>

    <!-- ============================================== -->
    <!-- PARAGRAPH -->
    <!-- ============================================== -->

    <p>

        <label for="paragraph_id">
            <?= adminH(t('paragraph')) ?>
        </label>

        <select
            id="paragraph_id"
            name="paragraph_id"
        >

            <option value="">
                <?= adminH(t('p150i_b0cc46cdfd08')) ?>
            </option>

        </select>

    </p>

    </div>
<div id="paragraph-preview"></div>
</fieldset>

    <fieldset class="question-group">
<legend><?= adminH(t('p150i_073a62b264a7')) ?></legend>
<!-- ============================================== -->
    <!-- QUESTION -->
    <!-- ============================================== -->

    <p>

        <label for="question_text">
            <strong><?= adminH(t('p150i_1fde33e11c58')) ?></strong>
        </label>

        <textarea
            id="question_text"
            name="question_text"
            rows="4"
            cols="70"
            required
        ></textarea>

    </p>

    <!-- ============================================== -->
    <!-- MEDIA -->
    <!-- ============================================== -->

    <p>

        <label for="question_media">

            <strong>
                <?= adminH(t('p150i_6d0aa63dff91')) ?>
            </strong>

            <?= adminH(t('p150i_0059798b7f70')) ?>

        </label>

        <input
            type="file"
            id="question_media"
            name="question_media"
            accept="image/jpeg,image/png,image/gif,image/webp,audio/mpeg,audio/wav,audio/ogg,video/mp4,video/webm"
        >

    </p>

    <p>
        <?= adminH(t('p150i_b230e43664eb')) ?>
    </p>

    <p>
        <?= adminH(t('p150i_63919f7d08a8')) ?>
    </p>

    <p>
        <?= adminH(t('p150i_55c5319012ae')) ?>
        <strong><?= adminH(t('p150i_e405e596108c')) ?></strong>
    </p>

    </fieldset>
<fieldset class="question-group">
<legend><?= adminH(t('p150i_c257281cdec6')) ?></legend>
<div class="question-grid">
<!-- ============================================== -->
    <!-- QUESTION TYPE -->
    <!-- ============================================== -->

    <p>

        <label for="question_type">
            <strong><?= adminH(t('p150i_63f07d20bdef')) ?></strong>
        </label>

        <select
            id="question_type"
            name="question_type"
            required
        >

            <option value="single_choice">
                <?= adminH(t('single_choice')) ?>
            </option>

            <option value="multiple_choice">
                <?= adminH(t('multiple_choice')) ?>
            </option>

            <option value="open">
                <?= adminH(t('open_question')) ?>
            </option>

        </select>

    </p>

    <!-- ============================================== -->
    <!-- POINTS -->
    <!-- ============================================== -->

    <p>

        <label for="points">
            <?= adminH(t('points')) ?>
        </label>

        <input
            type="number"
            id="points"
            name="points"
            value="1"
            min="0"
            step="0.5"
            required
        >

    </p>

    <!-- ============================================== -->
    <!-- DISPLAY ORDER -->
    <!-- ============================================== -->

    <p>

        <label for="display_order">
            <?= adminH(t('p150i_2efb1952e9e5')) ?>
        </label>

        <input
            type="number"
            id="display_order"
            name="display_order"
            value="0"
            min="0"
        >

    </p>

    <!-- ============================================== -->
    <!-- ANSWERS -->
    <!-- ============================================== -->

    </div>
</fieldset>
<?php require __DIR__ . "/../includes/question_support_fields.php"; ?>
<div id="choices-section" class="question-group">

        <h3>
            <?= adminH(t('p150i_aeb3b6d4955e')) ?>
        </h3>

        <p>
            <?= adminH(t('p150i_84e9ca8fea99')) ?>
        </p>

        <?php require __DIR__ . "/../includes/question_choice_rows.php"; ?>

    </div>

    <p class="actions">
<button
            type="submit"
            name="create_question"
            class="btn"
        >
            <?= adminH(t('create_question')) ?>
        </button>

    </p>

</form>
</section>

<!-- ================================================== -->
<!-- EXISTING QUESTIONS -->
<!-- ================================================== -->

<section class="card" aria-labelledby="existing-questions-title">
<h2 id="existing-questions-title"><?= adminH(t('p150i_c141c2dc21a4')) ?></h2>

<?php if (empty($questions)): ?>

    <p class="text-muted">
        <?= adminH(t('p150i_78800d1e9ddc')) ?>
    </p>

<?php else: ?>

<div class="questions-table-wrap" role="region" aria-label="<?= adminH(t('p150i_dbca3e9ae526')) ?>" tabindex="0">

<table class="questions-table">

<thead>

<tr>

    <th scope="col"><?= adminH(t('p150i_3843971dcfde')) ?></th>

    <th scope="col"><?= adminH(t('h150_theme')) ?></th>

    <th scope="col"><?= adminH(t('h150_chapter')) ?></th>

    <th scope="col"><?= adminH(t('h150_lesson')) ?></th>

    <th scope="col"><?= adminH(t('h150_topic')) ?></th>

    <th scope="col"><?= adminH(t('paragraph')) ?></th>

    <th scope="col"><?= adminH(t('h150_question')) ?></th>

    <th scope="col"><?= adminH(t('media')) ?></th>

    <th scope="col"><?= adminH(t('type')) ?></th>

    <th scope="col"><?= adminH(t('points')) ?></th>

    <th scope="col"><?= adminH(t('h150_status')) ?></th>

    <th scope="col"><?= adminH(t('actions')) ?></th>

</tr>

</thead>

<tbody>

<?php foreach ($questions as $question): ?>

<tr>

    <td>
        <?= (int)$question['id'] ?>
    </td>

    <td>

        <?= htmlspecialchars(
            $question['theme_name']
        ) ?>

    </td>

    <td>

        <?php if (
            !empty($question['chapter_title'])
        ): ?>

            <?= htmlspecialchars(
                $question['chapter_title']
            ) ?>

        <?php else: ?>

            —

        <?php endif; ?>

    </td>

    <td>

        <?php if (
            !empty($question['lesson_title'])
        ): ?>

            <?= htmlspecialchars(
                $question['lesson_title']
            ) ?>

        <?php else: ?>

            —

        <?php endif; ?>

    </td>

    <td>

        <?php if (
            !empty($question['topic_title'])
        ): ?>

            <?= htmlspecialchars(
                $question['topic_title']
            ) ?>

        <?php else: ?>

            —

        <?php endif; ?>

    </td>

    <td>

        <?php if (
            !empty($question['paragraph_title'])
        ): ?>

            <strong>

                <?= htmlspecialchars(
                    $question['paragraph_title']
                ) ?>

            </strong>

        <?php elseif (
            !empty($question['paragraph_content'])
        ): ?>

            <?= htmlspecialchars(
                mb_strimwidth(
                    $question['paragraph_content'],
                    0,
                    60,
                    '...'
                )
            ) ?>

        <?php else: ?>

            —

        <?php endif; ?>

    </td>

    <td>

        <?= htmlspecialchars(
            $question['question_text']
        ) ?>

    </td>

    <!-- MEDIA -->

    <td>

        <?php if (
            empty($question['media_path'])
        ): ?>

            —

        <?php else: ?>

            <?php

            $mediaUrl =
                '../'
                . $question['media_path'];

            ?>

            <?php if (
                $question['media_type'] === 'image'
            ): ?>

                <img
                    src="<?= htmlspecialchars($mediaUrl) ?>"
                    alt="<?= adminH(t('p150i_3b49fe550958')) ?>"
                    class="question-image"
                >

            <?php elseif (
                $question['media_type'] === 'audio'
            ): ?>

                <audio class="question-audio"
                    controls
                    preload="metadata"
                >

                    <source
                        src="<?= htmlspecialchars($mediaUrl) ?>"
                    >

                    <?= adminH(t('h150_your_browser_does_not_support_audio_playback')) ?>

                </audio>

            <?php elseif (
                $question['media_type'] === 'video'
            ): ?>

                <video
                    controls
                    preload="metadata"
                    class="question-video"
                >

                    <source
                        src="<?= htmlspecialchars($mediaUrl) ?>"
                    >

                    <?= adminH(t('h150_your_browser_does_not_support_video_playback')) ?>

                </video>

            <?php endif; ?>

        <?php endif; ?>

    </td>

    <td>

        <?= adminH(adminEnumLabel($question['question_type'])) ?>

    </td>

    <td>

        <?= htmlspecialchars(
            $question['points']
        ) ?>

    </td>

    <td>

        <?php if (
            (int)$question['is_active'] === 1
        ): ?>

            <span class="status status-active"><?= adminH(t('active')) ?></span>

        <?php else: ?>

            <span class="status status-inactive"><?= adminH(t('inactive')) ?></span>

        <?php endif; ?>

    </td>

    <td>
<div class="actions question-actions">

        <a class="btn btn-secondary"
            href="edit_question.php?lang=<?= adminH(currentLanguage()) ?>&amp;id=<?= (int)$question['id'] ?>"
        >
            <?= adminH(t('edit')) ?>
        </a>

        <form
            method="POST"
            class="question-action-form"
        ><?php ux_csrf_field(); ?><input type="hidden" name="lang" value="<?= adminH(currentLanguage()) ?>">


            <input
                type="hidden"
                name="question_id"
                value="<?= (int)$question['id'] ?>"
            >

            <button
                type="submit"
                name="toggle_question"
                class="btn btn-secondary"
            >

                <?=
                    (int)$question['is_active'] === 1
                        ? t('deactivate')
                        : t('activate')
                ?>

            </button>

        </form>

        <form
            method="POST"
            class="question-action-form"
            data-confirm="<?= adminH(t('p150i_430cc4782725')) ?>
" onsubmit="return confirm(this.dataset.confirm);"
        ><?php ux_csrf_field(); ?><input type="hidden" name="lang" value="<?= adminH(currentLanguage()) ?>">

            <input
                type="hidden"
                name="question_id"
                value="<?= (int)$question['id'] ?>"
            >

            <button
                type="submit"
                name="delete_question"
                class="btn btn-danger"
            >
                <?= adminH(t('delete')) ?>
            </button>

        </form>

    </div>
    </td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</section>
<nav class="workflow-navigation" aria-label="<?= adminH(t('p150i_45c50bdb9f17')) ?>">
    <p class="actions">
        <a class="btn btn-secondary" href="manage_content.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('p150i_13491a39764b')) ?></a>
        <a class="btn btn-secondary" href="assign_questionnaire.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('a150j_1efbf656e89c')) ?></a>
    </p>

    <p class="actions">
        <a class="btn btn-secondary" href="index.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('admin_dashboard_link')) ?></a>
        <a class="btn btn-secondary" href="../index.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('p150i_ad2638a9d1dd')) ?></a>
        <a class="btn btn-danger" href="logout.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('h150_logout')) ?></a>
    </p>
</nav>

</div>

<!-- ================================================== -->
<!-- JAVASCRIPT DATA -->
<!-- ================================================== -->

<script>

const chapters =
    <?= json_encode(
        $chapters,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    ) ?>;

const lessons =
    <?= json_encode(
        $lessons,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    ) ?>;

const topics =
    <?= json_encode(
        $topics,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    ) ?>;

const paragraphs =
    <?= json_encode(
        $paragraphs,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    ) ?>;

// ======================================================
// ELEMENTS
// ======================================================

const themeSelect =
    document.getElementById('theme_id');

const chapterSelect =
    document.getElementById('chapter_id');

const lessonSelect =
    document.getElementById('lesson_id');

const topicSelect =
    document.getElementById('topic_id');

const paragraphSelect =
    document.getElementById('paragraph_id');

const paragraphPreview =
    document.getElementById(
        'paragraph-preview'
    );

// ======================================================
// RESET SELECT
// ======================================================

function resetSelect(
    selectElement,
    firstText
) {

    selectElement.innerHTML = '';

    const option =
        document.createElement('option');

    option.value = '';
    option.textContent = firstText;

    selectElement.appendChild(option);
}

// ======================================================
// THEME → CHAPTER
// ======================================================

function loadChapters() {

    resetSelect(
        chapterSelect,
        <?= json_encode(t('p150i_5030572451bb'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    );

    resetSelect(
        lessonSelect,
        <?= json_encode(t('p150i_9e84b87daf8c'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    );

    resetSelect(
        topicSelect,
        <?= json_encode(t('p150i_2d47c8701817'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    );

    resetSelect(
        paragraphSelect,
        <?= json_encode(t('p150i_b0cc46cdfd08'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    );

    paragraphPreview.innerHTML = '';

    const themeId =
        parseInt(themeSelect.value, 10);

    if (!themeId) {
        return;
    }

    chapters.forEach(function (chapter) {

        if (
            parseInt(chapter.theme_id, 10)
            !== themeId
        ) {
            return;
        }

        const option =
            document.createElement('option');

        option.value = chapter.id;
        option.textContent = chapter.title;

        chapterSelect.appendChild(option);
    });
}

// ======================================================
// CHAPTER → LESSON
// ======================================================

function loadLessons() {

    resetSelect(
        lessonSelect,
        <?= json_encode(t('p150i_9e84b87daf8c'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    );

    resetSelect(
        topicSelect,
        <?= json_encode(t('p150i_2d47c8701817'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    );

    resetSelect(
        paragraphSelect,
        <?= json_encode(t('p150i_b0cc46cdfd08'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    );

    paragraphPreview.innerHTML = '';

    const chapterId =
        parseInt(chapterSelect.value, 10);

    if (!chapterId) {
        return;
    }

    lessons.forEach(function (lesson) {

        if (
            parseInt(lesson.chapter_id, 10)
            !== chapterId
        ) {
            return;
        }

        const option =
            document.createElement('option');

        option.value = lesson.id;
        option.textContent = lesson.title;

        lessonSelect.appendChild(option);
    });
}

// ======================================================
// LESSON → TOPIC
// ======================================================

function loadTopics() {

    resetSelect(
        topicSelect,
        <?= json_encode(t('p150i_2d47c8701817'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    );

    resetSelect(
        paragraphSelect,
        <?= json_encode(t('p150i_b0cc46cdfd08'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    );

    paragraphPreview.innerHTML = '';

    const lessonId =
        parseInt(lessonSelect.value, 10);

    if (!lessonId) {
        return;
    }

    topics.forEach(function (topic) {

        if (
            parseInt(topic.lesson_id, 10)
            !== lessonId
        ) {
            return;
        }

        const option =
            document.createElement('option');

        option.value = topic.id;
        option.textContent = topic.title;

        topicSelect.appendChild(option);
    });
}

// ======================================================
// TOPIC → PARAGRAPH
// ======================================================

function loadParagraphs() {

    resetSelect(
        paragraphSelect,
        <?= json_encode(t('p150i_b0cc46cdfd08'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    );

    paragraphPreview.innerHTML = '';

    const topicId =
        parseInt(topicSelect.value, 10);

    if (!topicId) {
        return;
    }

    paragraphs.forEach(function (paragraph) {

        if (
            parseInt(paragraph.topic_id, 10)
            !== topicId
        ) {
            return;
        }

        const option =
            document.createElement('option');

        option.value = paragraph.id;

        if (
            paragraph.title &&
            paragraph.title.trim() !== ''
        ) {

            option.textContent =
                paragraph.title;

        } else {

            let shortText =
                paragraph.content.substring(
                    0,
                    60
                );

            if (
                paragraph.content.length > 60
            ) {
                shortText += '...';
            }

            option.textContent = shortText;
        }

        paragraphSelect.appendChild(option);
    });
}

// ======================================================
// PARAGRAPH PREVIEW
// ======================================================

function showParagraphPreview() {

    paragraphPreview.innerHTML = '';

    const paragraphId =
        parseInt(
            paragraphSelect.value,
            10
        );

    if (!paragraphId) {
        return;
    }

    const paragraph =
        paragraphs.find(function (item) {

            return (
                parseInt(item.id, 10)
                === paragraphId
            );
        });

    if (!paragraph) {
        return;
    }

    const box =
        document.createElement('div');

    box.className = 'paragraph-preview-box';

    if (
        paragraph.title &&
        paragraph.title.trim() !== ''
    ) {

        const title =
            document.createElement('strong');

        title.textContent =
            paragraph.title;

        box.appendChild(title);

        box.appendChild(
            document.createElement('br')
        );

        box.appendChild(
            document.createElement('br')
        );
    }

    const content =
        document.createElement('div');

    content.className = 'paragraph-preview-content';

    content.textContent =
        paragraph.content;

    box.appendChild(content);

    paragraphPreview.appendChild(box);
}

// ======================================================
// EVENT LISTENERS
// ======================================================

themeSelect.addEventListener(
    'change',
    loadChapters
);

chapterSelect.addEventListener(
    'change',
    loadLessons
);

lessonSelect.addEventListener(
    'change',
    loadTopics
);

topicSelect.addEventListener(
    'change',
    loadParagraphs
);

paragraphSelect.addEventListener(
    'change',
    showParagraphPreview
);

// ======================================================
// QUESTION TYPE
// ======================================================

const questionType =
    document.getElementById(
        'question_type'
    );

const choicesSection =
    document.getElementById(
        'choices-section'
    );

function updateQuestionForm() {

    if (
        questionType.value === 'open'
    ) {

        choicesSection.style.display =
            'none';

    } else {

        choicesSection.style.display =
            'block';
    }
}

questionType.addEventListener(
    'change',
    updateQuestionForm
);

updateQuestionForm();

</script>

</body>

</html>