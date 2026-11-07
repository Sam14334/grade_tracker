<?php

function templateFormInput(array $input): array {
    $text = fn($value) => is_string($value) ? trim($value) : '';
    $form = [
        'courseCode' => $text($input['courseCode'] ?? ''),
        'courseTitle' => $text($input['courseTitle'] ?? ''),
        'courseUnit' => $text($input['courseUnit'] ?? ''),
        'components' => [],
    ];
    foreach (gradingPeriods() as $period => $label) {
        $rows = $input['components'][$period] ?? [];
        $form['components'][$period] = [];
        if (!is_array($rows)) continue;
        foreach ($rows as $row) {
            $form['components'][$period][] = [
                'id' => $text(is_array($row) ? ($row['id'] ?? '') : ''),
                'name' => $text(is_array($row) ? ($row['name'] ?? '') : ''),
                'weight' => $text(is_array($row) ? ($row['weight'] ?? '') : ''),
                'is_exam' => is_array($row) && ($row['is_exam'] ?? '') === '1',
                'invalid' => !is_array($row) || (isset($row['id']) && !is_string($row['id'])),
            ];
        }
    }
    return $form;
}

function templateFormError(array $form): string {
    $titleLength = preg_match_all('/./us', $form['courseTitle']);
    if ($form['courseCode'] === '' || strlen($form['courseCode']) > 20 ||
        $form['courseTitle'] === '' || $titleLength === false || $titleLength > 100) {
        return 'Enter a course code (up to 20 characters) and title (up to 100 characters).';
    }
    $units = filter_var($form['courseUnit'], FILTER_VALIDATE_FLOAT);
    if ($units === false || $units <= 0 || $units > 99.9 || round($units, 1) !== $units) {
        return 'Enter a positive unit value up to 99.9, with at most one decimal place.';
    }
    foreach (gradingPeriods() as $period => $label) {
        $rows = $form['components'][$period];
        if (!$rows) return "$label needs components totaling 100%.";
        $weightTotal = $examCount = 0;
        $ids = [];
        foreach ($rows as $row) {
            $length = preg_match_all('/./us', $row['name']);
            $weight = filter_var($row['weight'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
            if ($row['invalid'] || $row['name'] === '' || $length === false || $length > 100 || $weight === false) {
                return "$label needs component names of up to 100 characters and whole-number weights from 1 to 100.";
            }
            if ($row['id'] !== '') {
                $id = filter_var($row['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($id === false || isset($ids[$id])) return "$label contains an invalid or repeated component.";
                $ids[$id] = true;
            }
            $weightTotal += $weight;
            $examCount += $row['is_exam'] ? 1 : 0;
        }
        if ($weightTotal !== 100 || $examCount > 1) return "$label must total 100% and have no more than one checked exam.";
    }
    return '';
}
