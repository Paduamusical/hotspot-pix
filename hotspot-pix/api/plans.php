<?php
declare(strict_types=1); require_once __DIR__ . '/database.php';
$stmt = db()->query('SELECT id,name,price_cents,duration_minutes FROM plans WHERE active=1 ORDER BY price_cents');
jsonResponse(['plans'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
