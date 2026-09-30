<?php

function getPositioningLevel(
    PDO $pdo,
    float $percentage,
    string $undefinedLabel = 'Not defined'
): string {

    $stmt = $pdo->prepare(
        "SELECT name
         FROM positioning_levels
         WHERE ? BETWEEN min_percentage AND max_percentage
         ORDER BY display_order ASC
         LIMIT 1"
    );

    $stmt->execute([$percentage]);

    $level = $stmt->fetchColumn();

    if ($level === false) {
        return $undefinedLabel;
    }

    return $level;
}