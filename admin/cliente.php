<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/billing.php';
require __DIR__ . '/inc/layout.php';

$admin = require_login();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$plans = q_all('SELECT * FROM plans ORDER BY sort');

function parse_money(string $v): int
{
    $v = trim(str_replace(['R$', ' '], '', $v));
    if (strpos($v, ',') !== false) $v = str_replace(',', '.', str_replace('.', '', $v));
    return (int) round(((float) $v) * 100);
}

$c = $id ? customer_find($id) : null;
if ($id && !$c) { flash('Cliente não encontrado.', 'bad'); redirect('clientes.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = (string) ($_POST['act'] ?? '');
    $back = $id ? 'cliente.php?id=' . $id : 'clientes.php';

    try {
        if ($act === 'create' || $act === 'save') {
            $name = clean_text($_POST['name'] ?? '', 80);
            $email = clean_text($_POST['email'] ?? '', 160);
            $wa = preg_replace('/\D/', '', (string) ($_POST['whatsapp'] ?? ''));
            $plan = q_one('SELECT * FROM plans WHERE id = ?', [(int) ($_POST['plan_id'] ?? 0)]);
            $price = parse_money((string) ($_POST['price'] ?? ''));
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($wa) < 10 || !$plan || $price <= 0) {
                flash('Confira nome, e-mail, WhatsApp, plano e valor.', 'bad');
                redirect($id ? $back : 'cliente.php?id=0');
            }
            $data = [$name, clean_text($_POST['business'] ?? '', 120) ?: null, $email, $wa, clean_text($_POST['site_url'] ?? '', 255) ?: null, (int) $plan['id'], $price, clean_text($_POST['notes'] ?? '', 4000) ?: null];
            if ($act === 'create') {
                q('INSERT INTO customers (name, business, email, whatsapp, site_url, plan_id, price_cents, notes, status, billing_mode, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())', array_merge($data, ['pendente', 'mercadopago']));
                $newId = (int) db()->lastInsertId();
                log_activity('cliente', 'Cliente criado manualmente', $newId);
                flash('Cliente criado. Gere o link de pagamento abaixo.');
                redirect('cliente.php?id=' . $newId);
            }
            $msgs = ['Dados salvos.'];
            $type = 'ok';
            if ($price !== (int) $c['price_cents'] && $c['mp_preapproval_id'] && $c['status'] !== 'cancelado' && mp_connected()) {
                $r = mp_request('PUT', '/preapproval/' . rawurlencode($c['mp_preapproval_id']), ['auto_recurring' => ['transaction_amount' => round($price / 100, 2), 'currency_id' => 'BRL']]);
                if ($r['error'] !== '') { $msgs[] = 'Atenção: não consegui atualizar o valor no Mercado Pago (' . $r['error'] . ').'; $type = 'warn'; }
                else $msgs[] = 'Valor atualizado também no Mercado Pago (vale a partir da próxima cobrança).';
            }
            q('UPDATE customers SET name=?, business=?, email=?, whatsapp=?, site_url=?, plan_id=?, price_cents=?, notes=?, updated_at=NOW() WHERE id=?', array_merge($data, [$id]));
            log_activity('cliente', 'Dados do cliente editados', $id);
            flash(implode(' ', $msgs), $type);
        } elseif ($c) {
            switch ($act) {
                case 'genlink':
                    if ($c['mp_preapproval_id'] && $c['status'] === 'pendente' && mp_connected()) mp_set_preapproval_status($c['mp_preapproval_id'], 'cancelled'); // descarta o link antigo
                    if ($c['mp_preapproval_id']) q('UPDATE customers SET mp_preapproval_id = NULL WHERE id = ?', [$id]);
                    [$ok, $info] = mp_generate_link($c);
                    flash($ok ? 'Link de assinatura gerado.' : 'Não foi possível gerar o link: ' . $info, $ok ? 'ok' : 'bad');
                    break;
                case 'sendlink':
                    q("DELETE FROM notifications WHERE customer_id = ? AND kind = 'link' AND ref_date = CURDATE()", [$id]);
                    flash(notify_customer($c, 'link', today()) ? 'Link enviado por e-mail para ' . $c['email'] . '.' : 'Não consegui enviar o e-mail (veja a linha do tempo).', 'ok');
                    break;
                case 'sync':
                    [$ok, $info] = mp_sync_customer($c);
                    flash($info, $ok ? 'ok' : 'bad');
                    break;
                case 'manual_pay':
                    $amt = parse_money((string) ($_POST['amount'] ?? '')) ?: (int) $c['price_cents'];
                    apply_payment($c, $amt, 'manual', null, today(), null, clean_text($_POST['note'] ?? '', 190) ?: 'registrado no painel');
                    flash('Pagamento registrado. Próximo vencimento atualizado.');
                    break;
                case 'suspend':
                    suspend_customer($c, 'suspenso manualmente', false);
                    flash('Cliente suspenso. Lembre de tirar o site do ar.', 'warn');
                    break;
                case 'reactivate':
                    $due = (string) ($_POST['new_due'] ?? '');
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) $due = date('Y-m-d', strtotime('+3 day'));
                    reactivate_customer($c, 'reativado manualmente', $due);
                    flash('Cliente reativado. Novo vencimento: ' . fmt_date($due) . '.');
                    break;
                case 'cancel':
                    $m = cancel_customer($c, 'cancelado pelo painel');
                    flash('Assinatura cancelada.' . ($m ? ' ' . $m : ''), $m ? 'warn' : 'ok');
                    break;
                case 'delete':
                    if ((int) q_val("SELECT COUNT(*) FROM payments WHERE customer_id = ? AND status = 'pago'", [$id]) > 0) { flash('Este cliente tem pagamentos registrados. Cancele em vez de apagar.', 'bad'); break; }
                    if ($c['mp_preapproval_id'] && mp_connected()) mp_set_preapproval_status($c['mp_preapproval_id'], 'cancelled');
                    q('UPDATE leads SET customer_id = NULL WHERE customer_id = ?', [$id]);
                    q('DELETE FROM payments WHERE customer_id = ?', [$id]);
                    q('DELETE FROM notifications WHERE customer_id = ?', [$id]);
                    q('DELETE FROM activity WHERE customer_id = ?', [$id]);
                    q('DELETE FROM customers WHERE id = ?', [$id]);
                    flash('Cliente apagado.');
                    redirect('clientes.php');
            }
        }
    } catch (Throwable $e) {
        error_log('[cliente] ' . $e->getMessage());
        flash('Erro: ' . $e->getMessage(), 'bad');
    }
    redirect($back);
}

