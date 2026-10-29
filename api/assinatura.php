<?php
/**
 * Lead do pop-up de planos da página /site-por-assinatura (fase 1).
 * Envia os dados por e-mail pela Brevo, com o mesmo .env do site.
 *
 * TODO (fase 2): substituir/complementar pelo webhook de leads e pelo checkout.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

function load_env(): void
{
    foreach ([dirname(__DIR__, 2) . '/.env', dirname(__DIR__) . '/.env'] as $file) {
        if (!is_readable($file)) continue;
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $value = trim($value, "\"'");
            if (getenv($key) === false) putenv("$key=$value");
        }
        return;
    }
}

function env(string $key, string $default = ''): string
{
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

load_env();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') respond(405, ['ok' => false, 'error' => 'Método não permitido.']);

$allowed = array_filter(array_map('trim', explode(',', env('ALLOWED_ORIGINS'))));
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($allowed && $origin !== '' && !in_array($origin, $allowed, true)) respond(403, ['ok' => false, 'error' => 'Origem não autorizada.']);

$data = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($data)) $data = $_POST;
if (!empty($data['website'])) respond(200, ['ok' => true]); // honeypot

/* limite simples por IP: 5 envios a cada 10 min */
$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'x';
$bucket = sys_get_temp_dir() . '/cg_assin_' . md5($ip);
$now = time();
$hits = is_readable($bucket) ? array_filter((array) json_decode((string) file_get_contents($bucket), true), fn($t) => is_int($t) && $t > $now - 600) : [];
if (count($hits) >= 5) respond(429, ['ok' => false, 'error' => 'Muitos envios. Tente de novo em alguns minutos.']);
$hits[] = $now;
@file_put_contents($bucket, json_encode(array_values($hits)), LOCK_EX);

$clean = fn($v, int $max) => mb_substr(trim(strip_tags((string) $v)), 0, $max);
$planos = ['essencial' => 'Plano Essencial · R$ 67/mês', 'completo' => 'Plano Completo · R$ 97/mês'];

$nome     = $clean($data['nome'] ?? '', 80);
$whatsapp = preg_replace('/\D/', '', (string) ($data['whatsapp'] ?? ''));
$email    = $clean($data['email'] ?? '', 120);
$plano    = $clean($data['plano'] ?? '', 20);
$pagina   = $clean($data['pagina'] ?? '', 300);
$utm = [];
foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $k) {
    if (!empty($data[$k])) $utm[$k] = $clean($data[$k], 200);
}

if (mb_strlen($nome) < 2 || !preg_match('/^\d{10,13}$/', $whatsapp) || !filter_var($email, FILTER_VALIDATE_EMAIL) || !isset($planos[$plano])) {
    respond(422, ['ok' => false, 'error' => 'Dados inválidos.']);
}

$apiKey = env('BREVO_API_KEY');
$to     = env('MAIL_TO');
if ($apiKey === '' || $to === '') {
    error_log('[assinatura] BREVO_API_KEY ou MAIL_TO não configurados.');
    respond(500, ['ok' => false, 'error' => 'Envio não configurado.']);
}

$wa     = 'https://wa.me/' . (strlen($whatsapp) <= 11 ? '55' . $whatsapp : $whatsapp);
$quando = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y H:i');
$h      = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$row    = fn($k, $v) => '<tr><td style="padding:12px 28px;color:#A49DB8;width:140px">' . $k . '</td><td style="padding:12px 28px">' . $v . '</td></tr>';

$html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#0A0A0F;color:#ECE8F6;border-radius:16px;overflow:hidden">'
    . '<div style="padding:24px 28px;background:linear-gradient(135deg,#8928FF,#3A0F86)">'
    . '<p style="margin:0;font-size:12px;letter-spacing:2px;color:#00EF9E;font-weight:bold">SITE POR ASSINATURA</p>'
    . '<h1 style="margin:8px 0 0;font-size:22px;color:#fff">' . $h($nome) . ' · ' . $h($planos[$plano]) . '</h1></div>'
    . '<table style="width:100%;border-collapse:collapse;font-size:15px">'
    . $row('Nome', $h($nome))
    . $row('WhatsApp', '<a style="color:#00EF9E" href="' . $h($wa) . '">' . $h($whatsapp) . '</a>')
    . $row('E-mail', '<a style="color:#A75FFF" href="mailto:' . $h($email) . '">' . $h($email) . '</a>')
    . $row('Plano', $h($planos[$plano]))
    . $row('Recebido em', $h($quando));
foreach ($utm as $k => $v) $html .= $row($h($k), $h($v));
$html .= '</table>'
    . '<div style="padding:20px 28px 28px"><a href="' . $h($wa) . '" style="display:inline-block;background:#00EF9E;color:#03140D;font-weight:bold;padding:12px 22px;border-radius:999px;text-decoration:none">Chamar no WhatsApp</a></div>'
    . '<p style="margin:0;padding:0 28px 22px;font-size:12px;color:#6F6884">Página: ' . $h($pagina) . '</p></div>';

$text = "Novo lead · site por assinatura\n\nNome: $nome\nWhatsApp: $whatsapp ($wa)\nE-mail: $email\nPlano: {$planos[$plano]}\nRecebido em: $quando\n";
foreach ($utm as $k => $v) $text .= "$k: $v\n";

$payload = [
    'sender'      => ['name' => env('MAIL_FROM_NAME', 'Site Cassiano Galvão'), 'email' => env('MAIL_FROM', $to)],
    'to'          => [['email' => $to, 'name' => env('MAIL_TO_NAME', 'Cassiano Galvão')]],
    'replyTo'     => ['email' => $email, 'name' => $nome],
    'subject'     => 'Site por assinatura: ' . $nome . ' quer o ' . ($plano === 'completo' ? 'Completo' : 'Essencial'),
    'htmlContent' => $html,
    'textContent' => $text,
    'tags'        => ['site-assinatura'],
];

$ch = curl_init('https://api.brevo.com/v3/smtp/email');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => ['accept: application/json', 'content-type: application/json', 'api-key: ' . $apiKey],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
]);
$res = curl_exec($ch);
$st  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($st < 200 || $st >= 300) {
    error_log('[assinatura] Falha Brevo (' . $st . '): ' . ($err ?: (string) $res));
    respond(502, ['ok' => false, 'error' => 'Não foi possível enviar agora.']);
}
respond(200, ['ok' => true]);
