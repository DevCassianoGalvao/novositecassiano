<?php
/** Tags da página /site-por-assinatura: padrão (Pixel que já estava no HTML) e leitura das configurações. */

declare(strict_types=1);

function default_tags_head(): string
{
    return <<<'HTML'
<!-- Meta Pixel Code -->
<script>
/* fila do pixel na hora; o script da Meta só carrega depois da página (não pesa no carregamento) */
!function(f){if(f.fbq)return;var n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[]}(window);
fbq('init', '987681871022914');
fbq('track', 'PageView');
(function(){var done=0,go=function(){if(done)return;done=1;
var t=document.createElement('script');t.async=!0;
t.src='https://connect.facebook.net/en_US/fbevents.js';document.head.appendChild(t);};
['scroll','pointerdown','touchstart','keydown','mousemove'].forEach(function(e){addEventListener(e,go,{once:true,passive:true});});
if(document.readyState==='complete')setTimeout(go,4000);else addEventListener('load',function(){setTimeout(go,4000);});})();
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
