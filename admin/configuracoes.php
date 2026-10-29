<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/billing.php';
require __DIR__ . '/inc/migrate.php';
require __DIR__ . '/inc/layout.php';

$admin = require_login();
$cronLog = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = (string) ($_POST['act'] ?? '');
    try {
        switch ($act) {
            case 'mp_save':
                $tok = trim((string) ($_POST['mp_token'] ?? ''));
                $sec = trim((string) ($_POST['mp_secret'] ?? ''));
                if ($tok !== '') secret_setting_set('mp_access_token', $tok);
                if ($sec !== '') secret_setting_set('mp_webhook_secret', $sec);
                if (!mp_connected()) { flash('Cole o Access Token do Mercado Pago.', 'bad'); break; }
                $r = mp_whoami();
                if ($r['error'] !== '') flash('Salvei, mas o Mercado Pago recusou o token: ' . $r['error'], 'bad');
                else flash('Mercado Pago conectado: ' . ($r['body']['email'] ?? $r['body']['nickname'] ?? 'conta ok') . '.');
                break;
            case 'mp_test':
                $r = mp_whoami();
                flash($r['error'] !== '' ? 'Falhou: ' . $r['error'] : 'Conexão ok: ' . ($r['body']['email'] ?? $r['body']['nickname'] ?? 'conta ok') . '.', $r['error'] !== '' ? 'bad' : 'ok');
                break;
            case 'mp_disconnect':
                setting_set('mp_access_token', '');
                setting_set('mp_webhook_secret', '');
                flash('Mercado Pago desconectado.', 'warn');
                break;
            case 'email_save':
                $ae = clean_text($_POST['admin_email'] ?? '', 160);
                if ($ae !== '' && !filter_var($ae, FILTER_VALIDATE_EMAIL)) { flash('E-mail inválido.', 'bad'); break; }
                setting_set('admin_email', $ae);
                setting_set('notify_customer', !empty($_POST['notify_customer']) ? '1' : '0');
                setting_set('notify_admin', !empty($_POST['notify_admin']) ? '1' : '0');
                flash('Preferências de e-mail salvas.');
                break;
            case 'email_test':
                $to = setting('admin_email', env('MAIL_TO')) ?: $admin['email'];
                $html = email_layout('Teste de e-mail', '<p style="margin:0">Se você está lendo isto, a Brevo está enviando direitinho pelo sistema de assinaturas.</p>', ['label' => 'Abrir o painel', 'url' => app_url() . '/admin/']);
                [$ok, $err] = brevo_send($to, $admin['name'], 'Teste do sistema de assinaturas', $html, email_to_text($html), ['teste']);
                flash($ok ? "E-mail de teste enviado para $to." : 'Falhou: ' . $err, $ok ? 'ok' : 'bad');
                break;
            case 'rules_save':
                $tol = max(0, min(15, (int) ($_POST['tolerance_days'] ?? 1)));
                $rem = array_filter(array_map('trim', explode(',', (string) ($_POST['reminder_days'] ?? ''))), fn($x) => ctype_digit($x) && (int) $x <= 60);
                setting_set('tolerance_days', (string) $tol);
                setting_set('reminder_days', implode(',', $rem) ?: '7,3,0');
                $wa = preg_replace('/\D/', '', (string) ($_POST['admin_whatsapp'] ?? ''));
                if (strlen($wa) >= 10) setting_set('admin_whatsapp', phone_digits($wa));
                flash('Regras salvas.');
                break;
            case 'plans_save':
                foreach (q_all('SELECT id FROM plans') as $p) {
                    $pid = (int) $p['id'];
                    $name = clean_text($_POST['name'][$pid] ?? '', 60);
                    $price = (int) round(((float) str_replace(',', '.', str_replace('.', '', (string) ($_POST['price'][$pid] ?? '0')))) * 100);
                    if ($name === '' || $price <= 0) continue;
                    q('UPDATE plans SET name = ?, price_cents = ?, active = ? WHERE id = ?', [$name, $price, !empty($_POST['active'][$pid]) ? 1 : 0, $pid]);
                }
                flash('Planos salvos. Quem já assina continua com o valor que tem.');
                break;
            case 'cron_run':
                $cronLog = run_daily();
                setting_set('cron_last_run', date('Y-m-d H:i:s'));
                setting_set('cron_last_log', implode("\n", $cronLog));
                flash('Rotina executada agora.');
                break;
            case 'pass_change':
                $a = q_one('SELECT * FROM admins WHERE id = ?', [(int) $admin['id']]);
                $new = (string) ($_POST['new'] ?? '');
                if (!$a || !password_verify((string) ($_POST['current'] ?? ''), $a['password_hash'])) { flash('Senha atual incorreta.', 'bad'); break; }
                if (strlen($new) < 10) { flash('A nova senha precisa ter 10+ caracteres.', 'bad'); break; }
                q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), (int) $admin['id']]);
                flash('Senha alterada.');
                break;
            case 'admin_add':
                $n = clean_text($_POST['name'] ?? '', 80);
                $e = mb_strtolower(clean_text($_POST['email'] ?? '', 160));
                $pw = (string) ($_POST['password'] ?? '');
                if ($n === '' || !filter_var($e, FILTER_VALIDATE_EMAIL) || strlen($pw) < 10) { flash('Informe nome, e-mail e senha (10+ caracteres).', 'bad'); break; }
                if (q_val('SELECT COUNT(*) FROM admins WHERE email = ?', [$e])) { flash('Já existe um acesso com esse e-mail.', 'bad'); break; }
                q('INSERT INTO admins (name, email, password_hash, created_at) VALUES (?, ?, ?, NOW())', [$n, $e, password_hash($pw, PASSWORD_DEFAULT)]);
                flash('Novo acesso criado.');
                break;
        }
    } catch (Throwable $e) {
        flash('Erro: ' . $e->getMessage(), 'bad');
    }
    if ($cronLog === null) redirect('configuracoes.php');
}

