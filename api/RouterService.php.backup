<?php
declare(strict_types=1); require_once __DIR__ . '/config.php';
final class RouterService {
    public function grantHotspotAccess(string $client, int $minutes, string $profile): void {
        $url = rtrim((string)env('MIKROTIK_REST_URL'), '/') . '/ip/hotspot/user';
        $payload = ['name'=>$client, 'password'=>bin2hex(random_bytes(8)), 'profile'=>$profile, 'limit-uptime'=>$minutes.'m', 'comment'=>'PIX access'];
        $ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode($payload), CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_USERPWD=>env('MIKROTIK_USER').':'.env('MIKROTIK_PASS'), CURLOPT_RETURNTRANSFER=>true, CURLOPT_SSL_VERIFYPEER=>true]);
        $out = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $error = curl_error($ch); curl_close($ch);
        if ($code < 200 || $code >= 300) throw new RuntimeException('RouterOS: '.($error ?: $out));
    }
}
