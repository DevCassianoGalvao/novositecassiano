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
<div class="head"><div><p class="eyebrow">Quem pediu o site</p><h1>Leads</h1></div></div>

<div class="tabs">
  <a href="leads.php" class="<?= $st === '' ? 'is-on' : '' ?>">Todos<span class="n"><?= $total ?></span></a>
  <?php foreach (LEAD_STATUS as $k => $label): ?>
    <a href="leads.php?s=<?= $k ?>" class="<?= $st === $k ? 'is-on' : '' ?>"><?= h($label) ?><span class="n"><?= (int) ($counts[$k] ?? 0) ?></span></a>
  <?php endforeach; ?>
</div>

<form method="get" class="card" style="padding:14px 16px">
  <?php if ($st): ?><input type="hidden" name="s" value="<?= h($st) ?>"><?php endif; ?>
  <div class="copy"><input type="search" name="q" value="<?= h($qs) ?>" placeholder="Buscar por nome, e-mail ou WhatsApp"><button class="btn">Buscar</button></div>
</form>

<div class="card">
<?php if (!$rows): ?><p class="empty">Nenhum lead encontrado.</p><?php else: ?>
<div class="table-wrap"><table class="t stack">
  <thead><tr><th>Quando</th><th>Nome</th><th>WhatsApp</th><th>Plano</th><th>Origem</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $l):
      $utm = $l['utm'] ? (json_decode($l['utm'], true) ?: []) : [];
      $src = trim(($utm['utm_source'] ?? '') . ($utm['utm_campaign'] ?? '' ? ' / ' . $utm['utm_campaign'] : ''), ' /') ?: 'direto'; ?>
    <tr>
      <td data-l="Quando"><?= h(fmt_dt($l['created_at'])) ?></td>
      <td data-l="Nome"><a class="row" href="lead.php?id=<?= $l['id'] ?>"><?= h($l['name']) ?></a></td>
      <td data-l="WhatsApp"><a href="<?= h(wa_link($l['whatsapp'])) ?>" target="_blank" rel="noopener"><?= h(phone_pretty($l['whatsapp'])) ?></a></td>
      <td data-l="Plano"><?= h(ucfirst($l['plan_slug'])) ?></td>
      <td data-l="Origem"><?= h($src) ?></td>
      <td data-l="Status"><span class="badge <?= $l['status'] === 'novo' ? 'badge--new' : ($l['status'] === 'fechado' ? 'badge--ok' : ($l['status'] === 'perdido' ? 'badge--mute' : 'badge--warn')) ?>"><?= h(LEAD_STATUS[$l['status']]) ?></span></td>
      <td class="r" data-l=""><a class="btn btn--sm" href="lead.php?id=<?= $l['id'] ?>">Abrir</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>
</div>
<?php page_end();
