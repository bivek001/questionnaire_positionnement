<?php
require_once __DIR__ . '/professor_language.php';
if (session_status() !== PHP_SESSION_ACTIVE) if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/expansion_security.php';
px_csrf_check();
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
$message = $error = $developmentResetLink = '';
$config = require __DIR__ . '/recovery_config.php';
$localTesting = $config['local_testing'] && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    if (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = t('invalid_email');
    } elseif (!$localTesting && (!filter_var($config['base_url'], FILTER_VALIDATE_URL) ||
        parse_url($config['base_url'], PHP_URL_SCHEME) !== 'https' ||
        !filter_var($config['from'], FILTER_VALIDATE_EMAIL))) {
        $error = t('p150i_3755896bc6d9');
    } else {
        $message = t('p150i_b1f86c0244ed');
        // Session throttling plus an account-wide interval persisted in the token table.
        if (time() - (int)($_SESSION['professor_reset_requested_at'] ?? 0) >= 60) {
            $_SESSION['professor_reset_requested_at'] = time();
            try {
                $pdo->beginTransaction();
                $s = $pdo->prepare('SELECT id, email FROM professors WHERE email = ? AND is_active = 1 LIMIT 1 FOR UPDATE');
                $s->execute([$email]);
                $account = $s->fetch(PDO::FETCH_ASSOC);
                $token = null;
                if ($account) {
                    $s = $pdo->prepare("SELECT id FROM password_reset_tokens WHERE account_type = 'professor' AND account_id = ? AND expires_at > DATE_ADD(NOW(), INTERVAL 29 MINUTE) LIMIT 1");
                    $s->execute([$account['id']]);
                    if (!$s->fetchColumn()) {
                        $s = $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE account_type = 'professor' AND account_id = ? AND used_at IS NULL");
                        $s->execute([$account['id']]);
                        $token = bin2hex(random_bytes(32));
                        $s = $pdo->prepare("INSERT INTO password_reset_tokens (account_type, account_id, token_hash, expires_at) VALUES ('professor', ?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
                        $s->execute([$account['id'], hash('sha256', $token)]);
                    }
                }
                $pdo->commit();
                if ($token !== null) {
                    if ($localTesting) {
                        $developmentResetLink = 'reset_password.php?lang=' . rawurlencode(currentLanguage()) . '&token=' . $token;
                    } else {
                        $link = $config['base_url'] . '/professor/reset_password.php?lang=' . rawurlencode(currentLanguage()) . '&token=' . $token;
                        $sent = @mail($account['email'], t('p150i_140952338501'), t('p150i_reset_email_body', ['link' => $link]), 'From: ' . $config['from']);
                        if (!$sent) error_log('Professor reset email delivery failed. Check mail transport configuration.');
                    }
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Professor password recovery failed. Check database/mail configuration.');
                // Keep the response identical for existing and unknown accounts.
            }
        }
    }
}
