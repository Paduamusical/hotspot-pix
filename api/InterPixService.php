<?php
declare(strict_types=1); require_once __DIR__ . '/config.php';
final class InterPixService {
    private function request(string $method, string $path, ?array $body = null, bool $oauth = false): array {
        $url = rtrim((string)env('INTER_BASE_URL'), '/') . $path; $curl = curl_init($url);
        $headers = ['Accept: application/json'];
        if ($oauth) { $headers[] = 'Content-Type: application/x-www-form-urlencoded'; $body = ['client_id'=>env('INTER_CLIENT_ID'), 'client_secret'=>env('INTER_CLIENT_SECRET'), 'grant_type'=>'client_credentials', 'scope'=>'cob.write cob.read pix.read']; }
        else { $headers[] = 'Content-Type: application/json'; $headers[] = 'Authorization: Bearer ' . $this->token(); if (env('INTER_ACCOUNT')) $headers[] = 'x-conta-corrente: '.env('INTER_ACCOUNT'); }
        curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST=>$method, CURLOPT_HTTPHEADER=>$headers, CURLOPT_RETURNTRANSFER=>true, CURLOPT_SSLCERT=>env('INTER_CERT_PATH'), CURLOPT_SSLKEY=>env('INTER_KEY_PATH'), CURLOPT_POSTFIELDS=>$body ? ($oauth ? http_build_query($body) : json_encode($body)) : null]);
        $raw = curl_exec($curl); $code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE); $error = curl_error($curl); curl_close($curl);
        $data = json_decode((string)$raw, true) ?: []; if ($code < 200 || $code >= 300) throw new RuntimeException('Inter API: ' . ($data['detail'] ?? $error ?: 'HTTP '.$code)); return $data;
    }
    private function token(): string { $d = $this->request('POST', '/oauth/v2/token', null, true); return $d['access_token'] ?? throw new RuntimeException('Token Inter ausente'); }
    public function createCharge(string $txid, int $cents, int $expires = 900): array { return $this->request('PUT', '/pix/v2/cob/'.$txid, ['calendario'=>['expiracao'=>$expires], 'valor'=>['original'=>number_format($cents/100, 2, '.', '')], 'chave'=>env('INTER_PIX_KEY'), 'solicitacaoPagador'=>'Acesso ao hotspot']); }
    public function getCharge(string $txid): array { return $this->request('GET', '/pix/v2/cob/'.$txid); }
    public function getQrCode(int $locationId): array { return $this->request('GET', '/pix/v2/loc/'.$locationId.'/qrcode'); }
}
