<?php
require_once __DIR__ . '/professor_language.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/expansion_security.php';
px_csrf_check();
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
$error = '';
$success = '';
$tokenValid = false;
$tokenRecord = null;
// ======================================================
// GET TOKEN
// ======================================================
if (isset($_GET['token']) && !is_string($_GET['token'])) px_deny(400);
if (isset($_POST['token']) && !is_string($_POST['token'])) px_deny(400);
$token = trim(
    $_GET['token']
    ?? $_POST['token']
    ?? ''
);
// ======================================================
// VALIDATE TOKEN FORMAT
// ======================================================
if (
    $token !== '' &&
    preg_match('/^[a-f0-9]{64}$/', $token)
) {
    $tokenHash =
        hash(
            'sha256',
            $token
        );
    // ==================================================
    // FIND VALID TRAINEE RESET TOKEN
    // ==================================================
    $stmt = $pdo->prepare(
        "SELECT
            id,
            account_id,
            expires_at
         FROM password_reset_tokens
         WHERE account_type = 'professor'
           AND token_hash = ?
           AND used_at IS NULL
           AND expires_at > NOW()
         LIMIT 1"
    );
    $stmt->execute([
        $tokenHash
    ]);
    $tokenRecord =
        $stmt->fetch(PDO::FETCH_ASSOC);
    if ($tokenRecord) {
        // ----------------------------------------------
        // MAKE SURE TRAINEE STILL EXISTS
        // ----------------------------------------------
        $stmt = $pdo->prepare(
            "SELECT id
             FROM professors
             WHERE id = ? AND is_active = 1
             LIMIT 1"
        );
        $stmt->execute([
            (int)$tokenRecord['account_id']
        ]);
        if ($stmt->fetchColumn()) {
            $tokenValid = true;
        }
    }
}
// ======================================================
// PROCESS NEW PASSWORD
// ======================================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $tokenValid
) {
    $password =
        $_POST['password']
        ?? '';
    $passwordConfirmation =
        $_POST['password_confirmation']
        ?? '';
    // --------------------------------------------------
    // VALIDATION
    // --------------------------------------------------
    if (!is_string($password) || !is_string($passwordConfirmation) || strlen($password) > 72) {
        $error = t('p150i_29f4b4aabaa1');
    } elseif ($password === '') {
        $error =
            t('enter_new_password_error');
    } elseif (strlen($password) < 8) {
        $error =
            t('new_password_minimum_error');
    } elseif (
        $password !==
        $passwordConfirmation
    ) {
        $error =
            t('password_mismatch');
    } else {
        try {
            $pdo->beginTransaction();
            // ------------------------------------------
            // LOCK / RECHECK TOKEN
            // ------------------------------------------
            $stmt = $pdo->prepare(
                "SELECT
                    id,
                    account_id
                 FROM password_reset_tokens
                 WHERE id = ?
                   AND account_type = 'professor'
                   AND token_hash = ?
                   AND used_at IS NULL
                   AND expires_at > NOW()
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute([
                (int)$tokenRecord['id'],
                $tokenHash
            ]);
            $lockedToken =
                $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$lockedToken) {
                throw new RuntimeException(
                    t('reset_link_no_longer_valid')
                );
            }
            $professorId =
                (int)$lockedToken['account_id'];
            // ------------------------------------------
            // HASH NEW PASSWORD
            // ------------------------------------------
            $passwordHash =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );
            if ($passwordHash === false) {
                throw new RuntimeException(
                    t('unable_secure_new_password')
                );
            }
            // ------------------------------------------
            // UPDATE TRAINEE PASSWORD
            // ------------------------------------------
            $stmt = $pdo->prepare(
                "UPDATE professors
                 SET password = ?
                 WHERE id = ? AND is_active = 1"
            );
            $stmt->execute([
                $passwordHash,
                $professorId
            ]);
            if ($stmt->rowCount() < 1) {
                /*
                 * rowCount() can theoretically be 0
                 * depending on database behaviour, so
                 * verify the account still exists.
                 */
                $stmt = $pdo->prepare(
                    "SELECT id
                     FROM professors
                     WHERE id = ? AND is_active = 1
                     LIMIT 1"
                );
                $stmt->execute([
                    $professorId
                ]);
                if (!$stmt->fetchColumn()) {
                    throw new RuntimeException(
                        t('p150i_701d8d518286')
                    );
                }
            }
            // ------------------------------------------
            // MARK ALL TRAINEE RESET TOKENS AS USED
            // ------------------------------------------
            $stmt = $pdo->prepare(
                "UPDATE password_reset_tokens
                 SET used_at = NOW()
                 WHERE account_type = 'professor'
                   AND account_id = ?
                   AND used_at IS NULL"
            );
            $stmt->execute([
                $professorId
            ]);
            $pdo->commit();
            $success =
                t('password_changed_successfully');
            /*
             * Hide the reset form after success.
             */
            $tokenValid = false;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error =
                t('password_change_failed');
        }
    }
}
// ======================================================
// INVALID / EXPIRED TOKEN MESSAGE
// ======================================================
if (
    $success === '' &&
    !$tokenValid &&
    $error === ''
) {
    if ($token === '') {
        $error =
            t('no_reset_token');
    } else {
        $error =
            t('invalid_expired_reset_link');
    }
}
?>
<!DOCTYPE html>
<html lang="<?= professorH(htmlLanguage()) ?>">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>
        <?= professorH(t('reset_password')) ?>
    </title>
    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >
