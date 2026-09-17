<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';

function validateCPF(string $cpf): bool {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) $d += (int)$cpf[$c] * (($t + 1) - $c);
        $d = ((10 * $d) % 11) % 10;
        if ((int)$cpf[$c] !== $d) return false;
    }
    return true;
}

function validateCNPJ(string $cnpj): bool {
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) return false;
    $calc = function(array $weights) use ($cnpj): int {
        $sum = 0;
        for ($i = 0; $i < count($weights); $i++) $sum += (int)$cnpj[$i] * $weights[$i];
        $r = $sum % 11;
        return $r < 2 ? 0 : 11 - $r;
    };
    if ((int)$cnpj[12] !== $calc([5,4,3,2,9,8,7,6,5,4,3,2])) return false;
    if ((int)$cnpj[13] !== $calc([6,5,4,3,2,9,8,7,6,5,4,3,2])) return false;
    return true;
}

function validateEmail(string $email): bool { return (bool)filter_var($email, FILTER_VALIDATE_EMAIL); }
function validatePhone(string $phone): bool { $d = preg_replace('/[^0-9]/', '', $phone); return strlen($d) >= 10 && strlen($d) <= 15; }
function clean(string $v): string { return trim(htmlspecialchars($v, ENT_QUOTES, 'UTF-8')); }

try {
    $db = db();
    $body = input();
    $action = $body['action'] ?? $_GET['action'] ?? '';

    if ($action === 'register') {
        $name      = clean((string)($body['name'] ?? ''));
        $docType   = strtoupper(clean((string)($body['document_type'] ?? 'CPF')));
        $docNumber = preg_replace('/[^0-9]/', '', (string)($body['document_number'] ?? ''));
        $email     = strtolower(clean((string)($body['email'] ?? '')));
        $phone     = preg_replace('/[^0-9+]/', '', (string)($body['phone'] ?? ''));
        $consent   = !empty($body['consent']);
        $marketing = !empty($body['marketing_consent']);
        $client    = preg_replace('/[^a-zA-Z0-9:_.-]/', '', (string)($body['client'] ?? ''));
        $ip        = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        $errors = [];
        if (mb_strlen($name) < 3) $errors[] = 'Informe seu nome completo.';
        if (!in_array($docType, ['CPF', 'CNPJ'], true)) $errors[] = 'Tipo de documento inválido.';
        if ($docType === 'CPF' && !validateCPF($docNumber)) $errors[] = 'CPF inválido.';
        if ($docType === 'CNPJ' && !validateCNPJ($docNumber)) $errors[] = 'CNPJ inválido.';
        if (!validateEmail($email)) $errors[] = 'E-mail inválido.';
        if (!validatePhone($phone)) $errors[] = 'Telefone/WhatsApp inválido.';
        if (!$consent) $errors[] = 'Você deve aceitar a Política de Privacidade para continuar.';
        if (!$client) $errors[] = 'Identificador do dispositivo não encontrado.';

        if ($errors) jsonResponse(['error' => implode(' ', $errors)], 422);

        $stmt = $db->prepare('SELECT id FROM customers WHERE document_type=? AND document_number=?');
        $stmt->execute([$docType, $docNumber]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $db->prepare('UPDATE customers SET name=?, email=?, phone=?, consent_accepted_at=NOW(), consent_ip=?, marketing_consent=? WHERE id=?')
               ->execute([$name, $email, $phone, $ip, $marketing ? 1 : 0, $existing['id']]);
            $customerId = (int)$existing['id'];
        } else {
            $stmt = $db->prepare('INSERT INTO customers (name, document_type, document_number, email, phone, consent_accepted_at, consent_ip, marketing_consent) VALUES (?,?,?,?,?,NOW(),?,?)');
            $stmt->execute([$name, $docType, $docNumber, $email, $phone, $ip, $marketing ? 1 : 0]);
            $customerId = (int)$db->lastInsertId();
        }

        $db->prepare('INSERT INTO customer_sessions (customer_id, mac_address) VALUES (?,?)')
           ->execute([$customerId, $client]);

        jsonResponse(['ok' => true, 'customer_id' => $customerId, 'message' => 'Cadastro realizado com sucesso!']);
    }

    if ($action === 'lookup') {
        $docType   = strtoupper(clean((string)($body['document_type'] ?? 'CPF')));
        $docNumber = preg_replace('/[^0-9]/', '', (string)($body['document_number'] ?? ''));
        $stmt = $db->prepare('SELECT id, name, email, phone, marketing_consent FROM customers WHERE document_type=? AND document_number=?');
        $stmt->execute([$docType, $docNumber]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$customer) jsonResponse(['error' => 'Cliente não encontrado. Faça seu cadastro.'], 404);
        jsonResponse(['ok' => true, 'customer' => $customer]);
    }

    jsonResponse(['error' => 'Ação inválida'], 400);
} catch (Throwable $e) {
    error_log('customer.php: ' . $e->getMessage());
    jsonResponse(['error' => 'Não foi possível processar o cadastro.'], 502);
}
