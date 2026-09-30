<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once dirname(__DIR__).'/includes/language.php';

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
        <?= htmlspecialchars(t('app_name')) ?>
    </title>

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

    <style>

        .language-test {
            max-width: 700px;
            margin: 50px auto;
            padding: 30px;
        }

        .language-switcher {
            display: flex;
            gap: 10px;
            margin: 20px 0;
        }

        .language-current {
            margin: 20px 0;
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
        }

        .translation-test {
            margin-top: 25px;
        }

        .translation-test p {
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }

    </style>

<link rel="stylesheet" href="assets/css/ui.css">
<script src="assets/js/ui.js" defer></script>
</head>

<body>

<div class="container">

    <main class="language-test">

        <h1>
            <?= htmlspecialchars(t('app_name')) ?>
        </h1>


        <div class="language-current">

            <strong>
                <?= htmlspecialchars(t('language')) ?>:
            </strong>

            <?= currentLanguage() === 'fr'
                ? 'Français'
                : 'English' ?>

        </div>


        <!-- ============================================= -->
        <!-- LANGUAGE SWITCHER -->
        <!-- ============================================= -->

        <div class="language-switcher">

            <a
                class="btn"
                href="<?= htmlspecialchars(
                    languageUrl('en')
                ) ?>"
            >
                English
            </a>


            <a
                class="btn"
                href="<?= htmlspecialchars(
                    languageUrl('fr')
                ) ?>"
            >
                Français
            </a>

        </div>


        <!-- ============================================= -->
        <!-- TRANSLATION TEST -->
        <!-- ============================================= -->

        <section class="translation-test">

            <h2>
                <?= htmlspecialchars(t('welcome')) ?>
            </h2>


            <p>
                <strong>
                    <?= htmlspecialchars(t('login')) ?>
                </strong>
            </p>


            <p>
                <?= htmlspecialchars(t('dashboard')) ?>
            </p>


            <p>
                <?= htmlspecialchars(t('professor')) ?>
            </p>


            <p>
                <?= htmlspecialchars(t('trainee')) ?>
            </p>


            <p>
                <?= htmlspecialchars(t('questions')) ?>
            </p>


            <p>
                <?= htmlspecialchars(t('course_content')) ?>
            </p>


            <p>
                <?= htmlspecialchars(t('results')) ?>
            </p>


            <p>
                <?= htmlspecialchars(t(
                    'attempt_number',
                    [
                        'id' => 15
                    ]
                )) ?>
            </p>

        </section>


        <p>

            <a
                class="btn btn-secondary"
                href="index.php"
            >
                <?= htmlspecialchars(
                    t('back_to_homepage')
                ) ?>
            </a>

        </p>

    </main>

</div>

</body>

</html>