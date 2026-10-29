<?php
/**
 * Briefing do site (9 etapas): estrutura dos campos, validação, relatório e arquivos.
 * A mesma estrutura alimenta o formulário do cliente (JS), o painel, o TXT e os e-mails.
 */

declare(strict_types=1);

const BRIEFING_MAX_FILE = 15 * 1024 * 1024;
const BRIEFING_MAX_FILES = 80;
const BRIEFING_EXT = ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif', 'pdf', 'doc', 'docx', 'zip', 'ai', 'psd', 'eps'];

function briefing_segments(): array
{
    return [
        'Saúde' => ['Médico', 'Dentista', 'Psicólogo', 'Fisioterapeuta', 'Nutricionista', 'Fonoaudiólogo', 'Clínica', 'Laboratório', 'Farmácia'],
        'Beleza e estética' => ['Salão', 'Barbearia', 'Clínica de estética', 'Manicure', 'Maquiagem', 'Sobrancelha e cílios'],
        'Bem-estar e fitness' => ['Academia', 'Personal trainer', 'Pilates', 'Yoga', 'Artes marciais'],
        'Alimentação' => ['Restaurante', 'Lanchonete', 'Pizzaria', 'Hamburgueria', 'Confeitaria', 'Cafeteria', 'Delivery', 'Marmitaria'],
        'Jurídico e finanças' => ['Advocacia', 'Contabilidade', 'Consultoria financeira', 'Seguros', 'Crédito'],
        'Imóveis e construção' => ['Imobiliária', 'Corretor', 'Arquitetura', 'Engenharia', 'Construtora', 'Reformas', 'Marcenaria', 'Decoração de interiores'],
        'Serviços técnicos' => ['Eletricista', 'Encanador', 'Ar-condicionado', 'Chaveiro', 'Limpeza', 'Dedetização', 'Assistência técnica'],
        'Automotivo' => ['Oficina', 'Estética automotiva', 'Lava-jato', 'Autopeças', 'Loja de veículos', 'Auto escola'],
        'Educação' => ['Escola', 'Curso livre', 'Idiomas', 'Aulas particulares', 'Cursos online'],
        'Eventos' => ['Buffet', 'Fotografia', 'Filmagem', 'Decoração', 'Cerimonial', 'Espaço de eventos', 'DJ e música'],
        'Moda e varejo' => ['Loja de roupas', 'Calçados', 'Acessórios', 'Ótica', 'Joalheria', 'Papelaria', 'Loja de presentes'],
        'Pet' => ['Pet shop', 'Veterinário', 'Banho e tosa', 'Adestramento'],
        'Turismo e hospedagem' => ['Pousada', 'Hotel', 'Camping', 'Agência de viagens', 'Passeios'],
        'Tecnologia e marketing' => ['Agência', 'Desenvolvimento de software', 'TI', 'Social media'],
        'Indústria e B2B' => ['Fábrica', 'Distribuidora', 'Atacado', 'Logística', 'Transportadora'],
        'Consultoria e serviços profissionais' => ['Consultoria', 'Coaching', 'RH', 'Tradução', 'Despachante'],
        'Terceiro setor e religioso' => ['ONG', 'Associação', 'Igreja', 'Projeto social'],
        'Política' => ['Mandato', 'Candidato', 'Gabinete'],
        'Outro' => ['Outro'],
    ];
}

/**
 * Etapas e campos. Tipos: text, tel, email, area, select, radio, check, checks, list, colors, refs, files, fixed.
 * 'when' = lista de condições [campo, op, valor] (todas precisam valer); ops: eq, in, has.
 * 'unless' = o campo deixa de ser obrigatório se algum destes campos estiver preenchido.
 */
