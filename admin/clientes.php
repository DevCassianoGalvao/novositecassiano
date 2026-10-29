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
<?php page_head('Clientes', 'Todos os assinantes, separados pela situação da assinatura.', '<a class="btn btn--primary" href="cliente.php?id=0">' . ic('plus') . '<span>Novo cliente</span></a>'); ?>

<div class="toolbar">
<div class="tabs">
  <?php foreach ($tabs as $k => [$label]): ?>
    <a href="clientes.php?t=<?= $k ?>" class="<?= $t === $k ? 'is-on' : '' ?>"><?= h($label) ?><span class="n"><?= $counts[$k] ?></span></a>
  <?php endforeach; ?>
</div>

<form method="get" role="search">
  <input type="hidden" name="t" value="<?= h($t) ?>">
  <label class="search"><?= ic('search') ?><input type="search" name="q" value="<?= h($qs) ?>" placeholder="Buscar por nome, negócio, e-mail ou WhatsApp" aria-label="Buscar clientes"></label><button class="btn">Buscar</button>
</form>
</div>

<div class="card">
<?php if (!$rows): ?><div class="empty"><span class="chip"><?= ic('users') ?></span><b><?= $qs !== '' ? 'Nada encontrado' : 'Ninguém nesta aba' ?></b><span><?= $qs !== '' ? 'Tente outro nome, e-mail ou número.' : 'Quando alguém entrar nesta situação, aparece aqui.' ?></span></div><?php else: ?>
<div class="table-wrap"><table class="t stack">
  <thead><tr><th>Cliente</th><th>Plano</th><th>Status</th><th>Vencimento</th><th>Site</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $c):
      $d = $c['next_due_date'] ? days_between(today(), $c['next_due_date']) : null; ?>
    <tr>
      <td data-l="Cliente"><a class="who" href="cliente.php?id=<?= $c['id'] ?>"><?= avatar($c['name'], 'av--sm') ?><span><b><?= h($c['name']) ?></b><small><?= h($c['business'] ?: $c['email']) ?></small></span></a></td>
      <td data-l="Plano"><?= h(str_replace('Plano ', '', $c['plan_name'])) ?> · <?= h(cycle_name($c)) ?><br><small class="muted num"><?= h(cycle_label($c, false)) ?></small></td>
      <td data-l="Status"><?= badge($c['status']) ?></td>
      <td data-l="Vencimento"><?= $c['next_due_date'] ? h(fmt_date($c['next_due_date'])) . ($d !== null && in_array($c['status'], ['ativo', 'atraso'], true) ? '<br><small class="muted">' . ($d > 0 ? "em $d dia(s)" : ($d === 0 ? 'hoje' : 'atrasado ' . (-$d) . ' dia(s)')) . '</small>' : '') : '—' ?></td>
      <td data-l="Site"><?= $c['site_url'] ? '<a class="iconbtn" href="' . h($c['site_url']) . '" target="_blank" rel="noopener" aria-label="Abrir o site">' . ic('globe') . '</a>' : '—' ?></td>
      <td class="r" data-l=""><a class="btn btn--sm" href="cliente.php?id=<?= $c['id'] ?>"><span>Abrir</span><?= ic('chevron') ?></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>
</div>
<?php page_end();
