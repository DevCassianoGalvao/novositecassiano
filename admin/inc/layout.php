<?php
/** Moldura do painel: fundo, menu lateral, ícones, avisos e rodapé. */

declare(strict_types=1);

/** Ícones de traço (mesma família, 24px, traço 1.8). */
const ICONS = [
    'grid'     => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>',
    'leads'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6"/><path d="M22 11h-6"/>',
    'users'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'user'     => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    'code'     => '<path d="m16 18 6-6-6-6"/><path d="m8 6-6 6 6 6"/>',
    'sliders'  => '<path d="M21 4h-7"/><path d="M10 4H3"/><path d="M21 12h-9"/><path d="M8 12H3"/><path d="M21 20h-5"/><path d="M12 20H3"/><path d="M14 2v4"/><path d="M8 10v4"/><path d="M16 18v4"/>',
    'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
    'plus'     => '<path d="M5 12h14"/><path d="M12 5v14"/>',
    'search'   => '<circle cx="11" cy="11" r="7.5"/><path d="m21 21-4.3-4.3"/>',
    'wallet'   => '<path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>',
    'up'       => '<path d="m22 7-8.5 8.5-5-5L2 17"/><path d="M16 7h6v6"/>',
    'down'     => '<path d="m22 17-8.5-8.5-5 5L2 7"/><path d="M16 17h6v-6"/>',
    'calendar' => '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
    'alert'    => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    'power'    => '<path d="M12 2v10"/><path d="M18.4 6.6a9 9 0 1 1-12.77.04"/>',
    'check'    => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m22 4-10 10.01-3-3"/>',
    'tick'     => '<path d="M20 6 9 17l-5-5"/>',
    'clock'    => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    'mail'     => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
    'chat'     => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
    'file'     => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
    'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
    'copy'     => '<rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
    'link'     => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
    'card'     => '<rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/>',
    'qr'       => '<rect width="5" height="5" x="3" y="3" rx="1"/><rect width="5" height="5" x="16" y="3" rx="1"/><rect width="5" height="5" x="3" y="16" rx="1"/><path d="M21 16h-3a2 2 0 0 0-2 2v3"/><path d="M21 21v.01"/><path d="M12 7v3a2 2 0 0 1-2 2H7"/><path d="M3 12h.01"/><path d="M12 3h.01"/><path d="M12 16v.01"/><path d="M16 12h1"/><path d="M21 12v.01"/><path d="M12 21v-1"/>',
    'arrow'    => '<path d="M7 7h10v10"/><path d="M7 17 17 7"/>',
    'chevron'  => '<path d="m9 18 6-6-6-6"/>',
    'back'     => '<path d="m15 18-6-6 6-6"/>',
    'menu'     => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
    'x'        => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
    'refresh'  => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
    'trash'    => '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>',
    'zap'      => '<path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/>',
    'eye'      => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
    'globe'    => '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>',
    'ban'      => '<circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/>',
    'play'     => '<path d="M6 3v18l14-9Z"/>',
    'image'    => '<rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.09-3.09a2 2 0 0 0-2.82 0L6 21"/>',
    'shield'   => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
    'send'     => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
    'lock'     => '<rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/>',
    'pen'      => '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>',
    'funnel'   => '<path d="M10 20a1 1 0 0 0 .55.89l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .52-1.34L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.74 1.67l7.22 7.99A2 2 0 0 1 10 14z"/>',
    'pie'      => '<path d="M21 12c.55 0 1-.45.97-1a10 10 0 0 0-8.97-8.97c-.55-.04-1 .41-1 .97v8a1 1 0 0 0 1 1z"/><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/>',
    'activity' => '<path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/>',
    'target'   => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
    'inbox'    => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
    'note'     => '<path d="M12 20h9"/><path d="M16.38 3.62a1 1 0 0 1 3 3L7.37 18.64a2 2 0 0 1-.85.5l-2.87.84a.5.5 0 0 1-.62-.62l.84-2.87a2 2 0 0 1 .5-.85z"/>',
];

function ic(string $name, string $cls = ''): string
{
    return '<svg class="ic' . ($cls !== '' ? ' ' . $cls : '') . '" aria-hidden="true"><use href="#i-' . $name . '"/></svg>';
}

/** Estrela de quatro pontas do site. */
function spark(string $cls = ''): string
{
    return '<svg class="spk ' . $cls . '" viewBox="0 0 100 100" aria-hidden="true"><use href="#i-spark"/></svg>';
}

