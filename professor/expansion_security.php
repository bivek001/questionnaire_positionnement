<?php
require_once __DIR__ . '/professor_language.php';
// Shared by public recovery pages and authenticated Professor pages.
function px_deny(int $status = 403): never {
    http_response_code($status);
    exit($status === 403 ? t('p150i_278f003df0c2') : t('h150_invalid_request'));
}
function px_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function px_csrf_check(): void {
    if (empty($_SESSION['professor_csrf'])) {
        $_SESSION['professor_csrf'] = bin2hex(random_bytes(32));
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
        (!is_string($_POST['csrf_token'] ?? null) ||
         !hash_equals($_SESSION['professor_csrf'], $_POST['csrf_token']))) {
        px_deny();
    }
}
function px_csrf_field(): void {
    echo '<input type="hidden" name="csrf_token" value="' . px_h($_SESSION['professor_csrf']) . '">';
}
function px_id(array $data, string $key, bool $optional = false): ?int {
    $v = $data[$key] ?? null;
    if ($optional && ($v === null || $v === '')) return null;
    if (!is_scalar($v) || !preg_match('/^[1-9][0-9]*$/D', (string)$v)) px_deny(400);
    $id = filter_var($v, FILTER_VALIDATE_INT);
    if ($id === false) px_deny(400);
    return $id;
}
