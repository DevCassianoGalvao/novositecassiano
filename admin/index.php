<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/billing.php';
require __DIR__ . '/inc/layout.php';

$admin = require_login();

const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

/* ---------- números ---------- */
$mrr = (int) q_val("SELECT COALESCE(SUM(price_cents),0) FROM customers WHERE status IN ('ativo','atraso')");
$ativos = (int) q_val("SELECT COUNT(*) FROM customers WHERE status IN ('ativo','atraso')");
$novosMes = (int) q_val("SELECT COUNT(*) FROM customers WHERE started_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");
$pend = (int) q_val("SELECT COUNT(*) FROM customers WHERE status = 'pendente'");
$leads7 = (int) q_val("SELECT COUNT(*) FROM leads WHERE created_at > (NOW() - INTERVAL 7 DAY)");
$leads7prev = (int) q_val("SELECT COUNT(*) FROM leads WHERE created_at BETWEEN (NOW() - INTERVAL 14 DAY) AND (NOW() - INTERVAL 7 DAY)");
$leads30 = (int) q_val("SELECT COUNT(*) FROM leads WHERE created_at > (NOW() - INTERVAL 30 DAY)");
$fech30 = (int) q_val("SELECT COUNT(*) FROM leads WHERE created_at > (NOW() - INTERVAL 30 DAY) AND status = 'fechado'");
$conv = $leads30 ? (int) round($fech30 / $leads30 * 100) : 0;
$previsto = (int) q_val("SELECT COALESCE(SUM(price_cents),0) FROM customers WHERE status IN ('ativo','atraso') AND next_due_date <= (CURDATE() + INTERVAL 30 DAY)");

/* receita recebida por mês (últimos 6) */
$serie = [];
for ($i = 5; $i >= 0; $i--) $serie[date('Y-m', strtotime("first day of -$i month"))] = 0;
foreach (q_all("SELECT DATE_FORMAT(COALESCE(paid_at, created_at), '%Y-%m') m, SUM(amount_cents) v FROM payments WHERE status = 'pago' AND COALESCE(paid_at, created_at) >= ? GROUP BY m", [array_key_first($serie) . '-01']) as $r) {
    if (isset($serie[$r['m']])) $serie[$r['m']] = (int) $r['v'];
}
$vals = array_values($serie);
$esteMes = end($vals);
$mesPassado = $vals[count($vals) - 2] ?? 0;
$trend = $mesPassado > 0 ? (int) round(($esteMes - $mesPassado) / $mesPassado * 100) : ($esteMes > 0 ? 100 : 0);

/* leads por dia (14 dias) para a minilinha */
$porDia = [];
for ($i = 13; $i >= 0; $i--) $porDia[date('Y-m-d', strtotime("-$i day"))] = 0;
foreach (q_all("SELECT DATE(created_at) d, COUNT(*) n FROM leads WHERE created_at >= (CURDATE() - INTERVAL 13 DAY) GROUP BY d") as $r) if (isset($porDia[$r['d']])) $porDia[$r['d']] = (int) $r['n'];

/* listas */
$tirar = q_all("SELECT c.*, p.name AS plan_name FROM customers c JOIN plans p ON p.id = c.plan_id WHERE c.status = 'suspenso' ORDER BY c.suspended_at DESC");
$atraso = (int) q_val("SELECT COUNT(*) FROM customers WHERE status = 'atraso'");
$semBriefing = (int) q_val("SELECT COUNT(*) FROM customers c LEFT JOIN briefings b ON b.customer_id = c.id AND b.status = 'enviado' WHERE c.status IN ('ativo','atraso') AND b.id IS NULL");
$leadsNovos = (int) q_val("SELECT COUNT(*) FROM leads WHERE status = 'novo'");
$vencendo = q_all("SELECT c.*, p.name AS plan_name FROM customers c JOIN plans p ON p.id = c.plan_id WHERE c.status IN ('ativo','atraso') AND c.next_due_date BETWEEN CURDATE() AND (CURDATE() + INTERVAL 7 DAY) ORDER BY c.next_due_date LIMIT 6");
$prazos = q_all("SELECT b.submitted_at, c.id, c.name, b.data FROM briefings b JOIN customers c ON c.id = b.customer_id WHERE b.status = 'enviado' AND b.submitted_at > (NOW() - INTERVAL 4 DAY) AND c.status <> 'cancelado' ORDER BY b.submitted_at LIMIT 5");
$novos = q_all("SELECT * FROM leads WHERE status = 'novo' ORDER BY created_at DESC LIMIT 5");
$atividade = q_all("SELECT a.*, c.name AS cname FROM activity a LEFT JOIN customers c ON c.id = a.customer_id ORDER BY a.id DESC LIMIT 7");

