<?php
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/admin_language.php';

require_once 'auth.php';
require_once __DIR__.'/../includes/account_security.php';
ux_csrf_check();
require_once '../config/database.php';

$message = '';
$error = '';

// =====================================
// UPDATE LEVEL
// =====================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $levelId = filter_input(
        INPUT_POST,
        'level_id',
        FILTER_VALIDATE_INT
    );

    $name = trim($_POST['name'] ?? '');

    $minPercentage = filter_input(
        INPUT_POST,
        'min_percentage',
        FILTER_VALIDATE_FLOAT
    );

    $maxPercentage = filter_input(
        INPUT_POST,
        'max_percentage',
        FILTER_VALIDATE_FLOAT
    );

    if (!$levelId) {
        $error = t('a150j_db264bdd15a4');
    } elseif ($name === '') {
        $error = t('a150j_f3b4c432dae8');
    } elseif (
        $minPercentage === false ||
        $maxPercentage === false
    ) {
        $error = t('a150j_32149519b8ad');
    } elseif (
        $minPercentage < 0 ||
        $maxPercentage > 100
    ) {
        $error = t('a150j_81a1b8cebd99');
    } elseif ($minPercentage > $maxPercentage) {
        $error =
            t('a150j_266ae58e0b29');
    } else {
        // Check whether this range overlaps another level.
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM positioning_levels
             WHERE id != ?
               AND min_percentage <= ?
               AND max_percentage >= ?"
        );

        $stmt->execute([
            $levelId,
            $maxPercentage,
            $minPercentage
        ]);

        $overlapCount = (int)$stmt->fetchColumn();

        if ($overlapCount > 0) {
            $error =
                t('a150j_78822c08d106');
        } else {
            $stmt = $pdo->prepare(
                "UPDATE positioning_levels
                 SET
                    name = ?,
                    min_percentage = ?,
                    max_percentage = ?
                 WHERE id = ?"
            );

            $stmt->execute([
                $name,
                $minPercentage,
                $maxPercentage,
                $levelId
            ]);

            header('Location: levels.php?updated=1');
            exit;
        }
    }
}

if (isset($_GET['updated'])) {
    $message = t('a150j_d9752930efd3');
}

// =====================================
// GET LEVELS
// =====================================

$stmt = $pdo->query(
    "SELECT
        id,
        name,
        min_percentage,
        max_percentage,
        display_order
     FROM positioning_levels
     ORDER BY display_order ASC"
);

