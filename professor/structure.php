<?php
require_once __DIR__ . '/professor_language.php';
require_once __DIR__ . '/expansion_bootstrap.php';
$definitions = [
    'chapter'=>['chapters','lessons','chapter_id'],
    'lesson'=>['lessons','topics','lesson_id'],
    'topic'=>['topics','paragraphs','topic_id']
];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kind = $_POST['kind'] ?? '';
    $action = $_POST['action'] ?? '';
    if (!is_string($kind) || !isset($definitions[$kind]) || !in_array($action, ['save','delete'], true)) px_deny(400);
    [$table, $childTable, $foreignKey] = $definitions[$kind];
    $id = px_id($_POST, 'id');
    px_entity($table, $id);
    try {
        $pdo->beginTransaction();
        // Lock the parent, then recheck current permission inside the transaction.
        $s = $pdo->prepare("SELECT id FROM $table WHERE id = ? FOR UPDATE");
        $s->execute([$id]);
        px_entity($table, $id);
        if ($action === 'delete') {
            if (px_row("SELECT id FROM $childTable WHERE $foreignKey = ? LIMIT 1", [$id]) ||
                px_row("SELECT id FROM questions WHERE $foreignKey = ? LIMIT 1", [$id])) {
                throw new RuntimeException(t('p150i_08ab842aea28'));
            }
            if ($kind === 'chapter' && px_row('SELECT id FROM professor_content_assignments WHERE chapter_id = ? LIMIT 1', [$id])) {
                throw new RuntimeException(t('p150i_ffcd343a173c'));
            }
            $s = $pdo->prepare("DELETE FROM $table WHERE id = ?");
            $s->execute([$id]);
        } else {
            $title = is_string($_POST['title'] ?? null) ? trim($_POST['title']) : '';
            $order = filter_var($_POST['display_order'] ?? null, FILTER_VALIDATE_INT);
            if ($title === '' || mb_strlen($title) > 255 || $order === false) {
                throw new RuntimeException(t('p150i_ac70d20d7004'));
            }
            // Parent identifiers are immutable; posted parent fields are never used.
            $s = $pdo->prepare("UPDATE $table SET title = ?, display_order = ?, is_active = ? WHERE id = ?");
            $s->execute([$title, $order, isset($_POST['is_active']) ? 1 : 0, $id]);
        }
        $pdo->commit();
        header('Location: structure.php?saved=1');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e instanceof PDOException ? t('p150i_0cfd71f33bb5') : $e->getMessage();
    }
}
?>
<!DOCTYPE html><html lang="<?= professorH(htmlLanguage()) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= professorH(t('p150i_180276edde85')) ?></title><link rel="stylesheet" href="../assets/css/style.css">
<style>.structure-item{padding:1rem;border:1px solid #ddd;margin:1rem 0}.structure-item label{display:inline-block;margin:.5rem} input[type=text]{min-width:240px}</style><script src="../assets/js/professor_language.js" defer></script>
<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>
<body><div class="container"><h1><?= professorH(t('p150i_2d74e5b105ee')) ?></h1><?php require __DIR__ . '/professor_nav.php'; ?>
<p><a class="btn" href="manage_content.php"><?= professorH(t('p150i_bb682a6ee771')) ?></a></p>
<p><?= professorH(t('p150i_4301dd66c677')) ?></p>
<?php if ($error): ?><p role="alert"><?= px_h($error) ?></p><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><p role="status"><?= professorH(t('p150i_e8d74017a1d8')) ?></p><?php endif; ?>
<?php foreach ($definitions as $kind=>[$table]):
    $rows = $pdo->query("SELECT * FROM $table WHERE " . px_scope_sql($table, $table) . ' ORDER BY display_order, id')->fetchAll(PDO::FETCH_ASSOC); ?>
<section><h2><?= professorH(professorStructureLabel($kind)) ?></h2>
<?php if (!$rows): ?><p><?= professorH(t('p150i_09262934547d')) ?></p><?php endif; ?>
<?php foreach ($rows as $row): ?><form method="POST" class="structure-item">
<?php px_csrf_field(); ?>
<input type="hidden" name="kind" value="<?= $kind ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
<label><?= professorH(t('title')) ?> <input type="text" name="title" maxlength="255" required value="<?= px_h($row['title']) ?>"></label>
<label><?= professorH(t('p150i_6be090825c99')) ?> <input type="number" name="display_order" required value="<?= (int)$row['display_order'] ?>"></label>
<label><input type="checkbox" name="is_active" <?= $row['is_active'] ? 'checked' : '' ?>> <?= professorH(t('active')) ?></label>
<button name="action" value="save"><?= professorH(t('save')) ?></button>
<button name="action" value="delete" formnovalidate data-confirm="<?= professorH(t('p150i_f3c14b1ab273')) ?>" onclick="return confirm(this.dataset.confirm)"><?= professorH(t('p150i_60c039a9b8cb')) ?></button>
</form><?php endforeach; ?></section><?php endforeach; ?>
</div></body></html>
