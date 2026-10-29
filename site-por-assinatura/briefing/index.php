<?php
/**
 * Briefing do site, em 9 etapas, aberto depois do pagamento.
 * Acesso pelo mesmo link único (token) da página de pagamento. Salva o progresso.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/admin/inc/bootstrap.php';
require dirname(__DIR__, 2) . '/admin/inc/billing.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

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
            if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) bf_json(['ok' => false, 'error' => 'Arquivo grande demais. O limite é 15 MB. Se precisar, me manda pelo WhatsApp.']);
            if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) bf_json(['ok' => false, 'error' => 'Não consegui receber o arquivo. Tente de novo.']);
            if ($file['size'] > BRIEFING_MAX_FILE) bf_json(['ok' => false, 'error' => 'Arquivo grande demais. O limite é 15 MB. Se precisar, me manda pelo WhatsApp.']);
            $ext = briefing_ext_ok((string) $file['name'], (string) $f['accept']);
            if (!$ext) bf_json(['ok' => false, 'error' => 'Tipo de arquivo não aceito aqui (aceito: ' . strtoupper(str_replace(',', ', ', (string) $f['accept'])) . ').']);
            $all = briefing_files($cid);
            $total = array_sum(array_map('count', $all));
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
        $boot = [
            't' => $c['portal_token'], 'steps' => briefing_steps(), 'data' => (object) $d, 'files' => (object) $files,
            'step' => $b ? max(1, (int) $b['step']) : 1, 'submitted' => $b && $b['status'] === 'enviado',
            'planoNome' => $c['plan_name'], 'wa' => $wa, 'pay' => pay_url($c),
        ];
    } catch (Throwable $e) {
        $fatal = 'Sistema indisponível no momento. Tente de novo em instantes.';
    }
}
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Briefing do seu site · Cassiano Galvão</title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#07060B">
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Nunito:wght@500;700&family=Playfair+Display:wght@500;700&family=Space+Grotesk:wght@400;500;700&family=Space+Mono&display=swap">
<style>
  :root{--bg:#07060B;--card:#120F1D;--roxo:#8928FF;--roxo-claro:#A75FFF;--verde:#00EF9E;--txt:#ECE8F6;--muted:#A49DB8;--line:rgba(236,232,246,.14);--bad:#FF5C7A}
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;padding:24px 16px 70px;background:radial-gradient(900px 520px at 50% -10%,rgba(137,40,255,.35),transparent 62%),var(--bg);color:var(--txt);font:18px/1.55 'Space Grotesk',system-ui,sans-serif}
  main{width:100%;max-width:640px;margin:0 auto}
  .eyebrow{font-size:13px;letter-spacing:.16em;text-transform:uppercase;color:var(--verde);margin:6px 0 10px;font-weight:700}
  h1{font-size:clamp(28px,6vw,38px);line-height:1.1;margin:0 0 14px;letter-spacing:-.02em}
  h2{font-size:22px;margin:0 0 6px}
  p{margin:0 0 14px;color:var(--muted)}
  .bar{height:6px;border-radius:99px;background:rgba(236,232,246,.1);overflow:hidden;margin:6px 0 22px}
  .bar i{display:block;height:100%;background:linear-gradient(90deg,var(--roxo),var(--verde));border-radius:99px;transition:width .3s}
  .fields{display:grid;gap:18px;margin-top:16px}
  .fld{display:grid;gap:6px}
  .fld>label,.fld>.lbl{color:var(--txt);font-weight:500}
  .fld small{color:var(--muted);font-size:15px}
  .req{color:var(--verde)}
  input[type=text],input[type=tel],input[type=email],textarea,select{width:100%;font:inherit;font-size:17px;color:var(--txt);background:#0E0B17;border:1px solid var(--line);border-radius:14px;padding:13px 14px}
  textarea{min-height:104px;resize:vertical}
  textarea.big{min-height:220px}
  input:focus,textarea:focus,select:focus{outline:2px solid var(--roxo-claro);outline-offset:1px;border-color:transparent}
  .err{color:var(--bad);font-size:15px}
  .bad input,.bad textarea,.bad select{border-color:var(--bad)}
  .row{display:flex;gap:10px;align-items:center}
  .row input[type=text]{flex:1}
  .opt{display:flex;gap:12px;align-items:flex-start;padding:13px 14px;border:1px solid var(--line);border-radius:14px;background:var(--card);cursor:pointer}
  .opt:hover{border-color:rgba(0,239,158,.5)}
  .opt input{margin-top:5px;accent-color:var(--verde)}
  .opt.on{border-color:var(--verde);background:rgba(0,239,158,.06)}
  .opt em{font-style:normal;font-size:12px;font-weight:700;color:#03140D;background:var(--verde);border-radius:99px;padding:1px 9px;margin-left:8px}
  .opt small{display:block}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .card{display:block;padding:0;overflow:hidden;position:relative}
  .card .sw{height:74px}
  .card .tx{padding:10px 12px 12px}
  .card .tx b{display:block}
  .card input{position:absolute;opacity:0}
  .card.on{box-shadow:0 0 0 2px var(--verde)}
  .fnt b{font-size:24px;font-weight:500}
  .ac{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .sm{min-height:42px;padding:8px 16px;font-size:15px}
  .clr{display:flex;gap:10px}
  .clr label{display:grid;gap:6px;flex:1;font-size:15px;color:var(--muted)}
  .clr input[type=color]{width:100%;height:46px;padding:2px;background:#0E0B17;border:1px solid var(--line);border-radius:12px}
  .clr input[type=text]{font-family:'Space Mono',monospace;font-size:14px;padding:9px}
  .refs{display:grid;gap:8px}
  .files{display:grid;gap:8px}
  .file{display:flex;justify-content:space-between;gap:10px;align-items:center;padding:10px 14px;border:1px solid var(--line);border-radius:12px;background:var(--card);font-size:16px}
  .file button{background:none;border:0;color:var(--bad);cursor:pointer;font:inherit;font-size:15px}
  .btn{display:inline-flex;align-items:center;justify-content:center;min-height:54px;padding:12px 26px;border-radius:99px;background:var(--verde);color:#03140D;font:700 18px 'Space Grotesk',sans-serif;border:0;cursor:pointer;text-decoration:none}
  .btn--ghost{background:transparent;color:var(--txt);border:1px solid var(--line)}
  .btn[disabled]{opacity:.5;cursor:wait}
  .nav{display:flex;justify-content:space-between;gap:12px;margin-top:28px}
  .nav .btn{flex:1}
  .note{padding:14px 16px;border-radius:14px;font-size:16px;border:1px solid rgba(255,92,122,.4);background:rgba(255,92,122,.1);color:#ffc2cd;margin-top:16px}
  .rev{border:1px solid var(--line);border-radius:16px;background:var(--card);padding:14px 16px;margin-bottom:12px}
  .rev h3{margin:0 0 8px;font-size:17px;display:flex;justify-content:space-between;gap:10px;align-items:center}
  .rev h3 button{background:none;border:0;color:var(--roxo-claro);cursor:pointer;font:inherit;font-size:15px}
  .rev p{margin:0 0 6px;font-size:16px;color:var(--txt)}
  .rev p b{color:var(--muted);font-weight:500}
  .done h1{text-transform:uppercase}
  .done h1 em{font-style:normal;color:var(--verde)}
  .done ol{color:var(--muted);padding-left:22px;margin:0 0 20px}
  .foot{text-align:center;margin-top:26px;font-size:16px}
  .foot a{color:var(--roxo-claro)}
  .saved{position:fixed;right:14px;bottom:14px;background:var(--card);border:1px solid var(--line);border-radius:99px;padding:6px 14px;font-size:14px;color:var(--muted);opacity:0;transition:.3s;pointer-events:none}
  .saved.on{opacity:1}
  @media(max-width:460px){.grid{grid-template-columns:1fr}.clr{flex-direction:column}}
</style>
</head>
<body>
<main id="app">
<?php if ($fatal): ?>
  <p class="eyebrow">Site por assinatura</p><h1>Ops</h1><p><?= h($fatal) ?></p>
<?php elseif (!$c): ?>
  <p class="eyebrow">Site por assinatura</p>
  <h1>Link não encontrado</h1>
  <p>Esse link não existe ou foi trocado. Fale comigo que eu te mando o certo.</p>
  <a class="btn" href="<?= h($wa) ?>">Falar com o Cassiano</a>
<?php elseif ($c['status'] === 'pendente'): ?>
  <p class="eyebrow">Site por assinatura</p>
  <h1>Falta o pagamento</h1>
  <p>O briefing abre assim que o primeiro pagamento for confirmado.</p>
  <a class="btn" href="<?= h(pay_url($c)) ?>">Ir para o pagamento</a>
<?php elseif ($c['status'] === 'cancelado'): ?>
  <p class="eyebrow">Site por assinatura</p>
  <h1>Assinatura cancelada</h1>
  <p>Fale comigo se quiser voltar.</p>
  <a class="btn" href="<?= h($wa) ?>">Falar com o Cassiano</a>
<?php else: ?>
  <noscript><p>Ative o JavaScript para preencher o briefing.</p></noscript>
<?php endif; ?>
</main>
<div class="saved" id="saved">Progresso salvo</div>
<?php if ($boot): ?>
<script>window.BOOT=<?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script>
(function(){
  'use strict';
  var B=window.BOOT,steps=B.steps,D=B.data,F=B.files,app=document.getElementById('app'),saved=document.getElementById('saved');
  var cur=Math.min(Math.max(B.step,1),steps.length)-1,done=!!B.submitted,dirty=false,errs={},busy=false;
  var FONTS={};
  function $(t,a,k){var e=document.createElement(t);a=a||{};for(var n in a){if(n==='class')e.className=a[n];else if(n==='html')e.innerHTML=a[n];else if(n.slice(0,2)==='on')e.addEventListener(n.slice(2),a[n]);else if(a[n]!==false&&a[n]!==null&&a[n]!==undefined)e.setAttribute(n,a[n]===true?'':a[n]);}(k||[]).forEach(function(x){if(x!=null)e.appendChild(typeof x==='string'?document.createTextNode(x):x)});return e}
  function cond(w){return !w||w.every(function(c){var v=D[c[0]];if(v==null)v='';if(c[1]==='eq')return String(v)===String(c[2]);if(c[1]==='in')return c[2].map(String).indexOf(String(v))>-1;if(c[1]==='has')return Array.isArray(v)&&v.indexOf(c[2])>-1;return false})}
  function filled(v){if(Array.isArray(v))return v.some(function(x){return x&&typeof x==='object'?(x.url||x.why):String(x).trim()!==''});return v!=null&&String(v).trim()!==''}
  function opt(o){return Array.isArray(o)?{v:o[0],l:o[1],d:o[2],x:o[3]}:{v:o,l:o}}
  function hasFiles(k){return F[k]&&F[k].length>0}
  function missing(f){
    if(!cond(f.when))return false;
    if(!f.req&&!f.unless)return false;
    var has=f.type==='files'?hasFiles(f.key):filled(D[f.key]);
    if(has)return false;
    if(f.unless&&f.unless.some(function(u){return filled(D[u])||hasFiles(u)}))return false;
    return !!(f.req||f.unless);
  }
  function flash(){saved.classList.add('on');setTimeout(function(){saved.classList.remove('on')},1400)}
  function post(data){var fd=new FormData();fd.append('t',B.t);for(var k in data)fd.append(k,data[k]);return fetch(location.pathname,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()})}
  function save(quiet){dirty=false;return post({a:'save',payload:JSON.stringify(D),step:cur+1}).then(function(r){if(r&&r.ok&&!quiet)flash();return r}).catch(function(){})}
  setInterval(function(){if(dirty&&!done)save(true)},15000);

  function mask(v){var d=v.replace(/\D/g,'').slice(0,11);if(d.length>6)return '('+d.slice(0,2)+') '+d.slice(2,d.length>10?7:6)+'-'+d.slice(d.length>10?7:6);if(d.length>2)return '('+d.slice(0,2)+') '+d.slice(2);return d}
  function set(k,v,rerender){D[k]=v;dirty=true;delete errs[k];if(rerender)render(true)}

  function field(f){
    if(!cond(f.when))return null;
    var k=f.key,w=$('div',{class:'fld'+(errs[k]?' bad':'')}),lab=function(){return $('label',{for:'f_'+k},[f.label,f.req?$('span',{class:'req'},[' *']):null])};
    var t=f.type,v=D[k];
    if(t==='fixed'){w.appendChild($('span',{class:'lbl'},[f.label]));w.appendChild($('div',{class:'opt on',style:'cursor:default'},[(B.planoNome||'')]));}
    else if(t==='text'||t==='tel'||t==='email'){
      w.appendChild(lab());
      var i=$('input',{id:'f_'+k,type:t==='tel'?'tel':(t==='email'?'email':'text'),maxlength:300,value:v||'',autocomplete:'off',list:f.suggest?'dl_'+k:null,oninput:function(e){var x=e.target.value;if(t==='tel'){x=mask(x);e.target.value=x}set(k,x)}});
      w.appendChild(i);
      if(f.suggest)w.appendChild($('datalist',{id:'dl_'+k},f.suggest.map(function(s){return $('option',{value:s})})));
    }
    else if(t==='area'){w.appendChild(lab());w.appendChild($('textarea',{id:'f_'+k,class:f.big?'big':'',maxlength:4000,oninput:function(e){set(k,e.target.value)}},[v||'']))}
    else if(t==='select'){
      w.appendChild(lab());
      var s=$('select',{id:'f_'+k,onchange:function(e){set(k,e.target.value,true)}},[$('option',{value:''},['Escolha...'])]);
      Object.keys(f.groups).forEach(function(g){var og=$('optgroup',{label:g});f.groups[g].forEach(function(x){var o=$('option',{value:x},[x]);if(v===x)o.selected=true;og.appendChild(o)});s.appendChild(og)});
      w.appendChild(s);
    }
    else if(t==='radio'){
      w.appendChild($('span',{class:'lbl'},[f.label,f.req?$('span',{class:'req'},[' *']):null]));
      var cards=f.variant==='cards',box=$('div',{class:cards?'grid':'ac',style:cards?'':'display:grid;gap:10px'});
      f.options.forEach(function(o0){var o=opt(o0),on=String(v)===String(o.v);
        var inp=$('input',{type:'radio',name:'r_'+k,value:o.v,checked:on,onchange:function(){set(k,o.v,true)}});
        var lb;
        if(cards)lb=$('label',{class:'opt card'+(on?' on':'')},[inp,$('div',{class:'sw',style:'background:'+o.x}),$('div',{class:'tx'},[$('b',{},[o.l]),$('small',{},[o.d])])]);
        else if(f.variant==='fonts')lb=$('label',{class:'opt fnt'+(on?' on':''),style:'width:100%'},[inp,$('span',{style:'font-family:'+o.x},[$('b',{},[o.l]),$('small',{style:'font-family:Space Grotesk'},[o.d+' · Aa Bb Cc 123'])])]);
        else lb=$('label',{class:'opt'+(on?' on':''),style:'width:100%'},[inp,$('span',{},[o.l,o.d?$('em',{},[o.d]):null])]);
        box.appendChild(lb)});
      w.appendChild(box);
    }
    else if(t==='check'){w.appendChild($('label',{class:'opt'+(v==='1'?' on':'')},[$('input',{type:'checkbox',checked:v==='1',onchange:function(e){set(k,e.target.checked?'1':'',true)}}),$('span',{},[f.label,f.req?$('span',{class:'req'},[' *']):null])]))}
    else if(t==='checks'){
      w.appendChild($('span',{class:'lbl'},[f.label,f.req?$('span',{class:'req'},[' *']):null]));
      f.options.forEach(function(o0){var o=opt(o0),arr=Array.isArray(v)?v:[],on=arr.indexOf(o.v)>-1;
        w.appendChild($('label',{class:'opt'+(on?' on':'')},[$('input',{type:'checkbox',checked:on,onchange:function(e){var a=(Array.isArray(D[k])?D[k]:[]).filter(function(x){return x!==o.v});if(e.target.checked)a.push(o.v);set(k,a,true)}}),$('span',{},[o.l])]))});
    }
    else if(t==='list'){
      w.appendChild($('span',{class:'lbl'},[f.label,f.req?$('span',{class:'req'},[' *']):null]));
      var arr=Array.isArray(v)&&v.length?v.slice():[''];D[k]=arr;
      arr.forEach(function(x,ix){w.appendChild($('div',{class:'row'},[$('input',{type:'text',maxlength:200,value:x,oninput:function(e){D[k][ix]=e.target.value;dirty=true;delete errs[k]}}),arr.length>1?$('button',{type:'button',class:'btn btn--ghost sm',onclick:function(){D[k].splice(ix,1);dirty=true;render(true)}},['Remover']):null]))});
      w.appendChild($('div',{},[$('button',{type:'button',class:'btn btn--ghost sm',onclick:function(){D[k].push('');dirty=true;render(true)}},['+ Adicionar item'])]));
    }
    else if(t==='colors'){
      w.appendChild($('span',{class:'lbl'},[f.label,f.req?$('span',{class:'req'},[' *']):null]));
      var cs=Array.isArray(v)?v.slice(0,3):[];while(cs.length<3)cs.push('');D[k]=cs;
      var row=$('div',{class:'clr'});
      [0,1,2].forEach(function(ix){
        var pick=$('input',{type:'color',value:/^#[0-9a-f]{6}$/i.test(cs[ix])?cs[ix]:'#8928ff'});
        var hex=$('input',{type:'text',maxlength:7,placeholder:'#000000',value:cs[ix]});
        pick.addEventListener('input',function(){hex.value=pick.value;D[k][ix]=pick.value;dirty=true;delete errs[k]});
        hex.addEventListener('input',function(){var x=hex.value.trim();if(x&&x[0]!=='#')x='#'+x;D[k][ix]=x;if(/^#[0-9a-f]{6}$/i.test(x))pick.value=x;dirty=true;delete errs[k]});
        row.appendChild($('label',{},['Cor '+(ix+1),pick,hex]))});
      w.appendChild(row);
    }
    else if(t==='refs'){
      w.appendChild($('span',{class:'lbl'},[f.label]));
      var rs=Array.isArray(v)?v.slice(0,3):[];while(rs.length<3)rs.push({url:'',why:''});D[k]=rs;
      var rb=$('div',{class:'refs'});
      [0,1,2].forEach(function(ix){rb.appendChild($('input',{type:'text',maxlength:300,placeholder:'Link do site '+(ix+1),value:rs[ix].url,oninput:function(e){D[k][ix].url=e.target.value;dirty=true}}));rb.appendChild($('input',{type:'text',maxlength:300,placeholder:'O que você gosta nele',value:rs[ix].why,oninput:function(e){D[k][ix].why=e.target.value;dirty=true}}))});
      w.appendChild(rb);
    }
    else if(t==='files'){
      w.appendChild($('span',{class:'lbl'},[f.label,(f.req||(f.unless&&!f.req))?$('span',{class:'req'},[' *']):null]));
      var list=$('div',{class:'files'}),draw=function(){list.innerHTML='';(F[k]||[]).forEach(function(x){list.appendChild($('div',{class:'file'},[$('span',{},[x.name+' · '+x.size]),$('button',{type:'button',onclick:function(){post({a:'del',id:x.id}).then(function(){F[k]=F[k].filter(function(y){return y.id!==x.id});draw()})}},['Remover'])]))})};
      draw();
      var msg=$('span',{class:'err'}),fi=$('input',{type:'file',multiple:f.max>1,accept:f.accept.split(',').map(function(x){return '.'+x}).join(','),onchange:function(e){
        var fl=[].slice.call(e.target.files),n=0;e.target.value='';msg.textContent='';
        (function next(){if(!fl.length){msg.textContent='';return}var one=fl.shift();n++;msg.style.color='var(--muted)';msg.textContent='Enviando '+one.name+'...';
          var fd=new FormData();fd.append('t',B.t);fd.append('a','upload');fd.append('field',k);fd.append('file',one);
          fetch(location.pathname,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(r){if(r&&r.ok){F[k]=r.files;draw();delete errs[k];next()}else{msg.style.color='var(--bad)';msg.textContent=(r&&r.error)||'Não consegui enviar.';next()}}).catch(function(){msg.style.color='var(--bad)';msg.textContent='Falha de conexão no envio. Tente de novo.';})})();
      }});
      w.appendChild(fi);w.appendChild(msg);w.appendChild(list);
    }
    if(f.hint&&t!=='files'||(t==='files'&&f.hint))w.appendChild($('small',{},[f.hint]));
    if(errs[k])w.appendChild($('span',{class:'err'},[errs[k]]));
    return w;
  }

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
    return v==null?'':String(v);
  }

  function validate(i){
    var ok=true,first=null;errs={};
    steps[i].fields.forEach(function(f){if(missing(f)){errs[f.key]='Preencha este campo para continuar.';ok=false;if(!first)first=f.key}});
    if(!ok)setTimeout(function(){var e=document.querySelector('.fld.bad');if(e)e.scrollIntoView({behavior:'smooth',block:'center'})},50);
    return ok;
  }

  function stepView(){
    var st=steps[cur],wrap=$('div'),pct=Math.round((cur+1)/steps.length*100);
    wrap.appendChild($('p',{class:'eyebrow'},['Etapa '+(cur+1)+' de '+steps.length]));
    wrap.appendChild($('div',{class:'bar'},[$('i',{style:'width:'+pct+'%'})]));
    if(cur===0)wrap.appendChild($('p',{style:'color:var(--txt)'},['Pagamento confirmado! Agora me conta tudo sobre a sua empresa. Quanto mais completo, mais certeiro fica o seu site. Leva uns 15 minutos.']));
    wrap.appendChild($('h1',{},[st.title]));
    if(st.intro)wrap.appendChild($('p',{},[st.intro]));
    var fs=$('div',{class:'fields'});
    if(st.id==='revisao'){
      steps.slice(0,steps.length-1).forEach(function(s,si){
        var rows=s.fields.filter(function(f){return cond(f.when)&&f.type!=='fixed'&&fmt(f)!==''});
        var box=$('div',{class:'rev'},[$('h3',{},[s.title,$('button',{type:'button',onclick:function(){go(si)}},['editar'])])]);
        if(!rows.length)box.appendChild($('p',{},['Nada preenchido.']));
        rows.forEach(function(f){box.appendChild($('p',{},[$('b',{},[f.label+': ']),fmt(f)]))});
        wrap.appendChild(box)});
    }
    st.fields.forEach(function(f){var e=field(f);if(e)fs.appendChild(e)});
    wrap.appendChild(fs);
    if(errs._sub)wrap.appendChild($('p',{class:'note'},[errs._sub]));
    var last=cur===steps.length-1;
    wrap.appendChild($('div',{class:'nav'},[
      cur>0?$('button',{type:'button',class:'btn btn--ghost',onclick:function(){go(cur-1,true)}},['Voltar']):null,
      $('button',{type:'button',class:'btn',id:'nx',onclick:last?submit:next},[last?'Enviar meu briefing':'Continuar'])]));
    return wrap;
  }

  function go(i,skipVal){cur=i;errs={};render();save(true);window.scrollTo(0,0)}
  function next(){if(!validate(cur)){render(true);return}go(cur+1)}
  function submit(){
    if(busy)return;
    if(!validate(cur)){render(true);return}
    busy=true;var b=document.getElementById('nx');b.disabled=true;b.textContent='Enviando...';
    post({a:'submit',payload:JSON.stringify(D),step:steps.length}).then(function(r){busy=false;
      if(r&&r.ok){done=true;dirty=false;render();window.scrollTo(0,0);try{window.dataLayer=window.dataLayer||[];window.dataLayer.push({event:'BriefingEnviado'})}catch(e){}return}
      if(r&&r.missing&&r.missing.length){errs={_sub:'Falta preencher: '+r.missing.map(function(m){return m.label}).join('; ')+'.'};cur=r.missing[0].step;steps[cur].fields.forEach(function(f){if(missing(f))errs[f.key]='Preencha este campo para continuar.'});render(true);return}
      errs={_sub:(r&&r.error)||'Não consegui enviar agora. Tente de novo.'};render(true)
    }).catch(function(){busy=false;errs={_sub:'Falha de conexão. Seus dados estão salvos, tente enviar de novo.'};render(true)});
  }

  function doneView(){
    var own=D.copia==='propria',emp=D.empresa||'sua empresa',num=D.whatsapp_site||D.whatsapp||'';
    var w=$('div',{class:'done'},[$('p',{class:'eyebrow'},['Briefing recebido']),$('h1',{html:'Pronto. <em>Agora é comigo.</em>'})]);
    w.appendChild($('p',{style:'color:var(--txt)'},[own?'Recebi o seu briefing e a sua copy. Agora eu parto direto para o layout e te chamo no WhatsApp '+num+' com as duas direções visuais.':'Recebi o seu briefing. Agora eu vou desenvolver a copy do site da '+emp+' e te chamo no WhatsApp '+num+' para você aprovar. Também te mandei uma cópia do briefing por e-mail.']));
    w.appendChild($('h2',{},['O que acontece agora']));
    w.appendChild($('ol',{},(own?['Você já enviou a copy, então vou direto ao layout.','Eu te envio duas direções visuais e você escolhe uma.','Eu desenvolvo o site completo, você me manda sua lista de ajustes e o site vai pro ar.']:['Eu escrevo a copy do seu site e te envio pelo WhatsApp.','Você aprova a copy.','Eu te envio duas direções visuais e você escolhe uma.','Eu desenvolvo o site completo, você me manda sua lista de ajustes e o site vai pro ar.']).map(function(x){return $('li',{},[x])})));
    w.appendChild($('p',{},['Esqueceu de mandar alguma coisa? Me chama no WhatsApp.']));
    w.appendChild($('p',{class:'foot',style:'margin:0 0 18px;text-align:left'},[$('a',{href:B.pay,style:'color:var(--roxo-claro)'},['Gerenciar ou cancelar minha assinatura'])]));
    w.appendChild($('div',{class:'ac'},[$('a',{class:'btn',href:B.wa},['Falar no WhatsApp']),$('button',{type:'button',class:'btn btn--ghost',onclick:function(){done=false;cur=steps.length-1;render();window.scrollTo(0,0)}},['Revisar minhas respostas'])]));
    return w;
  }

  function render(keep){
    var y=window.scrollY;app.innerHTML='';app.appendChild(done?doneView():stepView());
    if(keep)window.scrollTo(0,y);
  }
  render();
})();
</script>
<script defer src="/api/tags.php"></script>
<?php endif; ?>
</body>
</html>
