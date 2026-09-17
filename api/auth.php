<?php
declare(strict_types=1); require_once __DIR__ . '/database.php';

// Sessão expira ao fechar o navegador (cookie não persistente)
ini_set('session.cookie_lifetime', '0');
ini_set('session.gc_maxlifetime', '1800'); // 30 minutos de inatividade
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'path' => '/',
    'lifetime' => 0
]);
session_start();

// Renovar timestamp de atividade
$_SESSION['last_activity'] = time();

// Expirar sessão por inatividade (30 minutos)
if (!empty($_SESSION['admin_id']) && isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > 1800) {
        session_unset();
        session_destroy();
        session_start();
    }
}

function requireAdmin(): void {
    if (empty($_SESSION['admin_id'])) {
        http_response_code(401);
        header('Location: login.php');
        exit('Não autorizado');
    }
}

function requireSuperAdmin(): void {
    requireAdmin();
    if (($_SESSION['admin_role'] ?? '') !== 'super') {
        http_response_code(403);
        header('Location: dashboard.php');
        exit('Acesso restrito ao super administrador');
    }
}

function isSuperAdmin(): bool {
    return ($_SESSION['admin_role'] ?? '') === 'super';
}

function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function verifyCsrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) jsonResponse(['error'=>'CSRF inválido'], 403); }
