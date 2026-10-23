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

    // check if email is already registered
    $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
    $checkStmt->execute([$email]);

    if ($checkStmt->fetch()) {
        header("Location: ../index.html?error=" . urlencode("Email address is already registered. Please log in."));
        exit();
    }

    $roleId = ($role === 'professor') ? 2 : 3;
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // generate 6-digit otp
    $otpCode = sprintf("%06d", random_int(100000, 999999));
    $expiresAt = date('Y-m-d H:i:sP', strtotime('+10 minutes'));

    // save pending registration to otp_verifications
    $otpStmt = $conn->prepare("
        INSERT INTO otp_verifications (email, full_name, password_hash, role_id, otp_code, expires_at)
        VALUES (?, ?, ?, ?, ?, ?)
        ON CONFLICT (email) DO UPDATE SET
            full_name = EXCLUDED.full_name,
            password_hash = EXCLUDED.password_hash,
            role_id = EXCLUDED.role_id,
            otp_code = EXCLUDED.otp_code,
            expires_at = EXCLUDED.expires_at,
            created_at = CURRENT_TIMESTAMP
    ");
    $otpStmt->execute([$email, $fullName, $passwordHash, $roleId, $otpCode, $expiresAt]);

    // send otp email via resend
    require_once __DIR__ . '/resend.php';
    try {
        $subject = "Your Grade Tracker Verification Code";
        $html = "
            <div style='font-family: sans-serif; max-width: 480px; margin: auto; padding: 24px; border: 1px solid #f1dede; border-radius: 12px;'>
                <h2 style='color: #800020; margin-bottom: 8px;'>Grade Tracker Verification</h2>
                <p>Hello <strong>" . htmlspecialchars($fullName) . "</strong>,</p>
                <p>Use the 6-digit verification code below to verify your PUP account:</p>
                <div style='text-align: center; margin: 24px 0;'>
                    <span style='font-size: 32px; font-weight: bold; letter-spacing: 4px; color: #800020; background: #fdf2f4; padding: 10px 24px; border-radius: 8px; border: 1px dashed #be2b4b;'>{$otpCode}</span>
                </div>
                <p style='color: #666; font-size: 13px;'>This code is valid for 10 minutes. If you did not request this, please ignore this email.</p>
            </div>
        ";
        sendResendEmail($email, $subject, $html);
    } catch (Exception $e) {
        error_log("Resend error: " . $e->getMessage());
    }

    $_SESSION['pending_otp_email'] = $email;
    header("Location: ../otp.html?email=" . urlencode($email));
    exit();
} else {
    header("Location: ../index.html");
    exit();
}
?>