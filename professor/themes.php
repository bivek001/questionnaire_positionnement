<?php
require_once __DIR__ . '/expansion_bootstrap.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!in_array($action, ['create', 'save'], true)) px_deny(400);
    $name = is_string($_POST['name'] ?? null) ? trim($_POST['name']) : '';
    $description = is_string($_POST['description'] ?? null) ? trim($_POST['description']) : '';
    $order = filter_var($_POST['display_order'] ?? null, FILTER_VALIDATE_INT);
    if ($name === '' || mb_strlen($name) > 100 || strlen($description) > 60000 || $order === false) {
        $error = t('ux_invalid_fields');
    } else {
        try {
            $pdo->beginTransaction();
            // Serialize against account deletion/deactivation, then recheck active status.
            $active = px_row('SELECT id FROM professors WHERE id = ? AND is_active = 1 FOR UPDATE', [$professorId]);
            if (!$active) throw new RuntimeException(t('ux_access_denied'));
            if ($action === 'create') {
                $s = $pdo->prepare('INSERT INTO themes (name, description, display_order) VALUES (?, ?, ?)');
                $s->execute([$name, $description, $order]);
                $themeId = (int)$pdo->lastInsertId();
                $s = $pdo->prepare('INSERT INTO professor_content_assignments (professor_id, theme_id, chapter_id) VALUES (?, ?, NULL)');
                $s->execute([$professorId, $themeId]);
            } else {
                $themeId = px_id($_POST, 'theme_id');
                // A chapter grant does not permit editing the shared theme.
                $grant = px_row('SELECT id FROM professor_content_assignments WHERE professor_id = ? AND theme_id = ? AND chapter_id IS NULL FOR UPDATE', [$professorId, $themeId]);
                if (!$grant) { $pdo->rollBack(); px_deny(); }
                $s = $pdo->prepare('UPDATE themes SET name = ?, description = ?, display_order = ? WHERE id = ?');
                $s->execute([$name, $description, $order, $themeId]);
            }
            $pdo->commit();
            header('Location: themes.php?saved=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = t('ux_save_failed');
        }
    }
}
$themes = $pdo->query('SELECT * FROM themes WHERE ' . px_scope_sql('themes', 'themes') . ' ORDER BY display_order, id')->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="<?= px_h(htmlLanguage()) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= px_h(t('themes')) ?></title><link rel="stylesheet" href="../assets/css/style.css"><link rel="stylesheet" href="../assets/css/ui.css"><script src="../assets/js/ui.js" defer></script></head>
<body><div class="container"><?php require __DIR__ . '/professor_nav.php'; ?>
<main id="main-content"><h1><?= px_h(t('themes')) ?></h1>
<p><?= px_h(t('ux_theme_rule')) ?></p>
<?php if ($error): ?><p class="alert alert-error" role="alert"><?= px_h($error) ?></p><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><p class="alert alert-success" role="status"><?= px_h(t('ux_saved')) ?></p><?php endif; ?>
<section class="card"><h2><?= px_h(t('ux_create_theme')) ?></h2>
<form method="post"><?php px_csrf_field(); ?>
<input type="hidden" name="action" value="create">
<label for="theme-name"><?= px_h(t('title')) ?></label><input id="theme-name" name="name" maxlength="100" required value="<?= px_h($name ?? '') ?>">
<label for="theme-description"><?= px_h(t('description')) ?></label><textarea id="theme-description" name="description" rows="4"><?= px_h($description ?? '') ?></textarea>
<label for="theme-order"><?= px_h(t('display_order')) ?></label><input id="theme-order" type="number" name="display_order" value="<?= (int)($order ?? 0) ?>" required>
<button class="btn" type="submit"><?= px_h(t('ux_create_theme')) ?></button></form></section>
<?php if (!$themes): ?><section class="card"><p><?= px_h(t('pkg_no_courses')) ?></p></section><?php endif; ?>
<?php foreach ($themes as $theme): ?>
<section class="card"><h2><?= px_h($theme['name']) ?></h2>
<?php if (px_allowed((int)$theme['id'], null)): ?>
<form method="post"><?php px_csrf_field(); ?><input type="hidden" name="action" value="save"><input type="hidden" name="theme_id" value="<?= (int)$theme['id'] ?>">
<label><?= px_h(t('title')) ?><input name="name" maxlength="100" required value="<?= px_h($theme['name']) ?>"></label>
<label><?= px_h(t('description')) ?><textarea name="description" rows="3"><?= px_h($theme['description']) ?></textarea></label>
<label><?= px_h(t('display_order')) ?><input type="number" name="display_order" required value="<?= (int)$theme['display_order'] ?>"></label>
<button class="btn" type="submit"><?= px_h(t('save')) ?></button> <a class="btn btn-secondary" href="manage_content.php"><?= px_h(t('ux_build_content')) ?></a></form>
<?php else: ?><p class="badge"><?= px_h(t('ux_chapter_access')) ?></p><a class="btn btn-secondary" href="manage_content.php"><?= px_h(t('ux_build_content')) ?></a><?php endif; ?>
</section><?php endforeach; ?></main></div></body></html>
