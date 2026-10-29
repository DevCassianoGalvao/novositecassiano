<?php
/**
 * Regras de assinatura:
 *  - lembretes por e-mail (padrão: 7 dias antes, 3 dias antes e no dia);
 *  - 1 dia de tolerância (configurável), depois suspende e avisa o dono;
 *  - pagou depois, volta sozinho, sem taxa de religação;
 *  - sincroniza com o Mercado Pago (webhook + varredura diária).
 */

declare(strict_types=1);

require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/mp.php';

/* ---------- Consultas ---------- */

function customer_find(int $id): ?array
{
    return q_one('SELECT c.*, p.name AS plan_name, p.slug AS plan_slug FROM customers c JOIN plans p ON p.id = c.plan_id WHERE c.id = ?', [$id]);
}

function customer_by_preapproval(string $pid, string $extRef = ''): ?array
{
    $c = q_one('SELECT id FROM customers WHERE mp_preapproval_id = ?', [$pid]);
    if (!$c && preg_match('/^c(\d+)$/', $extRef, $m)) $c = q_one('SELECT id FROM customers WHERE id = ?', [(int) $m[1]]);
    return $c ? customer_find((int) $c['id']) : null;
}

function customer_update(int $id, array $fields): void
{
    if (!$fields) return;
    $set = [];
    $vals = [];
    foreach ($fields as $k => $v) {
        if ($v === 'NOW()') {
            $set[] = "`$k` = NOW()";
        } else {
            $set[] = "`$k` = ?";
            $vals[] = $v;
        }
    }
    $set[] = 'updated_at = NOW()';
    $vals[] = $id;
    q('UPDATE customers SET ' . implode(', ', $set) . ' WHERE id = ?', $vals);
}

function first_name(string $n): string
{
    $p = preg_split('/\s+/', trim($n));
    return $p[0] ?? $n;
}

function reminder_days(): array
{
    $d = array_filter(array_map('trim', explode(',', setting('reminder_days', '7,3,0'))), fn($x) => $x !== '' && ctype_digit($x));
    $d = array_values(array_unique(array_map('intval', $d)));
    rsort($d);
    return $d;
}

function tolerance_days(): int
{
    return max(0, (int) setting('tolerance_days', '1'));
}

function admin_wa(): string
{
    return setting('admin_whatsapp', '5522997560717');
}

/* ---------- E-mails ---------- */

function customer_url(int $id): string
{
    return app_url() . '/admin/cliente.php?id=' . $id;
}

