<?php
require_once __DIR__.'/../api/auth.php';
requireAdmin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $db = db();

    $s = $db->prepare('SELECT * FROM admins WHERE id=?');
    $s->execute([$_SESSION['admin_id']]);
    $admin = $s->fetch(PDO::FETCH_ASSOC);

    if (!$admin || !password_verify($currentPassword, $admin['password_hash'])) {
        $error = 'Senha atual incorreta.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'As senhas não conferem.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'A senha deve ter no mínimo 6 caracteres.';
    } else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $db->prepare('UPDATE admins SET password_hash=? WHERE id=?')->execute([$hash, $_SESSION['admin_id']]);
        $success = 'Senha alterada com sucesso!';
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="../assets/css/admin.css?v=4">
<title>Trocar Senha · Hotspot PIX</title>
</head>
<body>
<div class="login-wrap">
<form class="login-box" method="post" action="change-password.php" autocomplete="off">
<img src="../assets/img/logo-accesnet.jpg" alt="Accesnet" style="width:120px;margin:0 auto 16px;display:block;border-radius:12px">
<span class="eyebrow">HOTSPOT PIX</span>
<h1>Trocar Senha</h1>
<?php if($error):?><p class="error"><?=htmlspecialchars($error)?></p><?php endif?>
<?php if($success):?><p style="color:var(--accent-2);margin-bottom:12px"><?=htmlspecialchars($success)?></p><?php endif?>
<div class="login-form">
<label>Senha atual<input name="current_password" type="password" required placeholder="••••••••" autocomplete="off"></label>
<label>Nova senha<input name="new_password" type="password" required placeholder="Mínimo 6 caracteres" autocomplete="off"></label>
<label>Confirmar senha<input name="confirm_password" type="password" required placeholder="Repita a nova senha" autocomplete="off"></label>
<button type="submit">Trocar senha</button>
</div>
<p style="text-align:center;margin-top:16px;font-size:.9rem;color:var(--muted)"><a href="dashboard.php" style="color:var(--accent);font-weight:600">Voltar ao painel</a></p>
</form>
</div>
</body>
</html>
