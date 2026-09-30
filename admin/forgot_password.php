<?php
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/admin_language.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__.'/../includes/account_security.php';
ux_csrf_check();

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once '../config/database.php';


$message = '';
$error = '';
$developmentResetLink = '';
$username = '';


// ======================================================
// ALREADY LOGGED IN
// ======================================================

if (isset($_SESSION['admin_id'])) {

    header('Location: index.php?lang=' . rawurlencode(currentLanguage()));
    exit;
}


// ======================================================
// PROCESS FORGOT PASSWORD REQUEST
// ======================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim(
        $_POST['username'] ?? ''
    );


    // --------------------------------------------------
    // VALIDATE USERNAME
    // --------------------------------------------------

    if ($username === '') {

        $error =
            t('a150j_10dea516fb2b');

    } else {

        // --------------------------------------------------
        // FIND ADMIN
        // --------------------------------------------------

        $stmt = $pdo->prepare(
            "SELECT
                id,
                username

             FROM admins

             WHERE username = ?

             LIMIT 1"
        );

        $stmt->execute([
            $username
        ]);

        $admin =
            $stmt->fetch(PDO::FETCH_ASSOC);


        /*
         * Always display the same message whether
         * the username exists or not.
         *
         * This prevents account enumeration.
         */
        $message =
            t('a150j_8855d54dc16a');


        // --------------------------------------------------
        // ADMIN EXISTS
        // --------------------------------------------------

        if ($admin) {

            $adminId =
                (int)$admin['id'];


            // ----------------------------------------------
            // INVALIDATE PREVIOUS UNUSED ADMIN TOKENS
            // ----------------------------------------------

            $stmt = $pdo->prepare(
                "UPDATE password_reset_tokens

                 SET used_at = NOW()

                 WHERE account_type = 'admin'
                   AND account_id = ?
                   AND used_at IS NULL"
            );

            $stmt->execute([
                $adminId
            ]);


            // ----------------------------------------------
            // GENERATE SECURE TOKEN
            // ----------------------------------------------

            $token =
                bin2hex(
                    random_bytes(32)
                );


            /*
             * Store only the SHA-256 hash.
             *
             * The original token is used in the URL,
             * but is never stored directly in the DB.
             */
            $tokenHash =
                hash(
                    'sha256',
                    $token
                );


            // ----------------------------------------------
            // EXPIRE AFTER 30 MINUTES
            // ----------------------------------------------

            $expiresAt =
                date(
                    'Y-m-d H:i:s',
                    time() + (30 * 60)
                );


            // ----------------------------------------------
            // SAVE TOKEN
            // ----------------------------------------------

            $stmt = $pdo->prepare(
                "INSERT INTO password_reset_tokens
                (
                    account_type,
                    account_id,
                    token_hash,
                    expires_at
                )

                VALUES
                (
                    'admin',
                    ?,
                    ?,
                    ?
                )"
            );

            $stmt->execute([
                $adminId,
                $tokenHash,
                $expiresAt
            ]);


            // ----------------------------------------------
            // DEVELOPMENT RESET LINK
            // ----------------------------------------------
            //
            // Later this link can be sent by email.
            // For development we display it directly.
            // ----------------------------------------------

            $developmentResetLink =
                'reset_password.php?lang=' . rawurlencode(currentLanguage()) . '&token='
                . urlencode($token);
        }
    }
}