<script src="../assets/js/professor_language.js" defer></script>
<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>
<body>
<?php require __DIR__ . '/professor_language_switcher.php'; ?>
<div class="container">
    <!-- ================================================= -->
    <!-- HEADER -->
    <!-- ================================================= -->
    <header>
        <h1>
            <?= professorH(t('reset_password')) ?>
        </h1>
        <p>
            <?= professorH(t('p150i_9c58a81e6741')) ?>
        </p>
    </header>
    <hr>
    <main>
        <section>
            <!-- ========================================= -->
            <!-- SUCCESS -->
            <!-- ========================================= -->
            <?php if ($success !== ''): ?>
                <h2>
                    <?= professorH(t('password_changed')) ?>
                </h2>
                <p>
                    <strong>
                        <?= htmlspecialchars(
                            $success
                        ) ?>
                    </strong>
                </p>
                <p>
                    <a href="login.php">
                        <?= professorH(t('p150i_520bec5aa762')) ?>
                    </a>
                </p>
            <!-- ========================================= -->
            <!-- VALID RESET TOKEN -->
            <!-- ========================================= -->
            <?php elseif ($tokenValid): ?>
                <h2>
                    <?= professorH(t('choose_new_password')) ?>
                </h2>
                <p>
                    <?= professorH(t('enter_new_password_below')) ?>
                </p>
                <p>
                    <?= professorH(t('new_password_minimum_error')) ?>
                </p>
                <?php if ($error !== ''): ?>
                    <p>
                        <strong>
                            <?= htmlspecialchars(
                                $error
                            ) ?>
                        </strong>
                    </p>
                <?php endif; ?>
                <form method="POST"><?php px_csrf_field(); ?>
                    <input
                        type="hidden"
                        name="token"
                        value="<?= htmlspecialchars(
                            $token
                        ) ?>"
                    >
                    <!-- NEW PASSWORD -->
                    <div>
                        <label for="password">
                            <strong>
                                <?= professorH(t('p150i_7c451e0f436d')) ?>
                            </strong>
                        </label>
                        <br>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >
                    </div>
                    <br>
                    <!-- CONFIRM PASSWORD -->
                    <div>
                        <label for="password_confirmation">
                            <strong>
                                <?= professorH(t('p150i_642776c02c0d')) ?>
                            </strong>
                        </label>
                        <br>
                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >
                    </div>
                    <br>
                    <button type="submit">
                        <?= professorH(t('change_password')) ?>
                    </button>
                </form>
            <!-- ========================================= -->
            <!-- INVALID / EXPIRED TOKEN -->
            <!-- ========================================= -->
            <?php else: ?>
                <h2>
                    <?= professorH(t('reset_link_not_available')) ?>
                </h2>
                <p>
                    <strong>
                        <?= htmlspecialchars(
                            $error
                        ) ?>
                    </strong>
                </p>
                <p>
                    <a href="forgot_password.php">
                        <?= professorH(t('p150i_6f87f982034b')) ?>
                    </a>
                </p>
            <?php endif; ?>
        </section>
    </main>
    <hr>
    <footer>
        <p>
            <a href="login.php">
                <?= professorH(t('p150i_1a0939661e4a')) ?>
            </a>
            &nbsp; | &nbsp;
            <a href="../index.php">
                <?= professorH(t('homepage')) ?>
            </a>
        </p>
    </footer>
</div>
</body>
</html>