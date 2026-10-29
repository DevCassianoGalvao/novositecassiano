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
            $cm = (int) ($_POST['cycle_months'] ?? 1); if (!isset(CYCLES[$cm])) $cm = 1;
            $data = [$name, clean_text($_POST['business'] ?? '', 120) ?: null, $email, $wa, clean_text($_POST['site_url'] ?? '', 255) ?: null, (int) $plan['id'], $price, $cm, clean_text($_POST['notes'] ?? '', 4000) ?: null];
            if ($act === 'create') {
                q('INSERT INTO customers (name, business, email, whatsapp, site_url, plan_id, price_cents, cycle_months, notes, status, billing_mode, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())', array_merge($data, ['pendente', 'mercadopago']));
                $newId = (int) db()->lastInsertId();
                log_activity('cliente', 'Cliente criado manualmente', $newId);
                flash('Cliente criado. Gere o link de pagamento abaixo.');
                redirect('cliente.php?id=' . $newId);
            }
            $msgs = ['Dados salvos.'];
            $type = 'ok';
            $newAmt = period_amount($price, $cm);
            if ($cm !== cycle_months($c) && has_card_auto($c)) { $msgs[] = 'Atenção: o cartão continua cobrando no período antigo. Para mudar, gere um novo link do cartão.'; $type = 'warn'; }
            elseif ($cm !== cycle_months($c) && $c['mp_preapproval_id'] && $c['mp_status'] === 'pending') { q('UPDATE customers SET mp_preapproval_id = NULL, mp_init_point = NULL WHERE id = ?', [$id]); }
            if ($newAmt !== cycle_amount($c) && $cm === cycle_months($c) && $c['mp_preapproval_id'] && $c['status'] !== 'cancelado' && mp_connected()) {
                $r = mp_request('PUT', '/preapproval/' . rawurlencode($c['mp_preapproval_id']), ['auto_recurring' => ['transaction_amount' => round($newAmt / 100, 2), 'currency_id' => 'BRL']]);
                if ($r['error'] !== '') { $msgs[] = 'Atenção: não consegui atualizar o valor no Mercado Pago (' . $r['error'] . ').'; $type = 'warn'; }
                else $msgs[] = 'Valor atualizado também no Mercado Pago (vale a partir da próxima cobrança).';
            }
            q('UPDATE customers SET name=?, business=?, email=?, whatsapp=?, site_url=?, plan_id=?, price_cents=?, cycle_months=?, notes=?, updated_at=NOW() WHERE id=?', array_merge($data, [$id]));
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
                    $amt = parse_money((string) ($_POST['amount'] ?? '')) ?: cycle_amount($c);
                    $pm = (int) ($_POST['months'] ?? cycle_months($c)); if (!isset(CYCLES[$pm])) $pm = cycle_months($c);
                    apply_payment($c, $amt, 'manual', null, today(), null, clean_text($_POST['note'] ?? '', 190) ?: 'registrado no painel', true, $pm);
                    flash('Pagamento registrado. Vencimento avançou ' . $pm . ($pm > 1 ? ' meses.' : ' mês.'));
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
<header class="head">
  <div><?= back_link('clientes.php', 'Clientes') ?>
    <div class="who who--xl"><?= $c ? avatar($c['name'], 'av--xl') : '<span class="chip chip--g" style="width:56px;height:56px;border-radius:18px">' . ic('plus') . '</span>' ?><span><h1><?= h($title) ?></h1>
      <small><?= $c ? badge($c['status']) . ' <span>' . h($c['plan_name']) . ' · ' . h(cycle_name($c)) . ' · ' . h(cycle_label($c, false)) . ($c['business'] ? ' · ' . h($c['business']) : '') . '</span>' : 'Cadastre um cliente que fechou fora da página.' ?></small></span></div>
  </div>
  <?php if ($c): ?><div class="actions">
    <a class="btn" href="mailto:<?= h($c['email']) ?>"><?= ic('mail') ?><span>E-mail</span></a>
    <a class="btn btn--primary" href="<?= h(wa_link($c['whatsapp'])) ?>" target="_blank" rel="noopener"><?= ic('chat') ?><span>WhatsApp</span></a></div><?php endif; ?>
