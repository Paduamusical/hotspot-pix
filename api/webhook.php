<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
file_put_contents(__DIR__.'/webhook.log', date('c').' '.file_get_contents('php://input')."\n", FILE_APPEND);
require_once __DIR__ . '/database.php';

/**
 * Tenta liberar o acesso via REST API do MikroTik (instantâneo na LAN).
 * Se falhar (MikroTik remoto/CGNAT), o comando fica PENDING e o MikroTik
 * puxa via HTTPS em até 5 segundos.
 */
function tryRestGrant(PDO $db, int $cmdId, string $client, int $minutes, string $profile): void {
    try {
        require_once __DIR__ . '/RouterService.php';
        $router = new RouterService();
        $router->grantHotspotAccess($client, $minutes, $profile);
        // REST API funcionou — marcar comando como DONE
        $db->prepare("UPDATE pending_commands SET status='DONE', executed_at=NOW() WHERE id=?")
           ->execute([$cmdId]);
        error_log("tryRestGrant: Grant via REST API SUCCESS para $client");
    } catch (Throwable $e) {
        // REST API falhou — comando continua PENDING, MikroTik puxa via HTTPS
        error_log("tryRestGrant: REST API falhou, dependendo do pull: " . $e->getMessage());
    }
}

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$db = db();

// ========================================================================
// MODO: BANCO INTER
// ========================================================================
if (env('PAYMENT_MODE') === 'inter') {
    foreach (($payload['pix'] ?? []) as $pix) {
        if (empty($pix['txid'])) continue;
        $s = $db->prepare("SELECT * FROM pix_charges WHERE txid=? AND status='PENDING'");
        $s->execute([$pix['txid']]);
        if ($row = $s->fetch(PDO::FETCH_ASSOC)) {
            $db->prepare("UPDATE pix_charges SET status='PAID', paid_at=NOW(), inter_payload=? WHERE id=?")
               ->execute([json_encode($pix), $row['id']]);
            $p = $db->prepare('SELECT duration_minutes,mikrotik_profile FROM plans WHERE id=?');
            $p->execute([$row['plan_id']]);
            if ($plan = $p->fetch(PDO::FETCH_ASSOC)) {
                $db->prepare("INSERT INTO pending_commands (command, client_identifier, profile, duration_minutes) VALUES ('grant', ?, ?, ?)")
                   ->execute([$row['client_identifier'], $plan['mikrotik_profile'], (int)$plan['duration_minutes']]);
                $cmdId = (int)$db->lastInsertId();
                $db->prepare('UPDATE pix_charges SET granted_at=NOW() WHERE id=?')->execute([$row['id']]);
                if (!empty($row['customer_id'])) {
                    $db->prepare('UPDATE customer_sessions SET plan_id=?, pix_charge_id=?, session_end=DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE customer_id=? AND mac_address=? ORDER BY id DESC LIMIT 1')
                       ->execute([$row['plan_id'], $row['id'], (int)$plan['duration_minutes'], $row['customer_id'], $row['client_identifier']]);
                }
                // Tentar grant via REST API (instantâneo na LAN)
                tryRestGrant($db, $cmdId, $row['client_identifier'], (int)$plan['duration_minutes'], $plan['mikrotik_profile']);
                // Enviar notificacao push
                try {
                    require_once __DIR__ . '/WebPush.php';
                    $wp = new WebPush();
                    $wp->sendToClient($row['client_identifier'], 'Internet Liberada!', 'Seu pagamento foi confirmado e seu pacote esta ativo. Voce ja pode navegar!');
                } catch (Throwable $e) {
                    error_log('WebPush erro: ' . $e->getMessage());
                }
            }
        }
    }
}

// ========================================================================
// MODO: MERCADO PAGO
// ========================================================================
if (env('PAYMENT_MODE') === 'mercadopago') {
    require_once __DIR__ . '/MercadoPagoService.php';

    $paymentId = (int)($payload['data']['id'] ?? $_GET['data_id'] ?? $_GET['id'] ?? 0);

    if ($paymentId) {
        try {
            $mp = new MercadoPagoService;
            $payment = $mp->getPayment($paymentId);
            $status = $payment['status'] ?? '';
            $txid   = $payment['external_reference'] ?? '';

            error_log('Webhook MP: paymentId=' . $paymentId . ' status=' . $status . ' txid=' . $txid);

            if ($status === 'approved') {
                if ($txid) {
                    $s = $db->prepare("SELECT * FROM pix_charges WHERE txid=? AND status='PENDING'");
                    $s->execute([$txid]);
                    if ($row = $s->fetch(PDO::FETCH_ASSOC)) {
                        $db->prepare("UPDATE pix_charges SET status='PAID', paid_at=NOW(), inter_payload=? WHERE id=?")
                           ->execute([json_encode($payment), $row['id']]);
                        $p = $db->prepare('SELECT duration_minutes,mikrotik_profile FROM plans WHERE id=?');
                        $p->execute([$row['plan_id']]);
                        if ($plan = $p->fetch(PDO::FETCH_ASSOC)) {
                            $db->prepare("INSERT INTO pending_commands (command, client_identifier, profile, duration_minutes) VALUES ('grant', ?, ?, ?)")
                               ->execute([$row['client_identifier'], $plan['mikrotik_profile'], (int)$plan['duration_minutes']]);
                            $cmdId = (int)$db->lastInsertId();
                            $db->prepare('UPDATE pix_charges SET granted_at=NOW() WHERE id=?')->execute([$row['id']]);
                            if (!empty($row['customer_id'])) {
                                $db->prepare('UPDATE customer_sessions SET plan_id=?, pix_charge_id=?, session_end=DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE customer_id=? AND mac_address=? ORDER BY id DESC LIMIT 1')
                                   ->execute([$row['plan_id'], $row['id'], (int)$plan['duration_minutes'], $row['customer_id'], $row['client_identifier']]);
                            }
                            error_log('Webhook MP: Comando grant gravado para ' . $row['client_identifier'] . ' plano=' . $plan['mikrotik_profile'] . ' duracao=' . $plan['duration_minutes'] . 'min');
                            // Tentar grant via REST API (instantâneo na LAN)
                            tryRestGrant($db, $cmdId, $row['client_identifier'], (int)$plan['duration_minutes'], $plan['mikrotik_profile']);
                // Enviar notificacao push
                try {
                    require_once __DIR__ . '/WebPush.php';
                    $wp = new WebPush();
                    $wp->sendToClient($row['client_identifier'], 'Internet Liberada!', 'Seu pagamento foi confirmado e seu pacote esta ativo. Voce ja pode navegar!');
                } catch (Throwable $e) {
                    error_log('WebPush erro: ' . $e->getMessage());
                }
                        }
                    } else {
                        error_log('Webhook MP: Cobranca nao encontrada ou ja paga para txid=' . $txid);
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('Erro webhook MP: ' . $e->getMessage());
        }
    } else {
        error_log('Webhook MP: paymentId nao encontrado. GET=' . json_encode($_GET) . ' payload=' . json_encode($payload));
    }
}

http_response_code(200);
