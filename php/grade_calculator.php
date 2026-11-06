<?php

function pupGradeScale(): array {
    return [
        [97, 1.00], [94, 1.25], [91, 1.50], [88, 1.75], [85, 2.00],
        [82, 2.25], [79, 2.50], [76, 2.75], [75, 3.00], [0, 5.00],
    ];
}

function percentageToGrade(float $percentage): float {
    // Remove floating-point noise without rounding to a whole percentage.
    $percentage = round($percentage, 10);
    foreach (pupGradeScale() as [$minimum, $grade]) {
        if ($percentage >= $minimum) return $grade;
    }
    return 5.00;
}

function gradingPeriods(): array {
    return ['midterms' => 'Midterms', 'finals' => 'Finals'];
}

function calculatePeriodGrade(array $components, array $scores): array {
    $totals = [];
    foreach ($scores as $score) {
        $id = (string)$score['component_id'];
        if (!isset($totals[$id])) $totals[$id] = ['score' => 0, 'max' => 0];
        $totals[$id]['score'] += (float)$score['raw_score'];
        $totals[$id]['max'] += (int)$score['total_items'];
    }

    $weightTotal = 0;
    $percentage = 0.0;
    $pendingExams = false;
    $missingCoursework = false;
    $validWeights = count($components) > 0;
    foreach ($components as $component) {
        $weight = (int)$component['weight'];
        $weightTotal += $weight;
        if ($weight < 0 || $weight > 100) $validWeights = false;
        $total = $totals[(string)$component['id']] ?? ['score' => 0, 'max' => 0];
        if ($total['max'] <= 0) {
            if (filter_var($component['is_exam'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $pendingExams = true;
            } else {
                $missingCoursework = true;
            }
        } else {
            $percentage += ($total['score'] / $total['max']) * $weight;
        }
    }

    if (!$validWeights || $weightTotal !== 100) {
        return ['grade' => null, 'percentage' => null, 'status' => 'Invalid weights'];
    }
    if ($missingCoursework) {
        return ['grade' => null, 'percentage' => null, 'status' => 'Incomplete'];
    }
    if ($pendingExams) {
        return ['grade' => null, 'percentage' => $percentage, 'status' => 'In Progress'];
    }
    return ['grade' => percentageToGrade($percentage), 'percentage' => $percentage, 'status' => 'Complete'];
}

function calculateCourseGrade(array $components, array $scores): array {
    $periods = [];
    foreach (gradingPeriods() as $period => $label) {
        $periodComponents = array_values(array_filter($components, fn($component) => $component['period'] === $period));
        $periods[$period] = calculatePeriodGrade($periodComponents, $scores);
    }
    $statuses = array_column($periods, 'status');
    $status = 'Complete';
    foreach (['Invalid weights', 'Incomplete', 'In Progress'] as $pendingStatus) {
        if (in_array($pendingStatus, $statuses, true)) {
            $status = $pendingStatus;
            break;
        }
    }
    $midterms = $periods['midterms']['percentage'];
    $finals = $periods['finals']['percentage'];
    $percentage = $midterms !== null && $finals !== null ? ($midterms + $finals) / 2 : null;
    return [
        'grade' => $status === 'Complete' ? percentageToGrade($percentage) : null,
        'percentage' => $percentage,
        'status' => $status,
        'periods' => $periods,
    ];
}
