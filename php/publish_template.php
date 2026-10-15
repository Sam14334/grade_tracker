<?php
// publish_template.php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!empty($_POST['courseCode']) || isset($_POST['btnPublishTemplate']))) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../index.html");
        exit();
    }

    $userId = (int)$_SESSION['user_id'];
    $courseCode = trim($_POST['courseCode'] ?? '');
    $courseTitle = trim($_POST['courseTitle'] ?? '');

    // Generate class code, e.g. CS301-SEC5
    $cleanCode = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $courseCode));
    $classCode = $cleanCode . "-SEC" . rand(1, 9);

    // 1. Insert into grade_templates table
    $stmtTpl = mysqli_prepare($conn, "INSERT INTO grade_templates (user_id, course_code, course_title, class_code) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmtTpl, "isss", $userId, $courseCode, $courseTitle, $classCode);

    if (mysqli_stmt_execute($stmtTpl)) {
        $templateId = mysqli_insert_id($conn);

        // 2. Insert each component and its weight using component_name
        $componentNames = $_POST['component_name'] ?? $_POST['component_id'] ?? [];
        $weights = $_POST['weight'] ?? [];

        $stmtComp = mysqli_prepare($conn, "INSERT INTO template_components (template_id, component_name, weight) VALUES (?, ?, ?)");

        for ($i = 0; $i < count($componentNames); $i++) {
            $compName = trim((string)$componentNames[$i]);
            $weight = (int)($weights[$i] ?? 0);

            if (!empty($compName)) {
                mysqli_stmt_bind_param($stmtComp, "isi", $templateId, $compName, $weight);
                mysqli_stmt_execute($stmtComp);
            }
        }

        // 3. Automatically link creator in enrolled_courses so it shows up in their active courses
        $stmtEnroll = mysqli_prepare($conn, "INSERT IGNORE INTO enrolled_courses (user_id, template_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmtEnroll, "ii", $userId, $templateId);
        mysqli_stmt_execute($stmtEnroll);

        // Redirect back to dashboard with success
        if (isset($_SESSION['role']) && (strcasecmp($_SESSION['role'], 'Professor') === 0 || strcasecmp($_SESSION['role'], 'Teacher') === 0)) {
            header("Location: ../professor_dashboard.php?template=success&code=" . urlencode($classCode));
        } else {
            header("Location: ../student_dashboard.php?template=success&code=" . urlencode($classCode));
        }
        exit();
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>
