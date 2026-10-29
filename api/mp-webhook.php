<?php
/**
 * Webhook do Mercado Pago (Planos e assinaturas).
 * Configure em: Mercado Pago > Suas integrações > sua aplicação > Webhooks,
 * eventos "Planos e assinaturas" (cartão recorrente) e "Pagamentos" (Pix).
 * URL: https://SEU-DOMINIO/api/mp-webhook.php
 */

declare(strict_types=1);

require dirname(__DIR__) . '/admin/inc/bootstrap.php';
require dirname(__DIR__) . '/admin/inc/billing.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function wh_log(string $topic, string $dataId, string $result, string $note = ''): void
{
    try {
        q('INSERT INTO webhook_log (topic, data_id, result, note, created_at) VALUES (?, ?, ?, ?, NOW())', [mb_substr($topic, 0, 60), mb_substr($dataId, 0, 64), $result, mb_substr($note, 0, 255)]);
    } catch (Throwable $e) {
    }
}

if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['POST', 'GET'], true)) {
    http_response_code(405);
    exit('{"ok":false}');
}

$raw = (string) file_get_contents('php://input');
$body = json_decode($raw, true);
$body = is_array($body) ? $body : [];

$topic = (string) ($body['type'] ?? $body['topic'] ?? $_GET['type'] ?? $_GET['topic'] ?? '');
$dataId = (string) ($body['data']['id'] ?? $_GET['data_id'] ?? $_GET['data.id'] ?? $_GET['id'] ?? '');

try {
    if ($topic === '' || $dataId === '') {
        echo '{"ok":true,"ignored":"sem evento"}';
        exit;
    }
    $sigOk = mp_signature_valid();
    // Pagamentos Pix: o efeito só acontece depois de conferir o pagamento direto na API do Mercado Pago
    // com o nosso token, então um aviso falso não libera ninguém. Os outros tópicos exigem assinatura.
    if (!$sigOk && $topic !== 'payment') {
        wh_log($topic, $dataId, 'recusado', 'assinatura inválida');
        http_response_code(401);
        exit('{"ok":false,"error":"assinatura inválida"}');
    }

    $note = '';
    switch ($topic) {
        case 'subscription_preapproval':
        case 'preapproval':
            $r = mp_get_preapproval($dataId);
            $c = $r['error'] === '' ? customer_by_preapproval($dataId, (string) ($r['body']['external_reference'] ?? '')) : null;
            if ($c) {
                if (!$c['mp_preapproval_id']) customer_update((int) $c['id'], ['mp_preapproval_id' => $dataId]);
                [, $note] = mp_sync_customer(customer_find((int) $c['id']));
            } else {
                $note = $r['error'] !== '' ? $r['error'] : 'cliente não encontrado';
            }
            break;
        case 'subscription_authorized_payment':
        case 'authorized_payment':
            $note = mp_handle_authorized_payment($dataId);
            break;
        case 'payment':
            $note = pix_settle($dataId);
            break;
        default:
            $note = 'tópico ignorado';
    }
    wh_log($topic, $dataId, 'ok', $note);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('[mp-webhook] ' . $e->getMessage());
    wh_log($topic, $dataId, 'erro', $e->getMessage());
    http_response_code(500); // o Mercado Pago tenta de novo
    echo '{"ok":false}';
}
