<?php
/** Tags da página /site-por-assinatura: padrão (Pixel que já estava no HTML) e leitura das configurações. */

declare(strict_types=1);

/** Sem tag padrão: o Pixel/GTM da página é o que você colar no painel (Rastreamento). */
function default_tags_head(): string
{
    return '';
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
