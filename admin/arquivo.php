<?php
/** Baixa um arquivo enviado pelo cliente no briefing (só com login no painel). */
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/billing.php';
require_login();

$r = q_one('SELECT * FROM briefing_files WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
$path = $r ? briefing_dir((int) $r['customer_id']) . '/' . $r['stored_name'] : '';
if (!$r || !is_file($path)) { http_response_code(404); exit('Arquivo não encontrado.'); }

$inline = isset($_GET['ver']) && in_array(strtolower(pathinfo($r['orig_name'], PATHINFO_EXTENSION)), ['png', 'jpg', 'jpeg', 'webp', 'gif', 'pdf'], true);
header('Content-Type: ' . ($inline ? briefing_mime($r['orig_name']) : 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
$ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $r['orig_name']);
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($r['orig_name']));
header('Cache-Control: private, no-store');
readfile($path);
