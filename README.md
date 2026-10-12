# cassianogalvao.com.br

Landing page do Cassiano Galvão: HTML, CSS e JS puros, com GSAP + ScrollTrigger + Lenis via CDN e um endpoint PHP para o formulário.

## Estrutura

```
index.html            página
assets/css/style.css  estilos (tokens de cor e fonte no topo)
assets/js/data.js     CONTEÚDO EDITÁVEL: portfólio, vídeos, depoimentos, WhatsApp
assets/js/main.js     animações e interações
assets/img/           logo, favicon e fotos
api/lead.php          recebe o formulário e envia o e-mail pela Brevo
.env.example          modelo das credenciais
.htaccess             HTTPS, cache e bloqueio de arquivos sensíveis
```

## Publicar no servidor

Passo a passo com Git no cPanel: veja **DEPLOY.md**.


1. Envie todos os arquivos para a pasta pública do domínio (ex.: `public_html`).
2. Copie `.env.example` para `.env` e preencha:
   - `BREVO_API_KEY`: Brevo > SMTP & API > API Keys
   - `MAIL_TO`: e-mail que recebe os leads
   - `MAIL_FROM`: remetente validado na Brevo (domínio autenticado)
   - `ALLOWED_ORIGINS`: domínios que podem enviar o formulário
3. De preferência, deixe o `.env` **um nível acima** da pasta pública. Se ficar na raiz do site, o `.htaccess` bloqueia o acesso.
4. O PHP precisa da extensão `curl` (PHP 7.4+).

## Como o formulário funciona

1. Valida nome, WhatsApp (com máscara), Instagram e serviço no navegador.
2. Envia para `api/lead.php`, que valida de novo, aplica honeypot e limite por IP, e manda o e-mail via Brevo.
3. Redireciona para `wa.me/5522997560717` com a mensagem pronta:
   "Oi, Cassiano! Sou [nome], da [@instagram]. Vim pelo site e [preciso de um site]."
   Se o e-mail falhar, o redirecionamento acontece mesmo assim (o lead não se perde).

## Vídeos

- `assets/video/motion/`: reels de sites e de campanha, otimizados em 540x960 (H.264, CRF 30, áudio 64k, faststart) com capa `.webp`.
- `assets/video/depoimento-weslen-ruiz.mp4`: depoimento hospedado no site (720x1280).
- Para adicionar um vídeo novo:
  `ffmpeg -i entrada.mp4 -vf "scale=540:960,fps=30,format=yuv420p" -c:v libx264 -preset slow -crf 30 -movflags +faststart -c:a aac -b:a 64k saida.mp4`
  e cadastre em `assets/js/data.js` (cat: 'motion').

## Pendências de conteúdo (placeholders)

Tudo em `assets/js/data.js`:

- **Portfólio / Sistemas:** prints do painel de marketplaces e da Copa Brasil de Talentos (ou esconder a aba).
- **Depoimentos:** frase de uma linha de cada vídeo (opcional).
