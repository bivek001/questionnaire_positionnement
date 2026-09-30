<?php
require_once __DIR__ . '/professor_language.php'; require_once __DIR__ . '/recovery_request.php'; ?>
<!DOCTYPE html>
<html lang="<?= professorH(htmlLanguage()) ?>">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title><?= professorH(t('p150i_fa827f4c6509')) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
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
        .forgot-password-page .reset-shell {
            width: 100%;
            min-height: 100vh;
            min-height: 100svh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 20px;
        }
        .forgot-password-page .reset-card {
            width: 100%;
            max-width: 540px;
            padding: 40px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.08);
            overflow-wrap: anywhere;
        }
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
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }
        .forgot-password-page .reset-header h1 {
            margin: 0 0 12px;
            color: #0f172a;
            font-size: clamp(1.75rem, 5vw, 2.15rem);
            line-height: 1.2;
            letter-spacing: -0.035em;
        }
        .forgot-password-page .reset-description {
            margin: 0;
            color: #475569;
            font-size: 0.975rem;
        }
        .forgot-password-page .reset-expiry {
            margin: 0 0 24px;
            padding: 12px 16px;
            color: #1e40af;
            background: #eff6ff;
            border: 1px solid #dbeafe;
            border-radius: 12px;
            font-size: 0.9rem;
            text-align: center;
        }
        .forgot-password-page .reset-notice {
            margin: 0 0 22px;
            padding: 16px 18px;
            border: 1px solid;
            border-left-width: 4px;
            border-radius: 12px;
            font-size: 0.925rem;
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
            font-size: 0.925rem;
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
        .forgot-password-page .reset-form input[aria-invalid="true"] {
            border-color: #b91c1c;
        }
        .forgot-password-page .reset-field-help {
            margin: 8px 0 0;
            color: #64748b;
            font-size: 0.825rem;
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
            box-shadow: 0 4px 10px rgba(29, 78, 216, 0.15);
        }
        .forgot-password-page .reset-submit:hover {
            background: #1e40af;
            border-color: #1e40af;
        }
        .forgot-password-page a:focus-visible,
        .forgot-password-page button:focus-visible,
        .forgot-password-page input:focus-visible {
            outline: 3px solid #2563eb;
            outline-offset: 3px;
        }
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
            font-size: 0.9rem;
        }
        .forgot-password-page .reset-development p:last-child {
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
            font-size: 0.8rem;
        }
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
            font-size: 0.875rem;
            font-weight: 700;
            text-decoration: underline;
            text-underline-offset: 4px;
        }
        .forgot-password-page .reset-navigation a:hover {
            color: #1e3a8a;
        }
        @media (max-width: 560px) {
            .forgot-password-page .reset-shell {
                padding: 24px 14px;
            }
            .forgot-password-page .reset-card {
                padding: 28px 22px;
                border-radius: 18px;
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
<script src="../assets/js/professor_language.js" defer></script>
<link rel="stylesheet" href="../assets/css/ui.css">
<script src="../assets/js/ui.js" defer></script>
</head>
<body class="forgot-password-page">
<?php require __DIR__ . '/professor_language_switcher.php'; ?>
    <main class="reset-shell">
        <div class="reset-card">
            <header class="reset-header">
                <div class="reset-icon" aria-hidden="true">
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
                        <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                        <circle cx="12" cy="15" r="1"></circle>
                        <path d="M12 16v2"></path>
                    </svg>
                </div>
                <p class="reset-eyebrow"><?= professorH(t('p150i_9c58a81e6741')) ?></p>
                <h1><?= professorH(t('forgot_password_title')) ?></h1>
                <p class="reset-description">
                    <?= professorH(t('p150i_27ea69562f93')) ?>
                </p>
            </header>
            <p class="reset-expiry">
                <?= professorH(t('p150i_a19c7d06c409')) ?>
                <strong><?= professorH(t('p150i_e0c87018f347')) ?></strong>
            </p>
            <?php if ($error !== ''): ?>
                <div
                    class="reset-notice reset-notice-error"
                    id="reset-error"
                    role="alert"
                >
                    <strong><?= professorH(t('check_email_address')) ?></strong>
                    <p><?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>
            <?php if ($message !== ''): ?>
                <div
                    class="reset-notice reset-notice-success"
                    role="status"
                    aria-live="polite"
                    aria-atomic="true"
                >
                    <strong><?= professorH(t('request_received')) ?></strong>
                    <p><?= htmlspecialchars($message) ?></p>
                </div>
            <?php endif; ?>
            <form method="POST" class="reset-form"><?php px_csrf_field(); ?>
                <label for="email"><?= professorH(t('email_address')) ?></label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="255"
                    autocomplete="email"
                    required
                    aria-describedby="email-help<?= $error !== '' ? ' reset-error' : '' ?>"
                    <?php if ($error !== ''): ?>
                        aria-invalid="true"
                    <?php endif; ?>
                >
                <p id="email-help" class="reset-field-help">
                    <?= professorH(t('p150i_48efee044e77')) ?>
                </p>
                <button type="submit" class="reset-submit">
                    <?= professorH(t('request_password_reset')) ?>
                </button>
            </form>
            <?php if ($developmentResetLink !== ''): ?>
                <section
                    class="reset-development"
                    aria-labelledby="development-heading"
                >
                    <h2 id="development-heading"><?= professorH(t('development_testing')) ?></h2>
                    <p>
                        <?= professorH(t('development_reset_description')) ?>
                    </p>
                    <p>
                        <a href="<?= htmlspecialchars($developmentResetLink) ?>">
                            <?= professorH(t('p150i_c5112bebfc22')) ?>
                        </a>
                    </p>
                    <p>
                        <small>
                            <?= professorH(t('development_reset_warning')) ?>
                        </small>
                    </p>
                </section>
            <?php endif; ?>
            <footer class="reset-footer">
                <nav
                    class="reset-navigation"
                    aria-label="<?= professorH(t('account_navigation')) ?>"
                >
                    <a href="login.php">
                        <?= professorH(t('p150i_fd818753b070')) ?>
                    </a>
                    <a href="../index.php"><?= professorH(t('homepage')) ?></a>
                </nav>
            </footer>
        </div>
    </main>
</body>
</html>