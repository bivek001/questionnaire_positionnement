<?php
/** The caller must first authenticate an active professor. */
function qp_whole_theme(PDO $pdo, int $professor, int $theme): bool {
    $s = $pdo->prepare('SELECT 1 FROM professor_content_assignments WHERE professor_id=? AND theme_id=? AND chapter_id IS NULL LIMIT 1');
    $s->execute([$professor, $theme]);
    return (bool)$s->fetchColumn();
}

/** Replace whole-attempt aggregates with visible response aggregates for chapter access. */
function qp_scope_attempt(PDO $pdo, int $professor, array $attempt): array {
    $attempt['whole_theme_access'] = qp_whole_theme($pdo, $professor, (int)$attempt['theme_id']);
    if ($attempt['whole_theme_access']) return $attempt;
    $s = $pdo->prepare("SELECT COUNT(*) AS visible_count, COALESCE(SUM(r.awarded_points),0) AS score,
        COALESCE(SUM(q.points),0) AS maximum,
        SUM(q.question_type='open' AND r.graded_at IS NULL) AS pending,
        MAX(r.graded_at) AS reviewed_at
        FROM responses r JOIN questions q ON q.id=r.question_id
        WHERE r.attempt_id=? AND q.theme_id=? AND EXISTS (
          SELECT 1 FROM professor_content_assignments p WHERE p.professor_id=?
          AND p.theme_id=q.theme_id AND (p.chapter_id IS NULL OR p.chapter_id=q.chapter_id))");
    $s->execute([$attempt['id'], $attempt['theme_id'], $professor]);
    $visible = $s->fetch(PDO::FETCH_ASSOC);
    if (!(int)$visible['visible_count']) { http_response_code(403); exit; }
    $attempt['total_score'] = $visible['score'];
    $attempt['maximum_score'] = $visible['maximum'];
    $attempt['corrected_at'] = (int)$visible['pending'] === 0 ? $visible['reviewed_at'] : null;
    $attempt['result_sent_at'] = null;
    $attempt['result_sent_to'] = null;
    $attempt['scope_pending'] = (int)$visible['pending'];
    return $attempt;
}
