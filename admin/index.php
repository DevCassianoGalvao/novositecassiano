<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/billing.php';
require __DIR__ . '/inc/layout.php';

$admin = require_login();

$mrr = (int) q_val("SELECT COALESCE(SUM(price_cents),0) FROM customers WHERE status IN ('ativo','atraso')");
$ativos = (int) q_val("SELECT COUNT(*) FROM customers WHERE status IN ('ativo','atraso')");
$pend = (int) q_val("SELECT COUNT(*) FROM customers WHERE status = 'pendente'");
$inativos = (int) q_val("SELECT COUNT(*) FROM customers WHERE status IN ('suspenso','cancelado')");
$leadsNovos = (int) q_val("SELECT COUNT(*) FROM leads WHERE status = 'novo'");
$leads7 = (int) q_val("SELECT COUNT(*) FROM leads WHERE created_at > (NOW() - INTERVAL 7 DAY)");
$mes = (int) q_val("SELECT COALESCE(SUM(amount_cents),0) FROM payments WHERE status = 'pago' AND paid_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");

$tirar = q_all("SELECT c.*, p.name AS plan_name FROM customers c JOIN plans p ON p.id = c.plan_id WHERE c.status = 'suspenso' ORDER BY c.suspended_at DESC");
$atraso = q_all("SELECT c.*, p.name AS plan_name FROM customers c JOIN plans p ON p.id = c.plan_id WHERE c.status = 'atraso' ORDER BY c.next_due_date");
$vencendo = q_all("SELECT c.*, p.name AS plan_name FROM customers c JOIN plans p ON p.id = c.plan_id WHERE c.status = 'ativo' AND c.next_due_date BETWEEN CURDATE() AND (CURDATE() + INTERVAL 7 DAY) ORDER BY c.next_due_date");
$novos = q_all("SELECT * FROM leads WHERE status = 'novo' ORDER BY created_at DESC LIMIT 6");
$atividade = q_all("SELECT a.*, c.name AS cname FROM activity a LEFT JOIN customers c ON c.id = a.customer_id ORDER BY a.id DESC LIMIT 14");

$todo = [];
if (!mp_connected()) $todo[] = ['Conectar o Mercado Pago', 'configuracoes.php#mp'];
if (env('BREVO_API_KEY') === '') $todo[] = ['Colocar a BREVO_API_KEY no .env (e-mails)', 'configuracoes.php#email'];
if (setting('cron_last_run') === '') $todo[] = ['Agendar a rotina diária (lembretes e suspensão)', 'configuracoes.php#cron'];
if (setting('mp_webhook_secret') === '' && mp_connected()) $todo[] = ['Cadastrar o webhook no Mercado Pago', 'configuracoes.php#mp'];

page_start('Painel', 'index', $admin);
?>
<div class="head"><div><p class="eyebrow">Visão geral</p><h1>Painel</h1></div>
  <div class="actions"><a class="btn btn--primary" href="cliente.php?id=0">Novo cliente</a></div></div>

<?php if ($todo): ?>
<div class="alert alert--warn"><b>Falta configurar:</b>
  <?php foreach ($todo as $i => [$t, $href]): ?><?= $i ? ' · ' : ' ' ?><a href="<?= h($href) ?>"><?= h($t) ?></a><?php endforeach; ?></div>
<?php endif; ?>

<div class="grid grid--4" style="margin-bottom:18px">
  <div class="stat stat--green"><small>Receita mensal recorrente</small><b><?= h(money($mrr)) ?></b></div>
  <div class="stat"><small>Clientes ativos</small><b><?= $ativos ?></b></div>
  <div class="stat"><small>Recebido neste mês</small><b><?= h(money($mes)) ?></b></div>
  <div class="stat"><small>Leads novos</small><b><?= $leadsNovos ?></b></div>
</div>
<div class="grid grid--4" style="margin-bottom:22px">
  <div class="stat"><small>Aguardando pagamento</small><b><?= $pend ?></b></div>
  <div class="stat <?= count($tirar) ? 'stat--bad' : '' ?>"><small>Sites para tirar do ar</small><b><?= count($tirar) ?></b></div>
  <div class="stat"><small>Clientes inativos</small><b><?= $inativos ?></b></div>
  <div class="stat"><small>Leads nos últimos 7 dias</small><b><?= $leads7 ?></b></div>
