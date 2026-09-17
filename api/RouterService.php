<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

final class RouterService
{
    private string $base;
    private string $user;
    private string $pass;

    public function __construct()
    {
        $this->base = rtrim((string) env('MIKROTIK_REST_URL'), '/');
        $this->user = (string) env('MIKROTIK_USER');
        $this->pass = (string) env('MIKROTIK_PASS');
    }

    public function grantTemporaryAccess(string $client): void
    {
        $users = $this->request('GET', '/ip/hotspot/user?name=' . urlencode($client));
        foreach ($users as $u) {
            if (($u['name'] ?? '') === $client) {
                $comment = $u['comment'] ?? '';
                $profile = $u['profile'] ?? '';
                if ($comment === 'PIX access - pago' && $profile !== 'payment' && $profile !== 'default-trial') {
                    return;
                }
            }
        }

        $this->deleteUser($client);

        $this->createUser([
            'name'          => $client,
            'password'      => $client,
            'profile'       => 'payment',
            'limit-uptime'  => '15m',
            'comment'       => 'Acesso temporario - pagamento PIX'
        ]);

        $this->disconnectActive($client);
    }

    public function grantHotspotAccess(string $client, int $minutes, string $profile): void
    {
        $profile = trim($profile);
        if ($profile === '') {
            $profile = 'default';
        }

        $this->deleteUser($client);

        $this->createUser([
            'name'          => $client,
            'password'      => $client,
            'profile'       => $profile,
            'limit-uptime'  => $minutes . 'm',
            'comment'       => 'PIX access - pago'
        ]);

        $this->addBindingBypassed($client, $minutes);

        $this->disconnectActive($client);
    }

    private function addBindingBypassed(string $client, int $minutes): void
    {
        try {
            $expiration = time() + ($minutes * 60);

            $bindings = $this->request('GET', '/ip/hotspot/ip-binding?mac-address=' . urlencode($client));
            foreach ($bindings as $b) {
                if (($b['mac-address'] ?? '') === $client && isset($b['.id'])) {
                    $this->request('DELETE', '/ip/hotspot/ip-binding/' . $b['.id']);
                }
            }

            $this->request('PUT', '/ip/hotspot/ip-binding', [
                'mac-address' => $client,
                'type'        => 'bypassed',
                'comment'     => 'PIX-EXP-' . $expiration
            ]);
            error_log('addBindingBypassed: ip-binding bypassed adicionado para ' . $client . ' expira em ' . $minutes . 'min (timestamp ' . $expiration . ')');
        } catch (Throwable $e) {
            error_log('addBindingBypassed: Erro para ' . $client . ' - ' . $e->getMessage());
        }
    }

    private function deleteUser(string $client): void
    {
        $users = $this->request('GET', '/ip/hotspot/user?name=' . urlencode($client));
        foreach ($users as $u) {
            if (($u['name'] ?? '') === $client && isset($u['.id'])) {
                $this->request('DELETE', '/ip/hotspot/user/' . $u['.id']);
            }
        }
    }

    private function disconnectActive(string $client): void
    {
        $active = $this->request('GET', '/ip/hotspot/active?mac-address=' . urlencode($client));
        foreach ($active as $s) {
            if (($s['mac-address'] ?? '') === $client && isset($s['.id'])) {
                $this->request('DELETE', '/ip/hotspot/active/' . $s['.id']);
            }
        }

        $activeByUser = $this->request('GET', '/ip/hotspot/active?user=' . urlencode($client));
        foreach ($activeByUser as $s) {
            if (($s['user'] ?? '') === $client && isset($s['.id'])) {
                $this->request('DELETE', '/ip/hotspot/active/' . $s['.id']);
            }
        }
    }

    private function createUser(array $payload): void
    {
        $this->request('PUT', '/ip/hotspot/user', $payload);
    }

    private function request(string $method, string $endpoint, ?array $payload = null): array
    {
        $ch = curl_init($this->base . $endpoint);

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_USERPWD        => $this->user . ':' . $this->pass,
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
        $code     = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error    = curl_error($ch);

        curl_close($ch);

        if ($response === false || $code < 200 || $code >= 300) {
            throw new RuntimeException('RouterOS REST: ' . ($error ?: $response));
        }

        $json = json_decode((string)$response, true);
        return is_array($json) ? $json : [];
    }
}
