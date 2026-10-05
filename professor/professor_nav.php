<?php
require_once __DIR__ . '/professor_language.php'; require_once __DIR__.'/../includes/positioning_ux.php'; $currentPage = basename($_SERVER['SCRIPT_NAME']); ?>
<nav class="admin-navigation" aria-label="<?= professorH(t('p150i_580d74bdfa39')) ?>">
<strong><?= professorH(t('professor_area')) ?></strong>
<p>
<?php foreach (['index.php'=>t('dashboard'),'course.php'=>pu('Course','Cours'),'course_results.php'=>pu('Course results','Résultats par cours'),'themes.php'=>t('themes'),'content.php'=>t('p150i_170767442d23'),'manage_content.php'=>t('p150i_844717d6e9c8'),'structure.php'=>t('p150i_b6b7c4908249'),'questions.php'=>t('questions'),'trainees.php'=>t('trainees_results'),'trainee_question_status.php'=>t('questionnaire_status')] as $url=>$label): ?>
<a href="<?= $url ?>" <?= $currentPage === $url ? 'aria-current="page"' : '' ?>><?= $label ?></a> &nbsp;
<?php endforeach; ?>
</p>
<p><?= professorH(t('logged_in_as')) ?> <strong><?= htmlspecialchars($_SESSION['professor_name'] ?? t('professor'), ENT_QUOTES, 'UTF-8') ?></strong>
 | <a href="../index.php"><?= professorH(t('homepage')) ?></a> | <a href="logout.php"><?= professorH(t('logout')) ?></a></p>
</nav><hr>

<?php require __DIR__ . '/professor_language_switcher.php'; ?>

<?php require_once __DIR__.'/../includes/notifications.php'; if(isset($pdo)) pkg_notifications($pdo); ?>
