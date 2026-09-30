<?php
require_once __DIR__ . '/professor_language.php';
require_once __DIR__ . '/expansion_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') px_deny(405);
$s = $pdo->prepare('SELECT t.id, t.first_name, t.last_name FROM trainees t WHERE EXISTS (SELECT 1 FROM attempts a JOIN professor_content_assignments p ON p.theme_id=a.theme_id WHERE a.trainee_id=t.id AND p.professor_id=?) ORDER BY t.last_name, t.first_name');
$s->execute([$professorId]);
$trainees = $s->fetchAll(PDO::FETCH_ASSOC);
$traineeId = px_id($_GET, 'trainee_id', true);
$trainee = null;
$rows = [];
if ($traineeId !== null) {
    $trainee = px_row('SELECT t.id, t.first_name, t.last_name FROM trainees t WHERE t.id = ? AND EXISTS (SELECT 1 FROM attempts a JOIN professor_content_assignments p ON p.theme_id=a.theme_id WHERE a.trainee_id=t.id AND p.professor_id=?)', [$traineeId, $professorId]);
    if (!$trainee) px_deny();
    // Status is per existing assignment, not inferred from question answers.
    // Retakes remain separate: completed and pending assignments can coexist.
    $s = $pdo->prepare("SELECT t.id AS theme_id, t.name, t.is_active,
        kinds.passation_type, qa.id AS assignment_id, qa.status, qa.assigned_at, qa.completed_at, qa.attempt_id
        FROM themes t
        CROSS JOIN (SELECT 'initial' AS passation_type UNION ALL SELECT 'final' UNION ALL SELECT 'sortie') kinds
        LEFT JOIN questionnaire_assignments qa ON qa.theme_id = t.id AND qa.trainee_id = ? AND qa.passation_type = kinds.passation_type
        WHERE " . px_scope_sql('themes', 't') . '
        ORDER BY t.display_order, t.name, kinds.passation_type, qa.id DESC');
    $s->execute([$traineeId]);
    $rows = $s->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html><html lang="<?= professorH(htmlLanguage()) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= professorH(t('p150i_441e39a45858')) ?></title><link rel="stylesheet" href="../assets/css/style.css">
<style>td,th{padding:.8rem;text-align:left;border-bottom:1px solid #ddd}.table-wrap{overflow:auto}</style><script src="../assets/js/professor_language.js" defer></script>
<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>
<body><div class="container"><h1><?= professorH(t('trainee_questionnaire_status')) ?></h1><?php require __DIR__ . '/professor_nav.php'; ?>
<form method="GET"><label><?= professorH(t('trainee')) ?> <select name="trainee_id" required><option value=""><?= professorH(t('p150i_9f8bfe2c407e')) ?></option>
<?php foreach ($trainees as $t): ?><option value="<?= (int)$t['id'] ?>" <?= $traineeId === (int)$t['id'] ? 'selected' : '' ?>><?= px_h($t['first_name'].' '.$t['last_name']) ?></option><?php endforeach; ?>
</select></label> <button><?= professorH(t('p150i_8d7c8a95182d')) ?></button></form>
<?php if (!$trainees): ?><p><?= professorH(t('p150i_3ad0d5839aa0')) ?></p><?php endif; ?>
<p><?= professorH(t('p150i_f20ff577a167')) ?></p>
<p><?= professorH(t('p150i_7a0e613f9cd2')) ?></p>
<?php if ($trainee): ?><h2><?= px_h($trainee['first_name'].' '.$trainee['last_name']) ?></h2>
<div class="table-wrap"><table><thead><tr><th><?= professorH(t('theme')) ?></th><th><?= professorH(t('h150_passation')) ?></th><th><?= professorH(t('status')) ?></th><th><?= professorH(t('h150_assigned')) ?></th><th><?= professorH(t('completed')) ?></th><th><?= professorH(t('results')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $row):
    $label = $row['assignment_id'] === null ? t('p150i_13075c233611') : match ($row['status']) {
        'completed'=>t('p150i_39e1f39a6540'), 'pending'=>t('p150i_a78afc306727'), default=>(string)$row['status']
    }; ?>
<tr><td><?= px_h($row['name']) ?><?= !$row['is_active'] ? t('p150i_564316135622') : '' ?></td><td><?= px_h(professorPassationLabel($row['passation_type'])) ?></td>
<td><?= px_h($label) ?><?= $row['assignment_id'] ? ' #'.(int)$row['assignment_id'] : '' ?></td><td><?= px_h($row['assigned_at'] ?? '—') ?></td><td><?= px_h($row['completed_at'] ?? '—') ?></td>
<td><?php if ($row['status'] === 'completed'): ?><a href="trainee_results.php?trainee_id=<?= $traineeId ?>"><?= professorH(t('p150i_badf4e3cf2a4')) ?></a><?php else: ?>—<?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div><?php if (!$rows): ?><p><?= professorH(t('p150i_e1df4836e27a')) ?></p><?php endif; ?><?php endif; ?>
</div></body></html>
