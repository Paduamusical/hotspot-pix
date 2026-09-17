<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
file_put_contents(__DIR__.'/wg-diag.log', date('c').' '.file_get_contents('php://input')."\n", FILE_APPEND);
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