function initials(string $name): string
{
    $p = preg_split('/\s+/', trim($name)) ?: [];
    $a = mb_substr($p[0] ?? '?', 0, 1);
    $b = count($p) > 1 ? mb_substr((string) end($p), 0, 1) : '';
    return mb_strtoupper($a . $b);
}

function avatar(string $name, string $cls = ''): string
{
    $hue = [272, 262, 286, 158, 300, 250][crc32($name) % 6];
    return '<span class="av ' . $cls . '" style="--h:' . $hue . '" aria-hidden="true">' . h(initials($name)) . '</span>';
}

/** Cabeçalho de página: título, subtítulo e ações. */
function page_head(string $title, string $sub = '', string $actions = '', string $back = ''): void
{
    echo '<header class="head">';
    echo '<div>';
    if ($back !== '') echo $back;
    echo '<h1>' . h($title) . '</h1>';
    if ($sub !== '') echo '<p class="head__sub">' . $sub . '</p>';
    echo '</div>';
    if ($actions !== '') echo '<div class="actions">' . $actions . '</div>';
    echo '</header>';
}

function back_link(string $href, string $label): string
{
    return '<a class="back" href="' . h($href) . '">' . ic('back') . h($label) . '</a>';
}

function card_head(string $title, string $icon, string $right = '', string $tone = ''): string
{
    return '<div class="card__h"><span class="chip' . ($tone ? ' chip--' . $tone : '') . '">' . ic($icon) . '</span><h2>' . h($title) . '</h2>' . ($right !== '' ? '<div class="card__r">' . $right . '</div>' : '') . '</div>';
}

/** Item da linha do tempo com ícone conforme o tipo do evento. */
function timeline_item(array $a, bool $withCustomer = false): string
{
    $map = ['pagamento' => ['wallet', 'g'], 'status' => ['activity', ''], 'email' => ['mail', ''], 'email_erro' => ['alert', 'bad'], 'lead' => ['leads', ''],
        'cliente' => ['user', ''], 'pix' => ['qr', ''], 'briefing' => ['file', 'g'], 'suspenso' => ['power', 'bad'], 'cancelado' => ['ban', 'bad'], 'link' => ['link', ''], 'mp' => ['card', '']];
    [$i, $t] = $map[$a['kind'] ?? ''] ?? ['zap', ''];
    $who = $withCustomer && !empty($a['cname']) ? '<a href="cliente.php?id=' . (int) $a['customer_id'] . '">' . h($a['cname']) . '</a> · ' : '';
    return '<li><span class="dot' . ($t ? ' dot--' . $t : '') . '">' . ic($i) . '</span><span title="' . h($a['message']) . '">' . $who . h($a['message']) . '</span><time>' . h(fmt_date($a['created_at'], 'd/m H:i')) . '</time></li>';
}

/** Cabeça comum das páginas do cliente (pagamento e briefing). */
function client_head(string $title, string $extraFonts = '', bool $close = true): void
{
    ?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($title) ?> · Cassiano Galvão</title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#07060B">
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Space+Mono:wght@400;700<?= $extraFonts ?>&display=swap">
<link rel="stylesheet" href="/site-por-assinatura/cliente.css?v=<?= (int) @filemtime(ROOT_DIR . '/site-por-assinatura/cliente.css') ?>">
<?php if ($close) echo "</head>\n";
}

/**
 * Tira o link pessoal (?t=) da barra de endereço antes das tags (Pixel/GTM) carregarem,
 * para o código do cliente não ir para a Meta ou o Google. Guarda na aba (sessionStorage)
 * para que recarregar a página continue funcionando.
 */
function client_token_guard(bool $valid): string
{
    return '<script>(function(){try{var ok=' . ($valid ? 'true' : 'false') . ',u=new URL(location.href),t=u.searchParams.get("t");'
        . 'if(t&&ok){sessionStorage.setItem("cg_t",t);u.searchParams.delete("t");history.replaceState(null,"",u.pathname+(u.search||"")+u.hash);}'
        . 'else if(!t&&!ok){var s=sessionStorage.getItem("cg_t");if(s&&/^[a-f0-9]{32,40}$/.test(s)){document.documentElement.style.visibility="hidden";location.replace(u.pathname+"?t="+s);}}'
        . '}catch(e){}})();</script>';
}

function client_bg(): string
{
    $sp = '<svg viewBox="0 0 100 100" class="%s" aria-hidden="true"><use href="#i-spark"/></svg>';
    return '<div class="fx" aria-hidden="true"><i class="o1"></i><i class="o2"></i><i class="o3"></i>' . sprintf($sp, 's1') . sprintf($sp, 's2') . sprintf($sp, 's3') . '</div>';
}

