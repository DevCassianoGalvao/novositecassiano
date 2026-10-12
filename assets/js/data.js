/* =========================================================
   CONTEÚDO EDITÁVEL DO SITE
   Edite só este arquivo para trocar portfólio, vídeos e depoimentos.
   Itens com placeholder: true aparecem com a marcação "[...]".
   ========================================================= */
window.SITE_DATA = {
  // WhatsApp com DDI + DDD, só números
  whatsapp: '5522997560717',

  // Endpoint PHP que envia o lead por e-mail (Brevo)
  endpoint: 'api/lead.php',

  /* ---------- PORTFÓLIO ----------
     cat: 'sites' | 'sistemas' | 'motion'
     img: caminho do print (ex.: 'assets/img/portfolio/ana-rangel.webp'), 16:10
     url: link do site no ar (deixe '' para desativar o botão)
     Para motion: video = 'assets/video/arquivo.mp4' (vertical 9:16) e poster = imagem de capa
  */
  projetos: [
    // destaque: true = card grande com print. Os outros aparecem na lista "Mais sites no ar".
    { cat: 'sites', destaque: true, tipo: 'Site institucional', nome: 'Vereador Maicon Gonçalves', desc: 'Site oficial do mandato, com projetos, programas e notícias.', img: 'assets/img/portfolio/maicon-goncalves.webp', url: 'https://maicongoncalves.com.br/' },
    { cat: 'sites', destaque: true, tipo: 'Site institucional', nome: 'Ana Rangel Arquitetura', desc: 'Preto e branco, cada scroll pensado.', img: 'assets/img/portfolio/ana-rangel.webp', url: 'https://www.anarangel.com.br/' },
    { cat: 'sites', destaque: true, tipo: 'Landing page', nome: 'Lamparina Hub', desc: 'Página de vendas para uma consultoria comercial.', img: 'assets/img/portfolio/lamparina-hub.webp', url: 'https://www.lamparinahub.com/' },
    { cat: 'sites', destaque: true, tipo: 'Site de produto · SaaS', nome: 'TechSallus', desc: 'Site de um sistema de gestão para clínicas e hospitais.', img: 'assets/img/portfolio/techsallus.webp', url: 'https://techsallus.com.br/techsallus/' },
    { cat: 'sites', tipo: 'Clínica · Austrália', nome: 'Navi Occupational Therapy', url: 'https://naviot.com.au/' },
    { cat: 'sites', tipo: 'Evento de saúde integrativa', nome: 'Congressis', url: 'https://congressis.com.br/' },
    { cat: 'sites', tipo: 'Transportes', nome: 'Difalux', url: 'https://difalux.com.br/' },
    { cat: 'sites', tipo: 'Urologista', nome: 'Dr. André Luiz Nogueira', url: 'https://drandreluiznogueira.com.br/' },
    { cat: 'sites', tipo: 'Advocacia', nome: 'Dra. Emanuelle de Lima', url: 'https://emanuelledelimaadv.com.br/' },
    { cat: 'sites', tipo: 'Pós-graduação', nome: 'Amanda Zanetti', url: 'https://amandazanetti.com.br/pos-graduacao/' },

    // Sistemas: imgs = galeria de prints (troca sozinha). wide = card na largura toda.
    { cat: 'sistemas', destaque: true, wide: true, tipo: 'Sistema web sob medida', nome: 'Torneio Online Web', desc: 'Sistema de gestão de torneios e campeonatos amadores: painel administrativo, cadastro de atletas e portal público do campeonato.', imgs: ['assets/img/portfolio/torneio-painel.webp', 'assets/img/portfolio/torneio-atletas.webp', 'assets/img/portfolio/torneio-portal.webp'], legendas: ['Painel administrativo', 'Cadastro de atletas', 'Portal público do campeonato'], url: '' },
    { cat: 'sistemas', destaque: true, wide: true, tipo: 'Painel sob medida', nome: 'Painel de métricas para marketplaces', desc: 'Faturamento, pedidos e ticket médio de cada cliente em todos os marketplaces, num painel só. Prints com dados ilustrativos.', imgs: ['assets/img/portfolio/painel-comparativo.webp', 'assets/img/portfolio/painel-cliente.webp', 'assets/img/portfolio/painel-graficos.webp'], legendas: ['Comparativo entre clientes', 'Visão do cliente', 'Gráficos por marketplace'], url: '' },

    // Motion: vídeos verticais 9:16 otimizados (540x960). Ordem: sites primeiro, campanhas depois.
    { cat: 'motion', tipo: 'Reel de site', nome: 'Congressis', video: 'assets/video/motion/reel-congressis.mp4', poster: 'assets/video/motion/reel-congressis.webp' },
    { cat: 'motion', tipo: 'Reel de site', nome: 'Ana Rangel Arquitetura', video: 'assets/video/motion/reel-ana-rangel.mp4', poster: 'assets/video/motion/reel-ana-rangel.webp' },
    { cat: 'motion', tipo: 'Reel de site', nome: 'Vereador Maicon Gonçalves', video: 'assets/video/motion/reel-maicon-goncalves.mp4', poster: 'assets/video/motion/reel-maicon-goncalves.webp' },
    { cat: 'motion', tipo: 'Reel de site', nome: 'Dr. André Luiz Nogueira', video: 'assets/video/motion/reel-andre-nogueira.mp4', poster: 'assets/video/motion/reel-andre-nogueira.webp' },
    { cat: 'motion', tipo: 'Reel de site', nome: 'Lamparina Hub', video: 'assets/video/motion/reel-lamparina-hub.mp4', poster: 'assets/video/motion/reel-lamparina-hub.webp' },
    { cat: 'motion', tipo: 'Reel de site', nome: 'Navi Occupational Therapy', video: 'assets/video/motion/reel-navi-ot.mp4', poster: 'assets/video/motion/reel-navi-ot.webp' },
    { cat: 'motion', tipo: 'Campanha', nome: 'Luiz Lima e Welbert Pedro', video: 'assets/video/motion/campanha-estilo-minecraft.mp4', poster: 'assets/video/motion/campanha-estilo-minecraft.webp' },
    { cat: 'motion', tipo: 'Campanha', nome: 'Não esquece', video: 'assets/video/motion/campanha-nao-esquece.mp4', poster: 'assets/video/motion/campanha-nao-esquece.webp' },
    { cat: 'motion', tipo: 'Campanha · vídeo com IA', nome: 'Welbert Pedro', video: 'assets/video/motion/campanha-welbert-ia.mp4', poster: 'assets/video/motion/campanha-welbert-ia.webp' },
    { cat: 'motion', tipo: 'Campanha', nome: 'Conversa no WhatsApp', video: 'assets/video/motion/campanha-whatsapp.mp4', poster: 'assets/video/motion/campanha-whatsapp.webp' },
    { cat: 'motion', tipo: 'Campanha', nome: 'A colinha', video: 'assets/video/motion/campanha-colinha.mp4', poster: 'assets/video/motion/campanha-colinha.webp' },
    { cat: 'motion', tipo: 'Campanha', nome: 'Luiz Lima · 3030', video: 'assets/video/motion/campanha-luiz-lima.mp4', poster: 'assets/video/motion/campanha-luiz-lima.webp' },
    { cat: 'motion', tipo: 'Campanha', nome: 'Welbert Pedro · 30300', video: 'assets/video/motion/campanha-welbert-pedro.mp4', poster: 'assets/video/motion/campanha-welbert-pedro.webp' }
  ],

  /* ---------- DEPOIMENTOS ----------
     youtube: só o ID do vídeo (ex.: 'dQw4w9WgXcQ'). Aceita também o link completo.
     frase: uma linha tirada do próprio vídeo (não inventar). Vazia = não aparece.
     empresa: opcional. formato: 'short' (vertical) ou 'video' (horizontal).
     Vídeo hospedado no site: use video + poster no lugar de youtube.
  */
  depoimentos: [
    { nome: 'Weslen Ruiz', empresa: 'Weslen Ruiz Estúdio Fotográfico', frase: '', video: 'assets/video/depoimento-weslen-ruiz.mp4', poster: 'assets/video/depoimento-weslen-ruiz.webp' },
    { nome: 'Jhoni Ricardo', empresa: '', frase: '', youtube: 'https://www.youtube.com/watch?v=funP2N_TWlA', formato: 'short' },
    { nome: 'Clara Fleiuss', empresa: '', frase: '', youtube: 'https://www.youtube.com/shorts/gjajgHXa54c', formato: 'short' },
    { nome: 'Amanda Zanetti', empresa: '', frase: '', youtube: 'https://www.youtube.com/shorts/IBwge3_1j6Q', formato: 'short' },
    { nome: 'Thallis Ventura', empresa: '', frase: '', youtube: 'https://www.youtube.com/shorts/2WuXA6MjnOU', formato: 'short' },
    { nome: 'Vanessa Fuster', empresa: '', frase: '', youtube: 'https://www.youtube.com/shorts/s-PqqI48OxA', formato: 'short' },
    { nome: 'Rafa Roni', empresa: '', frase: '', youtube: 'https://www.youtube.com/shorts/9bBZajY_WKI', formato: 'short' }
  ]
};