/** @return array{0:string,1:string,2:?array} [assunto, corpo html, cta] */
function email_content(string $kind, array $c, array $v = []): array
{
    $first = h(first_name($c['name']));
    $price = h(money((int) $c['price_cents']));
    $plan = h($c['plan_name'] ?? 'Plano');
    $due = h(fmt_date($c['next_due_date'] ?? null));
    $wa = wa_link(admin_wa(), 'Oi, Cassiano! Sou ' . $c['name'] . ', sobre minha assinatura do site.');
    $ctaWa = ['label' => 'Falar com o Cassiano no WhatsApp', 'url' => $wa];
    $p = fn(string $s) => '<p style="margin:0 0 14px">' . $s . '</p>';

    switch ($kind) {
        case 'link':
            return ['Sua assinatura do site está pronta para ativar',
                $p("Oi, $first!") . $p("Falta só um passo: ativar a assinatura do <b>$plan</b> ($price por mês) pelo Mercado Pago. Leva 1 minuto e o pagamento é feito direto no cartão, com a segurança do Mercado Pago.") . $p('Se tiver qualquer dúvida, é só me chamar no WhatsApp.'),
                ['label' => 'Ativar minha assinatura', 'url' => (string) ($c['mp_init_point'] ?? $wa)]];
        case 'welcome':
            return ['Assinatura confirmada!',
                $p("Oi, $first! Recebi seu pagamento de <b>$price</b>.") . $p('Seu site já entrou na minha fila. Vou te chamar no WhatsApp para alinharmos o briefing e começar.') . $p("A próxima cobrança será em <b>$due</b>."),
                $ctaWa];
        case 'pago':
            return ['Pagamento recebido',
                $p("Oi, $first! Recebi o pagamento de <b>$price</b>. Obrigado!") . $p("Seu próximo vencimento é em <b>$due</b>."), null];
        case 'lembrete':
            $n = (int) ($v['dias'] ?? 0);
            $quando = $n === 0 ? 'vence hoje' : ($n === 1 ? 'vence amanhã' : "vence em $n dias");
            return ["Sua mensalidade $quando",
                $p("Oi, $first!") . $p("A cobrança de <b>$price</b> do seu <b>$plan</b> $quando (<b>$due</b>). Ela acontece automaticamente no cartão cadastrado no Mercado Pago.") . $p('Confira se o cartão está com limite e dentro da validade. Para trocar o cartão ou tirar dúvidas, é só falar comigo.'),
                $ctaWa];
        case 'atraso':
            $lim = h(fmt_date(date('Y-m-d', strtotime(($c['next_due_date'] ?? today()) . ' +' . tolerance_days() . ' day'))));
            return ['Não consegui cobrar sua mensalidade',
                $p("Oi, $first!") . $p("Não consegui processar a cobrança de <b>$price</b> que venceu em <b>$due</b>.") . $p("Você tem uma tolerância até <b>$lim</b>. Se o pagamento não entrar até lá, o site sai do ar temporariamente. Assim que o pagamento cair, ele volta na hora, sem taxa nenhuma."),
                ['label' => 'Resolver agora no WhatsApp', 'url' => $wa]];
        case 'suspenso':
            return ['Seu site saiu do ar',
                $p("Oi, $first!") . $p("Como o pagamento de <b>$price</b> (vencimento em <b>$due</b>) não entrou, tirei seu site do ar.") . $p('Não cobro nada para reativar: pagou, o site volta. É só me chamar no WhatsApp ou regularizar pelo Mercado Pago.'),
                ['label' => 'Reativar meu site', 'url' => $wa]];
        case 'reativado':
            return ['Seu site está de volta',
                $p("Oi, $first! O pagamento entrou e seu site já está de volta ao ar.") . $p("Seu próximo vencimento é em <b>$due</b>. Obrigado!"), null];
        case 'cancelado':
            return ['Sua assinatura foi cancelada',
                $p("Oi, $first.") . $p("Sua assinatura do <b>$plan</b> foi cancelada e seu site será desativado.") . $p('Se foi engano ou se quiser voltar um dia, é só me chamar.'),
                $ctaWa];
    }
    return ['Aviso sobre sua assinatura', $p("Oi, $first!"), null];
}

/**
 * Envia um e-mail ao cliente uma única vez por (cliente, tipo, data de referência).
 * Se o envio falhar, o registro é apagado para tentar de novo na próxima rodada.
 */
function notify_customer(array $c, string $kind, string $refDate, array $v = []): bool
{
    if (setting('notify_customer', '1') !== '1') return false;
    if (empty($c['email'])) return false;
    $key = $kind . (isset($v['dias']) ? '_' . (int) $v['dias'] : '');
    $ins = q('INSERT IGNORE INTO notifications (customer_id, kind, ref_date, to_email, ok, created_at) VALUES (?, ?, ?, ?, 0, NOW())', [(int) $c['id'], $key, $refDate, $c['email']]);
    if ($ins->rowCount() === 0) return false;
    $id = (int) db()->lastInsertId();
    [$subject, $body, $cta] = email_content($kind, $c, $v);
    $html = email_layout($subject, $body, $cta);
    [$ok, $err] = brevo_send($c['email'], $c['name'], $subject, $html, email_to_text($html), ['assinatura', $kind]);
    if ($ok) {
        q('UPDATE notifications SET ok = 1 WHERE id = ?', [$id]);
        log_activity('email', "E-mail enviado ao cliente: $subject", (int) $c['id']);
        return true;
    }
    q('DELETE FROM notifications WHERE id = ?', [$id]);
    log_activity('email_erro', "Falha ao enviar \"$subject\": $err", (int) $c['id']);
    return false;
}

