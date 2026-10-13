# Como publicar no cPanel com Git

O repositório fica numa pasta separada no servidor e o cPanel **copia só os arquivos do site** para a pasta do domínio (arquivo `.cpanel.yml`). Nada é apagado: as outras pastas do domínio (beemote, copa-online, leads, lp, torneio-online…) continuam onde estão.

O que vai para a pasta do domínio:

```
index.html  robots.txt  sitemap.xml  .htaccess  assets/  api/  briefing/
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

- Usa o mesmo `.env` do formulário (Brevo). Cada briefing chega no `MAIL_TO` com o assunto "Novo briefing: Empresa (Nome)".
- Fotos, logo e PDFs vão anexados quando cabem (até 8 MB no total, formatos JPG, PNG, PDF, ZIP e EPS). SVG, WEBP e AI ficam só no servidor.
- Tudo também fica salvo em `briefing/respostas/AAAA-MM-DD_hhmmss_empresa/` (bloqueado para acesso pelo navegador). O deploy não apaga essa pasta.
- Link com dados preenchidos: `cassianogalvao.com.br/briefing/?nome=Ana&empresa=Clinica%20X&whatsapp=22999999999`