</header>

<?php if ($c): ?>
<div class="grid grid--2">
  <div class="card">
    <?= card_head('Assinatura', 'card') ?>
    <dl class="kv">
      <dt>Plano</dt><dd><?= h($c['plan_name']) ?> · <?= h(money((int) $c['price_cents'])) ?>/mês</dd>
      <dt>Período</dt><dd><?= h(cycle_name($c)) ?><?= cycle_months($c) > 1 ? ' · ' . h(cycle_label($c)) : '' ?></dd>
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

      <p class="lbl" style="margin-bottom:8px">Link pessoal do cliente: ele paga (Pix ou cartão), vê a assinatura e cancela por ali</p>
      <div class="copy"><input id="lnk" readonly value="<?= h(pay_url($c)) ?>"><button type="button" class="btn" data-copy="lnk"><?= ic('copy') ?><span>Copiar</span></button></div>
      <div class="actions" style="margin-top:12px">
        <a class="btn btn--primary btn--sm" target="_blank" rel="noopener" href="<?= h(wa_link($c['whatsapp'], $linkMsg)) ?>"><?= ic('chat') ?><span>Enviar por WhatsApp</span></a>
        <a class="btn btn--sm" target="_blank" rel="noopener" href="<?= h(pay_url($c)) ?>"><?= ic('arrow') ?><span>Abrir a página</span></a>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="sendlink"><button class="btn btn--sm"><?= ic('mail') ?><span>Enviar por e-mail</span></button></form>
      </div>

      <div class="actions" style="margin-top:14px">
        <?php if ($c['status'] === 'pendente' || !$c['mp_preapproval_id']): ?>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="genlink"><button class="btn btn--roxo btn--sm"><?= ic('card') ?><?= $c['mp_preapproval_id'] ? 'Gerar novo link do cartão' : 'Gerar só o link do cartão' ?></button></form>
        <?php endif; ?>
        <?php if ($c['mp_preapproval_id']): ?>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="sync"><button class="btn btn--sm"><?= ic('refresh') ?><span>Sincronizar com o Mercado Pago</span></button></form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <?= card_head('Ações', 'zap') ?>
    <?php if ($c['status'] !== 'cancelado'): ?>
      <form method="post" class="form" style="margin-bottom:18px">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="manual_pay">
        <p class="lbl" style="margin:0">Registrar pagamento fora do Mercado Pago (Pix, dinheiro...)</p>
        <div class="row3">
          <label>Valor <input name="amount" value="<?= h(number_format(cycle_amount($c) / 100, 2, ',', '.')) ?>" inputmode="decimal"></label>
          <label>Vale por
            <select name="months"><?php foreach (CYCLES as $m => $nm): ?><option value="<?= $m ?>" <?= $m === cycle_months($c) ? 'selected' : '' ?>><?= $m ?> <?= $m > 1 ? 'meses' : 'mês' ?></option><?php endforeach; ?></select></label>
          <label>Observação <input name="note" maxlength="190" placeholder="Pix, dinheiro..."></label>
        </div>
        <div><button class="btn btn--primary btn--sm"><?= ic('wallet') ?><span>Registrar pagamento</span></button></div>
      </form>
    <?php endif; ?>

    <div class="actions">
      <?php if (in_array($c['status'], ['ativo', 'atraso'], true)): ?>
        <form method="post" class="inline" data-confirm="Suspender este cliente agora? Ele recebe o aviso por e-mail."><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="suspend"><button class="btn btn--sm btn--danger"><?= ic('power') ?><span>Suspender</span></button></form>
      <?php endif; ?>
      <?php if (in_array($c['status'], ['suspenso', 'cancelado'], true)): ?>
        <form method="post" class="form" style="width:100%"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="reactivate">
          <div class="row2"><label>Novo vencimento <input type="date" name="new_due" value="<?= h(date('Y-m-d', strtotime('+3 day'))) ?>"></label><div style="align-self:end"><button class="btn btn--primary btn--sm"><?= ic('play') ?><span>Reativar (sem cobrar taxa)</span></button></div></div></form>
      <?php endif; ?>
      <?php if ($c['status'] !== 'cancelado'): ?>
        <form method="post" class="inline" data-confirm="Cancelar a assinatura? Ela também é cancelada no Mercado Pago e o cliente é avisado."><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="cancel"><button class="btn btn--sm btn--danger"><?= ic('ban') ?><span>Cancelar assinatura</span></button></form>
      <?php endif; ?>
      <form method="post" class="inline" data-confirm="Apagar este cliente de vez? Só funciona se ele nunca pagou."><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="act" value="delete"><button class="btn btn--sm btn--danger"><?= ic('trash') ?><span>Apagar</span></button></form>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <?= card_head($c ? 'Dados do cliente' : 'Dados do novo cliente', 'user') ?>
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
      <label>Valor mensal cheio (R$) <input name="price" required inputmode="decimal" value="<?= h(number_format((($c['price_cents'] ?? ($plans[0]['price_cents'] ?? 6700))) / 100, 2, ',', '.')) ?>"></label>
      <label>Período
        <select name="cycle_months"><?php foreach (CYCLES as $m => $nm): ?><option value="<?= $m ?>" <?= (int) ($c['cycle_months'] ?? 1) === $m ? 'selected' : '' ?>><?= h($nm) ?><?= cycle_discount($m) ? ' (-' . cycle_discount($m) . '%)' : '' ?></option><?php endforeach; ?></select></label>
    </div>
    <label>Endereço do site <input name="site_url" maxlength="255" placeholder="https://" value="<?= $f('site_url') ?>"></label>
    <label>Anotações <textarea class="plain" name="notes" maxlength="4000"><?= $f('notes') ?></textarea></label>
    <div><button class="btn btn--roxo"><?= ic($c ? 'tick' : 'plus') ?><span><?= $c ? 'Salvar dados' : 'Criar cliente' ?></span></button></div>
  </form>
