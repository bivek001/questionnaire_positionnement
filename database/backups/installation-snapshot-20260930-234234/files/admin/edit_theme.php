<?php
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/admin_language.php';

require_once 'auth.php';
require_once '../config/database.php';

$error = '';

// =====================================
// GET THEME ID
// =====================================

$themeId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$themeId) {
    die(t('a150j_ebef34b9b8dd'));
}

// =====================================
// FIND THEME
// =====================================

$stmt = $pdo->prepare(
    "SELECT *
     FROM themes
     WHERE id = ?"
);

$stmt->execute([$themeId]);

$theme = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$theme) {
    die(t('a150j_3d8f8d0799e6'));
}

// =====================================
// UPDATE THEME
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');

    $description = trim($_POST['description'] ?? '');

    $displayOrder = filter_input(
        INPUT_POST,
        'display_order',
        FILTER_VALIDATE_INT
    );

    if ($name === '') {
        $error = t('a150j_6a0482b15d3f');
    } else {
        if (
            $displayOrder === false ||
            $displayOrder === null
        ) {
            $displayOrder = 0;
        }

        $stmt = $pdo->prepare(
            "UPDATE themes
             SET
                name = ?,
                description = ?,
                display_order = ?
             WHERE id = ?"
        );

        $stmt->execute([
            $name,
            $description,
            $displayOrder,
            $themeId
        ]);

        header('Location: themes.php?lang=' . rawurlencode(currentLanguage()));
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="<?= adminH(htmlLanguage()) ?>">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title><?= adminH(t('a150j_355d6c526050')) ?> | <?= adminH(t('p150i_7b3acab7d31d')) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .edit-theme-form {
            max-width: 760px;
        }

        .edit-theme-form .theme-field {
            margin-bottom: 1.5rem;
        }

        .edit-theme-form label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .edit-theme-form input,
        .edit-theme-form textarea {
            display: block;
            box-sizing: border-box;
            width: 100%;
            max-width: 100%;
            padding: 0.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.5rem;
            background: #fff;
            color: #1e293b;
            font: inherit;
        }

        .edit-theme-form textarea {
            min-height: 150px;
            resize: vertical;
        }

        .edit-theme-form input:focus-visible,
        .edit-theme-form textarea:focus-visible {
            outline: 2px solid #2563eb;
            outline-offset: 2px;
        }

        .edit-theme-form .theme-order {
            max-width: 220px;
        }

        .edit-theme-form .actions,
        .edit-theme-navigation .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
    </style>
<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>
<body>
<?php require __DIR__ . '/admin_language_switcher.php'; ?>
<div class="container">
    <header class="admin-header">
        <h1><?= adminH(t('p150i_7b3acab7d31d')) ?></h1>
        <p class="text-muted">
            <?= adminH(t('h150_logged_in_as')) ?>
            <strong><?= htmlspecialchars($_SESSION['admin_username'] ?? t('admin')) ?></strong>
        </p>
    </header>

    <?php require 'admin_nav.php'; ?>

    <main>
        <div class="actions">
            <a href="themes.php?lang=<?= adminH(currentLanguage()) ?>" class="btn btn-secondary">
                <?= adminH(t('a150j_164c3356c660')) ?>
            </a>
        </div>

        <h2><?= adminH(t('a150j_355d6c526050')) ?></h2>
        <p class="text-muted">
            <?= adminH(t('a150j_efc5b4a587ca')) ?>
        </p>

        <?php if ($error): ?>
            <div class="alert alert-error" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <section class="card" aria-labelledby="theme-details-heading">
            <h3 id="theme-details-heading"><?= adminH(t('a150j_6c40c3959001')) ?></h3>

            <form method="POST" class="edit-theme-form">
<input type="hidden" name="lang" value="<?= adminH(currentLanguage()) ?>">
                <div class="theme-field">
                    <label for="name"><?= adminH(t('a150j_627c55e1f4a6')) ?></label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?= htmlspecialchars($theme['name']) ?>"
                        required
                    >
                </div>

                <div class="theme-field">
                    <label for="description"><?= adminH(t('description')) ?></label>
                    <textarea
                        id="description"
                        name="description"
                        rows="5"
                        cols="60"
                    ><?= htmlspecialchars($theme['description'] ?? '') ?></textarea>
                </div>

                <div class="theme-field theme-order">
                    <label for="display_order"><?= adminH(t('display_order')) ?></label>
                    <input
                        type="number"
                        id="display_order"
                        name="display_order"
                        min="0"
                        value="<?= (int)$theme['display_order'] ?>"
                    >
                </div>

                <div class="actions">
                    <button type="submit" class="btn">
                        <?= adminH(t('p150i_35322b5bb5a2')) ?>
                    </button>
                    <a href="themes.php?lang=<?= adminH(currentLanguage()) ?>" class="btn btn-secondary">
                        <?= adminH(t('cancel')) ?>
                    </a>
                </div>
            </form>
        </section>
    </main>

    <nav
        class="workflow-navigation edit-theme-navigation"
        aria-label="<?= adminH(t('a150j_d4e8cd499bb2')) ?>"
    >
        <div class="actions">
            <a href="themes.php?lang=<?= adminH(currentLanguage()) ?>" class="btn btn-secondary">
                <?= adminH(t('a150j_7258a08cddd4')) ?>
            </a>
            <a href="index.php?lang=<?= adminH(currentLanguage()) ?>" class="btn btn-secondary">
                <?= adminH(t('admin_dashboard_link')) ?>
            </a>
            <a href="../index.php?lang=<?= adminH(currentLanguage()) ?>" class="btn btn-secondary">
                <?= adminH(t('p150i_ad2638a9d1dd')) ?>
            </a>
            <a href="logout.php?lang=<?= adminH(currentLanguage()) ?>" class="btn btn-secondary">
                <?= adminH(t('h150_logout')) ?>
            </a>
        </div>
    </nav>
</div>
</body>
</html>