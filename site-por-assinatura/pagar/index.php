<?php
/**
 * Página pessoal do cliente: paga (Pix com QR Code ou cartão recorrente), vê a assinatura,
 * abre o briefing e cancela. Acesso pelo link único com token (enviado nos e-mails).
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/admin/inc/bootstrap.php';
require dirname(__DIR__, 2) . '/admin/inc/billing.php';
require dirname(__DIR__, 2) . '/admin/inc/layout.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
security_headers();

$c = null;
$fatal = null;
try {
    $c = customer_by_token((string) ($_REQUEST['t'] ?? ''));
} catch (Throwable $e) {
    $fatal = 'Sistema indisponível no momento. Tente de novo em instantes.';
}

$wa = wa_link($c ? admin_wa() : '5522997560717', 'Oi, Cassiano! Preciso de ajuda com a minha assinatura do site.');
$err = null;
$okmsg = null;
$pix = null;

/* limite simples por IP: 12 ações a cada 10 min */
function pay_rate_ok(): bool
{
    $f = sys_get_temp_dir() . '/cg_pay_' . md5(client_ip());
    $now = time();
    $hits = is_readable($f) ? array_filter((array) json_decode((string) file_get_contents($f), true), fn($t) => is_int($t) && $t > $now - 600) : [];
    if (count($hits) >= 12) return false;
    $hits[] = $now;
    @file_put_contents($f, json_encode(array_values($hits)), LOCK_EX);
    return true;
}

if ($c && $c['status'] !== 'cancelado' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $act = (string) ($_POST['act'] ?? '');
    try {
        if (!pay_rate_ok()) {
            $err = 'Muitas tentativas seguidas. Aguarde alguns minutos e tente de novo.';
        } elseif ($act === 'cancel') {
            if (!in_array($c['status'], ['ativo', 'atraso', 'suspenso'], true)) {
                $err = 'Não há assinatura ativa para cancelar.';
            } elseif (($_POST['confirm'] ?? '') !== '1') {
                $err = 'Marque a confirmação para cancelar.';
            } else {
                $m = cancel_customer($c, 'cancelado pelo cliente na página dele');
                $okmsg = $m === '' ? 'Sua assinatura foi cancelada. Não haverá novas cobranças.' : 'Cancelei por aqui. O cartão ainda precisa ser desvinculado e o Cassiano já foi avisado para resolver com você.';
                if ($m !== '') error_log('[pagar] cancel: ' . $m);
            }
        } elseif ($act === 'cycle') {
            $m = (int) ($_POST['m'] ?? 1);
            if (!isset(CYCLES[$m])) {
                $err = 'Período inválido.';
            } elseif (has_card_auto($c)) {
                $err = 'Sua cobrança automática no cartão já está ativa. Para mudar o período, fale comigo.';
            } elseif ($m !== cycle_months($c)) {
                q("UPDATE customers SET cycle_months = ?, mp_preapproval_id = IF(mp_status = 'pending' OR mp_status IS NULL, NULL, mp_preapproval_id), mp_init_point = IF(mp_status = 'pending' OR mp_status IS NULL, NULL, mp_init_point), updated_at = NOW() WHERE id = ?", [$m, (int) $c['id']]);
                log_activity('status', 'Cliente escolheu o período ' . mb_strtolower(CYCLES[$m]), (int) $c['id']);
            }
        } elseif (!mp_connected()) {
            $err = 'O pagamento online ainda não está disponível. Fale com o Cassiano pelo WhatsApp.';
        } elseif ($act === 'pix') {
            [$ok, $r] = pix_for_customer($c);
            if ($ok) $pix = $r; else { $err = 'Não consegui gerar o Pix agora. Tente de novo ou fale comigo no WhatsApp.'; error_log('[pagar] pix: ' . $r); }
        } elseif ($act === 'card' && cycle_months($c) > 1) {
            [$ok, $info] = mp_generate_link($c);
            if ($ok && $info !== '') { header('Location: ' . $info); exit; }
            $err = 'Não consegui abrir o pagamento no cartão agora. Tente de novo ou fale comigo no WhatsApp.';
            error_log('[pagar] card checkout: ' . $info);
        } elseif ($act === 'card') {
            if ($c['mp_init_point'] && $c['mp_status'] === 'pending') {
                header('Location: ' . $c['mp_init_point']);
                exit;
            }
            if (has_card_auto($c)) {
                $err = 'Seu cartão já está cadastrado para a cobrança automática.';
            } else {
                if ($c['mp_preapproval_id']) q('UPDATE customers SET mp_preapproval_id = NULL WHERE id = ?', [(int) $c['id']]);
                [$ok, $info] = mp_generate_link($c);
                if ($ok && $info !== '') { header('Location: ' . $info); exit; }
                $err = 'Não consegui abrir o pagamento no cartão agora. Tente de novo ou fale comigo no WhatsApp.';
                error_log('[pagar] card: ' . $info);
            }
        }
    } catch (Throwable $e) {
        error_log('[pagar] ' . $e->getMessage());
        $err = 'Algo deu errado. Tente de novo em instantes.';
    }
    if ($c) $c = customer_find((int) $c['id']);
}

