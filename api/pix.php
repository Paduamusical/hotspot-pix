<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/MercadoPagoService.php';

function txid(): string
{
    return bin2hex(random_bytes(16));
}

function loginInfo(string $client): array
{
    return [
        'login_url' => env('HOTSPOT_LOGIN_URL', 'http://192.168.50.1/login'),
        'username'  => $client,
        'password'  => $client,
        'dst'       => 'https://hotspot-pix.accesnet.com.br?client=' . $client,
    ];
}

/*
 * Coloca a liberação do pacote na fila do MikroTik.
 *
 * granted_at continua sendo utilizado pelo sistema atual como
 * indicação de que a ordem de liberação já foi criada.
 */
function grant(PDO $db, array $charge): void
{
    if (!empty($charge['granted_at'])) {
        return;
    }

    $plan = $db->prepare(
        'SELECT duration_minutes, mikrotik_profile
         FROM plans
         WHERE id=?'
    );

    $plan->execute([$charge['plan_id']]);

    $p = $plan->fetch(PDO::FETCH_ASSOC);

    if (!$p) {
        throw new RuntimeException('Plano não encontrado');
    }

    $db->prepare(
        "INSERT INTO pending_commands
         (command, client_identifier, profile, duration_minutes)
         VALUES ('grant', ?, ?, ?)"
    )->execute([
        $charge['client_identifier'],
        $p['mikrotik_profile'],
        (int)$p['duration_minutes']
    ]);

    $db->prepare(
        'UPDATE pix_charges
         SET granted_at=NOW()
         WHERE id=?'
    )->execute([
        $charge['id']
    ]);
}

