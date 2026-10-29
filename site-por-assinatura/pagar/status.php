<?php
/** Consulta se o Pix foi pago (a tela do cliente chama a cada 5 segundos). */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/admin/inc/bootstrap.php';
require dirname(__DIR__, 2) . '/admin/inc/billing.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $c = customer_by_token((string) ($_GET['t'] ?? ''));
    $row = $c ? q_one('SELECT * FROM pix_charges WHERE id = ? AND customer_id = ?', [(int) ($_GET['id'] ?? 0), (int) $c['id']]) : null;
    if (!$row) { echo '{"paid":false}'; exit; }
    if ($row['status'] === 'pendente') pix_settle($row['mp_payment_id']);
    $paid = q_val('SELECT status FROM pix_charges WHERE id = ?', [(int) $row['id']]) === 'pago';
    echo json_encode(['paid' => $paid]);
} catch (Throwable $e) {
    error_log('[pagar/status] ' . $e->getMessage());
    echo '{"paid":false}';
}