$plans = q_all('SELECT * FROM plans ORDER BY sort');
$token = '';
$tokenErr = '';
try { $token = mp_token(); } catch (Throwable $e) { $tokenErr = $e->getMessage(); }
$who = null;
if ($token !== '' && ($_GET['check'] ?? '') === '1') $who = mp_whoami();
$webhookUrl = app_url() . '/api/mp-webhook.php';
$lastHooks = q_all('SELECT * FROM webhook_log ORDER BY id DESC LIMIT 6');
$pendingMig = count(migrations_pending());
$cronCmd = '/usr/local/bin/php ' . ROOT_DIR . '/admin/cron.php';
$cronUrl = env('CRON_KEY') !== '' ? app_url() . '/admin/cron.php?key=' . env('CRON_KEY') : '';
$brevoOk = env('BREVO_API_KEY') !== '';

page_start('Configurações', 'configuracoes', $admin);
?>
<div class="head"><div><p class="eyebrow">Ajustes do sistema</p><h1>Configurações</h1></div></div>

<?php if ($cronLog): ?><div class="card"><h2>Resultado da rotina</h2><div class="code-block"><?= h(implode("\n", $cronLog)) ?></div></div><?php endif; ?>

<!-- Mercado Pago -->
<div class="card" id="mp">
  <h2>Mercado Pago <?= $token !== '' ? '<span class="badge badge--ok">conectado</span>' : '<span class="badge badge--warn">não conectado</span>' ?>
    <?= ($token !== '' && mp_is_test_token($token)) ? '<span class="badge badge--warn">modo teste</span>' : '' ?></h2>
  <?php if ($tokenErr): ?><p class="alert alert--bad"><?= h($tokenErr) ?> Defina <code>APP_KEY</code> no .env.</p><?php endif; ?>
  <?php if ($who): ?><p class="alert alert--<?= $who['error'] === '' ? 'ok' : 'bad' ?>"><?= $who['error'] === '' ? 'Conta: ' . h(($who['body']['nickname'] ?? '') . ' ' . ($who['body']['email'] ?? '')) : h($who['error']) ?></p><?php endif; ?>

  <details class="card" style="background:var(--card-2)">
    <summary>Passo a passo para conectar (leva 5 minutos)</summary>
    <ol class="small" style="display:grid;gap:8px;padding-left:20px;margin:14px 0 0">
      <li>Entre em <b>mercadopago.com.br/developers</b> com a sua conta e vá em <b>Suas integrações</b>. Clique em <b>Criar aplicação</b> (nome livre, ex.: "Site por assinatura"; tipo <b>Pagamentos online</b>).</li>
      <li>Abra a aplicação, vá em <b>Credenciais de produção</b> e copie o <b>Access Token</b> (começa com <code>APP_USR-</code>). Cole no campo abaixo.</li>
      <li>Em <b>Webhooks</b> (ou "Notificações"), escolha <b>modo produção</b>, cole a URL abaixo e marque os eventos <b>Planos e assinaturas</b> (cartão recorrente) e <b>Pagamentos</b> (Pix). Salve.</li>
      <li>O Mercado Pago mostra uma <b>assinatura secreta</b> do webhook. Copie e cole no segundo campo (protege contra avisos falsos).</li>
      <li>Clique em <b>Salvar e testar</b>. Se aparecer "conectado", está pronto.</li>
    </ol>
  </details>

  <p class="lbl" style="margin-top:6px">URL do webhook (cole no Mercado Pago)</p>
  <div class="copy" style="margin-bottom:16px"><input id="whurl" readonly value="<?= h($webhookUrl) ?>"><button type="button" class="btn" data-copy="whurl">Copiar</button></div>

  <form method="post" class="form" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="act" value="mp_save">
    <label>Access Token <input type="password" name="mp_token" placeholder="<?= $token !== '' ? '•••••••• guardado (deixe vazio para manter)' : 'APP_USR-...' ?>" autocomplete="off"></label>
    <label>Assinatura secreta do webhook <input type="password" name="mp_secret" placeholder="<?= setting('mp_webhook_secret') !== '' ? '•••••••• guardada (deixe vazio para manter)' : 'opcional, mas recomendado' ?>" autocomplete="off"></label>
    <div class="actions">
      <button class="btn btn--primary">Salvar e testar</button>
      <?php if ($token !== ''): ?><a class="btn" href="configuracoes.php?check=1#mp">Testar conexão</a><?php endif; ?>
    </div>
  </form>
  <?php if ($token !== ''): ?>
    <form method="post" data-confirm="Desconectar o Mercado Pago? Os links já gerados continuam valendo, mas o painel para de falar com ele." style="margin-top:12px"><?= csrf_field() ?><input type="hidden" name="act" value="mp_disconnect"><button class="btn btn--sm btn--danger">Desconectar</button></form>
  <?php endif; ?>

  <?php if ($lastHooks): ?>
    <hr><p class="lbl">Últimos avisos recebidos do Mercado Pago</p>
    <div class="table-wrap"><table class="t stack"><tbody>
    <?php foreach ($lastHooks as $w): ?>
      <tr><td data-l="Quando"><?= h(fmt_dt($w['created_at'])) ?></td><td data-l="Tipo"><?= h($w['topic']) ?></td><td data-l="Resultado"><span class="badge badge--<?= $w['result'] === 'ok' ? 'ok' : 'bad' ?>"><?= h($w['result']) ?></span></td><td data-l="Nota" class="small muted"><?= h($w['note']) ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>