function briefing_steps(): array
{
    $secoes = [
        ['abertura', 'Abertura (topo)', 'A principal mensagem ou oferta da empresa'],
        ['sobre', 'Sobre', 'História, quem está por trás, valores'],
        ['servicos', 'Serviços ou produtos', 'O que cada um resolve para o cliente'],
        ['diferenciais', 'Diferenciais', 'De 3 a 5 motivos para escolher a empresa'],
        ['galeria', 'Galeria ou portfólio', 'Fotos de trabalhos (envie na etapa Arquivos)'],
        ['depoimentos', 'Depoimentos', 'Nome do cliente + texto, ou link das avaliações do Google'],
        ['faq', 'Perguntas frequentes', 'Dúvidas que os clientes mais fazem'],
        ['localizacao', 'Localização', 'Endereço para o mapa'],
        ['contato', 'Contato', 'Confirmado na etapa de contato'],
    ];
    $secFields = [];
    foreach ($secoes as [$k, $l, $dica]) {
        $secFields[] = ['key' => 'sec_' . $k, 'label' => $l, 'type' => 'area', 'hint' => $dica . '. Em tópicos, não precisa escrever bonito.', 'when' => [['copia', 'eq', 'criar'], ['formato', 'eq', 'onepage'], ['secoes', 'has', $k]]];
    }
    $secOpts = array_map(fn($s) => [$s[0], $s[1]], $secoes);

    $estilos = [
        ['dark', 'Dark elegante', 'Fundo escuro, ar premium: estética, barbearia, advocacia', 'linear-gradient(135deg,#0b0b10,#2a1f3d)'],
        ['light', 'Light clean', 'Claro, muito respiro: saúde, consultórios, serviços', 'linear-gradient(135deg,#ffffff,#e6f2ef)'],
        ['minimal', 'Minimalista', 'Pouca cor, foco no conteúdo: arquitetura, design, fotografia', 'linear-gradient(135deg,#f4f4f4,#bdbdbd)'],
        ['tech', 'Tecnológico', 'Formas geométricas, brilho, cara de inovação: tecnologia, startups', 'linear-gradient(135deg,#0a1230,#2b6bff 60%,#00e5ff)'],
        ['vintage', 'Vintage', 'Texturas e fontes clássicas: cafeteria, artesanal, barbearia tradicional', 'linear-gradient(135deg,#d9c3a0,#8a5a35)'],
        ['sofisticado', 'Sofisticado', 'Serifas finas, dourado ou neutros: joalheria, eventos, imóveis de alto padrão', 'linear-gradient(135deg,#1c1a17,#b8975a)'],
        ['vibrante', 'Vibrante', 'Cores fortes e alegria: infantil, alimentação, fitness', 'linear-gradient(135deg,#ff3d81,#ffb800 55%,#00d4ff)'],
        ['corporativo', 'Corporativo', 'Sóbrio e confiável: indústria, contabilidade, consultoria', 'linear-gradient(135deg,#0f2a4a,#5d7a99)'],
        ['natural', 'Natural', 'Tons de terra e verde: bem-estar, agro, sustentabilidade', 'linear-gradient(135deg,#3b5a3a,#c9b48a)'],
    ];
    $fontes = [
        ['moderna', 'Moderna', 'Limpa e atual', "'Space Grotesk',sans-serif"],
        ['classica', 'Clássica', 'Serifada e tradicional', "'Playfair Display',serif"],
        ['elegante', 'Elegante', 'Fina e refinada', "'Cormorant Garamond',serif"],
        ['tecnologica', 'Tecnológica', 'Geométrica, cara de código', "'Space Mono',monospace"],
        ['amigavel', 'Amigável', 'Arredondada e acolhedora', "'Nunito',sans-serif"],
    ];
    $simnao = fn(string $k, string $l, string $sim) => ['key' => $k, 'label' => $l, 'type' => 'radio', 'req' => true, 'options' => [['sim', $sim], ['nao', 'Não']]];

    return [
        ['id' => 'dados', 'title' => 'Seus dados', 'intro' => 'Já preenchi com o que você informou no pagamento. Confira e corrija se precisar.', 'fields' => [
            ['key' => 'nome', 'label' => 'Nome', 'type' => 'text', 'req' => true],
            ['key' => 'whatsapp', 'label' => 'WhatsApp', 'type' => 'tel', 'req' => true],
            ['key' => 'email', 'label' => 'E-mail', 'type' => 'email', 'req' => true],
            ['key' => 'plano', 'label' => 'Plano contratado', 'type' => 'fixed'],
        ]],
        ['id' => 'empresa', 'title' => 'Sua empresa', 'fields' => [
            ['key' => 'empresa', 'label' => 'Nome da empresa', 'type' => 'text', 'req' => true],
            ['key' => 'segmento', 'label' => 'Segmento', 'type' => 'select', 'req' => true, 'groups' => briefing_segments()],
            ['key' => 'segmento_outro', 'label' => 'Qual é o segmento?', 'type' => 'text', 'req' => true, 'when' => [['segmento', 'eq', 'Outro']]],
            ['key' => 'atende', 'label' => 'Onde você atende', 'type' => 'text', 'hint' => 'Cidade e regiões.'],
            ['key' => 'online', 'label' => 'Atendo online, no Brasil todo', 'type' => 'check'],
            ['key' => 'endereco', 'label' => 'Endereço (se tiver atendimento presencial)', 'type' => 'text'],
            ['key' => 'descricao', 'label' => 'O que a sua empresa faz, em 2 ou 3 frases', 'type' => 'area', 'req' => true],
            ['key' => 'servicos', 'label' => 'Principais serviços ou produtos', 'type' => 'list', 'req' => true, 'hint' => 'Um item por linha. Use o botão para adicionar mais.'],
            ['key' => 'cliente_ideal', 'label' => 'Quem é o seu cliente ideal', 'type' => 'area', 'req' => true],
            ['key' => 'diferencial', 'label' => 'Por que o cliente escolhe você e não o concorrente', 'type' => 'area', 'req' => true],
            ['key' => 'busca_google', 'label' => 'Como o seu cliente te procura no Google', 'type' => 'text', 'req' => true, 'hint' => 'Ex.: "dentista em Niterói", "curso de inglês online". Isso ajuda o seu site a ser encontrado.'],
            ['key' => 'tempo', 'label' => 'Há quanto tempo a empresa existe', 'type' => 'text'],
        ]],
        ['id' => 'objetivo', 'title' => 'Objetivo do site', 'fields' => [
            ['key' => 'acao', 'label' => 'Principal ação que o visitante deve fazer', 'type' => 'radio', 'req' => true, 'options' => ['Chamar no WhatsApp', 'Ligar', 'Pedir orçamento', 'Agendar', 'Visitar a loja']],
            ['key' => 'dominio_tem', 'label' => 'Já tem domínio?', 'type' => 'radio', 'req' => true, 'options' => [['sim', 'Sim'], ['nao', 'Não, preciso de ajuda pra registrar']]],
            ['key' => 'dominio_qual', 'label' => 'Qual é o domínio?', 'type' => 'text', 'req' => true, 'when' => [['dominio_tem', 'eq', 'sim']]],
            $simnao('site_tem', 'Já tem site hoje?', 'Sim') + [],
            ['key' => 'site_link', 'label' => 'Link do site atual', 'type' => 'text', 'req' => true, 'when' => [['site_tem', 'eq', 'sim']]],
        ]],
        ['id' => 'copy', 'title' => 'Copy do site', 'intro' => 'Com as suas respostas, eu escrevo uma copy estratégica para o seu nicho: os textos de cada parte do site, pensados para o seu cliente entender o que você faz e entrar em contato. Antes de começar o layout, eu te envio pelo WhatsApp para aprovar.', 'fields' => [
            ['key' => 'copia', 'label' => 'Como você quer os textos do site?', 'type' => 'radio', 'req' => true, 'options' => [['criar', 'Quero que você crie a copy estratégica', 'Recomendado'], ['propria', 'Já tenho a copy pronta e vou enviar']]],
        ]],
        ['id' => 'estrutura', 'title' => 'Estrutura e conteúdo', 'fields' => array_merge([
            ['key' => 'formato', 'label' => 'Como você quer o seu site?', 'type' => 'radio', 'req' => true, 'when' => [['plano', 'eq', 'completo']], 'options' => [['institucional', 'Institucional (até 4 páginas)'], ['onepage', 'One page (uma página só)']]],
            ['key' => 'copia_arquivo', 'label' => 'Envie sua copy (.docx ou .pdf)', 'type' => 'files', 'max' => 3, 'accept' => 'docx,doc,pdf', 'unless' => ['copia_texto'], 'hint' => 'Organize o texto por seção (one page) ou por página (institucional). Se preferir, cole o texto no campo abaixo.', 'when' => [['copia', 'eq', 'propria']]],
            ['key' => 'copia_texto', 'label' => 'Ou cole o texto aqui', 'type' => 'area', 'big' => true, 'when' => [['copia', 'eq', 'propria']]],
            ['key' => 'secoes', 'label' => 'Quais seções você quer no site?', 'type' => 'checks', 'req' => true, 'hint' => 'Não precisa escrever bonito. A copy é comigo.', 'options' => $secOpts, 'when' => [['copia', 'eq', 'criar'], ['formato', 'eq', 'onepage']]],
        ], $secFields, [
            ['key' => 'pag1', 'label' => 'Página 1: Início. O que não pode faltar?', 'type' => 'area', 'req' => true, 'hint' => 'Em tópicos.', 'when' => [['copia', 'eq', 'criar'], ['formato', 'eq', 'institucional']]],
            ['key' => 'pag2_nome', 'label' => 'Página 2: nome', 'type' => 'text', 'req' => true, 'suggest' => ['Sobre', 'Serviços', 'Portfólio', 'Contato'], 'when' => [['copia', 'eq', 'criar'], ['formato', 'eq', 'institucional']]],
            ['key' => 'pag2', 'label' => 'Página 2: o que precisa aparecer', 'type' => 'area', 'req' => true, 'when' => [['copia', 'eq', 'criar'], ['formato', 'eq', 'institucional']]],
            ['key' => 'pag3_nome', 'label' => 'Página 3: nome', 'type' => 'text', 'suggest' => ['Sobre', 'Serviços', 'Portfólio', 'Contato'], 'when' => [['copia', 'eq', 'criar'], ['formato', 'eq', 'institucional']]],
            ['key' => 'pag3', 'label' => 'Página 3: o que precisa aparecer', 'type' => 'area', 'when' => [['copia', 'eq', 'criar'], ['formato', 'eq', 'institucional']]],
            ['key' => 'pag4_nome', 'label' => 'Página 4: nome', 'type' => 'text', 'suggest' => ['Sobre', 'Serviços', 'Portfólio', 'Contato'], 'when' => [['copia', 'eq', 'criar'], ['formato', 'eq', 'institucional']]],
            ['key' => 'pag4', 'label' => 'Página 4: o que precisa aparecer', 'type' => 'area', 'when' => [['copia', 'eq', 'criar'], ['formato', 'eq', 'institucional']]],
            ['key' => 'extra_tem', 'label' => 'Quer mais de 4 páginas?', 'type' => 'radio', 'req' => true, 'hint' => 'Cada página extra custa + R$ 20/mês.', 'options' => [['sim', 'Sim'], ['nao', 'Não']], 'when' => [['copia', 'eq', 'criar'], ['formato', 'eq', 'institucional']]],
            ['key' => 'extra_qtd', 'label' => 'Quantas páginas extras?', 'type' => 'text', 'req' => true, 'when' => [['extra_tem', 'eq', 'sim'], ['formato', 'eq', 'institucional']]],
        ])],
        ['id' => 'estilo', 'title' => 'Estilo visual', 'fields' => [
            ['key' => 'estilo', 'label' => 'Escolha 1 estilo principal', 'type' => 'radio', 'variant' => 'cards', 'req' => true, 'options' => $estilos],
            ['key' => 'fonte', 'label' => 'Fonte', 'type' => 'radio', 'variant' => 'fonts', 'req' => true, 'options' => $fontes],
            ['key' => 'cores_modo', 'label' => 'Cores', 'type' => 'radio', 'req' => true, 'options' => [['marca', 'Usar as cores da minha marca'], ['escolher', 'Não tenho, escolhe pra mim']]],
            ['key' => 'cores', 'label' => 'Cores da marca (até 3)', 'type' => 'colors', 'req' => true, 'when' => [['cores_modo', 'eq', 'marca']]],
            ['key' => 'refs', 'label' => 'Sites que você gosta', 'type' => 'refs', 'hint' => 'Até 3 links e o que você gosta em cada um.'],
            ['key' => 'nao_quero', 'label' => 'O que você não quer no site', 'type' => 'area'],
        ]],
        ['id' => 'arquivos', 'title' => 'Arquivos', 'intro' => 'Sem logo, eu uso o nome da sua empresa com uma tipografia bem escolhida. Sem fotos próprias, eu uso imagens de banco de alta qualidade.', 'fields' => [
            ['key' => 'logo', 'label' => 'Logotipo (PNG ou SVG)', 'type' => 'files', 'max' => 3, 'accept' => 'png,svg,jpg,jpeg,webp,ai,eps,pdf', 'unless' => ['sem_logo']],
            ['key' => 'sem_logo', 'label' => 'Não tenho logo', 'type' => 'check'],
            ['key' => 'fotos', 'label' => 'Fotos da empresa, equipe, produtos ou trabalhos (até 20)', 'type' => 'files', 'max' => 20, 'accept' => 'png,jpg,jpeg,webp,gif'],
            ['key' => 'manual', 'label' => 'Manual de marca, se tiver', 'type' => 'files', 'max' => 3, 'accept' => 'pdf,zip,png,jpg,jpeg,ai,psd,eps'],
            ['key' => 'outros', 'label' => 'Outros arquivos', 'type' => 'files', 'max' => 10, 'accept' => implode(',', BRIEFING_EXT)],
        ]],
        ['id' => 'contato', 'title' => 'Contato e redes', 'fields' => [
            ['key' => 'whatsapp_site', 'label' => 'WhatsApp que vai aparecer no site', 'type' => 'tel', 'req' => true],
            ['key' => 'tel_fixo', 'label' => 'Telefone fixo', 'type' => 'text'],
            ['key' => 'email_contato', 'label' => 'E-mail de contato', 'type' => 'email'],
            ['key' => 'horario', 'label' => 'Horário de atendimento', 'type' => 'text'],
            ['key' => 'instagram', 'label' => 'Instagram', 'type' => 'text'],
            ['key' => 'facebook', 'label' => 'Facebook', 'type' => 'text'],
            ['key' => 'linkedin', 'label' => 'LinkedIn', 'type' => 'text'],
            ['key' => 'tiktok', 'label' => 'TikTok', 'type' => 'text'],
            ['key' => 'youtube', 'label' => 'YouTube', 'type' => 'text'],
            ['key' => 'gmn', 'label' => 'Perfil no Google Meu Negócio (link)', 'type' => 'text'],
        ]],
        ['id' => 'revisao', 'title' => 'Revisão e envio', 'fields' => [
            ['key' => 'ok1', 'label' => 'Entendo que o prazo de 48 horas começa agora e pausa enquanto a copy espera a minha aprovação.', 'type' => 'check', 'req' => true],
            ['key' => 'ok2', 'label' => 'Vou receber a copy do site para aprovar antes do layout.', 'type' => 'check', 'req' => true, 'when' => [['copia', 'eq', 'criar']]],
            ['key' => 'ok3', 'label' => 'Vou receber duas direções visuais e escolher uma, sem misturar as duas.', 'type' => 'check', 'req' => true],
            ['key' => 'ok4', 'label' => 'Tenho direito a uma rodada de ajustes no site, enviada numa lista única.', 'type' => 'check', 'req' => true],
            ['key' => 'ok5', 'label' => 'O site é desenvolvido em código, sem painel de edição. As alterações são feitas pelo Cassiano.', 'type' => 'check', 'req' => true],
        ]],
    ];
}

