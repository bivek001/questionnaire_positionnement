<?php
// Included by authenticated trainee pages after their existing access checks.
$h150IsQuestionnaire = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'questionnaire.php';
?>
<nav class="h150-language" aria-label="<?= htmlspecialchars(t('select_language'), ENT_QUOTES, 'UTF-8') ?>">
    <?php foreach (['en' => 'English', 'fr' => 'Français'] as $h150Language => $h150Label): ?>
        <?php if ($h150IsQuestionnaire): ?>
            <button type="submit" form="h150-questionnaire" formmethod="post"
                    formaction="<?= htmlspecialchars(languageUrl($h150Language), ENT_QUOTES, 'UTF-8') ?>"
                    formnovalidate name="h150_language_switch" value="1"
                    lang="<?= $h150Language ?>"
                    <?= isLanguage($h150Language) ? 'aria-current="true"' : '' ?>>
                <?= $h150Label ?>
            </button>
        <?php else: ?>
            <a href="<?= htmlspecialchars(languageUrl($h150Language), ENT_QUOTES, 'UTF-8') ?>"
               lang="<?= $h150Language ?>" hreflang="<?= $h150Language ?>"
               <?= isLanguage($h150Language) ? 'aria-current="true"' : '' ?>>
                <?= $h150Label ?>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>

<?php require_once __DIR__.'/notifications.php'; if(isset($pdo)) pkg_notifications($pdo); ?>
