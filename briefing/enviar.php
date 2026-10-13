<?php
/**
 * Recebe o briefing do site e envia tudo por e-mail pela Brevo, com os arquivos ANEXADOS.
 * Nada fica guardado no servidor: os arquivos só passam pela pasta temporária do PHP
 * e são apagados no fim do envio.
 *
 * Só se a Brevo falhar, o briefing (e os arquivos) ficam salvos em /briefing/respostas/
 * para não se perderem (pasta bloqueada para acesso público).
 *
 * Usa o mesmo .env do formulário do site (BREVO_API_KEY, MAIL_TO, MAIL_FROM...).
 */

declare(strict_types=1);

$PASTA         = __DIR__ . '/respostas';
$MAX_ARQUIVOS  = 15;
$MAX_TOTAL     = 14 * 1024 * 1024;   // a Brevo aceita até 20 MB por e-mail (os anexos crescem ~33% no envio)
$EXTENSOES     = ['jpg', 'jpeg', 'png', 'webp', 'svg', 'pdf', 'ai', 'eps', 'zip'];
// Formatos que a Brevo aceita como anexo direto; os outros (webp, svg, ai) vão dentro de um .zip
$ANEXO_DIRETO  = ['jpg', 'jpeg', 'png', 'pdf', 'zip', 'eps'];

date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');

function responder(bool $ok, ?string $erro = null, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $erro], JSON_UNESCAPED_UNICODE);
    exit;
}

function load_env(): void
{
    $candidates = [
        dirname(__DIR__, 2) . '/.env', // acima da raiz pública
        dirname(__DIR__) . '/.env',    // raiz do site (protegida pelo .htaccess)
    ];
    foreach ($candidates as $file) {
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

function slug(string $s): string
{
    $a = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    $a = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $a !== false ? $a : $s));
    return trim($a, '-');
}

function brevo(array $payload, string $apiKey): array
{
    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => ['accept: application/json', 'content-type: application/json', 'api-key: ' . $apiKey],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $res = curl_exec($ch);
    $st  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$st, $err ?: (string) $res];
}

load_env();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') responder(false, 'Método não permitido.', 405);

// Envio maior que o limite do PHP chega com $_POST vazio
if (empty($_POST) && !empty($_SERVER['CONTENT_LENGTH'])) {
    responder(false, 'Os arquivos passaram do limite de envio. Use o link do Google Drive para as fotos.', 413);
}

// Honeypot anti-spam: humanos não veem esse campo
if (!empty($_POST['website'])) responder(true);

/* ---------- campos ---------- */
$rotulos = [
    'Contato' => [
        'nome' => 'Nome', 'empresa' => 'Empresa', 'whatsapp' => 'WhatsApp', 'email' => 'E-mail',
        'instagram' => 'Instagram', 'cidade' => 'Cidade / região', 'aprovador' => 'Quem aprova',
    ],
    'Negócio' => [
        'descricao' => 'O que a empresa faz', 'servicos' => 'Serviços / produtos', 'diferencial' => 'Diferencial',
        'publico' => 'Cliente ideal', 'anos' => 'Tempo de empresa', 'numeros' => 'Números', 'concorrentes' => 'Concorrentes',
    ],
    'Objetivos' => [
        'objetivos' => 'Objetivos do site', 'acao' => 'Ação principal', 'busca' => 'Buscas no Google',
        'endereco' => 'Endereço', 'horario' => 'Horário',
    ],
    'Conteúdo' => [
        'paginas' => 'Páginas', 'textos' => 'Textos', 'fotos' => 'Fotos', 'depoimentos' => 'Depoimentos', 'faq' => 'Perguntas frequentes',
    ],
    'Visual' => [
        'logo' => 'Logo', 'drive' => 'Link de arquivos', 'cores' => 'Cores', 'estilo' => 'Estilo',
        'referencias' => 'Referências', 'evitar' => 'Evitar',
    ],
    'Técnico' => [
        'dominio' => 'Tem domínio', 'dominio_nome' => 'Domínio', 'site_atual' => 'Site no ar hoje', 'acesso' => 'Registrado em',
        'extras' => 'Integrações', 'redes' => 'Redes sociais', 'data_limite' => 'Data importante', 'obs' => 'Observações',
    ],
];

function limpar($v): string
{
    if (is_array($v)) return implode(', ', array_map('limpar', $v));
    $v = trim((string) $v);
    $v = str_replace("\0", '', $v);
    return mb_substr($v, 0, 5000);
}

$dados = [];
foreach ($rotulos as $campos) {
    foreach ($campos as $chave => $rotulo) {
        $dados[$chave] = isset($_POST[$chave]) ? limpar($_POST[$chave]) : '';
    }
}

