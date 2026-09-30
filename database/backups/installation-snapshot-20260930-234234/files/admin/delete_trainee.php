<?php
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/admin_language.php';

require_once 'auth.php';
require_once '../config/database.php';

// =====================================
// GET TRAINEE ID
// =====================================

$traineeId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$traineeId) {
    die(t('a150j_2c18b0d14a06'));
}

// =====================================
// GET TRAINEE
// =====================================

$stmt = $pdo->prepare(
    "SELECT id, first_name, last_name, email
     FROM trainees
     WHERE id = ?
     LIMIT 1"
);
$stmt->execute([$traineeId]);
$trainee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trainee) {
    die(t('h150_trainee_not_found'));
}

// =====================================
// COUNT ATTEMPTS
// =====================================

$stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM attempts
     WHERE trainee_id = ?"
);
$stmt->execute([$traineeId]);
$attemptCount = (int)$stmt->fetchColumn();

$error = '';

// =====================================
// DELETE CONFIRMATION
// =====================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['confirm_delete'])
) {
    try {
        $pdo->beginTransaction();

        /*
         * Delete selected choices belonging
         * to responses from this trainee.
         */
        $stmt = $pdo->prepare(
            "DELETE rc
             FROM response_choices rc
             INNER JOIN responses r
                ON r.id = rc.response_id
             INNER JOIN attempts a
                ON a.id = r.attempt_id
             WHERE a.trainee_id = ?"
        );
        $stmt->execute([$traineeId]);

        /* Delete responses. */
        $stmt = $pdo->prepare(
            "DELETE r
             FROM responses r
             INNER JOIN attempts a
                ON a.id = r.attempt_id
             WHERE a.trainee_id = ?"
        );
        $stmt->execute([$traineeId]);

        /* Delete attempts. */
        $stmt = $pdo->prepare(
            "DELETE FROM attempts
             WHERE trainee_id = ?"
        );
        $stmt->execute([$traineeId]);

        /* Finally delete trainee. */
        $stmt = $pdo->prepare(
            "DELETE FROM trainees
             WHERE id = ?"
        );
        $stmt->execute([$traineeId]);

        $pdo->commit();

        /* Return to trainee management. */
        header('Location: results.php?deleted=1');
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = t('a150j_810fb5f53f7b') . ' ' . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="<?= adminH(htmlLanguage()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= adminH(t('a150j_65391c355b4a')) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .delete-trainee-page .card,
        .delete-trainee-page .alert {
            overflow-wrap: anywhere;
        }

        .delete-trainee-page .actions,
        .delete-trainee-page .workflow-navigation {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem;
        }

        .delete-trainee-page .workflow-navigation {
            margin: 1.5rem 0;
        }

        .delete-trainee-page .btn {
            white-space: normal;
            text-align: center;
        }

        @media (max-width: 600px) {
            .delete-trainee-page .actions .btn {
                box-sizing: border-box;
                width: 100%;
            }
        }
    </style>
<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>
<body>
<?php require __DIR__ . '/admin_language_switcher.php'; ?>
<div class="container delete-trainee-page">
    <header class="admin-header">
        <h1><?= adminH(t('p150i_7b3acab7d31d')) ?></h1>
        <p class="text-muted">
            <?= adminH(t('h150_logged_in_as')) ?>
            <strong><?= htmlspecialchars($_SESSION['admin_username'] ?? t('admin')) ?></strong>
        </p>
    </header>

    <?php require 'admin_nav.php'; ?>

    <nav class="workflow-navigation" aria-label="<?= adminH(t('back_navigation')) ?>">
        <a
            class="btn btn-secondary"
            href="trainee_details.php?lang=<?= adminH(currentLanguage()) ?>&amp;id=<?= (int)$traineeId ?>"
        >
            <?= adminH(t('a150j_88fff108e20a')) ?>
        </a>
        <a class="btn btn-secondary" href="results.php?lang=<?= adminH(currentLanguage()) ?>">
            <?= adminH(t('a150j_709c4d89c475')) ?>
        </a>
    </nav>

    <main>
        <h2><?= adminH(t('a150j_65391c355b4a')) ?></h2>
        <p class="text-muted">
            <?= adminH(t('a150j_39e9591761c7')) ?>
        </p>

        <?php if ($error): ?>
            <div class="alert alert-error" role="alert">
                <strong><?= htmlspecialchars($error) ?></strong>
            </div>
        <?php endif; ?>

        <section class="card" aria-labelledby="trainee-heading">
            <h3 id="trainee-heading">
                <?= htmlspecialchars($trainee['first_name']) ?>
                <?= htmlspecialchars($trainee['last_name']) ?>
            </h3>

            <p>
                <span class="text-muted"><?= adminH(t('p150i_5518894aaecc')) ?></span>
                <strong><?= (int)$trainee['id'] ?></strong>
            </p>
            <p>
                <span class="text-muted"><?= adminH(t('h150_email')) ?></span>
                <strong><?= htmlspecialchars($trainee['email']) ?></strong>
            </p>
            <p>
                <span class="text-muted"><?= adminH(t('a150j_143cbf93f4e0')) ?></span>
                <strong><?= $attemptCount ?></strong>
            </p>
        </section>

        <section class="card danger-zone" aria-labelledby="deletion-heading">
            <h3 id="deletion-heading"><?= adminH(t('a150j_39971381ffeb')) ?></h3>

            <div class="alert alert-error" id="deletion-warning">
                <p><strong><?= adminH(t('a150j_3d6a45267218')) ?></strong></p>
                <p>
                    <?= adminH(t('a150j_f4bbcb100b42')) ?>
                </p>

                <?php if ($attemptCount > 0): ?>
                    <p>
                        <strong><?= adminH(t('a150j_63ea82367063')) ?></strong>
                        <?= adminH(t('a150j_1a92d19ec497')) ?>
                    </p>
                    <p>
                        <?= adminH(t('a150j_351abcb54f06')) ?>
                    </p>
                <?php else: ?>
                    <p><?= adminH(t('a150j_e98c59ad0fd6')) ?></p>
                <?php endif; ?>
            </div>

            <form method="POST" aria-describedby="deletion-warning">
<input type="hidden" name="lang" value="<?= adminH(currentLanguage()) ?>">
                <div class="actions">
                    <button
                        class="btn btn-danger"
                        type="submit"
                        name="confirm_delete"
                        value="1"
                    >
                        <?= adminH(t('a150j_20e64c77f993')) ?>
                    </button>
                    <a class="btn btn-secondary" href="results.php?lang=<?= adminH(currentLanguage()) ?>">
                        <?= adminH(t('cancel')) ?>
                    </a>
                </div>
            </form>
        </section>
    </main>

    <nav class="workflow-navigation" aria-label="<?= adminH(t('a150j_d4e8cd499bb2')) ?>">
        <a href="trainee_details.php?lang=<?= adminH(currentLanguage()) ?>&amp;id=<?= (int)$traineeId ?>">
            <?= adminH(t('a150j_6ea4d66804ca')) ?>
        </a>
        <a href="results.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('a150j_709c4d89c475')) ?></a>
        <a href="index.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('admin_dashboard_link')) ?></a>
        <a href="../index.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('p150i_ad2638a9d1dd')) ?></a>
        <a href="logout.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('h150_logout')) ?></a>
    </nav>
</div>
</body>
</html>