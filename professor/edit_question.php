<?php
require_once __DIR__ . "/../includes/question_support.php";
require_once __DIR__ . '/professor_language.php';
require_once __DIR__ . '/expansion_bootstrap.php';
px_guard('edit_question.php');
require_once '../config/database.php';
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
    'audio/x-wav' => [
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
    ]
];
// ======================================================
// GET QUESTION ID
// ======================================================
$questionId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);
if (!$questionId) {
    die(t('p150i_d8c636d1e637'));
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
    $stmt->fetchAll(PDO::FETCH_ASSOC);
// ======================================================
// GET CHAPTERS
// ======================================================
$stmt = $pdo->query("SELECT
        id,
        theme_id,
        title,
        display_order
     FROM chapters
     WHERE " . px_scope_sql('chapters', 'chapters') . " AND  is_active = 1
     ORDER BY
        display_order ASC,
        id ASC");
$chapters =
    $stmt->fetchAll(PDO::FETCH_ASSOC);
// ======================================================
// GET LESSONS
// ======================================================
$stmt = $pdo->query("SELECT
        id,
        chapter_id,
        title,
        display_order
     FROM lessons
     WHERE " . px_scope_sql('lessons', 'lessons') . " AND  is_active = 1
     ORDER BY
        display_order ASC,
        id ASC");
$lessons =
    $stmt->fetchAll(PDO::FETCH_ASSOC);
// ======================================================
// GET TOPICS
// ======================================================
$stmt = $pdo->query("SELECT
        id,
        lesson_id,
        title,
        display_order
     FROM topics
     WHERE " . px_scope_sql('topics', 'topics') . " AND  is_active = 1
     ORDER BY
        display_order ASC,
        id ASC");
$topics =
    $stmt->fetchAll(PDO::FETCH_ASSOC);
// ======================================================
// GET PARAGRAPHS
// ======================================================
$stmt = $pdo->query("SELECT
        id,
        topic_id,
        title,
        content,
        display_order
     FROM paragraphs
     WHERE " . px_scope_sql('paragraphs', 'paragraphs') . " AND  is_active = 1
     ORDER BY
        display_order ASC,
        id ASC");
$paragraphs =
    $stmt->fetchAll(PDO::FETCH_ASSOC);
// ======================================================
// GET QUESTION
// ======================================================
$stmt = $pdo->prepare(
    "SELECT *
     FROM questions
     WHERE id = ?
     LIMIT 1"
);
$stmt->execute([$questionId]);
$question =
    $stmt->fetch(PDO::FETCH_ASSOC);
if (!$question) {
    die(t('p150i_6c6476677e5b'));
}
// ======================================================
// GET EXISTING CHOICES
// ======================================================
$stmt = $pdo->prepare(
    "SELECT *
     FROM choices
     WHERE question_id = ?
     ORDER BY
        display_order ASC,
        id ASC"
);
$stmt->execute([$questionId]);
$existingChoices =
    $stmt->fetchAll(PDO::FETCH_ASSOC);
// ======================================================
// UPDATE QUESTION
// ======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    // Optional hierarchy values become NULL.
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
    $removeMedia =
        isset($_POST['remove_media']);
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
        $points === null ||
        $points < 0
    ) {
        $error = t('p150i_32050a593326');
    } else {
        if (
            $displayOrder === false ||
            $displayOrder === null
        ) {
            $displayOrder = 0;
        }
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
    // VERIFY CHAPTER
    // ==================================================
    if (
        $error === '' &&
        $chapterId !== null
    ) {
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
    // ==================================================
    // VERIFY LESSON
    // ==================================================
    if (
        $error === '' &&
        $lessonId !== null
    ) {
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
    // ==================================================
    // VERIFY TOPIC
    // ==================================================
    if (
        $error === '' &&
        $topicId !== null
    ) {
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
    // ==================================================
    // VERIFY PARAGRAPH
    // ==================================================
    if (
        $error === '' &&
        $paragraphId !== null
    ) {
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
    // EXISTING MEDIA
    // ==================================================
    $oldMediaType =
        $question['media_type'] ?? null;
    $oldMediaPath =
        $question['media_path'] ?? null;
    $newMediaType =
        $oldMediaType;
    $newMediaPath =
        $oldMediaPath;
    $newPhysicalPath = null;
    $newFileUploaded = false;
    // ==================================================
    // CHECK NEW MEDIA
    // ==================================================
    $hasNewUpload = (
        isset($_FILES['question_media']) &&
        $_FILES['question_media']['error']
            !== UPLOAD_ERR_NO_FILE
    );
    if (
        $error === '' &&
        $hasNewUpload
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
                $newMediaType =
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
                $newPhysicalPath =
                    $uploadDirectory
                    . $filename;
                $newMediaPath =
                    $mediaDatabaseDirectory
                    . $filename;
                if (
                    !move_uploaded_file(
                        $uploadedFile['tmp_name'],
                        $newPhysicalPath
                    )
                ) {
                    $error =
                        t('p150i_c5ccdd425fc6');
                    $newMediaType =
                        $oldMediaType;
                    $newMediaPath =
                        $oldMediaPath;
                    $newPhysicalPath = null;
                } else {
                    $newFileUploaded = true;
                }
            }
        }
    }
    // ==================================================
    // REMOVE CURRENT MEDIA
    // ==================================================
    if (
        $error === '' &&
        $removeMedia &&
        !$hasNewUpload
    ) {
        $newMediaType = null;
        $newMediaPath = null;
    }
    // ==================================================
    // SAVE QUESTION
    // ==================================================
    if ($error === '') {
        try {
            $pdo->beginTransaction();
            px_guard('edit_question.php');
            qp_validate_choices($_POST, $questionType);
            $stmt = $pdo->prepare(
                "UPDATE questions
                 SET
                    theme_id = ?,
                    chapter_id = ?,
                    lesson_id = ?,
                    topic_id = ?,
                    paragraph_id = ?,
                    question_text = ?,
                    media_type = ?,
                    media_path = ?,
                    question_type = ?,
                    points = ?,
                    display_order = ?
                 WHERE id = ?"
            );
            $stmt->execute([
                $themeId,
                $chapterId,
                $lessonId,
                $topicId,
                $paragraphId,
                $questionText,
                $newMediaType,
                $newMediaPath,
                $questionType,
                $points,
                $displayOrder,
                $questionId
            ]);
            // ==========================================
            // CHOICE QUESTION
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
                $validChoices = [];
                foreach (
                    $choiceTexts
                    as $index => $text
                ) {
                    if (is_array($text)) {
                        continue;
                    }
                    $text =
                        trim((string)$text);
                    if ($text === '') {
                        continue;
                    }
                    $validChoices[$index] =
                        $text;
                }
                if (count($validChoices) < 2) {
                    throw new Exception(
                        t('p150i_a07b618cd5fd')
                    );
                }
                $correctCount = 0;
                foreach (
                    array_keys($validChoices)
                    as $index
                ) {
                    if (
                        in_array(
                            (string)$index,
                            array_map(
                                'strval',
                                $correctChoices
                            ),
                            true
                        )
                    ) {
                        $correctCount++;
                    }
                }
                if ($correctCount === 0) {
                    throw new Exception(
                        t('p150i_e568696c7352')
                    );
                }
                if (
                    $questionType ===
                        'single_choice' &&
                    $correctCount !== 1
                ) {
                    throw new Exception(
                        t('p150i_02a1f65eabea')
                    );
                }
                // Recreate choices.
                $stmt = $pdo->prepare(
                    "DELETE FROM choices
                     WHERE question_id = ?"
                );
                $stmt->execute([$questionId]);
                $order = 1;
                foreach (
                    $validChoices
                    as $index => $text
                ) {
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
                        $text,
                        $isCorrect,
                        $order
                    ]);
                    $order++;
                }
            } else {
                // Open questions do not use choices.
                $stmt = $pdo->prepare(
                    "DELETE FROM choices
                     WHERE question_id = ?"
                );
                $stmt->execute([$questionId]);
            }
            qp_save_support($pdo, $questionId, $_POST);
            $pdo->commit();
            // Delete previous media after successful
            // database transaction.
            if (
                !empty($oldMediaPath) &&
                $oldMediaPath !== $newMediaPath
            ) {
                $oldFilename =
                    basename($oldMediaPath);
                $oldPhysicalPath =
                    $uploadDirectory
                    . $oldFilename;
                if (is_file($oldPhysicalPath)) {
                    @unlink($oldPhysicalPath);
                }
            }
            header(
                'Location: questions.php'
            );
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (
                $newFileUploaded &&
                $newPhysicalPath !== null &&
                is_file($newPhysicalPath)
            ) {
                @unlink($newPhysicalPath);
            }
            $error =
                $e->getMessage();
        }
    }
    // ==================================================
    // REFRESH FORM AFTER ERROR
    // ==================================================
    if ($error !== '') {
        $question['theme_id'] =
            $themeId ?: $question['theme_id'];
        $question['chapter_id'] =
            $chapterId;
        $question['lesson_id'] =
            $lessonId;
        $question['topic_id'] =
            $topicId;
        $question['paragraph_id'] =
            $paragraphId;
        $question['question_text'] =
            $questionText;
        $question['question_type'] =
            $questionType;
        $question['points'] =
            $points !== false
                ? $points
                : $question['points'];
        $question['display_order'] =
            $displayOrder !== false
                ? $displayOrder
                : $question['display_order'];
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
        <?= professorH(t('edit_question')) ?>
    </title>
    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >
<style>
.edit-question-page .card h2 { margin-top: 0; }
.edit-question-page .question-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
.edit-question-page .question-grid > p { min-width: 0; margin: 0; }
.edit-question-page .question-choice { display: grid; grid-template-columns: 5rem minmax(0, 1fr) auto; align-items: center; gap: .75rem; padding: 1rem; border: 1px solid #dbe2ea; border-radius: 8px; }
.edit-question-page .question-choice input, .edit-question-page .question-choice label { margin-bottom: 0; }
.edit-question-page .question-correct { display: inline-flex; align-items: center; white-space: nowrap; }
.edit-question-page .current-media { display: block; width: 100%; height: auto; border-radius: 8px; }
.edit-question-page .question-image { max-width: 500px; }
.edit-question-page .question-audio { max-width: 600px; }
.edit-question-page .question-video { max-width: 650px; }
.edit-question-page .paragraph-preview-box { margin: 1rem 0; padding: 1rem; border: 1px solid #dbe2ea; border-radius: 8px; background: #f8fafc; overflow-wrap: anywhere; }
.edit-question-page .paragraph-preview-content { white-space: pre-wrap; }
@media (max-width: 600px) {
    .edit-question-page .question-grid, .edit-question-page .question-choice { grid-template-columns: minmax(0, 1fr); }
    .edit-question-page .card { padding: 1rem; }
}
</style>
<script src="../assets/js/professor_language.js" defer></script>
<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>
<body>
<div class="container edit-question-page">
<header class="admin-header">
    <h1><?= professorH(t('p150i_7b3acab7d31d')) ?></h1>
    <p><?= professorH(t('h150_logged_in_as')) ?> <?= htmlspecialchars(
        $_SESSION['admin_username'] ?? t('admin'),
        ENT_QUOTES,
        'UTF-8'
    ) ?></p>
</header>
<?php require 'professor_nav.php'; ?>
<h1>
    <?= professorH(t('edit_question')) ?>
</h1>
<?php if ($error !== ''): ?>
    <p class="alert alert-error" role="alert">
        <strong>
            <?= htmlspecialchars($error) ?>
        </strong>
    </p>
<?php endif; ?>
<form
    method="POST"
    enctype="multipart/form-data" class="question-form"
><?php px_csrf_field(); ?>
<section class="card"><h2><?= professorH(t('p150i_d6442c74b8ed')) ?></h2><div class="question-grid">
    <!-- ============================================== -->
    <!-- THEME -->
    <!-- ============================================== -->
    <p>
        <label for="theme_id">
            <strong><?= professorH(t('p150i_a214cea88890')) ?></strong>
        </label>
        <select
            id="theme_id"
            name="theme_id"
            required
        >
            <?php foreach ($themes as $theme): ?>
                <option
                    value="<?= (int)$theme['id'] ?>"
                    <?=
                        (int)$theme['id'] ===
                        (int)$question['theme_id']
                            ? 'selected'
                            : ''
                    ?>
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
            <?= professorH(t('chapter')) ?>
        </label>
        <select
            id="chapter_id"
            name="chapter_id"
        >
            <option value="">
                <?= professorH(t('p150i_5030572451bb')) ?>
            </option>
        </select>
    </p>
    <!-- ============================================== -->
    <!-- LESSON -->
    <!-- ============================================== -->
    <p>
        <label for="lesson_id">
            <?= professorH(t('lesson')) ?>
        </label>
        <select
            id="lesson_id"
            name="lesson_id"
        >
            <option value="">
                <?= professorH(t('p150i_9e84b87daf8c')) ?>
            </option>
        </select>
    </p>
    <!-- ============================================== -->
    <!-- TOPIC -->
    <!-- ============================================== -->
    <p>
        <label for="topic_id">
            <?= professorH(t('topic')) ?>
        </label>
        <select
            id="topic_id"
            name="topic_id"
        >
            <option value="">
                <?= professorH(t('p150i_2d47c8701817')) ?>
            </option>
        </select>
    </p>
    <!-- ============================================== -->
    <!-- PARAGRAPH -->
    <!-- ============================================== -->
    <p>
        <label for="paragraph_id">
            <?= professorH(t('paragraph')) ?>
        </label>
        <select
            id="paragraph_id"
            name="paragraph_id"
        >
            <option value="">
                <?= professorH(t('p150i_b0cc46cdfd08')) ?>
            </option>
        </select>
    </p>
    </div><div id="paragraph-preview"></div></section><section class="card"><h2><?= professorH(t('p150i_75c3c5e1408d')) ?></h2>
    <!-- ============================================== -->
    <!-- QUESTION -->
    <!-- ============================================== -->
    <p>
        <label for="question_text">
            <strong><?= professorH(t('p150i_1fde33e11c58')) ?></strong>
        </label>
        <textarea
            id="question_text"
            name="question_text"
            rows="4"
            cols="70"
            required
        ><?= htmlspecialchars(
            $question['question_text']
        ) ?></textarea>
    </p>
    <!-- ============================================== -->
    <!-- CURRENT MEDIA -->
    <!-- ============================================== -->
</section><section class="card"><h2><?= professorH(t('p150i_6d0aa63dff91')) ?></h2>
    <?php if (
        !empty($question['media_path'])
    ): ?>
        <p>
            <strong>
                <?= professorH(t('p150i_b8dcfb633d21')) ?>
            </strong>
        </p>
        <?php
        $currentMediaUrl =
            '../'
            . $question['media_path'];
        ?>
        <?php if (
            $question['media_type'] === 'image'
        ): ?>
            <img
                src="<?= htmlspecialchars(
                    $currentMediaUrl
                ) ?>"
                alt="<?= professorH(t('p150i_fa473caa3170')) ?>"
                class="current-media question-image"
            >
        <?php elseif (
            $question['media_type'] === 'audio'
        ): ?>
            <audio
                controls
                preload="metadata"
                class="current-media question-audio"
            >
                <source
                    src="<?= htmlspecialchars(
                        $currentMediaUrl
                    ) ?>"
                >
                <?= professorH(t('h150_your_browser_does_not_support_audio_playback')) ?>
            </audio>
        <?php elseif (
            $question['media_type'] === 'video'
        ): ?>
            <video
                controls
                preload="metadata"
                class="current-media question-video"
            >
                <source
                    src="<?= htmlspecialchars(
                        $currentMediaUrl
                    ) ?>"
                >
                <?= professorH(t('h150_your_browser_does_not_support_video_playback')) ?>
            </video>
        <?php endif; ?>
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
    <?php else: ?>
        <p class="text-muted">
            <?= professorH(t('p150i_f5ef98f3cd01')) ?>
        </p>
    <?php endif; ?>
    <!-- ============================================== -->
    <!-- REPLACE / ADD MEDIA -->
    <!-- ============================================== -->
    <p>
        <label for="question_media">
            <strong>
                <?=
                    !empty($question['media_path'])
                        ? t('p150i_4d8c3d900440')
                        : t('p150i_a078fb55c65a')
                ?>
            </strong>
        </label>
        <input
            type="file"
            id="question_media"
            name="question_media"
            accept="image/jpeg,image/png,image/gif,image/webp,audio/mpeg,audio/wav,audio/ogg,video/mp4,video/webm"
        >
    </p>
    <p class="text-muted">
        <?= professorH(t('p150i_9cdd162950a0')) ?>
    </p>
    <p class="text-muted">
        <?= professorH(t('p150i_63919f7d08a8')) ?>
    </p>
    <p class="text-muted">
        <?= professorH(t('p150i_327355c15d79')) ?>
        <strong><?= professorH(t('p150i_e405e596108c')) ?></strong>
    </p>
</section><section class="card"><h2><?= professorH(t('p150i_1d22fd60efe6')) ?></h2><div class="question-grid">
    <!-- ============================================== -->
    <!-- QUESTION TYPE -->
    <!-- ============================================== -->
    <p>
        <label for="question_type">
            <strong><?= professorH(t('p150i_63f07d20bdef')) ?></strong>
        </label>
        <select
            id="question_type"
            name="question_type"
            required
        >
            <option
                value="single_choice"
                <?=
                    $question['question_type']
                    === 'single_choice'
                        ? 'selected'
                        : ''
                ?>
            >
                <?= professorH(t('single_choice')) ?>
            </option>
            <option
                value="multiple_choice"
                <?=
                    $question['question_type']
                    === 'multiple_choice'
                        ? 'selected'
                        : ''
                ?>
            >
                <?= professorH(t('multiple_choice')) ?>
            </option>
            <option
                value="open"
                <?=
                    $question['question_type']
                    === 'open'
                        ? 'selected'
                        : ''
                ?>
            >
                <?= professorH(t('open_question')) ?>
            </option>
        </select>
    </p>
    <!-- ============================================== -->
    <!-- POINTS -->
    <!-- ============================================== -->
    <p>
        <label for="points">
            <?= professorH(t('points')) ?>
        </label>
        <input
            type="number"
            id="points"
            name="points"
            step="0.5"
            min="0"
            value="<?= htmlspecialchars(
                $question['points']
            ) ?>"
            required
        >
    </p>
    <!-- ============================================== -->
    <!-- DISPLAY ORDER -->
    <!-- ============================================== -->
    <p>
        <label for="display_order">
            <?= professorH(t('p150i_2efb1952e9e5')) ?>
        </label>
        <input
            type="number"
            id="display_order"
            name="display_order"
            min="0"
            value="<?= (int)$question[
                'display_order'
            ] ?>"
        >
    </p>
    <!-- ============================================== -->
    <!-- ANSWERS -->
    <!-- ============================================== -->
    </div></section><?php require __DIR__ . "/../includes/question_support_fields.php"; ?>
<div id="choices-section" class="card">
        <h2>
            <?= professorH(t('p150i_4ad0a0a6ae56')) ?>
        </h2>
        <?php require __DIR__ . "/../includes/question_choice_rows.php"; ?>
    </div>
    <p class="actions">
        <button type="submit" class="btn">
            <?= professorH(t('p150i_35322b5bb5a2')) ?>
        </button>
        <a href="questions.php" class="btn btn-secondary"><?= professorH(t('p150i_99c98808acaf')) ?></a>
    </p>
</form>
<nav class="workflow-navigation" aria-label="<?= professorH(t('p150i_f2ef30a0aa4b')) ?>">
    <p>
        <a class="btn btn-secondary" href="questions.php"><?= professorH(t('p150i_99c98808acaf')) ?></a>
        <a class="btn btn-secondary" href="manage_content.php"><?= professorH(t('p150i_b4fdfa19c145')) ?></a>
        <a class="btn btn-secondary" href="trainee_question_status.php"><?= professorH(t('p150i_302ca8f13e76')) ?></a>
    </p>
    <p>
        <a class="btn btn-secondary" href="index.php"><?= professorH(t('professor_dashboard')) ?></a>
        <a class="btn btn-secondary" href="../index.php"><?= professorH(t('p150i_ad2638a9d1dd')) ?></a>
        <a class="btn btn-danger" href="logout.php"><?= professorH(t('logout')) ?></a>
    </p>
</nav>
</div>
<script>
// ======================================================
// DATABASE DATA
// ======================================================
const chapters =
    <?= json_encode(
        $chapters,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
const lessons =
    <?= json_encode(
        $lessons,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
const topics =
    <?= json_encode(
        $topics,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
const paragraphs =
    <?= json_encode(
        $paragraphs,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?>;
// ======================================================
// CURRENT QUESTION VALUES
// ======================================================
const currentChapterId =
    <?= json_encode(
        $question['chapter_id'] ?? null
    ) ?>;
const currentLessonId =
    <?= json_encode(
        $question['lesson_id'] ?? null
    ) ?>;
const currentTopicId =
    <?= json_encode(
        $question['topic_id'] ?? null
    ) ?>;
const currentParagraphId =
    <?= json_encode(
        $question['paragraph_id'] ?? null
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
    element,
    text
) {
    element.innerHTML = '';
    const option =
        document.createElement('option');
    option.value = '';
    option.textContent = text;
    element.appendChild(option);
}
// ======================================================
// LOAD CHAPTERS
// ======================================================
function loadChapters(selectedId = null) {
    resetSelect(
        chapterSelect,
        <?= json_encode(t('p150i_5030572451bb'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    );
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
        if (
            selectedId !== null &&
            parseInt(chapter.id, 10) ===
            parseInt(selectedId, 10)
        ) {
            option.selected = true;
        }
        chapterSelect.appendChild(option);
    });
}
// ======================================================
// LOAD LESSONS
// ======================================================
function loadLessons(selectedId = null) {
    resetSelect(
        lessonSelect,
        <?= json_encode(t('p150i_9e84b87daf8c'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    );
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
        if (
            selectedId !== null &&
            parseInt(lesson.id, 10) ===
            parseInt(selectedId, 10)
        ) {
            option.selected = true;
        }
        lessonSelect.appendChild(option);
    });
}
// ======================================================
// LOAD TOPICS
// ======================================================
function loadTopics(selectedId = null) {
    resetSelect(
        topicSelect,
        <?= json_encode(t('p150i_2d47c8701817'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    );
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
        if (
            selectedId !== null &&
            parseInt(topic.id, 10) ===
            parseInt(selectedId, 10)
        ) {
            option.selected = true;
        }
        topicSelect.appendChild(option);
    });
}
// ======================================================
// LOAD PARAGRAPHS
// ======================================================
function loadParagraphs(selectedId = null) {
    resetSelect(
        paragraphSelect,
        <?= json_encode(t('p150i_b0cc46cdfd08'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    );
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
            let text =
                paragraph.content.substring(
                    0,
                    60
                );
            if (paragraph.content.length > 60) {
                text += '...';
            }
            option.textContent = text;
        }
        if (
            selectedId !== null &&
            parseInt(paragraph.id, 10) ===
            parseInt(selectedId, 10)
        ) {
            option.selected = true;
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
    content.textContent = paragraph.content;
    box.appendChild(content);
    paragraphPreview.appendChild(box);
}
// ======================================================
// USER CHANGES THE HIERARCHY
// ======================================================
themeSelect.addEventListener(
    'change',
    function () {
        loadChapters();
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
    }
);
chapterSelect.addEventListener(
    'change',
    function () {
        loadLessons();
        resetSelect(
            topicSelect,
            <?= json_encode(t('p150i_2d47c8701817'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
        );
        resetSelect(
            paragraphSelect,
            <?= json_encode(t('p150i_b0cc46cdfd08'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
        );
        paragraphPreview.innerHTML = '';
    }
);
lessonSelect.addEventListener(
    'change',
    function () {
        loadTopics();
        resetSelect(
            paragraphSelect,
            <?= json_encode(t('p150i_b0cc46cdfd08'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
        );
        paragraphPreview.innerHTML = '';
    }
);
topicSelect.addEventListener(
    'change',
    function () {
        loadParagraphs();
        paragraphPreview.innerHTML = '';
    }
);
paragraphSelect.addEventListener(
    'change',
    showParagraphPreview
);
// ======================================================
// LOAD CURRENT HIERARCHY
// ======================================================
loadChapters(currentChapterId);
loadLessons(currentLessonId);
loadTopics(currentTopicId);
loadParagraphs(currentParagraphId);
showParagraphPreview();
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
function updateForm() {
    if (questionType.value === 'open') {
        choicesSection.style.display =
            'none';
    } else {
        choicesSection.style.display =
            'block';
    }
}
questionType.addEventListener(
    'change',
    updateForm
);
updateForm();
</script>
</body>
</html>