# Como publicar no cPanel com Git

O repositório fica numa pasta separada no servidor e o cPanel **copia só os arquivos do site** para a pasta do domínio (arquivo `.cpanel.yml`). Nada é apagado: as outras pastas do domínio (beemote, copa-online, leads, lp, torneio-online…) continuam onde estão.

O que vai para a pasta do domínio:

```
index.html  robots.txt  sitemap.xml  .htaccess  assets/  api/  briefing/  site-por-assinatura/  admin/
```

## 0. Antes de começar (uma vez só)

1. **Confira a raiz do domínio.** Em cPanel > *Domínios*, veja a "Raiz do documento" de `cassianogalvao.com.br`. Se for diferente de `/home/SEU_USUARIO/cassianogalvao.com.br`, ajuste a linha `DEPLOYPATH` do `.cpanel.yml` e faça commit.
2. **Confira o `.htaccess` atual da pasta do domínio.** Se tiver um bloco `# php -- BEGIN cPanel-generated handler` (versão do PHP), copie esse bloco para o final do `.htaccess` do repositório antes do primeiro deploy, senão o PHP pode mudar de versão.
3. **Apague os arquivos do site antigo** na pasta do domínio (não são usados pelo novo):
   `index.html`, `script.js`, `styles.css`, `portfolio-auto.json`, `portfolio-data.js` e, se quiser, a pasta `Materiais para o site`.
   Não mexa nas pastas dos outros projetos.

## 1. Acesso ao GitHub

O repositório é **público**, então não precisa de chave: use a URL `https://github.com/DevCassianoGalvao/novositecassiano`. Só faça o passo abaixo se um dia deixar o repositório privado.

### (Só se for privado) Chave de deploy

1. cPanel > *Acesso SSH* > *Gerenciar chaves SSH* > *Gerar nova chave* (sem senha). Autorize a chave.
2. Abra a chave **pública** (`.pub`) e copie o conteúdo.
3. No GitHub: repositório `novositecassiano` > *Settings* > *Deploy keys* > *Add deploy key*. Cole a chave, marque só leitura.

> Se o repositório for público, pule este passo e use a URL `https://github.com/DevCassianoGalvao/novositecassiano.git`.

## 2. Clonar no cPanel

1. cPanel > *Git™ Version Control* > *Criar*.
2. **URL de clone:** `https://github.com/DevCassianoGalvao/novositecassiano`
3. **Caminho do repositório:** `repositories/novositecassiano`. **Não use a pasta do domínio** (`cassianogalvao.com.br`): o cPanel só clona em pasta vazia, e ela tem as pastas dos outros projetos. O repositório fica separado e o deploy copia os arquivos para o domínio.
4. **Nome:** `novositecassiano` > *Criar*.

## 3. Publicar

1. Na lista do Git Version Control, clique em *Gerenciar* > aba *Implantar com pull ou push*.
2. *Atualizar a partir do remoto* (puxa a última versão do GitHub).
3. *Implantar o commit HEAD* (roda o `.cpanel.yml` e copia os arquivos).

Toda vez que eu fizer push de alguma mudança, é só repetir os passos 2 e 3.

## 4. Formulário (Brevo)

1. Na pasta do domínio (ou, melhor, uma pasta acima dela), crie o arquivo `.env` copiando o `.env.example` do repositório.
2. Os e-mails e o assunto já vêm preenchidos. Só cole a `BREVO_API_KEY`.
3. O `.env` não vai para o GitHub e o `.htaccess` bloqueia o acesso a ele pelo navegador.
4. Teste: preencha o formulário do site. O e-mail deve chegar e a página deve abrir o WhatsApp.

## 5. Conferir depois do deploy

- `https://cassianogalvao.com.br` abre o site novo.
- As pastas antigas continuam respondendo (ex.: `https://cassianogalvao.com.br/torneio-online/`).
- `https://cassianogalvao.com.br/.env` e `/.cpanel.yml` devem dar **403**.
- Envie o `sitemap.xml` no Google Search Console.

## 6. Briefing (cassianogalvao.com.br/briefing)

- Usa o mesmo `.env` do formulário (Brevo). Cada briefing chega no `MAIL_TO` com o assunto "Novo briefing: Empresa (Nome)" e responder o e-mail responde direto pro cliente.
- Os arquivos do cliente (logo, fotos, PDFs) vão ANEXADOS no e-mail, até 14 MB no total. SVG, WEBP e AI vão juntos num .zip (a Brevo não aceita esses formatos soltos).
- Nada fica guardado no servidor. Só se a Brevo falhar, o briefing e os arquivos ficam em `briefing/respostas/` como reserva (bloqueado para acesso pelo navegador).
- Link com dados preenchidos: `cassianogalvao.com.br/briefing/?nome=Ana&empresa=Clinica%20X&whatsapp=22999999999`

