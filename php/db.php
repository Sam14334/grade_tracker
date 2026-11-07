<?php
// db.php

require_once __DIR__ . '/config.php';

try {
    $host = requiredConfig('DB_HOST');
    $port = requiredConfig('DB_PORT');
    $dbname = requiredConfig('DB_NAME');
    $user = requiredConfig('DB_USER');
    $password = requiredConfig('DB_PASSWORD');
    $sslmode = requiredConfig('DB_SSLMODE');
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode={$sslmode}";
    $conn = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (RuntimeException $e) {
    error_log('Database initialization failed: ' . $e->getMessage());
    http_response_code(503);
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') ||
        str_ends_with($_SERVER['SCRIPT_NAME'] ?? '', '_otp.php')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database connection failed. Check .env credentials.']);
        exit();
    }
    die('Database connection failed. Check the database configuration.');
}

/**
 * Ensures required rate limiting columns exist on otp_verifications table
 */
function ensureOtpRateLimitColumns(PDO $conn) {
    static $columnsEnsured = false;
    if ($columnsEnsured) return;
    try {
        $conn->exec("
            ALTER TABLE otp_verifications ADD COLUMN IF NOT EXISTS resend_count INT DEFAULT 0;
            ALTER TABLE otp_verifications ADD COLUMN IF NOT EXISTS failed_attempts INT DEFAULT 0;
            ALTER TABLE otp_verifications ADD COLUMN IF NOT EXISTS last_resend_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP;
        ");
        $columnsEnsured = true;
    } catch (Exception $e) {
        error_log("OTP migration check: " . $e->getMessage());
    }
}
?>
