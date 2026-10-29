<?php
/**
 * Núcleo do sistema de assinaturas: .env, banco, configurações, segredos,
 * sessão, CSRF e helpers. Usado pelo painel (/admin), pelo cron e pelas APIs.
 */

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
mb_internal_encoding('UTF-8');

define('ADMIN_DIR', dirname(__DIR__));
define('ROOT_DIR', dirname(__DIR__, 2));

/* ---------- .env ---------- */

function load_env(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    foreach ([dirname(ROOT_DIR) . '/.env', ROOT_DIR . '/.env'] as $file) {
        if (!is_readable($file)) continue;
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            $value = trim($value, "\"'");
            if (getenv($key) === false) putenv("$key=$value");
        }
        define('ENV_FILE', $file);
        return;
    }
}

function env(string $key, string $default = ''): string
{
    load_env();
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

function app_url(): string
{
    $u = rtrim(env('APP_URL'), '/');
    if ($u !== '') return $u;
    $host = $_SERVER['HTTP_HOST'] ?? 'cassianogalvao.com.br';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return ($https ? 'https' : 'http') . '://' . $host;
}

/* ---------- Banco ---------- */

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $name = env('DB_NAME');
    $user = env('DB_USER');
    if ($name === '' || $user === '') throw new RuntimeException('Banco não configurado no .env (DB_NAME, DB_USER, DB_PASS).');
    $dsn = 'mysql:host=' . env('DB_HOST', 'localhost') . ';port=' . env('DB_PORT', '3306') . ';dbname=' . $name . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $user, env('DB_PASS'), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec("SET time_zone = '-03:00'"); // horário de Brasília (sem horário de verão)
    return $pdo;
}

function db_ready(): bool
{
    try {
        db()->query('SELECT 1 FROM admins LIMIT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function q_one(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function q_val(string $sql, array $params = [])
{
    $r = q($sql, $params)->fetchColumn();
    return $r === false ? null : $r;
}

/* ---------- Segredos (criptografados com APP_KEY) ---------- */

function secret_key(): string
{
    $k = env('APP_KEY');
    if (strlen($k) < 16) throw new RuntimeException('Defina APP_KEY no .env (mínimo 16 caracteres) para guardar chaves com segurança.');
    return hash('sha256', $k, true);
}

function encrypt_secret(string $plain): string
{
    $iv = random_bytes(12);
    $tag = '';
    $c = openssl_encrypt($plain, 'aes-256-gcm', secret_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($c === false) throw new RuntimeException('Falha ao criptografar.');
    return 'enc:' . base64_encode($iv . $tag . $c);
}

function decrypt_secret(string $stored): string
{
    if (strpos($stored, 'enc:') !== 0) return '';
    $raw = base64_decode(substr($stored, 4), true);
    if ($raw === false || strlen($raw) < 29) return '';
    $p = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', secret_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $p === false ? '' : $p;
}

/* ---------- Configurações (tabela settings) ---------- */

function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = [];
        try {
            foreach (q_all('SELECT k, v FROM settings') as $r) $cache[$r['k']] = $r['v'];
        } catch (Throwable $e) {
            $cache = [];
        }
    }
    return $cache;
}

function setting(string $k, string $default = ''): string
{
    $all = settings_all();
    return isset($all[$k]) && $all[$k] !== null ? (string) $all[$k] : $default;
}

function setting_has(string $k): bool
{
    return array_key_exists($k, settings_all());
}

function setting_set(string $k, ?string $v): void
{
    q('INSERT INTO settings (k, v, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE v = VALUES(v), updated_at = NOW()', [$k, $v]);
    settings_all(true);
}

function secret_setting(string $k): string
{
    $v = setting($k);
    return $v === '' ? '' : decrypt_secret($v);
}

function secret_setting_set(string $k, string $plain): void
{
    setting_set($k, $plain === '' ? '' : encrypt_secret($plain));
}

/* ---------- Helpers ---------- */

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function money(int $cents): string
{
    return 'R$ ' . number_format($cents / 100, 2, ',', '.');
}

function fmt_date(?string $d, string $fmt = 'd/m/Y'): string
{
    if (!$d) return '—';
    $t = strtotime($d);
    return $t ? date($fmt, $t) : '—';
}

function fmt_dt(?string $d): string
{
    return fmt_date($d, 'd/m/Y H:i');
}

function today(): string
{
    return date('Y-m-d');
}

function days_between(string $from, string $to): int
{
    return (int) round((strtotime($to . ' 00:00:00') - strtotime($from . ' 00:00:00')) / 86400);
}

function add_months(string $date, int $n = 1): string
{
    $d = new DateTime($date);
    $day = (int) $d->format('j');
    $d->modify('first day of this month')->modify("+$n month");
    $d->setDate((int) $d->format('Y'), (int) $d->format('n'), min($day, (int) $d->format('t')));
    return $d->format('Y-m-d');
}

function phone_digits(string $p): string
{
    $d = preg_replace('/\D/', '', $p);
    return strlen($d) <= 11 ? '55' . $d : $d;
}

function wa_link(string $phone, string $text = ''): string
{
    return 'https://wa.me/' . phone_digits($phone) . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

function phone_pretty(string $p): string
{
    $d = preg_replace('/\D/', '', $p);
    if (strlen($d) > 11 && strpos($d, '55') === 0) $d = substr($d, 2);
    if (strlen($d) === 11) return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7);
    if (strlen($d) === 10) return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6);
    return $p;
}

function clean_text($v, int $max): string
{
    return mb_substr(trim(strip_tags((string) $v)), 0, $max);
}

function log_activity(string $kind, string $message, ?int $customerId = null, ?int $leadId = null): void
{
    try {
        q('INSERT INTO activity (customer_id, lead_id, kind, message, created_at) VALUES (?, ?, ?, ?, NOW())', [$customerId, $leadId, $kind, mb_substr($message, 0, 300)]);
    } catch (Throwable $e) {
        error_log('[assinaturas] activity: ' . $e->getMessage());
    }
}

function client_ip(): string
{
    return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'x';
}

/* ---------- Sessão, login e CSRF (painel) ---------- */

function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_name('cgadm');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/admin/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.gc_maxlifetime', '43200');
    session_start();
}

