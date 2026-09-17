<?php
require_once __DIR__.'/../api/auth.php';
if (!empty($_SESSION['admin_id'])) { header('Location: dashboard.php'); exit; }
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['email'])) {
    $s=db()->prepare('SELECT * FROM admins WHERE email=?');
    $s->execute([strtolower(trim($_POST['email']??''))]);
    $a=$s->fetch(PDO::FETCH_ASSOC);
    if ($a && password_verify($_POST['password']??'', $a['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id']=$a['id'];
        $_SESSION['admin_role']=$a['role'] ?? 'admin';
        $_SESSION['admin_name']=$a['name'] ?? '';
        $_SESSION['login_time']=time();
        header('Location: dashboard.php'); exit;
    }
    $error='Credenciais inválidas.';
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="../assets/css/admin.css?v=4">
<title>Admin · Hotspot PIX</title>
</head>
<body>
<div class="login-wrap">
<form class="login-box" method="post" action="login.php" autocomplete="off">
<img src="../assets/img/logo-accesnet.jpg" alt="Accesnet" style="width:120px;margin:0 auto 16px;display:block;border-radius:12px">
<span class="eyebrow">HOTSPOT PIX</span>
<h1>Administração</h1>
<?php if($error):?><p class="error"><?=htmlspecialchars($error)?></p><?php endif?>
<div class="login-form">
<label>E-mail<input name="email" type="email" required placeholder="seu@email.com" autocomplete="off"></label>
<label>Senha<input name="password" type="password" required placeholder="••••••••" autocomplete="off"></label>
<button type="submit">Entrar</button>
</div>
<p style="text-align:center;margin-top:16px;font-size:.9rem;color:var(--muted)"><a href="register.php" style="color:var(--accent);font-weight:600">Cadastrar administrador</a> · <a href="forgot-password.php" style="color:var(--accent);font-weight:600">Esqueci a senha</a></p>
</form>
</div>
</body>
</html>
