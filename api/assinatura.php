<?php
/**
 * Lead do pop-up de planos da página /site-por-assinatura (fase 1).
 * Grava o lead no banco (aparece no painel /admin) e avisa por e-mail pela Brevo.
 * Se o banco falhar, o e-mail continua sendo enviado (o lead nunca se perde).
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$GLOBALS['early_sent'] = false;
$GLOBALS['pay_url'] = null;

function respond(int $status, array $body): void
{
    if (!$GLOBALS['early_sent']) {
        if ($status < 300 && !empty($GLOBALS['pay_url'])) $body['pay_url'] = $GLOBALS['pay_url'];
        http_response_code($status);
        echo json_encode($body, JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/** Responde ao navegador já (para redirecionar rápido) e segue enviando o e-mail em segundo plano. */
function respond_early(array $body): void
{
    $fin = function_exists('fastcgi_finish_request') ? 'fastcgi_finish_request' : (function_exists('litespeed_finish_request') ? 'litespeed_finish_request' : null);
    if (!$fin) return; // sem suporte: responde só no final
    ignore_user_abort(true);
    $json = json_encode($body, JSON_UNESCAPED_UNICODE);
    http_response_code(200);
    header('Content-Length: ' . strlen($json));
    header('Connection: close');
    echo $json;
    $GLOBALS['early_sent'] = true;
    $fin();
}

require dirname(__DIR__) . '/admin/inc/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') respond(405, ['ok' => false, 'error' => 'Método não permitido.']);

$allowed = array_filter(array_map('trim', explode(',', env('ALLOWED_ORIGINS'))));
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($allowed && $origin !== '' && !in_array($origin, $allowed, true)) respond(403, ['ok' => false, 'error' => 'Origem não autorizada.']);

$data = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($data)) $data = $_POST;
if (!empty($data['website'])) respond(200, ['ok' => true]); // honeypot

/* limite simples por IP: 5 envios a cada 10 min */
$ip = client_ip();
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
$ciclo    = (int) ($data['ciclo'] ?? 1);
if (!in_array($ciclo, [1, 3, 6, 12], true)) $ciclo = 1;
$ciclos   = [1 => 'mensal', 3 => 'trimestral (-10%)', 6 => 'semestral (-15%)', 12 => 'anual (-20%)'];
$utm = [];
foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $k) {
    if (!empty($data[$k])) $utm[$k] = $clean($data[$k], 200);
}

if (mb_strlen($nome) < 2 || !preg_match('/^\d{10,13}$/', $whatsapp) || !filter_var($email, FILTER_VALIDATE_EMAIL) || !isset($planos[$plano])) {
    respond(422, ['ok' => false, 'error' => 'Dados inválidos.']);
}

