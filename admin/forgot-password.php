<?php
require_once __DIR__.'/../api/config.php';
require_once __DIR__.'/../api/auth.php';
require_once __DIR__.'/../api/database.php';
require_once __DIR__.'/../api/MailService.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $db = db();
    $s = $db->prepare('SELECT * FROM admins WHERE email=?');
    $s->execute([$email]);
    $admin = $s->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        $error = 'E-mail não encontrado.';
    } else {
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $db->prepare('INSERT INTO password_resets (email, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))')
           ->execute([$email, $code]);

        $html = '<div style="font-family:sans-serif;max-width:500px;margin:0 auto">
          <h2 style="color:#6c5ce7">Recuperação de Senha — Hotspot PIX</h2>
          <p>Use o código abaixo para redefinir sua senha:</p>
          <p style="font-size:32px;font-weight:bold;letter-spacing:8px;text-align:center;padding:20px;background:#f5f5f5;border-radius:10px">' . $code . '</p>
          <p>Este código expira em 15 minutos.</p>
          <p style="color:#999;font-size:12px">Se você não solicitou esta recuperação, ignore este e-mail.</p>
        </div>';

        $text = "Recuperação de Senha - Hotspot PIX\n\nSeu codigo: $code\n\nExpira em 15 minutos.";

        if (MailService::send($email, 'Recuperação de Senha - Hotspot PIX', $html, $text)) {
            $success = 'Código enviado para ' . htmlspecialchars($email) . '. Verifique sua caixa de entrada.';
        } else {
            $error = 'Erro ao enviar e-mail. Tente novamente.';
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
<title>Esqueci a Senha · Hotspot PIX</title>
</head>
<body>
<div class="login-wrap">
<form class="login-box" method="post" action="forgot-password.php" autocomplete="off">
<img src="../assets/img/logo-accesnet.jpg" alt="Accesnet" style="width:120px;margin:0 auto 16px;display:block;border-radius:12px">
<span class="eyebrow">HOTSPOT PIX</span>
<h1>Esqueci a Senha</h1>
<?php if($error):?><p class="error"><?=htmlspecialchars($error)?></p><?php endif?>
<?php if($success):?><p style="color:var(--accent-2);margin-bottom:12px"><?=htmlspecialchars($success)?> <a href="reset-password.php" style="color:var(--accent);font-weight:600">Redefinir senha</a></p><?php endif?>
<div class="login-form">
<label>E-mail<input name="email" type="email" required placeholder="seu@email.com" autocomplete="off"></label>
<button type="submit">Enviar código</button>
</div>
<p style="text-align:center;margin-top:16px;font-size:.9rem;color:var(--muted)"><a href="login.php" style="color:var(--accent);font-weight:600">Voltar para login</a></p>
</form>
</div>
</body>
</html>
