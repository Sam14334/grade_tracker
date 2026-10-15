<?php
// register.php
session_start();
include 'db.php';

if (isset($_POST['btnRegister'])) {
    $fullName = trim($_POST['regFullName'] ?? '');
    $role = strtolower(trim($_POST['regRole'] ?? 'student'));
    $email = strtolower(trim($_POST['regEmail'] ?? ''));
    $password = $_POST['regPassword'] ?? '';
    $confirmPassword = $_POST['regConfirmPassword'] ?? '';

    // Basic required fields check
    if (empty($fullName) || empty($email) || empty($password) || empty($confirmPassword)) {
        header("Location: ../index.html?error=" . urlencode("All fields are required."));
        exit();
    }

    // Password match check
    if ($password !== $confirmPassword) {
        header("Location: ../index.html?error=" . urlencode("Passwords do not match."));
        exit();
    }

    // Domain validation
    $emailParts = explode('@', $email);
    $domain = end($emailParts);

    if ($role === 'professor' && $domain !== 'pup.edu.ph') {
        header("Location: ../index.html?error=" . urlencode("Professor registration requires an official @pup.edu.ph email address."));
        exit();
    }

    if ($role === 'student' && $domain !== 'iskolarngbayan.pup.edu.ph') {
        header("Location: ../index.html?error=" . urlencode("Student registration requires an official @iskolarngbayan.pup.edu.ph email address."));
        exit();
    }

    // Check if email already exists
    $checkStmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ?");
    mysqli_stmt_bind_param($checkStmt, "s", $email);
    mysqli_stmt_execute($checkStmt);
    mysqli_stmt_store_result($checkStmt);

    if (mysqli_stmt_num_rows($checkStmt) > 0) {
        header("Location: ../index.html?error=" . urlencode("Email address is already registered. Please log in."));
        exit();
    }
    mysqli_stmt_close($checkStmt);

    // Map role string to database role_id
    $roleId = ($role === 'professor') ? 2 : 1;
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Insert user into database
    $insertStmt = mysqli_prepare($conn, "INSERT INTO users (full_name, email, password_hash, role_id) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($insertStmt, "sssi", $fullName, $email, $passwordHash, $roleId);

    if (mysqli_stmt_execute($insertStmt)) {
        // Redirect to OTP verification page to verify account email
        header("Location: ../otp.html?email=" . urlencode($email));
        exit();
    } else {
        header("Location: ../index.html?error=" . urlencode("Failed to create account. Please try again."));
        exit();
    }
} else {
    header("Location: ../index.html");
    exit();
}
?>