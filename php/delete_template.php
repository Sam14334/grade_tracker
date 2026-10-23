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
    // check if the user is the creator of this template
    $ownerQuery = $conn->prepare("SELECT user_id FROM grade_templates WHERE template_id = ? LIMIT 1");
    $ownerQuery->execute([$templateId]);
    $ownerRow = $ownerQuery->fetch();

    if ($ownerRow && (int)$ownerRow['user_id'] === $userId) {
        $delEnroll = $conn->prepare("DELETE FROM enrolled_courses WHERE template_id = ?");
        $delEnroll->execute([$templateId]);

        $delComp = $conn->prepare("DELETE FROM template_components WHERE template_id = ?");
        $delComp->execute([$templateId]);

        $delTpl = $conn->prepare("DELETE FROM grade_templates WHERE template_id = ? AND user_id = ?");
        $delTpl->execute([$templateId, $userId]);
    } else {
        $delEnroll = $conn->prepare("DELETE FROM enrolled_courses WHERE template_id = ? AND user_id = ?");
        $delEnroll->execute([$templateId, $userId]);
    }
}

if (isset($_SESSION['role']) && (strcasecmp($_SESSION['role'], 'Professor') === 0 || strcasecmp($_SESSION['role'], 'Teacher') === 0)) {
    header("Location: ../professor_dashboard.php");
} else {
    header("Location: ../student_dashboard.php");
}
exit();
?>
