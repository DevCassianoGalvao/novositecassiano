<?php
/**
 * Recebe o formulário de pré-qualificação do site e envia o lead
 * por e-mail usando a API transacional da Brevo.
 *
 * Configuração: copie .env.example para .env e preencha.
 * O .env é procurado, nesta ordem: variáveis de ambiente do servidor,
 * um nível acima da raiz do site (recomendado) e a raiz do site.
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
    $candidates = [
        dirname(__DIR__, 2) . '/.env', // acima da raiz pública
        dirname(__DIR__) . '/.env',    // raiz do site (protegida pelo .htaccess)
    ];
    foreach ($candidates as $file) {
        if (!is_readable($file)) {
            continue;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $value = trim($value, "\"'");
            if (getenv($key) === false) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
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

/* ---------- método e origem ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    respond(204, []);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['ok' => false, 'error' => 'Método não permitido.']);
}

$allowed = array_filter(array_map('trim', explode(',', env('ALLOWED_ORIGINS'))));
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($allowed && $origin !== '' && !in_array($origin, $allowed, true)) {
    respond(403, ['ok' => false, 'error' => 'Origem não autorizada.']);
}

/* ---------- leitura do corpo ---------- */
$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

/* honeypot: robôs preenchem o campo escondido */
if (!empty($data['website'])) {
    respond(200, ['ok' => true]);
}

/* ---------- limite simples por IP (5 envios a cada 10 min) ---------- */
$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
$bucket = sys_get_temp_dir() . '/cg_lead_' . md5($ip);
$now = time();
$hits = [];
if (is_readable($bucket)) {
    $hits = array_filter(
        (array) json_decode((string) file_get_contents($bucket), true),
        fn($t) => is_int($t) && $t > $now - 600
    );
}
if (count($hits) >= 5) {
    respond(429, ['ok' => false, 'error' => 'Muitos envios. Tente de novo em alguns minutos.']);
}
$hits[] = $now;
@file_put_contents($bucket, json_encode(array_values($hits)), LOCK_EX);

/* ---------- validação ---------- */
$clean = fn($v, int $max) => mb_substr(trim(strip_tags((string) $v)), 0, $max);

$nome      = $clean($data['nome'] ?? '', 80);
$whatsapp  = preg_replace('/\D/', '', (string) ($data['whatsapp'] ?? ''));
$instagram = $clean($data['instagram'] ?? '', 40);
$servico   = $clean($data['servico'] ?? '', 20);
$pagina    = $clean($data['pagina'] ?? '', 200);

$servicos = [
    'site'      => 'Preciso de um site',
    'design'    => 'Preciso de design',
    'video'     => 'Preciso de um vídeo',
    'automacao' => 'Preciso de uma automação',
];

$errors = [];
if (mb_strlen($nome) < 2) {
    $errors[] = 'nome';
}
if (!preg_match('/^\d{10,13}$/', $whatsapp)) {
    $errors[] = 'whatsapp';
}
$instagram = '@' . ltrim(preg_replace('/[^A-Za-z0-9._@]/', '', $instagram), '@');
if (mb_strlen($instagram) < 3) {
    $errors[] = 'instagram';
}
if (!isset($servicos[$servico])) {
    $errors[] = 'servico';
}
if ($errors) {
    respond(422, ['ok' => false, 'error' => 'Dados inválidos.', 'fields' => $errors]);
}

/* ---------- e-mail ---------- */
$apiKey = env('BREVO_API_KEY');
$to     = env('MAIL_TO');
if ($apiKey === '' || $to === '') {
    error_log('[lead] BREVO_API_KEY ou MAIL_TO não configurados.');
    respond(500, ['ok' => false, 'error' => 'Envio não configurado.']);
}

$waNumber = strlen($whatsapp) <= 11 ? '55' . $whatsapp : $whatsapp;
$waLink   = 'https://wa.me/' . $waNumber;
$igLink   = 'https://www.instagram.com/' . ltrim($instagram, '@') . '/';
$label    = $servicos[$servico];
$quando   = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y H:i');

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

$html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#0A0A0F;color:#ECE8F6;border-radius:16px;overflow:hidden">'
    . '<div style="padding:24px 28px;background:linear-gradient(135deg,#8928FF,#3A0F86)">'
    . '<p style="margin:0;font-size:12px;letter-spacing:2px;color:#00EF9E;font-weight:bold">NOVO LEAD PELO SITE</p>'
    . '<h1 style="margin:8px 0 0;font-size:22px;color:#fff">' . $h($nome) . ' · ' . $h($label) . '</h1></div>'
    . '<table style="width:100%;border-collapse:collapse;font-size:15px">'
    . '<tr><td style="padding:14px 28px;color:#A49DB8;width:140px">Nome</td><td style="padding:14px 28px">' . $h($nome) . '</td></tr>'
    . '<tr><td style="padding:14px 28px;color:#A49DB8">WhatsApp</td><td style="padding:14px 28px"><a style="color:#00EF9E" href="' . $h($waLink) . '">' . $h($whatsapp) . '</a></td></tr>'
    . '<tr><td style="padding:14px 28px;color:#A49DB8">Instagram</td><td style="padding:14px 28px"><a style="color:#A75FFF" href="' . $h($igLink) . '">' . $h($instagram) . '</a></td></tr>'
    . '<tr><td style="padding:14px 28px;color:#A49DB8">Precisa de</td><td style="padding:14px 28px">' . $h($label) . '</td></tr>'
    . '<tr><td style="padding:14px 28px;color:#A49DB8">Recebido em</td><td style="padding:14px 28px">' . $h($quando) . '</td></tr>'
    . '</table>'
    . '<div style="padding:20px 28px 28px"><a href="' . $h($waLink) . '" style="display:inline-block;background:#00EF9E;color:#03140D;font-weight:bold;padding:12px 22px;border-radius:999px;text-decoration:none">Responder no WhatsApp</a></div>'
    . '<p style="margin:0;padding:0 28px 22px;font-size:12px;color:#6F6884">Página: ' . $h($pagina) . '</p>'
    . '</div>';

$text = "Novo lead pelo site\n\nNome: $nome\nWhatsApp: $whatsapp ($waLink)\nInstagram: $instagram\nPrecisa de: $label\nRecebido em: $quando\nPágina: $pagina\n";

$payload = [
    'sender'      => ['name' => env('MAIL_FROM_NAME', 'Site Cassiano Galvão'), 'email' => env('MAIL_FROM', $to)],
    'to'          => [['email' => $to, 'name' => env('MAIL_TO_NAME', 'Cassiano Galvão')]],
    'subject'     => env('MAIL_SUBJECT', 'Novo contato vindo do site!'),
    'htmlContent' => $html,
    'textContent' => $text,
    'tags'        => ['lead-site'],
];

$ch = curl_init('https://api.brevo.com/v3/smtp/email');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => [
        'accept: application/json',
        'content-type: application/json',
        'api-key: ' . $apiKey,
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
]);
$response = curl_exec($ch);
$status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($status < 200 || $status >= 300) {
    error_log('[lead] Falha Brevo (' . $status . '): ' . ($curlErr ?: (string) $response));
    respond(502, ['ok' => false, 'error' => 'Não foi possível enviar agora.']);
}

respond(200, ['ok' => true]);