$funil = array_fill_keys(array_keys(LEAD_STATUS), 0);
foreach (q_all('SELECT status, COUNT(*) n FROM leads GROUP BY status') as $r) $funil[$r['status']] = (int) $r['n'];
$funilMax = max(1, max($funil));
$leadsTot = array_sum($funil);

$planos = q_all("SELECT p.name, p.slug, COUNT(c.id) n, COALESCE(SUM(c.price_cents),0) v FROM plans p LEFT JOIN customers c ON c.plan_id = p.id AND c.status IN ('ativo','atraso') GROUP BY p.id ORDER BY p.price_cents");

$todo = [];
if (!mp_connected()) $todo[] = ['Conectar o Mercado Pago', 'configuracoes.php#mp'];
if (env('BREVO_API_KEY') === '') $todo[] = ['Colocar a BREVO_API_KEY no .env', 'configuracoes.php#email'];
if (setting('cron_last_run') === '') $todo[] = ['Agendar a rotina diária', 'configuracoes.php#cron'];
if (setting('mp_webhook_secret') === '' && mp_connected()) $todo[] = ['Cadastrar o webhook no Mercado Pago', 'configuracoes.php#mp'];

/* ---------- desenho do gráfico ---------- */
function smooth_path(array $p): string
{
    $d = 'M' . $p[0][0] . ',' . $p[0][1];
    $n = count($p);
    for ($i = 0; $i < $n - 1; $i++) {
        $p0 = $p[max(0, $i - 1)]; $p1 = $p[$i]; $p2 = $p[$i + 1]; $p3 = $p[min($n - 1, $i + 2)];
        $c1 = [$p1[0] + ($p2[0] - $p0[0]) / 6, $p1[1] + ($p2[1] - $p0[1]) / 6];
        $c2 = [$p2[0] - ($p3[0] - $p1[0]) / 6, $p2[1] - ($p3[1] - $p1[1]) / 6];
        $d .= sprintf(' C%.1f,%.1f %.1f,%.1f %.1f,%.1f', $c1[0], $c1[1], $c2[0], $c2[1], $p2[0], $p2[1]);
    }
    return $d;
}
function nice_max(int $v): int
{
    if ($v <= 0) return 30000;
    $m = $v * 1.25;
    $mag = 10 ** floor(log10($m));
    foreach ([1, 2, 2.5, 5, 10] as $s) if ($s * $mag >= $m) return (int) ($s * $mag);
    return (int) (10 * $mag);
}
$W = 680; $H = 230; $L = 54; $R = 18; $T = 26; $B = 30;
$ymax = nice_max(max($vals));
$pts = [];
foreach ($vals as $i => $v) $pts[] = [round($L + $i * ($W - $L - $R) / (count($vals) - 1), 1), round($T + ($H - $T - $B) * (1 - $v / $ymax), 1)];
$line = smooth_path($pts);
$area = $line . ' L' . end($pts)[0] . ',' . ($H - $B) . ' L' . $pts[0][0] . ',' . ($H - $B) . ' Z';
$mk = array_keys($serie);

function spark_path(array $vals, int $w, int $h): string
{
    $max = max(1, max($vals));
    $n = count($vals);
    $p = [];
    foreach (array_values($vals) as $i => $v) $p[] = [round($i * $w / ($n - 1), 1), round(2 + ($h - 4) * (1 - $v / $max), 1)];
    return smooth_path($p);
}

$hora = (int) date('G');
$saud = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');
$dias = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
$hoje = $dias[(int) date('w')] . ', ' . date('j') . ' de ' . ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'][(int) date('n') - 1];