/* ---------- Tela ---------- */
$pays = $c ? q_all('SELECT * FROM payments WHERE customer_id = ? ORDER BY COALESCE(paid_at, created_at) DESC LIMIT 60', [$id]) : [];
$acts = $c ? q_all('SELECT * FROM activity WHERE customer_id = ? ORDER BY id DESC LIMIT 40', [$id]) : [];
$title = $c ? $c['name'] : 'Novo cliente';
$f = fn(string $k, string $def = '') => h($c[$k] ?? $def);
$linkMsg = $c ? 'Oi, ' . first_name($c['name']) . '! Aqui está o link para pagar sua assinatura do ' . $c['plan_name'] . ' (' . money((int) $c['price_cents']) . '/mês). Dá para pagar no Pix ou no cartão: ' . pay_url($c) : '';
$d = ($c && $c['next_due_date']) ? days_between(today(), $c['next_due_date']) : null;

page_start($title, 'clientes', $admin);
?>
<div class="head">
  <div><p class="eyebrow"><a href="clientes.php">← Clientes</a></p><h1><?= h($title) ?> <?= $c ? badge($c['status']) : '' ?></h1></div>
  <?php if ($c): ?><div class="actions"><a class="btn btn--primary" href="<?= h(wa_link($c['whatsapp'])) ?>" target="_blank" rel="noopener">WhatsApp</a></div><?php endif; ?>
</div>

