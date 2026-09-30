<?php

session_start();

require_once 'config/database.php';
require_once 'includes/language.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__.'/includes/account_security.php';
ux_csrf_check();

$error = '';

$firstName = '';
$lastName = '';
$dateOfBirth = '';
$email = '';


// ======================================================
// PROCESS REGISTRATION
// ======================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $dateOfBirth = trim($_POST['date_of_birth'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Do not trim passwords.
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';


    // ==================================================
    // VALIDATE REQUIRED FIELDS
    // ==================================================

    if (
        $firstName === '' ||
        $lastName === '' ||
        $dateOfBirth === '' ||
        $email === '' ||
        $password === '' ||
        $confirmPassword === ''
    ) {

        $error = t('please_complete_all_fields');

    }


    // ==================================================
    // VALIDATE DATE OF BIRTH
    // ==================================================

    elseif (
        !DateTime::createFromFormat(
            'Y-m-d',
            $dateOfBirth
        )
    ) {

        $error = t('valid_date_of_birth_required');

    }


    // ==================================================
    // PREVENT FUTURE DATE OF BIRTH
    // ==================================================

    elseif ($dateOfBirth > date('Y-m-d')) {

        $error = t('date_of_birth_future_error');

    }


    // ==================================================
    // VALIDATE EMAIL
    // ==================================================

    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = t('valid_email_required');

    }


    // ==================================================
    // VALIDATE PASSWORD LENGTH
    // ==================================================

    elseif (strlen($password) < 8) {

        $error = t('password_at_least_8');

    }


    // ==================================================
    // PASSWORD CONFIRMATION
    // ==================================================

    elseif ($password !== $confirmPassword) {

        $error = t('password_confirmation_mismatch');

    }


    // ==================================================
    // CREATE ACCOUNT
    // ==================================================

    else {

        /*
         * Check whether email already exists.
         */

        $stmt = $pdo->prepare(
            "SELECT id
             FROM trainees
             WHERE email = ?"
        );

        $stmt->execute([$email]);

        $trainee = $stmt->fetch(PDO::FETCH_ASSOC);


        if ($trainee) {

            $error = t('email_already_registered');

        } else {

            /*
             * Securely hash password.
             */

            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            /*
             * Create trainee.
             */

            $stmt = $pdo->prepare(
                "INSERT INTO trainees
                (
                    first_name,
                    last_name,
                    date_of_birth,
                    email,
                    password
                )
                VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $firstName,
                $lastName,
                $dateOfBirth,
                $email,
                $passwordHash
            ]);

            $traineeId = $pdo->lastInsertId();


            /*
             * Regenerate session ID.
             */

            session_regenerate_id(true);


            /*
             * Create trainee session.
             */

            $_SESSION['trainee_id'] =
                (int)$traineeId;

            $_SESSION['first_name'] =
                $firstName;

            $_SESSION['last_name'] =
                $lastName;


            /*
             * Redirect to trainee homepage.
             */

            header('Location: index.php');
            exit;
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
        <?= htmlspecialchars(t('trainee_registration')) ?>
    </title>

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

    <style>

        /* ==================================================
           PAGE
           ================================================== */

        body.trainee-register {
            margin: 0;
            background: #f3f7f8;
            color: #20343e;
            font-family:
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            line-height: 1.65;
        }

        .trainee-register *,
        .trainee-register *::before,
        .trainee-register *::after {
            box-sizing: border-box;
        }

        .trainee-register .auth-shell {
            width: calc(100% - 2rem);
            max-width: 1100px;
            min-height: 100vh;
            margin: 0 auto;
            padding: 1.5rem 0;
            display: flex;
            flex-direction: column;
        }

        .trainee-register a {
            color: #09665f;
            text-underline-offset: .2em;
        }

        .trainee-register a:hover {
            color: #074f49;
        }

        .trainee-register a:focus-visible,
        .trainee-register button:focus-visible,
        .trainee-register input:focus-visible {
            outline: 3px solid #9b4c00;
            outline-offset: 4px;
        }


        /* ==================================================
           TOP NAVIGATION
           ================================================== */

        .trainee-register .top-navigation {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .trainee-register .home-link {
            display: inline-flex;
            align-items: center;
            min-height: 44px;
            font-weight: 600;
            text-decoration: none;
        }

        .trainee-register .home-link:hover {
            text-decoration: underline;
        }


        /* ==================================================
           LANGUAGE SWITCHER
           ================================================== */

        .trainee-register .language-switcher {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .3rem;
            background: #ffffff;
            border: 1px solid #d8e4e7;
            border-radius: 10px;
        }

        .trainee-register .language-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            padding: .35rem .7rem;
            border-radius: 7px;
            color: #536772;
            font-size: .85rem;
            font-weight: 700;
            text-decoration: none;
        }

        .trainee-register .language-link:hover {
            background: #edf7f4;
            color: #064f49;
            text-decoration: none;
        }

        .trainee-register .language-link.active {
            background: #09665f;
            color: #ffffff;
        }


        /* ==================================================
           MAIN
           ================================================== */

        .trainee-register .auth-main {
            flex: 1;
            display: grid;
            align-content: center;
            justify-items: center;
            padding: 2rem 0;
        }

        .trainee-register .auth-card {
            width: 100%;
            max-width: 680px;
            min-width: 0;
            padding: clamp(1.25rem, 4vw, 2.5rem);
            background: #fff;
            border: 1px solid #d8e4e7;
            border-top: 5px solid #09665f;
            border-radius: 18px;
            box-shadow: 0 6px 22px rgba(23, 59, 69, .06);
            overflow-wrap: anywhere;
        }

        .trainee-register .auth-header {
            margin-bottom: 1.75rem;
            text-align: left;
        }

        .trainee-register .auth-brand {
            display: inline-block;
            margin-bottom: .75rem;
            font-size: .8rem;
            font-weight: 750;
            letter-spacing: .08em;
            text-transform: uppercase;
            text-decoration: none;
        }

        .trainee-register h1 {
            margin: 0 0 .75rem;
            color: #173b45;
            font-size: clamp(1.8rem, 5vw, 2.25rem);
            line-height: 1.25;
        }

        .trainee-register .auth-intro {
            margin: 0;
            color: #536772;
        }

        .trainee-register .required-note {
            margin: 1rem 0 0;
            color: #536772;
            font-size: .875rem;
        }


        /* ==================================================
           ERROR
           ================================================== */

        .trainee-register .auth-error {
            margin: 0 0 1.5rem;
            padding: 1rem 1.1rem;
            border: 1px solid #e6c5c1;
            border-left: 4px solid #9b3028;
            border-radius: 10px;
            background: #fff3f2;
            color: #9b3028;
        }


        /* ==================================================
           FORM
           ================================================== */

        .trainee-register .auth-form {
            margin: 0;
            padding: 0;
        }

        .trainee-register .auth-group {
            min-width: 0;
            margin: 0 0 1.5rem;
            padding: 0;
            border: 0;
        }

        .trainee-register .auth-group legend {
            width: 100%;
            margin-bottom: 1rem;
            padding: 0 0 .5rem;
            border-bottom: 1px solid #d8e4e7;
            color: #173b45;
            font-size: 1.05rem;
            font-weight: 700;
        }

        .trainee-register .auth-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem 1.25rem;
        }

        .trainee-register .auth-field {
            min-width: 0;
            margin: 0;
        }

        .trainee-register .auth-field label {
            display: block;
            margin-bottom: .45rem;
            color: #173b45;
            font-weight: 650;
        }

        .trainee-register .auth-field input {
            display: block;
            width: 100%;
            min-width: 0;
            max-width: 100%;
            min-height: 50px;
            margin: 0;
            padding: .7rem .85rem;
            border: 1px solid #a9bfc5;
            border-radius: 10px;
            background: #fff;
            color: #20343e;
            font: inherit;
            font-size: 1rem;
            line-height: 1.5;
            color-scheme: light;
        }

        .trainee-register .auth-field input:focus {
            border-color: #09665f;
        }

        .trainee-register .auth-hint {
            display: block;
            margin-top: .4rem;
            color: #536772;
            font-size: .875rem;
        }


        /* ==================================================
           REGISTER BUTTON
           ================================================== */

        .trainee-register .login-button {
            display: block;
            width: 100%;
            min-height: 50px;
            margin: 0;
            padding: .75rem 1.15rem;
            border: 1px solid #09665f;
            border-radius: 10px;
            background: #09665f;
            color: #fff;
            font: inherit;
            font-weight: 700;
            text-align: center;
            cursor: pointer;
        }

        .trainee-register .login-button:hover {
            background: #074f49;
            border-color: #074f49;
        }


        /* ==================================================
           LOGIN SECTION
           ================================================== */

        .trainee-register .auth-registration {
            margin-top: 1.75rem;
            padding-top: 1.5rem;
            border-top: 1px solid #d8e4e7;
            text-align: center;
        }

        .trainee-register .auth-registration p {
            margin: 0 0 .75rem;
            color: #536772;
        }

        .trainee-register .register-link {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: .65rem 1rem;
            border: 1px solid #a9bfc5;
            border-radius: 10px;
            background: #fff;
            font-weight: 700;
            text-decoration: none;
        }

        .trainee-register .register-link:hover {
            background: #e5f3ef;
            border-color: #09665f;
            text-decoration: none;
        }


        /* ==================================================
           FOOTER
           ================================================== */

        .trainee-register .auth-footer {
            margin-top: 1.25rem;
            text-align: center;
        }


        /* ==================================================
           RESPONSIVE
           ================================================== */

        @media (max-width: 560px) {

            .trainee-register .auth-grid {
                grid-template-columns: minmax(0, 1fr);
            }

        }

        @media (max-width: 480px) {

            .trainee-register .auth-shell {
                width: calc(100% - 1.5rem);
                padding: .75rem 0;
            }

            .trainee-register .auth-main {
                padding: 1rem 0;
            }

            .trainee-register .auth-card {
                padding: 1.5rem 1.25rem;
            }

            .trainee-register .top-navigation {
                align-items: flex-start;
                flex-direction: column;
            }

        }

    </style>

