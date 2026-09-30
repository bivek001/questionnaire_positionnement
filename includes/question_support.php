<?php
require_once __DIR__ . '/safe_rich_content.php';

function qp_post_text(array $input, string $key): string {
    $value = $input[$key] ?? '';
    if (!is_string($value) || strlen($value) > 60000) throw new InvalidArgumentException(t('qp_invalid_fields'));
    return trim($value);
}

function qp_keyword_groups(string $keywords): array {
    $groups = [];
    foreach (preg_split('/\R/u', $keywords) ?: [] as $line) {
        if (trim($line) === '') continue;
        $alternatives = array_values(array_filter(array_map('qp_normalize_answer', explode('|', $line)), static fn($v) => $v !== ''));
        if (!$alternatives) throw new InvalidArgumentException(t('qp_invalid_keywords'));
        $groups[] = array_values(array_unique($alternatives));
    }
    return $groups;
}

function qp_validate_choices(array $input, string $type): void {
    if (($input['qp_form_complete'] ?? null) !== '1') throw new InvalidArgumentException(t('qp_incomplete_form'));
    if ($type === 'open') return;
    $texts = $input['choices'] ?? [];
    $correct = $input['correct_choices'] ?? [];
    if (!is_array($texts) || !is_array($correct)) throw new InvalidArgumentException(t('qp_invalid_choices'));
    $valid = [];
    foreach ($texts as $index => $text) {
        if (!is_string($text) || !ctype_digit((string)$index)) throw new InvalidArgumentException(t('qp_invalid_choices'));
        if (trim($text) !== '') $valid[(string)$index] = true;
    }
    $selected = [];
    foreach ($correct as $index) {
        if (!is_scalar($index) || !isset($valid[(string)$index])) throw new InvalidArgumentException(t('qp_invalid_choices'));
        $selected[(string)$index] = true;
    }
    if (count($valid) < 2 || !$selected || ($type === 'single_choice' && count($selected) !== 1)) {
        throw new InvalidArgumentException(t('qp_invalid_choices'));
    }
}

function qp_save_support(PDO $pdo, int $id, array $input): void {
    $context = qp_rich_html(qp_post_text($input, 'pre_question_content'));
    $reference = qp_post_text($input, 'model_answer');
    $rubric = qp_post_text($input, 'grading_rubric');
    $keywords = qp_post_text($input, 'grading_keywords');
    qp_keyword_groups($keywords);
    $stmt = $pdo->prepare('UPDATE questions SET pre_question_content = ?, model_answer = ?, grading_rubric = ?, grading_keywords = ? WHERE id = ?');
    $stmt->execute([$context ?: null, $reference ?: null, $rubric ?: null, $keywords ?: null, $id]);
}

function qp_normalize_answer(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    if (class_exists('Normalizer')) $text = Normalizer::normalize($text, Normalizer::FORM_D) ?: $text;
    // Explicit Latin folding is stable on Windows, where iconv may emit e.g. "'e".
    $text = strtr($text, ['œ'=>'oe','æ'=>'ae','ß'=>'ss','à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','ç'=>'c','è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','ñ'=>'n','ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ý'=>'y','ÿ'=>'y']);
    $text = preg_replace('/\p{M}+/u', '', $text) ?? '';
    return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? '');
}

/** A transparent keyword coverage heuristic; never an authoritative semantic grade. */
function qp_suggest(array $context, array $config): array {
    $max = max(0.0, (float)$context['max_points']);
    $result = ['points' => null, 'method' => 'unconfigured', 'matched' => [], 'total' => 0];
    $answer = qp_normalize_answer($context['answer']);
    $groups = qp_keyword_groups($context['keywords']);
    if ($groups) {
        $matched = [];
        foreach ($groups as $i => $alternatives) {
            foreach ($alternatives as $term) {
                if (str_contains(' ' . $answer . ' ', ' ' . $term . ' ')) { $matched[] = $i + 1; break; }
            }
        }
        return ['points' => round($max * (count($matched) === 0 ? 0 : ceil(4 * count($matched) / count($groups)) / 4), 2), 'method' => 'criteria_bands_v2', 'matched' => $matched, 'total' => count($groups)];
    }
    $reference = qp_normalize_answer($context['model_answer']);
    if ($reference !== '' && $answer === $reference) return ['points' => round($max, 2), 'method' => 'reference_exact_v1'];
    // A paraphrase or non-matching model answer is not automatically a wrong answer.
    return $result;
}

