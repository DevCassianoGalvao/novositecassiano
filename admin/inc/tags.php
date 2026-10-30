<?php
/** Tags da página /site-por-assinatura: padrão (Pixel que já estava no HTML) e leitura das configurações. */

declare(strict_types=1);

/** Pixel padrão das páginas do site por assinatura (o que estiver salvo no painel tem prioridade). */
function default_tags_head(): string
{
    return <<<'HTML'
<!-- Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '1865202664841697');
fbq('track', 'PageView');
</script>
<!-- End Meta Pixel Code -->
HTML;
}

/** @return array{head:string, body:string, enabled:bool, configured:bool} */
function tags_config(): array
{
    $configured = setting_has('tags_configured');
    if (!$configured) return ['head' => default_tags_head(), 'body' => '', 'enabled' => true, 'configured' => false];
    return [
        'head'       => setting('tags_head'),
        'body'       => setting('tags_body'),
        'enabled'    => setting('tags_enabled', '1') === '1',
        'configured' => true,
    ];
}
