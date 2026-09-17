<?php
require_once __DIR__.'/../api/auth.php';
requireSuperAdmin();

$db = db();
$msg = '';

// Remover admin
if (isset($_GET['delete']) && (int)$_GET['delete'] !== (int)($_SESSION['admin_id'] ?? 0)) {
    $del = $db->prepare('DELETE FROM admins WHERE id=? AND role=?');
    $del->execute([(int)$_GET['delete'], 'admin']);
    $msg = 'Administrador removido.';
}

// Promover a super (apenas se houver apenas 1 super)
if (isset($_GET['promote'])) {
    $supers = $db->query("SELECT COUNT(*) as c FROM admins WHERE role='super'")->fetch(PDO::FETCH_ASSOC);
    if ((int)$supers['c'] === 1) {
        $db->prepare("UPDATE admins SET role='super' WHERE id=?")->execute([(int)$_GET['promote']]);
        $msg = 'Administrador promovido a super admin.';
    }
}

$admins = $db->query("SELECT id,name,email,cpf,role,created_at FROM admins ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="../assets/css/admin.css?v=4">
<title>Administradores · Hotspot PIX</title>
</head>
<body>
<main>
<header>
<div>
<img src="../assets/img/logo-accesnet.jpg" alt="Accesnet" style="width:80px;margin-bottom:8px;border-radius:10px">
<p class="eyebrow">HOTSPOT PIX</p>
<h1>Administradores</h1>
</div>
<nav style="display:flex;gap:.5rem">
<a href="dashboard.php">Planos</a>
<a href="analytics.php">Análises</a>
<a href="vouchers.php">Vouchers</a>
<a href="admins.php" style="font-weight:700">Admins</a>
<a href="settings.php">Config</a>
<a href="logout.php">Sair</a>
</nav>
</header>
<?php if($msg):?><p style="color:var(--accent-2);margin-bottom:12px"><?=htmlspecialchars($msg)?></p><?php endif?>
<section class="panel">
<h2>Lista de administradores</h2>
<table style="width:100%;border-collapse:collapse">
<thead>
<tr style="text-align:left;border-bottom:2px solid var(--border)">
<th style="padding:8px">ID</th>
<th style="padding:8px">Nome</th>
<th style="padding:8px">E-mail</th>
<th style="padding:8px">CPF</th>
<th style="padding:8px">Nível</th>
<th style="padding:8px">Cadastro</th>
<th style="padding:8px">Ações</th>
</tr>
</thead>
<tbody>
<?php foreach($admins as $a): ?>
<tr style="border-bottom:1px solid var(--border)">
<td style="padding:8px"><?=$a['id']?></td>
<td style="padding:8px"><?=htmlspecialchars($a['name'])?></td>
<td style="padding:8px"><?=htmlspecialchars($a['email'])?></td>
<td style="padding:8px"><?=htmlspecialchars($a['cpf'])?></td>
<td style="padding:8px">
<?php if($a['role']==='super'): ?>
<span style="color:var(--accent);font-weight:700">SUPER</span>
<?php else: ?>
<span style="color:var(--muted)">Admin</span>
<?php endif; ?>
</td>
<td style="padding:8px"><?=htmlspecialchars($a['created_at'])?></td>
<td style="padding:8px">
<?php if($a['role']!=='super'): ?>
<a href="?delete=<?=$a['id']?>" style="color:#e74c3c" onclick="return confirm('Remover este administrador?')">Remover</a>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</section>
<p style="margin-top:16px"><a href="register.php" style="color:var(--accent);font-weight:600">+ Cadastrar novo administrador</a></p>
</main>
</body>
</html>
