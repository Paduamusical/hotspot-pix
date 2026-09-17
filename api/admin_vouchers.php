<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requireAdmin();
require_once __DIR__ . '/database.php';

$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rows = $db->query('SELECT v.*, p.name AS plan_name FROM vouchers v LEFT JOIN plans p ON p.id=v.plan_id ORDER BY v.id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
    $plans = $db->query('SELECT id,name,duration_minutes FROM plans WHERE active=1 ORDER BY price_cents')->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(['vouchers' => $rows, 'plans' => $plans, 'csrf' => csrf()]);
}

verifyCsrf();
$d = input();

if (($_SERVER['REQUEST_METHOD'] === 'POST') && !isset($d['action'])) {
    $planId = filter_var($d['plan_id'] ?? null, FILTER_VALIDATE_INT);
    $qty    = max(1, min(50, (int)($d['quantity'] ?? 1)));
    $expires = trim((string)($d['expires_at'] ?? ''));
    if (!$planId) jsonResponse(['error' => 'Selecione um plano.'], 422);
    $p = $db->prepare('SELECT id FROM plans WHERE id=?');
    $p->execute([$planId]);
    if (!$p->fetch()) jsonResponse(['error' => 'Plano invalido.'], 404);
    $codes = [];
    for ($i = 0; $i < $qty; $i++) {
        $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $db->prepare('INSERT INTO vouchers(code,plan_id,expires_at) VALUES(?,?,?)')->execute([$code, $planId, $expires ?: null]);
        $codes[] = $code;
    }
    jsonResponse(['ok' => true, 'codes' => $codes]);
}

if (($_SERVER['REQUEST_METHOD'] === 'POST') && ($d['action'] ?? '') === 'cancel') {
    $id = filter_var($d['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id) jsonResponse(['error' => 'ID invalido'], 422);
    $db->prepare("UPDATE vouchers SET status='CANCELLED' WHERE id=? AND status='AVAILABLE'")->execute([$id]);
    jsonResponse(['ok' => true]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = filter_var($d['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id) jsonResponse(['error' => 'ID invalido'], 422);
    $db->prepare('DELETE FROM vouchers WHERE id=?')->execute([$id]);
    jsonResponse(['ok' => true]);
}

jsonResponse(['error' => 'Acao invalida'], 400);
