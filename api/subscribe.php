<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

$db = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// GET: retorna a chave VAPID publica
if ($method === 'GET') {
    jsonResponse(['vapidPublicKey' => env('VAPID_PUBLIC_KEY', '')]);
}

// POST: salva a subscription
if ($method === 'POST') {
    $body = input();
    $client = preg_replace('/[^a-zA-Z0-9:_.-]/', '', (string)($body['client'] ?? ''));
    $endpoint = (string)($body['endpoint'] ?? '');
    $p256dh = (string)($body['keys']['p256dh'] ?? $body['p256dh'] ?? '');
    $auth = (string)($body['keys']['auth'] ?? $body['auth'] ?? '');

    if (!$client || !$endpoint || !$p256dh || !$auth) {
        jsonResponse(['error' => 'Dados incompletos'], 422);
    }

    // Remover subscriptions antigas do mesmo cliente
    $db->prepare("DELETE FROM push_subscriptions WHERE client_identifier=?")->execute([$client]);

    // Salvar nova subscription
    $db->prepare("INSERT INTO push_subscriptions (client_identifier, endpoint, p256dh, auth) VALUES (?, ?, ?, ?)")
       ->execute([$client, $endpoint, $p256dh, $auth]);

    error_log("subscribe.php: Subscription salva para $client");
    jsonResponse(['ok' => true]);
}

jsonResponse(['error' => 'Metodo nao suportado'], 405);
