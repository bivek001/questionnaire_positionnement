<?php
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/admin_language.php';

require_once 'auth.php';
require_once '../config/database.php';

// FILTERS
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';

$allowedStatuses = ['', 'pending', 'completed'];

if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

// TOTAL NUMBER OF TRAINEES
$stmt = $pdo->query("SELECT COUNT(*) FROM trainees");
$totalTrainees = (int)$stmt->fetchColumn();

// BUILD QUERY
$sql = "
    SELECT
        trainees.id,
        trainees.first_name,
        trainees.last_name,
        trainees.date_of_birth,
        trainees.email,
        COUNT(DISTINCT attempts.id) AS attempt_count,
        MAX(attempts.completed_at) AS last_attempt,
        COUNT(
            DISTINCT CASE
                WHEN questions.question_type = 'open'
                 AND responses.graded_at IS NULL
                THEN responses.id
            END
        ) AS pending_open_answers
    FROM trainees
    LEFT JOIN attempts
        ON trainees.id = attempts.trainee_id
    LEFT JOIN responses
        ON attempts.id = responses.attempt_id
    LEFT JOIN questions
        ON responses.question_id = questions.id
    WHERE 1 = 1
";

$params = [];

// SEARCH FILTER
if ($search !== '') {
    $sql .= "
        AND (
            trainees.first_name LIKE ?
            OR trainees.last_name LIKE ?
            OR trainees.email LIKE ?
            OR CAST(trainees.id AS CHAR) LIKE ?
            OR CONCAT(trainees.first_name, ' ', trainees.last_name) LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

// GROUP RESULTS
$sql .= "
    GROUP BY
        trainees.id,
        trainees.first_name,
        trainees.last_name,
        trainees.date_of_birth,
        trainees.email
";

// STATUS FILTER
if ($status === 'pending') {
    $sql .= " HAVING pending_open_answers > 0 ";
} elseif ($status === 'completed') {
    $sql .= " HAVING pending_open_answers = 0 ";
}

// ORDER RESULTS
$sql .= " ORDER BY trainees.id ASC ";

// EXECUTE QUERY
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$trainees = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="<?= adminH(htmlLanguage()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= adminH(t('a150j_a957eeee7b30')) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .results-page .results-summary {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .results-page .results-total strong {
            display: block;
            font-size: 2rem;
            line-height: 1.2;
        }
        .results-page .results-filters {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) auto;
            align-items: end;
            gap: 1rem;
        }
        .results-page .results-field label {
            display: block;
            margin-bottom: .5rem;
        }
        .results-page .results-field input,
        .results-page .results-field select {
            box-sizing: border-box;
            width: 100%;
            margin-bottom: 0;
        }
        .results-page .results-filters .actions,
        .results-page .results-row-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            margin: 0;
        }
        .results-page .results-table-wrapper {
            max-width: 100%;
            overflow-x: auto;
        }
        .results-page .results-table { width: 100%; }
        .results-page .results-table th,
        .results-page .results-table td { vertical-align: top; }
        .results-page .results-row-actions { min-width: 10rem; }
        .results-page .results-row-actions .btn { white-space: nowrap; }
        .results-page .results-date { white-space: nowrap; }
        .results-page .results-grading-count {
            display: block;
            margin-top: .5rem;
        }
        @media (max-width: 768px) {
            .results-page .results-filters { grid-template-columns: 1fr; }
        }
    </style>
<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>
<body>
<?php require __DIR__ . '/admin_language_switcher.php'; ?>
<div class="container results-page">
    <header class="admin-header">
        <h1><?= adminH(t('p150i_7b3acab7d31d')) ?></h1>
        <p>
            <?= adminH(t('h150_logged_in_as')) ?>
            <strong><?= htmlspecialchars($_SESSION['admin_username'] ?? t('admin'), ENT_QUOTES, 'UTF-8') ?></strong>
        </p>
    </header>

    <?php require 'admin_nav.php'; ?>

    <main>
        <section class="card results-summary" aria-labelledby="results-title">
            <div>
                <h2 id="results-title"><?= adminH(t('trainees_results')) ?></h2>
                <p class="text-muted"><?= adminH(t('a150j_5556d950e0f0')) ?></p>
            </div>
            <p class="results-total">
                <span class="text-muted"><?= adminH(t('a150j_7615892b20e8')) ?></span>
                <strong><?= $totalTrainees ?></strong>
            </p>
        </section>

        <section class="card" aria-labelledby="filters-title">
            <h2 id="filters-title"><?= adminH(t('a150j_fac61ccf1966')) ?></h2>
            <form method="GET" class="results-filters">