function qp_record_suggestion(PDO $pdo, int $responseId, int $questionId, string $answer): float {
    $stmt = $pdo->prepare('SELECT q.question_text, q.points, q.pre_question_content, q.model_answer, q.grading_rubric, q.grading_keywords, p.content AS paragraph_content FROM questions q LEFT JOIN paragraphs p ON p.id = q.paragraph_id WHERE q.id = ?');
    $stmt->execute([$questionId]);
    $q = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$q) throw new RuntimeException(t('qp_invalid_fields'));
    $context = [
        'question' => $q['question_text'], 'paragraph' => qp_rich_html($q['paragraph_content'] ?? ''),
        'context' => qp_rich_html($q['pre_question_content'] ?? ''), 'model_answer' => $q['model_answer'] ?? '',
        'rubric' => $q['grading_rubric'] ?? '', 'keywords' => $q['grading_keywords'] ?? '',
        'answer' => $answer, 'max_points' => (float)$q['points'],
    ];
    $config = require __DIR__ . '/../config/open_grading.php';
    $suggestion = qp_suggest($context, $config);
    $details = json_encode(['version' => 1, 'context' => $context, 'suggestion' => $suggestion], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    // Initialize provisional awarded_points once; do not mark a human review complete.
    // Immutable once created; a human review can never trigger a replacement suggestion.
    $stmt = $pdo->prepare('UPDATE responses SET suggested_points = ?, suggestion_details = ?, suggested_at = CURRENT_TIMESTAMP, awarded_points = COALESCE(?, awarded_points) WHERE id = ? AND graded_at IS NULL AND suggestion_details IS NULL');
    $stmt->execute([$suggestion['points'], $details, $suggestion['points'], $responseId]);
    $read = $pdo->prepare('SELECT awarded_points FROM responses WHERE id=?');
    $read->execute([$responseId]);
    return (float)$read->fetchColumn();
}

/** Call only for a response already selected through the page's authorization query. */
function qp_review_suggestion(PDO $pdo, int $responseId): void {
    $stmt = $pdo->prepare('SELECT suggested_points, suggestion_details, suggested_at FROM responses WHERE id = ?');
    $stmt->execute([$responseId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo '<aside class="qp-suggestion"><h4>' . qp_h(t('qp_suggestion')) . '</h4>';
    $details = json_decode($row['suggestion_details'] ?? '', true);
    if (!is_array($details)) {
        echo '<p>' . qp_h(t('qp_no_suggestion')) . '</p></aside>';
        return;
    }
    $context = $details['context'];
    $s = $details['suggestion'];
    echo '<p>' . qp_h(t('qp_suggestion_notice')) . '</p>';
    if ($row['suggested_points'] !== null) echo '<p><strong>' . qp_h($row['suggested_points']) . ' / ' . qp_h($context['max_points']) . '</strong></p>';
    else echo '<p>' . qp_h(t('qp_no_suggestion')) . '</p>';
    $method = ['criteria_bands_v2' => 'qp_keyword_method', 'reference_exact_v1' => 'qp_exact_method', 'provider' => 'qp_provider_method'][$s['method']] ?? 'qp_manual_method';
    echo '<p>' . qp_h(t($method)) . '</p>';
    if (isset($s['total']) && $s['total'] > 0) echo '<p>' . qp_h(t('qp_coverage')) . ': ' . count($s['matched']) . ' / ' . (int)$s['total'] . '</p>';
    if (($s['method'] ?? '') === 'criteria_bands_v2') {
        echo '<ul>';
        foreach (preg_split('/\R/u', $context['keywords'] ?? '') ?: [] as $line) {
            if (trim($line) === '') continue;
            $criterion = ($criterion ?? 0) + 1;
            echo '<li>' . qp_h(t(in_array($criterion, $s['matched'], true) ? 'pkg_matched' : 'pkg_missing')) . ': ' . qp_h($line) . '</li>';
        }
        echo '</ul>';
    }
    if (!empty($s['explanation'])) echo '<p>' . nl2br(qp_h($s['explanation'])) . '</p>';
    echo '<details><summary>' . qp_h(t('qp_snapshot')) . '</summary>';
    foreach (['question'=>'qp_question_snapshot','paragraph'=>'qp_paragraph_snapshot','context'=>'qp_context','model_answer'=>'qp_model_answer','rubric'=>'qp_rubric','keywords'=>'qp_keywords'] as $key => $label) {
        if (($context[$key] ?? '') === '') continue;
        echo '<h5>' . qp_h(t($label)) . '</h5><div>';
        echo in_array($key, ['paragraph','context'], true) ? qp_rich_html($context[$key]) : nl2br(qp_h($context[$key]));
        echo '</div>';
    }
    echo '</details></aside>';
}
