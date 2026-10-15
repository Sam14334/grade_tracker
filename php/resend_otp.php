<?php
// resend otp endpoint
session_start();
header('Content-Type: application/json');

$email = strtolower(trim($_POST['email'] ?? $_SESSION['pending_otp_email'] ?? ''));

if (empty($email)) {
    echo json_encode(['success' => false, 'error' => 'Email address is required.']);
    exit();
}

include 'db.php';
require_once __DIR__ . '/resend.php';

try {
    // get pending record
    $stmt = $conn->prepare("SELECT * FROM otp_verifications WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $pending = $stmt->fetch();

    if (!$pending) {
        echo json_encode(['success' => false, 'error' => 'No pending verification found for this email.']);
        exit();
    }

    // generate new 6-digit otp
    $newOtp = sprintf("%06d", random_int(100000, 999999));
    $newExpiresAt = date('Y-m-d H:i:sP', strtotime('+10 minutes'));

    // update record with new code and expiry
    $update = $conn->prepare("UPDATE otp_verifications SET otp_code = ?, expires_at = ? WHERE email = ?");
    $update->execute([$newOtp, $newExpiresAt, $email]);

    // send via resend
    $subject = "Your New Grade Tracker Verification Code";
    $html = "
        <div style='font-family: sans-serif; max-width: 480px; margin: auto; padding: 24px; border: 1px solid #f1dede; border-radius: 12px;'>
            <h2 style='color: #800020; margin-bottom: 8px;'>Grade Tracker Verification</h2>
            <p>Hello <strong>" . htmlspecialchars($pending['full_name']) . "</strong>,</p>
            <p>Here is your new 6-digit verification code:</p>
            <div style='text-align: center; margin: 24px 0;'>
                <span style='font-size: 32px; font-weight: bold; letter-spacing: 4px; color: #800020; background: #fdf2f4; padding: 10px 24px; border-radius: 8px; border: 1px dashed #be2b4b;'>{$newOtp}</span>
            </div>
            <p style='color: #666; font-size: 13px;'>This code is valid for 10 minutes.</p>
        </div>
    ";
    sendResendEmail($email, $subject, $html);

    echo json_encode(['success' => true, 'message' => 'A new verification code has been sent to your email.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
