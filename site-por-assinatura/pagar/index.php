<?php
/**
 * Página de pagamento do cliente: Pix (QR Code) ou cartão (cobrança automática).
 * Acesso por link único com token (enviado por e-mail/WhatsApp pelo painel).
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/admin/inc/bootstrap.php';
require dirname(__DIR__, 2) . '/admin/inc/billing.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

$c = null;
$fatal = null;
try {
    $c = customer_by_token((string) ($_REQUEST['t'] ?? ''));
} catch (Throwable $e) {
    $fatal = 'Sistema indisponível no momento. Tente de novo em instantes.';
}

$wa = wa_link($c ? admin_wa() : '5522997560717', 'Oi, Cassiano! Preciso de ajuda com o pagamento da minha assinatura.');
$err = null;
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
            $err = 'Muitas tentativas. Aguarde alguns minutos.';
        } elseif (!mp_connected()) {
            $err = 'O pagamento online ainda não está disponível. Fale com o Cassiano pelo WhatsApp.';
        } elseif ($act === 'pix') {
            [$ok, $r] = pix_for_customer($c);
            if ($ok) $pix = $r; else { $err = 'Não consegui gerar o Pix agora. Tente de novo ou fale comigo no WhatsApp.'; error_log('[pagar] pix: ' . $r); }
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

$due = $c && $c['next_due_date'] ? fmt_date($c['next_due_date']) : null;
$limit = $c && $c['next_due_date'] ? fmt_date(date('Y-m-d', strtotime($c['next_due_date'] . ' +' . tolerance_days() . ' day'))) : null;
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Pagamento da assinatura · Cassiano Galvão</title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#07060B">
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Caveat:wght@500&family=Space+Grotesk:wght@400;500;700&display=swap">
<style>
  :root{--bg:#07060B;--card:#120F1D;--roxo:#8928FF;--roxo-claro:#A75FFF;--verde:#00EF9E;--txt:#ECE8F6;--muted:#A49DB8;--line:rgba(236,232,246,.14);--bad:#FF5C7A;--warn:#FFC24B}
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;padding:28px 18px 60px;background:radial-gradient(900px 520px at 50% -10%,rgba(137,40,255,.35),transparent 62%),var(--bg);color:var(--txt);font:19px/1.55 'Space Grotesk',system-ui,sans-serif}
  main{width:100%;max-width:560px;margin:0 auto}
  .eyebrow{font-size:13px;letter-spacing:.16em;text-transform:uppercase;color:var(--verde);margin:6px 0 10px;font-weight:700}
  h1{font-size:clamp(30px,6vw,40px);line-height:1.1;margin:0 0 18px;letter-spacing:-.02em}
  p{margin:0 0 14px;color:var(--muted)}
  .box{background:var(--card);border:1px solid var(--line);border-radius:20px;padding:20px 22px;margin-bottom:18px}
  .box b{color:var(--txt)}
  .plan{display:flex;justify-content:space-between;gap:12px;align-items:baseline;flex-wrap:wrap;margin-bottom:6px}
  .plan strong{font-size:22px}
  .badge{display:inline-block;padding:3px 12px;border-radius:99px;font-size:15px;font-weight:700}
  .b-ok{background:rgba(0,239,158,.12);color:var(--verde)} .b-warn{background:rgba(255,194,75,.14);color:var(--warn)} .b-bad{background:rgba(255,92,122,.14);color:var(--bad)} .b-mute{background:rgba(164,157,184,.14);color:var(--muted)}
  .opts{display:grid;gap:14px}
  .opt{width:100%;text-align:left;cursor:pointer;background:var(--card);border:1px solid var(--line);border-radius:20px;padding:20px 22px;color:var(--txt);font:inherit;transition:.15s}
  .opt:hover{border-color:var(--verde);transform:translateY(-1px)}
  .opt strong{display:block;font-size:22px;margin-bottom:4px}
  .opt span{color:var(--muted);font-size:17px;display:block}
  .opt--pix{border-color:rgba(0,239,158,.5);box-shadow:0 0 40px rgba(0,239,158,.1)}
  .opt .tag{display:inline-block;font-size:13px;font-weight:700;color:#03140D;background:var(--verde);border-radius:99px;padding:2px 10px;margin-left:8px;vertical-align:middle}
  .qr{text-align:center}
  .qr img{width:min(280px,100%);height:auto;background:#fff;padding:12px;border-radius:16px;margin:6px auto 14px;display:block}
  .copy{display:flex;gap:8px;margin:10px 0}
  .copy input{flex:1;min-width:0;font:15px ui-monospace,Menlo,monospace;color:var(--txt);background:#0E0B17;border:1px solid var(--line);border-radius:12px;padding:12px}
  .btn{display:inline-flex;align-items:center;justify-content:center;min-height:52px;padding:12px 24px;border-radius:99px;background:var(--verde);color:#03140D;font:700 18px 'Space Grotesk',sans-serif;border:0;cursor:pointer;text-decoration:none}
  .btn--ghost{background:transparent;color:var(--txt);border:1px solid var(--line)}
  .alert{padding:14px 16px;border-radius:14px;margin-bottom:16px;font-size:17px;border:1px solid rgba(255,92,122,.4);background:rgba(255,92,122,.1);color:#ffc2cd}
  .ok{text-align:center;padding:26px 10px}
  .ok .tick{width:78px;height:78px;border-radius:50%;margin:0 auto 18px;display:grid;place-items:center;background:rgba(0,239,158,.12);box-shadow:0 0 0 1px rgba(0,239,158,.4),0 0 60px rgba(0,239,158,.25)}
  .ok svg{width:38px;height:38px;stroke:var(--verde);fill:none;stroke-width:3;stroke-linecap:round;stroke-linejoin:round}
  .small{font-size:16px}
  .foot{text-align:center;margin-top:26px;font-size:16px}
  .foot a{color:var(--roxo-claro)}
  @media(max-width:420px){.copy{flex-direction:column}.copy .btn{width:100%}}
</style>
</head>
<body>
<main>
<?php if ($fatal): ?>
  <p class="eyebrow">Site por assinatura</p><h1>Ops</h1><p><?= h($fatal) ?></p>
<?php elseif (!$c): ?>
  <p class="eyebrow">Site por assinatura</p>
  <h1>Link não encontrado</h1>
  <p>Esse link de pagamento não existe ou foi trocado. Fale comigo que eu te mando o certo.</p>
  <a class="btn" href="<?= h($wa) ?>">Falar com o Cassiano</a>
<?php else:
    $st = $c['status'];
    $badge = ['pendente' => ['Aguardando pagamento', 'warn'], 'ativo' => ['Em dia', 'ok'], 'atraso' => ['Em aberto', 'warn'], 'suspenso' => ['Site fora do ar', 'bad'], 'cancelado' => ['Cancelada', 'mute']][$st] ?? ['', 'mute']; ?>
  <p class="eyebrow">Site por assinatura</p>
  <h1>Olá, <?= h(first_name($c['name'])) ?></h1>

  <div class="box">
    <div class="plan"><strong><?= h($c['plan_name']) ?></strong><span class="badge b-<?= $badge[1] ?>"><?= h($badge[0]) ?></span></div>
    <p style="margin:0"><b><?= h(money((int) $c['price_cents'])) ?></b> por mês
      <?php if ($st === 'pendente'): ?><br>Escolha abaixo como pagar a primeira mensalidade.
      <?php elseif ($st === 'ativo'): ?><br>Tudo certo. Próximo vencimento em <b><?= h((string) $due) ?></b>.<?= has_card_auto($c) ? ' Cobrança automática no cartão.' : '' ?>
      <?php elseif ($st === 'atraso'): ?><br>Venceu em <b><?= h((string) $due) ?></b>. Pague até <b><?= h((string) $limit) ?></b> para o site não sair do ar.
      <?php elseif ($st === 'suspenso'): ?><br>Seu site está fora do ar. Pagou, volta na hora, <b>sem taxa nenhuma</b>.
      <?php else: ?><br>Esta assinatura foi cancelada. Fale comigo se quiser voltar.<?php endif; ?></p>
  </div>

  <?php if ($err): ?><p class="alert"><?= h($err) ?></p><?php endif; ?>

  <?php if ($st === 'cancelado'): ?>
    <a class="btn" href="<?= h($wa) ?>">Falar com o Cassiano</a>

  <?php elseif ($pix): ?>
    <div class="box qr" id="pixBox" data-t="<?= h($c['portal_token']) ?>" data-id="<?= (int) $pix['id'] ?>" data-value="<?= h((string) round($pix['amount_cents'] / 100, 2)) ?>">
      <div id="pixWait">
        <p style="color:var(--txt);margin-bottom:4px"><b>Pague <?= h(money((int) $pix['amount_cents'])) ?> com Pix</b></p>
        <?php if ($pix['qr_base64']): ?><img alt="QR Code do Pix" src="data:image/png;base64,<?= h($pix['qr_base64']) ?>"><?php endif; ?>
        <p class="small">Abra o app do seu banco, escolha <b>Pix</b> e <b>Ler QR Code</b>. Ou use o Pix <b>Copia e Cola</b>:</p>
        <div class="copy"><input id="pixcode" readonly value="<?= h($pix['qr_code']) ?>"><button type="button" class="btn" id="pixcopy">Copiar</button></div>
        <p class="small" style="margin:12px 0 0">Vale até <?= h(fmt_dt($pix['expires_at'])) ?>. Assim que você pagar, esta tela confirma sozinha e seu site é liberado.</p>
      </div>
      <div id="pixDone" class="ok" hidden>
        <div class="tick"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></div>
        <h1 style="font-size:32px">Pagamento confirmado!</h1>
        <p>Obrigado, <?= h(first_name($c['name'])) ?>. Está tudo certo com a sua assinatura. Te aviso por e-mail antes do próximo vencimento.</p>
      </div>
    </div>
    <form method="post"><input type="hidden" name="t" value="<?= h($c['portal_token']) ?>"><button class="btn btn--ghost" name="act" value="card">Prefiro pagar no cartão</button></form>

  <?php else: ?>
    <form method="post" class="opts">
      <input type="hidden" name="t" value="<?= h($c['portal_token']) ?>">
      <button class="opt opt--pix" name="act" value="pix" type="submit">
        <strong>Pagar com Pix <span class="tag">na hora</span></strong>
        <span>Gera o QR Code aqui mesmo. Pagou, seu site é liberado automaticamente. Todo mês você recebe um lembrete com o link.</span>
      </button>
      <?php if (!has_card_auto($c)): ?>
      <button class="opt" name="act" value="card" type="submit">
        <strong>Cartão de crédito</strong>
        <span>Cobrança automática todo mês, sem precisar lembrar. Pagamento seguro pelo Mercado Pago.</span>
      </button>
      <?php endif; ?>
    </form>
  <?php endif; ?>

  <p class="foot">Dúvida? <a href="<?= h($wa) ?>">Fale comigo no WhatsApp</a></p>
<?php endif; ?>
</main>
<script defer src="/api/tags.php"></script>
<script>
(function(){
  var b=document.getElementById('pixcopy'),i=document.getElementById('pixcode');
  if(b&&i)b.addEventListener('click',function(){
    var done=function(){var o=b.textContent;b.textContent='Copiado!';setTimeout(function(){b.textContent=o;},1800)};
    if(navigator.clipboard)navigator.clipboard.writeText(i.value).then(done,function(){i.select()});else{i.select();try{document.execCommand('copy');done()}catch(e){}}
  });
  var box=document.getElementById('pixBox'); if(!box)return;
  var t=box.getAttribute('data-t'),id=box.getAttribute('data-id'),tries=0,stop=false;
  function paid(){
    stop=true;document.getElementById('pixWait').hidden=true;document.getElementById('pixDone').hidden=false;window.scrollTo(0,0);
    var v=parseFloat(box.getAttribute('data-value'))||0;
    try{window.dataLayer=window.dataLayer||[];window.dataLayer.push({event:'Purchase',value:v,currency:'BRL'})}catch(e){}
    try{if(window.fbq)window.fbq('track','Purchase',{value:v,currency:'BRL'})}catch(e){}
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
</body>
</html>