page_start('Painel', 'index', $admin);
?>
<section class="hello rise">
  <div>
    <time><?= h(ucfirst($hoje)) ?></time>
    <h1><?= h($saud) ?>, <?= h(first_name((string) $admin['name'])) ?>.</h1>
    <p><?= $ativos ? 'Sua carteira tem ' . $ativos . ' assinatura' . ($ativos > 1 ? 's' : '') . ' ativa' . ($ativos > 1 ? 's' : '') . '.' : 'Quando o primeiro cliente pagar, a carteira aparece aqui.' ?></p>
  </div>
  <div class="actions">
    <a class="btn" href="leads.php"><?= ic('leads') ?><span>Ver leads</span></a>
    <a class="btn btn--primary" href="cliente.php?id=0"><?= ic('plus') ?><span>Novo cliente</span></a>
  </div>
</section>

<?php if ($todo): ?>
<p class="aviso aviso--warn rise"><?= ic('alert') ?><span><b>Falta configurar:</b>
  <?php foreach ($todo as $i => [$t, $href]): ?><?= $i ? ' · ' : ' ' ?><a href="<?= h($href) ?>"><?= h($t) ?></a><?php endforeach; ?></span></p>
<?php endif; ?>

<div class="dash">
  <!-- receita -->
  <section class="card rev span-8 rise" aria-labelledby="t-rev">
    <div class="rev__top">
      <div>
        <span class="rev__label" id="t-rev"><span class="chip"><?= ic('wallet') ?></span>Receita recorrente por mês</span>
        <b class="rev__num" data-count="<?= $mrr ?>" data-money><?= h(money($mrr)) ?></b>
        <div class="rev__meta">
          <span>Recebido em <?= MESES[(int) date('n') - 1] ?>: <b><?= h(money($esteMes)) ?></b></span>
          <span class="trend trend--<?= $trend > 0 ? 'up' : ($trend < 0 ? 'down' : 'flat') ?>"><?= ic($trend < 0 ? 'down' : 'up') ?><?= ($trend > 0 ? '+' : '') . $trend ?>% vs. <?= MESES[(int) date('n', strtotime('first day of -1 month')) - 1] ?></span>
          <span>Entra nos próximos 30 dias: <b><?= h(money($previsto)) ?></b></span>
        </div>
      </div>
      <div class="rev__orb" aria-hidden="true"><i></i><?= spark() ?><?= spark('spk--g') ?></div>
    </div>
    <div class="chart<?= array_sum($vals) === 0 ? ' is-empty' : '' ?>" data-empty="Os pagamentos aparecem aqui">
      <svg viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Recebido nos últimos 6 meses">
        <defs>
          <linearGradient id="gArea" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#8928FF" stop-opacity=".42"/><stop offset="1" stop-color="#8928FF" stop-opacity="0"/></linearGradient>
          <linearGradient id="gLine" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#8928FF"/><stop offset=".7" stop-color="#A75FFF"/><stop offset="1" stop-color="#00EF9E"/></linearGradient>
        </defs>
        <?php for ($g = 0; $g <= 3; $g++): $y = $T + ($H - $T - $B) * $g / 3; ?>
          <line class="grid-l" x1="<?= $L ?>" x2="<?= $W - $R ?>" y1="<?= $y ?>" y2="<?= $y ?>"/>
          <text class="ax" x="<?= $L - 10 ?>" y="<?= $y + 4 ?>" text-anchor="end"><?php $yv = $ymax * (3 - $g) / 3 / 100; echo h($yv >= 1000 ? number_format($yv / 1000, 1, ',', '') . 'k' : (string) (int) round($yv)); ?></text>
        <?php endfor; ?>
        <path class="area" d="<?= $area ?>"/>
        <path class="line" d="<?= $line ?>"/>
        <?php foreach ($pts as $i => [$x, $y]): $last = $i === count($pts) - 1; $tx = min(max($x - 62, $L), $W - $R - 124); ?>
          <g class="pt<?= $last ? ' is-last' : '' ?>" tabindex="0">
            <rect class="hit" x="<?= $x - 30 ?>" y="<?= $T ?>" width="60" height="<?= $H - $T - $B ?>"/>
            <circle cx="<?= $x ?>" cy="<?= $y ?>" r="<?= $last ? 6 : 4.5 ?>"/>
            <g class="tip"><rect x="<?= $tx ?>" y="<?= max(0, $y - 54) ?>" width="124" height="40" rx="10"/><text x="<?= $tx + 12 ?>" y="<?= max(0, $y - 54) + 17 ?>"><?= h(money($vals[$i])) ?></text><text class="t2" x="<?= $tx + 12 ?>" y="<?= max(0, $y - 54) + 32 ?>">recebido em <?= MESES[(int) substr($mk[$i], 5, 2) - 1] ?></text></g>
          </g>
          <text class="ax" x="<?= $x ?>" y="<?= $H - 6 ?>" text-anchor="middle"><?= MESES[(int) substr($mk[$i], 5, 2) - 1] ?></text>
        <?php endforeach; ?>
      </svg>
    </div>
  </section>

  <!-- hoje -->
  <section class="card span-4 rise" aria-labelledby="t-hoje">
    <?= card_head('Pra fazer hoje', 'zap', '', 'g') ?>
    <?php
    $tasks = array_filter([
        [count($tirar), 'Tirar sites do ar', 'Não pagaram depois da tolerância', 'clientes.php?t=inativos', 'power', 'bad'],
        [$atraso, 'Em atraso', 'Ainda dentro da tolerância', 'clientes.php?t=ativos', 'clock', 'warn'],
        [count($prazos), 'Briefings para produzir', 'O prazo de 48h está correndo', '#prazos', 'file', 'g'],
        [$semBriefing, 'Sem briefing', 'Pagaram e ainda não enviaram', 'clientes.php?t=ativos', 'inbox', ''],
        [$leadsNovos, 'Leads para chamar', 'Pediram o site e esperam contato', 'leads.php?s=novo', 'chat', ''],
    ], fn($t) => $t[0] > 0);
    if (!$tasks): ?>
      <div class="allgood"><div class="allgood__art"><?= ic('tick') ?><?= spark() ?></div><b>Tudo em dia</b><span>Nenhuma pendência agora. Bom momento para prospectar.</span></div>
    <?php else: ?>
      <div class="today">
      <?php foreach ($tasks as [$n, $t, $s, $href, $icon, $tone]): ?>
        <a class="task<?= $tone ? ' task--' . $tone : '' ?>" href="<?= h($href) ?>"><span class="chip<?= $tone ? ' chip--' . $tone : '' ?>"><?= ic($icon) ?></span><span><b><?= h($t) ?></b><small><?= h($s) ?></small></span><span class="task__n"><?= $n ?></span><?= ic('chevron', 'go') ?></a>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- indicadores -->
  <section class="card kpi span-3 rise">
    <div class="kpi__top"><span class="kpi__label">Clientes ativos</span><span class="chip"><?= ic('users') ?></span></div>
    <b class="kpi__num" data-count="<?= $ativos ?>"><?= $ativos ?></b>
    <div class="kpi__foot"><?= $novosMes ? '<span class="trend trend--up">' . ic('up') . '+' . $novosMes . '</span> novos neste mês' : 'Nenhum novo neste mês' ?></div>
  </section>
  <section class="card kpi span-3 rise">
    <div class="kpi__top"><span class="kpi__label">Leads em 7 dias</span><span class="chip"><?= ic('leads') ?></span></div>
    <b class="kpi__num" data-count="<?= $leads7 ?>"><?= $leads7 ?></b>
    <div class="kpi__foot"><?php $d7 = $leads7 - $leads7prev; ?><span class="trend trend--<?= $d7 > 0 ? 'up' : ($d7 < 0 ? 'down' : 'flat') ?>"><?= ic($d7 < 0 ? 'down' : 'up') ?><?= ($d7 > 0 ? '+' : '') . $d7 ?></span> vs. semana anterior</div>
    <svg class="kpi__spark" viewBox="0 0 120 36" preserveAspectRatio="none" aria-hidden="true"><path d="<?= spark_path($porDia, 120, 36) ?>"/></svg>
  </section>
  <section class="card kpi span-3 rise">
    <div class="kpi__top"><span class="kpi__label">Aguardando pagamento</span><span class="chip chip--warn"><?= ic('clock') ?></span></div>
    <b class="kpi__num" data-count="<?= $pend ?>"><?= $pend ?></b>
    <div class="kpi__foot"><a href="clientes.php?t=pendentes">Ver quem falta pagar</a></div>
  </section>
  <section class="card kpi span-3 rise">
    <div class="kpi__top"><span class="kpi__label">Conversão em 30 dias</span><span class="chip chip--g"><?= ic('target') ?></span></div>
    <b class="kpi__num"><span data-count="<?= $conv ?>"><?= $conv ?></span>%</b>
    <div class="kpi__foot"><?= $fech30 ?> de <?= $leads30 ?> leads viraram clientes</div>
    <div class="kpi__bar"><i style="width:<?= max(2, $conv) ?>%"></i></div>
  </section>

  <!-- funil -->
  <section class="card span-4 rise" aria-labelledby="t-funil">
    <?= card_head('Funil de leads', 'funnel', '<a href="leads.php">Abrir ' . ic('chevron') . '</a>') ?>
    <div class="funnel">
      <?php foreach (LEAD_STATUS as $k => $label): ?>
        <div class="funnel__row<?= $k === 'fechado' ? ' funnel__row--ok' : ($k === 'perdido' ? ' funnel__row--mute' : '') ?>"><span><?= h($label) ?></span><div class="funnel__bar"><i style="width:<?= max($funil[$k] ? 4 : 0, round($funil[$k] / $funilMax * 100)) ?>%"></i></div><b><?= $funil[$k] ?></b></div>
      <?php endforeach; ?>
    </div>
    <div class="funnel__foot"><span><?= $leadsTot ?> leads no total</span><span>Fechados <b><?= $leadsTot ? round($funil['fechado'] / $leadsTot * 100) : 0 ?>%</b></span></div>
  </section>

  <!-- planos -->
  <section class="card span-4 half rise" aria-labelledby="t-planos">
    <?= card_head('Assinaturas por plano', 'pie') ?>
    <?php
    $totP = max(1, array_sum(array_map(fn($p) => (int) $p['n'], $planos)));
    $cores = ['#A75FFF', '#00EF9E', '#FFC24B', '#FF5C7A'];
    $C = 2 * M_PI * 58; $acc = 0; ?>
    <div class="donut">
      <div class="donut__c">
        <svg viewBox="0 0 150 150" aria-hidden="true"><circle class="ring" cx="75" cy="75" r="58"/>
          <?php foreach ($planos as $i => $p): $len = $C * ((int) $p['n'] / $totP); if ((int) $p['n'] === 0) continue; ?>
            <circle class="seg" cx="75" cy="75" r="58" stroke="<?= $cores[$i % 4] ?>" stroke-dasharray="<?= round(max($len - 4, 1), 2) ?> <?= round($C, 2) ?>" style="--c:<?= round(max($len - 4, 1), 2) ?>;--o:0;transform:rotate(<?= round($acc / $C * 360, 2) ?>deg);transform-origin:75px 75px"/>
          <?php $acc += $len; endforeach; ?>
        </svg>
        <div><b data-count="<?= $ativos ?>"><?= $ativos ?></b><small>ativas</small></div>
      </div>
      <ul class="legend">
        <?php foreach ($planos as $i => $p): ?>
          <li><i style="background:<?= $cores[$i % 4] ?>"></i><span><?= h(str_replace('Plano ', '', $p['name'])) ?></span><b><?= (int) $p['n'] ?></b><small><?= h(money((int) $p['v'])) ?>/mês</small></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <!-- vencimentos -->
  <section class="card span-4 half rise" aria-labelledby="t-venc">
    <?= card_head('Vencem nesta semana', 'calendar', '<a href="clientes.php">Clientes ' . ic('chevron') . '</a>') ?>
    <?php if (!$vencendo): ?>
      <div class="empty"><span class="chip"><?= ic('calendar') ?></span><b>Semana tranquila</b><span>Nenhuma mensalidade vence nos próximos 7 dias.</span></div>
    <?php else: ?>
      <ul class="plist">
      <?php foreach ($vencendo as $c): $isToday = $c['next_due_date'] === today(); ?>
        <li><span class="date<?= $isToday ? ' date--today' : '' ?>"><b><?= h(date('d', strtotime($c['next_due_date']))) ?></b><small><?= $isToday ? 'hoje' : MESES[(int) date('n', strtotime($c['next_due_date'])) - 1] ?></small></span>
          <a class="who" href="cliente.php?id=<?= (int) $c['id'] ?>"><span style="min-width:0"><b><?= h($c['name']) ?></b><small><?= h(str_replace('Plano ', '', $c['plan_name'])) ?> · <?= has_card_auto($c) ? 'cartão automático' : 'Pix' ?></small></span></a>
          <span class="side-v"><b><?= h(money((int) $c['price_cents'])) ?></b></span></li>
      <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <?php if ($tirar): ?>
  <section class="card card--bad span-12 rise">
    <?= card_head('Tire do ar: não pagaram', 'power', '', 'bad') ?>
    <ul class="plist">
    <?php foreach ($tirar as $c): ?>
      <li><?= avatar($c['name']) ?><a class="who" href="cliente.php?id=<?= (int) $c['id'] ?>"><span style="min-width:0"><b><?= h($c['name']) ?></b><small>Venceu em <?= h(fmt_date($c['next_due_date'])) ?><?= $c['site_url'] ? ' · ' . h(preg_replace('#^https?://#', '', $c['site_url'])) : '' ?></small></span></a>
        <a class="btn btn--sm" href="<?= h(wa_link($c['whatsapp'])) ?>" target="_blank" rel="noopener"><?= ic('chat') ?><span>WhatsApp</span></a></li>
    <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <!-- prazos -->
  <section class="card span-4 rise" id="prazos" aria-labelledby="t-prazo">
    <?= card_head('Prazo de 48h', 'clock', '', 'g') ?>
    <?php if (!$prazos): ?>
      <div class="empty"><span class="chip chip--g"><?= ic('file') ?></span><b>Nenhum briefing na fila</b><span>Quando um cliente enviar, o prazo aparece aqui.</span></div>
    <?php else: ?>
      <div class="due">
      <?php foreach ($prazos as $p):
          $passou = (time() - strtotime($p['submitted_at'])) / 3600;
          $resta = 48 - $passou; $late = $resta < 0;
          $emp = (json_decode((string) $p['data'], true) ?: [])['empresa'] ?? ''; ?>
        <div class="due__item<?= $late ? ' is-late' : '' ?>">
          <?= avatar($p['name'], 'av--sm') ?>
          <a class="who" href="cliente.php?id=<?= (int) $p['id'] ?>#briefing"><span style="min-width:0"><b><?= h($emp ?: $p['name']) ?></b><small>enviado <?= h(fmt_date($p['submitted_at'], 'd/m H:i')) ?></small></span></a>
          <span class="due__left"><?= $late ? 'passou ' . (int) ceil(-$resta) . 'h' : 'faltam ' . (int) floor($resta) . 'h' ?></span>
          <div class="meter"><i style="width:<?= min(100, max(3, round($passou / 48 * 100))) ?>%"></i></div>
        </div>
      <?php endforeach; ?>
      </div>
      <p class="small muted" style="margin:12px 0 0">O prazo pausa enquanto a copy espera a aprovação do cliente.</p>
    <?php endif; ?>
  </section>

  <!-- leads novos -->
  <section class="card span-4 rise" aria-labelledby="t-novos">
    <?= card_head('Leads novos', 'leads', '<a href="leads.php?s=novo">Todos ' . ic('chevron') . '</a>') ?>
    <?php if (!$novos): ?>
      <div class="empty"><span class="chip"><?= ic('inbox') ?></span><b>Caixa vazia</b><span>Os pedidos da página de vendas chegam aqui.</span></div>
    <?php else: ?>
      <ul class="plist">
      <?php foreach ($novos as $l): ?>
        <li><?= avatar($l['name']) ?><a class="who" href="lead.php?id=<?= (int) $l['id'] ?>"><span style="min-width:0"><b><?= h($l['name']) ?></b><small><?= h(ucfirst($l['plan_slug'])) ?> · <?= h(fmt_dt($l['created_at'])) ?></small></span></a>
          <a class="iconbtn" href="<?= h(wa_link($l['whatsapp'], 'Oi, ' . first_name($l['name']) . '! Aqui é o Cassiano, vi seu pedido do site por assinatura.')) ?>" target="_blank" rel="noopener" aria-label="Chamar <?= h(first_name($l['name'])) ?> no WhatsApp"><?= ic('chat') ?></a></li>
      <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <!-- atividade -->
  <section class="card span-4 rise" aria-labelledby="t-ativ">
    <?= card_head('Atividade recente', 'activity') ?>
    <?php if (!$atividade): ?>
      <div class="empty"><span class="chip"><?= ic('activity') ?></span><b>Nada por aqui ainda</b><span>Pagamentos, e-mails e mudanças aparecem em ordem.</span></div>
    <?php else: ?>
      <ul class="timeline">
      <?php foreach ($atividade as $a): ?><?= timeline_item($a, true) ?>
      <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>
<?php page_end();
