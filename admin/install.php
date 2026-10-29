<?php
/**
 * Instalador / atualizador do banco.
 *  - Web:  https://seudominio/admin/install.php  (pede a INSTALL_KEY do .env)
 *  - CLI:  php admin/install.php migrate
 * Depois de criado o primeiro administrador, só quem está logado roda as migrações.
 */

declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/migrate.php';

/* ---------- CLI ---------- */
if (PHP_SAPI === 'cli') {
    try {
        foreach (run_migrations() as $l) echo $l, "\n";
    } catch (Throwable $e) {
        fwrite(STDERR, 'ERRO: ' . $e->getMessage() . "\n");
        exit(1);
    }
    exit(0);
}

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$checks = [];
$checks[] = ['PHP 8.0 ou mais novo', version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION];
foreach (['pdo_mysql', 'curl', 'openssl', 'mbstring'] as $ext) $checks[] = ["Extensão $ext", extension_loaded($ext), extension_loaded($ext) ? 'ok' : 'ative no cPanel > Selecionar versão do PHP'];
load_env();
$checks[] = ['Arquivo .env encontrado', defined('ENV_FILE'), defined('ENV_FILE') ? 'ok' : 'crie o .env (veja DEPLOY.md, seção 8)'];
$checks[] = ['APP_KEY definido (16+ caracteres)', strlen(env('APP_KEY')) >= 16, strlen(env('APP_KEY')) >= 16 ? 'ok' : 'preencha APP_KEY no .env'];
$checks[] = ['INSTALL_KEY definido', env('INSTALL_KEY') !== '', env('INSTALL_KEY') !== '' ? 'ok' : 'preencha INSTALL_KEY no .env'];

$dbErr = null;
$dbOk = false;
try {
    db();
    $dbOk = true;
} catch (Throwable $e) {
    $dbErr = $e->getMessage();
}
$checks[] = ['Conexão com o MySQL', $dbOk, $dbOk ? 'ok' : (string) $dbErr];

$installed = $dbOk && db_ready();
$hasAdmin = $installed && (int) q_val('SELECT COUNT(*) FROM admins') > 0;

$msg = [];
$err = null;
$allOk = !array_filter($checks, fn($c) => !$c[1]);

session_boot();
$loggedIn = $installed && current_admin() !== null;
$authorized = $loggedIn || (!empty($_SESSION['install_ok']) && !$hasAdmin);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = $_POST['do'] ?? '';
    try {
        if ($do === 'unlock') {
            $key = (string) ($_POST['install_key'] ?? '');
            if (env('INSTALL_KEY') !== '' && hash_equals(env('INSTALL_KEY'), $key)) {
                $_SESSION['install_ok'] = true;
                $authorized = true;
            } else {
                usleep(600000);
                $err = 'Chave de instalação incorreta.';
            }
        } elseif ($do === 'migrate' && $authorized && $dbOk) {
            csrf_check();
            $msg = run_migrations();
            $installed = db_ready();
            $hasAdmin = $installed && (int) q_val('SELECT COUNT(*) FROM admins') > 0;
        } elseif ($do === 'admin' && $authorized && $installed && !$hasAdmin) {
            csrf_check();
            $name = clean_text($_POST['name'] ?? '', 80);
            $email = clean_text($_POST['email'] ?? '', 160);
            $pass = (string) ($_POST['password'] ?? '');
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Informe nome e e-mail válidos.');
            if (strlen($pass) < 10) throw new RuntimeException('A senha precisa ter pelo menos 10 caracteres.');
            q('INSERT INTO admins (name, email, password_hash, created_at) VALUES (?, ?, ?, NOW())', [$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            unset($_SESSION['install_ok']);
            $msg[] = 'Administrador criado. Já pode entrar no painel.';
            $hasAdmin = true;
            $authorized = false;
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$pending = ($dbOk && $installed) ? migrations_pending() : (($dbOk) ? migration_files() : []);
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Instalação · Assinaturas</title>
<link rel="stylesheet" href="assets/admin.css?v=<?= (int) @filemtime(__DIR__ . '/assets/admin.css') ?>">
</head>
<body class="auth">
<main class="auth__card auth__card--wide">
  <p class="eyebrow">Sistema de assinaturas</p>
  <h1>Instalação</h1>

  <?php foreach ($msg as $m): ?><p class="alert alert--ok"><?= h($m) ?></p><?php endforeach; ?>
  <?php if ($err): ?><p class="alert alert--bad"><?= h($err) ?></p><?php endif; ?>

  <h2>1. Checagem do servidor</h2>
  <ul class="checks">
    <?php foreach ($checks as [$label, $ok, $info]): ?>
      <li class="<?= $ok ? 'is-ok' : 'is-bad' ?>"><span><?= $ok ? '✓' : '✕' ?></span><b><?= h($label) ?></b><small><?= h($info) ?></small></li>
    <?php endforeach; ?>
  </ul>

  <?php if (!$allOk): ?>
    <p class="muted">Corrija os itens em vermelho (normalmente é só preencher o <code>.env</code>) e recarregue esta página.</p>
  <?php elseif (!$authorized): ?>
    <h2>2. Liberar a instalação</h2>
    <form method="post" class="form">
      <input type="hidden" name="do" value="unlock">
      <label>Chave de instalação (a <code>INSTALL_KEY</code> do seu .env)
        <input type="password" name="install_key" autocomplete="off" required>
      </label>
      <button class="btn btn--primary">Liberar</button>
    </form>
    <?php if ($hasAdmin): ?><p class="muted">Já existe um administrador. <a href="login.php">Entrar no painel</a>. Para rodar novas migrações, entre no painel e volte aqui.</p><?php endif; ?>
  <?php else: ?>
    <h2>2. Banco de dados</h2>
    <?php if ($pending): ?>
      <p><?= count($pending) ?> migração(ões) pendente(s): <code><?= h(implode(', ', array_map('basename', $pending))) ?></code></p>
      <form method="post" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="migrate">
        <button class="btn btn--primary">Criar / atualizar tabelas</button>
      </form>
    <?php else: ?>
      <p class="alert alert--ok">Tabelas em dia.</p>
    <?php endif; ?>

    <?php if ($installed && !$hasAdmin): ?>
      <h2>3. Seu acesso de administrador</h2>
      <form method="post" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="admin">
        <label>Nome <input name="name" required maxlength="80" value="Cassiano Galvão"></label>
        <label>E-mail de login <input type="email" name="email" required maxlength="160" value="cassianogalvao2020@gmail.com"></label>
        <label>Senha (10+ caracteres) <input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
        <button class="btn btn--primary">Criar administrador</button>
      </form>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($hasAdmin && !$pending && !$authorized): ?>
    <p style="margin-top:24px"><a class="btn btn--primary" href="login.php">Ir para o login</a></p>
  <?php elseif ($loggedIn): ?>
    <p style="margin-top:24px"><a class="btn" href="index.php">Voltar ao painel</a></p>
  <?php endif; ?>
</main>
</body>
</html>
