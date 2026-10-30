<?php
/**
 * Briefing do site, em 9 etapas, aberto depois do pagamento.
 * Acesso pelo mesmo link único (token) da página de pagamento. Salva o progresso.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/admin/inc/bootstrap.php';
require dirname(__DIR__, 2) . '/admin/inc/billing.php';
require dirname(__DIR__, 2) . '/admin/inc/layout.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
security_headers();

function bf_json(array $a, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($a, JSON_UNESCAPED_UNICODE);
    exit;
}

$c = null;
$fatal = null;
try {
    $c = customer_by_token((string) ($_REQUEST['t'] ?? ''));
} catch (Throwable $e) {
    $fatal = 'Sistema indisponível no momento. Tente de novo em instantes.';
}
$wa = wa_link($c ? admin_wa() : '5522997560717', 'Oi, Cassiano! Preciso de ajuda com o briefing do meu site.');
$open = $c && !in_array($c['status'], ['pendente', 'cancelado'], true);
$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';

/* ---------------- ações (JSON) ---------------- */
if ($isPost && isset($_POST['a'])) {
    if ($fatal || !$open) bf_json(['ok' => false, 'error' => 'Briefing indisponível para este link.'], 403);
    $cid = (int) $c['id'];
    $a = (string) $_POST['a'];
    try {
        $b = briefing_find($cid);
        $cur = $b['fields'] ?? [];
        $payload = json_decode((string) ($_POST['payload'] ?? ''), true);

        if ($a === 'save' || $a === 'submit') {
            $data = is_array($payload) ? briefing_sanitize($payload, $c) : $cur;
            $step = (int) ($_POST['step'] ?? 1);
            if ($a === 'save') {
                briefing_save($cid, $data, $step);
                bf_json(['ok' => true]);
            }
            $files = briefing_files($cid);
            $miss = briefing_missing($data, $files);
            briefing_save($cid, $data, 9);
            if ($miss) bf_json(['ok' => false, 'missing' => array_values($miss)]);
            $first = !$b || $b['status'] !== 'enviado';
            q("UPDATE briefings SET status = 'enviado', submitted_at = COALESCE(submitted_at, NOW()), step = 9 WHERE customer_id = ?", [$cid]);
            $b = briefing_find($cid);
            log_activity('briefing', $first ? 'Briefing enviado pelo cliente' : 'Briefing atualizado pelo cliente', $cid);
            $copiaTxt = ($data['copia'] ?? '') === 'propria' ? 'enviada pelo cliente' : 'criar';
            $sub = ($first ? '[Novo briefing]' : '[Briefing atualizado]') . ' Plano ' . ucfirst((string) $c['plan_slug']) . ' · ' . ($data['empresa'] ?? $c['name']) . ' · copy: ' . $copiaTxt;
            $html = '<p style="margin:0 0 6px">Cliente: <a href="' . h(wa_link($data['whatsapp'] ?? $c['whatsapp'])) . '">' . h($data['nome'] ?? $c['name']) . '</a></p>' . briefing_html($c, $b, $files);
            notify_admin($sub, $html, ['label' => 'Abrir no painel (copiar tudo e baixar arquivos)', 'url' => customer_url($cid)]);
            if ($first) notify_customer($c, 'briefing', today(), ['html' => briefing_html($c, $b, $files), 'empresa' => (string) ($data['empresa'] ?? ''), 'copia' => (string) ($data['copia'] ?? 'criar')]);
            bf_json(['ok' => true]);
        }

        if ($a === 'upload') {
            $map = briefing_field_map();
            $field = (string) ($_POST['field'] ?? '');
            if (!isset($map[$field]) || $map[$field]['type'] !== 'files') bf_json(['ok' => false, 'error' => 'Campo inválido.'], 400);
            $f = $map[$field];
            $file = $_FILES['file'] ?? null;
            if (!$file || !is_array($file) || is_array($file['name'])) bf_json(['ok' => false, 'error' => 'Nenhum arquivo recebido.'], 400);
            if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) bf_json(['ok' => false, 'limit' => true, 'error' => 'Esse arquivo passa de 15 MB. Mande por link (Google Drive, OneDrive...) no campo "Prefere mandar por link?" logo abaixo.']);
            if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) bf_json(['ok' => false, 'error' => 'Não consegui receber o arquivo. Tente de novo.']);
            if ($file['size'] > BRIEFING_MAX_FILE) bf_json(['ok' => false, 'limit' => true, 'error' => 'Esse arquivo passa de 15 MB. Mande por link (Google Drive, OneDrive...) no campo "Prefere mandar por link?" logo abaixo.']);
            $ext = briefing_ext_ok((string) $file['name'], (string) $f['accept']);
            if (!$ext) bf_json(['ok' => false, 'error' => 'Tipo de arquivo não aceito aqui (aceito: ' . strtoupper(str_replace(',', ', ', (string) $f['accept'])) . ').']);
            $all = briefing_files($cid);
            $total = array_sum(array_map('count', $all));
            $bytes = (int) q_val('SELECT COALESCE(SUM(size_bytes),0) FROM briefing_files WHERE customer_id = ?', [$cid]);
            if ($bytes + (int) $file['size'] > 100 * 1024 * 1024) bf_json(['ok' => false, 'limit' => true, 'error' => 'Os arquivos passaram do limite de 100 MB por aqui. Mande o restante por link (Google Drive, OneDrive...) no campo "Prefere mandar por link?" logo abaixo.']);
            if (count($all[$field] ?? []) >= (int) $f['max'] || $total >= BRIEFING_MAX_FILES) bf_json(['ok' => false, 'error' => 'Limite de arquivos neste campo: ' . (int) $f['max'] . '.']);
            $stored = bin2hex(random_bytes(10)) . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], briefing_dir($cid) . '/' . $stored)) bf_json(['ok' => false, 'error' => 'Não consegui salvar o arquivo.']);
            $orig = mb_substr(preg_replace('/[^\p{L}\p{N}._ \-()]+/u', '_', basename((string) $file['name'])), 0, 150);
            q('INSERT INTO briefing_files (customer_id, field, orig_name, stored_name, size_bytes, created_at) VALUES (?, ?, ?, ?, ?, NOW())', [$cid, $field, $orig, $stored, (int) $file['size']]);
            if (!$b) briefing_save($cid, briefing_sanitize([], $c), 1);
            bf_json(['ok' => true, 'files' => array_map(fn($r) => ['id' => (int) $r['id'], 'name' => $r['orig_name'], 'size' => briefing_size((int) $r['size_bytes'])], briefing_files($cid)[$field] ?? [])]);
        }

        if ($a === 'del') {
            $r = q_one('SELECT * FROM briefing_files WHERE id = ? AND customer_id = ?', [(int) ($_POST['id'] ?? 0), $cid]);
            if ($r) {
                @unlink(briefing_dir($cid) . '/' . $r['stored_name']);
                q('DELETE FROM briefing_files WHERE id = ?', [(int) $r['id']]);
            }
            bf_json(['ok' => true]);
        }
        bf_json(['ok' => false, 'error' => 'Ação inválida.'], 400);
    } catch (Throwable $e) {
        error_log('[briefing] ' . $e->getMessage());
        bf_json(['ok' => false, 'error' => 'Algo deu errado. Tente de novo em instantes.'], 500);
    }
}

