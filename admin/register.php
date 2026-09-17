<?php
require_once __DIR__.'/../api/config.php';
require_once __DIR__.'/../api/auth.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reg_email'])) {
    $superEmail = strtolower(trim($_POST['super_email'] ?? ''));
    $superPass   = $_POST['super_password'] ?? '';
    $name       = trim($_POST['reg_name'] ?? '');
    $address    = trim($_POST['reg_address'] ?? '');
    $cpf        = preg_replace('/[^0-9]/', '', $_POST['reg_cpf'] ?? '');
    $email      = strtolower(trim($_POST['reg_email'] ?? ''));
    $password   = $_POST['reg_password'] ?? '';

    // Verificar credenciais do super admin
    $db = db();
    $super = $db->prepare('SELECT * FROM admins WHERE email=? AND role=?');
    $super->execute([$superEmail, 'super']);
    $superAdmin = $super->fetch(PDO::FETCH_ASSOC);

    if (!$superAdmin || !password_verify($superPass, $superAdmin['password_hash'])) {
        $error = 'Credenciais do super administrador inválidas.';
    } elseif (!$name || !$address || !$cpf || !$email || !$password) {
        $error = 'Todos os campos são obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'E-mail inválido.';
    } elseif (strlen($password) < 6) {
        $error = 'A senha deve ter no mínimo 6 caracteres.';
    } else {
        $check = $db->prepare('SELECT id FROM admins WHERE email=?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'Este e-mail já está cadastrado.';
        } else {
            $checkCpf = $db->prepare('SELECT id FROM admins WHERE cpf=?');
            $checkCpf->execute([$cpf]);
            if ($checkCpf->fetch()) {
                $error = 'Este CPF já está cadastrado.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $insert = $db->prepare('INSERT INTO admins (name, address, cpf, email, password_hash, role) VALUES (?, ?, ?, ?, ?, ?)');
                $insert->execute([$name, $address, $cpf, $email, $hash, 'admin']);
                $success = 'Administrador cadastrado! Faça login.';
            }
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
<title>Cadastrar Admin · Hotspot PIX</title>
</head>
<body>
<div class="login-wrap">
<form class="login-box" method="post" action="register.php" autocomplete="off">
<img src="../assets/img/logo-accesnet.jpg" alt="Accesnet" style="width:120px;margin:0 auto 16px;display:block;border-radius:12px">
<span class="eyebrow">HOTSPOT PIX</span>
<h1>Cadastrar Admin</h1>
<?php if($error):?><p class="error"><?=htmlspecialchars($error)?></p><?php endif?>
<?php if($success):?><p style="color:var(--accent-2);margin-bottom:12px"><?=htmlspecialchars($success)?> <a href="login.php" style="color:var(--accent);font-weight:600">Ir para login</a></p><?php endif?>

<div style="background:var(--panel-2);border-radius:10px;padding:14px;margin-bottom:16px;border:1px solid var(--border)">
<p style="font-weight:700;margin-bottom:10px;color:var(--accent)">🔒 Autorização do Super Administrador</p>
<label>E-mail do super admin<input name="super_email" type="email" required placeholder="super@admin.com" autocomplete="off"></label>
<label>Senha do super admin<input name="super_password" type="password" required placeholder="••••••••" autocomplete="off"></label>
</div>

<div class="login-form">
<label>Nome Completo<input name="reg_name" type="text" required placeholder="Seu nome completo" autocomplete="off"></label>
<label>Endereço<input name="reg_address" type="text" required placeholder="Rua, número, bairro, cidade" autocomplete="off"></label>
<label>CPF<input name="reg_cpf" type="text" required placeholder="000.000.000-00" autocomplete="off"></label>
<label>E-mail<input name="reg_email" type="email" required placeholder="seu@email.com" autocomplete="off"></label>
<label>Senha<input name="reg_password" type="password" required placeholder="Mínimo 6 caracteres" autocomplete="off"></label>
<button type="submit">Cadastrar</button>
</div>
<p style="text-align:center;margin-top:16px;font-size:.9rem;color:var(--muted)"><a href="login.php" style="color:var(--accent);font-weight:600">Voltar para login</a></p>
</form>
</div>
</body>
</html>
