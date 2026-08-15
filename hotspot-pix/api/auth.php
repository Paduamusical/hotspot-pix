<?php
declare(strict_types=1); require_once __DIR__ . '/database.php';
session_set_cookie_params(['httponly'=>true, 'samesite'=>'Strict', 'secure'=>(($_SERVER['HTTPS'] ?? '') === 'on')]); session_start();
function requireAdmin(): void { if (empty($_SESSION['admin_id'])) { http_response_code(401); exit('Não autorizado'); } }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function verifyCsrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) jsonResponse(['error'=>'CSRF inválido'], 403); }
