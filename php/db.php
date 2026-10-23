<?php
// db.php

// load .env file
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));

        if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
            (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
            $val = substr($val, 1, -1);
        }

        $_ENV[$key] = $val;
        $_SERVER[$key] = $val;
        putenv("$key=$val");
    }
}

// supabase postgres pdo connection
$host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'localhost');
$port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '5432');
$dbname = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'postgres');
$user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'postgres');
$password = getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? '');
$sslmode = getenv('DB_SSLMODE') ?: ($_ENV['DB_SSLMODE'] ?? 'require');

$dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode={$sslmode}";

try {
    $conn = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') ||
        str_ends_with($_SERVER['SCRIPT_NAME'] ?? '', '_otp.php')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database connection failed. Check .env credentials.']);
        exit();
    }
    die("Database connection failed: " . $e->getMessage());
}
?>