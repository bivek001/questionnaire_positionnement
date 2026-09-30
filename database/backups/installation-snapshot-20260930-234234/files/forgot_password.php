<?php

session_start();

require_once 'config/database.php';
require_once 'includes/language.php';

$message = '';
$error = '';
$developmentResetLink = '';
$email = '';


// ======================================================
// REDIRECT LOGGED-IN TRAINEES
// ======================================================

if (isset($_SESSION['trainee_id'])) {
    header('Location: index.php');
    exit;
}


// ======================================================
// PROCESS FORGOT PASSWORD REQUEST
// ======================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');


    // ==================================================
    // VALIDATE EMAIL
    // ==================================================

    if ($email === '') {

        $error = t('enter_email_address');

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = t('valid_email_required');

    } else {

        // ==================================================
        // FIND TRAINEE
        // ==================================================

        $stmt = $pdo->prepare(
            "SELECT
                id,
                email
             FROM trainees
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->execute([$email]);

        $trainee = $stmt->fetch(PDO::FETCH_ASSOC);


        /*
         * Always display the same general message.
         *
         * This avoids revealing whether an email address
         * is registered in the system.
         */

        $message = t('password_reset_generic_message');


        // ==================================================
        // CREATE RESET TOKEN IF ACCOUNT EXISTS
        // ==================================================

        if ($trainee) {

            $traineeId = (int)$trainee['id'];


            // ==================================================
            // INVALIDATE OLD UNUSED TOKENS
            // ==================================================

            $stmt = $pdo->prepare(
                "UPDATE password_reset_tokens
                 SET used_at = NOW()
                 WHERE account_type = 'trainee'
                   AND account_id = ?
                   AND used_at IS NULL"
            );

            $stmt->execute([$traineeId]);


            // ==================================================
            // GENERATE SECURE RANDOM TOKEN
            // ==================================================

            $token = bin2hex(random_bytes(32));


            /*
             * Store only the SHA-256 hash in the database.
             * The original token is used only in the reset URL.
             */

            $tokenHash = hash('sha256', $token);


            // ==================================================
            // TOKEN EXPIRES AFTER 30 MINUTES
            // ==================================================

            $expiresAt = date(
                'Y-m-d H:i:s',
                time() + (30 * 60)
            );


            // ==================================================
            // STORE TOKEN
            // ==================================================

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
                    'trainee',
                    ?,
                    ?,
                    ?
                )"
            );

            $stmt->execute([
                $traineeId,
                $tokenHash,
                $expiresAt
            ]);


            // ==================================================
            // DEVELOPMENT RESET LINK
            // ==================================================

            /*
             * For development/testing only.
             *
             * Later this link can be sent by email instead of
             * displaying it directly on the page.
             */

            $developmentResetLink =
                'reset_password.php?token='
                . urlencode($token);
        }
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
        <?= htmlspecialchars(t('forgot_password_title')) ?>
        |
        <?= htmlspecialchars(t('trainee_account')) ?>
    </title>

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

    <style>

        /* ==================================================
           PAGE
           ================================================== */

        body.forgot-password-page {
            margin: 0;
            min-height: 100vh;
            color: #1e293b;
            background: #f3f6fb;
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
        }

        .forgot-password-page *,
        .forgot-password-page *::before,
        .forgot-password-page *::after {
            box-sizing: border-box;
        }

        .forgot-password-page .page-container {
            width: calc(100% - 2rem);
            max-width: 1100px;
            min-height: 100vh;
            margin: 0 auto;
            padding: 1.5rem 0;
            display: flex;
            flex-direction: column;
        }


        /* ==================================================
           TOP BAR
           ================================================== */

        .forgot-password-page .top-navigation {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .forgot-password-page .home-link {
            display: inline-flex;
            align-items: center;
            min-height: 44px;
            color: #1d4ed8;
            font-weight: 700;
            text-decoration: none;
        }

        .forgot-password-page .home-link:hover {
            color: #1e3a8a;
            text-decoration: underline;
        }


        /* ==================================================
           LANGUAGE SWITCHER
           ================================================== */

        .forgot-password-page .language-switcher {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .3rem;
            background: #ffffff;
            border: 1px solid #dbe3ee;
            border-radius: 10px;
        }

        .forgot-password-page .language-link {
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

        .forgot-password-page .language-link:hover {
            color: #1e40af;
            background: #eff6ff;
        }

        .forgot-password-page .language-link.active {
            color: #ffffff;
            background: #1d4ed8;
        }


        /* ==================================================
           MAIN
           ================================================== */

        .forgot-password-page .reset-shell {
            flex: 1;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }

        .forgot-password-page .reset-card {
            width: 100%;
            max-width: 540px;
            padding: 40px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            box-shadow:
                0 18px 50px
                rgba(15, 23, 42, 0.08);
            overflow-wrap: anywhere;
        }


        /* ==================================================
           HEADER
           ================================================== */

        .forgot-password-page .reset-header {
            margin: 0 0 28px;
            padding: 0;
            color: inherit;
            background: transparent;
            border: 0;
            text-align: center;
            box-shadow: none;
        }

        .forgot-password-page .reset-icon {
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

        .forgot-password-page .reset-eyebrow {
            margin: 0 0 8px;
            color: #475569;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .forgot-password-page .reset-header h1 {
            margin: 0 0 12px;
            color: #0f172a;
            font-size: clamp(1.75rem, 5vw, 2.15rem);
            line-height: 1.2;
            letter-spacing: -.035em;
        }

        .forgot-password-page .reset-description {
            margin: 0;
            color: #475569;
            font-size: .975rem;
        }


        /* ==================================================
           EXPIRY INFORMATION
           ================================================== */

        .forgot-password-page .reset-expiry {
            margin: 0 0 24px;
            padding: 12px 16px;
            color: #1e40af;
            background: #eff6ff;
            border: 1px solid #dbeafe;
            border-radius: 12px;
            font-size: .9rem;
            text-align: center;
        }


        /* ==================================================
           NOTICES
           ================================================== */

        .forgot-password-page .reset-notice {
            margin: 0 0 22px;
            padding: 16px 18px;
            border: 1px solid;
            border-left-width: 4px;
            border-radius: 12px;
            font-size: .925rem;
        }

        .forgot-password-page .reset-notice strong {
            display: block;
            margin-bottom: 4px;
        }

        .forgot-password-page .reset-notice p {
            margin: 0;
            color: inherit;
        }

        .forgot-password-page .reset-notice-error {
            color: #991b1b;
            background: #fef2f2;
            border-color: #fca5a5;
        }

        .forgot-password-page .reset-notice-success {
            color: #166534;
            background: #f0fdf4;
            border-color: #86efac;
        }


        /* ==================================================
           FORM
           ================================================== */

        .forgot-password-page .reset-form {
            display: block;
            width: 100%;
            margin: 0;
            padding: 0;
            background: transparent;
            border: 0;
            box-shadow: none;
        }

        .forgot-password-page .reset-form label {
            display: block;
            margin: 0 0 8px;
            color: #1e293b;
            font-size: .925rem;
            font-weight: 700;
        }

        .forgot-password-page .reset-form input {
            display: block;
            width: 100%;
            min-width: 0;
            min-height: 50px;
            margin: 0;
            padding: 12px 14px;
            color: #0f172a;
            background: #ffffff;
            border: 1px solid #94a3b8;
            border-radius: 10px;
            font: inherit;
            font-size: 1rem;
            box-shadow: none;
        }

        .forgot-password-page
        .reset-form input[aria-invalid="true"] {
            border-color: #b91c1c;
        }

        .forgot-password-page .reset-field-help {
            margin: 8px 0 0;
            color: #64748b;
            font-size: .825rem;
        }

        .forgot-password-page .reset-submit {
            display: block;
            width: 100%;
            min-height: 50px;
            margin: 24px 0 0;
            padding: 13px 18px;
            color: #ffffff;
            background: #1d4ed8;
            border: 1px solid #1d4ed8;
            border-radius: 10px;
            font: inherit;
            font-weight: 700;
            line-height: 1.4;
            white-space: normal;
            cursor: pointer;
            box-shadow:
                0 4px 10px
                rgba(29, 78, 216, .15);
        }

        .forgot-password-page .reset-submit:hover {
            background: #1e40af;
            border-color: #1e40af;
        }


        /* ==================================================
           DEVELOPMENT TESTING
           ================================================== */

        .forgot-password-page .reset-development {
            margin: 28px 0 0;
            padding: 20px;
            color: #78350f;
            background: #fffbeb;
            border: 1px dashed #d97706;
            border-radius: 14px;
        }

        .forgot-password-page .reset-development h2 {
            margin: 0 0 10px;
            color: #78350f;
            font-size: 1rem;
            line-height: 1.4;
        }

        .forgot-password-page .reset-development p {
            margin: 0 0 14px;
            color: inherit;
            font-size: .9rem;
        }

        .forgot-password-page
        .reset-development p:last-child {
            margin-bottom: 0;
        }

        .forgot-password-page .reset-development a {
            display: inline-block;
            padding: 8px 0;
            color: #78350f;
            font-weight: 700;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .forgot-password-page .reset-development small {
            font-size: .8rem;
        }


        /* ==================================================
           FOOTER
           ================================================== */

        .forgot-password-page .reset-footer {
            margin: 28px 0 0;
            padding: 22px 0 0;
            background: transparent;
            border: 0;
            border-top: 1px solid #e2e8f0;
            text-align: center;
        }

        .forgot-password-page .reset-navigation {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 4px 24px;
        }

        .forgot-password-page .reset-navigation a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            color: #1d4ed8;
            font-size: .875rem;
            font-weight: 700;
            text-decoration: underline;
            text-underline-offset: 4px;
        }

        .forgot-password-page .reset-navigation a:hover {
            color: #1e3a8a;
        }


        /* ==================================================
           ACCESSIBILITY
           ================================================== */

        .forgot-password-page a:focus-visible,
        .forgot-password-page button:focus-visible,
        .forgot-password-page input:focus-visible {
            outline: 3px solid #2563eb;
            outline-offset: 3px;
        }


        /* ==================================================
           RESPONSIVE
           ================================================== */

        @media (max-width: 560px) {

            .forgot-password-page .page-container {
                width: calc(100% - 1.5rem);
                padding: .75rem 0;
            }

            .forgot-password-page .reset-shell {
                padding: 1.5rem 0;
            }

            .forgot-password-page .reset-card {
                padding: 28px 22px;
                border-radius: 18px;
            }

            .forgot-password-page .top-navigation {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media (max-width: 360px) {

            .forgot-password-page .reset-card {
                padding: 24px 16px;
            }

            .forgot-password-page .reset-navigation {
                flex-direction: column;
            }
        }

    </style>

<link rel="stylesheet" href="assets/css/ui.css">
<script src="assets/js/ui.js" defer></script>
</head>


<body class="forgot-password-page">

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

        <div class="reset-card">


            <!-- HEADER -->

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
                    <?= htmlspecialchars(t('forgot_password_title')) ?>
                </h1>

                <p class="reset-description">
                    <?= htmlspecialchars(t('forgot_password_description')) ?>
                </p>

            </header>


            <!-- ==================================================
                 EXPIRY NOTICE
                 ================================================== -->

            <p class="reset-expiry">
                <?= htmlspecialchars(t('reset_request_expiry')) ?>
            </p>


            <!-- ==================================================
                 ERROR
                 ================================================== -->

            <?php if ($error !== ''): ?>

                <div
                    class="reset-notice reset-notice-error"
                    id="reset-error"
                    role="alert"
                >

                    <strong>
                        <?= htmlspecialchars(t('check_email_address')) ?>
                    </strong>

                    <p>
                        <?= htmlspecialchars($error) ?>
                    </p>

                </div>

            <?php endif; ?>


            <!-- ==================================================
                 SUCCESS / GENERIC RESPONSE
                 ================================================== -->

            <?php if ($message !== ''): ?>

                <div
                    class="reset-notice reset-notice-success"
                    role="status"
                    aria-live="polite"
                    aria-atomic="true"
                >

                    <strong>
                        <?= htmlspecialchars(t('request_received')) ?>
                    </strong>

                    <p>
                        <?= htmlspecialchars($message) ?>
                    </p>

                </div>

            <?php endif; ?>


            <!-- ==================================================
                 FORM
                 ================================================== -->

            <form
                method="POST"
                class="reset-form"
            >

                <label for="email">
                    <?= htmlspecialchars(t('email_address')) ?>
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="255"
                    value="<?= htmlspecialchars($email) ?>"
                    autocomplete="email"
                    required
                    aria-describedby="email-help<?= $error !== '' ? ' reset-error' : '' ?>"
                    <?php if ($error !== ''): ?>
                        aria-invalid="true"
                    <?php endif; ?>
                >

                <p
                    id="email-help"
                    class="reset-field-help"
                >
                    <?= htmlspecialchars(t('trainee_email_help')) ?>
                </p>


                <button
                    type="submit"
                    class="reset-submit"
                >
                    <?= htmlspecialchars(t('request_password_reset')) ?>
                </button>

            </form>


            <!-- ==================================================
                 DEVELOPMENT RESET LINK
                 ================================================== -->

            <?php if ($developmentResetLink !== ''): ?>

                <section
                    class="reset-development"
                    aria-labelledby="development-heading"
                >

                    <h2 id="development-heading">
                        <?= htmlspecialchars(t('development_testing')) ?>
                    </h2>

                    <p>
                        <?= htmlspecialchars(
                            t('development_reset_description')
                        ) ?>
                    </p>

                    <p>

                        <a
                            href="<?= htmlspecialchars($developmentResetLink) ?>"
                        >
                            <?= htmlspecialchars(
                                t('continue_reset_password')
                            ) ?>
                            &rarr;
                        </a>

                    </p>

                    <p>

                        <small>
                            <?= htmlspecialchars(
                                t('development_reset_warning')
                            ) ?>
                        </small>

                    </p>

                </section>

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
                        <?= htmlspecialchars(t('back_to_trainee_login')) ?>
                    </a>

                    <a href="index.php">
                        <?= htmlspecialchars(t('homepage')) ?>
                    </a>

                </nav>

            </footer>

        </div>

    </main>

</div>

</body>

</html>