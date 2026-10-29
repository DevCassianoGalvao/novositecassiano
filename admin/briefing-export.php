<?php
/** Exporta o briefing de um cliente: TXT (para colar numa IA) ou ZIP com o TXT e todos os arquivos. */
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/billing.php';
require_login();

$c = customer_find((int) ($_GET['id'] ?? 0));
$b = $c ? briefing_find((int) $c['id']) : null;
if (!$c || !$b) { http_response_code(404); exit('Briefing não encontrado.'); }
$files = briefing_files((int) $c['id']);
$slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT', (string) ($b['fields']['empresa'] ?? $c['name'])))), '-') ?: 'cliente';
$txt = briefing_text($c, $b, $files);

if (($_GET['fmt'] ?? 'txt') === 'zip') {
    if (!class_exists('ZipArchive')) { http_response_code(501); exit('ZipArchive indisponível neste servidor. Baixe os arquivos um a um.'); }
    $tmp = tempnam(sys_get_temp_dir(), 'bf');
    $z = new ZipArchive();
    $z->open($tmp, ZipArchive::OVERWRITE);
    $z->addFromString('briefing-' . $slug . '.txt', $txt);
    foreach ($files as $field => $rows) {
        foreach ($rows as $r) {
            $p = briefing_dir((int) $c['id']) . '/' . $r['stored_name'];
            if (is_file($p)) $z->addFile($p, 'arquivos/' . $field . '/' . $r['orig_name']);
        }
    }
    $z->close();
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($tmp));
    header('Content-Disposition: attachment; filename="briefing-' . $slug . '.zip"');
    readfile($tmp);
    @unlink($tmp);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="briefing-' . $slug . '.txt"');
echo $txt;
