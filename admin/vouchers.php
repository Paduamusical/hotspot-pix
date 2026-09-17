<?php require_once __DIR__ . '/../api/auth.php'; requireAdmin(); ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="../assets/css/admin.css?v=2">
<title>Vouchers VIP - Hotspot Pix</title>
<style>
.v-grid{display:grid;gap:1.5rem;grid-template-columns:1fr 2fr}
.v-form{background:var(--card);padding:1.5rem;border-radius:8px}
.v-form label{display:block;margin-top:1rem}
.v-form input,.v-form select{width:100%;padding:.6rem;border:1px solid var(--border);border-radius:6px;background:var(--bg);color:var(--text)}
.v-form button{margin-top:1rem}
table{width:100%;border-collapse:collapse;font-size:.9rem}
th,td{text-align:left;padding:.5rem;border-bottom:1px solid var(--border)}
.badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:.75rem;font-weight:600}
.badge.available{background:#1a6d1a;color:#fff}
.badge.used{background:#555;color:#ccc}
.badge.expired{background:#6d1a1a;color:#fff}
.badge.cancelled{background:#333;color:#999}
.codes-box{background:var(--bg);border:1px solid var(--border);border-radius:6px;padding:1rem;margin-top:1rem;display:none}
.codes-box pre{margin:0;white-space:pre-wrap;word-break:break-all}
</style>
</head>
<body>
<main>
<header>
  <div>
    <img src="../assets/img/logo-accesnet.jpg" alt="Accesnet" style="width:80px;margin-bottom:8px;border-radius:10px"><p class="eyebrow">HOTSPOT PIX</p>
    <h1>Vouchers VIP</h1>
  </div>
  <nav style="display:flex;gap:.5rem">
    <a href="dashboard.php">Planos</a>
    <a href="vouchers.php" style="font-weight:700">Vouchers</a>
    <a href="logout.php">Sair</a>
  </nav>
</header>
<div class="v-grid">
  <section class="v-form">
    <h2>Gerar vouchers</h2>
    <form id="v-form">
      <label>Plano<select id="plan_id" required></select></label>
      <label>Quantidade<input id="quantity" type="number" min="1" max="50" value="1"></label>
      <label>Expirar em (opcional)<input id="expires_at" type="datetime-local"></label>
      <button type="submit">Gerar voucher(s)</button>
    </form>
    <div class="codes-box" id="codes-box">
      <strong>Codigos gerados:</strong>
      <pre id="codes-list"></pre>
      <button type="button" class="secondary" onclick="navigator.clipboard.writeText(document.getElementById('codes-list').textContent)">Copiar todos</button>
    </div>
  </section>
  <section>
    <h2>Vouchers cadastrados</h2>
    <div id="list">Carregando...</div>
  </section>
</div>
</main>
<script>
let csrf='';
async function apiGet(){
  const r=await fetch('../api/admin_vouchers.php',{method:'GET',headers:{'X-CSRF-TOKEN':csrf}});
  return r.json();
}
async function apiPost(body){
  const r=await fetch('../api/admin_vouchers.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify(body)});
  return r.json();
}
async function load(){
  const data=await apiGet();
  csrf=data.csrf;
  document.getElementById('plan_id').innerHTML=data.plans.map(p=>'<option value="'+p.id+'">'+p.name+' ('+p.duration_minutes+'min)</option>').join('');
  renderList(data.vouchers);
}
function renderList(v){
  const el=document.getElementById('list');
  if(!v.length){el.innerHTML='<p>Nenhum voucher gerado ainda.</p>';return;}
  el.innerHTML='<table><thead><tr><th>Codigo</th><th>Plano</th><th>Status</th><th>Cliente</th><th>Usado em</th><th>Acoes</th></tr></thead><tbody>'+v.map(function(x){return '<tr><td><code>'+x.code+'</code></td><td>'+(x.plan_name||'---')+'</td><td><span class="badge '+x.status.toLowerCase()+'">'+x.status+'</span></td><td>'+(x.client_identifier||'---')+'</td><td>'+(x.used_at||'---')+'</td><td>'+(x.status==='AVAILABLE'?'<button onclick="cancel('+x.id+')" class="plain">Cancelar</button>':'---')+'</td></tr>';}).join('')+'</tbody></table>';
}
async function cancel(id){if(!confirm('Cancelar este voucher?'))return;await apiPost({action:'cancel',id:id});load();}
document.getElementById('v-form').onsubmit=async function(e){
  e.preventDefault();
  var data=await apiPost({plan_id:+document.getElementById('plan_id').value,quantity:+document.getElementById('quantity').value,expires_at:document.getElementById('expires_at').value});
  if(data.ok){document.getElementById('codes-list').textContent=data.codes.join('\n');document.getElementById('codes-box').style.display='block';load();}else{alert(data.error||'Erro ao gerar vouchers');}
};
load();
</script>
</body>
</html>