<?php if ($c): ?>
<div class="grid grid--2">
  <div class="card">
    <h2>Assinatura</h2>
    <dl class="kv">
      <dt>Plano</dt><dd><?= h($c['plan_name']) ?> · <?= h(money((int) $c['price_cents'])) ?>/mês</dd>
      <dt>Próx. vencimento</dt><dd><?= $c['next_due_date'] ? h(fmt_date($c['next_due_date'])) . ($d !== null ? ' <span class="muted">(' . ($d > 0 ? "em $d dia(s)" : ($d === 0 ? 'hoje' : 'atrasado ' . (-$d) . ' dia(s)')) . ')</span>' : '') : '—' ?></dd>
      <dt>Cliente desde</dt><dd><?= h(fmt_date($c['started_at'])) ?></dd>
      <dt>Cobrança</dt><dd><?= has_card_auto($c) ? 'Cartão, automática' : ($c['billing_mode'] === 'manual' ? 'Pix (lembrete todo mês)' : 'ainda não escolheu') ?></dd>
      <dt>Mercado Pago</dt><dd><?= $c['mp_preapproval_id'] ? h(($c['mp_status'] ?: '—')) . ' · <code>' . h($c['mp_preapproval_id']) . '</code>' : 'sem assinatura criada' ?></dd>
      <?php if ($c['suspended_at']): ?><dt>Suspenso em</dt><dd><?= h(fmt_dt($c['suspended_at'])) ?></dd><?php endif; ?>
    </dl>

    <?php if ($c['status'] !== 'cancelado'): ?>
      <hr>
      <?php if (!mp_connected()): ?>
        <p class="aviso aviso--warn">Conecte o Mercado Pago em <a href="configuracoes.php#mp">Configurações</a> para gerar links de pagamento.</p>
      <?php endif; ?>

      <p class="lbl">Link de pagamento do cliente (ele escolhe Pix ou cartão)</p>
      <div class="copy"><input id="lnk" readonly value="<?= h(pay_url($c)) ?>"><button type="button" class="btn" data-copy="lnk">Copiar</button></div>
      <div class="actions" style="margin-top:12px">
        <a class="btn btn--primary btn--sm" target="_blank" rel="noopener" href="<?= h(wa_link($c['whatsapp'], $linkMsg)) ?>">Enviar por WhatsApp</a>
        <a class="btn btn--sm" target="_blank" rel="noopener" href="<?= h(pay_url($c)) ?>">Abrir a página</a>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="sendlink"><button class="btn btn--sm">Enviar por e-mail</button></form>
      </div>

      <div class="actions" style="margin-top:14px">
        <?php if ($c['status'] === 'pendente' || !$c['mp_preapproval_id']): ?>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="genlink"><button class="btn btn--roxo btn--sm"><?= $c['mp_preapproval_id'] ? 'Gerar novo link do cartão' : 'Gerar só o link do cartão' ?></button></form>
        <?php endif; ?>
        <?php if ($c['mp_preapproval_id']): ?>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="sync"><button class="btn btn--sm">Sincronizar com o Mercado Pago</button></form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Ações</h2>
    <?php if ($c['status'] !== 'cancelado'): ?>
      <form method="post" class="form" style="margin-bottom:18px">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="manual_pay">
        <p class="lbl" style="margin:0">Registrar pagamento fora do Mercado Pago (Pix, dinheiro...)</p>
        <div class="row2">
          <label>Valor <input name="amount" value="<?= h(number_format($c['price_cents'] / 100, 2, ',', '.')) ?>" inputmode="decimal"></label>
          <label>Observação <input name="note" maxlength="190" placeholder="Pix, dinheiro..."></label>
        </div>
        <div><button class="btn btn--primary btn--sm">Registrar pagamento (+1 mês)</button></div>
      </form>
    <?php endif; ?>

    <div class="actions">
      <?php if (in_array($c['status'], ['ativo', 'atraso'], true)): ?>
        <form method="post" class="inline" data-confirm="Suspender este cliente agora? Ele recebe o aviso por e-mail."><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="suspend"><button class="btn btn--sm btn--danger">Suspender</button></form>
      <?php endif; ?>
      <?php if (in_array($c['status'], ['suspenso', 'cancelado'], true)): ?>
        <form method="post" class="form" style="width:100%"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="reactivate">
          <div class="row2"><label>Novo vencimento <input type="date" name="new_due" value="<?= h(date('Y-m-d', strtotime('+3 day'))) ?>"></label><div style="align-self:end"><button class="btn btn--primary btn--sm">Reativar (sem cobrar taxa)</button></div></div></form>
      <?php endif; ?>
      <?php if ($c['status'] !== 'cancelado'): ?>
        <form method="post" class="inline" data-confirm="Cancelar a assinatura? Ela também é cancelada no Mercado Pago e o cliente é avisado."><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="cancel"><button class="btn btn--sm btn--danger">Cancelar assinatura</button></form>
      <?php endif; ?>
      <form method="post" class="inline" data-confirm="Apagar este cliente de vez? Só funciona se ele nunca pagou."><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="delete"><button class="btn btn--sm btn--danger">Apagar</button></form>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <h2><?= $c ? 'Dados do cliente' : 'Novo cliente' ?></h2>
  <form method="post" class="form">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="<?= $c ? 'save' : 'create' ?>">
    <div class="row2">
      <label>Nome <input name="name" required maxlength="80" value="<?= $f('name') ?>"></label>
      <label>Nome do negócio <input name="business" maxlength="120" value="<?= $f('business') ?>"></label>
    </div>
    <div class="row2">
      <label>E-mail <input type="email" name="email" required maxlength="160" value="<?= $f('email') ?>"></label>
      <label>WhatsApp <input name="whatsapp" data-phone required inputmode="tel" value="<?= $c ? h(phone_pretty($c['whatsapp'])) : '' ?>"></label>
    </div>
    <div class="row3">
      <label>Plano
        <select name="plan_id"><?php foreach ($plans as $p): ?><option value="<?= $p['id'] ?>" <?= ($c['plan_id'] ?? 0) == $p['id'] ? 'selected' : '' ?>><?= h($p['name'] . ' · ' . money((int) $p['price_cents'])) ?></option><?php endforeach; ?></select></label>
      <label>Valor mensal (R$) <input name="price" required inputmode="decimal" value="<?= h(number_format((($c['price_cents'] ?? ($plans[0]['price_cents'] ?? 6700))) / 100, 2, ',', '.')) ?>"></label>
      <label>Endereço do site <input name="site_url" maxlength="255" placeholder="https://" value="<?= $f('site_url') ?>"></label>
    </div>
    <label>Anotações <textarea class="plain" name="notes" maxlength="4000"><?= $f('notes') ?></textarea></label>
    <div><button class="btn btn--roxo"><?= $c ? 'Salvar dados' : 'Criar cliente' ?></button></div>
  </form>
