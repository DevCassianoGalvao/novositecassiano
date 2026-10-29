<?php
/**
 * Rotina diária: confere o Mercado Pago, manda lembretes, aplica tolerância e suspende.
 *
 * cPanel > Tarefas Cron (1x por dia, ex.: 09:00):
 *   /usr/local/bin/php /home/USUARIO/cassianogalvao.com.br/admin/cron.php
 * Alternativa por URL (se o cron só aceitar URL):
 *   https://cassianogalvao.com.br/admin/cron.php?key=SUA_CRON_KEY
 */

declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/billing.php';

$cli = PHP_SAPI === 'cli';
if (!$cli) {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    $key = env('CRON_KEY');
    if ($key === '' || !hash_equals($key, (string) ($_GET['key'] ?? ''))) {
        http_response_code(403);
        exit("Forbidden\n");
    }
}

$lock = sys_get_temp_dir() . '/cg_cron.lock';
$fh = fopen($lock, 'c');
if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) exit("Já está rodando.\n");

try {
    $log = run_daily();
    setting_set('cron_last_run', date('Y-m-d H:i:s'));
    setting_set('cron_last_log', implode("\n", $log));
    echo date('c'), "\n", implode("\n", $log), "\n";
} catch (Throwable $e) {
    error_log('[assinaturas] cron: ' . $e->getMessage());
    http_response_code(500);
    echo 'ERRO: ', $e->getMessage(), "\n";
}
flock($fh, LOCK_UN);