/** Lista plana dos campos, por chave. */
function briefing_field_map(): array
{
    $m = [];
    foreach (briefing_steps() as $st) foreach ($st['fields'] as $f) $m[$f['key']] = $f;
    return $m;
}

function briefing_cond(array $when, array $d): bool
{
    foreach ($when as [$k, $op, $val]) {
        $v = $d[$k] ?? '';
        if ($op === 'eq' && (string) $v !== (string) $val) return false;
        if ($op === 'in' && !in_array((string) $v, array_map('strval', (array) $val), true)) return false;
        if ($op === 'has' && !(is_array($v) && in_array($val, $v, true))) return false;
    }
    return true;
}

function briefing_filled($v): bool
{
    if (is_array($v)) {
        foreach ($v as $x) if (is_array($x) ? array_filter($x, 'strlen') : trim((string) $x) !== '') return true;
        return false;
    }
    return trim((string) $v) !== '';
}

/** Limpa o que veio do navegador: só chaves conhecidas, com tipo e tamanho controlados. */
function briefing_sanitize(array $in, array $c): array
{
    $out = [];
    foreach (briefing_field_map() as $k => $f) {
        if ($f['type'] === 'fixed' || $f['type'] === 'files') continue;
        $v = $in[$k] ?? null;
        switch ($f['type']) {
            case 'area':
                $out[$k] = mb_substr(trim(strip_tags(str_replace("\r", '', (string) $v))), 0, 4000);
                break;
            case 'check':
                $out[$k] = !empty($v) && $v !== '0' ? '1' : '';
                break;
            case 'checks':
                $allowed = array_map(fn($o) => is_array($o) ? $o[0] : $o, $f['options'] ?? []);
                $out[$k] = array_values(array_intersect($allowed, array_map('strval', (array) $v)));
                break;
            case 'list':
                $out[$k] = array_slice(array_values(array_filter(array_map(fn($x) => clean_text($x, 200), (array) $v), 'strlen')), 0, 30);
                break;
            case 'colors':
                $out[$k] = array_slice(array_values(array_filter(array_map(fn($x) => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $x) ? strtolower((string) $x) : '', (array) $v), 'strlen')), 0, 3);
                break;
            case 'refs':
                $r = [];
                foreach (array_slice((array) $v, 0, 3) as $x) {
                    $u = clean_text(is_array($x) ? ($x['url'] ?? '') : '', 300);
                    $w = clean_text(is_array($x) ? ($x['why'] ?? '') : '', 300);
                    if ($u !== '' || $w !== '') $r[] = ['url' => $u, 'why' => $w];
                }
                $out[$k] = $r;
                break;
            case 'radio':
            case 'select':
                $out[$k] = clean_text($v, 120);
                break;
            default:
                $out[$k] = clean_text($v, 300);
        }
    }
    $out['plano'] = (string) ($c['plan_slug'] ?? '');
    if ($out['plano'] !== 'completo') $out['formato'] = 'onepage';
    return $out;
}

