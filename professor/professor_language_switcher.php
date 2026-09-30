<?php require_once __DIR__ . '/professor_language.php'; ?>
<nav class="professor-language" aria-label="<?= professorH(t('language')) ?>" style="display:flex;justify-content:flex-end;gap:1rem;padding:1rem;flex-wrap:wrap">
<?php foreach (['en' => 'English', 'fr' => 'Français'] as $code => $label): ?>
<a href="<?= professorH(languageUrl($code)) ?>" lang="<?= $code ?>" hreflang="<?= $code ?>" data-professor-language="<?= $code ?>" data-unsaved-message="<?= professorH(t('p150i_d190fa28c7fb')) ?>" <?= isLanguage($code) ? 'aria-current="true"' : '' ?>><?= $label ?></a>
<?php endforeach; ?>
</nav>
