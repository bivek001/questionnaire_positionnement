<?php
// Included inside the existing authorized create/edit form.
$qpValues = isset($question) && is_array($question) && isset($question['id']) ? $question : [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['delete_question']) && !isset($_POST['toggle_question']))) {
    foreach (['pre_question_content','model_answer','grading_rubric','grading_keywords'] as $qpKey) {
        if (is_string($_POST[$qpKey] ?? null)) $qpValues[$qpKey] = $_POST[$qpKey];
    }
}
?>
<section class="card qp-support">
    <label for="pre_question_content"><?= qp_h(t('qp_context')) ?></label>
    <p><?= qp_h(t('qp_context_help')) ?></p>
    <textarea id="pre_question_content" name="pre_question_content" rows="4" style="width:100%;box-sizing:border-box"><?= qp_h($qpValues['pre_question_content'] ?? '') ?></textarea>
    <div id="qp-open-settings">
        <h3><?= qp_h(t('qp_grading_settings')) ?></h3>
        <p><?= qp_h(t('qp_grading_help')) ?></p>
        <?php foreach (['model_answer'=>'qp_model_answer','grading_rubric'=>'qp_rubric','grading_keywords'=>'qp_keywords'] as $qpKey=>$qpLabel): ?>
            <p><label for="<?= qp_h($qpKey) ?>"><?= qp_h(t($qpLabel)) ?></label>
            <textarea id="<?= qp_h($qpKey) ?>" name="<?= qp_h($qpKey) ?>" rows="4" style="width:100%;box-sizing:border-box"><?= qp_h($qpValues[$qpKey] ?? '') ?></textarea></p>
        <?php endforeach; ?>
        <p><?= qp_h(t('qp_keywords_help')) ?></p>
    </div>
</section>

<p><?= qp_h(t('pkg_bands')) ?></p>

<?php if(isset($pdo,$qpValues['id'])) { require_once __DIR__.'/positioning_ux.php'; pu_staff_word_correction($pdo,$qpValues['id']); } ?>