try {
    $db = db();

    $body = input();

    $action = $body['action'] ?? $_GET['action'] ?? '';

    /*
     * ==========================================================
     * VOUCHER
     * ==========================================================
     */
    if ($action === 'voucher') {

        $code = trim(
            strtoupper(
                (string)($body['code'] ?? $_GET['code'] ?? '')
            )
        );

        $client = preg_replace(
            '/[^a-zA-Z0-9:_.-]/',
            '',
            (string)($body['client'] ?? $_GET['client'] ?? '')
        );

        if (!$code || !$client) {
            jsonResponse([
                'error' => 'Informe o voucher e o identificador do dispositivo.'
            ], 422);
        }

        $stmt = $db->prepare(
            "SELECT
                v.*,
                p.duration_minutes,
                p.mikrotik_profile
             FROM vouchers v
             JOIN plans p ON p.id=v.plan_id
             WHERE v.code=?
               AND v.status='AVAILABLE'"
        );

        $stmt->execute([$code]);

        $v = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$v) {
            jsonResponse([
                'error' => 'Voucher inválido, expirado ou já utilizado.'
            ], 404);
        }

        if (
            $v['expires_at'] &&
            strtotime($v['expires_at']) < time()
        ) {
            $db->prepare(
                "UPDATE vouchers
                 SET status='EXPIRED'
                 WHERE id=?"
            )->execute([$v['id']]);

            jsonResponse([
                'error' => 'Voucher expirado.'
            ], 410);
        }

        $db->prepare(
            "INSERT INTO pending_commands
             (command, client_identifier, profile, duration_minutes)
             VALUES ('grant', ?, ?, ?)"
        )->execute([
            $client,
            $v['mikrotik_profile'],
            (int)$v['duration_minutes']
        ]);

        $cmdId = (int)$db->lastInsertId();

        $db->prepare(
            "UPDATE vouchers
             SET status='USED',
                 client_identifier=?,
                 used_at=NOW()
             WHERE id=?"
        )->execute([
            $client,
            $v['id']
        ]);

        jsonResponse(
            array_merge(
                [
                    'status' => 'PAID',
                    'granted' => true,
                    'cmd_id' => $cmdId,
                    'message' =>
                        'Voucher validado! Acesso liberado por ' .
                        $v['duration_minutes'] .
                        ' minutos.'
                ],
                loginInfo($client)
            )
        );
    }

    /*
     * ==========================================================
     * CONNECT
     *
     * Mantido como está.
     * O cliente utiliza dados móveis para realizar o PIX.
     * ==========================================================
     */
    if ($action === 'connect') {
        $client = preg_replace('/[^a-zA-Z0-9:_.-]/', '', (string)($body['client'] ?? $_GET['client'] ?? ''));
        if (!$client) jsonResponse(['error' => 'Informe o identificador do dispositivo.'], 422);
        $db->prepare("INSERT INTO pending_commands (command, client_identifier, profile, duration_minutes) VALUES ('temporary', ?, 'payment', 5)")
           ->execute([$client]);
        jsonResponse(['status' => 'CONNECTED', 'login_url' => env('HOTSPOT_LOGIN_URL', 'http://192.168.50.1/login'), 'username' => $client, 'password' => $client, 'message' => 'Acesso liberado por 5 minutos para pagamento.']);
    }

    /*
     * ==========================================================
     * STATUS TEMPORÁRIO
     * ==========================================================
     */
    if ($action === 'temp_status') {

        $cmdId = (int)(
            $body['cmd_id'] ??
            $_GET['cmd_id'] ??
            0
        );

        if (!$cmdId) {
            jsonResponse([
                'error' => 'cmd_id obrigatório'
            ], 422);
        }

        $stmt = $db->prepare(
            "SELECT status
             FROM pending_commands
             WHERE id=?"
        );

        $stmt->execute([$cmdId]);

        $cmdRow = $stmt->fetch(PDO::FETCH_ASSOC);

        jsonResponse([
            'done' =>
                ($cmdRow && $cmdRow['status'] === 'DONE')
        ]);
    }

    /*
     * ==========================================================
     * CRIAR COBRANÇA PIX
     * ==========================================================
     */
    if ($action === 'create') {

        $planId = filter_var(
            $body['plan_id'] ??
            $_GET['plan_id'] ??
            null,
            FILTER_VALIDATE_INT
        );

        $client = preg_replace(
            '/[^a-zA-Z0-9:_.-]/',
            '',
            (string)(
                $body['client'] ??
                $_GET['client'] ??
                ''
            )
        );

        if (!$planId || !$client) {
            jsonResponse([
                'error' =>
                    'Informe plano e identificador do dispositivo.'
            ], 422);
        }

        $p = $db->prepare(
            'SELECT *
             FROM plans
             WHERE id=?
               AND active=1'
        );

        $p->execute([$planId]);

        $plan = $p->fetch(PDO::FETCH_ASSOC);

        if (!$plan) {
            jsonResponse([
                'error' => 'Plano inválido.'
            ], 404);
        }

        /*
         * ======================================================
         * MODO SIMULAÇÃO
         *
         * Mantido para preservar a estrutura do sistema.
         * Não é utilizado quando PAYMENT_MODE=mercadopago.
         * ======================================================
         */
        if (
            env('PAYMENT_MODE', 'simulation') ===
            'simulation'
        ) {

            $id =
                'SIM-' .
                strtoupper(
                    substr(txid(), 0, 16)
                );

            $db->prepare(
                'INSERT INTO pix_charges
                 (
                    txid,
                    plan_id,
                    client_identifier,
                    amount_cents,
                    inter_payload
                 )
                 VALUES (?,?,?,?,?)'
            )->execute([
                $id,
                $planId,
                $client,
                $plan['price_cents'],
                json_encode([
                    'simulation' => true
                ])
            ]);

            $fakePayload =
                '00020126580014BR.GOV.BCB.PIX0136' .
                $id .
                '5204000053039865802BR5913HOTSPOT-PIX6009SAOPAULO62070503***6304' .
                substr(md5($id), 0, 4);

            jsonResponse(
                array_merge(
                    [
                        'txid' => $id,
                        'copy_paste' => $fakePayload,
                        'image' => null,
                        'expires_in' => 900,
                        'simulation' => true,
                        'message' =>
                            'Pague o PIX para liberar o acesso.'
                    ],
                    loginInfo($client)
                )
            );
        }

        /*
         * ======================================================
         * MERCADO PAGO
         *
         * É o provedor ativo desta instalação.
         * ======================================================
         */
        if (
            env('PAYMENT_MODE') ===
            'mercadopago'
        ) {

            $id = txid();

            $mp = new MercadoPagoService();

            $payment = $mp->createPixPayment(
                $id,
                ((int)$plan['price_cents']) / 100,
                'Hotspot PIX - ' . $plan['name'],
                30
            );

            /*
             * Verificação mínima da resposta do Mercado Pago.
             */
            if (
                empty($payment['id']) ||
                empty(
                    $payment['point_of_interaction']
                    ['transaction_data']
                    ['qr_code']
                )
            ) {
                throw new RuntimeException(
                    'Mercado Pago não retornou uma cobrança PIX válida.'
                );
            }

            /*
             * Guarda a resposta completa do Mercado Pago.
             *
             * O txid local é o external_reference enviado
             * ao Mercado Pago.
             */
            $db->prepare(
                'INSERT INTO pix_charges
                 (
                    txid,
                    plan_id,
                    client_identifier,
                    amount_cents,
                    inter_payload
                 )
                 VALUES (?,?,?,?,?)'
            )->execute([
                $id,
                $planId,
                $client,
                $plan['price_cents'],
                json_encode(
                    $payment,
                    JSON_UNESCAPED_UNICODE
                )
            ]);

            $qrCode =
                $payment['point_of_interaction']
                ['transaction_data']
                ['qr_code'];

            $qrImage =
                $payment['point_of_interaction']
                ['transaction_data']
                ['qr_code_base64'] ??
                null;

            jsonResponse(
                array_merge(
                    [
                        'txid' => $id,
                        'copy_paste' => $qrCode,
                        'image' => $qrImage,
                        'expires_in' => 1800,
                        'simulation' => false,
                        'message' =>
                            'Abra o app do seu banco e pague o PIX para liberar o acesso.'
                    ],
                    loginInfo($client)
                )
            );
        }

        jsonResponse([
            'error' =>
                'PAYMENT_MODE inválido no .env.'
        ], 500);
    }

    /*
     * ==========================================================
     * STATUS DA COBRANÇA
     *
     * ESTA É A PARTE PRINCIPAL DA CORREÇÃO.
     * ==========================================================
     */
    if ($action === 'status') {

        $id = preg_replace(
            '/[^a-zA-Z0-9-]/',
            '',
            (string)(
                $body['txid'] ??
                $_GET['txid'] ??
                ''
            )
        );

        $client = preg_replace(
            '/[^a-zA-Z0-9:_.-]/',
            '',
            (string)(
                $body['client'] ??
                $_GET['client'] ??
                ''
            )
        );

        /*
         * Busca EXATAMENTE a cobrança solicitada.
         *
         * Não procura outra cobrança do cliente.
         */
        $stmt = $db->prepare(
            'SELECT *
             FROM pix_charges
             WHERE txid=?'
        );

        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            jsonResponse([
                'error' =>
                    'Cobrança não encontrada'
            ], 404);
        }

        /*
         * ======================================================
         * SIMULAÇÃO
         * ======================================================
         */
        if (
            env('PAYMENT_MODE', 'simulation') ===
            'simulation' &&
            $row['status'] === 'PENDING'
        ) {

            $elapsed =
                time() -
                strtotime($row['created_at']);

            if ($elapsed >= 10) {

                $db->prepare(
                    "UPDATE pix_charges
                     SET status='PAID',
                         paid_at=NOW(),
                         inter_payload=?
                     WHERE id=?"
                )->execute([
                    json_encode([
                        'simulation' => 'auto-paid'
                    ]),
                    $row['id']
                ]);

                $row['status'] = 'PAID';
            }
        }

        /*
         * ======================================================
         * MERCADO PAGO
         * ======================================================
         */
        if (
            env('PAYMENT_MODE') ===
            'mercadopago' &&
            $row['status'] === 'PENDING'
        ) {

            $payload =
                json_decode(
                    (string)$row['inter_payload'],
                    true
                ) ?: [];

            $paymentId =
                $payload['id'] ??
                null;

            if ($paymentId) {

                try {

                    $remote =
                        (new MercadoPagoService)
                        ->getPayment(
                            (int)$paymentId
                        );

                    /*
                     * ------------------------------------------------
                     * REGRA 1:
                     * O Mercado Pago precisa dizer APPROVED.
                     * ------------------------------------------------
                     */
                    $remoteStatus =
                        strtolower(
                            (string)(
                                $remote['status'] ??
                                ''
                            )
                        );

                    /*
                     * ------------------------------------------------
                     * REGRA 2:
                     * external_reference precisa ser EXATAMENTE
                     * o txid desta cobrança.
                     * ------------------------------------------------
                     */
                    $remoteReference =
                        (string)(
                            $remote['external_reference'] ??
                            ''
                        );

                    /*
                     * ------------------------------------------------
                     * REGRA 3:
                     * O valor pago precisa ser exatamente o valor
                     * da cobrança.
                     * ------------------------------------------------
                     */
                    $remoteAmount =
                        isset(
                            $remote['transaction_amount']
                        )
                            ? (float)$remote['transaction_amount']
                            : null;

                    $localAmount =
                        ((int)$row['amount_cents']) /
                        100;

                    $amountOk =
                        $remoteAmount !== null &&
                        abs(
                            $remoteAmount -
                            $localAmount
                        ) < 0.001;

                    /*
                     * ------------------------------------------------
                     * REGRA 4:
                     * Deve ser PIX.
                     * ------------------------------------------------
                     */
                    $paymentType =
                        strtolower(
                            (string)(
                                $remote['payment_type_id'] ??
                                ''
                            )
                        );

                    $pixOk =
                        ($paymentType === 'pix');

                    /*
                     * Só agora o pagamento pode ser considerado PAID.
                     */
                    if (
                        $remoteStatus === 'approved' &&
                        hash_equals(
                            (string)$row['txid'],
                            $remoteReference
                        ) &&
                        $amountOk &&
                        $pixOk
                    ) {

                        $db->prepare(
                            "UPDATE pix_charges
                             SET status='PAID',
                                 paid_at=NOW(),
                                 inter_payload=?
                             WHERE id=?
                               AND status='PENDING'"
                        )->execute([
                            json_encode(
                                $remote,
                                JSON_UNESCAPED_UNICODE
                            ),
                            $row['id']
                        ]);

                        /*
                         * Recarrega a cobrança depois da atualização.
                         */
                        $reload = $db->prepare(
                            'SELECT *
                             FROM pix_charges
                             WHERE id=?'
                        );

                        $reload->execute([
                            $row['id']
                        ]);

                        $row =
                            $reload->fetch(
                                PDO::FETCH_ASSOC
                            ) ?: $row;

                    } else {

                        /*
                         * Não aprovar silenciosamente.
                         * Registrar o motivo no log.
                         */
                        error_log(
                            'MP pagamento NÃO aprovado para txid=' .
                            $row['txid'] .
                            ' status=' .
                            $remoteStatus .
                            ' external_reference=' .
                            $remoteReference .
                            ' valor_remoto=' .
                            (string)$remoteAmount .
                            ' valor_local=' .
                            (string)$localAmount .
                            ' payment_type=' .
                            $paymentType
                        );
                    }

                } catch (Throwable $e) {

                    error_log(
                        'Erro MP status: ' .
                        $e->getMessage()
                    );
                }
            }
        }

        /*
         * ======================================================
         * LIBERAÇÃO
         *
         * Somente a cobrança EXATA que ficou PAID.
         * ======================================================
         */
        $mikrotik_done = false;

        if ($row['status'] === 'PAID') {

            grant(
                $db,
                $row
            );

            /*
             * Procurar o comando relacionado ao cliente.
             *
             * Mantemos a estrutura existente para não alterar
             * o mecanismo atual do MikroTik nesta correção.
             */
            $cmdStmt = $db->prepare(
                "SELECT status
                 FROM pending_commands
                 WHERE client_identifier=?
                   AND command='grant'
                 ORDER BY id DESC
                 LIMIT 1"
            );

            $cmdStmt->execute([
                $row['client_identifier']
            ]);

            $cmdRow =
                $cmdStmt->fetch(
                    PDO::FETCH_ASSOC
                );

            $mikrotik_done =
                (
                    $cmdRow &&
                    $cmdRow['status'] === 'DONE'
                );
        }

        /*
         * ======================================================
         * IMPORTANTE:
         *
         * NÃO EXISTE MAIS:
         *
         * SELECT ... WHERE client_identifier=? AND status='PAID'
         *
         * Portanto uma cobrança antiga não pode aprovar
         * uma cobrança nova.
         * ======================================================
         */

        $resp = [
            'status' =>
                $row['status'],

            'granted' =>
                ($row['status'] === 'PAID'),

            'mikrotik_done' =>
                $mikrotik_done,

            'simulation' =>
                env(
                    'PAYMENT_MODE',
                    'simulation'
                ) === 'simulation'
        ];

        if ($row['status'] === 'PAID') {

            $resp = array_merge(
                $resp,
                loginInfo(
                    $row['client_identifier']
                )
            );
        }

        jsonResponse($resp);
    }

    /*
     * ==========================================================
     * SIMULAR PAGAMENTO
     * ==========================================================
     */
    if ($action === 'simulate-payment') {

        if (
            env('PAYMENT_MODE', 'simulation') !==
            'simulation'
        ) {
            jsonResponse([
                'error' =>
                    'Modo simulação não está ativo'
            ], 403);
        }

        $id = preg_replace(
            '/[^a-zA-Z0-9-]/',
            '',
            (string)(
                $body['txid'] ??
                $_GET['txid'] ??
                ''
            )
        );

        $stmt = $db->prepare(
            'SELECT *
             FROM pix_charges
             WHERE txid=?
               AND status="PENDING"'
        );

        $stmt->execute([$id]);

        $row =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$row) {
            jsonResponse([
                'error' =>
                    'Cobrança não encontrada ou já paga'
            ], 404);
        }

        $db->prepare(
            "UPDATE pix_charges
             SET status='PAID',
                 paid_at=NOW(),
                 inter_payload=?
             WHERE id=?"
        )->execute([
            json_encode([
                'simulation' => 'manual'
            ]),
            $row['id']
        ]);

        grant(
            $db,
            $row
        );

        jsonResponse([
            'status' => 'PAID',
            'granted' => true,
            'message' =>
                'Pagamento simulado e acesso liberado!'
        ]);
    }

    jsonResponse([
        'error' => 'Ação inválida'
    ], 400);

} catch (Throwable $e) {

    error_log(
        'pix.php: ' .
        $e->getMessage()
    );

    jsonResponse([
        'error' =>
            'Não foi possível processar a solicitação.'
    ], 502);
}
