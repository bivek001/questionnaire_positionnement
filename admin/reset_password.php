<?php
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/admin_language.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__.'/../includes/account_security.php';
ux_csrf_check();



if (session_status() !== PHP_SESSION_ACTIVE) session_start();



require_once '../config/database.php';





$error = '';

$success = '';

$tokenValid = false;

$tokenRecord = null;





// ======================================================

// GET TOKEN

// ======================================================



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

    // FIND VALID ADMIN RESET TOKEN

    // ==================================================



    $stmt = $pdo->prepare(

        "SELECT

            id,

            account_id,

            expires_at



         FROM password_reset_tokens



         WHERE account_type = 'admin'

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

        // MAKE SURE ADMIN STILL EXISTS

        // ----------------------------------------------



        $stmt = $pdo->prepare(

            "SELECT id



             FROM admins



             WHERE id = ?



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

    // VALIDATE PASSWORD

    // --------------------------------------------------



    if ($password === '') {



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

            t('new_password_confirmation_error');



    } else {



        try {



            $pdo->beginTransaction();





            // ------------------------------------------

            // LOCK AND RECHECK TOKEN

            // ------------------------------------------



            $stmt = $pdo->prepare(

                "SELECT

                    id,

                    account_id



                 FROM password_reset_tokens



                 WHERE id = ?

                   AND account_type = 'admin'

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





            $adminId =

                (int)$lockedToken['account_id'];





            // ------------------------------------------

            // CREATE SECURE PASSWORD HASH

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

            // UPDATE ADMIN PASSWORD

            // ------------------------------------------



            $stmt = $pdo->prepare(

                "UPDATE admins



                 SET password = ?



                 WHERE id = ?"

            );



            $stmt->execute([

                $passwordHash,

                $adminId

            ]);





            // ------------------------------------------

            // VERIFY ADMIN STILL EXISTS

            // ------------------------------------------



            $stmt = $pdo->prepare(

                "SELECT id



                 FROM admins



                 WHERE id = ?



                 LIMIT 1"

            );



            $stmt->execute([

                $adminId

            ]);



            if (!$stmt->fetchColumn()) {



                throw new RuntimeException(

                    t('a150j_36e456e0dd10')

                );

            }





            // ------------------------------------------

            // INVALIDATE ALL ADMIN RESET TOKENS

            // ------------------------------------------



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





            // ------------------------------------------

            // COMMIT

            // ------------------------------------------



            $pdo->commit();





            $success =

                t('a150j_9cbb8fc3e7ac');





            // Hide password form after successful reset.

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
<html lang="<?= adminH(htmlLanguage()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= adminH(t('a150j_a2354c3aa030')) ?> | <?= adminH(t('p150i_7b3acab7d31d')) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .admin-reset-page { min-height: 100vh; min-height: 100svh; display: flex; align-items: center; justify-content: center; padding: 32px 16px; }
        .admin-reset-page .reset-shell { width: 100%; max-width: 520px; }
        .admin-reset-page .reset-card { padding: 0; margin: 0; overflow: hidden; border: 1px solid #e5e7eb; border-radius: 16px; box-shadow: 0 12px 36px rgba(17,24,39,.08); background: #fff; }
        .admin-reset-page .reset-header { margin: 0; border-radius: 0; padding: 28px 32px; }
        .admin-reset-page .reset-header h1 { font-size: 25px; line-height: 1.3; margin: 0 0 10px; }
        .admin-reset-page .reset-header p { font-size: 14px; }
        .admin-reset-page .reset-kicker { display: block; margin-bottom: 12px; color: #bfdbfe; font-size: 12px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
        .admin-reset-page .reset-content { padding: 30px 32px; }
        .admin-reset-page h2 { margin: 0 0 10px; font-size: 22px; line-height: 1.35; }
        .admin-reset-page .reset-intro { color: #4b5563; margin: 0 0 24px; }
        .admin-reset-page .reset-field { margin-bottom: 20px; }
        .admin-reset-page input[type="password"] { min-height: 48px; margin: 0; font-size: 16px; border-radius: 8px; }
        .admin-reset-page .reset-help { margin: 7px 0 0; color: #4b5563; font-size: 13px; }
        .admin-reset-page .reset-action { width: 100%; min-height: 48px; margin: 4px 0 0; padding: 12px 16px; border-radius: 8px; text-align: center; font-size: 15px; line-height: 1.6; }
        .admin-reset-page .alert { margin: 0 0 22px; overflow-wrap: anywhere; }
        .admin-reset-page .alert p { margin: 0; }
        .admin-reset-page a:focus-visible,
        .admin-reset-page button:focus-visible { outline: 3px solid #2563eb; outline-offset: 4px; }
        .admin-reset-page .reset-footer { padding: 16px 24px; border-top: 1px solid #e5e7eb; background: #f9fafb; }
        .admin-reset-page .reset-footer nav { display: flex; justify-content: center; flex-wrap: wrap; gap: 4px 24px; }
        .admin-reset-page .reset-footer a { display: inline-flex; align-items: center; min-height: 44px; font-size: 14px; }
        @media (max-width: 480px) {
            .admin-reset-page { padding: 20px 12px; }
            .admin-reset-page .reset-header { padding: 24px; }
            .admin-reset-page .reset-content { padding: 24px; }
            .admin-reset-page .reset-header h1 { font-size: 23px; }
            .admin-reset-page h2 { font-size: 20px; }
        }
    </style>
<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>
<body class="admin-reset-page">
<?php require __DIR__ . '/admin_language_switcher.php'; ?>
    <div class="reset-shell">
        <div class="card reset-card">
            <header class="admin-header reset-header">
                <span class="reset-kicker"><?= adminH(t('a150j_64dbd25f2f7f')) ?></span>
                <h1><?= adminH(t('p150i_7b3acab7d31d')) ?></h1>
                <p><?= adminH(t('a150j_ad0a5f40cf0a')) ?></p>
            </header>

            <main class="reset-content">
                <section aria-labelledby="reset-title">
                    <?php if ($success !== ''): ?>
                        <h2 id="reset-title"><?= adminH(t('password_changed')) ?></h2>
                        <div class="alert alert-success" role="status">
                            <p><?= htmlspecialchars($success) ?></p>
                        </div>
                        <a class="btn reset-action" href="login.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('a150j_33aa4d0ae532')) ?></a>

                    <?php elseif ($tokenValid): ?>
                        <h2 id="reset-title"><?= adminH(t('a150j_b551cc60333a')) ?></h2>
                        <p class="reset-intro"><?= adminH(t('enter_new_password_below')) ?></p>

                        <?php if ($error !== ''): ?>
                            <div class="alert alert-error" id="reset-error" role="alert">
                                <p><?= htmlspecialchars($error) ?></p>
                            </div>
                        <?php endif; ?>

                        <form method="POST"><?php ux_csrf_field(); ?>
<input type="hidden" name="lang" value="<?= adminH(currentLanguage()) ?>">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                            <div class="reset-field">
                                <label for="password"><?= adminH(t('p150i_7c451e0f436d')) ?></label>
                                <input type="password" id="password" name="password"
                                       minlength="8" autocomplete="new-password"
                                       aria-describedby="password-help" required>
                                <p class="reset-help" id="password-help"><?= adminH(t('a150j_da9544e3897c')) ?></p>
                            </div>

                            <div class="reset-field">
                                <label for="password_confirmation"><?= adminH(t('p150i_642776c02c0d')) ?></label>
                                <input type="password" id="password_confirmation" name="password_confirmation"
                                       minlength="8" autocomplete="new-password"
                                       aria-describedby="confirmation-help" required>
                                <p class="reset-help" id="confirmation-help"><?= adminH(t('a150j_621de338db2b')) ?></p>
                            </div>

                            <button class="reset-action" type="submit"><?= adminH(t('a150j_dd179b6eb928')) ?></button>
                        </form>

                    <?php else: ?>
                        <h2 id="reset-title"><?= adminH(t('reset_link_not_available')) ?></h2>
                        <div class="alert alert-error" role="alert">
                            <p><?= htmlspecialchars($error) ?></p>
                        </div>
                        <a class="btn reset-action" href="forgot_password.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('p150i_6f87f982034b')) ?></a>
                    <?php endif; ?>
                </section>
            </main>

            <footer class="reset-footer">
                <nav aria-label="<?= adminH(t('account_navigation')) ?>">
                    <a href="login.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('a150j_f773679ec273')) ?></a>
                    <a href="../index.php?lang=<?= adminH(currentLanguage()) ?>"><?= adminH(t('homepage')) ?></a>
                </nav>
            </footer>
        </div>
    </div>
</body>
</html>