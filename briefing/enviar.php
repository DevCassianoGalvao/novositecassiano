<?php
/**
 * Recebe o briefing do site, salva no servidor e envia por e-mail pela Brevo.
 * Usa o mesmo .env do formulário do site (BREVO_API_KEY, MAIL_TO, MAIL_FROM...).
 * Respostas e arquivos ficam em /briefing/respostas/ (bloqueado para acesso público).
 */

declare(strict_types=1);

$PASTA        = __DIR__ . '/respostas';
$MAX_ARQUIVOS = 15;
$MAX_TAMANHO  = 10 * 1024 * 1024;               // 10 MB por arquivo
$EXTENSOES    = ['jpg', 'jpeg', 'png', 'webp', 'svg', 'pdf', 'ai', 'eps', 'zip'];
// A Brevo só aceita alguns formatos como anexo; os outros ficam só no servidor
$ANEXAVEIS    = ['jpg', 'jpeg', 'png', 'pdf', 'zip', 'eps'];
$MAX_ANEXOS   = 8 * 1024 * 1024;                // total anexado ao e-mail

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

load_env();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') responder(false, 'Método não permitido.', 405);

// Envio maior que o limite do PHP chega com $_POST vazio
if (empty($_POST) && !empty($_SERVER['CONTENT_LENGTH'])) {
    responder(false, 'Os arquivos passaram do limite de envio do servidor. Use o link do Google Drive para as fotos.', 413);
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

/* ---------- pasta deste briefing ---------- */
$id  = date('Y-m-d_His') . '_' . substr(slug($dados['empresa']) ?: 'cliente', 0, 40);
$dir = $PASTA . '/' . $id;
if (!is_dir($PASTA) && !mkdir($PASTA, 0750, true)) responder(false, 'Erro no servidor ao salvar.', 500);
if (!is_dir($dir) && !mkdir($dir, 0750, true)) responder(false, 'Erro no servidor ao salvar.', 500);

/* ---------- arquivos ---------- */
$salvos = [];
if (!empty($_FILES['arquivos']) && is_array($_FILES['arquivos']['name'])) {
    $total = count($_FILES['arquivos']['name']);
    for ($i = 0; $i < $total && count($salvos) < $MAX_ARQUIVOS; $i++) {
        if ($_FILES['arquivos']['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($_FILES['arquivos']['size'][$i] > $MAX_TAMANHO) continue;
        $original = basename((string) $_FILES['arquivos']['name'][$i]);
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, $EXTENSOES, true)) continue;
        $base = substr(slug(pathinfo($original, PATHINFO_FILENAME)) ?: 'arquivo', 0, 60);
        $nome = sprintf('%02d-%s.%s', count($salvos) + 1, $base, $ext);
        if (move_uploaded_file($_FILES['arquivos']['tmp_name'][$i], $dir . '/' . $nome)) $salvos[] = $nome;
    }
}

/* ---------- texto do briefing (salvo no servidor) ---------- */
$texto  = "BRIEFING DE SITE\nRecebido em: " . date('d/m/Y H:i') . "\n" . str_repeat('=', 40) . "\n";
foreach ($rotulos as $secao => $campos) {
    $texto .= "\n## " . mb_strtoupper($secao) . "\n";
    foreach ($campos as $chave => $rotulo) {
        if ($dados[$chave] !== '') $texto .= "- {$rotulo}: " . str_replace("\n", "\n    ", $dados[$chave]) . "\n";
    }
}
$texto .= "\n## ARQUIVOS ENVIADOS\n";
$texto .= $salvos ? ('- ' . implode("\n- ", $salvos) . "\n  (pasta: briefing/respostas/{$id})\n") : "- Nenhum\n";

file_put_contents($dir . '/briefing.txt', $texto);
file_put_contents($dir . '/briefing.json', json_encode(
    ['recebido_em' => date('c'), 'dados' => $dados, 'arquivos' => $salvos],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
));

/* ---------- e-mail pela Brevo ---------- */
$apiKey = env('BREVO_API_KEY');
$to     = env('MAIL_TO');
if ($apiKey === '' || $to === '') {
    error_log('[brief] BREVO_API_KEY ou MAIL_TO não configurados. Briefing salvo em ' . $id);
    responder(true); // o briefing já está salvo no servidor
}

$wa = preg_replace('/\D/', '', $dados['whatsapp']);
if (strlen($wa) <= 11) $wa = '55' . $wa;
$waLink = 'https://wa.me/' . $wa;

$h = static function (string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };

// anexos (só formatos aceitos pela Brevo, até o limite total)
$anexos = [];
$naoAnexados = [];
$soma = 0;
foreach ($salvos as $arq) {
    $ext = strtolower(pathinfo($arq, PATHINFO_EXTENSION));
    $tam = (int) filesize($dir . '/' . $arq);
    if (in_array($ext, $ANEXAVEIS, true) && $soma + $tam <= $MAX_ANEXOS) {
        $anexos[] = ['name' => $arq, 'content' => base64_encode((string) file_get_contents($dir . '/' . $arq))];
        $soma += $tam;
    } else {
        $naoAnexados[] = $arq;
    }
}

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
            . '<td style="padding:8px 28px 8px 0;font-size:14px;white-space:pre-wrap">' . nl2br($h($dados[$chave])) . '</td></tr>';
    }
    if ($linhas === '') continue;
    $html .= '<p style="margin:0;padding:20px 28px 6px;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#00EF9E;font-weight:bold">' . $h($secao) . '</p>'
        . '<table style="width:100%;border-collapse:collapse">' . $linhas . '</table>';
}
$html .= '<p style="margin:0;padding:20px 28px 6px;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#00EF9E;font-weight:bold">Arquivos</p>'
    . '<p style="margin:0;padding:0 28px;font-size:14px">' . ($salvos
        ? ($anexos ? count($anexos) . ' anexado(s) neste e-mail. ' : '')
          . ($naoAnexados ? 'No servidor (briefing/respostas/' . $h($id) . '): ' . $h(implode(', ', $naoAnexados)) : '')
        : 'Nenhum') . '</p>'
    . '<div style="padding:24px 28px 28px"><a href="' . $h($waLink) . '" style="display:inline-block;background:#00EF9E;color:#03140D;font-weight:bold;padding:12px 22px;border-radius:999px;text-decoration:none">Responder no WhatsApp</a></div>'
    . '</div>';

