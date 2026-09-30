<?php
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/admin_language.php';
require_once 'auth.php';
require_once '../config/database.php';
$message = '';
$error = '';
// ======================================================
// ADD THEME
// ======================================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['add_theme'])
) {
    $name = trim(
        $_POST['name'] ?? ''
    );
    $description = trim(
        $_POST['description'] ?? ''
    );
    $displayOrder = filter_input(
        INPUT_POST,
        'display_order',
        FILTER_VALIDATE_INT
    );
    if ($name === '') {
        $error =
            t('a150j_6a0482b15d3f');
    } else {
        if (
            $displayOrder === false ||
            $displayOrder === null
        ) {
            $displayOrder = 0;
        }
        $stmt = $pdo->prepare(
            "INSERT INTO themes
            (
                name,
                description,
                display_order
            )
            VALUES (?, ?, ?)"
        );
        $stmt->execute([
            $name,
            $description,
            $displayOrder
        ]);
        $message =
            t('a150j_7eca8a8c633d');
    }
}
// ======================================================
// DELETE THEME SAFELY
// ======================================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['delete_theme'])
) {
    $themeId = filter_input(
        INPUT_POST,
        'theme_id',
        FILTER_VALIDATE_INT
    );
    if (!$themeId) {
        $error =
            t('a150j_ebef34b9b8dd');
    } else {
        // --------------------------------------------------
        // CHECK HISTORICAL ATTEMPTS
        // --------------------------------------------------
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM attempts
             WHERE theme_id = ?"
        );
        $stmt->execute([
            $themeId
        ]);
        $attemptCount =
            (int)$stmt->fetchColumn();
        // --------------------------------------------------
        // CHECK HISTORICAL RESPONSES
        // --------------------------------------------------
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM responses
             INNER JOIN questions
                ON questions.id =
                   responses.question_id
             WHERE questions.theme_id = ?"
        );
        $stmt->execute([
            $themeId
        ]);
        $responseCount =
            (int)$stmt->fetchColumn();
        // --------------------------------------------------
        // PREVENT DELETION OF HISTORICAL DATA
        // --------------------------------------------------
        if (
            $attemptCount > 0 ||
            $responseCount > 0
        ) {
            $error =
                t('a150j_46fb033147bd');
        } else {
            try {
                $pdo->beginTransaction();
                // ------------------------------------------
                // DELETE QUESTION CHOICES
                // ------------------------------------------
                $stmt = $pdo->prepare(
                    "DELETE choices
                     FROM choices
                     INNER JOIN questions
                        ON questions.id =
                           choices.question_id
                     WHERE questions.theme_id = ?"
                );
                $stmt->execute([
                    $themeId
                ]);
                // ------------------------------------------
                // DELETE UNUSED QUESTIONS
                // ------------------------------------------
                $stmt = $pdo->prepare(
                    "DELETE FROM questions
                     WHERE theme_id = ?"
                );
                $stmt->execute([
                    $themeId
                ]);
                // ------------------------------------------
                // DELETE THEME
                // ------------------------------------------
                $stmt = $pdo->prepare(
                    "DELETE FROM themes
                     WHERE id = ?"
                );
                $stmt->execute([
                    $themeId
                ]);
                $pdo->commit();
                $message =
                    t('a150j_a95d7c3c897e');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error =
                    t('a150j_a2a30632b713');
            }
        }
    }
}
// ======================================================
// ACTIVATE / DEACTIVATE THEME
// ======================================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['toggle_theme'])
) {
    $themeId = filter_input(
        INPUT_POST,
        'theme_id',
        FILTER_VALIDATE_INT
    );
    if (!$themeId) {
        $error =
            t('a150j_ebef34b9b8dd');
    } else {
        $stmt = $pdo->prepare(
            "UPDATE themes
             SET is_active =
                CASE
                    WHEN is_active = 1
                        THEN 0
                    ELSE 1
                END
             WHERE id = ?"
        );
        $stmt->execute([
            $themeId
        ]);
        header(
            'Location: themes.php'
        );
        exit;
    }
}
// ======================================================
// GET THEMES
// ======================================================
$stmt = $pdo->query(
    "SELECT *
     FROM themes
     ORDER BY
        display_order ASC,
        id ASC"
);
$themes =
    $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?= adminH(htmlLanguage()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= adminH(t('a150j_d71da25aa9b5')) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .themes-page .theme-fields {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(8rem, 12rem);
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .themes-page .theme-description { grid-column: 1 / -1; }
        .themes-page .theme-field label {
            display: block;
            font-weight: 600;
            margin-bottom: .4rem;
        }
        .themes-page .theme-field input,
        .themes-page .theme-field textarea {
            box-sizing: border-box;
            width: 100%;
        }
        .themes-page .theme-field textarea { resize: vertical; }
        .themes-page .themes-table-wrap { overflow-x: auto; }
        .themes-page .themes-table {
            width: 100%;
            min-width: 760px;
            border-collapse: collapse;
        }
        .themes-page .themes-table th,
        .themes-page .themes-table td {
            padding: .85rem 1rem;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid #e2e8f0;
        }
        .themes-page .themes-table th { background: #f8fafc; }
        .themes-page .theme-copy {
            max-width: 24rem;
            overflow-wrap: anywhere;
        }
        .themes-page .actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .5rem;
        }
        .themes-page .theme-actions { min-width: 15rem; }
        .themes-page .theme-actions form { margin: 0; }
        .themes-page .theme-actions .btn { white-space: nowrap; }
        @media (max-width: 600px) {
            .themes-page .theme-fields { grid-template-columns: minmax(0, 1fr); }
        }
    </style>
<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>
<body>
<?php require __DIR__ . '/admin_language_switcher.php'; ?>
<div class="container themes-page">
    <header class="admin-header">
        <h1><?= adminH(t('p150i_7b3acab7d31d')) ?></h1>
        <p class="text-muted">
            <?= adminH(t('h150_logged_in_as')) ?>
            <strong><?= htmlspecialchars($_SESSION['admin_username'] ?? t('admin')) ?></strong>
        </p>
    </header>
    <?php require 'admin_nav.php'; ?>
    <main>
        <section class="card">
            <h2><?= adminH(t('a150j_d71da25aa9b5')) ?></h2>
            <p class="text-muted">
                <?= adminH(t('a150j_3faeff53d834')) ?>
            </p>
        </section>
        <?php if ($message !== ''): ?>
            <div class="alert alert-success" role="status"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <section class="card">
            <h2><?= adminH(t('a150j_47b7b1a1070f')) ?></h2>
            <form method="POST">
<input type="hidden" name="lang" value="<?= adminH(currentLanguage()) ?>">
                <div class="theme-fields">
                    <div class="theme-field">
                        <label for="name"><?= adminH(t('a150j_627c55e1f4a6')) ?></label>
                        <input type="text" id="name" name="name" maxlength="255" required>
                    </div>
                    <div class="theme-field">
                        <label for="display_order"><?= adminH(t('display_order')) ?></label>
                        <input type="number" id="display_order" name="display_order" value="0" min="0">
                    </div>
                    <div class="theme-field theme-description">
                        <label for="description"><?= adminH(t('description')) ?></label>
                        <textarea id="description" name="description" rows="4" cols="50"></textarea>
                    </div>
                </div>
                <div class="actions">
                    <button class="btn" type="submit" name="add_theme"><?= adminH(t('a150j_544c3eb026d3')) ?></button>
                </div>
            </form>
        </section>
        <section class="card">
            <h2 id="existing-themes-heading"><?= adminH(t('a150j_1ad7386c58f8')) ?></h2>
            <?php if (empty($themes)): ?>
                <p class="text-muted"><?= adminH(t('a150j_4e6f818db398')) ?></p>
            <?php else: ?>
                <div class="themes-table-wrap" role="region" aria-labelledby="existing-themes-heading" tabindex="0">
                    <table class="themes-table">
                        <thead>
                            <tr>
                                <th scope="col"><?= adminH(t('p150i_3843971dcfde')) ?></th>
                                <th scope="col"><?= adminH(t('name')) ?></th>
                                <th scope="col"><?= adminH(t('description')) ?></th>
                                <th scope="col"><?= adminH(t('p150i_6be090825c99')) ?></th>
                                <th scope="col"><?= adminH(t('h150_status')) ?></th>
                                <th scope="col"><?= adminH(t('actions')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($themes as $theme): ?>
                            <tr>
                                <td><?= (int)$theme['id'] ?></td>
                                <td class="theme-copy"><strong><?= htmlspecialchars($theme['name']) ?></strong></td>
                                <td class="theme-copy"><?= htmlspecialchars($theme['description'] ?? '') ?></td>
                                <td><?= (int)$theme['display_order'] ?></td>
                                <td>
                                    <?php if ((int)$theme['is_active'] === 1): ?>
                                        <span class="status status-active"><?= adminH(t('active')) ?></span>
                                    <?php else: ?>
                                        <span class="status status-inactive"><?= adminH(t('inactive')) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions theme-actions">
                                        <a class="btn btn-secondary" href="edit_theme.php?lang=<?= adminH(currentLanguage()) ?>&amp;id=<?= (int)$theme['id'] ?>"><?= adminH(t('edit')) ?></a>
                                        <form method="POST">
<input type="hidden" name="lang" value="<?= adminH(currentLanguage()) ?>">
                                            <input type="hidden" name="theme_id" value="<?= (int)$theme['id'] ?>">
                                            <button class="btn btn-secondary" type="submit" name="toggle_theme">
                                                <?= (int)$theme['is_active'] === 1 ? t('deactivate') : t('activate') ?>
                                            </button>
                                        </form>
                                        <form method="POST" data-confirm="<?= adminH(t('a150j_240bc54ceb10')) ?>
<input type="hidden" name="lang" value="<?= adminH(currentLanguage()) ?>">" onsubmit="return confirm(this.dataset.confirm);">
                                            <input type="hidden" name="theme_id" value="<?= (int)$theme['id'] ?>">
                                            <button class="btn btn-danger" type="submit" name="delete_theme"><?= adminH(t('delete')) ?></button>
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
        <section class="card">
            <h2><?= adminH(t('a150j_eea568e67bbe')) ?></h2>
            <p class="text-muted"><?= adminH(t('a150j_5485f2c4843d')) ?></p>
            <p><strong><?= adminH(t('h150_theme_chapter_lesson_topic_paragraph')) ?></strong></p>
            <div class="actions">
                <a class="btn" href="manage_content.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('a150j_f2cb72b3db8f')) ?></a>
            </div>
        </section>
    </main>
    <footer class="workflow-navigation">
        <p class="actions">
            <a class="btn btn-secondary" href="manage_pages.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('a150j_e85977517b25')) ?></a>
            <a class="btn" href="manage_content.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('a150j_dcb893f23ba5')) ?></a>
        </p>
        <p class="actions">
            <a class="btn btn-secondary" href="index.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('admin_dashboard_link')) ?></a>
            <a class="btn btn-secondary" href="../index.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('p150i_ad2638a9d1dd')) ?></a>
            <a class="btn btn-secondary" href="logout.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('h150_logout')) ?></a>
        </p>
    </footer>
</div>
</body>
</html>
