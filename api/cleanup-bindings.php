<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$base = rtrim((string) env('MIKROTIK_REST_URL'), '/');
$user = (string) env('MIKROTIK_USER');
$pass = (string) env('MIKROTIK_PASS');

function routerRequest(string $base, string $user, string $pass, string $method, string $endpoint, ?array $payload = null): array {
    $ch = curl_init($base . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_USERPWD        => $user . ':' . $pass,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
    ]);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($response === false || $code < 200 || $code >= 300) return [];
    $json = json_decode((string)$response, true);
    return is_array($json) ? $json : [];
}

$now = time();
$bindings = routerRequest($base, $user, $pass, 'GET', '/ip/hotspot/ip-binding');

foreach ($bindings as $b) {
    $comment = $b['comment'] ?? '';
    $mac = $b['mac-address'] ?? '';
    $id = $b['.id'] ?? '';

    if (!str_starts_with($comment, 'PIX-EXP-') || empty($mac) || empty($id)) continue;

    $expiration = (int) substr($comment, 8);
    if ($now < $expiration) continue;

    routerRequest($base, $user, $pass, 'DELETE', '/ip/hotspot/ip-binding/' . $id);
    error_log("cleanup-bindings: Removido ip-binding expirado para $mac");

    $active = routerRequest($base, $user, $pass, 'GET', '/ip/hotspot/active?mac-address=' . urlencode($mac));
    foreach ($active as $s) {
        if (($s['mac-address'] ?? '') === $mac && isset($s['.id'])) {
            routerRequest($base, $user, $pass, 'DELETE', '/ip/hotspot/active/' . $s['.id']);
        }
    }

    $users = routerRequest($base, $user, $pass, 'GET', '/ip/hotspot/user?name=' . urlencode($mac));
    foreach ($users as $u) {
        if (($u['name'] ?? '') === $mac && isset($u['.id'])) {
            routerRequest($base, $user, $pass, 'DELETE', '/ip/hotspot/user/' . $u['.id']);
        }
    }

    echo "  -> Expirado e removido: $mac\n";
}
