<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/RouterService.php';
require_once __DIR__ . '/MercadoPagoService.php';

$db = db();
$stmt = $db->query("SELECT * FROM pix_charges WHERE status='PENDING' AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $payload = json_decode((string)$row['inter_payload'], true) ?: [];
    $paymentId = $payload['id'] ?? null;
    if (!$paymentId) continue;

    try {
        $remote = (new MercadoPagoService)->getPayment((int)$paymentId);
        if (($remote['status'] ?? '') === 'approved') {
            $db->prepare("UPDATE pix_charges SET status='PAID', paid_at=NOW(), inter_payload=? WHERE id=?")
               ->execute([json_encode($remote), $row['id']]);
            $p = $db->prepare('SELECT duration_minutes,mikrotik_profile FROM plans WHERE id=?');
            $p->execute([$row['plan_id']]);
            if ($plan = $p->fetch(PDO::FETCH_ASSOC)) {
                try {
                    (new RouterService)->grantHotspotAccess($row['client_identifier'], (int)$plan['duration_minutes'], $plan['mikrotik_profile']);
                } catch (Throwable $re) {
                    $db->prepare("INSERT INTO pending_commands (command, client_identifier, profile, duration_minutes) VALUES ('grant', ?, ?, ?)")
                       ->execute([$row['client_identifier'], $plan['mikrotik_profile'], (int)$plan['duration_minutes']]);
                    error_log('check-pending: REST falhou, gravado em pending_commands: ' . $re->getMessage());
                }
                $db->prepare('UPDATE pix_charges SET granted_at=NOW() WHERE id=?')->execute([$row['id']]);
                if (!empty($row['customer_id'])) {
                    $db->prepare('UPDATE customer_sessions SET plan_id=?, pix_charge_id=?, session_end=DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE customer_id=? AND mac_address=? ORDER BY id DESC LIMIT 1')
                       ->execute([$row['plan_id'], $row['id'], (int)$plan['duration_minutes'], $row['customer_id'], $row['client_identifier']]);
                }
                // Enviar notificacao push
                try {
                    require_once __DIR__ . '/WebPush.php';
                    $wp = new WebPush();
                    $wp->sendToClient($row['client_identifier'], 'Internet Liberada!', 'Seu pagamento foi confirmado e seu pacote esta ativo. Voce ja pode navegar!');
                } catch (Throwable $e) {
                    error_log('WebPush erro (check-pending): ' . $e->getMessage());
                }
                echo "  -> Acesso liberado! Perfil={$plan['mikrotik_profile']}, Minutos={$plan['duration_minutes']}\n";
            }
        }
    } catch (Throwable $e) {
        error_log('check-pending: ' . $e->getMessage());
    }
}