function csrf_token(): string
{
    session_boot();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check(): void
{
    session_boot();
    $sent = (string) ($_POST['_csrf'] ?? '');
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(419);
        exit('Sessão expirada. Volte, atualize a página e tente de novo.');
    }
}

function current_admin(): ?array
{
    session_boot();
    if (empty($_SESSION['admin_id'])) return null;
    static $a = false;
    if ($a === false) $a = q_one('SELECT id, name, email FROM admins WHERE id = ?', [(int) $_SESSION['admin_id']]);
    return $a;
}

function require_login(): array
{
    header('Cache-Control: no-store');
    $a = null;
    try {
        $a = db_ready() ? current_admin() : null;
    } catch (Throwable $e) {
        $a = null;
    }
    if (!$a) {
        if (!db_ready()) redirect('install.php');
        redirect('login.php');
    }
    return $a;
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function flash(?string $msg = null, string $type = 'ok')
{
    session_boot();
    if ($msg !== null) {
        $_SESSION['flash'][] = ['m' => $msg, 't' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- Estados e rótulos ---------- */

const CUSTOMER_STATUS = [
    'pendente'  => ['Aguardando pagamento', 'warn'],
    'ativo'     => ['Ativo', 'ok'],
    'atraso'    => ['Em atraso (tolerância)', 'warn'],
    'suspenso'  => ['Suspenso', 'bad'],
    'cancelado' => ['Cancelado', 'mute'],
];

const LEAD_STATUS = [
    'novo'       => 'Novo',
    'contato'    => 'Em contato',
    'negociando' => 'Negociando',
    'fechado'    => 'Fechado',
    'perdido'    => 'Perdido',
];

function badge(string $status): string
{
    $s = CUSTOMER_STATUS[$status] ?? [$status, 'mute'];
    return '<span class="badge badge--' . $s[1] . '">' . h($s[0]) . '</span>';
}
