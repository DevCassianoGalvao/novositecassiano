<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/billing.php';
require __DIR__ . '/inc/layout.php';

$admin = require_login();

$tabs = [
    'ativos'    => ['Ativos', "c.status IN ('ativo','atraso')"],
    'pendentes' => ['Aguardando pagamento', "c.status = 'pendente'"],
    'inativos'  => ['Inativos', "c.status IN ('suspenso','cancelado')"],
    'todos'     => ['Todos', '1=1'],
];
$t = (string) ($_GET['t'] ?? 'ativos');
if (!isset($tabs[$t])) $t = 'ativos';
$qs = trim((string) ($_GET['q'] ?? ''));

$counts = [];
foreach ($tabs as $k => [, $cond]) $counts[$k] = (int) q_val("SELECT COUNT(*) FROM customers c WHERE $cond");

$where = $tabs[$t][1];
$params = [];
if ($qs !== '') { $where .= ' AND (c.name LIKE ? OR c.business LIKE ? OR c.email LIKE ? OR c.whatsapp LIKE ?)'; $like = '%' . $qs . '%'; $params = [$like, $like, $like, $like]; }
$rows = q_all("SELECT c.*, p.name AS plan_name FROM customers c JOIN plans p ON p.id = c.plan_id WHERE $where ORDER BY c.next_due_date IS NULL, c.next_due_date, c.name LIMIT 500", $params);

page_start('Clientes', 'clientes', $admin);
?>
<div class="head"><div><p class="eyebrow">Assinantes</p><h1>Clientes</h1></div>
  <div class="actions"><a class="btn btn--primary" href="cliente.php?id=0">Novo cliente</a></div></div>

<div class="tabs">
  <?php foreach ($tabs as $k => [$label]): ?>
    <a href="clientes.php?t=<?= $k ?>" class="<?= $t === $k ? 'is-on' : '' ?>"><?= h($label) ?><span class="n"><?= $counts[$k] ?></span></a>
  <?php endforeach; ?>
</div>

<form method="get" class="card" style="padding:14px 16px">
  <input type="hidden" name="t" value="<?= h($t) ?>">
  <div class="copy"><input type="search" name="q" value="<?= h($qs) ?>" placeholder="Buscar por nome, negócio, e-mail ou WhatsApp"><button class="btn">Buscar</button></div>
</form>

<div class="card">
<?php if (!$rows): ?><p class="empty">Nenhum cliente nesta aba.</p><?php else: ?>
<div class="table-wrap"><table class="t stack">
  <thead><tr><th>Cliente</th><th>Plano</th><th>Status</th><th>Vencimento</th><th>Site</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $c):
      $d = $c['next_due_date'] ? days_between(today(), $c['next_due_date']) : null; ?>
    <tr>
      <td data-l="Cliente"><a class="row" href="cliente.php?id=<?= $c['id'] ?>"><?= h($c['name']) ?></a><?= $c['business'] ? '<br><small class="muted">' . h($c['business']) . '</small>' : '' ?></td>
      <td data-l="Plano"><?= h(str_replace('Plano ', '', $c['plan_name'])) ?> · <?= h(money((int) $c['price_cents'])) ?></td>
      <td data-l="Status"><?= badge($c['status']) ?></td>
      <td data-l="Vencimento"><?= $c['next_due_date'] ? h(fmt_date($c['next_due_date'])) . ($d !== null && in_array($c['status'], ['ativo', 'atraso'], true) ? '<br><small class="muted">' . ($d > 0 ? "em $d dia(s)" : ($d === 0 ? 'hoje' : 'atrasado ' . (-$d) . ' dia(s)')) . '</small>' : '') : '—' ?></td>
      <td data-l="Site"><?= $c['site_url'] ? '<a href="' . h($c['site_url']) . '" target="_blank" rel="noopener">abrir</a>' : '—' ?></td>
      <td class="r" data-l=""><a class="btn btn--sm" href="cliente.php?id=<?= $c['id'] ?>">Abrir</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>
</div>
<?php page_end();
