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
    { cat: 'sites', destaque: true, tipo: 'Site institucional · Austrália', nome: 'Navi Occupational Therapy', desc: 'Clínica de terapia ocupacional do outro lado do mundo.', img: 'assets/img/portfolio/navi-ot.webp', url: 'https://naviot.com.au/' },
    { cat: 'sites', tipo: 'Evento de saúde integrativa', nome: 'Congressis', url: 'https://congressis.com.br/' },
    { cat: 'sites', tipo: 'Transportes', nome: 'Difalux', url: 'https://difalux.com.br/' },
    { cat: 'sites', tipo: 'Urologista', nome: 'Dr. André Luiz Nogueira', url: 'https://drandreluiznogueira.com.br/' },
    { cat: 'sites', tipo: 'Advocacia', nome: 'Dra. Emanuelle de Lima', url: 'https://emanuelledelimaadv.com.br/' },
    { cat: 'sites', tipo: 'Pós-graduação', nome: 'Amanda Zanetti', url: 'https://amandazanetti.com.br/pos-graduacao/' },

    { cat: 'sistemas', destaque: true, tipo: 'Painel sob medida', nome: 'Painel de métricas para marketplaces', desc: 'Quanto cada conta realmente fatura, num painel só.', img: '', url: '', placeholder: true },
    { cat: 'sistemas', destaque: true, tipo: 'Portal + painel admin', nome: 'Copa Brasil de Talentos', desc: 'Portal público + painel para gerenciar o torneio inteiro.', img: '', url: '', placeholder: true },

    // Motion: vídeos verticais 9:16 (video = .mp4 otimizado, poster = capa .webp)
    { cat: 'motion', tipo: 'Reel de apresentação', nome: '[CLIENTE]', desc: 'Vídeo vertical apresentando o site.', video: '', poster: '', placeholder: true },
    { cat: 'motion', tipo: 'Reel de apresentação', nome: '[CLIENTE]', desc: 'Vídeo vertical apresentando o site.', video: '', poster: '', placeholder: true },
    { cat: 'motion', tipo: 'Reel de apresentação', nome: '[CLIENTE]', desc: 'Vídeo vertical apresentando o site.', video: '', poster: '', placeholder: true },
    { cat: 'motion', tipo: 'Reel de apresentação', nome: '[CLIENTE]', desc: 'Vídeo vertical apresentando o site.', video: '', poster: '', placeholder: true }
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
