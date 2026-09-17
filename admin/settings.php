<?php require_once __DIR__.'/../api/auth.php'; requireAdmin(); ?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="../assets/css/admin.css?v=3">
<title>Configurações · Hotspot PIX</title>
</head>
<body>
<main>
<header>
<div>
<img src="../assets/img/logo-accesnet.jpg" alt="Accesnet" style="width:80px;margin-bottom:8px;border-radius:10px">
<p class="eyebrow">HOTSPOT PIX</p>
<h1>Configurações</h1>
</div>
<nav style="display:flex;gap:.5rem">
<a href="dashboard.php">Planos</a>
<a href="analytics.php">Análises</a>
<a href="vouchers.php">Vouchers</a>
<a href="settings.php" style="font-weight:700">Config</a>
<a href="logout.php">Sair</a>
</nav>
</header>

<section class="panel">
<h2>Gateway de Pagamento</h2>
<div id="status" class="status"></div>
<form id="settings-form" style="grid-template-columns:1fr">
<label>Modo de pagamento
<select id="payment_mode">
<option value="mercadopago">Mercado Pago</option>
<option value="simulation">Simulação (teste)</option>
</select>
</label>
<label>Access Token (Mercado Pago)<input id="mp_access_token" type="password" placeholder="Deixe vazio para manter o atual"></label>
<label>Public Key (Mercado Pago)<input id="mp_public_key" type="text" placeholder="APP_USR-..."></label>
<button type="submit">Salvar configurações</button>
</form>
</section>
</main>

<script>
let csrf='';
async function load(){
  const r=await fetch('../api/admin_settings.php');
  const d=await r.json();
  csrf=d.csrf;
  document.getElementById('payment_mode').value=d.settings.payment_mode||'mercadopago';
  document.getElementById('mp_public_key').value=d.settings.mp_public_key||'';
  const s=document.getElementById('status');
  s.textContent=d.settings.has_token?'✓ Token configurado':'⚠ Nenhum token configurado';
  s.className='status '+(d.settings.has_token?'status--success':'status--error');
}
document.getElementById('settings-form').onsubmit=async e=>{
  e.preventDefault();
  const r=await fetch('../api/admin_settings.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({
    mp_access_token:document.getElementById('mp_access_token').value,
    mp_public_key:document.getElementById('mp_public_key').value,
    payment_mode:document.getElementById('payment_mode').value
  })});
  const d=await r.json();
  if(d.success){document.getElementById('mp_access_token').value='';alert(d.message);load();}
  else{alert(d.error||'Erro ao salvar');}
};
load();
</script>
</body>
</html>