foreach (['nome', 'empresa', 'whatsapp', 'cidade', 'descricao', 'servicos', 'diferencial'] as $obrigatorio) {
    if ($dados[$obrigatorio] === '') responder(false, 'Faltou preencher um campo obrigatório.', 422);
}
if ($dados['email'] !== '' && !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
    responder(false, 'O e-mail informado parece inválido.', 422);
}

/* ---------- arquivos: lidos direto da pasta temporária do PHP ---------- */
$arquivos = [];   // [nome, caminho temporário, extensão, tamanho]
$recusados = [];
$soma = 0;
if (!empty($_FILES['arquivos']) && is_array($_FILES['arquivos']['name'])) {
    $total = count($_FILES['arquivos']['name']);
    for ($i = 0; $i < $total && count($arquivos) < $MAX_ARQUIVOS; $i++) {
        $original = basename((string) $_FILES['arquivos']['name'][$i]);
        if ($_FILES['arquivos']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        if ($_FILES['arquivos']['error'][$i] !== UPLOAD_ERR_OK) { $recusados[] = $original; continue; }
        $tmp = (string) $_FILES['arquivos']['tmp_name'][$i];
        $tam = (int) $_FILES['arquivos']['size'][$i];
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, $EXTENSOES, true) || !is_uploaded_file($tmp) || $soma + $tam > $MAX_TOTAL) { $recusados[] = $original; continue; }
        $base = substr(slug(pathinfo($original, PATHINFO_FILENAME)) ?: 'arquivo', 0, 60);
        $arquivos[] = [sprintf('%02d-%s.%s', count($arquivos) + 1, $base, $ext), $tmp, $ext, $tam];
        $soma += $tam;
    }
}

/* ---------- monta os anexos ---------- */
$anexos = [];
$extras = [];   // webp, svg, ai: a Brevo não aceita direto, vão num .zip
foreach ($arquivos as [$nome, $tmp, $ext]) {
    if (in_array($ext, $ANEXO_DIRETO, true)) {
        $anexos[] = ['name' => $nome, 'content' => base64_encode((string) file_get_contents($tmp))];
    } else {
        $extras[] = [$nome, $tmp];
    }
}
$zipTmp = null;
if ($extras) {
    if (class_exists('ZipArchive')) {
        $zipTmp = tempnam(sys_get_temp_dir(), 'brief');
        $zip = new ZipArchive();
        if ($zip->open($zipTmp, ZipArchive::OVERWRITE) === true) {
            foreach ($extras as [$nome, $tmp]) $zip->addFile($tmp, $nome);
            $zip->close();
            $anexos[] = ['name' => 'arquivos-' . (slug($dados['empresa']) ?: 'cliente') . '.zip', 'content' => base64_encode((string) file_get_contents($zipTmp))];
        }
        @unlink($zipTmp);
    } else {
        foreach ($extras as [$nome]) $recusados[] = $nome . ' (formato não aceito como anexo)';
    }
}
$nomesAnexados = array_map(static function ($a) { return $a[0]; }, $arquivos);

/* ---------- texto do briefing ---------- */
$texto  = "BRIEFING DE SITE\nRecebido em: " . date('d/m/Y H:i') . "\n" . str_repeat('=', 40) . "\n";
foreach ($rotulos as $secao => $campos) {
    $texto .= "\n## " . mb_strtoupper($secao) . "\n";
    foreach ($campos as $chave => $rotulo) {
        if ($dados[$chave] !== '') $texto .= "- {$rotulo}: " . str_replace("\n", "\n    ", $dados[$chave]) . "\n";
    }
}
$texto .= "\n## ARQUIVOS\n";
$texto .= $nomesAnexados ? ('- ' . implode("\n- ", $nomesAnexados) . "\n") : "- Nenhum\n";
if ($recusados) $texto .= "- Não vieram (limite ou formato): " . implode(', ', $recusados) . "\n";

/* ---------- e-mail ---------- */
$wa = preg_replace('/\D/', '', $dados['whatsapp']);
if (strlen($wa) <= 11) $wa = '55' . $wa;
$waLink = 'https://wa.me/' . $wa;
$h = static function (string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };

