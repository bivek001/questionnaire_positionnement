<?php

session_start();

require_once 'config/database.php';
require_once 'includes/language.php';


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

    $tokenHash = hash(
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

         WHERE account_type = 'trainee'
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
             FROM trainees
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

    // Do not trim passwords.
    $password =
        $_POST['password']
        ?? '';

    $passwordConfirmation =
        $_POST['password_confirmation']
        ?? '';


    // ==================================================
    // VALIDATION
    // ==================================================

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

            // ==================================================
            // START TRANSACTION
            // ==================================================

            $pdo->beginTransaction();


            // ==================================================
            // LOCK AND RECHECK TOKEN
            // ==================================================

            $stmt = $pdo->prepare(
                "SELECT
                    id,
                    account_id

                 FROM password_reset_tokens

                 WHERE id = ?
                   AND account_type = 'trainee'
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


            $traineeId =
                (int)$lockedToken['account_id'];


            // ==================================================
            // HASH NEW PASSWORD
            // ==================================================

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


            // ==================================================
            // UPDATE TRAINEE PASSWORD
            // ==================================================

            $stmt = $pdo->prepare(
                "UPDATE trainees
                 SET password = ?
                 WHERE id = ?"
            );

            $stmt->execute([
                $passwordHash,
                $traineeId
            ]);


            /*
             * rowCount() can theoretically be 0 depending on
             * database behaviour, so verify that the account
             * still exists before considering this a failure.
             */

            if ($stmt->rowCount() < 1) {

                $stmt = $pdo->prepare(
                    "SELECT id
                     FROM trainees
                     WHERE id = ?
                     LIMIT 1"
                );

                $stmt->execute([
                    $traineeId
                ]);

                if (!$stmt->fetchColumn()) {

                    throw new RuntimeException(
                        t('trainee_account_not_found')
                    );
                }
            }


            // ==================================================
            // MARK ALL TRAINEE RESET TOKENS AS USED
            // ==================================================

            $stmt = $pdo->prepare(
                "UPDATE password_reset_tokens
                 SET used_at = NOW()
                 WHERE account_type = 'trainee'
                   AND account_id = ?
                   AND used_at IS NULL"
            );

            $stmt->execute([
                $traineeId
            ]);


            // ==================================================
            // COMMIT
            // ==================================================

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

            /*
             * Do not expose internal database or exception
             * information to the user.
             */

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

<html lang="<?= htmlspecialchars(htmlLanguage()) ?>">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars(t('reset_password')) ?>
    </title>

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

    <style>

        /* ==================================================
           PAGE
           ================================================== */

        body.reset-password-page {
            margin: 0;
            min-height: 100vh;
            color: #1e293b;
            background: #f3f6fb;
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            line-height: 1.6;
        }

        .reset-password-page *,
        .reset-password-page *::before,
        .reset-password-page *::after {
            box-sizing: border-box;
        }

        .reset-password-page .page-container {
            width: calc(100% - 2rem);
            max-width: 1100px;
            min-height: 100vh;
            margin: 0 auto;
            padding: 1.5rem 0;
            display: flex;
            flex-direction: column;
        }


        /* ==================================================
           TOP NAVIGATION
           ================================================== */

        .reset-password-page .top-navigation {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .reset-password-page .home-link {
            display: inline-flex;
            align-items: center;
            min-height: 44px;
            color: #1d4ed8;
            font-weight: 700;
            text-decoration: none;
        }

        .reset-password-page .home-link:hover {
            color: #1e3a8a;
            text-decoration: underline;
        }


        /* ==================================================
           LANGUAGE SWITCHER
           ================================================== */

        .reset-password-page .language-switcher {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .3rem;
            background: #ffffff;
            border: 1px solid #dbe3ee;
            border-radius: 10px;
        }

        .reset-password-page .language-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            padding: .35rem .7rem;
            border-radius: 7px;
            color: #475569;
            font-size: .85rem;
            font-weight: 700;
            text-decoration: none;
        }

        .reset-password-page .language-link:hover {
            color: #1e40af;
            background: #eff6ff;
        }

        .reset-password-page .language-link.active {
            color: #ffffff;
            background: #1d4ed8;
        }


        /* ==================================================
           MAIN
           ================================================== */

        .reset-password-page .reset-shell {
            flex: 1;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }

        .reset-password-page .reset-card {
            width: 100%;
            max-width: 560px;
            padding: 40px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            box-shadow:
                0 18px 50px
                rgba(15, 23, 42, .08);
        }


        /* ==================================================
           HEADER
           ================================================== */

        .reset-password-page .reset-header {
            margin-bottom: 28px;
            text-align: center;
        }

        .reset-password-page .reset-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            margin: 0 auto 18px;
            color: #1d4ed8;
            background: #eff6ff;
            border: 1px solid #dbeafe;
            border-radius: 18px;
        }

        .reset-password-page .reset-eyebrow {
            margin: 0 0 8px;
            color: #475569;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .reset-password-page h1 {
            margin: 0;
            color: #0f172a;
            font-size: clamp(1.75rem, 5vw, 2.15rem);
            line-height: 1.2;
        }

        .reset-password-page h2 {
            margin: 0 0 12px;
            color: #0f172a;
            font-size: 1.35rem;
        }

        .reset-password-page .description {
            margin: 0 0 20px;
            color: #475569;
        }


        /* ==================================================
           NOTICES
           ================================================== */

        .reset-password-page .notice {
            margin: 0 0 22px;
            padding: 16px 18px;
            border: 1px solid;
            border-left-width: 4px;
            border-radius: 12px;
        }

        .reset-password-page .notice p {
            margin: 0;
        }

        .reset-password-page .notice-error {
            color: #991b1b;
            background: #fef2f2;
            border-color: #fca5a5;
        }

        .reset-password-page .notice-success {
            color: #166534;
            background: #f0fdf4;
            border-color: #86efac;
        }


        /* ==================================================
           FORM
           ================================================== */

        .reset-password-page .reset-form {
            margin: 0;
        }

        .reset-password-page .form-field {
            margin-bottom: 1.25rem;
        }

        .reset-password-page .form-field label {
            display: block;
            margin-bottom: 8px;
            color: #1e293b;
            font-size: .925rem;
            font-weight: 700;
        }

        .reset-password-page .form-field input {
            display: block;
            width: 100%;
            min-height: 50px;
            padding: 12px 14px;
            color: #0f172a;
            background: #ffffff;
            border: 1px solid #94a3b8;
            border-radius: 10px;
            font: inherit;
            font-size: 1rem;
        }

        .reset-password-page .form-help {
            margin: 7px 0 0;
            color: #64748b;
            font-size: .85rem;
        }

        .reset-password-page .submit-button {
            display: block;
            width: 100%;
            min-height: 50px;
            margin-top: 8px;
            padding: 13px 18px;
            color: #ffffff;
            background: #1d4ed8;
            border: 1px solid #1d4ed8;
            border-radius: 10px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .reset-password-page .submit-button:hover {
            background: #1e40af;
            border-color: #1e40af;
        }


        /* ==================================================
           ACTION LINK
           ================================================== */

        .reset-password-page .action-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            margin-top: 10px;
            padding: .6rem 1rem;
            color: #1d4ed8;
            background: #ffffff;
            border: 1px solid #93c5fd;
            border-radius: 10px;
            font-weight: 700;
            text-decoration: none;
        }

        .reset-password-page .action-link:hover {
            color: #1e3a8a;
            background: #eff6ff;
        }


        /* ==================================================
           FOOTER
           ================================================== */

        .reset-password-page .reset-footer {
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid #e2e8f0;
        }

        .reset-password-page .reset-navigation {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 8px 24px;
        }

        .reset-password-page .reset-navigation a {
            display: inline-flex;
            align-items: center;
            min-height: 44px;
            color: #1d4ed8;
            font-size: .875rem;
            font-weight: 700;
            text-decoration: underline;
            text-underline-offset: 4px;
        }


        /* ==================================================
           ACCESSIBILITY
           ================================================== */

        .reset-password-page a:focus-visible,
        .reset-password-page button:focus-visible,
        .reset-password-page input:focus-visible {
            outline: 3px solid #2563eb;
            outline-offset: 3px;
        }


        /* ==================================================
           RESPONSIVE
           ================================================== */

        @media (max-width: 560px) {

            .reset-password-page .page-container {
                width: calc(100% - 1.5rem);
                padding: .75rem 0;
            }

            .reset-password-page .reset-shell {
                padding: 1.5rem 0;
            }

            .reset-password-page .reset-card {
                padding: 28px 22px;
                border-radius: 18px;
            }

            .reset-password-page .top-navigation {
                align-items: flex-start;
                flex-direction: column;
            }
        }

    </style>

<link rel="stylesheet" href="assets/css/ui.css">
<script src="assets/js/ui.js" defer></script>
</head>

<body class="reset-password-page">

<div class="page-container">


    <!-- ==================================================
         TOP NAVIGATION
         ================================================== -->

    <nav
        class="top-navigation"
        aria-label="<?= htmlspecialchars(t('account_navigation')) ?>"
    >

        <a
            class="home-link"
            href="trainee_login.php"
        >
            &larr;
            <?= htmlspecialchars(t('back_to_trainee_login')) ?>
        </a>


        <!--
            languageUrl() keeps the existing ?token=...
            query parameter when changing language.
        -->

        <div
            class="language-switcher"
            aria-label="<?= htmlspecialchars(t('select_language')) ?>"
        >

            <a
                class="language-link <?= isLanguage('en') ? 'active' : '' ?>"
                href="<?= htmlspecialchars(languageUrl('en')) ?>"
                <?= isLanguage('en') ? 'aria-current="page"' : '' ?>
            >
                English
            </a>

            <a
                class="language-link <?= isLanguage('fr') ? 'active' : '' ?>"
                href="<?= htmlspecialchars(languageUrl('fr')) ?>"
                <?= isLanguage('fr') ? 'aria-current="page"' : '' ?>
            >
                Français
            </a>

        </div>

    </nav>


    <!-- ==================================================
         MAIN
         ================================================== -->

    <main class="reset-shell">

        <section class="reset-card">


            <!-- ==================================================
                 HEADER
                 ================================================== -->

            <header class="reset-header">

                <div
                    class="reset-icon"
                    aria-hidden="true"
                >
                    <svg
                        width="28"
                        height="28"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        focusable="false"
                    >
                        <rect
                            x="5"
                            y="10"
                            width="14"
                            height="11"
                            rx="2"
                        ></rect>

                        <path
                            d="M8 10V7a4 4 0 0 1 8 0v3"
                        ></path>

                        <circle
                            cx="12"
                            cy="15"
                            r="1"
                        ></circle>

                        <path
                            d="M12 16v2"
                        ></path>
                    </svg>
                </div>

                <p class="reset-eyebrow">
                    <?= htmlspecialchars(t('trainee_account')) ?>
                </p>

                <h1>
                    <?= htmlspecialchars(t('reset_password')) ?>
                </h1>

            </header>


            <!-- ==================================================
                 SUCCESS
                 ================================================== -->

            <?php if ($success !== ''): ?>

                <h2>
                    <?= htmlspecialchars(t('password_changed')) ?>
                </h2>

                <div
                    class="notice notice-success"
                    role="status"
                    aria-live="polite"
                >
                    <p>
                        <strong>
                            <?= htmlspecialchars($success) ?>
                        </strong>
                    </p>
                </div>

                <a
                    class="action-link"
                    href="trainee_login.php"
                >
                    <?= htmlspecialchars(t('go_to_trainee_login')) ?>
                    &rarr;
                </a>


            <!-- ==================================================
                 VALID TOKEN
                 ================================================== -->

            <?php elseif ($tokenValid): ?>

                <h2>
                    <?= htmlspecialchars(t('choose_new_password')) ?>
                </h2>

                <p class="description">
                    <?= htmlspecialchars(t('enter_new_password_below')) ?>
                </p>


                <?php if ($error !== ''): ?>

                    <div
                        class="notice notice-error"
                        role="alert"
                    >
                        <p>
                            <strong>
                                <?= htmlspecialchars($error) ?>
                            </strong>
                        </p>
                    </div>

                <?php endif; ?>


                <form
                    method="POST"
                    class="reset-form"
                >

                    <!-- Keep reset token during POST -->

                    <input
                        type="hidden"
                        name="token"
                        value="<?= htmlspecialchars($token) ?>"
                    >


                    <!-- NEW PASSWORD -->

                    <div class="form-field">

                        <label for="password">
                            <?= htmlspecialchars(t('new_password')) ?>
                        </label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            minlength="8"
                            autocomplete="new-password"
                            aria-describedby="password-help"
                            required
                        >

                        <p
                            class="form-help"
                            id="password-help"
                        >
                            <?= htmlspecialchars(t('new_password_minimum_error')) ?>
                        </p>

                    </div>


                    <!-- CONFIRM PASSWORD -->

                    <div class="form-field">

                        <label for="password_confirmation">
                            <?= htmlspecialchars(t('confirm_new_password')) ?>
                        </label>

                        <input
                            type="password"
                            id="password_confirmation"
                            name="password_confirmation"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >

                    </div>


                    <button
                        type="submit"
                        class="submit-button"
                    >
                        <?= htmlspecialchars(t('change_password')) ?>
                    </button>

                </form>


            <!-- ==================================================
                 INVALID / EXPIRED TOKEN
                 ================================================== -->

            <?php else: ?>

                <h2>
                    <?= htmlspecialchars(t('reset_link_not_available')) ?>
                </h2>

                <div
                    class="notice notice-error"
                    role="alert"
                >
                    <p>
                        <strong>
                            <?= htmlspecialchars($error) ?>
                        </strong>
                    </p>
                </div>

                <a
                    class="action-link"
                    href="forgot_password.php"
                >
                    <?= htmlspecialchars(t('request_new_reset_link')) ?>
                    &rarr;
                </a>

            <?php endif; ?>


            <!-- ==================================================
                 FOOTER
                 ================================================== -->

            <footer class="reset-footer">

                <nav
                    class="reset-navigation"
                    aria-label="<?= htmlspecialchars(t('account_navigation')) ?>"
                >

                    <a href="trainee_login.php">
                        &larr;
                        <?= htmlspecialchars(t('trainee_login_title')) ?>
                    </a>

                    <a href="index.php">
                        <?= htmlspecialchars(t('homepage')) ?>
                    </a>

                </nav>

            </footer>

        </section>

    </main>

</div>

</body>

</html>