<link rel="stylesheet" href="assets/css/ui.css">
<script src="assets/js/ui.js" defer></script>
</head>

<body class="trainee-course trainee-register">

<div class="auth-shell">

    <!-- ==================================================
         TOP NAVIGATION
         ================================================== -->

    <nav
        class="top-navigation"
        aria-label="<?= htmlspecialchars(t('homepage')) ?>"
    >

        <a
            class="home-link"
            href="index.php"
        >
            &larr;
            <?= htmlspecialchars(t('homepage')) ?>
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
         REGISTRATION
         ================================================== -->

    <main class="auth-main">

        <section
            class="auth-card"
            aria-labelledby="register-heading"
        >

            <header class="auth-header">

                <a
                    class="auth-brand"
                    href="index.php"
                >
                    <?= htmlspecialchars(t('app_name')) ?>
                </a>

                <h1 id="register-heading">
                    <?= htmlspecialchars(t('create_account')) ?>
                </h1>

                <p class="auth-intro">
                    <?= htmlspecialchars(t('registration_intro')) ?>
                </p>

                <p class="required-note">
                    <?= htmlspecialchars(t('all_fields_required')) ?>
                </p>

            </header>


            <?php if ($error !== ''): ?>

                <div
                    class="auth-error"
                    role="alert"
                >
                    <strong>
                        <?= htmlspecialchars($error) ?>
                    </strong>
                </div>

            <?php endif; ?>


            <!-- ==================================================
                 REGISTRATION FORM
                 ================================================== -->

            <form
                class="auth-form"
                method="POST"
            ><?php ux_csrf_field(); ?>

                <!-- PERSONAL INFORMATION -->

                <fieldset class="auth-group">

                    <legend>
                        <?= htmlspecialchars(t('personal_information')) ?>
                    </legend>

                    <div class="auth-grid">


                        <!-- FIRST NAME -->

                        <div class="auth-field">

                            <label for="first_name">
                                <?= htmlspecialchars(t('first_name')) ?>
                            </label>

                            <input
                                type="text"
                                id="first_name"
                                name="first_name"
                                value="<?= htmlspecialchars($firstName) ?>"
                                autocomplete="given-name"
                                required
                            >

                        </div>


                        <!-- LAST NAME -->

                        <div class="auth-field">

                            <label for="last_name">
                                <?= htmlspecialchars(t('last_name')) ?>
                            </label>

                            <input
                                type="text"
                                id="last_name"
                                name="last_name"
                                value="<?= htmlspecialchars($lastName) ?>"
                                autocomplete="family-name"
                                required
                            >

                        </div>


                        <!-- DATE OF BIRTH -->

                        <div class="auth-field">

                            <label for="date_of_birth">
                                <?= htmlspecialchars(t('date_of_birth')) ?>
                            </label>

                            <input
                                type="date"
                                id="date_of_birth"
                                name="date_of_birth"
                                value="<?= htmlspecialchars($dateOfBirth) ?>"
                                max="<?= date('Y-m-d') ?>"
                                autocomplete="bday"
                                required
                            >

                        </div>


                        <!-- EMAIL -->

                        <div class="auth-field">

                            <label for="email">
                                <?= htmlspecialchars(t('email')) ?>
                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="<?= htmlspecialchars($email) ?>"
                                autocomplete="email"
                                required
                            >

                        </div>

                    </div>

                </fieldset>


                <!-- ==================================================
                     PASSWORD
                     ================================================== -->

                <fieldset class="auth-group">

                    <legend>
                        <?= htmlspecialchars(t('your_password')) ?>
                    </legend>

                    <div class="auth-grid">


                        <!-- PASSWORD -->

                        <div class="auth-field">

                            <label for="password">
                                <?= htmlspecialchars(t('password')) ?>
                            </label>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                minlength="8"
                                autocomplete="new-password"
                                aria-describedby="password-hint"
                                required
                            >

                            <small
                                class="auth-hint"
                                id="password-hint"
                            >
                                <?= htmlspecialchars(t('minimum_8_characters')) ?>
                            </small>

                        </div>


                        <!-- CONFIRM PASSWORD -->

                        <div class="auth-field">

                            <label for="confirm_password">
                                <?= htmlspecialchars(t('confirm_password')) ?>
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

                    </div>

                </fieldset>


                <!-- REGISTER -->

                <button
                    class="login-button"
                    type="submit"
                >
                    <?= htmlspecialchars(t('register_and_continue')) ?>
                </button>

            </form>


            <!-- ==================================================
                 EXISTING ACCOUNT
                 ================================================== -->

            <section
                class="auth-registration"
                aria-label="<?= htmlspecialchars(t('trainee_login_title')) ?>"
            >

                <p>
                    <?= htmlspecialchars(t('already_registered')) ?>
                </p>

                <a
                    class="register-link"
                    href="trainee_login.php"
                >
                    <?= htmlspecialchars(t('login_here')) ?>
                </a>

            </section>

        </section>


        <footer class="auth-footer">

            <a
                class="home-link"
                href="index.php"
            >
                &larr;
                <?= htmlspecialchars(t('return_to_homepage')) ?>
            </a>

        </footer>

    </main>

</div>

</body>

</html>