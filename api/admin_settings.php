<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
requireAdmin();
require_once __DIR__ . '/database.php';
header('Content-Type: application/json; charset=utf-8');

$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $row = $db->query('SELECT payment_mode, mp_public_key, CASE WHEN mp_access_token != "" THEN 1 ELSE 0 END as has_token FROM settings WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    csrf();
    jsonResponse(['settings' => $row, 'csrf' => $_SESSION['csrf']]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $body = input();
    $token = trim($body['mp_access_token'] ?? '');
    $publicKey = trim($body['mp_public_key'] ?? '');
    $mode = trim($body['payment_mode'] ?? 'mercadopago');

    // Se o token veio vazio, não sobrescrever o existente
    if ($token === '') {
        $db->prepare('UPDATE settings SET mp_public_key=?, payment_mode=? WHERE id=1')->execute([$publicKey, $mode]);
    } else {
        $db->prepare('UPDATE settings SET mp_access_token=?, mp_public_key=?, payment_mode=? WHERE id=1')->execute([$token, $publicKey, $mode]);
    }

    jsonResponse(['success' => true, 'message' => 'Configurações salvas com sucesso!']);
}

jsonResponse(['error' => 'Método não permitido'], 405);