/* ---------------- página ---------------- */
$boot = null;
if ($open && !$fatal) {
    try {
        $b = briefing_find((int) $c['id']);
        $d = $b['fields'] ?? [];
        $d += ['nome' => $c['name'], 'whatsapp' => phone_pretty($c['whatsapp']), 'email' => $c['email'], 'empresa' => (string) ($c['business'] ?? '')];
        foreach (['nome', 'whatsapp', 'email'] as $k) if (($d[$k] ?? '') === '') $d[$k] = $k === 'whatsapp' ? phone_pretty($c['whatsapp']) : $c[$k];
        $d['plano'] = (string) $c['plan_slug'];
        if ($d['plano'] !== 'completo') $d['formato'] = 'onepage';
        $files = [];
        foreach (briefing_files((int) $c['id']) as $k => $rows) $files[$k] = array_map(fn($r) => ['id' => (int) $r['id'], 'name' => $r['orig_name'], 'size' => briefing_size((int) $r['size_bytes'])], $rows);
        // Purchase do Pixel: uma vez por pedido, na primeira abertura do briefing depois do pagamento
        $purchase = null;
        $paid = q_one("SELECT amount_cents FROM payments WHERE customer_id = ? AND status = 'pago' ORDER BY COALESCE(paid_at, created_at) LIMIT 1", [(int) $c['id']]);
        if ($paid && q("INSERT IGNORE INTO notifications (customer_id, kind, ref_date, to_email, ok, created_at) VALUES (?, 'px_purchase', '1970-01-01', '', 1, NOW())", [(int) $c['id']])->rowCount() === 1) {
            $purchase = round((int) $paid['amount_cents'] / 100, 2);
        }
        $boot = [
            'purchase' => $purchase,
            't' => $c['portal_token'], 'steps' => briefing_steps(), 'data' => (object) $d, 'files' => (object) $files,
            'step' => $b ? max(1, (int) $b['step']) : 1, 'submitted' => $b && $b['status'] === 'enviado',
            'planoNome' => $c['plan_name'], 'wa' => $wa, 'pay' => pay_url($c),
        ];
    } catch (Throwable $e) {
        $fatal = 'Sistema indisponível no momento. Tente de novo em instantes.';
    }
}
client_head('Briefing do seu site', '&family=Cormorant+Garamond:wght@500;600&family=Nunito:wght@500;700&family=Playfair+Display:wght@500;700', false);
?>
<style>
  /* ---------- briefing ---------- */
  .steps-top{position:sticky;top:0;z-index:20;margin:0 -18px 22px;padding:12px 18px 14px;background:linear-gradient(180deg,rgba(7,6,11,.92),rgba(7,6,11,.78));-webkit-backdrop-filter:blur(14px);backdrop-filter:blur(14px);border-bottom:1px solid var(--line)}
  .steps-top__row{display:flex;justify-content:space-between;align-items:baseline;gap:10px;margin-bottom:10px;font-size:14px;color:var(--muted)}
  .steps-top__row b{color:#fff;font-weight:600}
  .steps-top__row span{font-family:var(--f-mono);font-size:12px;color:var(--dim)}
  .segs{display:grid;grid-template-columns:repeat(9,1fr);gap:5px}
  .segs i{height:5px;border-radius:99px;background:rgba(236,232,246,.1);transition:background .4s}
  .segs i.on{background:linear-gradient(90deg,var(--roxo),var(--roxo-claro))}
  .segs i.cur{background:linear-gradient(90deg,var(--roxo-claro),var(--verde));box-shadow:0 0 10px rgba(0,239,158,.5)}
  .lead-in{display:flex;gap:12px;align-items:flex-start;margin:0 0 18px;padding:14px 16px;border-radius:var(--r-md);background:rgba(0,239,158,.06);border:1px solid rgba(0,239,158,.28);color:var(--txt);font-size:15.5px}
  .lead-in .ic{color:var(--verde);margin-top:2px}
  .st-h h1{font-size:clamp(28px,6.4vw,38px)}
  .st-h p{margin:10px 0 0;font-size:16px}
  .panel{margin-top:20px;padding:22px 20px}
  .fields{display:grid;gap:22px}
  .fld{display:grid;gap:8px}
  .fld>label,.fld>.lbl{color:#fff;font-weight:600;font-size:15.5px}
  .fld small{color:var(--dim);font-size:14px}
  .req{color:var(--verde)}
  input[type=text],input[type=tel],input[type=email],textarea,select{width:100%;font:16.5px var(--f);color:var(--txt);background:rgba(7,6,11,.55);border:1px solid var(--line-2);border-radius:14px;padding:14px 15px;transition:border-color .2s,box-shadow .2s}
  input::placeholder,textarea::placeholder{color:var(--dim)}
  textarea{min-height:110px;resize:vertical;line-height:1.5}
  textarea.big{min-height:230px}
  input:focus,textarea:focus,select:focus{outline:none;border-color:var(--roxo-claro);box-shadow:0 0 0 4px rgba(137,40,255,.2)}
  select{appearance:none;-webkit-appearance:none;padding-right:42px;background:rgba(7,6,11,.55) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23A49DB8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right 15px center/16px}
  select option,select optgroup{background:#15101F;color:var(--txt)}
  .err{display:flex;align-items:center;gap:6px;color:#ff8ea3;font-size:14px}
  .bad input,.bad textarea,.bad select{border-color:rgba(255,92,122,.7)}
  .row{display:flex;gap:8px;align-items:center}
  .row input{flex:1}
  .choice{display:grid;gap:10px}
  .opt2{position:relative;display:flex;gap:14px;align-items:center;padding:14px 16px;border-radius:16px;cursor:pointer;color:var(--txt);background:rgba(255,255,255,.03);border:1px solid var(--line);transition:border-color .25s,background .25s}
  .opt2:hover{border-color:rgba(167,95,255,.55)}
  .opt2.on{border-color:var(--verde);background:rgba(0,239,158,.07)}
  .opt2 input{appearance:none;-webkit-appearance:none;flex:none;width:22px;height:22px;margin:0;border-radius:50%;border:2px solid var(--line-2);display:grid;place-items:center;cursor:pointer;transition:border-color .2s}
  .opt2 input[type=checkbox]{border-radius:7px}
  .opt2 input::after{content:"";width:10px;height:10px;border-radius:50%;background:var(--verde);transform:scale(0);transition:transform .2s var(--ease)}
  .opt2 input[type=checkbox]::after{width:11px;height:6px;border-radius:0;background:none;border-left:2.5px solid #03140D;border-bottom:2.5px solid #03140D;transform:rotate(-45deg) scale(0);margin-top:-3px}
  .opt2 input:checked{border-color:var(--verde)}
  .opt2 input[type=checkbox]:checked{background:var(--verde)}
  .opt2 input:checked::after{transform:scale(1)}
  .opt2 input[type=checkbox]:checked::after{transform:rotate(-45deg) scale(1)}
  .opt2 em{font-style:normal;font-size:11.5px;font-weight:700;color:#03140D;background:var(--verde);border-radius:99px;padding:2px 9px;margin-left:8px;white-space:nowrap}
  .cards{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
  .scard{position:relative;display:block;border-radius:18px;overflow:hidden;cursor:pointer;background:rgba(255,255,255,.03);border:1px solid var(--line);transition:border-color .25s,transform .25s var(--ease),box-shadow .25s}
  .scard:hover{transform:translateY(-2px);border-color:rgba(167,95,255,.55)}
  .scard input{position:absolute;opacity:0;pointer-events:none}
  .scard.on{border-color:var(--verde);box-shadow:0 0 0 1px var(--verde),0 18px 40px -20px rgba(0,239,158,.5)}
  .scard.on::after{content:"";position:absolute;right:10px;top:10px;width:24px;height:24px;border-radius:50%;background:var(--verde) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2303140D' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M20 6 9 17l-5-5'/%3E%3C/svg%3E") no-repeat center/14px}
  .scard:focus-within{outline:2px solid var(--verde);outline-offset:2px}
  .sw{position:relative;height:96px;padding:12px}
  /* mini página dentro da amostra */
  .sw .mn{position:absolute;inset:12px 12px 0;border-radius:9px 9px 0 0;background:rgba(255,255,255,.14);-webkit-backdrop-filter:blur(2px);backdrop-filter:blur(2px);padding:9px}
  .sw .mn b{display:block;width:30%;height:5px;border-radius:4px;background:currentColor;opacity:.9}
  .sw .mn i{display:block;height:7px;margin-top:9px;border-radius:4px;background:currentColor;opacity:.85;width:78%}
  .sw .mn i + i{width:52%;margin-top:5px;opacity:.5}
  .sw .mn u{display:block;width:34%;height:12px;margin-top:9px;border-radius:99px;background:currentColor;opacity:.95}
  .scard .tx{padding:11px 13px 14px}
  .scard .tx b{display:block;color:#fff;font-size:15px}
  .scard .tx small{display:block;color:var(--muted);font-size:12.5px;line-height:1.4;margin-top:3px}
  .fonts{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px}
  .fcard{position:relative;display:grid;gap:4px;padding:14px 16px;border-radius:16px;cursor:pointer;background:rgba(255,255,255,.03);border:1px solid var(--line);transition:border-color .25s}
  .fcard:hover{border-color:rgba(167,95,255,.55)}
  .fcard.on{border-color:var(--verde);background:rgba(0,239,158,.06)}
  .fcard input{position:absolute;opacity:0}
  .fcard:focus-within{outline:2px solid var(--verde);outline-offset:2px}
  .fcard .aa{font-size:34px;line-height:1;color:#fff}
  .fcard b{color:#fff;font-size:15px;font-family:var(--f)}
  .fcard small{color:var(--muted);font-size:12.5px;font-family:var(--f)}
  .clr{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
  .clr label{display:grid;gap:8px;padding:10px;border-radius:16px;background:rgba(255,255,255,.03);border:1px solid var(--line);font-size:13px;color:var(--muted)}
  .clr input[type=color]{width:100%;height:56px;padding:0;border:0;border-radius:11px;background:none;cursor:pointer}
  .clr input[type=color]::-webkit-color-swatch-wrapper{padding:0}
  .clr input[type=color]::-webkit-color-swatch{border:0;border-radius:11px}
  .clr input[type=text]{font:13px var(--f-mono);padding:9px 10px;text-transform:uppercase}
  .refs{display:grid;gap:10px}
  .ref{display:grid;gap:8px;padding:12px;border-radius:16px;background:rgba(255,255,255,.025);border:1px solid var(--line)}
  .drop{position:relative;display:grid;justify-items:center;gap:6px;padding:22px 16px;border-radius:18px;text-align:center;cursor:pointer;border:1.5px dashed rgba(167,95,255,.45);background:rgba(137,40,255,.06);transition:border-color .25s,background .25s}
  .drop:hover,.drop.is-over{border-color:var(--verde);background:rgba(0,239,158,.06)}
  .drop input{position:absolute;inset:0;opacity:0;cursor:pointer}
  .drop .di{display:grid;place-items:center;width:46px;height:46px;border-radius:14px;color:var(--roxo-claro);background:rgba(137,40,255,.16);border:1px solid rgba(167,95,255,.35)}
  .drop b{color:#fff;font-size:15px}
  .drop span{color:var(--dim);font-size:13px}
  .files{display:grid;gap:8px}
  .file{display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:14px;background:rgba(255,255,255,.035);border:1px solid var(--line);font-size:14.5px;animation:up .4s var(--ease) both}
  .file .fi{display:grid;place-items:center;width:34px;height:34px;flex:none;border-radius:10px;color:var(--verde);background:rgba(0,239,158,.1)}
  .file .fi .ic{width:17px;height:17px}
  .file span{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--txt)}
  .file span small{color:var(--dim);margin-left:6px}
  .file button{background:none;border:0;color:var(--dim);cursor:pointer;padding:6px;border-radius:8px;display:grid;place-items:center}
  .file button:hover{color:var(--bad);background:rgba(255,92,122,.1)}
  .upmsg{font-size:14px;color:var(--muted)}
  .upmsg.is-bad{color:#ff8ea3}
  .add{display:inline-flex;align-items:center;gap:8px;background:none;border:1px dashed var(--line-2);color:var(--muted);border-radius:12px;padding:10px 14px;font:600 14.5px var(--f);cursor:pointer;width:fit-content}
  .add:hover{color:#fff;border-color:var(--roxo-claro)}
  .rm{flex:none;display:grid;place-items:center;width:44px;height:48px;border-radius:12px;background:rgba(255,255,255,.03);border:1px solid var(--line);color:var(--dim);cursor:pointer}
  .rm:hover{color:var(--bad);border-color:rgba(255,92,122,.5)}
  .terms{list-style:none;margin:0;padding:18px;display:grid;gap:12px;border-radius:18px;background:radial-gradient(100% 120% at 100% 0%,rgba(137,40,255,.22),transparent 60%),rgba(255,255,255,.03);border:1px solid rgba(167,95,255,.3)}
  .terms li{display:grid;grid-template-columns:26px 1fr;gap:10px;align-items:start;font-size:15px;color:var(--txt)}
  .terms li .ic{width:22px;height:22px;padding:4px;border-radius:7px;color:var(--verde);background:rgba(0,239,158,.12);margin-top:1px}
  .rev{padding:16px 18px;margin-bottom:12px}
  .rev h3{display:flex;justify-content:space-between;align-items:center;gap:10px;margin:0 0 10px;font-size:16px}
  .rev h3 button{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.04);border:1px solid var(--line-2);color:#fff;border-radius:99px;padding:5px 12px;font:600 13px var(--f);cursor:pointer}
  .rev h3 button:hover{border-color:var(--roxo-claro);background:rgba(137,40,255,.16)}
  .rev h3 button .ic{width:14px;height:14px}
  .rev dl{display:grid;grid-template-columns:minmax(120px,38%) 1fr;gap:8px 14px;margin:0;font-size:14.5px}
  .rev dt{color:var(--dim)}
  .rev dd{margin:0;color:var(--txt);word-break:break-word}
  .rev .none{color:var(--dim);font-size:14.5px;margin:0}
  .nav{position:sticky;bottom:0;z-index:15;display:flex;gap:10px;margin:22px -18px 0;padding:14px 18px calc(env(safe-area-inset-bottom,0px) + 14px);background:linear-gradient(0deg,rgba(7,6,11,.95) 60%,rgba(7,6,11,0));}
  .nav .btn{flex:1}
  .nav .btn--ghost{flex:0 0 auto}
  .sub-err{margin-top:18px}
  .saved{position:fixed;left:50%;bottom:92px;transform:translate(-50%,10px);display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:99px;font-size:13.5px;color:#b9ffe3;background:rgba(10,24,18,.9);border:1px solid rgba(0,239,158,.4);opacity:0;transition:opacity .3s,transform .3s var(--ease);pointer-events:none;z-index:30}
  .saved .ic{width:15px;height:15px}
  .saved.on{opacity:1;transform:translate(-50%,0)}
  .final{text-align:center;padding-top:10px}
  .final h1{font-size:clamp(30px,7vw,44px);text-transform:uppercase;letter-spacing:-.03em;line-height:1.02}
  .final h1 em{display:block;font-style:normal;background:linear-gradient(180deg,#C49BFF 0%,var(--roxo-claro) 45%,var(--roxo) 100%);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
  .final > p{max-width:48ch;margin:14px auto 0;color:var(--txt)}
  .next{list-style:none;margin:26px 0 22px;padding:20px;display:grid;gap:14px;text-align:left}
  .next li{display:grid;grid-template-columns:34px 1fr;gap:12px;align-items:center;color:var(--txt);font-size:15.5px}
  .next li span{display:grid;place-items:center;width:34px;height:34px;border-radius:11px;font-weight:700;color:#fff;background:rgba(137,40,255,.22);border:1px solid rgba(167,95,255,.4)}
  .next li:first-child span{color:#03140D;background:var(--verde);border-color:var(--verde)}
  .final .acts{display:grid;gap:10px}
  .anim{animation:up .55s var(--ease) both}
  .fld.pulse textarea{border-color:var(--verde);box-shadow:0 0 0 4px rgba(0,239,158,.22)}
  @media (max-width:560px){.cards{grid-template-columns:1fr 1fr}.clr{gap:8px}.clr label{padding:8px}.clr input[type=color]{height:46px}.panel{padding:18px 16px}.rev dl{grid-template-columns:1fr;gap:2px}.rev dd{margin-bottom:8px}}
  @media (max-width:440px){.steps-top,.nav{margin-left:-14px;margin-right:-14px;padding-left:14px;padding-right:14px}}
  @media (max-width:360px){.cards{grid-template-columns:1fr}}
</style>
</head>
<body>
<?= client_token_guard((bool) $c) ?>
<?= icon_sprite() ?><?= client_bg() ?>
<div class="page page--wide">
  <header class="bar">
    <a href="/" aria-label="Cassiano Galvão"><img src="/assets/img/logo.svg" alt="Cassiano Galvão" width="104" height="42"></a>
    <span class="tag tag--live">Briefing do site</span>
  </header>
  <main id="app">
<?php if ($fatal): ?>
    <div class="hi in-up"><h1>Ops</h1><p><?= h($fatal) ?></p></div>
<?php elseif (!$c): ?>
    <div class="hi in-up"><h1>Link não encontrado</h1><p>Esse link não existe ou foi trocado. Me chama no WhatsApp que eu te mando o certo.</p></div>
    <a class="btn btn--green" href="<?= h($wa) ?>"><?= ic('chat') ?>Falar com o Cassiano</a>
<?php elseif ($c['status'] === 'pendente'): ?>
    <div class="hi in-up"><h1>Falta o pagamento</h1><p>O briefing abre assim que o primeiro pagamento for confirmado.</p></div>
    <a class="btn btn--green" href="<?= h(pay_url($c)) ?>"><?= ic('wallet') ?>Ir para o pagamento</a>
<?php elseif ($c['status'] === 'cancelado'): ?>
    <div class="hi in-up"><h1>Assinatura cancelada</h1><p>Se quiser voltar, é só me chamar.</p></div>
    <a class="btn btn--green" href="<?= h($wa) ?>"><?= ic('chat') ?>Falar com o Cassiano</a>
<?php else: ?>
    <noscript><p class="note note--bad">Ative o JavaScript do navegador para preencher o briefing.</p></noscript>
<?php endif; ?>
  </main>
</div>
<div class="saved" id="saved" role="status"><svg class="ic" aria-hidden="true"><use href="#i-check"/></svg>Progresso salvo</div>
<?php if ($boot): ?>
<script>window.BOOT=<?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script>
(function(){
  'use strict';
  var B=window.BOOT,steps=B.steps,D=B.data,F=B.files,app=document.getElementById('app'),saved=document.getElementById('saved');
  var cur=Math.min(Math.max(B.step,1),steps.length)-1,done=!!B.submitted,dirty=false,errs={},busy=false;
  function $(t,a,k){var e=document.createElement(t);a=a||{};for(var n in a){var v=a[n];if(n==='class')e.className=v;else if(n==='html')e.innerHTML=v;else if(n.slice(0,2)==='on')e.addEventListener(n.slice(2),v);else if(v!==false&&v!==null&&v!==undefined)e.setAttribute(n,v===true?'':v);}(k||[]).forEach(function(x){if(x!=null)e.appendChild(typeof x==='string'?document.createTextNode(x):x)});return e}
  function icon(n){var s=document.createElementNS('http://www.w3.org/2000/svg','svg');s.setAttribute('class','ic');s.setAttribute('aria-hidden','true');var u=document.createElementNS('http://www.w3.org/2000/svg','use');u.setAttribute('href','#i-'+n);s.appendChild(u);return s}
  function cond(w){return !w||w.every(function(c){var v=D[c[0]];if(v==null)v='';if(c[1]==='eq')return String(v)===String(c[2]);if(c[1]==='in')return c[2].map(String).indexOf(String(v))>-1;if(c[1]==='has')return Array.isArray(v)&&v.indexOf(c[2])>-1;return false})}
  function filled(v){if(Array.isArray(v))return v.some(function(x){return x&&typeof x==='object'?(x.url||x.why):String(x).trim()!==''});return v!=null&&String(v).trim()!==''}
  function opt(o){return Array.isArray(o)?{v:o[0],l:o[1],d:o[2],x:o[3]}:{v:o,l:o}}
  function hasFiles(k){return F[k]&&F[k].length>0}
  function missing(f){
    if(!cond(f.when))return false;
    if(!f.req&&!f.unless)return false;
    if(f.type==='files'?hasFiles(f.key):filled(D[f.key]))return false;
    if(f.unless&&f.unless.some(function(u){return filled(D[u])||hasFiles(u)}))return false;
    return true;
  }
  function flash(){saved.classList.add('on');setTimeout(function(){saved.classList.remove('on')},1500)}
  function post(data){var fd=new FormData();fd.append('t',B.t);for(var k in data)fd.append(k,data[k]);return fetch(location.pathname,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()})}
  function save(quiet){dirty=false;return post({a:'save',payload:JSON.stringify(D),step:cur+1}).then(function(r){if(r&&r.ok&&!quiet)flash();return r}).catch(function(){})}
  setInterval(function(){if(dirty&&!done)save(true)},15000);
  window.addEventListener('pagehide',function(){if(dirty&&!done&&navigator.sendBeacon){var fd=new FormData();fd.append('t',B.t);fd.append('a','save');fd.append('payload',JSON.stringify(D));fd.append('step',cur+1);navigator.sendBeacon(location.pathname,fd)}});

  function mask(v){var d=v.replace(/\D/g,'').slice(0,11);if(d.length>6)return '('+d.slice(0,2)+') '+d.slice(2,d.length>10?7:6)+'-'+d.slice(d.length>10?7:6);if(d.length>2)return '('+d.slice(0,2)+') '+d.slice(2);return d}
  function set(k,v,rerender){D[k]=v;dirty=true;delete errs[k];if(rerender)render(true)}
  function req(f){return (f.req||(f.unless&&!f.req))?$('span',{class:'req'},[' *']):null}

  function field(f){
    if(!cond(f.when))return null;
    var k=f.key,t=f.type,v=D[k],w=$('div',{class:'fld'+(errs[k]?' bad':'')});
    var lab=function(){return $('label',{for:'f_'+k},[f.label,req(f)])},title=function(){return $('span',{class:'lbl',id:'l_'+k},[f.label,req(f)])};
    if(t==='fixed'){w.appendChild(title());w.appendChild($('div',{class:'opt2 on',style:'cursor:default'},[icon('check'),$('span',{},[B.planoNome||''])]));}
    else if(t==='text'||t==='tel'||t==='email'){
      w.appendChild(lab());
      w.appendChild($('input',{id:'f_'+k,type:t==='tel'?'tel':(t==='email'?'email':'text'),inputmode:t==='tel'?'tel':null,maxlength:300,value:v||'',autocomplete:t==='email'?'email':(t==='tel'?'tel':'off'),list:f.suggest?'dl_'+k:null,oninput:function(e){var x=e.target.value;if(t==='tel'){x=mask(x);e.target.value=x}set(k,x)}}));
      if(f.suggest)w.appendChild($('datalist',{id:'dl_'+k},f.suggest.map(function(s){return $('option',{value:s})})));
    }
    else if(t==='area'){w.appendChild(lab());w.appendChild($('textarea',{id:'f_'+k,class:f.big?'big':'',maxlength:4000,oninput:function(e){set(k,e.target.value)}},[v||'']))}
    else if(t==='select'){
      w.appendChild(lab());
      var s=$('select',{id:'f_'+k,onchange:function(e){set(k,e.target.value,true)}},[$('option',{value:''},['Escolha o seu segmento'])]);
      Object.keys(f.groups).forEach(function(g){var og=$('optgroup',{label:g});f.groups[g].forEach(function(x){var o=$('option',{value:x},[x]);if(v===x)o.selected=true;og.appendChild(o)});s.appendChild(og)});
      w.appendChild(s);
    }
    else if(t==='radio'&&f.variant==='cards'){
      w.appendChild(title());
      var g=$('div',{class:'cards',role:'radiogroup','aria-labelledby':'l_'+k});
      f.options.forEach(function(o0){var o=opt(o0),on=String(v)===o.v,light=['light','minimal','vintage','natural'].indexOf(o.v)>-1;
        g.appendChild($('label',{class:'scard'+(on?' on':'')},[$('input',{type:'radio',name:'r_'+k,value:o.v,checked:on,onchange:function(){set(k,o.v,true)}}),
          $('div',{class:'sw',style:'background:'+o.x+';color:'+(light?'rgba(20,20,30,.75)':'rgba(255,255,255,.9)')},[$('div',{class:'mn'},[$('b'),$('i'),$('i'),$('u')])]),
          $('div',{class:'tx'},[$('b',{},[o.l]),$('small',{},[o.d])])]))});
      w.appendChild(g);
    }
    else if(t==='radio'&&f.variant==='fonts'){
      w.appendChild(title());
      var fg=$('div',{class:'fonts',role:'radiogroup','aria-labelledby':'l_'+k});
      f.options.forEach(function(o0){var o=opt(o0),on=String(v)===o.v;
        fg.appendChild($('label',{class:'fcard'+(on?' on':'')},[$('input',{type:'radio',name:'r_'+k,value:o.v,checked:on,onchange:function(){set(k,o.v,true)}}),$('span',{class:'aa',style:'font-family:'+o.x},['Aa']),$('b',{},[o.l]),$('small',{},[o.d])]))});
      w.appendChild(fg);
    }
    else if(t==='radio'){
      w.appendChild(title());
      var box=$('div',{class:'choice',role:'radiogroup','aria-labelledby':'l_'+k});
      f.options.forEach(function(o0){var o=opt(o0),on=String(v)===String(o.v);
        box.appendChild($('label',{class:'opt2'+(on?' on':'')},[$('input',{type:'radio',name:'r_'+k,value:o.v,checked:on,onchange:function(){set(k,o.v,true)}}),$('span',{},[o.l,o.d?$('em',{},[o.d]):null])]))});
      w.appendChild(box);
    }
    else if(t==='check'){w.appendChild($('label',{class:'opt2'+(v==='1'?' on':'')},[$('input',{type:'checkbox',checked:v==='1',onchange:function(e){set(k,e.target.checked?'1':'',true)}}),$('span',{},[f.label,f.req?$('span',{class:'req'},[' *']):null])]))}
    else if(t==='checks'){
      w.appendChild(title());
      var cb=$('div',{class:'choice'});
      f.options.forEach(function(o0){var o=opt(o0),arr=Array.isArray(v)?v:[],on=arr.indexOf(o.v)>-1;
        cb.appendChild($('label',{class:'opt2'+(on?' on':'')},[$('input',{type:'checkbox',checked:on,onchange:function(e){var a=(Array.isArray(D[k])?D[k]:[]).filter(function(x){return x!==o.v});if(e.target.checked)a.push(o.v);set(k,a,true)}}),$('span',{},[o.l])]))});
      w.appendChild(cb);
    }
    else if(t==='list'){
      w.appendChild(title());
      var arr=Array.isArray(v)&&v.length?v.slice():[''];D[k]=arr;
      arr.forEach(function(x,ix){w.appendChild($('div',{class:'row'},[$('input',{type:'text',maxlength:200,value:x,placeholder:'Item '+(ix+1),'aria-label':f.label+' '+(ix+1),oninput:function(e){D[k][ix]=e.target.value;dirty=true;delete errs[k]}}),arr.length>1?$('button',{type:'button',class:'rm','aria-label':'Remover item',onclick:function(){D[k].splice(ix,1);dirty=true;render(true)}},[icon('x')]):null]))});
      w.appendChild($('button',{type:'button',class:'add',onclick:function(){D[k].push('');dirty=true;render(true);var ins=document.querySelectorAll('.fld .row input');if(ins.length)ins[ins.length-1].focus()}},[icon('plus'),'Adicionar item']));
    }
    else if(t==='colors'){
      w.appendChild(title());
      var cs=Array.isArray(v)?v.slice(0,3):[];while(cs.length<3)cs.push('');D[k]=cs;
      var row=$('div',{class:'clr'});
      [0,1,2].forEach(function(ix){
        var pick=$('input',{type:'color',value:/^#[0-9a-f]{6}$/i.test(cs[ix])?cs[ix]:'#231d33','aria-label':'Cor '+(ix+1)});
        var hex=$('input',{type:'text',maxlength:7,placeholder:ix?'opcional':'toque acima',value:cs[ix],'aria-label':'Código da cor '+(ix+1)});
        pick.addEventListener('input',function(){hex.value=pick.value;D[k][ix]=pick.value;dirty=true;delete errs[k]});
        hex.addEventListener('input',function(){var x=hex.value.trim();if(x&&x[0]!=='#')x='#'+x;D[k][ix]=x;if(/^#[0-9a-f]{6}$/i.test(x))pick.value=x;dirty=true;delete errs[k]});
        row.appendChild($('label',{},['Cor '+(ix+1),pick,hex]))});
      w.appendChild(row);
    }
    else if(t==='refs'){
      w.appendChild(title());
      var rs=Array.isArray(v)?v.slice(0,3):[];while(rs.length<3)rs.push({url:'',why:''});D[k]=rs;
      var rb=$('div',{class:'refs'});
      [0,1,2].forEach(function(ix){rb.appendChild($('div',{class:'ref'},[$('input',{type:'text',maxlength:300,placeholder:'Link do site '+(ix+1),value:rs[ix].url,inputmode:'url',oninput:function(e){D[k][ix].url=e.target.value;dirty=true}}),$('input',{type:'text',maxlength:300,placeholder:'O que você gosta nele',value:rs[ix].why,oninput:function(e){D[k][ix].why=e.target.value;dirty=true}})]))});
      w.appendChild(rb);
    }
    else if(t==='files'){
      w.appendChild(title());
      var list=$('div',{class:'files'}),draw=function(){list.innerHTML='';(F[k]||[]).forEach(function(x){list.appendChild($('div',{class:'file'},[$('span',{class:'fi'},[icon(/\.(png|jpe?g|webp|gif|svg)$/i.test(x.name)?'image':'file')]),$('span',{},[x.name,$('small',{},[x.size])]),$('button',{type:'button','aria-label':'Remover '+x.name,onclick:function(){post({a:'del',id:x.id}).then(function(){F[k]=F[k].filter(function(y){return y.id!==x.id});draw()})}},[icon('trash')])]))})};
      draw();
      var msg=$('span',{class:'upmsg',role:'status'});
      var exts=f.accept.split(','),shown=exts.slice(0,5).map(function(x){return x.toUpperCase()}).join(', ')+(exts.length>5?'…':'');
      var fi=$('input',{type:'file',multiple:f.max>1,accept:exts.map(function(x){return '.'+x}).join(','),'aria-label':f.label,onchange:function(e){
        var fl=[].slice.call(e.target.files);e.target.value='';msg.textContent='';msg.className='upmsg';
        (function next(){if(!fl.length){if(msg.className==='upmsg')msg.textContent='';return}var one=fl.shift();msg.className='upmsg';msg.textContent='Enviando '+one.name+'…';
          var fd=new FormData();fd.append('t',B.t);fd.append('a','upload');fd.append('field',k);fd.append('file',one);
          fetch(location.pathname,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){if(r&&r.ok){F[k]=r.files;draw();delete errs[k];next()}else{msg.className='upmsg is-bad';msg.textContent=(r&&r.error)||'Não consegui enviar.';if(r&&r.limit)pointLink();next()}}).catch(function(){msg.className='upmsg is-bad';msg.textContent='Falha de conexão no envio. Tente de novo.';})})();
      }});
      var drop=$('label',{class:'drop'},[fi,$('span',{class:'di'},[icon('download')]),$('b',{},[f.max>1?'Toque para escolher os arquivos':'Toque para escolher o arquivo']),$('span',{},[shown+' · até 15 MB cada'+(f.max>1?' · até '+f.max:'')])]);
      ['dragenter','dragover'].forEach(function(n){drop.addEventListener(n,function(){drop.classList.add('is-over')})});
      ['dragleave','drop'].forEach(function(n){drop.addEventListener(n,function(){drop.classList.remove('is-over')})});
      w.appendChild(drop);w.appendChild(msg);w.appendChild(list);
    }
    else if(t==='terms'){
      w.appendChild(title());
      w.appendChild($('ul',{class:'terms'},f.items.filter(function(it){return cond(it[1])}).map(function(it){return $('li',{},[icon('tick'),$('span',{},[it[0]])])})));
    }
    if(f.hint&&t!=='terms')w.appendChild($('small',{},[f.hint]));
    if(errs[k])w.appendChild($('span',{class:'err'},[icon('alert'),errs[k]]));
    return w;
  }

  function pointLink(){var el=document.getElementById('f_arquivos_link');if(!el)return;var w=el.closest('.fld');if(w){w.classList.add('pulse');setTimeout(function(){w.classList.remove('pulse')},2400)}el.scrollIntoView({behavior:'smooth',block:'center'});setTimeout(function(){el.focus({preventScroll:true})},500)}
  function fmt(f){
    var v=D[f.key];
    if(f.type==='files')return (F[f.key]||[]).map(function(x){return x.name}).join(', ');
    if(f.type==='check')return v==='1'?'Sim':'';
    if(f.type==='checks')return (v||[]).map(function(x){var o=f.options.map(opt).filter(function(o){return o.v===x})[0];return o?o.l:x}).join(', ');
    if(f.type==='list')return (v||[]).filter(String).join(' · ');
    if(f.type==='colors')return (v||[]).filter(String).join(', ');
    if(f.type==='refs')return (v||[]).filter(function(r){return r.url||r.why}).map(function(r){return r.url+(r.why?' — '+r.why:'')}).join(' | ');
    if(f.type==='radio'){var o=f.options.map(opt).filter(function(o){return String(o.v)===String(v)})[0];return o?o.l:(v||'')}
    if(f.type==='fixed')return B.planoNome;
    if(f.type==='terms')return '';
    return v==null?'':String(v);
  }

  function validate(i){
    var ok=true;errs={};
    steps[i].fields.forEach(function(f){if(missing(f)){errs[f.key]=f.type==='files'?'Envie o arquivo, cole um link no campo "Prefere mandar por link?" ou marque a opção abaixo.':'Preencha este campo para continuar.';ok=false}});
    if(!ok)setTimeout(function(){var e=document.querySelector('.fld.bad');if(e){e.scrollIntoView({behavior:'smooth',block:'center'});var inp=e.querySelector('input,textarea,select');if(inp&&inp.type!=='file')inp.focus({preventScroll:true})}},60);
    return ok;
  }

  function stepView(){
    var st=steps[cur],wrap=$('div',{class:'anim'});
    var segs=$('div',{class:'segs','aria-hidden':'true'});
    steps.forEach(function(_,i){segs.appendChild($('i',{class:i<cur?'on':(i===cur?'cur':'')}))});
    wrap.appendChild($('div',{class:'steps-top'},[$('div',{class:'steps-top__row'},[$('b',{},[st.title]),$('span',{},['etapa '+(cur+1)+' de '+steps.length])]),segs]));
    if(cur===0)wrap.appendChild($('p',{class:'lead-in'},[icon('check'),$('span',{},['Pagamento confirmado! Agora me conta tudo sobre a sua empresa. Quanto mais completo, mais certeiro fica o seu site. Leva uns 15 minutos.'])]));
    var head=$('div',{class:'st-h'},[$('h1',{},[st.title])]);
    if(st.intro)head.appendChild($('p',{},[st.intro]));
    wrap.appendChild(head);
    if(st.id==='revisao'){
      var revs=$('div',{style:'margin-top:20px'});
      steps.slice(0,steps.length-1).forEach(function(s,si){
        var rows=s.fields.filter(function(f){return cond(f.when)&&f.type!=='fixed'&&fmt(f)!==''});
        var box=$('section',{class:'glass rev'},[$('h3',{},[s.title,$('button',{type:'button',onclick:function(){go(si)},'aria-label':'Editar '+s.title},[icon('pen'),'editar'])])]);
        if(!rows.length)box.appendChild($('p',{class:'none'},['Nada preenchido.']));
        else{var dl=$('dl');rows.forEach(function(f){dl.appendChild($('dt',{},[f.label]));dl.appendChild($('dd',{},[fmt(f)]))});box.appendChild(dl)}
        revs.appendChild(box)});
      wrap.appendChild(revs);
    }
    var fs=$('div',{class:'fields'});
    st.fields.forEach(function(f){var e=field(f);if(e)fs.appendChild(e)});
    wrap.appendChild($('section',{class:'glass panel'},[fs]));
    if(errs._sub)wrap.appendChild($('p',{class:'note note--bad sub-err',role:'alert'},[icon('alert'),$('span',{},[errs._sub])]));
    var last=cur===steps.length-1;
    wrap.appendChild($('div',{class:'nav'},[
      cur>0?$('button',{type:'button',class:'btn btn--ghost','aria-label':'Voltar',onclick:function(){go(cur-1)}},[icon('back'),$('span',{},['Voltar'])]):null,
      $('button',{type:'button',class:'btn btn--green',id:'nx',onclick:last?submit:next},[last?'Enviar meu briefing':'Continuar',icon(last?'send':'chevron')])]));
    return wrap;
  }

  function go(i){cur=i;errs={};render();save(true);window.scrollTo(0,0)}
  function next(){if(!validate(cur)){render(true);return}go(cur+1);flash()}
  function submit(){
    if(busy)return;
    if(!validate(cur)){render(true);return}
    busy=true;var b=document.getElementById('nx');b.disabled=true;b.firstChild.textContent='Enviando…';
    post({a:'submit',payload:JSON.stringify(D),step:steps.length}).then(function(r){busy=false;
      if(r&&r.ok){done=true;dirty=false;render();window.scrollTo(0,0);try{window.dataLayer=window.dataLayer||[];window.dataLayer.push({event:'BriefingEnviado'})}catch(e){}return}
      if(r&&r.missing&&r.missing.length){errs={_sub:'Falta preencher: '+r.missing.map(function(m){return m.label}).join('; ')+'.'};cur=r.missing[0].step;steps[cur].fields.forEach(function(f){if(missing(f))errs[f.key]='Preencha este campo para continuar.'});render(true);return}
      errs={_sub:(r&&r.error)||'Não consegui enviar agora. Tente de novo.'};render(true)
    }).catch(function(){busy=false;errs={_sub:'Falha de conexão. Suas respostas estão salvas, tente enviar de novo.'};render(true)});
  }

  function doneView(){
    var own=D.copia==='propria',emp=D.empresa||'sua empresa',num=D.whatsapp_site||D.whatsapp||'';
    var w=$('div',{class:'final anim'});
    var tick=$('div',{class:'tick',html:'<svg class="ok" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg><svg viewBox="0 0 100 100" class="sp sp1" aria-hidden="true"><use href="#i-spark"/></svg><svg viewBox="0 0 100 100" class="sp sp2" aria-hidden="true"><use href="#i-spark"/></svg><svg viewBox="0 0 100 100" class="sp sp3" aria-hidden="true"><use href="#i-spark"/></svg>'});
    w.appendChild(tick);
    w.appendChild($('h1',{html:'Pronto. <em>Agora é comigo.</em>'}));
    w.appendChild($('p',{},[own?'Recebi o seu briefing e a sua copy. Agora eu parto direto para o layout e te chamo no WhatsApp '+num+' com as duas direções visuais.':'Recebi o seu briefing. Agora eu vou desenvolver a copy do site da '+emp+' e te chamo no WhatsApp '+num+' para você aprovar. Também te mandei uma cópia do briefing por e-mail.']));
    var items=own?['Você já enviou a copy, então eu vou direto para o layout.','Eu te envio duas direções visuais e você escolhe uma.','Eu desenvolvo o site completo, você me manda sua lista de ajustes e o site vai pro ar.']:['Eu escrevo a copy do seu site e te envio pelo WhatsApp.','Você aprova a copy.','Eu te envio duas direções visuais e você escolhe uma.','Eu desenvolvo o site completo, você me manda sua lista de ajustes e o site vai pro ar.'];
    w.appendChild($('ol',{class:'glass next','aria-label':'O que acontece agora'},items.map(function(x,i){return $('li',{},[$('span',{},[String(i+1)]),x])})));
    w.appendChild($('p',{style:'margin:0 0 14px;color:var(--muted)'},['Esqueceu de mandar alguma coisa? Me chama no WhatsApp.']));
    w.appendChild($('div',{class:'acts'},[$('a',{class:'btn btn--green',href:B.wa},[icon('chat'),'Falar no WhatsApp']),$('button',{type:'button',class:'btn btn--ghost',onclick:function(){done=false;cur=steps.length-1;render();window.scrollTo(0,0)}},[icon('pen'),'Revisar minhas respostas'])]));
    return w;
  }

  function render(keep){var y=window.scrollY;app.innerHTML='';app.appendChild(done?doneView():stepView());if(keep)window.scrollTo(0,y)}
  render();
  if(B.purchase){
    try{window.dataLayer=window.dataLayer||[];window.dataLayer.push({event:'Purchase',value:B.purchase,currency:'BRL'})}catch(e){}
    var pt=0,pi=setInterval(function(){if(window.fbq){clearInterval(pi);try{window.fbq('track','Purchase',{value:B.purchase,currency:'BRL'})}catch(e){}}else if(++pt>60)clearInterval(pi)},250);
  }
})();
</script>
<script defer src="/api/tags.php"></script>
<?php endif; ?>
</body>
</html>