<!-- E-mails -->
<div class="card" id="email">
  <h2>E-mails (Brevo) <?= $brevoOk ? '<span class="badge badge--ok">chave encontrada</span>' : '<span class="badge badge--bad">sem BREVO_API_KEY</span>' ?></h2>
  <p class="small muted">A chave da Brevo e o remetente ficam no <code>.env</code> (os mesmos do formulário do site). Aqui você escolhe quem recebe os avisos e o que é enviado.</p>
  <form method="post" class="form">
    <?= csrf_field() ?><input type="hidden" name="act" value="email_save">
    <label>E-mail que recebe os avisos (novo lead, "fulano não pagou", cancelamento)
      <input type="email" name="admin_email" value="<?= h(setting('admin_email', env('MAIL_TO'))) ?>"></label>
    <label class="check"><input type="checkbox" name="notify_customer" value="1" <?= setting('notify_customer', '1') === '1' ? 'checked' : '' ?>> Enviar lembretes e avisos ao cliente (7, 3 dias, vencimento, atraso, suspensão)</label>
    <label class="check"><input type="checkbox" name="notify_admin" value="1" <?= setting('notify_admin', '1') === '1' ? 'checked' : '' ?>> Enviar avisos para mim</label>
    <div class="actions"><button class="btn btn--roxo">Salvar</button></div>
  </form>
  <form method="post" style="margin-top:12px"><?= csrf_field() ?><input type="hidden" name="act" value="email_test"><button class="btn btn--sm">Enviar e-mail de teste</button></form>
</div>

<!-- Regras -->
<div class="card" id="regras">
  <h2>Regras de cobrança</h2>
  <form method="post" class="form">
    <?= csrf_field() ?><input type="hidden" name="act" value="rules_save">
    <div class="row3">
      <label>Dias de tolerância <input type="number" name="tolerance_days" min="0" max="15" value="<?= tolerance_days() ?>"></label>
      <label>Lembretes (dias antes, separados por vírgula) <input name="reminder_days" value="<?= h(implode(',', reminder_days())) ?>"></label>
      <label>Seu WhatsApp (nos e-mails) <input name="admin_whatsapp" value="<?= h(admin_wa()) ?>"></label>
    </div>
    <p class="small muted" style="margin:0">Hoje: lembrete <?= h(implode(', ', reminder_days())) ?> dia(s) antes (0 = no dia). Passou do vencimento + <?= tolerance_days() ?> dia(s) de tolerância sem pagar, o cliente é suspenso e você recebe o aviso "fulano não pagou". Se ele pagar depois, volta sozinho, sem taxa.</p>
    <div><button class="btn btn--roxo">Salvar regras</button></div>
  </form>
