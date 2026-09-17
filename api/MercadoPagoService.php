<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

final class MercadoPagoService
{
    private string $baseUrl = 'https://api.mercadopago.com';

    private function token(): string
    {
        try {
            require_once __DIR__ . '/database.php';
            $db = db();
            $row = $db->query("SELECT mp_access_token FROM settings WHERE id=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if (!empty($row['mp_access_token'])) {
                return $row['mp_access_token'];
            }
        } catch (Throwable $e) {}
        return (string) env('MP_ACCESS_TOKEN');
    }

    public function createPixPayment(string $txid, float $amount, string $description, int $expiresInMinutes = 30): array
    {
        $expiration = new DateTime();
        $expiration->modify("+{$expiresInMinutes} minutes");
        $expirationStr = $expiration->format('Y-m-d') . 'T' . $expiration->format('H:i:s') . '-03:00';

        $payload = [
            'transaction_amount' => round($amount, 2),
            'description' => $description,
            'payment_method_id' => 'pix',
            'external_reference' => $txid,
            'notification_url' => 'https://hotspot-pix.accesnet.com.br/api/webhook.php',
            'payer' => ['email' => 'cliente@hotspot-pix.com.br'],
        ];

        $ch = curl_init($this->baseUrl . '/v1/payments');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->token(),
                'X-Idempotency-Key: ' . $txid,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ]);

        $out = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($out === false || $code < 200 || $code >= 300) {
            $detail = 'HTTP ' . $code . ' - ' . ($error ?: substr((string) $out, 0, 500));
            error_log('MercadoPago createPixPayment FAILED: txid=' . $txid . ' ' . $detail);
            throw new RuntimeException('Mercado Pago: ' . $detail);
        }

        $data = json_decode((string) $out, true);
        if (!$data || !isset($data['id'])) {
            error_log('MercadoPago createPixPayment INVALID: txid=' . $txid . ' response=' . substr((string) $out, 0, 500));
            throw new RuntimeException('Mercado Pago: resposta inválida - ' . substr((string) $out, 0, 200));
        }

        return $data;
    }

    public function getPayment(int $paymentId): array
    {
        $ch = curl_init($this->baseUrl . '/v1/payments/' . $paymentId);
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->token()],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ]);

        $out = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($out === false || $code < 200 || $code >= 300) {
            $detail = 'HTTP ' . $code . ' - ' . ($error ?: substr((string) $out, 0, 500));
            error_log('MercadoPago getPayment FAILED: paymentId=' . $paymentId . ' ' . $detail);
            throw new RuntimeException('Mercado Pago: ' . $detail);
        }

        return json_decode((string) $out, true) ?: [];
    }
}