/** Campos obrigatórios que faltam (só os que estão visíveis). Devolve [chave => rótulo]. */
function briefing_missing(array $d, array $files): array
{
    $miss = [];
    foreach (briefing_steps() as $si => $st) {
        foreach ($st['fields'] as $f) {
            if (empty($f['req']) && empty($f['unless'])) continue;
            if (!empty($f['when']) && !briefing_cond($f['when'], $d)) continue;
            $has = $f['type'] === 'files' ? !empty($files[$f['key']]) : briefing_filled($d[$f['key']] ?? '');
            if ($has) continue;
            $alt = false;
            foreach ($f['unless'] ?? [] as $u) if (briefing_filled($d[$u] ?? '') || !empty($files[$u])) $alt = true;
            if ($alt) continue;
            $miss[$f['key']] = ['step' => $si, 'label' => $f['label']];
        }
    }
    return $miss;
}

/* ---------- armazenamento ---------- */

function briefing_find(int $customerId): ?array
{
    $r = q_one('SELECT * FROM briefings WHERE customer_id = ?', [$customerId]);
    if (!$r) return null;
    $r['fields'] = json_decode((string) $r['data'], true) ?: [];
    return $r;
}

function briefing_url(array $c): string
{
    return app_url() . '/site-por-assinatura/briefing/?t=' . $c['portal_token'];
}

