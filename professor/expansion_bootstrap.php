<?php
require_once __DIR__ . '/professor_language.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/expansion_security.php';
header('Cache-Control: no-store');
if (empty($_SESSION['professor_id'])) {
    header('Location: login.php?lang=' . rawurlencode(currentLanguage()));
    exit;
}
$professorId = px_id($_SESSION, 'professor_id');
$stmt = $pdo->prepare('SELECT id, first_name, last_name, username, is_active FROM professors WHERE id = ? AND is_active = 1');
$stmt->execute([$professorId]);
$professor = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$professor) {
    unset($_SESSION['professor_id'], $_SESSION['professor_name'], $_SESSION['professor_username']);
    px_deny();
}
$_SESSION['professor_name'] = trim($professor['first_name'] . ' ' . $professor['last_name']);
$_SESSION['professor_username'] = $professor['username'];
px_csrf_check();

function px_row(string $sql, array $params): ?array {
    global $pdo;
    $s = $pdo->prepare($sql);
    $s->execute($params);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}
function px_allowed(int $theme, ?int $chapter): bool {
    global $professorId;
    return px_row('SELECT id FROM professor_content_assignments WHERE professor_id = ? AND theme_id = ? AND (chapter_id IS NULL OR chapter_id = ?) LIMIT 1', [$professorId, $theme, $chapter]) !== null;
}
// All identifiers passed here are hard-coded by the server, never supplied by a browser.
function px_scope_sql(string $table, string $alias): string {
    global $professorId;
    if (!in_array($table, ['themes','chapters','lessons','topics','paragraphs','questions'], true) ||
        !preg_match('/^[a-z]+$/D', $alias)) throw new LogicException('Invalid scope');
    $grant = 'pa.professor_id = ' . (int)$professorId;
    if ($table === 'themes') return "EXISTS (SELECT 1 FROM professor_content_assignments pa WHERE $grant AND pa.theme_id = $alias.id)";
    if ($table === 'questions') return "EXISTS (SELECT 1 FROM professor_content_assignments pa WHERE $grant AND pa.theme_id = $alias.theme_id AND (pa.chapter_id IS NULL OR pa.chapter_id = $alias.chapter_id))";
    $joins = '';
    $condition = "pc.id = $alias.id";
    if ($table === 'lessons') $condition = "pc.id = $alias.chapter_id";
    if ($table === 'topics') {
        $joins = ' JOIN lessons pl ON pl.chapter_id = pc.id';
        $condition = "pl.id = $alias.lesson_id";
    }
    if ($table === 'paragraphs') {
        $joins = ' JOIN lessons pl ON pl.chapter_id = pc.id JOIN topics pt ON pt.lesson_id = pl.id';
        $condition = "pt.id = $alias.topic_id";
    }
    return "EXISTS (SELECT 1 FROM chapters pc $joins JOIN professor_content_assignments pa ON pa.theme_id = pc.theme_id AND (pa.chapter_id IS NULL OR pa.chapter_id = pc.id) WHERE $grant AND $condition)";
}
function px_entity(string $table, int $id): array {
    $scope = px_scope_sql($table, $table);
    $row = px_row("SELECT * FROM $table WHERE id = ? AND $scope", [$id]);
    if (!$row) px_deny();
    return $row;
}
function px_question_destination(): void {
    $theme = px_id($_POST, 'theme_id');
    $chapter = px_id($_POST, 'chapter_id', true);
    if (!px_allowed($theme, $chapter)) px_deny();
    $previous = $theme;
    foreach (['chapters'=>['chapter_id','theme_id'], 'lessons'=>['lesson_id','chapter_id'], 'topics'=>['topic_id','lesson_id'], 'paragraphs'=>['paragraph_id','topic_id']] as $table=>$keys) {
        $id = px_id($_POST, $keys[0], true);
        if ($id !== null) {
            $row = px_entity($table, $id);
            if ($previous === null || (int)$row[$keys[1]] !== $previous) px_deny();
        }
        $previous = $id;
    }
}
function px_unused_question(int $id): void {
    if (px_row('SELECT id FROM responses WHERE question_id = ? LIMIT 1', [$id])) {
        http_response_code(409);
        exit(t('p150i_fc5e5ef5833f'));
    }
}
function px_guard(string $page): void {
    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET','POST'], true)) px_deny(405);
    foreach ($_POST as $key => $value) {
        if (in_array($key, ['choices','correct_choices'], true)) {
            if (!is_array($value)) px_deny(400);
            foreach ($value as $item) if (!is_scalar($item)) px_deny(400);
        } elseif (!is_scalar($value)) px_deny(400);
    }
    if ($page === 'edit_question.php') {
        $id = px_id($_GET, 'id');
        px_entity('questions', $id);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            px_unused_question($id);
            px_question_destination();
        }
        return;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $actions = $page === 'questions.php'
        ? ['create_question','delete_question','toggle_question']
        : ['create_chapter','create_lesson','create_topic','create_paragraph','update_paragraph'];
    $chosen = array_values(array_filter($actions, static fn($a) => isset($_POST[$a])));
    if (count($chosen) !== 1) px_deny(400);
    switch ($chosen[0]) {
        case 'create_chapter':
            if (!px_allowed(px_id($_POST, 'theme_id'), null)) px_deny();
            break;
        case 'create_lesson': px_entity('chapters', px_id($_POST, 'chapter_id')); break;
        case 'create_topic': px_entity('lessons', px_id($_POST, 'lesson_id')); break;
        case 'create_paragraph': px_entity('topics', px_id($_POST, 'topic_id')); break;
        case 'update_paragraph':
            $row = px_entity('paragraphs', px_id($_POST, 'paragraph_id'));
            px_entity('topics', px_id($_POST, 'topic_id'));
            // Keep hierarchy of existing questions consistent: no reparenting here.
            if ((int)$row['topic_id'] !== px_id($_POST, 'topic_id')) px_deny();
            break;
        case 'create_question': px_question_destination(); break;
        case 'delete_question':
            px_entity('questions', px_id($_POST, 'question_id'));
            px_unused_question(px_id($_POST, 'question_id'));
            break;
        case 'toggle_question': px_entity('questions', px_id($_POST, 'question_id')); break;
    }
}
