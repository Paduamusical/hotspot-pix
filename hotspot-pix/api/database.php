<?php
declare(strict_types=1); require_once __DIR__ . '/config.php';
function db(): PDO {
    static $pdo; if ($pdo) return $pdo;
    $pdo = new PDO((string)env('DB_DSN'), (string)env('DB_USER'), (string)env('DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    seedAdmin($pdo); return $pdo;
}
function seedAdmin(PDO $pdo): void {
    if ((int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0) {
        $stmt = $pdo->prepare('INSERT INTO admins(email,password_hash) VALUES(?,?)');
        $stmt->execute([env('ADMIN_EMAIL', 'admin@local'), password_hash((string)env('ADMIN_PASSWORD', 'troque-agora'), PASSWORD_DEFAULT)]);
    }
}