function briefing_save(int $customerId, array $data, int $step): void
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $step = max(1, min(9, $step));
    if (q_one('SELECT id FROM briefings WHERE customer_id = ?', [$customerId])) {
        q('UPDATE briefings SET data = ?, step = ?, updated_at = NOW() WHERE customer_id = ?', [$json, $step, $customerId]);
    } else {
        q('INSERT INTO briefings (customer_id, data, step, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())', [$customerId, $json, $step]);
    }
}

/** @return array<string,array> arquivos agrupados por campo */
function briefing_files(int $customerId): array
{
    $out = [];
    foreach (q_all('SELECT * FROM briefing_files WHERE customer_id = ? ORDER BY id', [$customerId]) as $r) $out[$r['field']][] = $r;
    return $out;
}

function briefing_base_dir(): string
{
    $cands = [];
    if (env('UPLOAD_DIR') !== '') $cands[] = rtrim(env('UPLOAD_DIR'), '/');
    $cands[] = dirname(dirname(ADMIN_DIR)) . '/assinaturas-arquivos';
    $cands[] = ADMIN_DIR . '/storage';
    foreach ($cands as $d) {
        if (!is_dir($d)) @mkdir($d, 0750, true);
        if (is_dir($d) && is_writable($d)) {
            if (!is_file($d . '/.htaccess')) @file_put_contents($d . '/.htaccess', "Require all denied\nDeny from all\n");
            return $d;
        }
    }
    throw new RuntimeException('Sem pasta para guardar arquivos');
}