$payload = [
    'sender'      => ['name' => 'Briefing do site', 'email' => env('MAIL_FROM', $to)],
    'to'          => [['email' => $to, 'name' => env('MAIL_TO_NAME', 'Cassiano Galvão')]],
    'subject'     => 'Novo briefing: ' . $dados['empresa'] . ' (' . $dados['nome'] . ')',
    'htmlContent' => $html,
    'textContent' => $texto . "\nResponder no WhatsApp: {$waLink}\n",
    'tags'        => ['briefing'],
];
if ($dados['email'] !== '') $payload['replyTo'] = ['email' => $dados['email'], 'name' => $dados['nome']];

function brevo(array $payload, string $apiKey): array
{
    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_HTTPHEADER     => ['accept: application/json', 'content-type: application/json', 'api-key: ' . $apiKey],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $res = curl_exec($ch);
    $st  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$st, $err ?: (string) $res];
}

[$st, $res] = brevo($anexos ? $payload + ['attachment' => $anexos] : $payload, $apiKey);
if (($st < 200 || $st >= 300) && $anexos) {
    // se os anexos derem problema, manda pelo menos o texto
    error_log('[brief] Brevo recusou com anexos (' . $st . '): ' . $res);
    $payload['htmlContent'] = str_replace(count($anexos) . ' anexado(s) neste e-mail. ', 'Arquivos salvos em briefing/respostas/' . $h($id) . '. ', $payload['htmlContent']);
    [$st, $res] = brevo($payload, $apiKey);
}
if ($st < 200 || $st >= 300) error_log('[brief] Falha Brevo (' . $st . '): ' . $res . ' · briefing salvo em ' . $id);

// O briefing já está salvo no servidor, então confirma mesmo se o e-mail falhar.
responder(true);
