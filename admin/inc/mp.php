<?php
/**
 * Cliente do Mercado Pago (assinaturas / Preapproval).
 * Token e segredo do webhook ficam criptografados na tabela settings.
 */

declare(strict_types=1);


function mp_token(): string
{
    return secret_setting('mp_access_token');
}

function mp_connected(): bool
{
    return mp_token() !== '';
}

/** @return array{status:int, body:array, error:string} */
function mp_request(string $method, string $path, ?array $body = null): array
{
    $token = mp_token();
    if ($token === '') return ['status' => 0, 'body' => [], 'error' => 'Mercado Pago não conectado (cole o Access Token em Configurações).'];
    $ch = curl_init(rtrim(env('MP_API_URL', 'https://api.mercadopago.com'), '/') . $path);
    $headers = ['Authorization: Bearer ' . $token, 'accept: application/json'];
    $opts = [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
    ];
    if ($body !== null) {
        $headers[] = 'content-type: application/json';
        $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    if (in_array($method, ['POST', 'PUT'], true)) $headers[] = 'X-Idempotency-Key: ' . bin2hex(random_bytes(12));
    $opts[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opts);
    $res = curl_exec($ch);
    $st = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    $json = is_string($res) ? json_decode($res, true) : null;
    $json = is_array($json) ? $json : [];
    $error = $cerr;
    if ($error === '' && ($st < 200 || $st >= 300)) {
        $error = (string) ($json['message'] ?? $json['error'] ?? ('HTTP ' . $st));
        if (!empty($json['cause'][0]['description'])) $error .= ' (' . $json['cause'][0]['description'] . ')';
    }
    return ['status' => $st, 'body' => $json, 'error' => $error];
}

/** Testa o token: devolve dados da conta ou erro. */
function mp_whoami(): array
{
    return mp_request('GET', '/users/me');
}

function mp_is_test_token(string $t): bool
{
    return strpos($t, 'TEST-') === 0;
}

function mp_create_preapproval(array $customer, string $planName, ?string $startDate = null): array
{
    $body = [
        'reason'             => 'Site por assinatura · ' . $planName . (cycle_months($customer) > 1 ? ' · ' . cycle_name($customer) : ''),
        'external_reference' => 'c' . $customer['id'],
        'payer_email'        => $customer['email'],
        'auto_recurring'     => [
            'frequency'          => cycle_months($customer),
            'frequency_type'     => 'months',
            'transaction_amount' => round(cycle_amount($customer) / 100, 2),
            'currency_id'        => 'BRL',
        ],
        'back_url'           => app_url() . '/site-por-assinatura/obrigado/',
        'status'             => 'pending',
    ];
    // cliente que já pagou o mês (ex.: via Pix): o cartão só começa a cobrar no próximo vencimento
    if ($startDate && $startDate > date('Y-m-d')) $body['auto_recurring']['start_date'] = $startDate . 'T09:00:00.000-03:00';
    return mp_request('POST', '/preapproval', $body);
}

function mp_get_preapproval(string $id): array
{
    return mp_request('GET', '/preapproval/' . rawurlencode($id));
}

function mp_set_preapproval_status(string $id, string $status): array
{
    return mp_request('PUT', '/preapproval/' . rawurlencode($id), ['status' => $status]);
}

function mp_get_authorized_payment(string $id): array
{
    return mp_request('GET', '/authorized_payments/' . rawurlencode($id));
}

/**
 * Valida a assinatura do webhook (x-signature) quando o segredo foi cadastrado.
 * Mesmo sem segredo, todo evento é confirmado consultando a API com o nosso token,
 * então um aviso falso não consegue criar pagamento.
 */
function mp_signature_valid(): bool
{
    $secret = secret_setting('mp_webhook_secret');
    if ($secret === '') return true;
    $sig = (string) ($_SERVER['HTTP_X_SIGNATURE'] ?? '');
    $reqId = (string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? '');
    $ts = '';
    $v1 = '';
    foreach (explode(',', $sig) as $part) {
        $kv = explode('=', trim($part), 2);
        if (count($kv) !== 2) continue;
        if ($kv[0] === 'ts') $ts = $kv[1];
        if ($kv[0] === 'v1') $v1 = $kv[1];
    }
    $dataId = (string) ($_GET['data_id'] ?? $_GET['data.id'] ?? $_GET['id'] ?? '');
    if (ctype_alnum($dataId)) $dataId = strtolower($dataId);
    if ($ts === '' || $v1 === '') return false;
    $manifest = 'id:' . $dataId . ';request-id:' . $reqId . ';ts:' . $ts . ';';
    return hash_equals(hash_hmac('sha256', $manifest, $secret), $v1);
}

function mp_date_br(?string $iso): ?string
{
    if (!$iso) return null;
    try {
        return (new DateTime($iso))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d');
    } catch (Throwable $e) {
        return null;
    }
}

/** Cobrança Pix (QR Code) pela API de pagamentos. */
function mp_create_pix(array $c, int $amountCents, string $description): array
{
    return mp_request('POST', '/v1/payments', [
        'transaction_amount' => round($amountCents / 100, 2),
        'description'        => $description,
        'payment_method_id'  => 'pix',
        'payer'              => ['email' => $c['email'], 'first_name' => first_name($c['name'])],
        'external_reference' => 'c' . $c['id'],
        'date_of_expiration' => date('Y-m-d\TH:i:s.000P', strtotime('+1 day')),
        'notification_url'   => app_url() . '/api/mp-webhook.php',
    ]);
}

function mp_get_payment(string $id): array
{
    return mp_request('GET', '/v1/payments/' . rawurlencode($id));
}

/** Cancela uma cobrança Pix ainda não paga (melhor esforço). */
function mp_cancel_payment(string $id): array
{
    return mp_request('PUT', '/v1/payments/' . rawurlencode($id), ['status' => 'cancelled']);
}

/**
 * Checkout do Mercado Pago para pagar um período (trimestral, semestral, anual) no cartão,
 * à vista ou parcelado. Os juros do parcelamento ficam com o comprador (regra da conta no Mercado Pago).
 */
function mp_create_preference(array $c): array
{
    $months = cycle_months($c);
    $amount = round(cycle_amount($c) / 100, 2);
    $back = pay_url($c);
    return mp_request('POST', '/checkout/preferences', [
        'items' => [[
            'id'          => 'assinatura-' . $c['id'],
            'title'       => 'Site por assinatura · ' . $c['plan_name'] . ' · ' . cycle_name($c),
            'quantity'    => 1,
            'currency_id' => 'BRL',
            'unit_price'  => $amount,
        ]],
        'payer'              => ['email' => $c['email'], 'name' => first_name($c['name'])],
        'external_reference' => 'cp' . $c['id'],
        'notification_url'   => app_url() . '/api/mp-webhook.php',
        'back_urls'          => ['success' => $back, 'pending' => $back, 'failure' => $back],
        'auto_return'        => 'approved',
        'payment_methods'    => [
            'excluded_payment_types' => [['id' => 'ticket'], ['id' => 'atm'], ['id' => 'bank_transfer']],
            'installments'           => min(12, max(1, $months)),
            'default_installments'   => 1,
        ],
        'statement_descriptor' => 'CASSIANOGALVAO',
        'expires'              => true,
        'expiration_date_to'   => date('Y-m-d\TH:i:s.000P', strtotime('+3 days')),
    ]);
}

