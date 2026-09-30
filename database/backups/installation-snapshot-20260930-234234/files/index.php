<?php

session_start();

require_once 'config/database.php';
require_once 'includes/language.php';

// ======================================================
// CHECK LOGIN
// ======================================================

$isLoggedIn = isset($_SESSION['trainee_id']);

$trainee = null;

// ======================================================
// LOGGED-IN TRAINEE
// ======================================================

if ($isLoggedIn) {

    $traineeId = (int)$_SESSION['trainee_id'];

    $stmt = $pdo->prepare(
        "SELECT
            id,
            first_name,
            last_name,
            email
         FROM trainees
         WHERE id = ?
         LIMIT 1"
    );

    $stmt->execute([$traineeId]);

    $trainee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trainee) {

        session_unset();
        session_destroy();

        header('Location: trainee_login.php');
        exit;
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
        <?= htmlspecialchars(
            $isLoggedIn
                ? t('course_information')
                : t('positioning_questionnaire')
        ) ?>
    </title>

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

    <style>

        /* ==================================================
           PAGE
           ================================================== */

        body.trainee-home {
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

        .trainee-home *,
        .trainee-home *::before,
        .trainee-home *::after {
            box-sizing: border-box;
        }

        .trainee-home .container {
            width: calc(100% - 2rem);
            max-width: 1120px;
            margin: 0 auto;
            padding: 2rem 0;
            background: transparent;
            border: 0;
            box-shadow: none;
        }


        /* ==================================================
           TYPOGRAPHY
           ================================================== */

        .trainee-home h1,
        .trainee-home h2,
        .trainee-home h3 {
            color: #173b45;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .trainee-home h1 {
            margin: 0.35rem 0 0.75rem;
            font-size: clamp(1.8rem, 4vw, 2.7rem);
        }

        .trainee-home h2 {
            margin: 0 0 0.75rem;
            font-size: clamp(1.35rem, 3vw, 1.8rem);
        }

        .trainee-home h3 {
            margin: 0 0 0.6rem;
            font-size: 1.1rem;
        }

        .trainee-home p {
            margin: 0 0 1rem;
        }

        .trainee-home a {
            color: #09665f;
            text-underline-offset: 0.2em;
        }

        .trainee-home a:hover {
            text-decoration: underline;
        }

        .trainee-home a:focus-visible {
            outline: 3px solid #9b4c00;
            outline-offset: 4px;
        }

        .trainee-home .text-muted {
            color: #536772;
        }


        /* ==================================================
           HEADER
           ================================================== */

        .trainee-home .trainee-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem 2rem;
            margin-bottom: 1.5rem;
            padding: 0;
            background: transparent;
            border: 0;
            text-align: left;
        }

        .trainee-home .trainee-header > * {
            min-width: 0;
        }

        .trainee-home .trainee-header p {
            margin-bottom: 0;
            overflow-wrap: anywhere;
        }

        .trainee-home .trainee-eyebrow {
            color: #09665f;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }


        /* ==================================================
           HEADER ACTION AREA
           ================================================== */

        .trainee-home .header-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            align-items: center;
            gap: 0.65rem;
        }

        .trainee-home .trainee-admin-link {
            color: #536772;
            font-size: 0.9rem;
            text-decoration: none;
        }

        .trainee-home .trainee-admin-link:hover {
            color: #09665f;
        }


        /* ==================================================
           LANGUAGE SWITCHER
           ================================================== */

        .trainee-home .language-switcher {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem;
            background: #ffffff;
            border: 1px solid #d8e4e7;
            border-radius: 10px;
        }

        .trainee-home .language-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            padding: 0.35rem 0.7rem;
            border-radius: 7px;
            color: #536772;
            font-size: 0.85rem;
            font-weight: 700;
            text-decoration: none;
        }

        .trainee-home .language-link:hover {
            background: #edf7f4;
            color: #064f49;
            text-decoration: none;
        }

        .trainee-home .language-link.active {
            background: #09665f;
            color: #ffffff;
        }


        /* ==================================================
           CARDS
           ================================================== */

        .trainee-home .card {
            margin: 0 0 1.5rem;
            padding: clamp(1.25rem, 3vw, 2rem);
            background: #fff;
            border: 1px solid #d8e4e7;
            border-radius: 18px;
            box-shadow: 0 6px 22px rgba(23, 59, 69, 0.04);
        }

        .trainee-home .trainee-hero {
            padding: clamp(1.5rem, 5vw, 3.5rem);
            background: linear-gradient(120deg, #e8f5f1, #fff 85%);
            border-top: 4px solid #08756a;
        }

        .trainee-home .trainee-hero h2 {
            max-width: 28ch;
            font-size: clamp(1.6rem, 4vw, 2.4rem);
        }

        .trainee-home .trainee-hero > p {
            max-width: 68ch;
        }


        /* ==================================================
           BUTTONS
           ================================================== */

        .trainee-home .actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.75rem;
            margin: 1.25rem 0 0;
        }

        .trainee-home .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            padding: 0.7rem 1.25rem;
            background: #09665f;
            color: #fff;
            border: 1px solid #09665f;
            border-radius: 10px;
            font: inherit;
            font-weight: 700;
            line-height: 1.4;
            text-align: center;
            text-decoration: none;
        }

        .trainee-home .btn:hover {
            background: #064f49;
            border-color: #064f49;
            text-decoration: none;
        }

        .trainee-home .btn-secondary {
            background: #fff;
            color: #09665f;
            border-color: #8baea8;
        }

        .trainee-home .btn-secondary:hover {
            background: #edf7f4;
            color: #064f49;
            border-color: #09665f;
        }


        /* ==================================================
           COURSE FLOW
           ================================================== */

        .trainee-home .trainee-flow {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 0.65rem;
            margin: 1.25rem 0 0;
            padding: 0;
            list-style: none;
        }

        .trainee-home .trainee-flow li {
            min-width: 0;
            margin: 0;
            padding: 1rem 0.75rem;
            background: #f5f8f9;
            border: 1px solid #d8e4e7;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        .trainee-home .trainee-step-number {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            margin-bottom: 0.65rem;
            background: #e1e9ed;
            color: #29434e;
            border-radius: 50%;
            font-weight: 700;
        }

        .trainee-home .trainee-flow [aria-current="step"] {
            background: #e8f5f1;
            border: 2px solid #08756a;
        }

        .trainee-home [aria-current="step"] .trainee-step-number {
            background: #09665f;
            color: #fff;
        }

        .trainee-home .trainee-current {
            display: block;
            margin-top: 0.5rem;
            color: #09665f;
            font-size: 0.75rem;
            font-weight: 700;
        }


        /* ==================================================
           PROFILE
           ================================================== */

        .trainee-home .trainee-profile {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem 3rem;
            margin: 1.25rem 0 0;
            padding-top: 1.25rem;
            border-top: 1px solid #d8e4e7;
        }

        .trainee-home .trainee-profile > div {
            min-width: 0;
        }

        .trainee-home .trainee-profile dt {
            color: #536772;
            font-size: 0.85rem;
        }

        .trainee-home .trainee-profile dd {
            margin: 0.2rem 0 0;
            font-weight: 600;
            overflow-wrap: anywhere;
        }


        /* ==================================================
           COURSE DETAILS
           ================================================== */

        .trainee-home .trainee-details {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
            margin: 1.5rem 0 0;
            padding: 0;
            list-style: none;
        }

        .trainee-home .trainee-details li {
            margin: 0;
            padding: 1.25rem;
            background: #f7fafb;
            border: 1px solid #d8e4e7;
            border-radius: 12px;
        }

        .trainee-home .trainee-details p:last-child {
            margin-bottom: 0;
        }


        /* ==================================================
           WORKFLOW NAVIGATION
           ================================================== */

        .trainee-home .workflow-navigation {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            background: #e8f5f1;
        }

        .trainee-home .workflow-navigation p {
            margin-bottom: 0;
        }


        /* ==================================================
           ACCOUNT FOOTER
           ================================================== */

        .trainee-home .trainee-account {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem 1.5rem;
            padding: 0.5rem 0 1rem;
            background: transparent;
            text-align: left;
        }

        .trainee-home .trainee-account p,
        .trainee-home .trainee-account .actions {
            margin: 0;
        }


        /* ==================================================
           RESPONSIVE
           ================================================== */

        @media (max-width: 900px) {

            .trainee-home .trainee-flow {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }

        }

        @media (max-width: 600px) {

            .trainee-home .container {
                padding-top: 1.25rem;
            }

            .trainee-home .trainee-flow {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .trainee-home .trainee-details {
                grid-template-columns: minmax(0, 1fr);
            }

            .trainee-home .actions .btn,
            .trainee-home .workflow-navigation .btn {
                width: 100%;
            }

            .trainee-home .header-actions {
                width: 100%;
                justify-content: flex-start;
            }

        }

    </style>

<link rel="stylesheet" href="assets/css/ui.css">
<script src="assets/js/ui.js" defer></script>
</head>

<body class="trainee-home">

<div class="container">

    <?php if (!$isLoggedIn): ?>

        <!-- ==================================================
             PUBLIC HOMEPAGE
             ================================================== -->

        <header class="trainee-header">

            <div>

                <p class="trainee-eyebrow">
                    <?= htmlspecialchars(t('training_assessment_portal')) ?>
                </p>

                <h1>
                    <?= htmlspecialchars(t('positioning_questionnaire')) ?>
                </h1>

            </div>


            <div class="header-actions">

                <!-- LANGUAGE -->

                <nav
                    class="language-switcher"
                    aria-label="<?= htmlspecialchars(t('select_language')) ?>"
                >

                    <a
                        class="language-link <?= isLanguage('en') ? 'active' : '' ?>"
                        href="<?= htmlspecialchars(languageUrl('en')) ?>"
                    >
                        English
                    </a>

                    <a
                        class="language-link <?= isLanguage('fr') ? 'active' : '' ?>"
                        href="<?= htmlspecialchars(languageUrl('fr')) ?>"
                    >
                        Français
                    </a>

                </nav>


                <a
                    class="trainee-admin-link"
                    href="admin/login.php"
                >
                    <?= htmlspecialchars(t('admin_login')) ?>
                </a>

            </div>

        </header>


        <main>

            <!-- WELCOME -->

            <section
                class="card trainee-hero"
                aria-labelledby="welcome-title"
            >

                <h2 id="welcome-title">
                    <?= htmlspecialchars(t('welcome_training_portal')) ?>
                </h2>

                <p>
                    <?= htmlspecialchars(t('homepage_description')) ?>
                </p>

                <p class="text-muted">
                    <?= htmlspecialchars(t('please_login_begin')) ?>
                </p>

                <div class="actions">

                    <a
                        class="btn"
                        href="trainee_login.php"
                    >
                        <?= htmlspecialchars(t('trainee_login')) ?>
                    </a>

                    <a
                        class="btn btn-secondary"
                        href="register.php"
                    >
                        <?= htmlspecialchars(t('create_account')) ?>
                    </a>

                    <a
                        class="btn btn-secondary"
                        href="professor/login.php"
                    >
                        <?= htmlspecialchars(t('professor_login')) ?>
                    </a>

                </div>

            </section>


            <!-- COURSE FLOW -->

            <section
                class="card"
                aria-labelledby="flow-title"
            >

                <h2 id="flow-title">
                    <?= htmlspecialchars(t('trainee_course_flow')) ?>
                </h2>

                <p class="text-muted">
                    <?= htmlspecialchars(t('course_flow_description')) ?>
                </p>

                <ol
                    class="trainee-flow"
                    role="list"
                >

                    <li>

                        <span
                            class="trainee-step-number"
                            aria-hidden="true"
                        >
                            1
                        </span>

                        <?= htmlspecialchars(t('course_information')) ?>

                    </li>


                    <li>

                        <span
                            class="trainee-step-number"
                            aria-hidden="true"
                        >
                            2
                        </span>

                        <?= htmlspecialchars(t('sommaire')) ?>

                    </li>


                    <li>

                        <span
                            class="trainee-step-number"
                            aria-hidden="true"
                        >
                            3
                        </span>

                        <?= htmlspecialchars(t('introduction')) ?>

                    </li>


                    <li>

                        <span
                            class="trainee-step-number"
                            aria-hidden="true"
                        >
                            4
                        </span>

                        <?= htmlspecialchars(
                            t('chapters_lessons_topics_paragraphs')
                        ) ?>

                    </li>


                    <li>

                        <span
                            class="trainee-step-number"
                            aria-hidden="true"
                        >
                            5
                        </span>

                        <?= htmlspecialchars(t('assigned_questionnaires')) ?>

                    </li>


                    <li>

                        <span
                            class="trainee-step-number"
                            aria-hidden="true"
                        >
                            6
                        </span>

                        <?= htmlspecialchars(t('available_questionnaires')) ?>

                    </li>


                    <li>

                        <span
                            class="trainee-step-number"
                            aria-hidden="true"
                        >
                            7
                        </span>

                        <?= htmlspecialchars(
                            t('questionnaire_history_results')
                        ) ?>

                    </li>

                </ol>

            </section>

        </main>


    <?php else: ?>

        <!-- ==================================================
             LOGGED-IN HEADER
             ================================================== -->

        <header class="trainee-header">

            <div>

                <p class="trainee-eyebrow">
                    <?= htmlspecialchars(t('positioning_questionnaire')) ?>
                </p>

                <h1>
                    <?= htmlspecialchars(t('course_information')) ?>
                </h1>

            </div>


            <div class="header-actions">

                <nav
                    class="language-switcher"
                    aria-label="<?= htmlspecialchars(t('select_language')) ?>"
                >

                    <a
                        class="language-link <?= isLanguage('en') ? 'active' : '' ?>"
                        href="<?= htmlspecialchars(languageUrl('en')) ?>"
                    >
                        English
                    </a>

                    <a
                        class="language-link <?= isLanguage('fr') ? 'active' : '' ?>"
                        href="<?= htmlspecialchars(languageUrl('fr')) ?>"
                    >
                        Français
                    </a>

                </nav>


                <p class="text-muted">

                    <?= htmlspecialchars(t('logged_in_as')) ?>:

                    <strong>

                        <?= htmlspecialchars($trainee['first_name']) ?>

                        <?= htmlspecialchars($trainee['last_name']) ?>

                    </strong>

                </p>

            </div>

        </header>


        <!-- ==================================================
             COURSE PROGRESS
             ================================================== -->

        <nav
            class="card"
            aria-labelledby="progress-title"
        >

            <h2 id="progress-title">
                <?= htmlspecialchars(t('course_progress')) ?>
            </h2>

            <ol
                class="trainee-flow"
                role="list"
            >

                <li aria-current="step">

                    <span
                        class="trainee-step-number"
                        aria-hidden="true"
                    >
                        1
                    </span>

                    <?= htmlspecialchars(t('course_information')) ?>

                    <span class="trainee-current">
                        <?= htmlspecialchars(t('current_step')) ?>
                    </span>

                </li>


                <li>

                    <span
                        class="trainee-step-number"
                        aria-hidden="true"
                    >
                        2
                    </span>

                    <?= htmlspecialchars(t('sommaire')) ?>

                </li>


                <li>

                    <span
                        class="trainee-step-number"
                        aria-hidden="true"
                    >
                        3
                    </span>

                    <?= htmlspecialchars(t('introduction')) ?>

                </li>


                <li>

                    <span
                        class="trainee-step-number"
                        aria-hidden="true"
                    >
                        4
                    </span>

                    <?= htmlspecialchars(t('course_content')) ?>

                </li>


                <li>

                    <span
                        class="trainee-step-number"
                        aria-hidden="true"
                    >
                        5
                    </span>

                    <?= htmlspecialchars(t('assigned_questionnaires')) ?>

                </li>


                <li>

                    <span
                        class="trainee-step-number"
                        aria-hidden="true"
                    >
                        6
                    </span>

                    <?= htmlspecialchars(t('available_questionnaires')) ?>

                </li>


                <li>

                    <span
                        class="trainee-step-number"
                        aria-hidden="true"
                    >
                        7
                    </span>

                    <?= htmlspecialchars(t('history_results')) ?>

                </li>

            </ol>

        </nav>


        <main>

            <!-- ==================================================
                 TRAINEE INFORMATION
                 ================================================== -->

            <section
                class="card trainee-hero"
                aria-labelledby="profile-title"
            >

                <h2 id="profile-title">

                    <?= htmlspecialchars(
                        t(
                            'welcome_trainee',
                            [
                                'name' => $trainee['first_name']
                            ]
                        )
                    ) ?>

                </h2>

                <p>
                    <?= htmlspecialchars(t('entering_course_area')) ?>
                </p>


                <dl class="trainee-profile">

                    <div>

                        <dt>
                            <?= htmlspecialchars(t('trainee_id')) ?>
                        </dt>

                        <dd>
                            <?= (int)$trainee['id'] ?>
                        </dd>

                    </div>


                    <div>

                        <dt>
                            <?= htmlspecialchars(t('email')) ?>
                        </dt>

                        <dd>
                            <?= htmlspecialchars($trainee['email']) ?>
                        </dd>

                    </div>

                </dl>

            </section>


            <!-- ==================================================
                 COURSE INFORMATION
                 ================================================== -->

            <section
                class="card"
                aria-labelledby="course-title"
            >

                <h2 id="course-title">
                    <?= htmlspecialchars(t('how_course_works')) ?>
                </h2>

                <p class="text-muted">
                    <?= htmlspecialchars(t('follow_course_order')) ?>
                </p>


                <ol
                    class="trainee-details"
                    role="list"
                >

                    <li>

                        <h3>
                            <?= htmlspecialchars(
                                t('course_step_sommaire_title')
                            ) ?>
                        </h3>

                        <p>
                            <?= htmlspecialchars(
                                t('course_step_sommaire_description')
                            ) ?>
                        </p>

                    </li>


                    <li>

                        <h3>
                            <?= htmlspecialchars(
                                t('course_step_introduction_title')
                            ) ?>
                        </h3>

                        <p>
                            <?= htmlspecialchars(
                                t('course_step_introduction_description')
                            ) ?>
                        </p>

                    </li>


                    <li>

                        <h3>
                            <?= htmlspecialchars(
                                t('course_step_content_title')
                            ) ?>
                        </h3>

                        <p>
                            <?= htmlspecialchars(
                                t('course_step_content_description')
                            ) ?>
                        </p>

                        <p>
                            <strong>
                                <?= htmlspecialchars(t('course_hierarchy')) ?>
                            </strong>
                        </p>

                    </li>


                    <li>

                        <h3>
                            <?= htmlspecialchars(
                                t('course_step_assigned_title')
                            ) ?>
                        </h3>

                        <p>
                            <?= htmlspecialchars(
                                t('course_step_assigned_description')
                            ) ?>
                        </p>

                    </li>


                    <li>

                        <h3>
                            <?= htmlspecialchars(
                                t('course_step_available_title')
                            ) ?>
                        </h3>

                        <p>
                            <?= htmlspecialchars(
                                t('course_step_available_description')
                            ) ?>
                        </p>

                        <p>
                            <?= htmlspecialchars(
                                t('course_step_available_initial')
                            ) ?>
                        </p>

                    </li>


                    <li>

                        <h3>
                            <?= htmlspecialchars(
                                t('course_step_results_title')
                            ) ?>
                        </h3>

                        <p>
                            <?= htmlspecialchars(
                                t('course_step_results_description')
                            ) ?>
                        </p>

                    </li>

                </ol>

            </section>


            <!-- ==================================================
                 NEXT
                 ================================================== -->

            <section
                class="card workflow-navigation"
                aria-labelledby="next-title"
            >

                <div>

                    <h2 id="next-title">
                        <?= htmlspecialchars(t('ready_to_continue')) ?>
                    </h2>

                    <p>
                        <?= htmlspecialchars(t('next_page_sommaire')) ?>
                    </p>

                </div>


                <a
                    class="btn"
                    href="sommaire.php"
                >
                    <?= htmlspecialchars(t('next_sommaire')) ?>
                </a>

            </section>

        </main>


        <!-- ==================================================
             ACCOUNT
             ================================================== -->

        <footer class="trainee-account">

            <p class="text-muted">
                <?= htmlspecialchars(t('your_trainee_account')) ?>
            </p>

            <nav
                class="actions"
                aria-label="<?= htmlspecialchars(t('account')) ?>"
            >

                <a
                    class="btn btn-secondary"
                    href="change_password.php"
                >
                    <?= htmlspecialchars(t('change_password')) ?>
                </a>

                <a
                    class="btn btn-secondary"
                    href="trainee_logout.php"
                >
                    <?= htmlspecialchars(t('logout')) ?>
                </a>

            </nav>

        </footer>

    <?php endif; ?>

</div>

</body>

</html>