/* volta do checkout do cartão: confere o pagamento na hora (a confirmação também chega pelo webhook) */
$backId = (string) ($_GET['payment_id'] ?? $_GET['collection_id'] ?? '');
if ($c && ctype_digit($backId) && strlen($backId) < 30 && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    try {
        $res = pix_settle($backId);
        if (strpos($res, 'cartão') === 0) {
            $c = customer_find((int) $c['id']);
            if ($res === 'cartão pago e registrado' || $res === 'cartão já registrado') $okmsg = 'Pagamento no cartão aprovado. Obrigado!';
            elseif (in_array($res, ['cartão pending', 'cartão in_process'], true)) $okmsg = 'Seu pagamento no cartão está em análise no Mercado Pago. Assim que for aprovado, esta página atualiza e você recebe um e-mail.';
        }
    } catch (Throwable $e) {
        error_log('[pagar] retorno cartão: ' . $e->getMessage());
    }
}

$due = $c && $c['next_due_date'] ? fmt_date($c['next_due_date']) : null;
$limit = $c && $c['next_due_date'] ? fmt_date(date('Y-m-d', strtotime($c['next_due_date'] . ' +' . tolerance_days() . ' day'))) : null;
$sp = fn(string $cls = '') => '<svg viewBox="0 0 100 100" class="' . $cls . '" aria-hidden="true"><use href="#i-spark"/></svg>';

client_head('Sua assinatura');
?>
<body>
<?= client_token_guard((bool) $c) ?>
<?= icon_sprite() ?><?= client_bg() ?>
<main class="page">
  <header class="bar">
    <a href="/" aria-label="Cassiano Galvão"><img src="/assets/img/logo.svg" alt="Cassiano Galvão" width="104" height="42"></a>
    <span class="tag"><?= ic('lock') ?>Pagamento seguro</span>
  </header>

<?php if ($fatal): ?>
  <div class="hi in-up"><h1>Ops</h1><p><?= h($fatal) ?></p></div>
<?php elseif (!$c): ?>
  <div class="hi in-up"><h1>Link não encontrado</h1><p>Esse link não existe ou foi trocado. Me chama no WhatsApp que eu te mando o certo.</p></div>
  <a class="btn btn--green" href="<?= h($wa) ?>"><?= ic('chat') ?>Falar com o Cassiano</a>
