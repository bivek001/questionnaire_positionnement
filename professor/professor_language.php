<?php
// Shared language selection; no authorization or database decisions belong here.
require_once __DIR__ . '/../includes/language.php';
function professorH($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function professorPassationLabel(string $value): string {
    return match ($value) {
        'initial' => t('initial'),
        'final' => t('final'),
        'sortie' => t('exit'),
        default => $value,
    };
}
function professorStructureLabel(string $value): string {
    return match ($value) {
        'chapter' => t('chapters'),
        'lesson' => t('lessons'),
        'topic' => t('topics'),
        default => $value,
    };
}
function professorQuestionTypeLabel(string $value): string {
    return match ($value) {
        'single_choice' => t('single_choice'),
        'multiple_choice' => t('multiple_choice'),
        'open' => t('open_question'),
        default => $value,
    };
}
