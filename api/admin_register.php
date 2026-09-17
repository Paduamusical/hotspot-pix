<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/database.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método não permitido'], 405);
}

$body = input();
$name = trim($body['name'] ?? '');
$address = trim($body['address'] ?? '');
$cpf = preg_replace('/[^0-9]/', '', (string)($body['cpf'] ?? ''));
$email = strtolower(trim($body['email'] ?? ''));
$password = (string)($body['password'] ?? '');

if (!$name || !$address || !$cpf || !$email || !$password) {
    jsonResponse(['error' => 'Todos os campos são obrigatórios'], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['error' => 'E-mail inválido'], 422);
}
if (strlen($password) < 6) {
    jsonResponse(['error' => 'A senha deve ter no mínimo 6 caracteres'], 422);
}

$db = db();

// Verificar se email já existe
$check = $db->prepare('SELECT id FROM admins WHERE email = ?');
$check->execute([$email]);
if ($check->fetch()) {
    jsonResponse(['error' => 'Este e-mail já está cadastrado'], 409);
}

// Verificar se CPF já existe
$checkCpf = $db->prepare('SELECT id FROM admins WHERE cpf = ?');
$checkCpf->execute([$cpf]);
if ($checkCpf->fetch()) {
    jsonResponse(['error' => 'Este CPF já está cadastrado'], 409);
}

// Inserir novo admin
$hash = password_hash($password, PASSWORD_DEFAULT);
$insert = $db->prepare('INSERT INTO admins (name, address, cpf, email, password_hash) VALUES (?, ?, ?, ?, ?)');
$insert->execute([$name, $address, $cpf, $email, $hash]);

jsonResponse(['success' => true, 'message' => 'Administrador cadastrado com sucesso!']);
