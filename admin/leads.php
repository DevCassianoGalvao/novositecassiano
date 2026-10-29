<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/billing.php';
require __DIR__ . '/inc/layout.php';

$admin = require_login();
$st = (string) ($_GET['s'] ?? '');
$qs = trim((string) ($_GET['q'] ?? ''));

$counts = array_column(q_all('SELECT status, COUNT(*) n FROM leads GROUP BY status'), 'n', 'status');
$total = array_sum(array_map('intval', $counts));

$where = [];
$params = [];
if (isset(LEAD_STATUS[$st])) { $where[] = 'status = ?'; $params[] = $st; } else { $st = ''; }
if ($qs !== '') { $where[] = '(name LIKE ? OR email LIKE ? OR whatsapp LIKE ?)'; $like = '%' . $qs . '%'; array_push($params, $like, $like, $like); }
$rows = q_all('SELECT * FROM leads' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY created_at DESC LIMIT 300', $params);

page_start('Leads', 'leads', $admin);
?>
<?php page_head('Leads', 'Quem pediu o site pela página de vendas. Quando a pessoa paga, o lead vira <b>Fechado</b> sozinho.'); ?>

<div class="toolbar">
<div class="tabs">
  <a href="leads.php" class="<?= $st === '' ? 'is-on' : '' ?>">Todos<span class="n"><?= $total ?></span></a>
  <?php foreach (LEAD_STATUS as $k => $label): ?>
    <a href="leads.php?s=<?= $k ?>" class="<?= $st === $k ? 'is-on' : '' ?>"><?= h($label) ?><span class="n"><?= (int) ($counts[$k] ?? 0) ?></span></a>
  <?php endforeach; ?>
</div>

<form method="get" role="search">
  <?php if ($st): ?><input type="hidden" name="s" value="<?= h($st) ?>"><?php endif; ?>
  <label class="search"><?= ic('search') ?><input type="search" name="q" value="<?= h($qs) ?>" placeholder="Buscar por nome, e-mail ou WhatsApp" aria-label="Buscar leads"></label><button class="btn">Buscar</button>
</form>
</div>

<div class="card">
<?php if (!$rows): ?><div class="empty"><span class="chip"><?= ic('inbox') ?></span><b><?= $qs !== '' ? 'Nada encontrado' : 'Nenhum lead aqui' ?></b><span><?= $qs !== '' ? 'Tente buscar por outro nome, e-mail ou número.' : 'Os pedidos da página de vendas chegam nesta lista.' ?></span></div><?php else: ?>
<div class="table-wrap"><table class="t stack">
  <thead><tr><th>Lead</th><th>WhatsApp</th><th>Plano</th><th>Origem</th><th>Status</th><th>Quando</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $l):
      $utm = $l['utm'] ? (json_decode($l['utm'], true) ?: []) : [];
      $src = trim(($utm['utm_source'] ?? '') . ($utm['utm_campaign'] ?? '' ? ' / ' . $utm['utm_campaign'] : ''), ' /') ?: 'direto'; ?>
    <tr>
      <td data-l="Lead"><a class="who" href="lead.php?id=<?= $l['id'] ?>"><?= avatar($l['name'], 'av--sm') ?><span><b><?= h($l['name']) ?></b><small><?= h($l['email']) ?></small></span></a></td>
      <td data-l="WhatsApp"><a href="<?= h(wa_link($l['whatsapp'])) ?>" target="_blank" rel="noopener"><?= h(phone_pretty($l['whatsapp'])) ?></a></td>
      <td data-l="Plano"><?= h(ucfirst($l['plan_slug'])) ?><?= (int) ($l['cycle_months'] ?? 1) > 1 ? '<br><small class="muted">' . h(CYCLES[(int) $l['cycle_months']] ?? '') . '</small>' : '' ?></td>
      <td data-l="Origem"><?= h($src) ?></td>
      <td data-l="Status"><span class="badge <?= $l['status'] === 'novo' ? 'badge--new' : ($l['status'] === 'fechado' ? 'badge--ok' : ($l['status'] === 'perdido' ? 'badge--mute' : 'badge--warn')) ?>"><?= h(LEAD_STATUS[$l['status']]) ?></span></td>
      <td data-l="Quando" class="num muted"><?= h(fmt_date($l['created_at'], 'd/m H:i')) ?></td>
      <td class="r" data-l=""><a class="btn btn--sm" href="lead.php?id=<?= $l['id'] ?>"><span>Abrir</span><?= ic('chevron') ?></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>
</div>
<?php page_end();