<?php else:
    $st = $c['status'];
    $badge = ['pendente' => ['Aguardando pagamento', 'warn'], 'ativo' => ['Em dia', 'ok'], 'atraso' => ['Em aberto', 'warn'], 'suspenso' => ['Site fora do ar', 'bad'], 'cancelado' => ['Cancelada', 'mute']][$st] ?? ['', 'mute'];
    $auto = has_card_auto($c);
    $hasBrief = false;
    try { $bq = briefing_find((int) $c['id']); $hasBrief = $bq && $bq['status'] === 'enviado'; } catch (Throwable $e) {}
    $intro = [
        'pendente'  => 'Falta só um passo para o seu site entrar na fila: escolher como pagar.',
        'ativo'     => 'Aqui você acompanha a sua assinatura.',
        'atraso'    => 'Sua mensalidade está em aberto. Dá para resolver agora em um minuto.',
        'suspenso'  => 'Seu site está fora do ar por falta de pagamento. Pagou, ele volta na hora.',
        'cancelado' => 'Esta assinatura foi cancelada.',
    ][$st] ?? ''; ?>
  <div class="hi in-up">
    <h1>Olá, <?= h(first_name($c['name'])) ?></h1>
    <p id="intro"><?= h($intro) ?></p>
  </div>

  <section class="glass plan in-up" style="--d:.08s" aria-label="Sua assinatura">
    <div class="plan__top">
      <div>
        <span class="plan__name"><?= h($c['plan_name']) ?> · <?= h(cycle_name($c)) ?></span>
        <div class="plan__price"><b><?= h(money(cycle_amount($c))) ?></b><span><?= h([1 => 'por mês', 3 => 'a cada 3 meses', 6 => 'a cada 6 meses', 12 => 'por ano'][cycle_months($c)]) ?></span></div>
        <?php if (cycle_months($c) > 1): ?><p class="plan__eq">Equivale a <b><?= h(money(monthly_equiv($c))) ?>/mês</b> · você economiza <b><?= h(money((int) $c['price_cents'] * cycle_months($c) - cycle_amount($c))) ?></b></p><?php endif; ?>
        <span class="badge b-<?= $badge[1] ?>" id="planBadge"><?= h($badge[0]) ?></span>
      </div>
      <div class="plan__orb" aria-hidden="true"><i></i><?= $sp() ?></div>
    </div>
    <?php if ($st === 'ativo'): ?><p class="plan__msg" id="planMsg">Próximo vencimento em <b><?= h((string) $due) ?></b>.<?= $auto ? ' Cobrança automática no cartão.' : ' Você recebe um lembrete por e-mail antes.' ?></p>
    <?php elseif ($st === 'atraso'): ?><p class="plan__msg">Venceu em <b><?= h((string) $due) ?></b>. Pague até <b><?= h((string) $limit) ?></b> para o site não sair do ar.</p>
    <?php elseif ($st === 'suspenso'): ?><p class="plan__msg">Pagou, o site volta na hora, <b>sem taxa nenhuma</b>.</p>
    <?php endif; ?>
  </section>

  <?php if ($err): ?><p class="note note--bad" role="alert"><?= ic('alert') ?><span><?= h($err) ?></span></p><?php endif; ?>
  <?php if ($okmsg): ?><p class="note note--ok" role="status"><?= ic('check') ?><span><?= h($okmsg) ?></span></p><?php endif; ?>

  <?php if ($st === 'cancelado'): ?>
    <a class="btn btn--green btn--block" href="<?= h($wa) ?>"><?= ic('chat') ?>Quero voltar a assinar</a>

  <?php elseif ($pix): ?>
    <section class="glass pix in-up" id="pixBox" data-t="<?= h($c['portal_token']) ?>" data-id="<?= (int) $pix['id'] ?>" data-value="<?= h((string) round($pix['amount_cents'] / 100, 2)) ?>" aria-live="polite">
      <div id="pixWait">
        <h2>Pague com Pix</h2>
        <p class="pix__val">Valor: <b><?= h(money((int) $pix['amount_cents'])) ?></b></p>
        <?php if ($pix['qr_base64']): ?><div class="qr"><img alt="QR Code do Pix" width="244" height="244" src="data:image/png;base64,<?= h($pix['qr_base64']) ?>"></div><?php endif; ?>
        <ol class="steps">
          <li><span>1</span><em>Abra o app do seu banco e escolha <b>Pix</b>.</em></li>
          <li><span>2</span><em>Leia o QR Code ou use o <b>Pix Copia e Cola</b>.</em></li>
          <li><span>3</span><em>Confirme. Esta tela atualiza sozinha.</em></li>
        </ol>
        <div class="copy"><input id="pixcode" readonly value="<?= h($pix['qr_code']) ?>" aria-label="Código Pix Copia e Cola"><button type="button" class="btn btn--green btn--sm" id="pixcopy"><?= ic('copy') ?><span>Copiar código</span></button></div>
        <span class="wait"><i></i>Aguardando o pagamento</span>
        <span class="pix__exp">O código vale até <?= h(fmt_dt($pix['expires_at'])) ?>.</span>
      </div>
      <div id="pixDone" class="done" hidden>
        <div class="tick"><svg class="ok" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg><?= $sp('sp sp1') ?><?= $sp('sp sp2') ?><?= $sp('sp sp3') ?></div>
        <h1>Pagamento confirmado!</h1>
        <p>Obrigado, <?= h(first_name($c['name'])) ?>. Agora me conta sobre o seu negócio para eu começar o seu site.</p>
        <a class="btn btn--green btn--block" href="../briefing/?t=<?= h($c['portal_token']) ?>"><?= ic('file') ?>Preencher o briefing</a>
      </div>
    </section>
    <?php if (!$auto): ?><form method="post" class="alt" id="cardAlt"><input type="hidden" name="t" value="<?= h($c['portal_token']) ?>"><button class="btn btn--ghost btn--block" name="act" value="card"><?= ic('card') ?>Prefiro pagar no cartão</button></form><?php endif; ?>

  <?php else: ?>
    <?php if ($st !== 'ativo'): ?>
      <p class="safe in-up" style="--d:.14s"><?= ic('shield') ?><span><b>Sem fidelidade.</b> Você pode cancelar quando quiser, pelo link que chega no seu e-mail, sem precisar de conta nem falar comigo. Cancelou, não é cobrado de novo.</span></p>
    <?php endif; ?>
    <?php if (!$auto): $mNow = cycle_months($c); ?>
    <div class="sec in-up" style="--d:.16s"><h2><?= $st === 'ativo' ? 'Próximo período' : 'Escolha o período' ?></h2><span>à vista sai mais barato</span></div>
    <form method="post" class="cycles in-up" style="--d:.17s" aria-label="Período">
      <input type="hidden" name="t" value="<?= h($c['portal_token']) ?>"><input type="hidden" name="act" value="cycle">
      <?php foreach (CYCLES as $m => $name): $amt = period_amount((int) $c['price_cents'], $m); $d = cycle_discount($m); ?>
        <button class="cyc<?= $m === $mNow ? ' is-on' : '' ?>" name="m" value="<?= $m ?>" type="submit" aria-pressed="<?= $m === $mNow ? 'true' : 'false' ?>">
          <span class="cyc__top"><b><?= h($name) ?></b><?php if ($d): ?><em>-<?= $d ?>%</em><?php endif; ?></span>
          <strong><?= h(money((int) round($amt / $m))) ?><small>/mês</small></strong>
          <span class="cyc__tot"><?= $m === 1 ? 'cobrado todo mês' : h(money($amt)) . ' à vista' ?></span>
        </button>
      <?php endforeach; ?>
    </form>
    <?php endif; ?>
    <div class="sec in-up" style="--d:.18s"><h2><?= $st === 'ativo' ? 'Forma de pagamento' : 'Como você quer pagar?' ?></h2><?php if ($st === 'ativo'): ?><span><?= $auto ? 'cartão automático' : 'hoje: Pix' ?></span><?php endif; ?></div>
    <?php if ($st === 'ativo'): ?><p class="in-up" style="--d:.2s;font-size:15px;margin-top:-4px"><?= $auto ? 'Está tudo certo. Se quiser, você pode adiantar a próxima mensalidade pelo Pix.' : 'Está tudo certo. Se preferir não se preocupar com o próximo pagamento, deixe a cobrança automática no cartão.' ?></p><?php endif; ?>
    <form method="post" class="opts in-up" style="--d:.22s">
      <input type="hidden" name="t" value="<?= h($c['portal_token']) ?>">
      <button class="opt opt--pix" name="act" value="pix" type="submit">
        <span class="opt__ic"><?= ic('qr') ?></span>
        <span><strong><?= $st === 'ativo' ? 'Adiantar com Pix' : 'Pix' ?> <span class="pill">na hora</span></strong><small><?= cycle_months($c) > 1 ? 'Paga ' . h(money(cycle_amount($c))) . ' à vista e fica em dia por ' . cycle_months($c) . ' meses. O QR Code aparece aqui mesmo.' : 'O QR Code aparece aqui mesmo. Pagou, está liberado. Todo mês chega um lembrete com o link.' ?></small></span>
        <?= ic('chevron', 'go') ?>
      </button>
      <?php if (!$auto): ?>
      <button class="opt" name="act" value="card" type="submit">
        <span class="opt__ic"><?= ic('card') ?></span>
        <span><strong>Cartão de crédito <span class="pill pill--roxo"><?= cycle_months($c) > 1 ? 'até ' . min(12, cycle_months($c)) . 'x' : 'automático' ?></span></strong><small><?= cycle_months($c) > 1 ? h(money(cycle_amount($c))) . ' à vista ou em até ' . min(12, cycle_months($c)) . 'x no checkout seguro do Mercado Pago.' : 'Cobra sozinho todo mês, sem você precisar lembrar. Pagamento seguro pelo Mercado Pago.' ?></small></span>
        <?= ic('chevron', 'go') ?>
      </button>
      <?php endif; ?>
    </form>
    <?php if (!$auto && cycle_months($c) > 1): ?>
      <p class="fee in-up" style="--d:.24s"><?= ic('card') ?><span>No cartão à vista não há acréscimo. Se você parcelar, o Mercado Pago (gateway de pagamento) cobra os juros do parcelamento, e o valor de cada parcela aparece antes de você confirmar. No Pix, nunca há acréscimo.</span></p>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (($st === 'ativo' || $st === 'atraso') && !$pix): ?>
    <section class="glass box brief in-up" style="--d:.26s;margin-top:22px">
      <span class="brief__ic"><?= ic($hasBrief ? 'check' : 'file') ?></span>
      <span class="brief__t"><b><?= $hasBrief ? 'Briefing enviado' : 'Falta o briefing do seu site' ?></b><span><?= $hasBrief ? 'Lembrou de algo? Dá para ajustar as respostas.' : 'Leva uns 15 minutos e é o que eu preciso para começar.' ?></span></span>
      <a class="btn <?= $hasBrief ? 'btn--ghost' : 'btn--green' ?> btn--sm" href="../briefing/?t=<?= h($c['portal_token']) ?>"><?= $hasBrief ? 'Ver ou editar' : 'Preencher agora' ?></a>
    </section>
  <?php endif; ?>

  <?php if (in_array($st, ['ativo', 'atraso', 'suspenso'], true) && !$pix): ?>
    <details class="cancel">
      <summary><?= ic('ban') ?>Quero cancelar minha assinatura</summary>
      <form method="post" class="in">
        <input type="hidden" name="t" value="<?= h($c['portal_token']) ?>">
        <p>Ao cancelar, as próximas cobranças param<?= $auto ? ' e a cobrança automática do cartão é encerrada' : '' ?>, e o seu site é desativado. Se mudar de ideia, é só me chamar.</p>
        <label><input type="checkbox" name="confirm" value="1" required> Entendi e quero cancelar</label>
        <button class="btn btn--danger btn--sm" name="act" value="cancel" type="submit"><?= ic('ban') ?>Cancelar assinatura</button>
      </form>
    </details>
  <?php endif; ?>
