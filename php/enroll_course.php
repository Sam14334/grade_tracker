<?php
// enroll_course.php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.html");
    exit();
}

$userId = (int)$_SESSION['user_id'];
$classCode = trim($_POST['templateCodeInput'] ?? $_POST['class_code'] ?? '');

if (empty($classCode)) {
    header("Location: ../student_dashboard.php?error=" . urlencode("Please enter a class code."));
    exit();
}

// 1. Check if class code exists in grade_templates
$stmt = mysqli_prepare($conn, "SELECT template_id, course_code, course_title FROM grade_templates WHERE class_code = ?");
mysqli_stmt_bind_param($stmt, "s", $classCode);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

if ($template = mysqli_fetch_assoc($res)) {
    $templateId = (int)$template['template_id'];

    // 2. Insert enrollment (IGNORE skips if already enrolled)
    $insert = mysqli_prepare($conn, "INSERT IGNORE INTO enrolled_courses (user_id, template_id) VALUES (?, ?)");
    mysqli_stmt_bind_param($insert, "ii", $userId, $templateId);
    mysqli_stmt_execute($insert);

    header("Location: ../student_dashboard.php?enrolled=success");
    exit();
} else {
    header("Location: ../student_dashboard.php?error=" . urlencode("Invalid class code: '$classCode' not found."));
    exit();
}
?>
