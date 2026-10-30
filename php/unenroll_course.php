<?php
// unenroll_course.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.html");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnUnenrollCourse'])) {
    $userId = (int)$_SESSION['user_id'];
    $templateId = $_POST['template_id'] ?? '';
    if (!is_string($templateId) || !ctype_digit($templateId) || (int)$templateId <= 0) {
        header("Location: ../student_dashboard.php?unenrolled=error#grades");
        exit();
    }
    $templateId = (int)$templateId;

    include 'db.php';

    try {
        // Remove only this student's enrollment.
        $stmt = $conn->prepare("DELETE FROM enrolled_courses WHERE user_id = ? AND template_id = ?");
        $stmt->execute([$userId, $templateId]);
        header("Location: ../student_dashboard.php?unenrolled=success#grades");
    } catch (PDOException $e) {
        error_log('Unenrollment failed: ' . $e->getMessage());
        header("Location: ../student_dashboard.php?unenrolled=error#grades");
    }
    exit();
} else {
    header("Location: ../student_dashboard.php#grades");
    exit();
}
?>
