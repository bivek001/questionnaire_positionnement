<?php
require_once __DIR__ . '/professor_language.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once '../config/database.php';

$error = '';
$username = '';


// ======================================================
// ALREADY LOGGED IN
// ======================================================

if (isset($_SESSION['professor_id'])) {
    header('Location: index.php');
    exit;
}


// ======================================================
// PROCESS LOGIN
// ======================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';


    // --------------------------------------------------
    // VALIDATION
    // --------------------------------------------------

    if ($username === '' || $password === '') {

        $error = t('p150i_4c8928c9b66f');

    } else {

        // --------------------------------------------------
        // FIND PROFESSOR
        // --------------------------------------------------

        $stmt = $pdo->prepare(
            "SELECT
                id,
                first_name,
                last_name,
                email,
                username,
                password,
                is_active
             FROM professors
             WHERE username = ?
             LIMIT 1"
        );

        $stmt->execute([$username]);

        $professor = $stmt->fetch(PDO::FETCH_ASSOC);


        // --------------------------------------------------
        // VERIFY ACCOUNT
        // --------------------------------------------------

        if (
            $professor &&
            (int)$professor['is_active'] === 1 &&
            !empty($professor['password']) &&
            password_verify($password, $professor['password'])
        ) {

            session_regenerate_id(true);

            $_SESSION['professor_id'] =
                (int)$professor['id'];

            $_SESSION['professor_username'] =
                $professor['username'];

            $_SESSION['professor_name'] =
                trim(
                    $professor['first_name']
                    . ' '
                    . $professor['last_name']
                );

            header('Location: index.php');
            exit;
        }


        // Generic error avoids revealing whether
        // the username exists or the account is inactive.

        $error =
            t('p150i_e8e0cf8553a8');
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

    <title><?= professorH(t('professor_login')) ?></title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <style>
        body.professor-login-page {
            margin: 0;
            min-height: 100vh;
            background: #f3f6fb;
            font-family: Arial, Helvetica, sans-serif;
            color: #1e293b;
        }

        .professor-login-page * {
            box-sizing: border-box;
        }

        .professor-login-shell {
            min-height: 100vh;
            min-height: 100svh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }

        .professor-login-card {
            width: 100%;
            max-width: 480px;
            padding: 40px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.08);
        }

        .professor-login-header {
            margin-bottom: 28px;
            text-align: center;
        }

        .professor-login-badge {
            display: inline-block;
            margin-bottom: 12px;
            padding: 7px 12px;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .professor-login-header h1 {
            margin: 0 0 10px;
            color: #0f172a;
            font-size: 2rem;
        }

        .professor-login-header p {
            margin: 0;
            color: #64748b;
        }

        .professor-login-error {
            margin-bottom: 22px;
            padding: 14px 16px;
            color: #991b1b;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-left: 4px solid #dc2626;
            border-radius: 10px;
        }

        .professor-login-form {
            margin: 0;
            padding: 0;
            background: transparent;
            border: 0;
            box-shadow: none;
        }

        .professor-login-field {
            margin-bottom: 20px;
        }

        .professor-login-field label {
            display: block;
            margin-bottom: 8px;
            color: #1e293b;
            font-weight: 700;
        }

        .professor-login-field input {
            width: 100%;
            min-height: 50px;
            padding: 12px 14px;
            border: 1px solid #94a3b8;
            border-radius: 10px;
            font: inherit;
        }

        .professor-login-field input:focus {
            border-color: #2563eb;
            outline: 3px solid rgba(37, 99, 235, 0.15);
        }

        .professor-login-button {
            width: 100%;
            min-height: 50px;
            padding: 12px 18px;
            color: #ffffff;
            background: #1d4ed8;
            border: 1px solid #1d4ed8;
            border-radius: 10px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .professor-login-button:hover {
            background: #1e40af;
            border-color: #1e40af;
        }

        .professor-login-links {
            margin-top: 26px;
            padding-top: 22px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
        }

        .professor-login-links p {
            margin: 8px 0;
        }

        .professor-login-links a {
            color: #1d4ed8;
            font-weight: 600;
        }

        @media (max-width: 520px) {
            .professor-login-shell {
                padding: 20px 14px;
            }

            .professor-login-card {
                padding: 28px 22px;
                border-radius: 18px;
            }
        }
    </style>

<script src="../assets/js/professor_language.js" defer></script>
<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>

<body class="professor-login-page">
<?php require __DIR__ . '/professor_language_switcher.php'; ?>

<main class="professor-login-shell">

    <section class="professor-login-card">

        <header class="professor-login-header">

            <span class="professor-login-badge">
                <?= professorH(t('professor_area')) ?>
            </span>

            <h1>
                <?= professorH(t('professor_login')) ?>
            </h1>

            <p>
                <?= professorH(t('p150i_33190c1490cf')) ?>
            </p>

        </header>


        <?php if ($error !== ''): ?>

            <div
                class="professor-login-error"
                role="alert"
            >
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <form
            method="POST"
            class="professor-login-form"
        >

            <div class="professor-login-field">

                <label for="username">
                    <?= professorH(t('username')) ?>
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?= htmlspecialchars($username) ?>"
                    maxlength="100"
                    autocomplete="username"
                    required
                >

            </div>


            <div class="professor-login-field">

                <label for="password">
                    <?= professorH(t('password')) ?>
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >

            </div>


            <button
                type="submit"
                class="professor-login-button"
            >
                <?= professorH(t('sign_in')) ?>
            </button>

        </form>


        <footer class="professor-login-links">

            <p>
                <a href="forgot_password.php">
                    <?= professorH(t('forgot_password')) ?>
                </a>
            </p>

            <p>
                <a href="../index.php">
                    <?= professorH(t('p150i_026abb5ba746')) ?>
                </a>
            </p>

        </footer>

    </section>

</main>

</body>
</html>