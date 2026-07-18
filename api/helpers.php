<?php

function api_headers(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function json_input(): array
{
    $body = file_get_contents('php://input');
    $data = json_decode($body ?: '[]', true);

    return is_array($data) ? $data : [];
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function create_token(int $userId): string
{
    $config = require __DIR__ . '/config.php';
    $expiresAt = time() + (int) $config['app']['token_ttl_seconds'];
    $payload = base64_encode(json_encode([
        'uid' => $userId,
        'exp' => $expiresAt,
    ]));
    $signature = hash_hmac('sha256', $payload, $config['app']['token_secret']);

    return $payload . '.' . $signature;
}