function icon_sprite(): string
{
    $o = '<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>';
    foreach (ICONS as $k => $paths) $o .= '<symbol id="i-' . $k . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</symbol>';
    $o .= '<symbol id="i-spark" viewBox="0 0 100 100"><path d="M50 0C54 36 64 46 100 50 64 54 54 64 50 100 46 64 36 54 0 50 36 46 46 36 50 0Z"/></symbol>';
    return $o . '</defs></svg>';
}

function bg_fx(): string
{
    return '<div class="fx" aria-hidden="true"><i class="fx__o fx__o--a"></i><i class="fx__o fx__o--b"></i><i class="fx__o fx__o--c"></i>'
        . spark('fx__s fx__s--1') . spark('fx__s fx__s--2') . spark('fx__s fx__s--3 spk--g') . '</div>';
}

function admin_head(string $title): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    ?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#07060B">
<title><?= h($title) ?> · Assinaturas</title>
<script>document.documentElement.classList.add('js')</script>
<link rel="icon" href="../assets/img/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap">
<link rel="stylesheet" href="assets/admin.css?v=<?= (int) @filemtime(ADMIN_DIR . '/assets/admin.css') ?>">
<script defer src="assets/admin.js?v=<?= (int) @filemtime(ADMIN_DIR . '/assets/admin.js') ?>"></script>
</head>
<?php
}

function page_start(string $title, string $active, array $admin): void
{
    $newLeads = 0;
    try {
        $newLeads = (int) q_val("SELECT COUNT(*) FROM leads WHERE status = 'novo'");
    } catch (Throwable $e) {
    }
    $nav = [
        'index'         => ['index.php', 'Painel', 'grid'],
        'leads'         => ['leads.php', 'Leads', 'leads'],
        'clientes'      => ['clientes.php', 'Clientes', 'users'],
        'rastreamento'  => ['rastreamento.php', 'Rastreamento', 'code'],
        'configuracoes' => ['configuracoes.php', 'Configurações', 'sliders'],
    ];
    admin_head($title);
    ?>
<body class="app">
<?= icon_sprite() ?>
<?= bg_fx() ?>
<a class="skip" href="#conteudo">Pular para o conteúdo</a>
<div class="top">
  <a class="top__brand" href="index.php"><img src="../assets/img/logo.svg" alt="Cassiano Galvão" width="92" height="38"></a>
  <button type="button" class="iconbtn" data-menu aria-label="Abrir menu" aria-expanded="false"><?= ic('menu') ?></button>
</div>
<div class="shell">
  <nav class="side" aria-label="Menu principal">
    <a class="side__brand" href="index.php"><img src="../assets/img/logo.svg" alt="Cassiano Galvão" width="112" height="46"><span>Assinaturas</span></a>
    <div class="side__nav">
    <?php foreach ($nav as $key => [$href, $label, $icon]): ?>
      <a class="nav<?= $key === $active ? ' is-on' : '' ?>" href="<?= $href ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= ic($icon) ?><span><?= h($label) ?></span><?php if ($key === 'leads' && $newLeads > 0): ?><em class="count" aria-label="<?= $newLeads ?> novos"><?= $newLeads ?></em><?php endif; ?></a>
    <?php endforeach; ?>
    </div>
    <div class="side__promo">
      <?= spark('side__promo-s') ?>
      <b>Página de vendas</b>
      <span>Veja como o cliente chega até aqui.</span>
      <a href="../site-por-assinatura/" target="_blank" rel="noopener">Abrir a página <?= ic('arrow') ?></a>
    </div>
    <div class="side__foot">
      <?= avatar((string) $admin['name'], 'av--sm') ?>
      <div class="side__me"><b><?= h($admin['name']) ?></b><small><?= h($admin['email']) ?></small></div>
      <form method="post" action="logout.php"><?= csrf_field() ?><button class="iconbtn iconbtn--ghost" title="Sair" aria-label="Sair"><?= ic('logout') ?></button></form>
    </div>
  </nav>
  <div class="scrim" data-menu-close></div>
  <main class="main" id="conteudo">
    <?php foreach (flash() ?? [] as $f): $t = $f['t'] === 'bad' ? 'bad' : ($f['t'] === 'warn' ? 'warn' : 'ok'); ?>
      <p class="aviso aviso--<?= $t ?>" role="status"><?= ic($t === 'ok' ? 'check' : 'alert') ?><span><?= h($f['m']) ?></span></p>
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
