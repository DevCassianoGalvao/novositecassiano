<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';

header('Cache-Control: no-store');
if (!db_ready()) redirect('install.php');
session_boot();
if (current_admin()) redirect('index.php');

$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = mb_strtolower(clean_text($_POST['email'] ?? '', 160));
    $pass = (string) ($_POST['password'] ?? '');
    $ip = client_ip();
    $fails = (int) q_val('SELECT COUNT(*) FROM login_attempts WHERE (ip = ? OR email = ?) AND created_at > (NOW() - INTERVAL 15 MINUTE)', [$ip, $email]);
    if ($fails >= 8) {
        $err = 'Muitas tentativas. Aguarde 15 minutos e tente de novo.';
    } else {
        $a = q_one('SELECT * FROM admins WHERE email = ?', [$email]);
        if ($a && password_verify($pass, $a['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $a['id'];
            unset($_SESSION['csrf']);
            q('UPDATE admins SET last_login_at = NOW() WHERE id = ?', [(int) $a['id']]);
            q('DELETE FROM login_attempts WHERE ip = ? OR email = ?', [$ip, $email]);
            if (password_needs_rehash($a['password_hash'], PASSWORD_DEFAULT)) q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), (int) $a['id']]);
            redirect('index.php');
        }
        q('INSERT INTO login_attempts (ip, email, created_at) VALUES (?, ?, NOW())', [$ip, $email]);
        usleep(500000);
        $err = 'E-mail ou senha incorretos.';
    }
}
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Entrar · Assinaturas</title>
<link rel="stylesheet" href="assets/admin.css?v=<?= (int) @filemtime(__DIR__ . '/assets/admin.css') ?>">
</head>
<body class="auth">
<main class="auth__card">
  <p class="eyebrow">Painel</p>
  <h1>Assinaturas</h1>
  <?php if ($err): ?><p class="alert alert--bad"><?= h($err) ?></p><?php endif; ?>
  <form method="post" class="form" autocomplete="on">
    <?= csrf_field() ?>
    <label>E-mail <input type="email" name="email" required autofocus autocomplete="username"></label>
    <label>Senha <input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn--primary">Entrar</button>
  </form>
</main>
</body>
</html>
