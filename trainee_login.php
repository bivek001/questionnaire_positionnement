<?php

session_start();

require_once 'config/database.php';
require_once 'includes/language.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__.'/includes/account_security.php';
ux_csrf_check();

$error = '';
$email = '';
$dateOfBirth = '';


// ======================================================
// ALREADY LOGGED IN
// ======================================================

if (isset($_SESSION['trainee_id'])) {

    header('Location: index.php');
    exit;
}


// ======================================================
// PROCESS LOGIN
// ======================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $dateOfBirth = trim($_POST['date_of_birth'] ?? '');

    // Do not trim passwords.
    $password = $_POST['password'] ?? '';


    if (
        $email === '' ||
        $dateOfBirth === '' ||
        $password === ''
    ) {

        $error = t('please_complete_all_fields');

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = t('valid_email_required');

    } elseif (!DateTime::createFromFormat('Y-m-d', $dateOfBirth)) {

        $error = t('valid_date_of_birth_required');

    } else {

        $stmt = $pdo->prepare(
            "SELECT
                id,
                first_name,
                last_name,
                email,
                date_of_birth,
                password
             FROM trainees
             WHERE email = ?
               AND date_of_birth = ?
             LIMIT 1"
        );

        $stmt->execute([
            $email,
            $dateOfBirth
        ]);

        $trainee = $stmt->fetch(PDO::FETCH_ASSOC);


        if (
            !$trainee ||
            empty($trainee['password']) ||
            !password_verify(
                $password,
                $trainee['password']
            )
        ) {

            // Keep this message general so that we do not
            // reveal which credential was incorrect.
            $error = t('invalid_trainee_credentials');

        } else {

            session_regenerate_id(true);

            $_SESSION['trainee_id'] =
                (int)$trainee['id'];

            $_SESSION['first_name'] =
                $trainee['first_name'];

            $_SESSION['last_name'] =
                $trainee['last_name'];

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
        <?= htmlspecialchars(t('trainee_login_title')) ?>
    </title>

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

    <style>

        /* ==================================================
           PAGE
           ================================================== */

        body.trainee-login {
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

        .trainee-login *,
        .trainee-login *::before,
        .trainee-login *::after {
            box-sizing: border-box;
        }

        .trainee-login .auth-shell {
            width: calc(100% - 2rem);
            max-width: 1100px;
            min-height: 100vh;
            margin: 0 auto;
            padding: 1.5rem 0;
            display: flex;
            flex-direction: column;
        }

        .trainee-login a {
            color: #09665f;
            text-underline-offset: .2em;
        }

        .trainee-login a:hover {
            color: #074f49;
        }

        .trainee-login a:focus-visible,
        .trainee-login button:focus-visible,
        .trainee-login input:focus-visible {
            outline: 3px solid #9b4c00;
            outline-offset: 4px;
        }


        /* ==================================================
           TOP NAVIGATION
           ================================================== */

        .trainee-login .top-navigation {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .trainee-login .home-link {
            display: inline-flex;
            align-items: center;
            min-height: 44px;
            font-weight: 600;
            text-decoration: none;
        }

        .trainee-login .home-link:hover {
            text-decoration: underline;
        }


        /* ==================================================
           LANGUAGE SWITCHER
           ================================================== */

        .trainee-login .language-switcher {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .3rem;
            background: #ffffff;
            border: 1px solid #d8e4e7;
            border-radius: 10px;
        }

        .trainee-login .language-link {
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

        .trainee-login .language-link:hover {
            background: #edf7f4;
            color: #064f49;
            text-decoration: none;
        }

        .trainee-login .language-link.active {
            background: #09665f;
            color: #ffffff;
        }


        /* ==================================================
           MAIN
           ================================================== */

        .trainee-login .auth-main {
            flex: 1;
            display: grid;
            align-content: center;
            justify-items: center;
            padding: 2rem 0;
        }

        .trainee-login .auth-card {
            width: 100%;
            max-width: 490px;
            min-width: 0;
            padding: clamp(1.25rem, 4vw, 2.5rem);
            background: #fff;
            border: 1px solid #d8e4e7;
            border-top: 5px solid #09665f;
            border-radius: 18px;
            box-shadow: 0 6px 22px rgba(23, 59, 69, .06);
            overflow-wrap: anywhere;
        }

        .trainee-login .auth-header {
            margin-bottom: 1.75rem;
            text-align: left;
        }

        .trainee-login .auth-brand {
            display: inline-block;
            margin-bottom: .75rem;
            font-size: .8rem;
            font-weight: 750;
            letter-spacing: .08em;
            text-transform: uppercase;
            text-decoration: none;
        }

        .trainee-login h1 {
            margin: 0 0 .75rem;
            color: #173b45;
            font-size: clamp(1.8rem, 5vw, 2.25rem);
            line-height: 1.25;
        }

        .trainee-login .auth-intro {
            margin: 0;
            color: #536772;
        }


        /* ==================================================
           ERROR
           ================================================== */

        .trainee-login .auth-error {
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

        .trainee-login .auth-form {
            margin: 0;
            padding: 0;
        }

        .trainee-login .auth-field {
            margin-bottom: 1.25rem;
        }

        .trainee-login .auth-field label {
            display: block;
            margin-bottom: .45rem;
            color: #173b45;
            font-weight: 650;
        }

        .trainee-login .auth-field input {
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

        .trainee-login .auth-field input:focus {
            border-color: #09665f;
        }

        .trainee-login .forgot-password {
            margin: -.5rem 0 1rem;
            text-align: right;
        }

        .trainee-login .forgot-password a {
            display: inline-flex;
            align-items: center;
            min-height: 44px;
            font-size: .95rem;
            font-weight: 600;
        }


        /* ==================================================
           LOGIN BUTTON
           ================================================== */

        .trainee-login .login-button {
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

        .trainee-login .login-button:hover {
            background: #074f49;
            border-color: #074f49;
        }


        /* ==================================================
           REGISTRATION
           ================================================== */

        .trainee-login .auth-registration {
            margin-top: 1.75rem;
            padding-top: 1.5rem;
            border-top: 1px solid #d8e4e7;
            text-align: center;
        }

        .trainee-login .auth-registration p {
            margin: 0 0 .75rem;
            color: #536772;
        }

        .trainee-login .register-link {
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

        .trainee-login .register-link:hover {
            background: #e5f3ef;
            border-color: #09665f;
            text-decoration: none;
        }


        /* ==================================================
           FOOTER
           ================================================== */

        .trainee-login .auth-footer {
            margin-top: 1.25rem;
            text-align: center;
        }


        /* ==================================================
           RESPONSIVE
           ================================================== */

        @media (max-width: 480px) {

            .trainee-login .auth-shell {
                width: calc(100% - 1.5rem);
                padding: .75rem 0;
            }

            .trainee-login .auth-main {
                padding: 1rem 0;
            }

            .trainee-login .auth-card {
                padding: 1.5rem 1.25rem;
            }

            .trainee-login .top-navigation {
                align-items: flex-start;
                flex-direction: column;
            }

        }

    </style>

<link rel="stylesheet" href="assets/css/ui.css">
<script src="assets/js/ui.js" defer></script>
</head>

<body class="trainee-course trainee-login">

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
         LOGIN
         ================================================== -->

    <main class="auth-main">

        <section
            class="auth-card"
            aria-labelledby="login-heading"
        >

            <header class="auth-header">

                <a
                    class="auth-brand"
                    href="index.php"
                >
                    <?= htmlspecialchars(t('app_name')) ?>
                </a>

                <h1 id="login-heading">
                    <?= htmlspecialchars(t('trainee_login_title')) ?>
                </h1>

                <p class="auth-intro">
                    <?= htmlspecialchars(t('trainee_login_intro')) ?>
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


            <form
                class="auth-form"
                method="POST"
            ><?php ux_csrf_field(); ?>

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


                <!-- PASSWORD -->

                <div class="auth-field">

                    <label for="password">
                        <?= htmlspecialchars(t('password')) ?>
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="current-password"
                        required
                    >

                </div>


                <!-- FORGOT PASSWORD -->

                <p class="forgot-password">

                    <a href="forgot_password.php">
                        <?= htmlspecialchars(t('forgot_password')) ?>
                    </a>

                </p>


                <!-- LOGIN -->

                <button
                    class="login-button"
                    type="submit"
                >
                    <?= htmlspecialchars(t('login')) ?>
                </button>

            </form>


            <!-- REGISTRATION -->

            <section
                class="auth-registration"
                aria-label="<?= htmlspecialchars(t('register')) ?>"
            >

                <p>
                    <?= htmlspecialchars(t('new_trainee')) ?>
                </p>

                <a
                    class="register-link"
                    href="register.php"
                >
                    <?= htmlspecialchars(t('create_account')) ?>
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