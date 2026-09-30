<?php require_once __DIR__ . '/admin_language.php'; ?>
<nav aria-label="<?= adminH(t('language')) ?>" style="display:flex;justify-content:flex-end;gap:1rem;padding:.75rem 1rem;flex-wrap:wrap">
    <a href="<?= adminH(languageUrl('en')) ?>" lang="en" hreflang="en" <?= isLanguage('en') ? 'aria-current="true"' : '' ?>>English</a>
    <a href="<?= adminH(languageUrl('fr')) ?>" lang="fr" hreflang="fr" <?= isLanguage('fr') ? 'aria-current="true"' : '' ?>>Français</a>
</nav>