/* 1) grava no banco (painel) */
$leadId = null;
try {
    if (db_ready()) {
        q('INSERT INTO leads (name, whatsapp, email, plan_slug, cycle_months, status, source_page, utm, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [$nome, $whatsapp, $email, $plano, $ciclo, 'novo', $pagina, $utm ? json_encode($utm, JSON_UNESCAPED_UNICODE) : null]);
        $leadId = (int) db()->lastInsertId();
        log_activity('lead', "Novo lead: $nome ({$planos[$plano]})", null, $leadId);

        /* cria (ou reaproveita) o cliente "aguardando pagamento" e devolve o link da página de pagamento */
        require_once dirname(__DIR__) . '/admin/inc/billing.php';
        $planRow = q_one('SELECT * FROM plans WHERE slug = ? AND active = 1', [$plano]);
        if ($planRow) {
            $cust = q_one("SELECT id, status FROM customers WHERE email = ? AND status <> 'cancelado' ORDER BY id DESC LIMIT 1", [$email]);
            $isSubscriber = false;
            if ($cust && $cust['status'] === 'pendente') {
                q('UPDATE customers SET name = ?, whatsapp = ?, plan_id = ?, price_cents = ?, cycle_months = ?, mp_preapproval_id = IF(mp_status = \'pending\', NULL, mp_preapproval_id), mp_init_point = IF(mp_status = \'pending\', NULL, mp_init_point), updated_at = NOW() WHERE id = ?', [$nome, $whatsapp, (int) $planRow['id'], (int) $planRow['price_cents'], $ciclo, (int) $cust['id']]);
                $custId = (int) $cust['id'];
            } elseif ($cust) {
                // já é assinante: o link pessoal NUNCA volta para o navegador (qualquer um poderia digitar o e-mail
                // de outra pessoa). Ele vai só para o e-mail do próprio cliente.
                $custId = (int) $cust['id'];
                $isSubscriber = true;
            } else {
                q('INSERT INTO customers (name, email, whatsapp, plan_id, price_cents, cycle_months, status, billing_mode, lead_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                    [$nome, $email, $whatsapp, (int) $planRow['id'], (int) $planRow['price_cents'], $ciclo, 'pendente', 'mercadopago', $leadId]);
                $custId = (int) db()->lastInsertId();
                log_activity('cliente', 'Cliente criado automaticamente pelo formulário da página', $custId, $leadId);
            }
            if ($isSubscriber) {
                // não liga o lead ao assinante: pode ser outra pessoa usando o e-mail dele
                q('UPDATE leads SET notes = ?, updated_at = NOW() WHERE id = ?', ['O e-mail já é de um assinante (cliente #' . $custId . '). O link da assinatura foi enviado só para o e-mail dele.', $leadId]);
            } else {
                q("UPDATE leads SET customer_id = ?, status = 'negociando', updated_at = NOW() WHERE id = ?", [$custId, $leadId]);
            }
            $custRow = customer_find($custId);
            if ($custRow && !$isSubscriber) $GLOBALS['pay_url'] = pay_url($custRow);
            if ($custRow && $isSubscriber) notify_customer($custRow, 'acesso', today());
        }
    }
} catch (Throwable $e) {
    error_log('[assinatura] banco: ' . $e->getMessage());
}
if ($GLOBALS['pay_url']) respond_early(['ok' => true, 'pay_url' => $GLOBALS['pay_url']]);

/* 2) avisa por e-mail */
$apiKey = env('BREVO_API_KEY');
$to     = setting('admin_email', env('MAIL_TO'));
if ($apiKey === '' || $to === '') {
    error_log('[assinatura] BREVO_API_KEY ou MAIL_TO não configurados.');
    respond($leadId ? 200 : 500, $leadId ? ['ok' => true] : ['ok' => false, 'error' => 'Envio não configurado.']);
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
    . $row('Período', $h(ucfirst($ciclos[$ciclo])))
    . $row('Recebido em', $h($quando));
foreach ($utm as $k => $v) $html .= $row($h($k), $h($v));
$html .= '</table>'
    . '<div style="padding:20px 28px 28px"><a href="' . $h($wa) . '" style="display:inline-block;background:#00EF9E;color:#03140D;font-weight:bold;padding:12px 22px;border-radius:999px;text-decoration:none">Chamar no WhatsApp</a>'
    . ($leadId ? ' <a href="' . $h(app_url() . '/admin/leads.php') . '" style="display:inline-block;color:#A75FFF;font-weight:bold;padding:12px 14px;text-decoration:none">Abrir no painel</a>' : '')
    . '</div>'
    . '<p style="margin:0;padding:0 28px 22px;font-size:12px;color:#6F6884">Página: ' . $h($pagina) . '</p></div>';

$text = "Novo lead · site por assinatura\n\nNome: $nome\nWhatsApp: $whatsapp ($wa)\nE-mail: $email\nPlano: {$planos[$plano]}\nPeríodo: {$ciclos[$ciclo]}\nRecebido em: $quando\n";
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

$ch = curl_init(env('BREVO_API_URL', 'https://api.brevo.com/v3/smtp/email'));
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
    if ($leadId) respond(200, ['ok' => true]); // o lead está salvo no painel
    respond(502, ['ok' => false, 'error' => 'Não foi possível enviar agora.']);
}
respond(200, ['ok' => true]);