function notify_admin(string $subject, string $bodyHtml, ?array $cta = null): void
{
    if (setting('notify_admin', '1') !== '1') return;
    $to = setting('admin_email', env('MAIL_TO'));
    if ($to === '') return;
    $html = email_layout($subject, $bodyHtml, $cta);
    [$ok, $err] = brevo_send($to, env('MAIL_TO_NAME', 'Cassiano'), $subject, $html, email_to_text($html), ['assinatura', 'admin']);
    if (!$ok) log_activity('email_erro', "Falha ao avisar o dono: $err");
}

function admin_summary_html(array $c): string
{
    $row = fn($k, $v) => '<tr><td style="padding:4px 12px 4px 0;color:#7b7590">' . $k . '</td><td style="padding:4px 0"><b>' . $v . '</b></td></tr>';
    return '<table style="font-size:15px;border-collapse:collapse">'
        . $row('Cliente', h($c['name']))
        . $row('Plano', h(($c['plan_name'] ?? '') . ' · ' . money((int) $c['price_cents'])))
        . $row('WhatsApp', '<a href="' . h(wa_link($c['whatsapp'])) . '">' . h(phone_pretty($c['whatsapp'])) . '</a>')
        . $row('E-mail', h($c['email']))
        . $row('Site', h($c['site_url'] ?: '—'))
        . $row('Vencimento', h(fmt_date($c['next_due_date'] ?? null)))
        . '</table>';
}

/* ---------- Mudanças de estado ---------- */

function set_customer_status(array $c, string $new, string $why): void
{
    $f = ['status' => $new];
    if ($new === 'suspenso') $f['suspended_at'] = 'NOW()';
    if ($new === 'ativo') $f['suspended_at'] = null;
    if ($new === 'cancelado') $f['canceled_at'] = 'NOW()';
    customer_update((int) $c['id'], $f);
    log_activity('status', 'Status: ' . (CUSTOMER_STATUS[$new][0] ?? $new) . ' · ' . $why, (int) $c['id']);
}

function suspend_customer(array $c, string $why = 'pagamento não entrou', bool $alertAdmin = true): void
{
    if ($c['status'] === 'suspenso' || $c['status'] === 'cancelado') return;
    set_customer_status($c, 'suspenso', $why);
    $c = customer_find((int) $c['id']);
    notify_customer($c, 'suspenso', (string) ($c['next_due_date'] ?? today()));
    if (!$alertAdmin) return;
    notify_admin($c['name'] . ' não pagou', '<p style="margin:0 0 14px"><b>' . h($c['name']) . '</b> não pagou e passou da tolerância. Tire o site do ar.</p>' . admin_summary_html($c)
        . '<p style="margin:14px 0 0">Se ele pagar depois, o sistema reativa sozinho e avisa você.</p>',
        ['label' => 'Abrir o cliente no painel', 'url' => customer_url((int) $c['id'])]);
}

function reactivate_customer(array $c, string $why, ?string $newDue = null): void
{
    $f = [];
    if ($newDue) $f['next_due_date'] = $newDue;
    if ($f) customer_update((int) $c['id'], $f);
    set_customer_status($c, 'ativo', $why);
    $c = customer_find((int) $c['id']);
    notify_customer($c, 'reativado', today());
}

function cancel_customer(array $c, string $why, bool $cancelOnMp = true): string
{
    $msg = '';
    if ($cancelOnMp && $c['mp_preapproval_id'] && mp_connected()) {
        $r = mp_set_preapproval_status($c['mp_preapproval_id'], 'cancelled');
        if ($r['error'] !== '') $msg = 'Não consegui cancelar no Mercado Pago (' . $r['error'] . '). Cancele por lá também.';
        else customer_update((int) $c['id'], ['mp_status' => 'cancelled']);
    }
    if ($c['status'] !== 'cancelado') {
        set_customer_status($c, 'cancelado', $why);
        $c = customer_find((int) $c['id']);
        notify_customer($c, 'cancelado', today());
        notify_admin($c['name'] . ' cancelou a assinatura', '<p style="margin:0 0 14px">Assinatura cancelada (' . h($why) . '). Desative o site.</p>' . admin_summary_html($c),
            ['label' => 'Abrir o cliente no painel', 'url' => customer_url((int) $c['id'])]);
    }
    return $msg;
}

