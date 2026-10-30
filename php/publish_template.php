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
    $courseUnit = $_POST['courseUnit'] ?? '';

    // Match the form and NUMERIC(3,1): positive units with at most one decimal.
    if (!is_string($courseUnit) ||
        !preg_match('/^\d{1,2}(?:\.\d)?$/D', trim($courseUnit)) ||
        (float)$courseUnit <= 0) {
        header("Location: ../professor_dashboard.php?error=" . urlencode("Enter course units from 0.1 to 99.9, with at most one decimal place."));
        exit();
    }
    $courseUnit = trim($courseUnit);

    // Generate class code, e.g. CS301-SEC5
    $cleanCode = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $courseCode));
    $classCode = $cleanCode . "-SEC" . rand(1, 9);

    // insert grade template and return generated id
    $stmtTpl = $conn->prepare("INSERT INTO grade_templates (user_id, course_code, course_title, course_unit, class_code) VALUES (?, ?, ?, ?, ?) RETURNING template_id");

    if ($stmtTpl->execute([$userId, $courseCode, $courseTitle, $courseUnit, $classCode])) {
        $templateId = (int)$stmtTpl->fetchColumn();

        // insert components
        $componentNames = $_POST['component_name'] ?? $_POST['component_id'] ?? [];
        $weights = $_POST['weight'] ?? [];

        $stmtComp = $conn->prepare("INSERT INTO template_components (template_id, component_name, weight) VALUES (?, ?, ?)");

        for ($i = 0; $i < count($componentNames); $i++) {
            $compName = trim((string)$componentNames[$i]);
            $weight = (int)($weights[$i] ?? 0);

            if (!empty($compName)) {
                $stmtComp->execute([$templateId, $compName, $weight]);
            }
        }

        // postgres on conflict do nothing instead of insert ignore
        $stmtEnroll = $conn->prepare("INSERT INTO enrolled_courses (user_id, template_id) VALUES (?, ?) ON CONFLICT DO NOTHING");
        $stmtEnroll->execute([$userId, $templateId]);

        // Redirect back to dashboard with success
        if (isset($_SESSION['role']) && (strcasecmp($_SESSION['role'], 'Professor') === 0 || strcasecmp($_SESSION['role'], 'Teacher') === 0)) {
            header("Location: ../professor_dashboard.php?template=success&code=" . urlencode($classCode));
        } else {
            header("Location: ../student_dashboard.php?template=success&code=" . urlencode($classCode));
        }
        exit();
    } else {
        echo "Error saving template.";
    }
}
?>