## 7. Site por assinatura (cassianogalvao.com.br/site-por-assinatura)

- Página de vendas separada, com o mesmo visual do site. Arquivos em `site-por-assinatura/` (usa o `assets/` do site principal).
- O pop-up de planos manda o lead por e-mail (Brevo, mesmo `.env`) pelo `api/assinatura.php`, com plano e UTMs. Assunto: "Site por assinatura: Nome quer o Completo".
- Fase 2 (TODO no código): webhook de leads, checkout e evento InitiateCheckout.
- A pergunta "Posso cancelar quando quiser?" está escondida até definir fidelidade e multa (comentário no HTML).

## 8. Sistema de assinaturas (painel em /admin)

Painel PHP + MySQL para receber os leads da página `/site-por-assinatura`, gerenciar clientes (ativos, aguardando pagamento, inativos), cobrar pelo Mercado Pago, mandar lembretes pela Brevo e cadastrar as tags (Pixel, GTM) só dessa página.

### 8.1 Criar o banco (cPanel)
1. cPanel > *Bancos de dados MySQL* > crie um banco (ex.: `cassiano_assin`). O cPanel coloca o prefixo da conta: o nome final fica `usuario_assin`.
2. Na mesma tela, crie um usuário com senha forte e, em *Adicionar usuário ao banco*, marque **Todos os privilégios**.

### 8.2 Preencher o `.env`
Acrescente ao `.env` (o mesmo da Brevo; modelo no `.env.example`):

```
DB_HOST=localhost
DB_NAME=usuario_assin
DB_USER=usuario_user
DB_PASS=a_senha_do_usuario
APP_URL=https://cassianogalvao.com.br
APP_KEY=uma-frase-longa-aleatoria-nunca-troque
INSTALL_KEY=uma-senha-so-para-instalar
CRON_KEY=letrasenumeros
```
`APP_KEY` protege o token do Mercado Pago dentro do banco (não troque depois). `INSTALL_KEY` só serve para liberar a instalação.

### 8.3 Instalar (cria as tabelas e o seu acesso)
1. Faça o deploy (passos 2 e 3 acima).
2. Abra `https://cassianogalvao.com.br/admin/install.php`. A tela confere o servidor (tudo verde).
3. Digite a `INSTALL_KEY`, clique em **Criar / atualizar tabelas** e depois crie seu usuário administrador (e-mail + senha de 10+ caracteres).
4. Entre em `/admin`. Depois de instalado, o instalador trava sozinho.

Sem painel? Também dá por terminal: `php admin/install.php migrate`.

**Atualizações futuras do banco:** quando uma versão nova trouxer migrações (`admin/sql/00X_*.sql`), faça o deploy e abra `/admin/install.php` logado (ou use o link em Configurações > Banco de dados).

### 8.4 Conectar o Mercado Pago
No painel, *Configurações > Mercado Pago* tem o passo a passo: criar a aplicação no Mercado Pago Developers, colar o **Access Token de produção** e a **assinatura secreta** do webhook, e cadastrar a URL `https://cassianogalvao.com.br/api/mp-webhook.php` com os eventos **Planos e assinaturas** (cartão) e **Pagamentos** (Pix). Faça isso na aba *Modo de produção* (e, se quiser testar, também na de teste). Clique em *Salvar e testar*.

### 8.5 Agendar a rotina diária (lembretes e suspensão)
cPanel > *Tarefas Cron* > 1x por dia (ex.: 9h), comando (copie o exato em Configurações > Rotina diária):

```
/usr/local/bin/php /home/USUARIO/cassianogalvao.com.br/admin/cron.php
```
Ela confere o Mercado Pago, manda lembretes (7 dias, 3 dias e no dia), dá 1 dia de tolerância, suspende e avisa você ("fulano não pagou"). Quem pagar depois volta sozinho, sem taxa.

### 8.6 Tags (Pixel, GTM)
`/admin > Rastreamento`. Valem só em `/site-por-assinatura/` (e na página de obrigado). Enquanto nada for salvo lá, a página continua com o Pixel 987681871022914.

