<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}
if (!((isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 2) ||
    (isset($_SESSION['role']) && strcasecmp($_SESSION['role'], 'Professor') === 0))) {
    header('Location: ../student_dashboard.php');
    exit();
}
require_once __DIR__ . '/course_components.php';
require_once __DIR__ . '/grade_calculator.php';
$error = '';
$courseCode = is_string($_POST['courseCode'] ?? null) ? trim($_POST['courseCode']) : '';
$courseTitle = is_string($_POST['courseTitle'] ?? null) ? trim($_POST['courseTitle']) : '';
$courseUnit = filter_var($_POST['courseUnit'] ?? '', FILTER_VALIDATE_FLOAT);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['components_complete']) ||
    !isset($_SESSION['score_csrf_token']) || !is_string($_POST['csrf_token'] ?? null) ||
    !hash_equals($_SESSION['score_csrf_token'], $_POST['csrf_token'])) {
    $error = 'Your form expired or was incomplete. Reload and try again.';
} elseif ($courseCode === '' || strlen($courseCode) > 20 || $courseTitle === '' ||
    preg_match_all('/./us', $courseTitle) === false || preg_match_all('/./us', $courseTitle) > 100) {
    $error = 'Enter a course code (up to 20 characters) and title (up to 100 characters).';
} elseif ($courseUnit === false || $courseUnit <= 0 || $courseUnit > 99.9 || round($courseUnit, 1) !== $courseUnit) {
    $error = 'Enter a positive unit value up to 99.9, with at most one decimal place.';
}

$components = [];
foreach (gradingPeriods() as $period => $label) {
    if ($error !== '') break;
    $rows = $_POST['components'][$period] ?? null;
    if (!is_array($rows) || !$rows) {
        $error = "$label needs components totaling 100%.";
        break;
    }
    $weightTotal = 0;
    $examCount = 0;
    foreach ($rows as $row) {
        if (!is_array($row) || !is_string($row['name'] ?? null) || !is_string($row['weight'] ?? null)) {
            $error = "$label contains invalid component fields.";
            break;
        }
        $name = trim($row['name']);
        $length = preg_match_all('/./us', $name);
        $weight = filter_var($row['weight'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if ($name === '' || $length === false || $length > 100 || $weight === false) {
            $error = "$label needs component names of up to 100 characters and whole-number weights from 1 to 100.";
            break;
        }
        $isExam = isset($row['is_exam']) && $row['is_exam'] === '1';
        $examCount += $isExam ? 1 : 0;
        $weightTotal += $weight;
        $components[$period][] = ['name' => $name, 'weight' => $weight, 'is_exam' => $isExam];
    }
    if ($error === '' && ($weightTotal !== 100 || $examCount > 1)) {
        $error = "$label must total 100% and have no more than one checked exam.";
    }
}
if ($error !== '') {
    header('Location: ../professor_dashboard.php?error=' . urlencode($error) . '#templates');
    exit();
}

require __DIR__ . '/db.php';
try {
    $conn->beginTransaction();
    $classCode = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $courseCode)) . '-SEC' . random_int(1, 9);
    $stmt = $conn->prepare('INSERT INTO grade_templates (user_id, course_code, course_title, class_code, course_unit) VALUES (?, ?, ?, ?, ?) RETURNING template_id');
    $stmt->execute([(int)$_SESSION['user_id'], $courseCode, $courseTitle, $classCode, $courseUnit]);
    $templateId = (int)$stmt->fetchColumn();
    foreach (componentTables() as $period => $table) {
        $stmt = $conn->prepare("INSERT INTO $table (template_id, component_name, weight, is_exam) VALUES (?, ?, ?, ?)");
        foreach ($components[$period] as $component) {
            $stmt->execute([$templateId, $component['name'], $component['weight'], $component['is_exam'] ? 'true' : 'false']);
        }
    }
    $stmt = $conn->prepare('INSERT INTO enrolled_courses (user_id, template_id) VALUES (?, ?) ON CONFLICT DO NOTHING');
    $stmt->execute([(int)$_SESSION['user_id'], $templateId]);
    $conn->commit();
    header('Location: ../professor_dashboard.php?template=success&code=' . urlencode($classCode) . '#templates');
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log('Template publishing failed: ' . $e->getMessage());
    header('Location: ../professor_dashboard.php?error=' . urlencode('Could not publish the template. Please try again.') . '#templates');
}
exit();
