<?php
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/admin_language.php';

require_once 'auth.php';

require_once '../config/database.php';

$message = '';

$error = '';

// ======================================================

// UPDATE PAGE

// ======================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $pageId = filter_input(

        INPUT_POST,

        'page_id',

        FILTER_VALIDATE_INT

    );

    $title = trim(

        $_POST['title'] ?? ''

    );

    $content = trim(

        $_POST['content'] ?? ''

    );

    if (!$pageId) {

        $error = t('a150j_b89ac7cba53a');

    } elseif ($title === '') {

        $error = t('a150j_eed0d0b670c4');

    } elseif ($content === '') {

        $error = t('a150j_d9c07884bc60');

    } else {

        /*

         * Only allow the two system pages

         * to be edited here.

         */

        $stmt = $pdo->prepare(

            "SELECT id

             FROM content_pages

             WHERE id = ?

               AND page_key IN (

                   'sommaire',

                   'introduction'

               )"

        );

        $stmt->execute([

            $pageId

        ]);

        $existingPage =

            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existingPage) {

            $error = t('a150j_d53fc6cd3673');

        } else {

            $stmt = $pdo->prepare(

                "UPDATE content_pages

                 SET

                    title = ?,

                    content = ?

                 WHERE id = ?"

            );

            $stmt->execute([

                $title,

                $content,

                $pageId

            ]);

            /*

             * Redirect after POST so refreshing

             * the browser does not submit again.

             */

            header(

                'Location: manage_pages.php?updated=1'

            );

            exit;

        }

    }

}

// ======================================================

// SUCCESS MESSAGE

// ======================================================

if (

    isset($_GET['updated']) &&

    $_GET['updated'] === '1'

) {

    $message =

        t('a150j_a75b8f97be1a');

}

// ======================================================

// GET PAGES

// ======================================================

$stmt = $pdo->query(

    "SELECT

        id,

        page_key,

        title,

        content,

        updated_at

     FROM content_pages

     WHERE page_key IN (

        'sommaire',

        'introduction'

     )

     ORDER BY

        CASE page_key

            WHEN 'sommaire' THEN 1

            WHEN 'introduction' THEN 2

            ELSE 3

        END"

);

$pages =

    $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="<?= adminH(htmlLanguage()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= adminH(t('a150j_39c1e3face40')) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>
<body>
<?php require __DIR__ . '/admin_language_switcher.php'; ?>
<div class="container">
    <header class="admin-header">
        <h1><?= adminH(t('a150j_39c1e3face40')) ?></h1>
        <p><?= adminH(t('a150j_eca854b661f9')) ?></p>
        <p>
            <?= adminH(t('h150_logged_in_as')) ?>
            <strong><?= htmlspecialchars($_SESSION['admin_username'] ?? t('admin')) ?></strong>
        </p>
    </header>

    <?php require 'admin_nav.php'; ?>

    <main>
        <section class="card">
            <h2><?= adminH(t('course_pages')) ?></h2>
            <p><?= adminH(t('a150j_df3822e99838')) ?></p>
            <p class="text-muted">
                <?= adminH(t('a150j_e5d22b82526b')) ?>
                <strong><?= adminH(t('a150j_d9048c7b5b46')) ?></strong>
            </p>
        </section>

        <?php if ($message !== ''): ?>
            <div class="alert alert-success" role="status">
                <strong><?= htmlspecialchars($message) ?></strong>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error" role="alert">
                <strong><?= htmlspecialchars($error) ?></strong>
            </div>
        <?php endif; ?>

        <?php if (empty($pages)): ?>
            <p class="card text-muted"><?= adminH(t('a150j_b3f713c828d4')) ?></p>
        <?php endif; ?>

        <?php foreach ($pages as $page): ?>
            <section class="card">
                <h2><?= htmlspecialchars($page['title']) ?></h2>
                <p class="text-muted">
                    <?= adminH(t('a150j_a7c0b8328e8f')) ?> <strong><?= htmlspecialchars(adminEnumLabel($page['page_key'])) ?></strong>
                </p>

                <form method="POST">
<input type="hidden" name="lang" value="<?= adminH(currentLanguage()) ?>">
                    <input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>">

                    <div>
                        <label for="title_<?= (int)$page['id'] ?>"><?= adminH(t('a150j_91ac8dd7c3e5')) ?></label>
                        <input
                            type="text"
                            id="title_<?= (int)$page['id'] ?>"
                            name="title"
                            value="<?= htmlspecialchars($page['title']) ?>"
                            maxlength="150"
                            size="60"
                            required
                        >
                    </div>

                    <div>
                        <label for="content_<?= (int)$page['id'] ?>"><?= adminH(t('a150j_fce9c5d4e03b')) ?></label>
                        <textarea
                            id="content_<?= (int)$page['id'] ?>"
                            name="content"
                            rows="15"
                            cols="100"
                            required
                        ><?= htmlspecialchars($page['content']) ?></textarea>
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn">
                            <?= adminH(t('save')) ?> <?= htmlspecialchars($page['title']) ?>
                        </button>
                    </div>
                </form>

                <p class="text-muted">
                    <small><?= adminH(t('h150_last_updated')) ?> <?= htmlspecialchars($page['updated_at']) ?></small>
                </p>
            </section>
        <?php endforeach; ?>
    </main>

    <nav class="workflow-navigation" aria-label="<?= adminH(t('a150j_ddf3daff4f22')) ?>">
        <div class="actions">
            <a href="index.php?lang=<?= adminH(currentLanguage()) ?>" class="btn btn-secondary"><?= adminH(t('a150j_8b361d792dc3')) ?></a>
            <a href="manage_content.php?lang=<?= adminH(currentLanguage()) ?>" class="btn"><?= adminH(t('h150_next_course_content')) ?></a>
        </div>
    </nav>

    <footer>
        <p class="actions">
            <a href="../index.php?lang=<?= adminH(currentLanguage()) ?>" class="btn btn-secondary"><?= adminH(t('p150i_ad2638a9d1dd')) ?></a>
            <a href="logout.php?lang=<?= adminH(currentLanguage()) ?>" class="btn btn-secondary"><?= adminH(t('h150_logout')) ?></a>
        </p>
    </footer>
</div>
</body>
</html>