</div>

<?php if ($tirar): ?>
<div class="card" style="border-color:rgba(255,92,122,.4)">
  <h2>Tire do ar (não pagaram)</h2>
  <div class="table-wrap"><table class="t stack"><thead><tr><th>Cliente</th><th>Site</th><th>Venceu em</th><th></th></tr></thead><tbody>
  <?php foreach ($tirar as $c): ?>
    <tr><td data-l="Cliente"><a class="row" href="cliente.php?id=<?= $c['id'] ?>"><?= h($c['name']) ?></a></td>
      <td data-l="Site"><?= $c['site_url'] ? '<a href="' . h($c['site_url']) . '" target="_blank" rel="noopener">' . h($c['site_url']) . '</a>' : '—' ?></td>
      <td data-l="Venceu em"><?= h(fmt_date($c['next_due_date'])) ?></td>
      <td class="r" data-l=""><a class="btn btn--sm" href="<?= h(wa_link($c['whatsapp'])) ?>" target="_blank" rel="noopener">WhatsApp</a></td></tr>
  <?php endforeach; ?></tbody></table></div>
</div>
<?php endif; ?>

<div class="grid grid--2">
  <div class="card">
    <h2>Leads novos</h2>
    <?php if (!$novos): ?><p class="empty">Nenhum lead novo.</p><?php else: ?>
    <div class="table-wrap"><table class="t stack"><tbody>
    <?php foreach ($novos as $l): ?>
      <tr><td data-l="Lead"><a class="row" href="lead.php?id=<?= $l['id'] ?>"><?= h($l['name']) ?></a></td>
        <td data-l="Plano"><?= h(ucfirst($l['plan_slug'])) ?></td>
        <td data-l="Quando"><?= h(fmt_dt($l['created_at'])) ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <p style="margin:12px 0 0"><a href="leads.php">Ver todos os leads</a></p>
    <?php endif; ?>
  </div>
  <div class="card">
    <h2>Vencem nos próximos 7 dias</h2>
    <?php if (!$vencendo): ?><p class="empty">Nenhum vencimento na semana.</p><?php else: ?>
    <div class="table-wrap"><table class="t stack"><tbody>
    <?php foreach ($vencendo as $c): ?>
      <tr><td data-l="Cliente"><a class="row" href="cliente.php?id=<?= $c['id'] ?>"><?= h($c['name']) ?></a></td>
        <td data-l="Valor"><?= h(money((int) $c['price_cents'])) ?></td>
        <td data-l="Vence"><?= h(fmt_date($c['next_due_date'], 'd/m')) ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
  </div>
</div>

<?php if ($atraso): ?>
<div class="card" style="border-color:rgba(255,194,75,.4)"><h2>Em atraso (dentro da tolerância)</h2>
  <div class="table-wrap"><table class="t stack"><tbody>
  <?php foreach ($atraso as $c): ?>
    <tr><td data-l="Cliente"><a class="row" href="cliente.php?id=<?= $c['id'] ?>"><?= h($c['name']) ?></a></td>
      <td data-l="Venceu em"><?= h(fmt_date($c['next_due_date'])) ?></td>
      <td class="r" data-l=""><a class="btn btn--sm" href="<?= h(wa_link($c['whatsapp'])) ?>" target="_blank" rel="noopener">WhatsApp</a></td></tr>
  <?php endforeach; ?></tbody></table></div></div>
<?php endif; ?>

<div class="card">
  <h2>Atividade recente</h2>
  <?php if (!$atividade): ?><p class="empty">Nada por aqui ainda.</p><?php else: ?>
  <ul class="timeline">
    <?php foreach ($atividade as $a): ?>
      <li><time><?= h(fmt_dt($a['created_at'])) ?></time><span><?= $a['cname'] ? '<a href="cliente.php?id=' . (int) $a['customer_id'] . '">' . h($a['cname']) . '</a> · ' : '' ?><?= h($a['message']) ?></span></li>
    <?php endforeach; ?>
  </ul><?php endif; ?>
</div>
<?php page_end();
