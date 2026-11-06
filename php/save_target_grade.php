<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}
if ((isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 2) ||
    (isset($_SESSION['role']) && strcasecmp($_SESSION['role'], 'Professor') === 0)) {
    header('Location: ../professor_dashboard.php');
    exit();
}

require_once __DIR__ . '/grade_calculator.php';
$enrollmentId = filter_var($_POST['enrollment_id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$target = filter_var($_POST['target_grade'] ?? '', FILTER_VALIDATE_FLOAT);
$allowedGrades = array_values(array_filter(array_column(pupGradeScale(), 1), fn($grade) => $grade <= 3.00));
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$enrollmentId || !in_array($target, $allowedGrades, true) ||
    !isset($_SESSION['score_csrf_token']) || !is_string($_POST['csrf_token'] ?? null) ||
    !hash_equals($_SESSION['score_csrf_token'], $_POST['csrf_token'])) {
    header('Location: ../student_dashboard.php?target=error#grades');
    exit();
}

require __DIR__ . '/db.php';
try {
    $stmt = $conn->prepare('UPDATE enrolled_courses SET target_grade = ? WHERE enrollment_id = ? AND user_id = ?');
    $stmt->execute([$target, $enrollmentId, (int)$_SESSION['user_id']]);
    $status = $stmt->rowCount() === 1 ? 'saved' : 'error';
} catch (PDOException $e) {
    error_log('Target grade could not be saved: ' . $e->getMessage());
    $status = 'error';
}
header('Location: ../student_dashboard.php?target=' . $status . '#grades');
exit();