</div>

<?php if ($c): ?>
<div class="card">
  <h2>Pagamentos</h2>
  <?php if (!$pays): ?><p class="empty">Nenhum pagamento ainda.</p><?php else: ?>
  <div class="table-wrap"><table class="t stack"><thead><tr><th>Data</th><th>Valor</th><th>Situação</th><th>Forma</th><th>Obs.</th></tr></thead><tbody>
  <?php foreach ($pays as $p): ?>
    <tr><td data-l="Data"><?= h(fmt_dt($p['paid_at'] ?: $p['created_at'])) ?></td>
      <td data-l="Valor"><?= h(money((int) $p['amount_cents'])) ?></td>
      <td data-l="Situação"><span class="badge badge--<?= $p['status'] === 'pago' ? 'ok' : ($p['status'] === 'falhou' ? 'bad' : 'warn') ?>"><?= h($p['status']) ?></span></td>
      <td data-l="Forma"><?= $p['method'] === 'manual' ? 'Manual' : ($p['method'] === 'pix' ? 'Pix' : 'Cartão') ?></td>
      <td data-l="Obs."><?= h($p['note'] ?? '') ?></td></tr>
  <?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>

<div class="card">
  <h2>Linha do tempo</h2>
  <?php if (!$acts): ?><p class="empty">Sem eventos ainda.</p><?php else: ?>
  <ul class="timeline"><?php foreach ($acts as $a): ?><li><time><?= h(fmt_dt($a['created_at'])) ?></time><span><?= h($a['message']) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
</div>
<?php endif; ?>
<?php page_end();