<?php endif; ?>

  <footer class="foot">
    <a href="<?= h($wa) ?>"><?= ic('chat') ?>Dúvida? Fale comigo no WhatsApp</a>
    <small><?= ic('lock') ?>Pagamentos processados pelo Mercado Pago</small>
  </footer>
</main>
<script>
(function(){
  var b=document.getElementById('pixcopy'),i=document.getElementById('pixcode');
  if(b&&i)b.addEventListener('click',function(){
    var l=b.querySelector('span'),done=function(){var o=l.textContent;l.textContent='Copiado!';setTimeout(function(){l.textContent=o},1800)};
    if(navigator.clipboard&&window.isSecureContext)navigator.clipboard.writeText(i.value).then(done,function(){i.select()});else{i.select();try{document.execCommand('copy');done()}catch(e){}}
  });
  document.querySelectorAll('form').forEach(function(f){f.addEventListener('submit',function(e){var s=e.submitter;if(s&&s.classList.contains('opt')){s.setAttribute('aria-busy','true');s.style.opacity='.7';}})});
  var box=document.getElementById('pixBox'); if(!box)return;
  var t=box.getAttribute('data-t'),id=box.getAttribute('data-id'),tries=0,stop=false;
  function paid(){
    stop=true;document.getElementById('pixWait').hidden=true;document.getElementById('pixDone').hidden=false;
    var alt=document.getElementById('cardAlt');if(alt)alt.hidden=true;
    var bd=document.getElementById('planBadge');if(bd){bd.className='badge b-ok';bd.textContent='Pago';}
    var it=document.getElementById('intro');if(it)it.textContent='Pagamento confirmado. Agora é só me contar sobre o seu negócio.';
    box.scrollIntoView({behavior:'smooth',block:'center'});
    var v=parseFloat(box.getAttribute('data-value'))||0;
    try{window.dataLayer=window.dataLayer||[];window.dataLayer.push({event:'pix_pago',value:v,currency:'BRL'})}catch(e){}
  }
  function poll(){
    if(stop||tries++>360)return;
    fetch('status.php?t='+encodeURIComponent(t)+'&id='+encodeURIComponent(id),{cache:'no-store'})
      .then(function(r){return r.json()}).then(function(d){if(d&&d.paid)paid();else setTimeout(poll,5000)})
      .catch(function(){setTimeout(poll,8000)});
  }
  setTimeout(poll,4000);
})();
</script>
<script defer src="/api/tags.php"></script>
</body>
</html>