### 8.7 Conferir
- `/admin` pede login; `/admin/inc/` e `/admin/sql/` dão **403**.
- Preencha o formulário da página: o lead aparece em *Leads* e o e-mail chega.
- Em *Configurações > E-mails* use "Enviar e-mail de teste".

### 8.8 Pix e cartão (página de pagamento do cliente)
Cada cliente tem um link único de pagamento (aparece no cadastro dele, com botões de WhatsApp e e-mail): `https://cassianogalvao.com.br/site-por-assinatura/pagar/?t=...`.
Nessa página ele escolhe:
- **Pix:** o QR Code é gerado na hora (Pix Copia e Cola também). Quando o pagamento cai, a tela confirma sozinha e o sistema libera o cliente e atualiza o vencimento.
- **Cartão:** abre o pagamento do Mercado Pago e fica com cobrança automática todo mês.

Os lembretes por e-mail (7 dias, 3 dias, no dia, atraso e suspensão) levam o botão "Pagar agora" para essa página. Quem paga por Pix recebe o lembrete todo mês e paga de novo pelo mesmo link; quem pagou no Pix pode trocar para o cartão a qualquer momento.

Se você já tinha instalado o painel antes desta versão, abra `/admin/install.php` logado (ou o link em Configurações > Banco de dados) para aplicar a migração `002_portal_pix.sql`.

### 8.9 Briefing (9 etapas) e cancelamento pelo cliente

As migrações `003_briefing.sql` e `004_briefing_etapas.sql` criam as tabelas do briefing e dos arquivos: depois do deploy, abra `/admin/install.php` e clique em "Criar / atualizar tabelas".

- O cliente abre o briefing pelo link do e-mail "Assinatura confirmada" ou pela página de pagamento (`/site-por-assinatura/pagar/?t=...`). O progresso é salvo a cada etapa.
- **Arquivos** (logo, fotos, copy etc.) ficam numa pasta por pedido, fora do site: por padrão `assinaturas-arquivos/` ao lado da pasta do domínio (na pasta inicial da conta). Para escolher outro lugar, defina `UPLOAD_DIR` no `.env`. Se nenhuma pasta for gravável, usa `admin/storage/` (protegida por `.htaccess`). O limite é 15 MB por arquivo; se o PHP do servidor tiver um limite menor, aumente `upload_max_filesize` e `post_max_size` no cPanel (MultiPHP INI Editor).
- No painel, a ficha do cliente tem o relatório completo, "Copiar tudo", "Baixar .txt", "Baixar tudo (.zip)" e o download de cada arquivo. Você também recebe o relatório por e-mail, e o cliente recebe uma cópia.
- O cliente cancela sozinho pela página de pagamento, sem conta.

### 8.10 Segurança (resumo)

- O link pessoal do cliente (`?t=...`) nunca volta para o navegador de quem digita o e-mail de um assinante no formulário: ele vai só para o e-mail do próprio cliente.
- Nas páginas de pagamento e de briefing, o código do link sai da barra de endereço antes do Pixel/GTM carregar (não vai para a Meta nem para o Google). Recarregar continua funcionando na mesma aba.
- Painel e páginas do cliente não abrem dentro de outros sites (proteção contra clique enganado), a sessão do painel expira após 8 horas sem uso e o "Sair" exige o botão (não funciona por link).
- Adicionar outro administrador pede a sua senha atual. Erros do PHP não aparecem para o visitante (ficam no log).
- Se o site passar a usar a Cloudflare, coloque `TRUST_CLOUDFLARE=1` no `.env`.

### 8.11 Períodos com desconto (trimestral, semestral, anual)

A migração `005_ciclos.sql` guarda o período de cada cliente: depois do deploy, abra `/admin/install.php` e clique em "Criar / atualizar tabelas".

- Na página de vendas e na página de pagamento o cliente escolhe Mensal, Trimestral (-10%), Semestral (-15%) ou Anual (-20%). Pix cobra o período inteiro à vista; no cartão, a assinatura do Mercado Pago cobra o valor do período a cada 3, 6 ou 12 meses.
- O vencimento avança pelo período pago. Os lembretes (7, 3 e 0 dias) valem para o fim de cada período.
- Os percentuais da página de pagamento e do painel ficam em Configurações > Regras de cobrança. Os da página de vendas estão no HTML (`assinatura.js`, `DISC`), então, se mudar lá, mude aqui também.
- O briefing aceita até 100 MB de arquivos por cliente; acima disso ele cola um link (Drive, OneDrive...) no campo próprio.