<input type="hidden" name="lang" value="<?= adminH(currentLanguage()) ?>">
                <div class="results-field">
                    <label for="search"><?= adminH(t('a150j_15f1fb43e8b4')) ?></label>
                    <input type="text" id="search" name="search"
                           value="<?= htmlspecialchars($search) ?>"
                           placeholder="<?= adminH(t('a150j_d1a293155c0b')) ?>">
                </div>
                <div class="results-field">
                    <label for="status"><?= adminH(t('a150j_33f86c29bf1d')) ?></label>
                    <select id="status" name="status">
                        <option value="" <?= $status === '' ? 'selected' : '' ?>><?= adminH(t('all')) ?></option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>><?= adminH(t('a150j_2b613115fc13')) ?></option>
                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>><?= adminH(t('p150i_dd1c17c43141')) ?></option>
                    </select>
                </div>
                <div class="actions">
                    <button type="submit" class="btn"><?= adminH(t('a150j_638e249f4a15')) ?></button>
                    <a href="results.php?lang=<?= adminH(currentLanguage()) ?>" class="btn btn-secondary"><?= adminH(t('a150j_daee7606b339')) ?></a>
                </div>
            </form>
        </section>

        <section class="card" aria-labelledby="trainees-title">
            <h2 id="trainees-title"><?= adminH(t('p150i_bfcc39a6a126')) ?></h2>

            <?php if (empty($trainees)): ?>
                <p class="text-muted"><?= adminH(t('a150j_e58d6ed211c3')) ?></p>
            <?php else: ?>
                <div class="table-responsive results-table-wrapper" role="region"
                     aria-labelledby="trainees-title" tabindex="0">
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th scope="col"><?= adminH(t('p150i_3843971dcfde')) ?></th>
                                <th scope="col"><?= adminH(t('name')) ?></th>
                                <th scope="col"><?= adminH(t('date_of_birth')) ?></th>
                                <th scope="col"><?= adminH(t('email')) ?></th>
                                <th scope="col"><?= adminH(t('attempts')) ?></th>
                                <th scope="col"><?= adminH(t('a150j_2ca94ca3aa3d')) ?></th>
                                <th scope="col"><?= adminH(t('a150j_fc4a58ddbe4f')) ?></th>
                                <th scope="col"><?= adminH(t('actions')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($trainees as $trainee): ?>
                            <?php $pendingCount = (int)$trainee['pending_open_answers']; ?>
                            <tr>
                                <td><strong><?= (int)$trainee['id'] ?></strong></td>
                                <td>
                                    <?= htmlspecialchars($trainee['first_name']) ?>
                                    <?= htmlspecialchars($trainee['last_name']) ?>
                                </td>
                                <td class="results-date">
                                    <?php if (!empty($trainee['date_of_birth'])): ?>
                                        <?= htmlspecialchars(date('d/m/Y', strtotime($trainee['date_of_birth']))) ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($trainee['email']) ?></td>
                                <td><?= (int)$trainee['attempt_count'] ?></td>
                                <td class="results-date">
                                    <?php if (!empty($trainee['last_attempt'])): ?>
                                        <?= htmlspecialchars(date('d/m/Y H:i', strtotime($trainee['last_attempt']))) ?>
                                    <?php else: ?>
                                        <span class="text-muted"><?= adminH(t('a150j_be88ed46418d')) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($pendingCount > 0): ?>
                                        <span class="status status-inactive"><?= adminH(t('a150j_2b613115fc13')) ?></span>
                                        <span class="text-muted results-grading-count">
                                            <?= $pendingCount ?> <?= adminH(t('a150j_fbac19706934')) ?><?= $pendingCount !== 1 ? 's' : '' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="status status-active"><?= adminH(t('p150i_dd1c17c43141')) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions results-row-actions">
                                        <a class="btn" href="trainee_details.php?lang=<?= adminH(currentLanguage()) ?>&amp;id=<?= (int)$trainee['id'] ?>"><?= adminH(t('a150j_90789c12d073')) ?></a>
                                        <a class="btn btn-secondary" href="edit_trainee.php?lang=<?= adminH(currentLanguage()) ?>&amp;id=<?= (int)$trainee['id'] ?>"><?= adminH(t('edit')) ?></a>
                                        <a class="btn btn-danger" href="delete_trainee.php?lang=<?= adminH(currentLanguage()) ?>&amp;id=<?= (int)$trainee['id'] ?>"><?= adminH(t('delete')) ?></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <nav class="workflow-navigation" aria-label="<?= adminH(t('a150j_d4e8cd499bb2')) ?>">
        <p class="actions">
            <a class="btn btn-secondary" href="assign_questionnaire.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('a150j_ac116df2bdb2')) ?></a>
            <a class="btn" href="levels.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('a150j_c2ddb1602fab')) ?></a>
        </p>
        <p class="actions">
            <a class="btn btn-secondary" href="index.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('admin_dashboard_link')) ?></a>
            <a class="btn btn-secondary" href="../index.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('p150i_ad2638a9d1dd')) ?></a>
            <a class="btn btn-danger" href="logout.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('h150_logout')) ?></a>
        </p>
    </nav>
</div>
</body>
</html>