</div>

<?php if ($c): ?>
<div class="card" id="briefing">
  <?= card_head('Briefing', 'file', '', 'g') ?>
  <?php $brief = null; $bfiles = []; try { $brief = briefing_find((int) $c['id']); $bfiles = briefing_files((int) $c['id']); } catch (Throwable $e) {} ?>
  <?php if (!$brief): ?>
    <p class="muted">Ainda não começou. Link do cliente (abre depois do primeiro pagamento):</p>
    <p><input readonly id="bflink" value="<?= h(briefing_url($c)) ?>" onclick="this.select()"></p>
    <p><button type="button" class="btn btn--sm" data-copy="bflink"><?= ic('copy') ?><span>Copiar link</span></button></p>
  <?php else:
      $sent = $brief['status'] === 'enviado';
      $deadline = $sent && $brief['submitted_at'] ? date('Y-m-d H:i:s', strtotime($brief['submitted_at'] . ' +48 hours')) : null; ?>
    <p class="muted"><?= $sent ? 'Briefing enviado em <b>' . h(fmt_dt($brief['submitted_at'])) . '</b>. Prazo de 48h até <b>' . h(fmt_dt($deadline)) . '</b> (pausa enquanto a copy espera a aprovação do cliente).' : 'Em preenchimento, etapa ' . (int) $brief['step'] . ' de 9. Atualizado em ' . h(fmt_dt($brief['updated_at'])) . '.' ?></p>
    <div class="actions" style="margin:10px 0 16px">
      <button type="button" class="btn btn--primary btn--sm" data-copy="bftxt"><?= ic('copy') ?><span>Copiar tudo</span></button>
      <a class="btn btn--sm" href="briefing-export.php?id=<?= (int) $c['id'] ?>&fmt=txt"><?= ic('file') ?><span>Baixar .txt</span></a>
      <?php if ($bfiles): ?><a class="btn btn--sm" href="briefing-export.php?id=<?= (int) $c['id'] ?>&fmt=zip"><?= ic('download') ?><span>Baixar tudo (.zip com os arquivos)</span></a><?php endif; ?>
      <?php if ($sent): ?><a class="btn btn--sm" target="_blank" rel="noopener" href="<?= h(wa_link($c['whatsapp'], 'Recebi tudo! Vou escrever a copy do seu site e te mando aqui pra aprovar.')) ?>"><?= ic('chat') ?><span>Avisar no WhatsApp</span></a><?php endif; ?>
    </div>
    <textarea id="bftxt" readonly style="position:absolute;left:-9999px"><?= h(briefing_text($c, $brief, $bfiles)) ?></textarea>
    <?php foreach (briefing_sections($c, $brief, $bfiles) as [$title, $rows]): ?>
      <h3 class="sec"><?= h($title) ?></h3>
      <dl class="kv">
        <?php foreach ($rows as [$l, $v]): ?><dt><?= h($l) ?></dt><dd style="white-space:pre-wrap"><?= h($v) ?></dd><?php endforeach; ?>
      </dl>
    <?php endforeach; ?>
    <?php if ($bfiles): ?>
      <h3 class="sec">Arquivos recebidos</h3>
      <div class="table-wrap"><table class="t stack"><thead><tr><th>Campo</th><th>Arquivo</th><th>Tamanho</th><th></th></tr></thead><tbody>
        <?php foreach ($bfiles as $field => $rows): foreach ($rows as $r): $isImg = in_array(strtolower(pathinfo($r['orig_name'], PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'webp', 'gif'], true); ?>
          <tr><td data-l="Campo"><?= h(['logo' => 'Logotipo', 'fotos' => 'Fotos', 'manual' => 'Manual de marca', 'outros' => 'Outros', 'copia_arquivo' => 'Copy'][$field] ?? $field) ?></td>
            <td data-l="Arquivo"><span class="who"><?php if ($isImg): ?><a href="arquivo.php?id=<?= (int) $r['id'] ?>&ver=1" target="_blank"><img class="thumb" src="arquivo.php?id=<?= (int) $r['id'] ?>&ver=1" alt=""></a><?php else: ?><span class="chip"><?= ic('file') ?></span><?php endif; ?><span><b><?= h($r['orig_name']) ?></b></span></span></td>
            <td data-l="Tamanho" class="num"><?= h(briefing_size((int) $r['size_bytes'])) ?></td>
            <td class="r" data-l=""><a class="btn btn--sm" href="arquivo.php?id=<?= (int) $r['id'] ?>"><?= ic('download') ?><span>Baixar</span></a></td></tr>
        <?php endforeach; endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="card">
  <?= card_head('Pagamentos', 'wallet') ?>
  <?php if (!$pays): ?><div class="empty"><span class="chip"><?= ic('wallet') ?></span><b>Nenhum pagamento ainda</b><span>Pix, cartão e pagamentos manuais aparecem aqui.</span></div><?php else: ?>
  <div class="table-wrap"><table class="t stack"><thead><tr><th>Data</th><th>Valor</th><th>Situação</th><th>Forma</th><th>Obs.</th></tr></thead><tbody>
  <?php foreach ($pays as $p): ?>
    <tr><td data-l="Data"><?= h(fmt_dt($p['paid_at'] ?: $p['created_at'])) ?></td>
      <td data-l="Valor" class="num"><b><?= h(money((int) $p['amount_cents'])) ?></b></td>
      <td data-l="Situação"><span class="badge badge--<?= $p['status'] === 'pago' ? 'ok' : ($p['status'] === 'falhou' ? 'bad' : 'warn') ?>"><?= h($p['status']) ?></span></td>
      <td data-l="Forma"><?= $p['method'] === 'manual' ? 'Manual' : ($p['method'] === 'pix' ? 'Pix' : 'Cartão') ?></td>
      <td data-l="Obs."><?= h($p['note'] ?? '') ?></td></tr>
  <?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>

<div class="card">
  <?= card_head('Linha do tempo', 'activity') ?>
  <?php if (!$acts): ?><div class="empty"><span class="chip"><?= ic('activity') ?></span><b>Sem eventos ainda</b></div><?php else: ?>
  <ul class="timeline timeline--full"><?php foreach ($acts as $a): ?><?= timeline_item($a) ?><?php endforeach; ?></ul><?php endif; ?>
</div>
<?php endif; ?>
<?php page_end();
