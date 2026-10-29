<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/billing.php';
require __DIR__ . '/inc/layout.php';
require __DIR__ . '/inc/tags.php';

$admin = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = (string) ($_POST['act'] ?? 'save');
    if ($act === 'reset') {
        setting_set('tags_head', default_tags_head());
        setting_set('tags_body', '');
        setting_set('tags_enabled', '1');
        setting_set('tags_configured', '1');
        flash('Voltou para o Pixel padrão (987681871022914).');
    } else {
        setting_set('tags_head', mb_substr((string) ($_POST['head'] ?? ''), 0, 30000));
        setting_set('tags_body', mb_substr((string) ($_POST['body'] ?? ''), 0, 30000));
        setting_set('tags_enabled', !empty($_POST['enabled']) ? '1' : '0');
        setting_set('tags_configured', '1');
        flash('Tags salvas. Em até 2 minutos já valem na página.');
    }
    redirect('rastreamento.php');
}

$cfg = tags_config();
page_start('Rastreamento', 'rastreamento', $admin);
?>
<?php page_head('Rastreamento', 'Pixel da Meta, Google Tag Manager e outras tags. Valem só nas páginas do site por assinatura.'); ?>

<?php if (!$cfg['configured']): ?>
  <p class="aviso aviso--warn"><?= ic('alert') ?><span>Você ainda não salvou nada aqui. Enquanto isso, a página continua usando o Pixel padrão da Meta (987681871022914), que aparece abaixo. Ao salvar, o que estiver nas caixas passa a valer.</span></p>
<?php endif; ?>

<form method="post" class="card form">
  <?= card_head('Suas tags', 'code', $cfg['enabled'] ? '<span class="badge badge--ok">ligadas</span>' : '<span class="badge badge--mute">desligadas</span>') ?>
  <?= csrf_field() ?><input type="hidden" name="act" value="save">
  <label class="check"><input type="checkbox" name="enabled" value="1" <?= $cfg['enabled'] ? 'checked' : '' ?>> Tags ligadas (desmarque para tirar todas da página)</label>

  <label>Código para o <code>&lt;head&gt;</code> (Pixel da Meta, Google Tag Manager, GA4, outras tags)
    <textarea name="head" rows="12" spellcheck="false" placeholder="Cole aqui o código exatamente como a plataforma fornece, com as tags &lt;script&gt;"><?= h($cfg['head']) ?></textarea></label>

  <label>Código para o início do <code>&lt;body&gt;</code> (opcional, ex.: parte 2 do Google Tag Manager)
    <textarea name="body" rows="5" spellcheck="false" placeholder="Opcional"><?= h($cfg['body']) ?></textarea></label>

  <div class="actions"><button class="btn btn--primary"><?= ic('tick') ?><span>Salvar tags</span></button></div>
</form>

<div class="card">
  <?= card_head('Como funciona', 'eye') ?>
  <ul class="small muted" style="margin:0;padding-left:20px;display:grid;gap:6px">
    <li>As tags valem na página <code>/site-por-assinatura/</code>, na de obrigado e nas páginas de pagamento e de briefing do cliente. A home e o resto do site não mudam.</li>
    <li>Nas páginas de pagamento e de briefing, o link pessoal do cliente é tirado do endereço antes das tags carregarem, para não ir para a Meta ou o Google.</li>
    <li>Pode colar mais de uma tag (Pixel + Tag Manager + o que precisar). Cole o código inteiro, com <code>&lt;script&gt;</code>.</li>
    <li>A página já dispara os eventos <b>PageView</b>, <b>ViewContent</b> (chegou nos planos), <b>Lead</b> (enviou o formulário do plano), <b>InitiateCheckout</b> (foi para o pagamento), <b>Purchase</b> (Pix confirmado) e <b>Contact</b> (clicou no WhatsApp). Pelo Pixel eles funcionam sozinhos.</li>
    <li>Quem usa o Tag Manager recebe os mesmos eventos na <code>dataLayer</code> (<code>Lead</code>, <code>ViewContent</code>, <code>Contact</code>).</li>
    <li>Se você colar um Pixel novo, remova o antigo da caixa para não contar a visita duas vezes.</li>
  </ul>
  <form method="post" data-confirm="Voltar para o Pixel padrão e apagar o que está nas caixas?" style="margin-top:14px"><?= csrf_field() ?><input type="hidden" name="act" value="reset"><button class="btn btn--sm"><?= ic('refresh') ?><span>Restaurar o Pixel padrão</span></button></form>
</div>
<?php page_end();
