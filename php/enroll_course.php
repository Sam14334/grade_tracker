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

// find template by class code
$stmt = $conn->prepare("SELECT template_id, course_code, course_title FROM grade_templates WHERE class_code = ? LIMIT 1");
$stmt->execute([$classCode]);

if ($template = $stmt->fetch()) {
    $templateId = (int)$template['template_id'];

    // postgres on conflict do nothing replaces mysql insert ignore
    $insert = $conn->prepare("INSERT INTO enrolled_courses (user_id, template_id) VALUES (?, ?) ON CONFLICT DO NOTHING");
    $insert->execute([$userId, $templateId]);

    header("Location: ../student_dashboard.php?enrolled=success");
    exit();
} else {
    header("Location: ../student_dashboard.php?error=" . urlencode("Invalid class code: '$classCode' not found."));
    exit();
}
?>
