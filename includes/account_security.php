<?php
// Session must already be started by the role's authentication bootstrap.
function ux_csrf_field(): void {
    if (empty($_SESSION['account_csrf'])) $_SESSION['account_csrf'] = bin2hex(random_bytes(32));
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['account_csrf'], ENT_QUOTES, 'UTF-8') . '">';
}
function ux_csrf_check(): void {
    if (empty($_SESSION['account_csrf'])) $_SESSION['account_csrf'] = bin2hex(random_bytes(32));
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['account_csrf'], $_POST['csrf_token']))) {
        http_response_code(403);
        exit(htmlspecialchars(t('ux_csrf_error'), ENT_QUOTES, 'UTF-8'));
    }
}
function ux_delete_professor(PDO $pdo, int $id): void {
    // Explicitly remove only access records. Refuse schemas that could cascade
    // into content/history, including indirect cascades through assignment rows.
    $allowed = ['professors', 'professor_content_assignments', 'professor_trainee_assignments'];
    $engines = $pdo->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('professors','professor_content_assignments','professor_trainee_assignments')")->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($allowed as $table) if (strtoupper($engines[$table] ?? '') !== 'INNODB') throw new RuntimeException(t('ux_delete_schema'));
    $constraints = $pdo->query('SELECT TABLE_NAME, REFERENCED_TABLE_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($constraints as $fk) {
        if (in_array($fk['REFERENCED_TABLE_NAME'], $allowed, true) && $fk['DELETE_RULE'] === 'CASCADE' && !in_array($fk['TABLE_NAME'], $allowed, true)) throw new RuntimeException(t('ux_delete_schema'));
    }
    $pdo->beginTransaction();
    try {
        $s = $pdo->prepare('SELECT id FROM professors WHERE id = ? FOR UPDATE');
        $s->execute([$id]);
        if (!$s->fetchColumn()) throw new RuntimeException(t('ux_professor_missing'));
        foreach (['professor_content_assignments','professor_trainee_assignments'] as $table) {
            $s = $pdo->prepare("DELETE FROM $table WHERE professor_id = ?");
            $s->execute([$id]);
        }
        $s = $pdo->prepare('DELETE FROM professors WHERE id = ?');
        $s->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
