# De Olho na Bola

Projeto composto por um tema e plugin WordPress e por uma plataforma central Laravel
para conectar instalações WordPress independentes. Multisite é opcional.

## Componentes e documentação

- `wp-content/plugins/bola-em-campo-core/` — fluxo editorial, newsletter e conector
  autenticado para enviar rascunhos à plataforma central (plugin 3.2.2).
- `wp-content/themes/bola-em-campo/` — tema do portal (tema 3.1.4).
- `plataforma-central/` — aplicação Laravel para administrar sites e enviar
  conteúdo somente como rascunho. Consulte seu [guia de instalação](plataforma-central/README.md).
- `INSTALACAO-WORDPRESS.md` — instalação, atualização e configuração do WordPress.
- `FLUXO-EDITORIAL.md` — etapas, responsabilidades e limites editoriais.
- `GUIA-GIT.md` — estrutura versionada, arquivos ignorados e publicação do repositório.

O fluxo editorial é RSS → triagem → dossiê de fatos → rascunho com IA → revisão
humana. A publicação continua dependendo de uma pessoa. A plataforma central
também não publica automaticamente: o conector cria rascunhos idempotentes.

## Instalação rápida

1. Instale o plugin e o tema conforme [`INSTALACAO-WORDPRESS.md`](INSTALACAO-WORDPRESS.md).
2. Ative primeiro o plugin e depois o tema; salve os links permanentes.
3. Para administrar vários WordPress pela plataforma central, siga
   [`plataforma-central/README.md`](plataforma-central/README.md).

Use os arquivos-fonte deste projeto. Os ZIPs de instalação e as dependências
locais não fazem parte do repositório Git.

## Instalação no XAMPP
1. Instale o WordPress em `C:\xampp\htdocs\wordpress` (ou o caminho que preferir) e defina os links permanentes como **Nome do post**.
2. Copie `wp-content/themes/bola-em-campo` e `wp-content/plugins/bola-em-campo-core` para as pastas equivalentes.
3. Se existirem, **desative e apague** os plugins antigos `De Olho na Bola Core` (v1) e `Article Automation Bridge`, e o tema `tecnologia-sem-complicacao`.
4. Ative o plugin **antes** do tema (ele cria categorias, menu, páginas, clubes e fontes iniciais). Depois ative o tema **De Olho na Bola**.
5. Em **Configurações > Links permanentes**, clique em Salvar para atualizar as URLs (`/clubes/`).
6. (Automação externa) cole o trecho de `exemplos/wp-config-trecho.php` no `wp-config.php`.

### Copiar tema e plugin automaticamente (Windows)
Depois de criar o site WordPress, execute `instalar-site.py`. Escolha a pasta raiz do WordPress e confirme as pastas de origem do plugin e do tema (já preenchidas com as pastas deste pacote). O script copia os arquivos para `wp-content/plugins` e `wp-content/themes`, mesclando pastas existentes e substituindo arquivos de mesmo nome. Em seguida, ative o plugin e o tema pelo painel. O script não cria a instalação WordPress ou o banco de dados.

## O que a ativação cria
- Categorias: Notícias, Brasileirão, Libertadores, Copa do Brasil, Mercado da Bola, Análises, Resultados e Jogos na TV. As duas últimas aparecem como atalhos na central de Notícias, sem ampliar o menu principal.
- Menu principal na ordem do documento técnico (com Clubes).
- 12 páginas de clubes **em rascunho** (publique cada uma só quando tiver texto útil).
- Páginas institucionais **em rascunho**, com texto inicial a revisar: Sobre, Política editorial, Correções, Contato, Privacidade.
- Fontes: CBF, CONMEBOL e FIFA, **sem feed** e inativas (cadastre apenas feeds cujo uso seja permitido).

## Fluxo editorial
1. **Fontes** (menu *De Olho na Bola > Fontes e feeds*): cadastre URL do feed, permissão, confiabilidade (0–100) e intervalo. O fluxo consulta feeds ativos a cada 15 minutos; há botão "Consultar fontes agora".
2. **Pautas**: o feed cria pautas guardando título, link, data original e resumo curto. Itens sem data, com data futura ou mais antigos que 72 horas são ignorados. A consulta informa quantos itens antigos ou sem data foram descartados.
3. **Alertas de qualidade**: confiabilidade da fonte, quantidade de informação e possíveis dados não corroborados entram no termômetro da revisão; não impedem a criação do rascunho. Pautas duplicadas (mesmo link ou título ≥ 60% parecido nos últimos 7 dias) não são criadas.
4. **Gerar rascunho → revisar → publicar**: selecione OpenAI ou GroqCloud em Redação (IA), salve chave/modelo e teste a conexão. Abra uma pauta e clique em **Gerar rascunho com IA**, ou use a ação em massa. A nota, a confiabilidade da fonte e o volume de dados não bloqueiam o rascunho: aparecem como indicadores e alertas na Fila de revisão. Confira os fatos e links; a publicação só ocorre após revisão humana.
5. **Revisão e publicação**: o artigo só publica com ≥ 1 fonte, a caixa "revisão humana concluída" marcada e sem `[PREENCHER`. (Salve o rascunho com as fontes antes de clicar em Publicar.) Em *Configurações* é possível estender essa trava a todos os artigos.
6. Histórico de status por pauta e revisões nativas do WordPress para os artigos.

Para versionar o projeto completo sem incluir configurações locais, dependências
ou dados do WordPress, siga [`GUIA-GIT.md`](GUIA-GIT.md). O `.gitignore` da raiz
protege os arquivos locais mais comuns; revise os arquivos staged antes de cada envio.

Na central de Notícias, “Jogos na TV” é uma seção editorial: a API-Football atualmente configurada não fornece canais de transmissão. Confirme canal, data e horário com fonte atual antes de publicar um guia.

## Automação externa (Python, Node, n8n…)
Os endpoints `POST /wp-json/bec/v1/pautas` e `/rascunhos` (ou
`?rest_route=/bec/v1/...`) usam o token `BEC_REST_TOKEN` e criam somente pautas
ou rascunhos. O exemplo está em `exemplos/enviar_rascunho.py`; se a chamada
retornar 401, consulte `exemplos/htaccess-authorization.txt`. Não armazene o
token no repositório nem o compartilhe em mensagens.

## Limites conhecidos
- **Google Trends** não tem API oficial: os dados são digitados na pauta (interesse relativo, período, consultas relacionadas).
- **Search Console/Analytics** não estão integrados ao painel (o painel avisa).
- A geração por IA usa o provedor selecionado entre OpenAI e GroqCloud. Cada provedor tem chave e modelo próprios. Os artigos são sempre enviados à fila de revisão humana.
- Classificação e jogos dependem de chave própria da API-Football (opcional). O plano gratuito tem limite diário; há cache de 15 min.
- Imagens: o tema mostra legenda/crédito da imagem destacada. Use apenas imagens próprias ou licenciadas.
- No XAMPP o WP-Cron só roda com visitas; em produção, configure um cron real chamando `wp-cron.php`.
- Antes de colocar em produção: HTTPS, backups, revisão jurídica das páginas institucionais e política de privacidade/consentimento (LGPD) se usar anúncios ou analytics.
