<?php
require_once __DIR__ . '/config.php';

// send email via resend api
function sendResendEmail($to, $subject, $html) {
    $apiKey = requiredConfig('RESEND_API_KEY');
    $from = requiredConfig('RESEND_FROM_EMAIL');

    $ch = curl_init('https://api.resend.com/emails');
    $payload = json_encode([
        'from' => $from,
        'to' => [$to],
        'subject' => $subject,
        'html' => $html,
    ]);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("cURL error: " . $error);
    }

    $data = json_decode($response, true);
    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception($data['message'] ?? 'Failed to send email via Resend');
    }

    return true;
}
