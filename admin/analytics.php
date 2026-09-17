<?php require_once __DIR__.'/../api/auth.php'; requireAdmin(); ?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="../assets/css/admin.css?v=2">
<link rel="stylesheet" href="../assets/css/analytics.css">
<title>Análises · Hotspot PIX</title>
</head>
<body>
<main>
<header>
<div>
<img src="../assets/img/logo-accesnet.jpg" alt="Accesnet" style="width:80px;margin-bottom:8px;border-radius:10px"><p class="eyebrow">HOTSPOT PIX</p>
<h1>Análises &amp; Relatórios</h1>
</div>
<nav style="display:flex;gap:.5rem">
<a href="dashboard.php">Planos</a>
<a href="analytics.php" style="font-weight:700">Análises</a>
<a href="vouchers.php">Vouchers</a>
<a href="logout.php">Sair</a>
</nav>
</header>

<section class="kpi-grid">
<div class="kpi-card"><span class="kpi-label">Faturamento Total</span><span class="kpi-value" id="kpi-faturamento">—</span></div>
<div class="kpi-card"><span class="kpi-label">Vendas Pagas</span><span class="kpi-value" id="kpi-vendas">—</span></div>
<div class="kpi-card"><span class="kpi-label">Faturamento Hoje</span><span class="kpi-value" id="kpi-hoje">—</span></div>
<div class="kpi-card"><span class="kpi-label">Faturamento Semana</span><span class="kpi-value" id="kpi-semana">—</span></div>
<div class="kpi-card"><span class="kpi-label">Faturamento Mês</span><span class="kpi-value" id="kpi-mes">—</span></div>
<div class="kpi-card"><span class="kpi-label">Taxa de Conversão</span><span class="kpi-value" id="kpi-conversao">—</span></div>
<div class="kpi-card"><span class="kpi-label">Clientes Cadastrados</span><span class="kpi-value" id="kpi-clientes">—</span></div>
<div class="kpi-card"><span class="kpi-label">Sessões Ativas</span><span class="kpi-value" id="kpi-sessoes">—</span></div>
<div class="kpi-card"><span class="kpi-label">Vouchers Disponíveis</span><span class="kpi-value" id="kpi-vouchers">—</span></div>
</section>

<section class="panel">
<h2>Vendas — Últimos 30 dias</h2>
<div class="chart-container" id="chart-vendas">
<p style="text-align:center;color:#6b8aa8">Carregando…</p>
</div>
</section>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
<section class="panel">
<h2>Vendas por Plano</h2>
<table class="data-table">
<thead><tr><th>Plano</th><th>Vendas</th><th>Faturamento</th></tr></thead>
<tbody id="tabela-planos"></tbody>
</table>
</section>

<section class="panel">
<h2>Últimas 20 Vendas</h2>
<table class="data-table">
<thead><tr><th>Data</th><th>Plano</th><th>Valor</th><th>Status</th><th>Dispositivo</th></tr></thead>
<tbody id="tabela-vendas"></tbody>
</table>
</section>
</div>
<section class="panel">
<h2>Clientes Cadastrados</h2>
<table class="data-table">
<thead><tr><th>Nome</th><th>Documento</th><th>Email</th><th>Telefone</th><th>Compras</th><th>Total Gasto</th><th>Cadastro</th></tr></thead>
<tbody id="tabela-clientes"></tbody>
</table>
</section>

</main>
<script src="../assets/js/analytics.js"></script>
</body>
</html>