function briefing_dir(int $customerId): string
{
    $d = briefing_base_dir() . '/pedido-' . $customerId;
    if (!is_dir($d)) @mkdir($d, 0750, true);
    return $d;
}

function briefing_ext_ok(string $name, string $accept): ?string
{
    $e = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return in_array($e, explode(',', $accept), true) && in_array($e, BRIEFING_EXT, true) ? $e : null;
}

function briefing_mime(string $name): string
{
    return ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'pdf' => 'application/pdf'][strtolower(pathinfo($name, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
}

function briefing_size(int $b): string
{
    return $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
}

/* ---------- relatório ---------- */

function briefing_opt_label(array $f, string $v): string
{
    foreach ($f['options'] ?? [] as $o) {
        if (is_array($o) ? $o[0] === $v : $o === $v) return is_array($o) ? $o[1] : $o;
    }
    return $v;
}

/** Valor legível de um campo (texto puro, várias linhas quando for lista). */
function briefing_value_text(array $f, array $d, array $files): string
{
    $k = $f['key'];
    $v = $d[$k] ?? '';
    switch ($f['type']) {
        case 'files':
            return implode("\n", array_map(fn($r) => $r['orig_name'] . ' (' . briefing_size((int) $r['size_bytes']) . ')', $files[$k] ?? []));
        case 'check':
            return $v === '1' ? 'Sim' : '';
        case 'checks':
            return implode(', ', array_map(fn($x) => briefing_opt_label($f, (string) $x), (array) $v));
        case 'list':
            return implode("\n", array_map(fn($x) => '- ' . $x, (array) $v));
        case 'colors':
            return implode(', ', (array) $v);
        case 'refs':
            return implode("\n", array_map(fn($r) => trim($r['url'] . ($r['why'] !== '' ? ' — ' . $r['why'] : '')), (array) $v));
        case 'radio':
            return briefing_opt_label($f, (string) $v);
        case 'fixed':
            return $k === 'plano' ? ucfirst((string) $v) : (string) $v;
        default:
            return (string) $v;
    }
}

/**
 * Relatório em blocos: [[título, [[rótulo, valor], ...]], ...] na ordem pedida:
 * pedido, cliente, empresa, objetivo, copy, estrutura, estilo, arquivos, contato.
 */
function briefing_sections(array $c, array $b, array $files): array
{
    $d = $b['fields'] ?? [];
    $map = briefing_field_map();
    $row = function (string $k, ?string $label = null) use ($map, $d, $files): ?array {
        $f = $map[$k];
        if (!empty($f['when']) && !briefing_cond($f['when'], $d)) return null;
        $v = briefing_value_text($f, $d, $files);
        return $v === '' ? null : [$label ?? $f['label'], $v];
    };
    $pick = fn(array $keys) => array_values(array_filter(array_map(fn($k) => is_array($k) ? $row($k[0], $k[1]) : $row($k), $keys)));

    $secKeys = [];
    foreach (briefing_steps() as $st) foreach ($st['fields'] as $f) if (strpos($f['key'], 'sec_') === 0) $secKeys[] = $f['key'];
    $estr = $pick(array_merge(['formato', 'copia_arquivo', 'copia_texto', 'secoes'], $secKeys, ['pag1', 'pag2_nome', 'pag2', 'pag3_nome', 'pag3', 'pag4_nome', 'pag4', 'extra_tem', 'extra_qtd']));

    $nFotos = count($files['fotos'] ?? []);
    $arq = [['Logotipo', !empty($files['logo']) ? implode(', ', array_map(fn($r) => $r['orig_name'], $files['logo'])) : (($d['sem_logo'] ?? '') === '1' ? 'Não tem logo' : 'Não enviou')],
        ['Fotos', $nFotos ? $nFotos . ' arquivo(s)' : 'Nenhuma']];
    foreach (['manual' => 'Manual de marca', 'outros' => 'Outros arquivos'] as $k => $l) if (!empty($files[$k])) $arq[] = [$l, briefing_value_text($map[$k], $d, $files)];
    if (!empty($files['copia_arquivo'])) $arq[] = ['Copy enviada pelo cliente', briefing_value_text($map['copia_arquivo'], $d, $files)];

    return array_values(array_filter([
        ['Pedido', array_values(array_filter([
            ['Plano', ($c['plan_name'] ?? '') . ' · ' . money((int) $c['price_cents']) . '/mês'],
            ['Pedido', '#' . $c['id']],
            $b['submitted_at'] ? ['Enviado em', fmt_dt($b['submitted_at']) . ' (prazo de 48h começa aqui)'] : ['Situação', 'Rascunho, ainda não enviado (etapa ' . (int) $b['step'] . ' de 9)'],
        ]))],
        ['Cliente', array_values(array_filter([$row('nome'), $row('whatsapp'), $row('email')]))],
        ['Empresa', $pick(['empresa', 'segmento', 'segmento_outro', 'atende', 'online', 'endereco', 'descricao', 'servicos', 'cliente_ideal', 'diferencial', 'busca_google', 'tempo'])],
        ['Objetivo do site', $pick(['acao', 'dominio_tem', 'dominio_qual', 'site_tem', 'site_link'])],
        ['Copy', $pick(['copia'])],
        ['Estrutura e conteúdo', $estr],
        ['Estilo visual', $pick(['estilo', 'fonte', 'cores_modo', 'cores', 'refs', 'nao_quero'])],
        ['Arquivos', $arq],
        ['Contato e redes', $pick(['whatsapp_site', 'tel_fixo', 'email_contato', 'horario', 'instagram', 'facebook', 'linkedin', 'tiktok', 'youtube', 'gmn'])],
    ], fn($s) => $s[1]));
}

function briefing_text(array $c, array $b, array $files): string
{
    $o = '# Briefing — Site por assinatura' . "\n" . ($c['business'] ?: $c['name']) . "\n";
    foreach (briefing_sections($c, $b, $files) as [$title, $rows]) {
        $o .= "\n## " . $title . "\n";
        foreach ($rows as [$l, $v]) $o .= (strpos($v, "\n") !== false ? "\n**" . $l . ":**\n" . $v . "\n" : '**' . $l . ':** ' . $v . "\n");
    }
    return $o;
}

function briefing_html(array $c, array $b, array $files): string
{
    $o = '';
    foreach (briefing_sections($c, $b, $files) as [$title, $rows]) {
        $o .= '<h3 style="margin:22px 0 8px;font-size:16px">' . h($title) . '</h3>';
        foreach ($rows as [$l, $v]) $o .= '<p style="margin:0 0 8px"><b>' . h($l) . ':</b><br>' . nl2br(h($v)) . '</p>';
    }
    return $o;
}
