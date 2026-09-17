<?php
declare(strict_types=1);

class WebPush
{
    private string $vapidPublicKey;
    private string $privateKeyPem;
    private string $subject;

    public function __construct()
    {
        $this->vapidPublicKey = (string)env('VAPID_PUBLIC_KEY', '');
        $this->privateKeyPem = file_get_contents(__DIR__ . '/vapid_private.pem');
        $this->subject = 'mailto:paduamusical@gmail.com';
    }

    private function b64UrlDecode(string $data): string
    {
        $pad = strlen($data) % 4;
        if ($pad) $data .= str_repeat('=', 4 - $pad);
        return base64_decode(strtr($data, '-_', '+/'));
    }

    private function b64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function hkdfExpand(string $prk, string $info, int $length): string
    {
        $hashLen = 32;
        $n = (int)ceil($length / $hashLen);
        $okm = '';
        $t = '';
        for ($i = 1; $i <= $n; $i++) {
            $t = hash_hmac('sha256', $t . $info . chr($i), $prk, true);
            $okm .= $t;
        }
        return substr($okm, 0, $length);
    }

    private function uncompressedToPem(string $uncompressed): string
    {
        $der = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"
             . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00"
             . $uncompressed;
        return "-----BEGIN PUBLIC KEY-----\n"
             . chunk_split(base64_encode($der), 64, "\n")
             . "-----END PUBLIC KEY-----\n";
    }

    private function derToRawSig(string $der): string
    {
        $off = 0;
        if (ord($der[$off++]) !== 0x30) return str_repeat("\0", 64);
        $len = ord($der[$off++]);
        if ($len & 0x80) {
            $bytes = $len & 0x7f;
            $len = 0;
            for ($i = 0; $i < $bytes; $i++) $len = ($len << 8) | ord($der[$off++]);
        }
        if (ord($der[$off++]) !== 0x02) return str_repeat("\0", 64);
        $rLen = ord($der[$off++]);
        $r = ltrim(substr($der, $off, $rLen), "\x00");
        $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
        $off += $rLen;
        if (ord($der[$off++]) !== 0x02) return str_repeat("\0", 64);
        $sLen = ord($der[$off++]);
        $s = ltrim(substr($der, $off, $sLen), "\x00");
        $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);
        return $r . $s;
    }

    private function createVapidJwt(string $origin): string
    {
        $header = $this->b64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $payload = $this->b64UrlEncode(json_encode([
            'aud' => $origin,
            'exp' => time() + 43200,
            'sub' => $this->subject,
        ]));
        $data = $header . '.' . $payload;
        openssl_sign($data, $sig, $this->privateKeyPem, OPENSSL_ALGO_SHA256);
        $rawSig = $this->derToRawSig($sig);
        return $data . '.' . $this->b64UrlEncode($rawSig);
    }

    public function sendNotification(array $sub, string $payload): array
    {
        $endpoint = $sub['endpoint'];
        $clientPub = $this->b64UrlDecode($sub['p256dh']);
        $authSecret = $this->b64UrlDecode($sub['auth']);

        // ECDH
        $serverKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        $sDetails = openssl_pkey_get_details($serverKey);
        $sPubDer = base64_decode(implode('', array_slice(explode("\n", $sDetails['key']), 1, -1)));
        $serverPub = substr($sPubDer, -65);

        $clientKey = openssl_pkey_get_public($this->uncompressedToPem($clientPub));
        $shared = openssl_pkey_derive($serverKey, $clientKey, 32);
        if ($shared === false) return ['success' => false, 'error' => 'ECDH failed: ' . openssl_error_string()];

        // Derive CEK + nonce (RFC 8291)
        $prk = hash_hmac('sha256', $shared, $authSecret, true);
        $ctx = "P-256\x00" . pack('n', 65) . $clientPub . pack('n', 65) . $serverPub;
        $cek = $this->hkdfExpand($prk, "Content-Encoding: aes128gcm\0" . $ctx, 16);
        $nonce = $this->hkdfExpand($prk, "Content-Encoding: nonce\0" . $ctx, 12);

        // Encrypt (RFC 8188 aes128gcm)
        $plaintext = $payload . "\x02";
        $tag = '';
        $ct = openssl_encrypt($plaintext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($ct === false) return ['success' => false, 'error' => 'AES-GCM failed: ' . openssl_error_string()];

        $salt = random_bytes(16);
        $rs = 4096;
        $body = $salt . pack('N', $rs) . chr(65) . $serverPub . $ct . $tag;

        // VAPID JWT
        $parsed = parse_url($endpoint);
        $origin = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
        if (!empty($parsed['port'])) $origin .= ':' . $parsed['port'];
        $jwt = $this->createVapidJwt($origin);

        // Send
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'TTL: 2419200',
                'Authorization: vapid t=' . $jwt . ', k=' . $this->vapidPublicKey,
            ],
            CURLOPT_TIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($code >= 200 && $code < 300) return ['success' => true];
        return ['success' => false, 'error' => "HTTP $code: $resp", 'curl_err' => $err];
    }

    public function sendToClient(string $clientIdentifier, string $title, string $body): void
    {
        $db = db();
        $stmt = $db->prepare("SELECT * FROM push_subscriptions WHERE client_identifier=? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$clientIdentifier]);
        $sub = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sub) return;

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => '/?client=' . urlencode($clientIdentifier),
        ], JSON_UNESCAPED_UNICODE);

        $result = $this->sendNotification([
            'endpoint' => $sub['endpoint'],
            'p256dh' => $sub['p256dh'],
            'auth' => $sub['auth'],
        ], $payload);

        if ($result['success']) {
            error_log("WebPush: Notificacao enviada para $clientIdentifier");
        } else {
            error_log("WebPush: Falha para $clientIdentifier: " . ($result['error'] ?? 'unknown'));
            // Se a subscription expirou (410), remover
            if (strpos($result['error'] ?? '', '410') !== false) {
                $db->prepare("DELETE FROM push_subscriptions WHERE client_identifier=?")->execute([$clientIdentifier]);
            }
        }
    }
}
