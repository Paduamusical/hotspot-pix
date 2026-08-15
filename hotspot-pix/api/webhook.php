<?php
// Configure este endereço no Inter e valide mTLS/IP conforme o contrato antes de usar em produção.
declare(strict_types=1); require_once __DIR__ . '/database.php'; require_once __DIR__ . '/RouterService.php';
$payload = json_decode(file_get_contents('php://input'), true) ?: []; $db=db();
foreach (($payload['pix'] ?? []) as $pix) { if (empty($pix['txid'])) continue; $s=$db->prepare("SELECT * FROM pix_charges WHERE txid=? AND status='PENDING'"); $s->execute([$pix['txid']]); if ($row=$s->fetch(PDO::FETCH_ASSOC)) { $db->prepare("UPDATE pix_charges SET status='PAID',paid_at=NOW(),inter_payload=? WHERE id=?")->execute([json_encode($pix),$row['id']]); $p=$db->prepare('SELECT duration_minutes,mikrotik_profile FROM plans WHERE id=?'); $p->execute([$row['plan_id']]); if($plan=$p->fetch(PDO::FETCH_ASSOC)){ (new RouterService)->grantHotspotAccess($row['client_identifier'],(int)$plan['duration_minutes'],$plan['mikrotik_profile']); $db->prepare('UPDATE pix_charges SET granted_at=NOW() WHERE id=?')->execute([$row['id']]); } } }
http_response_code(204);
