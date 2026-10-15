<?php
// register.php
include 'db.php';

if (isset($_POST['btnRegister'])) {
    $fullName = trim($_POST['regFullName'] ?? '');
    $email = strtolower(trim($_POST['regEmail'] ?? ''));
    $password = $_POST['regPassword'] ?? '';
    $confirmPassword = $_POST['regConfirmPassword'] ?? '';
    $regRole = strtolower(trim($_POST['regRole'] ?? 'student'));

    // 1. Basic validation
    if (empty($fullName) || empty($email) || empty($password)) {
        header("Location: ../index.html?error=" . urlencode("All fields are required."));
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../index.html?error=" . urlencode("Please enter a valid email format."));
        exit();
    }

    // 2. Strict PUP domain validation
    $emailParts = explode('@', $email);
    $domain = end($emailParts);

    if ($regRole === 'professor' || $regRole === 'teacher') {
        if ($domain !== 'pup.edu.ph') {
            header("Location: ../index.html?error=" . urlencode("Professor registration requires an official @pup.edu.ph email address. Other domains (including Gmail) are not accepted."));
            exit();
        }
        $roleId = 2;
    } else {
        if ($domain !== 'iskolarngbayan.pup.edu.ph') {
            header("Location: ../index.html?error=" . urlencode("Student registration requires an official @iskolarngbayan.pup.edu.ph email address. Other domains (including Gmail) are not accepted."));
            exit();
        }
        $roleId = 3;
    }

    // 3. Password matching and strength
    if ($password !== $confirmPassword) {
        header("Location: ../index.html?error=" . urlencode("Passwords do not match."));
        exit();
    }

    if (strlen($password) < 6) {
        header("Location: ../index.html?error=" . urlencode("Password must be at least 6 characters."));
        exit();
    }

    // 4. Check if email already exists
    $checkStmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ?");
    mysqli_stmt_bind_param($checkStmt, "s", $email);
    mysqli_stmt_execute($checkStmt);
    $checkRes = mysqli_stmt_get_result($checkStmt);

    if (mysqli_fetch_assoc($checkRes)) {
        header("Location: ../index.html?error=" . urlencode("An account with this email is already registered."));
        exit();
    }

    // 5. Insert new user
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $insertStmt = mysqli_prepare($conn, "INSERT INTO users (full_name, email, password_hash, role_id) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($insertStmt, "sssi", $fullName, $email, $hashedPassword, $roleId);

    if (mysqli_stmt_execute($insertStmt)) {
        header("Location: ../index.html?registered=success");
        exit();
    } else {
        header("Location: ../index.html?error=" . urlencode("Registration failed: " . mysqli_error($conn)));
        exit();
    }
}
?>
