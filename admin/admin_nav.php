<?php
require_once __DIR__ . '/admin_language.php';
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$links = ['index.php'=>'dashboard', 'themes.php'=>'themes', 'manage_content.php'=>'h150_course_content', 'manage_pages.php'=>'course_pages', 'questions.php'=>'questions', 'assign_questionnaire.php'=>'questionnaire_assignments', 'professors.php'=>'professors', 'trainees.php'=>'pkg_trainee_management', 'results.php'=>'pkg_trainee_results', 'levels.php'=>'positioning_levels'];
?>
<nav class="admin-navigation" aria-label="<?= adminH(t('ux_navigation')) ?>">
<strong><?= adminH(t('administrator_area')) ?></strong><div class="nav-links">
<?php foreach ($links as $url=>$key): ?><a href="<?= $url ?>?lang=<?= adminH(currentLanguage()) ?>" <?= $currentPage === $url ? 'aria-current="page"' : '' ?>><?= adminH(t($key)) ?></a><?php endforeach; ?>
</div><div class="nav-links"><a href="../index.php"><?= adminH(t('homepage')) ?></a><a href="logout.php"><?= adminH(t('logout')) ?></a></div>
</nav>


<?php require_once __DIR__.'/../includes/notifications.php'; if(isset($pdo)) pkg_notifications($pdo); ?>
