<?php
declare(strict_types=1); require_once __DIR__ . '/database.php'; require_once __DIR__ . '/InterPixService.php'; require_once __DIR__ . '/RouterService.php';
function txid(): string { return bin2hex(random_bytes(16)); }
function grant(PDO $db, array $charge): void {
    if ($charge['granted_at']) return;
    $plan = $db->prepare('SELECT duration_minutes,mikrotik_profile FROM plans WHERE id=?'); $plan->execute([$charge['plan_id']]); $p = $plan->fetch(PDO::FETCH_ASSOC);
    if (!$p) throw new RuntimeException('Plano não encontrado');
    (new RouterService)->grantHotspotAccess($charge['client_identifier'], (int)$p['duration_minutes'], $p['mikrotik_profile']);
    $db->prepare('UPDATE pix_charges SET granted_at=NOW() WHERE id=?')->execute([$charge['id']]);
}
try {
    $db = db(); $body = input(); $action = $body['action'] ?? $_GET['action'] ?? '';
    if ($action === 'create') {
        $planId = filter_var($body['plan_id'] ?? null, FILTER_VALIDATE_INT); $client = preg_replace('/[^a-zA-Z0-9:_.-]/', '', (string)($body['client'] ?? ''));
        if (!$planId || !$client) jsonResponse(['error'=>'Informe plano e identificador do dispositivo.'], 422);
        $p = $db->prepare('SELECT * FROM plans WHERE id=? AND active=1'); $p->execute([$planId]); $plan = $p->fetch(PDO::FETCH_ASSOC); if (!$plan) jsonResponse(['error'=>'Plano inválido.'], 404);
        $id = txid(); $inter = new InterPixService; $charge = $inter->createCharge($id, (int)$plan['price_cents']);
        $db->prepare('INSERT INTO pix_charges(txid,plan_id,client_identifier,amount_cents,inter_payload) VALUES(?,?,?,?,?)')->execute([$id,$planId,$client,$plan['price_cents'],json_encode($charge)]);
        $locationId = $charge['loc']['id'] ?? null; if (!$locationId) throw new RuntimeException('Inter não retornou localização QR.');
        $qr = $inter->getQrCode((int)$locationId);
        jsonResponse(['txid'=>$id, 'copy_paste'=>$qr['qrcode'], 'image'=>$qr['imagemQrcode'] ?? null, 'expires_in'=>$charge['calendario']['expiracao'] ?? 900]);
    }
    if ($action === 'status') {
        $id = preg_replace('/[^a-zA-Z0-9]/', '', (string)($body['txid'] ?? $_GET['txid'] ?? '')); $stmt=$db->prepare('SELECT * FROM pix_charges WHERE txid=?'); $stmt->execute([$id]); $row=$stmt->fetch(PDO::FETCH_ASSOC); if (!$row) jsonResponse(['error'=>'Cobrança não encontrada'],404);
        if ($row['status'] === 'PENDING') { $remote=(new InterPixService)->getCharge($id); if (($remote['status'] ?? '') === 'CONCLUIDA') { $db->prepare("UPDATE pix_charges SET status='PAID', paid_at=NOW(),inter_payload=? WHERE id=?")->execute([json_encode($remote),$row['id']]); $row['status']='PAID'; } }
        if ($row['status'] === 'PAID') grant($db, $row); jsonResponse(['status'=>$row['status'], 'granted'=>($row['status']==='PAID')]);
    }
    jsonResponse(['error'=>'Ação inválida'], 400);
} catch (Throwable $e) { error_log($e->getMessage()); jsonResponse(['error'=>'Não foi possível processar a solicitação.'], 502); }
