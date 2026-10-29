<?php
/**
 * Briefing do site: o cliente responde depois do primeiro pagamento.
 * Acesso pelo mesmo link único (token) da página de pagamento.
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

$wa = wa_link($c ? admin_wa() : '5522997560717', 'Oi, Cassiano! Preciso de ajuda com o briefing do meu site.');
$fields = briefing_fields();
$saved = null;
$err = null;
$done = false;

if ($c && !in_array($c['status'], ['pendente', 'cancelado'], true)) {
    try {
        $saved = briefing_find((int) $c['id']);
    } catch (Throwable $e) {
        $fatal = 'Sistema indisponível no momento. Tente de novo em instantes.';
    }
    if (!$fatal && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $data = [];
        foreach ($fields as $k => [$label, $type, $req]) {
            $v = (string) ($_POST[$k] ?? '');
            $v = $type === 'area'
                ? mb_substr(trim(strip_tags(str_replace("\r", '', $v))), 0, 2000)
                : clean_text($v, 300);
            if ($req && $v === '') { $err = 'Preencha: ' . $label . '.'; break; }
            $data[$k] = $v;
        }
        if (!$err) {
            try {
                $json = json_encode($data, JSON_UNESCAPED_UNICODE);
                if ($saved) {
                    q('UPDATE briefings SET data = ?, updated_at = NOW() WHERE customer_id = ?', [$json, (int) $c['id']]);
                } else {
                    q('INSERT INTO briefings (customer_id, data, created_at, updated_at) VALUES (?, ?, NOW(), NOW())', [(int) $c['id'], $json]);
                }
                log_activity('briefing', $saved ? 'Briefing atualizado pelo cliente' : 'Briefing enviado pelo cliente', (int) $c['id']);
                notify_admin($c['name'] . ' enviou o briefing do site',
                    '<p style="margin:0 0 14px">Briefing ' . ($saved ? 'atualizado' : 'recebido') . ' (' . h($c['plan_name']) . '). As respostas estão na ficha do cliente.</p>' . admin_summary_html($c),
                    ['label' => 'Ver o briefing no painel', 'url' => customer_url((int) $c['id'])]);
                $saved = briefing_find((int) $c['id']);
                $done = true;
            } catch (Throwable $e) {
                error_log('[briefing] ' . $e->getMessage());
                $err = 'Não consegui salvar agora. Tente de novo em instantes.';
            }
        }
    }
}
$val = fn(string $k): string => (string) ($_POST[$k] ?? ($saved['fields'][$k] ?? ''));
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Briefing do seu site · Cassiano Galvão</title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#07060B">
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&display=swap">
<style>
  :root{--bg:#07060B;--card:#120F1D;--roxo:#8928FF;--roxo-claro:#A75FFF;--verde:#00EF9E;--txt:#ECE8F6;--muted:#A49DB8;--line:rgba(236,232,246,.14);--bad:#FF5C7A}
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;padding:28px 18px 60px;background:radial-gradient(900px 520px at 50% -10%,rgba(137,40,255,.35),transparent 62%),var(--bg);color:var(--txt);font:19px/1.55 'Space Grotesk',system-ui,sans-serif}
  main{width:100%;max-width:620px;margin:0 auto}
  .eyebrow{font-size:13px;letter-spacing:.16em;text-transform:uppercase;color:var(--verde);margin:6px 0 10px;font-weight:700}
  h1{font-size:clamp(30px,6vw,40px);line-height:1.1;margin:0 0 14px;letter-spacing:-.02em}
  p{margin:0 0 14px;color:var(--muted)}
  form{display:grid;gap:18px;margin-top:22px}
  label{display:grid;gap:6px;color:var(--txt);font-weight:500}
  label small{color:var(--muted);font-weight:400;font-size:15px}
  input,textarea,select{width:100%;font:inherit;font-size:18px;color:var(--txt);background:#0E0B17;border:1px solid var(--line);border-radius:14px;padding:13px 14px}
  textarea{min-height:104px;resize:vertical}
  input:focus,textarea:focus,select:focus{outline:2px solid var(--roxo-claro);outline-offset:1px;border-color:transparent}
  .req{color:var(--verde)}
  .btn{display:inline-flex;align-items:center;justify-content:center;min-height:54px;padding:12px 26px;border-radius:99px;background:var(--verde);color:#03140D;font:700 18px 'Space Grotesk',sans-serif;border:0;cursor:pointer;text-decoration:none}
  .aviso{padding:14px 16px;border-radius:14px;margin:16px 0 0;font-size:17px;border:1px solid rgba(255,92,122,.4);background:rgba(255,92,122,.1);color:#ffc2cd}
  .okmsg{padding:14px 16px;border-radius:14px;margin:16px 0 0;font-size:17px;border:1px solid rgba(0,239,158,.4);background:rgba(0,239,158,.08);color:#b9ffe3}
  .foot{text-align:center;margin-top:26px;font-size:16px}
  .foot a{color:var(--roxo-claro)}
</style>
</head>
<body>
<main>
<?php if ($fatal): ?>
  <p class="eyebrow">Site por assinatura</p><h1>Ops</h1><p><?= h($fatal) ?></p>
<?php elseif (!$c): ?>
  <p class="eyebrow">Site por assinatura</p>
  <h1>Link não encontrado</h1>
  <p>Esse link não existe ou foi trocado. Fale comigo que eu te mando o certo.</p>
  <a class="btn" href="<?= h($wa) ?>">Falar com o Cassiano</a>
<?php elseif ($c['status'] === 'pendente'): ?>
  <p class="eyebrow">Site por assinatura</p>
  <h1>Falta o pagamento</h1>
  <p>O briefing abre assim que o primeiro pagamento for confirmado.</p>
  <a class="btn" href="<?= h(pay_url($c)) ?>">Ir para o pagamento</a>
<?php elseif ($c['status'] === 'cancelado'): ?>
  <p class="eyebrow">Site por assinatura</p>
  <h1>Assinatura cancelada</h1>
  <p>Fale comigo se quiser voltar.</p>
  <a class="btn" href="<?= h($wa) ?>">Falar com o Cassiano</a>
<?php else: ?>
  <p class="eyebrow">Briefing do seu site</p>
  <h1><?= $saved ? 'Briefing enviado' : 'Conta pra mim do seu negócio' ?></h1>
  <p><?php if ($saved): ?>Recebi suas respostas. Se lembrar de algo, é só ajustar abaixo e enviar de novo.<?php else: ?>Leva uns 5 minutos. Responda do jeito que vier, não precisa estar perfeito. Só o que tem <span class="req">*</span> é obrigatório.<?php endif; ?></p>
  <?php if ($done): ?><p class="okmsg">Pronto, recebi! Vou analisar e te chamo no WhatsApp para confirmar os próximos passos.</p><?php endif; ?>
  <?php if ($err): ?><p class="aviso"><?= h($err) ?></p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="t" value="<?= h($c['portal_token']) ?>">
    <?php foreach ($fields as $k => [$label, $type, $req, $hint]): ?>
      <label><span><?= h($label) ?><?= $req ? ' <span class="req">*</span>' : '' ?></span>
        <?php if ($type === 'area'): ?><textarea name="<?= h($k) ?>" maxlength="2000"<?= $req ? ' required' : '' ?>><?= h($val($k)) ?></textarea>
        <?php elseif ($type === 'select'): ?><select name="<?= h($k) ?>"><option value="">Escolha...</option><?php foreach (explode('|', $hint) as $o): ?><option<?= $val($k) === $o ? ' selected' : '' ?>><?= h($o) ?></option><?php endforeach; ?></select>
        <?php else: ?><input name="<?= h($k) ?>" maxlength="300" value="<?= h($val($k)) ?>"<?= $req ? ' required' : '' ?>><?php endif; ?>
        <?php if ($hint && $type !== 'select'): ?><small><?= h($hint) ?></small><?php endif; ?>
      </label>
    <?php endforeach; ?>
    <button class="btn" type="submit"><?= $saved ? 'Enviar de novo' : 'Enviar briefing' ?></button>
  </form>
  <p class="foot">Dúvida? <a href="<?= h($wa) ?>">Fale comigo no WhatsApp</a></p>
<?php endif; ?>
</main>
</body>
</html>