?>
<!DOCTYPE html>
<html lang="<?= adminH(htmlLanguage()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= adminH(t('a150j_7f5075eef01c')) ?> | <?= adminH(t('p150i_7b3acab7d31d')) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body.admin-reset-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
        }
        .admin-reset-page .auth-shell {
            width: 100%;
            max-width: 540px;
            margin: auto;
        }
        .admin-reset-page .auth-card {
            padding: 0;
            margin: 0;
            overflow: hidden;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            box-shadow: 0 12px 36px rgba(17, 24, 39, 0.08);
        }
        .admin-reset-page .auth-header {
            margin: 0;
            padding: 30px 32px;
            border-radius: 0;
        }
        .admin-reset-page .auth-header h1 {
            margin: 0 0 8px;
            font-size: clamp(22px, 5vw, 28px);
            line-height: 1.3;
        }
        .admin-reset-page .auth-header p { font-size: 14px; }
        .admin-reset-page .auth-content { padding: 30px 32px; }
        .admin-reset-page .auth-content h2 {
            margin: 0 0 10px;
            font-size: 24px;
            line-height: 1.35;
        }
        .admin-reset-page .auth-intro {
            margin: 0 0 24px;
            color: #4b5563;
        }
        .admin-reset-page .alert { overflow-wrap: anywhere; }
        .admin-reset-page .alert strong { display: block; margin-bottom: 4px; }
        .admin-reset-page .alert p { margin: 0; }
        .admin-reset-page input[type="text"] {
            min-height: 48px;
            margin-bottom: 0;
            font-size: 16px;
        }
        .admin-reset-page .field-help {
            margin: 8px 0 20px;
            color: #4b5563;
            font-size: 14px;
        }
        .admin-reset-page .reset-submit {
            width: 100%;
            min-height: 48px;
            margin: 0;
            padding: 12px 16px;
            font-size: 16px;
            white-space: normal;
        }
        .admin-reset-page .expiry-note {
            margin: 14px 0 0;
            text-align: center;
            color: #4b5563;
            font-size: 14px;
        }
        .admin-reset-page .development-panel {
            margin: 26px 0 0;
            padding: 20px;
            border: 1px solid #fde68a;
            border-radius: 10px;
            background: #fffbeb;
            color: #78350f;
            overflow-wrap: anywhere;
        }
        .admin-reset-page .development-panel h3 {
            margin: 0 0 8px;
            color: #78350f;
            font-size: 17px;
        }
        .admin-reset-page .development-panel p { margin: 0 0 12px; }
        .admin-reset-page .development-panel p:last-child { margin-bottom: 0; }
        .admin-reset-page .development-panel a {
            display: inline-block;
            padding: 8px 0;
            font-weight: 600;
            text-decoration: underline;
        }
        .admin-reset-page .auth-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px 20px;
            flex-wrap: wrap;
            padding: 16px 32px;
            border-top: 1px solid #e5e7eb;
            background: #f9fafb;
            font-size: 14px;
        }
        .admin-reset-page .auth-footer a {
            display: inline-flex;
            align-items: center;
            min-height: 44px;
        }
        .admin-reset-page a:focus-visible,
        .admin-reset-page button:focus-visible,
        .admin-reset-page input:focus-visible {
            outline: 3px solid #2563eb;
            outline-offset: 3px;
        }
        @media (max-width: 480px) {
            body.admin-reset-page { padding: 20px 12px; }
            .admin-reset-page .auth-header,
            .admin-reset-page .auth-content { padding: 24px 20px; }
            .admin-reset-page .auth-footer { padding: 12px 20px; }
            .admin-reset-page .development-panel { padding: 16px; }
        }
    </style>
<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>
<body class="admin-reset-page">
<?php require __DIR__ . '/admin_language_switcher.php'; ?>
    <div class="auth-shell">
        <div class="card auth-card">
            <header class="admin-header auth-header">
                <h1><?= adminH(t('p150i_7b3acab7d31d')) ?></h1>
                <p><?= adminH(t('a150j_6d3f0137464f')) ?></p>
            </header>

            <main class="auth-content" aria-labelledby="reset-heading">
                <h2 id="reset-heading"><?= adminH(t('forgot_password_title')) ?></h2>
                <p class="auth-intro">
                    <?= adminH(t('a150j_6ceb8ceb6c8e')) ?>
                </p>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-error" id="username-error" role="alert">
                        <strong><?= adminH(t('a150j_bbefe4b04144')) ?></strong>
                        <p><?= htmlspecialchars($error) ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($message !== ''): ?>
                    <div class="alert alert-success" role="status">
                        <strong><?= adminH(t('request_received')) ?></strong>
                        <p><?= htmlspecialchars($message) ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST"><?php ux_csrf_field(); ?>
<input type="hidden" name="lang" value="<?= adminH(currentLanguage()) ?>">
                    <label for="username"><?= adminH(t('a150j_8ec7909703a5')) ?></label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        value="<?= htmlspecialchars($username) ?>"
                        maxlength="255"
                        autocomplete="username"
                        aria-describedby="username-help<?= $error !== '' ? ' username-error' : '' ?>"
                        <?php if ($error !== ''): ?>aria-invalid="true"<?php endif; ?>
                        required
                    >
                    <p class="field-help" id="username-help">
                        <?= adminH(t('a150j_cf4f8529905e')) ?>
                    </p>
                    <button class="reset-submit" type="submit"><?= adminH(t('request_password_reset')) ?></button>
                    <p class="expiry-note"><?= adminH(t('a150j_0a559b70d471')) ?></p>
                </form>

                <?php if ($developmentResetLink !== ''): ?>
                    <section class="development-panel" aria-labelledby="development-heading">
                        <h3 id="development-heading"><?= adminH(t('development_testing')) ?></h3>
                        <p>
                            <?= adminH(t('a150j_5649e4a97be4')) ?>
                        </p>
                        <p>
                            <a href="<?= htmlspecialchars($developmentResetLink) ?>">
                                <?= adminH(t('p150i_c5112bebfc22')) ?>
                            </a>
                        </p>
                        <p>
                            <small>
                                <?= adminH(t('development_reset_warning')) ?>
                            </small>
                        </p>
                    </section>
                <?php endif; ?>
            </main>

            <footer>
                <nav class="auth-footer" aria-label="<?= adminH(t('account_navigation')) ?>">
                    <a href="login.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('a150j_ec2b3c3d6aad')) ?></a>
                    <a href="../index.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('homepage')) ?></a>
                </nav>
            </footer>
        </div>
    </div>
</body>
</html>