</div>

<!-- Planos -->
<div class="card" id="planos">
  <h2>Planos</h2>
  <form method="post" class="form">
    <?= csrf_field() ?><input type="hidden" name="act" value="plans_save">
    <?php foreach ($plans as $p): ?>
      <div class="row3">
        <label>Nome <input name="name[<?= $p['id'] ?>]" value="<?= h($p['name']) ?>" maxlength="60"></label>
        <label>Valor mensal (R$) <input name="price[<?= $p['id'] ?>]" value="<?= h(number_format($p['price_cents'] / 100, 2, ',', '.')) ?>" inputmode="decimal"></label>
        <label class="check" style="align-self:end;min-height:46px"><input type="checkbox" name="active[<?= $p['id'] ?>]" value="1" <?= $p['active'] ? 'checked' : '' ?>> Ativo</label>
      </div>
    <?php endforeach; ?>
    <p class="small muted" style="margin:0">Mudar o valor aqui vale para novos clientes. Quem já assina mantém o preço (dá para ajustar no cadastro de cada cliente). O preço que aparece na página pública é editado no HTML da página.</p>
    <div><button class="btn btn--roxo">Salvar planos</button></div>
  </form>
</div>

<!-- Cron -->
<div class="card" id="cron">
  <h2>Rotina diária <?= setting('cron_last_run') !== '' ? '<span class="badge badge--ok">última: ' . h(fmt_dt(setting('cron_last_run'))) . '</span>' : '<span class="badge badge--warn">nunca rodou</span>' ?></h2>
  <p class="small muted">Todo dia ela confere o Mercado Pago, manda os lembretes, aplica a tolerância e suspende quem não pagou. Agende no cPanel em <b>Tarefas Cron</b>, uma vez por dia (ex.: 9h).</p>
  <p class="lbl">Comando (cPanel > Tarefas Cron)</p>
  <div class="copy" style="margin-bottom:12px"><input id="cmd" readonly value="<?= h($cronCmd) ?>"><button type="button" class="btn" data-copy="cmd">Copiar</button></div>
  <?php if ($cronUrl): ?>
    <p class="lbl">Ou por URL (se o seu cron só aceitar URL)</p>
    <div class="copy" style="margin-bottom:12px"><input id="cronurl" readonly value="<?= h($cronUrl) ?>"><button type="button" class="btn" data-copy="cronurl">Copiar</button></div>
  <?php else: ?>
    <p class="small muted">Para usar por URL, defina <code>CRON_KEY</code> no .env.</p>
  <?php endif; ?>
  <?php if (setting('cron_last_log') !== ''): ?><div class="code-block" style="margin-bottom:12px"><?= h(setting('cron_last_log')) ?></div><?php endif; ?>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="cron_run"><button class="btn btn--sm">Rodar agora</button></form>
</div>

<!-- Conta -->
<div class="grid grid--2">
  <div class="card" id="conta">
    <h2>Minha senha</h2>
    <form method="post" class="form" autocomplete="off">
      <?= csrf_field() ?><input type="hidden" name="act" value="pass_change">
      <label>Senha atual <input type="password" name="current" required autocomplete="current-password"></label>
      <label>Nova senha (10+ caracteres) <input type="password" name="new" required minlength="10" autocomplete="new-password"></label>
      <div><button class="btn btn--roxo">Trocar senha</button></div>
    </form>
  </div>
  <div class="card">
    <h2>Dar acesso a outra pessoa</h2>
    <form method="post" class="form" autocomplete="off">
      <?= csrf_field() ?><input type="hidden" name="act" value="admin_add">
      <label>Nome <input name="name" required maxlength="80"></label>
      <label>E-mail <input type="email" name="email" required maxlength="160"></label>
      <label>Senha inicial (10+ caracteres) <input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
      <div><button class="btn btn--roxo">Criar acesso</button></div>
    </form>
  </div>
</div>

<div class="card">
  <h2>Banco de dados</h2>
  <?php if ($pendingMig): ?>
    <p class="alert alert--warn"><?= $pendingMig ?> atualização(ões) do banco pendente(s). <a href="install.php">Aplicar agora</a>.</p>
  <?php else: ?>
    <p class="small muted" style="margin:0">Banco em dia. Depois de cada deploy que traga atualizações, abra <a href="install.php">install.php</a> para aplicar as novas migrações.</p>
  <?php endif; ?>
</div>
<?php page_end();
