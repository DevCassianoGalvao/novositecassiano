<?php
/** Moldura do painel: menu lateral, avisos e rodapé. */

declare(strict_types=1);

function page_start(string $title, string $active, array $admin): void
{
    $newLeads = 0;
    try {
        $newLeads = (int) q_val("SELECT COUNT(*) FROM leads WHERE status = 'novo'");
    } catch (Throwable $e) {
    }
    $nav = [
        'index'         => ['index.php', 'Painel'],
        'leads'         => ['leads.php', 'Leads'],
        'clientes'      => ['clientes.php', 'Clientes'],
        'rastreamento'  => ['rastreamento.php', 'Rastreamento'],
        'configuracoes' => ['configuracoes.php', 'Configurações'],
    ];
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    ?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= h($title) ?> · Assinaturas</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Space+Mono&display=swap">
<link rel="stylesheet" href="assets/admin.css?v=<?= (int) @filemtime(ADMIN_DIR . '/assets/admin.css') ?>">
<script defer src="assets/admin.js?v=<?= (int) @filemtime(ADMIN_DIR . '/assets/admin.js') ?>"></script>
</head>
<body>
<div class="top"><strong>Assinaturas</strong><button type="button" data-menu>Menu</button></div>
<div class="shell">
  <nav class="side" aria-label="Menu">
    <div class="side__brand"><i>◆</i> Assinaturas</div>
    <?php foreach ($nav as $key => [$href, $label]): ?>
      <a class="nav<?= $key === $active ? ' is-on' : '' ?>" href="<?= $href ?>"><?= h($label) ?><?php if ($key === 'leads' && $newLeads > 0): ?><span class="count"><?= $newLeads ?></span><?php endif; ?></a>
    <?php endforeach; ?>
    <div class="side__foot">
      <?= h($admin['name']) ?><br>
      <a href="logout.php">Sair</a>
    </div>
  </nav>
  <main class="main">
    <?php foreach (flash() ?? [] as $f): ?>
      <p class="alert alert--<?= $f['t'] === 'bad' ? 'bad' : ($f['t'] === 'warn' ? 'warn' : 'ok') ?>"><?= h($f['m']) ?></p>
    <?php endforeach; ?>
<?php
}

function page_end(): void
{
    ?>
  </main>
</div>
</body>
</html>
<?php
}

function post_only(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') redirect('index.php');
    csrf_check();
}