/**
 * Registra um pagamento recebido e atualiza o cliente.
 * Retorna false se o pagamento já tinha sido registrado (idempotente).
 */
function apply_payment(array $c, int $amountCents, string $method, ?string $mpRef, ?string $referenceDate, ?string $paidAt = null, ?string $note = null, bool $syncAfter = true): bool
{
    $c = customer_find((int) $c['id']) ?? $c;
    $paidAt = $paidAt ?: date('Y-m-d H:i:s');

    if ($method === 'mercadopago') {
        $dup = q_val("SELECT COUNT(*) FROM payments WHERE customer_id = ? AND method = 'mercadopago' AND status = 'pago' AND ABS(TIMESTAMPDIFF(HOUR, COALESCE(paid_at, created_at), ?)) < 36", [(int) $c['id'], $paidAt]);
        if ((int) $dup > 0) return false;
    }
    $first = (int) q_val("SELECT COUNT(*) FROM payments WHERE customer_id = ? AND status = 'pago'", [(int) $c['id']]) === 0;

    try {
        q('INSERT INTO payments (customer_id, mp_ref, amount_cents, status, method, reference_date, paid_at, note, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [(int) $c['id'], $mpRef, $amountCents, 'pago', $method, $referenceDate, $paidAt, $note]);
    } catch (PDOException $e) {
        if ((string) $e->getCode() === '23000') return false; // mp_ref repetido
        throw $e;
    }

    $prev = $c['status'];
    $due = $c['next_due_date'];
    $base = ($due && $due >= today()) ? $due : today();
    $fields = ['next_due_date' => add_months($base, 1), 'status' => 'ativo', 'suspended_at' => null];
    if (empty($c['started_at'])) $fields['started_at'] = 'NOW()';
    customer_update((int) $c['id'], $fields);
    log_activity('pagamento', 'Pagamento recebido: ' . money($amountCents) . ' (' . ($method === 'manual' ? 'manual' : 'Mercado Pago') . ')', (int) $c['id']);

    if ($prev !== 'ativo') log_activity('status', 'Status: Ativo · pagamento recebido', (int) $c['id']);

    if ($syncAfter && $method === 'mercadopago' && $c['mp_preapproval_id']) {
        try {
            mp_sync_customer(customer_find((int) $c['id']));
        } catch (Throwable $e) {
            error_log('[assinaturas] sync pós-pagamento: ' . $e->getMessage());
        }
    }

    $c = customer_find((int) $c['id']);
    if ($first) {
        notify_customer($c, 'welcome', '1970-01-01');
        notify_admin('Nova assinatura ativa: ' . $c['name'], '<p style="margin:0 0 14px">Entrou pagamento de <b>' . h(money($amountCents)) . '</b>. Hora de começar o briefing.</p>' . admin_summary_html($c),
            ['label' => 'Abrir o cliente no painel', 'url' => customer_url((int) $c['id'])]);
    } elseif ($prev === 'suspenso' || $prev === 'atraso' || $prev === 'cancelado') {
        notify_customer($c, 'reativado', today());
        notify_admin($c['name'] . ' pagou e voltou', '<p style="margin:0 0 14px">Pagamento recebido. ' . ($prev === 'suspenso' ? 'Pode colocar o site de volta no ar.' : 'Estava em atraso e regularizou.') . '</p>' . admin_summary_html($c),
            ['label' => 'Abrir o cliente no painel', 'url' => customer_url((int) $c['id'])]);
    } else {
        notify_customer($c, 'pago', (string) $c['next_due_date']);
    }
    return true;
}

/* ---------- Mercado Pago: sincronização ---------- */

/** @return array{0:bool,1:string} */
function mp_sync_customer(array $c): array
{
    if (empty($c['mp_preapproval_id'])) return [false, 'Cliente sem assinatura no Mercado Pago.'];
    $r = mp_get_preapproval($c['mp_preapproval_id']);
    if ($r['error'] !== '') return [false, $r['error']];
    $b = $r['body'];
    $id = (int) $c['id'];
    $status = (string) ($b['status'] ?? '');

    $upd = [];
    if ($status !== '' && $status !== $c['mp_status']) {
        $upd['mp_status'] = $status;
        log_activity('mp', 'Mercado Pago: assinatura ' . $status, $id);
    }
    if (!empty($b['init_point']) && $b['init_point'] !== $c['mp_init_point']) $upd['mp_init_point'] = (string) $b['init_point'];
    if ($upd) customer_update($id, $upd);

    // cobranças novas (cobre webhook perdido)
    $sum = $b['summarized'] ?? [];
    $qty = (int) ($sum['charged_quantity'] ?? 0);
    if ($qty > (int) $c['mp_charged_qty']) {
        customer_update($id, ['mp_charged_qty' => $qty]);
        $amount = (int) round(((float) ($sum['last_charged_amount'] ?? ((int) $c['price_cents'] / 100))) * 100);
        $paidAt = !empty($sum['last_charged_date']) ? date('Y-m-d H:i:s', (int) strtotime((string) $sum['last_charged_date'])) : null;
        apply_payment(customer_find($id), $amount, 'mercadopago', 'pq_' . $c['mp_preapproval_id'] . '_' . $qty, mp_date_br($sum['last_charged_date'] ?? null), $paidAt, null, false);
    }

    $c = customer_find($id);
    if ($status === 'cancelled') {
        if ($c['status'] !== 'cancelado') cancel_customer($c, 'cancelada no Mercado Pago', false);
    } elseif ($status === 'authorized') {
        $next = mp_date_br($b['next_payment_date'] ?? null);
        if ($next && $next !== $c['next_due_date'] && $c['status'] !== 'pendente') {
            customer_update($id, ['next_due_date' => $next]);
        }
    }
    return [true, 'Sincronizado (Mercado Pago: ' . ($status ?: 'sem status') . ').'];
}

/** Aviso de cobrança recorrente (topic subscription_authorized_payment). */
function mp_handle_authorized_payment(string $apId): string
{
    $r = mp_get_authorized_payment($apId);
    if ($r['error'] !== '') return 'erro: ' . $r['error'];
    $b = $r['body'];
    $c = customer_by_preapproval((string) ($b['preapproval_id'] ?? ''), (string) ($b['external_reference'] ?? ''));
    if (!$c) return 'cliente não encontrado';
    $payStatus = (string) ($b['payment']['status'] ?? '');
    $apStatus = (string) ($b['status'] ?? '');
    $amount = (int) round(((float) ($b['transaction_amount'] ?? ((int) $c['price_cents'] / 100))) * 100);
    $refDate = mp_date_br($b['debit_date'] ?? null);

    if ($payStatus === 'approved' || ($apStatus === 'processed' && $payStatus === '')) {
        $paidAt = !empty($b['last_modified']) ? date('Y-m-d H:i:s', (int) strtotime((string) $b['last_modified'])) : null;
        $new = apply_payment($c, $amount, 'mercadopago', 'ap_' . $apId, $refDate, $paidAt);
        return $new ? 'pagamento registrado' : 'pagamento já registrado';
    }
    if (in_array($payStatus, ['rejected', 'cancelled'], true) || $apStatus === 'recycling') {
        $ref = 'apf_' . $apId . '_' . (int) ($b['retry_attempt'] ?? 0);
        try {
            q('INSERT INTO payments (customer_id, mp_ref, amount_cents, status, method, reference_date, paid_at, note, created_at) VALUES (?, ?, ?, ?, ?, ?, NULL, ?, NOW())',
                [(int) $c['id'], $ref, $amount, 'falhou', 'mercadopago', $refDate, 'cobrança recusada (tentativa ' . (int) ($b['retry_attempt'] ?? 0) . ')']);
            log_activity('pagamento', 'Cobrança recusada pelo Mercado Pago (vai tentar de novo)', (int) $c['id']);
        } catch (PDOException $e) {
        }
        return 'cobrança falhou';
    }
    return 'status ' . $apStatus . '/' . $payStatus;
}

/** Cria a assinatura no Mercado Pago e guarda o link de pagamento. @return array{0:bool,1:string} */
function mp_generate_link(array $c): array
{
    $c = customer_find((int) $c['id']);
    $r = mp_create_preapproval($c, (string) $c['plan_name']);
    if ($r['error'] !== '' || empty($r['body']['id'])) return [false, $r['error'] !== '' ? $r['error'] : 'Resposta inesperada do Mercado Pago.'];
    $b = $r['body'];
    customer_update((int) $c['id'], [
        'mp_preapproval_id' => (string) $b['id'],
        'mp_init_point'     => (string) ($b['init_point'] ?? ''),
        'mp_status'         => (string) ($b['status'] ?? 'pending'),
        'billing_mode'      => 'mercadopago',
    ]);
    log_activity('mp', 'Link de assinatura gerado no Mercado Pago', (int) $c['id']);
    return [true, (string) ($b['init_point'] ?? '')];
}

/* ---------- Rotina diária (cron) ---------- */

/** @return string[] */
function run_daily(): array
{
    $log = [];
    $today = today();
    $tol = tolerance_days();
    $rem = reminder_days();

    // 1) Varredura no Mercado Pago (pega webhook perdido)
    if (mp_connected()) {
        $list = q_all("SELECT id FROM customers WHERE mp_preapproval_id IS NOT NULL AND status <> 'cancelado' ORDER BY id LIMIT 200");
        $n = 0;
        foreach ($list as $row) {
            $c = customer_find((int) $row['id']);
            [$ok, $m] = mp_sync_customer($c);
            if (!$ok) $log[] = "sync #{$c['id']} falhou: $m";
            $n++;
        }
        $log[] = "Mercado Pago: $n assinatura(s) conferida(s).";
    } else {
        $log[] = 'Mercado Pago não conectado: varredura ignorada.';
    }

    // 2) Lembretes, atraso e suspensão
    $sent = 0;
    $rows = q_all("SELECT id FROM customers WHERE status IN ('ativo','atraso') AND next_due_date IS NOT NULL ORDER BY next_due_date");
    foreach ($rows as $row) {
        $c = customer_find((int) $row['id']);
        $d = days_between($today, $c['next_due_date']); // >0 futuro, 0 hoje, <0 atrasado
        if ($d >= 0) {
            if ($c['status'] === 'atraso') set_customer_status($c, 'ativo', 'vencimento regularizado');
            if (in_array($d, $rem, true) && notify_customer($c, 'lembrete', $c['next_due_date'], ['dias' => $d])) $sent++;
            continue;
        }
        $late = -$d;
        if ($late <= $tol) {
            if ($c['status'] === 'ativo') {
                set_customer_status($c, 'atraso', "venceu em {$c['next_due_date']}, tolerância de $tol dia(s)");
                $c = customer_find((int) $c['id']);
            }
            if (notify_customer($c, 'atraso', $c['next_due_date'])) $sent++;
        } else {
            suspend_customer($c, "passou {$tol} dia(s) de tolerância sem pagamento");
            $log[] = "Suspenso: #{$c['id']} {$c['name']}";
        }
    }
    $log[] = "E-mails enviados nesta rodada: $sent.";

    // 3) Limpeza
    q('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 2 DAY)');
    q('DELETE FROM webhook_log WHERE created_at < (NOW() - INTERVAL 60 DAY)');
    $log[] = 'Limpeza concluída.';
    return $log;
}
