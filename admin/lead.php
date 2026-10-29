<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/billing.php';
require __DIR__ . '/inc/layout.php';

$admin = require_login();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$lead = q_one('SELECT * FROM leads WHERE id = ?', [$id]);
if (!$lead) { flash('Lead não encontrado.', 'bad'); redirect('leads.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = (string) ($_POST['act'] ?? '');
    if ($act === 'save') {
        $s = (string) ($_POST['status'] ?? 'novo');
        if (!isset(LEAD_STATUS[$s])) $s = 'novo';
        q('UPDATE leads SET status = ?, notes = ?, updated_at = NOW() WHERE id = ?', [$s, clean_text($_POST['notes'] ?? '', 4000), $id]);
        flash('Lead atualizado.');
    } elseif ($act === 'convert') {
        if ($lead['customer_id']) { redirect('cliente.php?id=' . (int) $lead['customer_id']); }
        $plan = q_one('SELECT * FROM plans WHERE id = ?', [(int) ($_POST['plan_id'] ?? 0)]);
        if (!$plan) { flash('Escolha um plano.', 'bad'); redirect('lead.php?id=' . $id); }
        $cm = (int) ($_POST['cycle_months'] ?? 1); if (!isset(CYCLES[$cm])) $cm = 1;
        q('INSERT INTO customers (name, business, email, whatsapp, site_url, plan_id, price_cents, cycle_months, status, billing_mode, lead_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [$lead['name'], clean_text($_POST['business'] ?? '', 120) ?: null, $lead['email'], $lead['whatsapp'], clean_text($_POST['site_url'] ?? '', 255) ?: null, (int) $plan['id'], (int) $plan['price_cents'], $cm, 'pendente', 'mercadopago', $id]);
        $cid = (int) db()->lastInsertId();
        q("UPDATE leads SET customer_id = ?, status = 'negociando', updated_at = NOW() WHERE id = ?", [$cid, $id]);
        log_activity('cliente', 'Cliente criado a partir do lead', $cid, $id);
        flash('Cliente criado. Agora gere o link de pagamento.');
        redirect('cliente.php?id=' . $cid);
    } elseif ($act === 'delete') {
        q('DELETE FROM leads WHERE id = ?', [$id]);
        flash('Lead apagado.');
        redirect('leads.php');
    }
    redirect('lead.php?id=' . $id);
}

$plans = q_all('SELECT * FROM plans WHERE active = 1 ORDER BY sort');
$utm = $lead['utm'] ? (json_decode($lead['utm'], true) ?: []) : [];
$msg = 'Oi, ' . first_name($lead['name']) . '! Aqui é o Cassiano, vi seu pedido do plano ' . ucfirst($lead['plan_slug']) . ' do site por assinatura. Posso te explicar como funciona?';

page_start('Lead · ' . $lead['name'], 'leads', $admin);
?>
<header class="head">
  <div><?= back_link('leads.php', 'Leads') ?>
    <div class="who who--xl"><?= avatar($lead['name'], 'av--xl') ?><span><h1><?= h($lead['name']) ?></h1><small><span class="badge <?= $lead['status'] === 'novo' ? 'badge--new' : ($lead['status'] === 'fechado' ? 'badge--ok' : ($lead['status'] === 'perdido' ? 'badge--mute' : 'badge--warn')) ?>"><?= h(LEAD_STATUS[$lead['status']]) ?></span> <span>Plano <?= h(ucfirst($lead['plan_slug'])) ?> · <?= h(CYCLES[(int) ($lead['cycle_months'] ?? 1)] ?? 'Mensal') ?> · pediu em <?= h(fmt_dt($lead['created_at'])) ?></span></small></span></div>
  </div>
  <div class="actions"><a class="btn btn--primary" href="<?= h(wa_link($lead['whatsapp'], $msg)) ?>" target="_blank" rel="noopener"><?= ic('chat') ?><span>Chamar no WhatsApp</span></a></div>
</header>

<div class="grid grid--2">
  <div class="card">
    <?= card_head('Contato', 'user') ?>
    <dl class="kv">
      <dt>WhatsApp</dt><dd><?= h(phone_pretty($lead['whatsapp'])) ?></dd>
      <dt>E-mail</dt><dd><a href="mailto:<?= h($lead['email']) ?>"><?= h($lead['email']) ?></a></dd>
      <dt>Plano</dt><dd><?= h(ucfirst($lead['plan_slug'])) ?> · <?= h(CYCLES[(int) ($lead['cycle_months'] ?? 1)] ?? 'Mensal') ?></dd>
      <dt>Recebido em</dt><dd><?= h(fmt_dt($lead['created_at'])) ?></dd>
      <?php foreach ($utm as $k => $v): ?><dt><?= h($k) ?></dt><dd><?= h($v) ?></dd><?php endforeach; ?>
      <?php if ($lead['source_page']): ?><dt>Página</dt><dd class="small"><?= h($lead['source_page']) ?></dd><?php endif; ?>
    </dl>
  </div>
  <div class="card">
    <?= card_head('Acompanhamento', 'note') ?>
    <form method="post" class="form">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="save">
      <label>Status
        <select name="status"><?php foreach (LEAD_STATUS as $k => $label): ?><option value="<?= $k ?>" <?= $lead['status'] === $k ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></label>
      <label>Anotações <textarea class="plain" name="notes" maxlength="4000" placeholder="O que combinaram, objeções, próximo passo..."><?= h($lead['notes']) ?></textarea></label>
      <div><button class="btn btn--roxo"><?= ic('tick') ?><span>Salvar</span></button></div>
    </form>
  </div>
</div>

<div class="card">
  <?= card_head('Fechou? Vire cliente', 'users', '', 'g') ?>
  <?php if ($lead['customer_id']): ?>
    <p class="muted" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin:0">Este lead já virou cliente. <a class="btn btn--sm" href="cliente.php?id=<?= (int) $lead['customer_id'] ?>"><span>Abrir o cliente</span><?= ic('chevron') ?></a></p>
  <?php else: ?>
    <form method="post" class="form">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="convert">
      <div class="row3">
        <label>Plano
          <select name="plan_id"><?php foreach ($plans as $p): ?><option value="<?= $p['id'] ?>" <?= $p['slug'] === $lead['plan_slug'] ? 'selected' : '' ?>><?= h($p['name'] . ' · ' . money((int) $p['price_cents'])) ?></option><?php endforeach; ?></select></label>
        <label>Período
        <select name="cycle_months"><?php foreach (CYCLES as $m => $nm): ?><option value="<?= $m ?>" <?= (int) ($lead['cycle_months'] ?? 1) === $m ? 'selected' : '' ?>><?= h($nm) ?><?= cycle_discount($m) ? ' (-' . cycle_discount($m) . '%)' : '' ?></option><?php endforeach; ?></select></label>
        <label>Nome do negócio <input name="business" maxlength="120"></label>
      </div>
      <label>Endereço do site <input name="site_url" maxlength="255" placeholder="https://"></label>
      <div><button class="btn btn--primary"><?= ic('plus') ?><span>Criar cliente</span></button></div>
    </form>
  <?php endif; ?>
</div>

<form method="post" data-confirm="Apagar este lead de vez?">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="delete">
  <button class="btn btn--danger btn--sm"><?= ic('trash') ?><span>Apagar lead</span></button>
</form>
<?php page_end();
