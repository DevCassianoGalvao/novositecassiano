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
    { cat: 'sites', tipo: 'Site institucional', nome: 'Ana Rangel Arquitetura', desc: 'Preto e branco, cada scroll pensado.', img: '', url: '', placeholder: true },
    { cat: 'sites', tipo: 'Landing page', nome: 'Navi Occupational Therapy', desc: 'Landing page para uma clínica na Austrália.', img: '', url: '', placeholder: true },
    { cat: 'sites', tipo: 'Site', nome: '[NOME DO CLIENTE]', desc: '[Descrição de uma linha do projeto]', img: '', url: '', placeholder: true },
    { cat: 'sites', tipo: 'Site', nome: '[NOME DO CLIENTE]', desc: '[Descrição de uma linha do projeto]', img: '', url: '', placeholder: true },

    { cat: 'sistemas', tipo: 'Painel sob medida', nome: 'Painel de métricas para marketplaces', desc: 'Quanto cada conta realmente fatura, num painel só.', img: '', url: '', placeholder: true },
    { cat: 'sistemas', tipo: 'Portal + painel admin', nome: 'Copa Brasil de Talentos', desc: 'Portal público + painel para gerenciar o torneio inteiro.', img: '', url: '', placeholder: true },

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
