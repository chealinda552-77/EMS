<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_login();
require_admin();

$payload = request_json();
$token = $payload['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
if (!verify_csrf_token($token)) {
    json_response(419, array('success' => false, 'message' => 'Invalid CSRF token.'));
}

$settings = db()->query('SELECT scanner_mode, api_endpoint FROM scanner_settings WHERE id = 1')->fetch();

$mode = $settings['scanner_mode'] ?? 'manual';
$endpoint = trim((string) ($settings['api_endpoint'] ?? ''));

if ($mode !== 'api') {
    json_response(400, array('success' => false, 'message' => 'Scanner mode is not API.'));
}

if ($endpoint === '') {
    json_response(400, array('success' => false, 'message' => 'Scanner API endpoint is empty.'));
}

$responseBody = '';

if (function_exists('curl_init')) {
    $handle = curl_init($endpoint);
    curl_setopt_array(
        $handle,
        array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 5,
        )
    );
    $responseBody = (string) curl_exec($handle);
    $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $error = curl_error($handle);
    curl_close($handle);

    if ($responseBody === '' || $statusCode >= 400) {
        json_response(502, array('success' => false, 'message' => 'Scanner service error: ' . ($error ?: 'Bad response')));
    }
} else {
    $context = stream_context_create(
        array(
            'http' => array(
                'method' => 'GET',
                'timeout' => 12,
            ),
        )
    );

    $responseBody = (string) @file_get_contents($endpoint, false, $context);
    if ($responseBody === '') {
        json_response(502, array('success' => false, 'message' => 'Unable to reach scanner service.'));
    }
}

$decoded = json_decode($responseBody, true);

if (is_array($decoded)) {
    $fingerprintId = trim((string) ($decoded['fingerprint_id'] ?? ($decoded['id'] ?? ($decoded['template_id'] ?? ''))));
    if ($fingerprintId === '') {
        json_response(502, array('success' => false, 'message' => 'Scanner response missing fingerprint_id.'));
    }
    json_response(200, array('success' => true, 'fingerprint_id' => $fingerprintId, 'source' => 'api'));
}

$fallbackFingerprint = trim($responseBody);
if ($fallbackFingerprint !== '') {
    json_response(200, array('success' => true, 'fingerprint_id' => $fallbackFingerprint, 'source' => 'api'));
}

json_response(502, array('success' => false, 'message' => 'Invalid scanner response.'));
