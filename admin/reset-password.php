<?php
require_once __DIR__.'/../api/config.php';
require_once __DIR__.'/../api/auth.php';
require_once __DIR__.'/../api/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $code = trim($_POST['code'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $db = db();

    if (!$email || !$code || !$newPassword) {
        $error = 'Todos os campos são obrigatórios.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'As senhas não conferem.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'A senha deve ter no mínimo 6 caracteres.';
    } else {
        $s = $db->prepare('SELECT * FROM password_resets WHERE email=? AND code=? AND used=0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
        $s->execute([$email, $code]);
        $reset = $s->fetch(PDO::FETCH_ASSOC);

        if (!$reset) {
            $error = 'Código inválido ou expirado.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $db->prepare('UPDATE admins SET password_hash=? WHERE email=?')->execute([$hash, $email]);
            $db->prepare('UPDATE password_resets SET used=1 WHERE id=?')->execute([$reset['id']]);
            $success = 'Senha redefinida com sucesso! Faça login.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="../assets/css/admin.css?v=4">
<title>Redefinir Senha · Hotspot PIX</title>
</head>
<body>
<div class="login-wrap">
<form class="login-box" method="post" action="reset-password.php" autocomplete="off">
<img src="../assets/img/logo-accesnet.jpg" alt="Accesnet" style="width:120px;margin:0 auto 16px;display:block;border-radius:12px">
<span class="eyebrow">HOTSPOT PIX</span>
<h1>Redefinir Senha</h1>
<?php if($error):?><p class="error"><?=htmlspecialchars($error)?></p><?php endif?>
<?php if($success):?><p style="color:var(--accent-2);margin-bottom:12px"><?=htmlspecialchars($success)?> <a href="login.php" style="color:var(--accent);font-weight:600">Ir para login</a></p><?php endif?>
<div class="login-form">
<label>E-mail<input name="email" type="email" required placeholder="seu@email.com" autocomplete="off"></label>
<label>Código de recuperação<input name="code" type="text" required placeholder="000000" autocomplete="off" maxlength="6"></label>
<label>Nova senha<input name="new_password" type="password" required placeholder="Mínimo 6 caracteres" autocomplete="off"></label>
<label>Confirmar senha<input name="confirm_password" type="password" required placeholder="Repita a nova senha" autocomplete="off"></label>
<button type="submit">Redefinir senha</button>
</div>
<p style="text-align:center;margin-top:16px;font-size:.9rem;color:var(--muted)"><a href="login.php" style="color:var(--accent);font-weight:600">Voltar para login</a></p>
</form>
</div>
</body>
</html>
