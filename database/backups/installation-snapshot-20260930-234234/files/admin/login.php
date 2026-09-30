<?php
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/admin_language.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once '../config/database.php';


// ======================================================
// ALREADY LOGGED IN
// ======================================================

if (isset($_SESSION['admin_id'])) {

    header('Location: index.php?lang=' . rawurlencode(currentLanguage()));
    exit;
}


$error = '';
$username = '';


// ======================================================
// PROCESS LOGIN
// ======================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim(
        $_POST['username'] ?? ''
    );

    // Do not trim passwords.
    $password =
        $_POST['password'] ?? '';


    // --------------------------------------------------
    // REQUIRED FIELDS
    // --------------------------------------------------

    if (
        $username === '' ||
        $password === ''
    ) {

        $error =
            t('p150i_4c8928c9b66f');

    } else {

        // ----------------------------------------------
        // FIND ADMIN
        // ----------------------------------------------

        $stmt = $pdo->prepare(
            "SELECT
                id,
                username,
                password

             FROM admins

             WHERE username = ?

             LIMIT 1"
        );

        $stmt->execute([
            $username
        ]);

        $admin =
            $stmt->fetch(PDO::FETCH_ASSOC);


        // ----------------------------------------------
        // VERIFY PASSWORD
        // ----------------------------------------------

        if (
            $admin &&
            !empty($admin['password']) &&
            password_verify(
                $password,
                $admin['password']
            )
        ) {

            // Prevent session fixation.
            session_regenerate_id(true);


            $_SESSION['admin_id'] =
                (int)$admin['id'];

            $_SESSION['admin_username'] =
                $admin['username'];


            header('Location: index.php?lang=' . rawurlencode(currentLanguage()));
            exit;

        } else {

            /*
             * General message so we do not reveal
             * whether the username exists.
             */
            $error =
                t('invalid_credentials');
        }
    }
}

?>

<!DOCTYPE html>

<html lang="<?= adminH(htmlLanguage()) ?>">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= adminH(t('a150j_4afa1302127b')) ?>
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>


<body>
<?php require __DIR__ . '/admin_language_switcher.php'; ?>

<div class="container">


    <!-- ================================================= -->
    <!-- HOMEPAGE NAVIGATION -->
    <!-- ================================================= -->

    <p>

        <a href="../index.php?lang=<?= adminH(currentLanguage()) ?>">
            <?= adminH(t('a150j_0a1604e803de')) ?>
        </a>

    </p>


    <hr>


    <!-- ================================================= -->
    <!-- HEADER -->
    <!-- ================================================= -->

    <header>

        <h1>
            <?= adminH(t('p150i_7b3acab7d31d')) ?>
        </h1>

        <h2>
            <?= adminH(t('admin_login')) ?>
        </h2>

        <p>
            <?= adminH(t('a150j_a84bf330aaa2')) ?>
        </p>

    </header>


    <!-- ================================================= -->
    <!-- ERROR -->
    <!-- ================================================= -->

    <?php if ($error !== ''): ?>

        <p>

            <strong>

                <?= htmlspecialchars(
                    $error
                ) ?>

            </strong>

        </p>

    <?php endif; ?>


    <!-- ================================================= -->
    <!-- LOGIN FORM -->
    <!-- ================================================= -->

    <main>

        <form method="POST">
<input type="hidden" name="lang" value="<?= adminH(currentLanguage()) ?>">


            <!-- USERNAME -->

            <div>

                <label for="username">

                    <strong>
                        <?= adminH(t('username')) ?>
                    </strong>

                </label>

                <br>

                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?= htmlspecialchars(
                        $username
                    ) ?>"
                    autocomplete="username"
                    required
                >

            </div>


            <br>


            <!-- PASSWORD -->

            <div>

                <label for="password">

                    <strong>
                        <?= adminH(t('password')) ?>
                    </strong>

                </label>

                <br>

                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >

            </div>


            <!-- FORGOT PASSWORD -->

            <p>

                <a href="forgot_password.php?lang=<?= adminH(currentLanguage()) ?>">
                    <?= adminH(t('forgot_password')) ?>
                </a>

            </p>


            <!-- LOGIN BUTTON -->

            <button type="submit">
                <?= adminH(t('login')) ?>
            </button>


        </form>

    </main>


    <hr>


    <!-- ================================================= -->
    <!-- FOOTER -->
    <!-- ================================================= -->

    <footer>

        <p>

            <a href="../index.php?lang=<?= adminH(currentLanguage()) ?>">
                <?= adminH(t('a150j_9615dba328a9')) ?>
            </a>

        </p>

    </footer>


</div>

</body>

</html>