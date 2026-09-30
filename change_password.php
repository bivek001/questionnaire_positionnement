<?php

session_start();

require_once 'config/database.php';
require_once 'includes/language.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__.'/includes/account_security.php';
ux_csrf_check();


// ======================================================
// TRAINEE AUTHENTICATION
// ======================================================

if (!isset($_SESSION['trainee_id'])) {

    header('Location: trainee_login.php');
    exit;
}


$error = '';
$success = '';

$traineeId = (int)$_SESSION['trainee_id'];


// ======================================================
// PROCESS PASSWORD CHANGE
// ======================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
     * Do not trim passwords.
     * Spaces may intentionally be part of a password.
     */

    $currentPassword =
        $_POST['current_password']
        ?? '';

    $newPassword =
        $_POST['new_password']
        ?? '';

    $confirmPassword =
        $_POST['confirm_password']
        ?? '';


    // ==================================================
    // REQUIRED FIELDS
    // ==================================================

    if (
        $currentPassword === '' ||
        $newPassword === '' ||
        $confirmPassword === ''
    ) {

        $error =
            t('please_complete_all_fields');


    // ==================================================
    // MINIMUM PASSWORD LENGTH
    // ==================================================

    } elseif (strlen($newPassword) < 8) {

        $error =
            t('new_password_minimum_8');


    // ==================================================
    // PASSWORD CONFIRMATION
    // ==================================================

    } elseif (
        $newPassword !==
        $confirmPassword
    ) {

        $error =
            t('new_passwords_do_not_match');


    // ==================================================
    // PREVENT SAME ENTERED PASSWORD
    // ==================================================

    } elseif (
        $newPassword ===
        $currentPassword
    ) {

        $error =
            t('new_password_must_be_different');

    } else {

        // ==================================================
        // GET CURRENT PASSWORD HASH
        // ==================================================

        $stmt = $pdo->prepare(
            "SELECT password
             FROM trainees
             WHERE id = ?
             LIMIT 1"
        );

        $stmt->execute([
            $traineeId
        ]);

        $trainee =
            $stmt->fetch(PDO::FETCH_ASSOC);


        // ==================================================
        // VERIFY TRAINEE ACCOUNT
        // ==================================================

        if (!$trainee) {

            $error =
                t('trainee_account_not_found');


        // ==================================================
        // VERIFY CURRENT PASSWORD
        // ==================================================

        } elseif (
            empty($trainee['password']) ||
            !password_verify(
                $currentPassword,
                $trainee['password']
            )
        ) {

            $error =
                t('current_password_incorrect');

        } else {

            /*
             * Also compare the new password against the
             * existing password hash.
             *
             * This prevents the trainee from reusing the
             * current password even in cases where the
             * entered current-password value differs in
             * representation.
             */

            if (
                password_verify(
                    $newPassword,
                    $trainee['password']
                )
            ) {

                $error =
                    t('new_password_must_be_different');

            } else {

                // ==========================================
                // CREATE SECURE PASSWORD HASH
                // ==========================================

                $newPasswordHash =
                    password_hash(
                        $newPassword,
                        PASSWORD_DEFAULT
                    );


                if ($newPasswordHash === false) {

                    $error =
                        t('unable_secure_new_password');

                } else {

                    // ======================================
                    // UPDATE PASSWORD
                    // ======================================

                    $stmt = $pdo->prepare(
                        "UPDATE trainees
                         SET password = ?
                         WHERE id = ?"
                    );

                    $stmt->execute([
                        $newPasswordHash,
                        $traineeId
                    ]);


                    // ======================================
                    // REGENERATE SESSION ID
                    // ======================================

                    session_regenerate_id(true);


                    // ======================================
                    // SUCCESS
                    // ======================================

                    $success =
                        t(
                            'password_changed_account_success'
                        );
                }
            }
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
        <?= htmlspecialchars(t('change_password')) ?>
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

        body.change-password-page {
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

        .change-password-page *,
        .change-password-page *::before,
        .change-password-page *::after {
            box-sizing: border-box;
        }

        .change-password-page .page-container {
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

        .change-password-page .top-navigation {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .change-password-page .home-link {
            display: inline-flex;
            align-items: center;
            min-height: 44px;
            color: #1d4ed8;
            font-weight: 700;
            text-decoration: none;
        }

        .change-password-page .home-link:hover {
            color: #1e3a8a;
            text-decoration: underline;
        }


        /* ==================================================
           LANGUAGE SWITCHER
           ================================================== */

        .change-password-page .language-switcher {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .3rem;
            background: #ffffff;
            border: 1px solid #dbe3ee;
            border-radius: 10px;
        }

        .change-password-page .language-link {
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

        .change-password-page .language-link:hover {
            color: #1e40af;
            background: #eff6ff;
        }

        .change-password-page .language-link.active {
            color: #ffffff;
            background: #1d4ed8;
        }


        /* ==================================================
           MAIN CARD
           ================================================== */

        .change-password-page .security-shell {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 2rem 0;
        }

        .change-password-page .security-card {
            width: 100%;
            max-width: 540px;
            margin: 0;
            padding: 40px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            box-shadow:
                0 18px 50px
                rgba(15, 23, 42, .08);
            overflow-wrap: anywhere;
        }


        /* ==================================================
           HEADER
           ================================================== */

        .change-password-page .security-header {
            margin: 0 0 28px;
            padding: 0;
            color: inherit;
            background: transparent;
            border: 0;
            text-align: center;
            box-shadow: none;
        }

        .change-password-page .security-icon {
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

        .change-password-page .security-eyebrow {
            margin: 0 0 8px;
            color: #475569;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .change-password-page .security-header h1 {
            margin: 0 0 12px;
            color: #0f172a;
            font-size:
                clamp(1.75rem, 5vw, 2.15rem);
            line-height: 1.2;
            letter-spacing: -.035em;
        }

        .change-password-page
        .security-description {
            margin: 0;
            color: #475569;
            font-size: .975rem;
        }


        /* ==================================================
           NOTICES
           ================================================== */

        .change-password-page
        .security-notice {
            margin: 0 0 22px;
            padding: 16px 18px;
            border: 1px solid;
            border-left-width: 4px;
            border-radius: 12px;
            font-size: .925rem;
        }

        .change-password-page
        .security-notice strong {
            display: block;
            margin-bottom: 4px;
        }

        .change-password-page
        .security-notice p {
            margin: 0;
            color: inherit;
        }

        .change-password-page
        .security-notice-error {
            color: #991b1b;
            background: #fef2f2;
            border-color: #fca5a5;
        }

        .change-password-page
        .security-notice-success {
            color: #166534;
            background: #f0fdf4;
            border-color: #86efac;
        }


        /* ==================================================
           FORM
           ================================================== */

        .change-password-page
        .security-form {
            display: block;
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 0;
            background: transparent;
            border: 0;
            box-shadow: none;
        }

        .change-password-page
        .security-field {
            margin: 0 0 22px;
        }

        .change-password-page
        .security-form label {
            display: block;
            margin: 0 0 8px;
            color: #1e293b;
            font-size: .925rem;
            font-weight: 700;
        }

        .change-password-page
        .security-form input {
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

        .change-password-page
        .security-field-help {
            display: block;
            margin: 8px 0 0;
            color: #475569;
            font-size: .825rem;
        }

        .change-password-page
        .security-submit {
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

        .change-password-page
        .security-submit:hover {
            background: #1e40af;
            border-color: #1e40af;
        }


        /* ==================================================
           FOOTER / BACK
           ================================================== */

        .change-password-page
        .security-navigation {
            margin: 28px 0 0;
            padding: 22px 0 0;
            background: transparent;
            border: 0;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            box-shadow: none;
        }

        .change-password-page
        .security-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 44px;
            color: #1d4ed8;
            font-size: .925rem;
            font-weight: 700;
            text-decoration: none;
        }

        .change-password-page
        .security-back:hover {
            color: #1e40af;
            text-decoration: underline;
            text-underline-offset: 4px;
        }


        /* ==================================================
           ACCESSIBILITY
           ================================================== */

        .change-password-page a:focus-visible,
        .change-password-page button:focus-visible,
        .change-password-page input:focus-visible {
            outline: 3px solid #2563eb;
            outline-offset: 3px;
        }


        /* ==================================================
           RESPONSIVE
           ================================================== */

        @media (max-width: 600px) {

            .change-password-page
            .page-container {
                width: calc(100% - 1.5rem);
                padding: .75rem 0;
            }

            .change-password-page
            .top-navigation {
                align-items: flex-start;
                flex-direction: column;
            }

            .change-password-page
            .security-shell {
                padding: 1.5rem 0;
            }

            .change-password-page
            .security-card {
                padding: 28px 22px;
                border-radius: 20px;
            }
        }

    </style>

<link rel="stylesheet" href="assets/css/ui.css">
<script src="assets/js/ui.js" defer></script>
</head>


<body class="change-password-page">

<div class="page-container">


    <!-- ==================================================
         TOP NAVIGATION
         ================================================== -->

    <nav
        class="top-navigation"
        aria-label="<?= htmlspecialchars(t('account_navigation')) ?>"
    >

        <a
            href="index.php"
            class="home-link"
        >
            &larr;
            <?= htmlspecialchars(t('back_to_questionnaire')) ?>
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
         CHANGE PASSWORD CARD
         ================================================== -->

    <main class="security-shell">

        <section
            class="security-card"
            aria-labelledby="security-title"
        >


            <!-- HEADER -->

            <header class="security-header">

                <div
                    class="security-icon"
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


                <p class="security-eyebrow">
                    <?= htmlspecialchars(t('account_security')) ?>
                </p>


                <h1 id="security-title">
                    <?= htmlspecialchars(t('change_password')) ?>
                </h1>


                <p class="security-description">
                    <?= htmlspecialchars(
                        t('change_password_description')
                    ) ?>
                </p>

            </header>


            <!-- ==================================================
                 ERROR MESSAGE
                 ================================================== -->

            <?php if ($error !== ''): ?>

                <div
                    class="security-notice security-notice-error"
                    role="alert"
                >

                    <strong>
                        <?= htmlspecialchars(
                            t('password_not_changed')
                        ) ?>
                    </strong>

                    <p>
                        <?= htmlspecialchars($error) ?>
                    </p>

                </div>

            <?php endif; ?>


            <!-- ==================================================
                 SUCCESS MESSAGE
                 ================================================== -->

            <?php if ($success !== ''): ?>

                <div
                    class="security-notice security-notice-success"
                    role="status"
                    aria-live="polite"
                >

                    <strong>
                        <?= htmlspecialchars(
                            t('password_changed_short')
                        ) ?>
                    </strong>

                    <p>
                        <?= htmlspecialchars($success) ?>
                    </p>

                </div>

            <?php endif; ?>


            <!-- ==================================================
                 FORM
                 ================================================== -->

            <form
                method="POST"
                class="security-form"
            ><?php ux_csrf_field(); ?>


                <!-- CURRENT PASSWORD -->

                <div class="security-field">

                    <label for="current_password">
                        <?= htmlspecialchars(
                            t('current_password')
                        ) ?>
                    </label>

                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        autocomplete="current-password"
                        required
                    >

                </div>


                <!-- NEW PASSWORD -->

                <div class="security-field">

                    <label for="new_password">
                        <?= htmlspecialchars(
                            t('new_password')
                        ) ?>
                    </label>

                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        minlength="8"
                        autocomplete="new-password"
                        aria-describedby="new-password-hint"
                        required
                    >

                    <small
                        id="new-password-hint"
                        class="security-field-help"
                    >
                        <?= htmlspecialchars(
                            t('new_password_requirements')
                        ) ?>
                    </small>

                </div>


                <!-- CONFIRM NEW PASSWORD -->

                <div class="security-field">

                    <label for="confirm_password">
                        <?= htmlspecialchars(
                            t('confirm_new_password')
                        ) ?>
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                </div>


                <!-- SUBMIT -->

                <button
                    type="submit"
                    class="security-submit"
                >
                    <?= htmlspecialchars(
                        t('change_password')
                    ) ?>
                </button>

            </form>


            <!-- ==================================================
                 NAVIGATION
                 ================================================== -->

            <nav
                class="security-navigation"
                aria-label="<?= htmlspecialchars(
                    t('back_navigation')
                ) ?>"
            >

                <a
                    href="index.php"
                    class="security-back"
                >

                    <span aria-hidden="true">
                        &larr;
                    </span>

                    <?= htmlspecialchars(
                        t('back_to_questionnaire')
                    ) ?>

                </a>

            </nav>

        </section>

    </main>

</div>

</body>

</html>