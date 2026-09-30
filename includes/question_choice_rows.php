<?php
$qpRows = $existingChoices ?? array_fill(0, 4, ['choice_text' => '', 'is_correct' => 0]);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['question_text']) && is_array($_POST['choices'] ?? null)) {
    $qpRows = [];
    $qpCorrect = is_array($_POST['correct_choices'] ?? null) ? array_filter($_POST['correct_choices'], 'is_scalar') : [];
    foreach ($_POST['choices'] as $qpIndex => $qpText) {
        if (is_string($qpText)) $qpRows[] = ['choice_text' => $qpText, 'is_correct' => in_array((string)$qpIndex, array_map('strval', $qpCorrect), true)];
    }
}
if (!$qpRows) $qpRows = array_fill(0, 2, ['choice_text'=>'', 'is_correct'=>0]);
$qpType = $_POST['question_type'] ?? ($question['question_type'] ?? 'single_choice');
?>
<div id="qp-choice-rows" data-option="<?= qp_h(t('qp_option')) ?>" data-correct="<?= qp_h(t('h150_correct')) ?>" data-remove="<?= qp_h(t('qp_remove_option')) ?>">
<?php foreach (array_values($qpRows) as $qpIndex=>$qpChoice): ?>
    <div class="qp-choice-row question-choice">
        <label class="qp-option-label" for="qp-option-<?= $qpIndex ?>"><?= qp_h(t('qp_option')) ?> <?= $qpIndex + 1 ?></label>
        <input type="text" id="qp-option-<?= $qpIndex ?>" name="choices[<?= $qpIndex ?>]" value="<?= qp_h($qpChoice['choice_text']) ?>">
        <label class="question-correct"><input type="<?= $qpType === 'single_choice' ? 'radio' : 'checkbox' ?>" name="correct_choices[]" value="<?= $qpIndex ?>" <?= $qpChoice['is_correct'] ? 'checked' : '' ?>> <?= qp_h(t('h150_correct')) ?></label>
        <button type="button" class="btn btn-secondary qp-remove"><?= qp_h(t('qp_remove_option')) ?></button>
    </div>
<?php endforeach; ?>
</div>
<button type="button" class="btn btn-secondary" id="qp-add-option"><?= qp_h(t('qp_add_option')) ?></button>
<input type="hidden" name="qp_form_complete" value="1">
<script src="../assets/js/question_support.js" defer></script>
