<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function is_post()
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_base_path()
{
    static $basePath = null;

    if ($basePath !== null) {
        return $basePath;
    }

    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $projectRoot = realpath(__DIR__ . '/..');

    if ($documentRoot && $projectRoot) {
        $documentRoot = str_replace('\\', '/', $documentRoot);
        $projectRoot = str_replace('\\', '/', $projectRoot);

        if (strpos($projectRoot, $documentRoot) === 0) {
            $relative = substr($projectRoot, strlen($documentRoot));
            $basePath = rtrim(str_replace('\\', '/', $relative), '/');
            return $basePath === '' ? '' : $basePath;
        }
    }

    $basePath = '';
    return $basePath;
}

function url($path = '')
{
    $base = app_base_path();
    $path = ltrim((string) $path, '/');

    if ($path === '') {
        return $base === '' ? '/' : $base;
    }

    if ($base === '') {
        return '/' . $path;
    }

    return rtrim($base, '/') . '/' . $path;
}

function redirect($target)
{
    header('Location: ' . $target);
    exit;
}

function set_flash($type, $message)
{
    $_SESSION['flash'] = array(
        'type' => $type,
        'message' => $message,
    );
}

function get_flash()
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token)
{
    if (!is_string($token) || !isset($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf()
{
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (!verify_csrf_token($token)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

function request_json()
{
    $rawBody = file_get_contents('php://input');
    if (!is_string($rawBody) || trim($rawBody) === '') {
        return array();
    }

    $decoded = json_decode($rawBody, true);
    return is_array($decoded) ? $decoded : array();
}

function json_response($status, array $payload)
{
    http_response_code((int) $status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function format_datetime($value)
{
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime((string) $value);
    if ($timestamp === false) {
        return '-';
    }

    return date('Y-m-d H:i:s', $timestamp);
}
