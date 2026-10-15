<?php
// delete_template.php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.html");
    exit();
}

$userId = (int)$_SESSION['user_id'];
$templateId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($templateId > 0) {
    // Check if the user is the creator of this template
    $ownerQuery = mysqli_query($conn, "SELECT user_id FROM grade_templates WHERE template_id = '$templateId'");
    $ownerRow = mysqli_fetch_assoc($ownerQuery);

    if ($ownerRow && (int)$ownerRow['user_id'] === $userId) {
        // Creator deleting their template: remove enrollments, components, and the template itself
        mysqli_query($conn, "DELETE FROM enrolled_courses WHERE template_id = '$templateId'");
        mysqli_query($conn, "DELETE FROM template_components WHERE template_id = '$templateId'");
        mysqli_query($conn, "DELETE FROM grade_templates WHERE template_id = '$templateId' AND user_id = '$userId'");
    } else {
        // Enrolled user un-enrolling: only remove their personal enrollment
        mysqli_query($conn, "DELETE FROM enrolled_courses WHERE template_id = '$templateId' AND user_id = '$userId'");
    }
}

if (isset($_SESSION['role']) && (strcasecmp($_SESSION['role'], 'Professor') === 0 || strcasecmp($_SESSION['role'], 'Teacher') === 0)) {
    header("Location: ../professor_dashboard.php");
} else {
    header("Location: ../student_dashboard.php");
}
exit();
?>
