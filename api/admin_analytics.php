<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
requireAdmin();
require_once __DIR__ . '/database.php';
header('Content-Type: application/json; charset=utf-8');

$db = db();

// Data de criação do dashboard — tudo antes disso é ignorado
$inicio = date('Y-m-d') . ' 00:00:00';

// KPIs gerais (apenas a partir do início)
$kpis = $db->prepare("
    SELECT
        COUNT(*) as total_cobrancas,
        SUM(CASE WHEN status='PAID' THEN 1 ELSE 0 END) as pagos,
        SUM(CASE WHEN status='PENDING' THEN 1 ELSE 0 END) as pendentes,
        SUM(CASE WHEN status='EXPIRED' THEN 1 ELSE 0 END) as expirados,
        SUM(CASE WHEN status='PAID' THEN amount_cents ELSE 0 END)/100 as faturamento,
        SUM(CASE WHEN status='PAID' AND DATE(paid_at)=CURDATE() THEN 1 ELSE 0 END) as vendas_hoje,
        SUM(CASE WHEN status='PAID' AND DATE(paid_at)=CURDATE() THEN amount_cents ELSE 0 END)/100 as faturamento_hoje,
        SUM(CASE WHEN status='PAID' AND YEARWEEK(paid_at,1)=YEARWEEK(CURDATE(),1) THEN 1 ELSE 0 END) as vendas_semana,
        SUM(CASE WHEN status='PAID' AND YEARWEEK(paid_at,1)=YEARWEEK(CURDATE(),1) THEN amount_cents ELSE 0 END)/100 as faturamento_semana,
        SUM(CASE WHEN status='PAID' AND MONTH(paid_at)=MONTH(CURDATE()) AND YEAR(paid_at)=YEAR(CURDATE()) THEN 1 ELSE 0 END) as vendas_mes,
        SUM(CASE WHEN status='PAID' AND MONTH(paid_at)=MONTH(CURDATE()) AND YEAR(paid_at)=YEAR(CURDATE()) THEN amount_cents ELSE 0 END)/100 as faturamento_mes
    FROM pix_charges
    WHERE created_at >= ?
");
$kpis->execute([$inicio]);
$kpis = $kpis->fetch(PDO::FETCH_ASSOC);

// Clientes (apenas a partir do início)
$clientes = $db->prepare("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN DATE(created_at)=CURDATE() THEN 1 ELSE 0 END) as novos_hoje,
        SUM(CASE WHEN marketing_consent=1 THEN 1 ELSE 0 END) as aceitaram_marketing
    FROM customers
    WHERE created_at >= ?
");
$clientes->execute([$inicio]);
$clientes = $clientes->fetch(PDO::FETCH_ASSOC);

// Sessões ativas
$sessoes = $db->query("SELECT COUNT(*) as ativas FROM customer_sessions WHERE session_end > NOW()")->fetch(PDO::FETCH_ASSOC);

// Vendas por dia (a partir do início)
$vendasDia = $db->prepare("
    SELECT
        DATE(paid_at) as data,
        COUNT(*) as vendas,
        SUM(amount_cents)/100 as valor
    FROM pix_charges
    WHERE status='PAID' AND paid_at >= ?
    GROUP BY DATE(paid_at)
    ORDER BY data ASC
");
$vendasDia->execute([$inicio]);
$vendasDia = $vendasDia->fetchAll(PDO::FETCH_ASSOC);

// Vendas por plano (apenas a partir do início)
$vendasPlano = $db->prepare("
    SELECT
        p.name as plano,
        COUNT(*) as vendas,
        SUM(pc.amount_cents)/100 as faturamento
    FROM pix_charges pc
    JOIN plans p ON p.id = pc.plan_id
    WHERE pc.status='PAID' AND pc.paid_at >= ?
    GROUP BY p.id, p.name
    ORDER BY vendas DESC
");
$vendasPlano->execute([$inicio]);
$vendasPlano = $vendasPlano->fetchAll(PDO::FETCH_ASSOC);

// Vouchers
$vouchers = $db->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status='AVAILABLE' THEN 1 ELSE 0 END) as disponiveis,
        SUM(CASE WHEN status='USED' THEN 1 ELSE 0 END) as usados,
        SUM(CASE WHEN status='EXPIRED' THEN 1 ELSE 0 END) as expirados
    FROM vouchers
")->fetch(PDO::FETCH_ASSOC);

// Últimas 20 vendas (apenas a partir do início)
$ultimasVendas = $db->prepare("
    SELECT
        pc.txid, pc.amount_cents/100 as valor, pc.status,
        pc.client_identifier, p.name as plano,
        pc.created_at, pc.paid_at
    FROM pix_charges pc
    LEFT JOIN plans p ON p.id = pc.plan_id
    WHERE pc.created_at >= ?
    ORDER BY pc.created_at DESC
    LIMIT 20
");
$ultimasVendas->execute([$inicio]);
$ultimasVendas = $ultimasVendas->fetchAll(PDO::FETCH_ASSOC);
// Lista de clientes (apenas a partir do início)
$clientesLista = $db->prepare("
    SELECT
        c.id, c.name, c.document_type, c.document_number,
        c.email, c.phone, c.marketing_consent, c.created_at,
        COUNT(pc.id) as total_compras,
        SUM(CASE WHEN pc.status='PAID' THEN pc.amount_cents ELSE 0 END)/100 as total_gasto
    FROM customers c
    LEFT JOIN pix_charges pc ON pc.customer_id = c.id AND pc.created_at >= ?
    WHERE c.created_at >= ?
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$clientesLista->execute([$inicio, $inicio]);
$clientesLista = $clientesLista->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'kpis' => $kpis,
    'clientes' => $clientes,
    'clientes_lista' => $clientesLista,
    'sessoes_ativas' => $sessoes['ativas'],
    'vendas_por_dia' => $vendasDia,
    'vendas_por_plano' => $vendasPlano,
    'vouchers' => $vouchers,
    'ultimas_vendas' => $ultimasVendas,
    'inicio' => $inicio,
], JSON_UNESCAPED_UNICODE);

echo json_encode([
    'kpis' => $kpis,
    'clientes' => $clientes,
    'sessoes_ativas' => $sessoes['ativas'],
    'vendas_por_dia' => $vendasDia,
    'vendas_por_plano' => $vendasPlano,
    'vouchers' => $vouchers,
    'ultimas_vendas' => $ultimasVendas,
    'inicio' => $inicio,
], JSON_UNESCAPED_UNICODE);
