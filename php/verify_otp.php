<?php
// verify otp endpoint
session_start();
header('Content-Type: application/json');

$email = strtolower(trim($_POST['email'] ?? $_SESSION['pending_otp_email'] ?? ''));
$otp = trim($_POST['otp'] ?? '');

if (empty($email) || empty($otp)) {
    echo json_encode(['success' => false, 'error' => 'Email and OTP code are required.']);
    exit();
}

include 'db.php';

try {
    ensureOtpRateLimitColumns($conn);

    // get pending record
    $stmt = $conn->prepare("SELECT * FROM otp_verifications WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $pending = $stmt->fetch();

    if (!$pending) {
        echo json_encode(['success' => false, 'error' => 'No pending verification found for this email.']);
        exit();
    }

    // check code expiry
    if (strtotime($pending['expires_at']) < time()) {
        echo json_encode(['success' => false, 'error' => 'Verification code has expired. Please request a new one.']);
        exit();
    }

    // verify otp code match with failed attempt rate limiting
    if ($pending['otp_code'] !== $otp) {
        $failedAttempts = (int)($pending['failed_attempts'] ?? 0) + 1;
        $maxAttempts = 5;

        if ($failedAttempts >= $maxAttempts) {
            $del = $conn->prepare("DELETE FROM otp_verifications WHERE email = ?");
            $del->execute([$email]);
            unset($_SESSION['pending_otp_email']);

            echo json_encode([
                'success' => false,
                'locked' => true,
                'error' => "Too many incorrect attempts ({$maxAttempts}/{$maxAttempts}). Verification session expired for security. Please register again."
            ]);
            exit();
        }

        $updateFail = $conn->prepare("UPDATE otp_verifications SET failed_attempts = ? WHERE email = ?");
        $updateFail->execute([$failedAttempts, $email]);

        $remaining = $maxAttempts - $failedAttempts;
        echo json_encode([
            'success' => false,
            'error' => "Invalid verification code. {$remaining} attempt(s) remaining."
        ]);
        exit();
    }

    // insert confirmed user into users
    $insert = $conn->prepare("
        INSERT INTO users (full_name, email, password_hash, role_id)
        VALUES (?, ?, ?, ?)
        ON CONFLICT (email) DO UPDATE SET
            full_name = EXCLUDED.full_name,
            password_hash = EXCLUDED.password_hash,
            role_id = EXCLUDED.role_id
        RETURNING user_id
    ");
    $insert->execute([
        $pending['full_name'],
        $pending['email'],
        $pending['password_hash'],
        $pending['role_id']
    ]);

    // clear verified otp record
    $del = $conn->prepare("DELETE FROM otp_verifications WHERE email = ?");
    $del->execute([$email]);

    unset($_SESSION['pending_otp_email']);

    echo json_encode(['success' => true, 'redirect' => 'index.html?registered=success']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