$montaHtml = static function (string $notaArquivos) use ($dados, $rotulos, $h, $waLink): string {
    $html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:640px;margin:0 auto;background:#0B0912;color:#F2F0F7;border-radius:16px;overflow:hidden">'
        . '<div style="padding:24px 28px;background:linear-gradient(135deg,#8928FF,#A75FFF)">'
        . '<p style="margin:0;font-size:12px;letter-spacing:2px;text-transform:uppercase;opacity:.85">Novo briefing de site</p>'
        . '<h1 style="margin:6px 0 0;font-size:22px">' . $h($dados['empresa']) . '</h1>'
        . '<p style="margin:4px 0 0;font-size:14px;opacity:.9">' . $h($dados['nome']) . ' · recebido em ' . $h(date('d/m/Y H:i')) . '</p></div>';
    foreach ($rotulos as $secao => $campos) {
        $linhas = '';
        foreach ($campos as $chave => $rotulo) {
            if ($dados[$chave] === '') continue;
            $linhas .= '<tr><td style="padding:8px 28px;color:#A49DB8;vertical-align:top;width:34%;font-size:13px">' . $h($rotulo) . '</td>'
                . '<td style="padding:8px 28px 8px 0;font-size:14px">' . nl2br($h($dados[$chave])) . '</td></tr>';
        }
        if ($linhas === '') continue;
        $html .= '<p style="margin:0;padding:20px 28px 6px;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#00EF9E;font-weight:bold">' . $h($secao) . '</p>'
            . '<table style="width:100%;border-collapse:collapse">' . $linhas . '</table>';
    }
    return $html
        . '<p style="margin:0;padding:20px 28px 6px;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#00EF9E;font-weight:bold">Arquivos</p>'
        . '<p style="margin:0;padding:0 28px;font-size:14px">' . $notaArquivos . '</p>'
        . '<div style="padding:24px 28px 28px"><a href="' . $h($waLink) . '" style="display:inline-block;background:#00EF9E;color:#03140D;font-weight:bold;padding:12px 22px;border-radius:999px;text-decoration:none">Responder no WhatsApp</a></div>'
        . '</div>';
};

$nota = $nomesAnexados ? count($nomesAnexados) . ' arquivo(s) anexado(s) neste e-mail.' : 'Nenhum arquivo enviado.';
if ($dados['drive'] !== '') $nota .= ' Link do cliente: ' . $h($dados['drive']);
if ($recusados) $nota .= '<br>Não vieram (limite ou formato): ' . $h(implode(', ', $recusados));

$apiKey = env('BREVO_API_KEY');
$to     = env('MAIL_TO');

$payload = [
    'sender'      => ['name' => 'Briefing do site', 'email' => env('MAIL_FROM', $to)],
    'to'          => [['email' => $to, 'name' => env('MAIL_TO_NAME', 'Cassiano Galvão')]],
    'subject'     => 'Novo briefing: ' . $dados['empresa'] . ' (' . $dados['nome'] . ')',
    'htmlContent' => $montaHtml($nota),
    'textContent' => $texto . "\nResponder no WhatsApp: {$waLink}\n",
    'tags'        => ['briefing'],
];
if ($dados['email'] !== '') $payload['replyTo'] = ['email' => $dados['email'], 'name' => $dados['nome']];

$enviado = false;
$arquivosNoEmail = false;
if ($apiKey !== '' && $to !== '') {
    [$st, $res] = brevo($anexos ? $payload + ['attachment' => $anexos] : $payload, $apiKey);
    $enviado = $st >= 200 && $st < 300;
    $arquivosNoEmail = $enviado;
    if (!$enviado && $anexos) {
        // se os anexos derem problema, manda pelo menos as respostas
        error_log('[briefing] Brevo recusou com anexos (' . $st . '): ' . $res);
        $payload['htmlContent'] = $montaHtml('Os arquivos não puderam ir anexados e ficaram salvos no servidor, em briefing/respostas/.');
        [$st, $res] = brevo($payload, $apiKey);
        $enviado = $st >= 200 && $st < 300;
    }
    if (!$enviado) error_log('[briefing] Falha Brevo (' . $st . '): ' . $res);
} else {
    error_log('[briefing] BREVO_API_KEY ou MAIL_TO não configurados.');
}

/* ---------- reserva: só grava no servidor o que não chegou por e-mail ---------- */
if (!$enviado || ($arquivos && !$arquivosNoEmail)) {
    $id  = date('Y-m-d_His') . '_' . substr(slug($dados['empresa']) ?: 'cliente', 0, 40);
    $dir = $PASTA . '/' . $id;
    if (is_dir($dir) || mkdir($dir, 0750, true)) {
        if (!$enviado) {
            file_put_contents($dir . '/briefing.txt', $texto);
            file_put_contents($dir . '/briefing.json', json_encode(['recebido_em' => date('c'), 'dados' => $dados, 'arquivos' => $nomesAnexados], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        if (!$arquivosNoEmail) {
            foreach ($arquivos as [$nome, $tmp]) @move_uploaded_file($tmp, $dir . '/' . $nome);
        }
    } elseif (!$enviado) {
        responder(false, 'Não consegui enviar agora.', 500);
    }
}

responder(true);
