<?php
declare(strict_types=1);

function env(string $key, ?string $default = null): ?string {
    static $values = null;
    if ($values === null) {
        $values = $_ENV;
        $file = dirname(__DIR__) . '/.env';
        if (is_file($file)) foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
            [$name, $value] = explode('=', $line, 2);
            $values[trim($name)] = trim($value, " \t\n\r\0\x0B\"");
        }
    }
    return $values[$key] ?? getenv($key) ?: $default;
}

function jsonResponse(array $data, int $status = 200): never {
    http_response_code($status); header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE); exit;
}
function input(): array { return json_decode(file_get_contents('php://input'), true) ?: $_POST; }
