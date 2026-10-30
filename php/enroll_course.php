<?php
// enroll_course.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.html");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnEnrollCourse'])) {
    $userId = (int)$_SESSION['user_id'];
    $classCode = $_POST['templateCodeInput'] ?? $_POST['class_code'] ?? '';
    $classCode = is_string($classCode) ? strtoupper(trim($classCode)) : '';

    if (empty($classCode)) {
        header("Location: ../student_dashboard.php?error=" . urlencode("Please enter a class code.") . "#templates");
        exit();
    }

    // find template by class code
    include 'db.php';
    $stmt = $conn->prepare("SELECT template_id, course_code, course_title FROM grade_templates WHERE class_code = ? LIMIT 1");
    $stmt->execute([$classCode]);

    if ($template = $stmt->fetch()) {
        $templateId = (int)$template['template_id'];

        // Check whether the course was added or already enrolled.
        $insert = $conn->prepare("INSERT INTO enrolled_courses (user_id, template_id) VALUES (?, ?) ON CONFLICT (user_id, template_id) DO NOTHING RETURNING enrollment_id");
        $insert->execute([$userId, $templateId]);

        if ($insert->fetchColumn() === false) {
            header("Location: ../student_dashboard.php?error=" . urlencode("You're already enrolled in this course.") . "#templates");
            exit();
        }

        header("Location: ../student_dashboard.php?enrolled=success#templates");
        exit();
    } else {
        header("Location: ../student_dashboard.php?error=" . urlencode("Invalid class code: '$classCode' not found.") . "#templates");
        exit();
    }
} else {
    header("Location: ../student_dashboard.php#templates");
    exit();
}
?>
