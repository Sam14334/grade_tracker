<?php

function componentTables(): array {
    return [
        'midterms' => 'midterms_template_components',
        'finals' => 'finals_template_components',
    ];
}

function loadTemplateComponents(PDO $conn, int $templateId): array {
    $components = [];
    foreach (componentTables() as $period => $table) {
        $stmt = $conn->prepare("SELECT id, component_name, weight, is_exam FROM $table WHERE template_id = ? ORDER BY id");
        $stmt->execute([$templateId]);
        while ($row = $stmt->fetch()) {
            $components[] = [
                'id' => $period . '_' . $row['id'],
                'database_id' => (int)$row['id'],
                'period' => $period,
                'name' => $row['component_name'],
                'weight' => (int)$row['weight'],
                'is_exam' => filter_var($row['is_exam'], FILTER_VALIDATE_BOOLEAN),
            ];
        }
    }
    return $components;
}

// The caller first checks ownership of the enrollment or its course template.
function loadEnrollmentScores(PDO $conn, int $enrollmentId, int $templateId): array {
    $scores = [];
    $columns = ['midterms' => 'midterm_component_id', 'finals' => 'final_component_id'];
    foreach (componentTables() as $period => $table) {
        $column = $columns[$period];
        $stmt = $conn->prepare("SELECT scs.*, tc.id AS period_component_id FROM student_component_scores scs
            JOIN $table tc ON tc.id = scs.$column
            WHERE scs.enrollment_id = ? AND tc.template_id = ? ORDER BY scs.score_id");
        $stmt->execute([$enrollmentId, $templateId]);
        while ($row = $stmt->fetch()) {
            $row['component_id'] = $period . '_' . $row['period_component_id'];
            $row['period'] = $period;
            $scores[] = $row;
        }
    }
    return $scores;
}