$levels = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="<?= adminH(htmlLanguage()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= adminH(t('a150j_41b93634f1b7')) ?></title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .levels-page .level-fields {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .levels-page .level-field {
            min-width: 0;
        }

        .levels-page .level-field label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .levels-page .level-field input {
            box-sizing: border-box;
            width: 100%;
            max-width: 100%;
            margin-bottom: 0;
        }

        .levels-page .card {
            margin-bottom: 1.5rem;
            min-width: 0;
        }

        .levels-page .card h2,
        .levels-page .card h3 {
            margin-top: 0;
            overflow-wrap: anywhere;
        }

        .levels-page .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .levels-page .scale-table-wrapper {
            max-width: 100%;
            overflow-x: auto;
        }

        .levels-page .scale-table {
            width: 100%;
            border-collapse: collapse;
        }

        .levels-page .scale-table th,
        .levels-page .scale-table td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: top;
        }

        .levels-page .scale-table thead th {
            background: #f8fafc;
            color: #334155;
            font-weight: 600;
        }

        .levels-page .scale-table tbody th {
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .levels-page .scale-table th:not(:first-child),
        .levels-page .scale-table td:not(:first-child) {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .levels-page .scale-table tbody tr:last-child th,
        .levels-page .scale-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .levels-page .workflow-navigation .actions + .actions {
            margin-top: 0.75rem;
        }

        @media (max-width: 640px) {
            .levels-page .level-fields {
                grid-template-columns: 1fr;
            }

            .levels-page .actions .btn {
                box-sizing: border-box;
                width: 100%;
                text-align: center;
                white-space: normal;
            }

            .levels-page .scale-table th,
            .levels-page .scale-table td {
                padding: 0.75rem 0.5rem;
            }
        }
    </style>
<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>
<body>
<?php require __DIR__ . '/admin_language_switcher.php'; ?>
<div class="container levels-page">

    <header class="admin-header">
        <h1><?= adminH(t('p150i_7b3acab7d31d')) ?></h1>
        <p class="text-muted">
            <?= adminH(t('h150_logged_in_as')) ?>
            <strong><?= htmlspecialchars($_SESSION['admin_username'] ?? t('admin')) ?></strong>
        </p>
    </header>

    <?php require 'admin_nav.php'; ?>

    <main>
        <section class="card" aria-labelledby="levels-heading">
            <h2 id="levels-heading"><?= adminH(t('a150j_41b93634f1b7')) ?></h2>
            <p>
                <?= adminH(t('a150j_77b903750613')) ?>
            </p>
            <p class="text-muted">
                <?= adminH(t('a150j_438ca0210a46')) ?>
            </p>
            <p class="text-muted">
                <?= adminH(t('a150j_e40ac14776ad')) ?>
            </p>
        </section>

        <?php if ($message): ?>
            <div class="alert alert-success" role="status">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php foreach ($levels as $level): ?>
            <section
                class="card"
                aria-labelledby="level-heading-<?= (int)$level['id'] ?>"
            >
                <h3 id="level-heading-<?= (int)$level['id'] ?>">
                    <?= htmlspecialchars($level['name']) ?>
                </h3>

                <form method="POST"><?php ux_csrf_field(); ?><input type="hidden" name="lang" value="<?= adminH(currentLanguage()) ?>">

                    <input
                        type="hidden"
                        name="level_id"
                        value="<?= (int)$level['id'] ?>"
                    >

                    <div class="level-fields">
                        <div class="level-field">
                            <label for="name_<?= (int)$level['id'] ?>">
                                <?= adminH(t('a150j_2a2335289762')) ?>
                            </label>
                            <input
                                type="text"
                                id="name_<?= (int)$level['id'] ?>"
                                name="name"
                                value="<?= htmlspecialchars($level['name']) ?>"
                                required
                            >
                        </div>

                        <div class="level-field">
                            <label for="min_percentage_<?= (int)$level['id'] ?>">
                                <?= adminH(t('a150j_c6a802b3b853')) ?>
                            </label>
                            <input
                                type="number"
                                id="min_percentage_<?= (int)$level['id'] ?>"
                                name="min_percentage"
                                min="0"
                                max="100"
                                step="0.01"
                                value="<?= htmlspecialchars($level['min_percentage']) ?>"
                                required
                            >
                        </div>

                        <div class="level-field">
                            <label for="max_percentage_<?= (int)$level['id'] ?>">
                                <?= adminH(t('a150j_45aa3e1fdf32')) ?>
                            </label>
                            <input
                                type="number"
                                id="max_percentage_<?= (int)$level['id'] ?>"
                                name="max_percentage"
                                min="0"
                                max="100"
                                step="0.01"
                                value="<?= htmlspecialchars($level['max_percentage']) ?>"
                                required
                            >
                        </div>
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn">
                            <?= adminH(t('p150i_35322b5bb5a2')) ?>
                        </button>
                    </div>
                </form>
            </section>
        <?php endforeach; ?>

        <section class="card" aria-labelledby="current-scale-heading">
            <h2 id="current-scale-heading"><?= adminH(t('a150j_a0ced4b23953')) ?></h2>
            <p class="text-muted">
                <?= adminH(t('a150j_7559d0486dd1')) ?>
            </p>

            <div
                class="scale-table-wrapper"
                role="region"
                aria-labelledby="current-scale-heading"
                tabindex="0"
            >
                <table
                    class="scale-table"
                    aria-labelledby="current-scale-heading"
                >
                    <thead>
                        <tr>
                            <th scope="col"><?= adminH(t('h150_level')) ?></th>
                            <th scope="col"><?= adminH(t('a150j_735beee2e390')) ?></th>
                            <th scope="col"><?= adminH(t('h150_maximum')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($levels as $level): ?>
                            <tr>
                                <th scope="row">
                                    <?= htmlspecialchars($level['name']) ?>
                                </th>
                                <td>
                                    <?= number_format((float)$level['min_percentage'], 2) ?>%
                                </td>
                                <td>
                                    <?= number_format((float)$level['max_percentage'], 2) ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <nav class="workflow-navigation" aria-label="<?= adminH(t('a150j_d4e8cd499bb2')) ?>">
        <div class="actions">
            <a href="results.php?lang=<?= adminH(currentLanguage()) ?>" class="btn btn-secondary">
                <?= adminH(t('a150j_ca2e6e12780d')) ?>
            </a>
        </div>
        <div class="actions">
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