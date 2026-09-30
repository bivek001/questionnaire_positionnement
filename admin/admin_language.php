<?php
// Presentation helpers only. Authentication remains in auth.php.
require_once __DIR__ . '/../includes/language.php';

function adminH(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function adminEnumLabel(string $value): string
{
    $keys = [
        'initial' => 'initial', 'final' => 'final', 'sortie' => 'exit',
        'pending' => 'pending', 'completed' => 'completed',
        'in_progress' => 'in_progress', 'corrected' => 'corrected',
        'single_choice' => 'single_choice', 'multiple_choice' => 'multiple_choice',
        'open' => 'open_question', 'sommaire' => 'sommaire',
        'introduction' => 'introduction',
    ];
    return isset($keys[$value]) ? t($keys[$value]) : $value;
}
