<?php
// Step 150H: display helpers only. Database values and submission rules stay intact.
require_once __DIR__ . '/language.php';

function traineePassationLabel(string $value): string
{
    $keys = [
        'initial' => 'h150_initial',
        'final' => 'h150_final',
        'sortie' => 'h150_exit',
    ];
    return isset($keys[$value]) ? t($keys[$value]) : $value;
}

// A language button posts the current form back to questionnaire.php.
// These values are used only to redisplay answers; submit.php revalidates them.
function traineeDraftAnswers(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
        ($_POST['h150_language_switch'] ?? null) !== '1' ||
        !is_array($_POST['answers'] ?? null)) {
        return [];
    }
    return $_POST['answers'];
}

function traineeChoiceSelected(int $questionId, int $choiceId): bool
{
    $answers = traineeDraftAnswers();
    $value = $answers[$questionId] ?? null;
    foreach (is_array($value) ? $value : [$value] as $selected) {
        if ((is_string($selected) || is_int($selected)) &&
            (string)$selected === (string)$choiceId) {
            return true;
        }
    }
    return false;
}

function traineeAnswerText(int $questionId): string
{
    $answers = traineeDraftAnswers();
    $value = $answers[$questionId] ?? '';
    return is_string($value) ? $value : '';
}
