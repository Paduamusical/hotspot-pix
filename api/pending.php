<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

try {
    $db = db();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // Confirmar execucao de comando (GET ?confirm=N)
    if ($method === 'GET' && isset($_GET['confirm'])) {
        $id = (int)$_GET['confirm'];
        if ($id > 0) {
            $stmt = $db->prepare("SELECT * FROM pending_commands WHERE id=? AND status='PENDING'");
            $stmt->execute([$id]);
            $cmd = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($cmd) {
                $db->prepare("UPDATE pending_commands SET status='DONE', executed_at=NOW() WHERE id=?")
                   ->execute([$id]);

                if ($cmd['command'] === 'cleanup') {
                    $db->prepare("UPDATE pix_charges SET cleanup_done=1 WHERE client_identifier=? AND status='PAID' AND cleanup_done=0")
                       ->execute([$cmd['client_identifier']]);
                }
                if ($cmd['command'] === 'temp_cleanup') {
                    $db->prepare("UPDATE pending_commands SET cleanup_done=1 WHERE client_identifier=? AND command='temporary' AND cleanup_done=0")
                       ->execute([$cmd['client_identifier']]);
                }
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($method === 'GET') {
        // 1) PACOTES EXPIRADOS -> inserir cleanup na fila
        $expired = $db->query(
            "SELECT c.client_identifier
             FROM pix_charges c
             JOIN plans p ON p.id = c.plan_id
             WHERE c.status='PAID'
               AND c.granted_at IS NOT NULL
               AND c.cleanup_done = 0
               AND DATE_ADD(c.granted_at, INTERVAL p.duration_minutes MINUTE) < NOW()
               AND NOT EXISTS (
                   SELECT 1 FROM pix_charges c2
                   JOIN plans p2 ON p2.id = c2.plan_id
                   WHERE c2.client_identifier = c.client_identifier
                     AND c2.status='PAID'
                     AND c2.granted_at IS NOT NULL
                     AND DATE_ADD(c2.granted_at, INTERVAL p2.duration_minutes MINUTE) > NOW()
               )
             ORDER BY c.granted_at DESC
             LIMIT 50"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($expired as $ex) {
            $client = $ex['client_identifier'];
            $check = $db->prepare("SELECT id FROM pending_commands WHERE client_identifier=? AND command='cleanup' AND status='PENDING'");
            $check->execute([$client]);
            if (!$check->fetch(PDO::FETCH_ASSOC)) {
                $db->prepare("INSERT INTO pending_commands (command, client_identifier, profile, duration_minutes) VALUES ('cleanup', ?, '', 0)")
                   ->execute([$client]);
            }
        }

        // 2) JANELA TEMPORARIA EXPIRADA -> inserir temp_cleanup
        $expiredTemp = $db->query(
            "SELECT DISTINCT t.client_identifier
             FROM pending_commands t
             WHERE t.command='temporary'
               AND t.status='DONE'
               AND t.cleanup_done = 0
               AND t.executed_at IS NOT NULL
               AND DATE_ADD(t.executed_at, INTERVAL 6 MINUTE) < NOW()
               AND NOT EXISTS (
                   SELECT 1 FROM pix_charges c
                   JOIN plans p ON p.id = c.plan_id
                   WHERE c.client_identifier = t.client_identifier
                     AND c.status='PAID'
                     AND c.granted_at IS NOT NULL
                     AND DATE_ADD(c.granted_at, INTERVAL p.duration_minutes MINUTE) > NOW()
               )
             LIMIT 50"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($expiredTemp as $ex) {
            $client = $ex['client_identifier'];
            $check = $db->prepare("SELECT id FROM pending_commands WHERE client_identifier=? AND command='temp_cleanup' AND status='PENDING'");
            $check->execute([$client]);
            if (!$check->fetch(PDO::FETCH_ASSOC)) {
                $db->prepare("INSERT INTO pending_commands (command, client_identifier, profile, duration_minutes) VALUES ('temp_cleanup', ?, '', 0)")
                   ->execute([$client]);
            }
        }

        // 3) BUSCAR COMANDOS PENDENTES - DEDUPLICAR POR client_identifier
        $stmt = $db->query("SELECT * FROM pending_commands WHERE status='PENDING' ORDER BY id ASC LIMIT 50");
        $allCommands = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Deduplicar: manter apenas o comando MAIS RECENTE por client_identifier
        $seen = [];
        $commands = [];
        foreach ($allCommands as $cmd) {
            $client = $cmd['client_identifier'];
            if (isset($seen[$client])) {
                // Marcar comandos antigos duplicados como DONE (pulados)
                $db->prepare("UPDATE pending_commands SET status='DONE', executed_at=NOW() WHERE id=?")
                   ->execute([$cmd['id']]);
                continue;
            }
            $seen[$client] = true;
            $commands[] = $cmd;
        }

        // Limitar a 5 comandos por batch (evita timeout no import do MikroTik)
        $commands = array_slice($commands, 0, 5);

        if (isset($_GET['format']) && $_GET['format'] === 'script') {
            header('Content-Type: text/plain');
            $lines = [];

            foreach ($commands as $cmd) {
                $client  = $cmd['client_identifier'];
                $cmdId   = $cmd['id'];
                $profile = $cmd['profile'] ?? 'default';
                $dur     = (int)($cmd['duration_minutes'] ?? 0);

                if ($cmd['command'] === 'temporary') {
                    $lines[] = ":do {";
                    $lines[] = "  /ip hotspot user remove [find name=\"$client\"]";
                    $lines[] = "  /ip hotspot user add name=\"$client\" password=\"$client\" profile=\"payment\" limit-uptime=\"5m\" comment=\"Acesso temporario - PIX\"";
                    $lines[] = "  /ip hotspot active remove [find user=\"$client\"]";
                    $lines[] = "  :delay 1s;";
                    $lines[] = "  /ip hotspot active login user=\"$client\" password=\"$client\" mac-address=\"$client\"";
                    $lines[] = "} on-error={};";
                } elseif ($cmd['command'] === 'grant') {
                    $lines[] = ":do {";
                    $lines[] = "  /ip hotspot user remove [find name=\"$client\"]";
                    $lines[] = "  /ip hotspot user add name=\"$client\" password=\"$client\" profile=\"$profile\" limit-uptime=\"${dur}m\" comment=\"PIX access - pago\"";
                    $lines[] = "  /ip hotspot active remove [find user=\"$client\"]";
                    $lines[] = "  :delay 1s;";
                    $lines[] = "  /ip hotspot active login user=\"$client\" password=\"$client\" mac-address=\"$client\"";
                    $lines[] = "} on-error={};";
                } elseif ($cmd['command'] === 'cleanup') {
                    $lines[] = ":do {";
                    $lines[] = "  /ip hotspot user remove [find name=\"$client\"]";
                    $lines[] = "  /ip hotspot active remove [find user=\"$client\"]";
                    $lines[] = "} on-error={};";
                } elseif ($cmd['command'] === 'temp_cleanup') {
                    $lines[] = ":do {";
                    $lines[] = "  /ip hotspot user remove [find name=\"$client\"]";
                    $lines[] = "  /ip hotspot active remove [find user=\"$client\"]";
                    $lines[] = "} on-error={};";
                }

                // Confirmar execucao (sem as-value, com error handling)
                $lines[] = ":do { /tool fetch url=\"https://hotspot-pix.accesnet.com.br/api/pending.php?confirm=$cmdId\" mode=https } on-error={};";
            }

            if (empty($lines)) {
                echo ":put \"ok\"\n";
            } else {
                echo implode("\n", $lines) . "\n";
            }
            exit;
        }

        header('Content-Type: application/json');
        jsonResponse(['commands' => $commands]);
    }

    if ($method === 'POST') {
        $body = input();
        $id = (int)($body['id'] ?? 0);
        $result = (string)($body['result'] ?? 'DONE');
        if (!$id) jsonResponse(['error' => 'ID obrigatorio'], 422);
        $stmt = $db->prepare("UPDATE pending_commands SET status=?, executed_at=NOW() WHERE id=?");
        $stmt->execute([$result === 'FAILED' ? 'FAILED' : 'DONE', $id]);
        jsonResponse(['ok' => true]);
    }

    jsonResponse(['error' => 'Metodo nao suportado'], 405);
} catch (Throwable $e) {
    error_log('pending.php: ' . $e->getMessage());
    jsonResponse(['error' => 'Erro interno'], 500);
}
