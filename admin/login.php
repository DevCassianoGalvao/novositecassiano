<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';

header('Cache-Control: no-store');
security_headers();
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
            $_SESSION['seen'] = time();
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
require __DIR__ . '/inc/layout.php';
admin_head('Entrar');
?>
<body class="auth">
<?= icon_sprite() ?><?= bg_fx() ?>
<main class="auth__card">
  <span class="auth__orb" aria-hidden="true"></span>
  <img class="auth__logo" src="../assets/img/logo.svg" alt="Cassiano Galvão" width="132" height="54">
  <h1>Entrar no painel</h1>
  <p class="head__sub">Assinaturas, clientes e briefings do site por assinatura.</p>
  <?php if ($err): ?><p class="aviso aviso--bad" role="alert"><?= ic('alert') ?><span><?= h($err) ?></span></p><?php endif; ?>
  <form method="post" class="form" autocomplete="on">
    <?= csrf_field() ?>
    <label>E-mail <input type="email" name="email" required autofocus autocomplete="username"></label>
    <label>Senha <input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn--primary" style="margin-top:6px"><?= ic('lock') ?><span>Entrar</span></button>
  </form>
</main>
</body>
</html>
