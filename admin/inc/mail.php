<?php
/** E-mails pela API da Brevo + layout padrão dos e-mails do sistema. */

declare(strict_types=1);

/** @return array{0:bool,1:?string} [ok, erro] */
function brevo_send(string $toEmail, string $toName, string $subject, string $html, string $text = '', array $tags = [], ?array $replyTo = null): array
{
    $apiKey = env('BREVO_API_KEY');
    if ($apiKey === '') return [false, 'BREVO_API_KEY não configurada no .env'];
    $from = env('MAIL_FROM', env('MAIL_TO'));
    if ($from === '') return [false, 'MAIL_FROM não configurado no .env'];

    $payload = [
        'sender'      => ['name' => env('MAIL_FROM_NAME', 'Cassiano Galvão'), 'email' => $from],
        'to'          => [['email' => $toEmail, 'name' => $toName !== '' ? $toName : $toEmail]],
        'subject'     => $subject,
        'htmlContent' => $html,
    ];
    if ($text !== '') $payload['textContent'] = $text;
    if ($tags) $payload['tags'] = $tags;
    if ($replyTo) $payload['replyTo'] = $replyTo;

    $ch = curl_init(env('BREVO_API_URL', 'https://api.brevo.com/v3/smtp/email'));
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['accept: application/json', 'content-type: application/json', 'api-key: ' . $apiKey],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $res = curl_exec($ch);
    $st = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($st >= 200 && $st < 300) return [true, null];
    $msg = $err !== '' ? $err : (string) $res;
    error_log('[assinaturas] Brevo ' . $st . ': ' . $msg);
    return [false, 'Brevo ' . $st . ': ' . mb_substr($msg, 0, 160)];
}

function email_layout(string $title, string $bodyHtml, ?array $cta = null, string $small = ''): string
{
    $btn = '';
    if ($cta) {
        $btn = '<p style="margin:26px 0 4px"><a href="' . h($cta['url']) . '" style="display:inline-block;background:#00EF9E;color:#03140D;font-weight:bold;font-size:16px;padding:14px 26px;border-radius:999px;text-decoration:none">' . h($cta['label']) . '</a></p>';
    }
    return '<div style="background:#f3f1f8;padding:24px 12px;font-family:Arial,Helvetica,sans-serif">'
        . '<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;color:#1d1a29">'
        . '<div style="padding:22px 28px;background:linear-gradient(135deg,#8928FF,#3A0F86)">'
        . '<p style="margin:0;font-size:12px;letter-spacing:2px;color:#00EF9E;font-weight:bold">SITE POR ASSINATURA</p>'
        . '<h1 style="margin:8px 0 0;font-size:22px;line-height:1.25;color:#ffffff">' . h($title) . '</h1></div>'
        . '<div style="padding:26px 28px 28px;font-size:16px;line-height:1.6">' . $bodyHtml . $btn . '</div>'
        . '<div style="padding:0 28px 24px;font-size:12px;color:#7b7590">Cassiano Galvão · cassianogalvao.com.br' . ($small !== '' ? '<br>' . $small : '') . '</div>'
        . '</div></div>';
}

function email_to_text(string $html): string
{
    $t = preg_replace('/<br\s*\/?>|<\/p>|<\/h1>|<\/tr>/i', "\n", $html);
    $t = preg_replace('/<a [^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is', '$2 ($1)', (string) $t);
    return trim(html_entity_decode(strip_tags((string) $t), ENT_QUOTES, 'UTF-8'));
}
