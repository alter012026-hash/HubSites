# De Olho na Bola v3 — Guia de instalação no WordPress

Este pacote tem uma **plataforma central** para conectar instalações WordPress independentes, um tema (nova identidade visual, mobile first) e um plugin/conector (motor editorial + newsletter + integração idempotente).
Para operar pelo painel central, instale o plugin conector em cada site WordPress de destino e configure a plataforma conforme `plataforma-central/README.md`.

## Multisite — rede em subdiretórios

Antes de habilitar a rede, faça backup de `wp-config.php`, `.htaccess` e do banco de dados. Em uma instalação nova, use **Ferramentas > Configuração da rede**, escolha subdiretórios e aplique os trechos gerados pelo próprio WordPress. Se o painel avisar que já existem tabelas de rede, **não as apague**: a rede pode conter sites e dados que devem ser preservados; recupere as constantes e regras correspondentes à rede existente.

Depois de habilitar a rede e entrar novamente:
1. Instale os arquivos dos plugins em **Meus sites > Administrador da rede > Plugins > Adicionar novo** e os temas em **Temas > Adicionar novo**. Em Multisite, os arquivos ficam compartilhados pela rede; não é necessário instalar uma segunda cópia para cada subsite.
2. Abra **Meus sites > Administrador da rede > Sites > Sites De Olho na Bola**.
3. Informe o nome e o identificador do endereço, escolha o tema e marque os plugins que esse subsite deve usar. O tema será ativado somente no novo subsite; plugins ativos na rede continuam ativos em todos os sites.
4. O administrador atual será incluído no novo site. Para alterar a seleção depois, use **Configurar este site** na linha correspondente da lista de sites nesta mesma tela, ou abra o painel daquele subsite.
5. Na lista de sites, use **Arquivar** para desativar um subsite sem apagar seus dados; use **Reativar** para restaurá-lo. O site principal da rede não pode ser arquivado por essa tela.

O subsite começa sem copiar conteúdo. Fontes, pautas, newsletter e configurações editoriais são próprias de cada site. Plugins com a opção **Ativar na rede** ficam ativos em todos os subsites; para escolher plugins diferentes por site, não os ative na rede e selecione-os individualmente na criação ou no painel do subsite. A administração geral da rede continua disponível em **Sites > Todos os sites**.

## Novidade: fluxo RSS → IA → revisão humana
Veja `FLUXO-EDITORIAL.md` para o desenho completo. Resumo do que configurar depois de instalar:
1. **De Olho na Bola > Fontes RSS**: cadastre feeds, marque *Ativa*, informe a URL do feed, a confiabilidade e a permissão de uso (*descoberta*, *oficial* ou *licenciada*).
2. **Configurações > Redação (IA)**: escolha OpenAI ou GroqCloud em **Provedor ativo**, informe a chave e o modelo da mesma seção e clique em **Testar conexão**. Para guardar a chave no `wp-config.php`, use `BEC_OPENAI_API_KEY` para OpenAI ou `BEC_GROQ_API_KEY` para GroqCloud.
3. **Configurações > Dados esportivos** (opcional): chave da API-Football, para a IA receber jogos e classificação.
4. **Configurações > Fluxo automático**: comece em **Assistido** para testar os rascunhos manualmente. Quando estiver pronto, selecione **Rascunhos automáticos** e ajuste os limites por rodada e por dia. Nota e confiabilidade da fonte são alertas de qualidade; não bloqueiam a geração.
5. **Visão geral > Rodar fluxo completo** para um primeiro teste e depois abra a **Fila de revisão**. Se estiver no modo Assistido, acione a geração manualmente nas pautas; em Rascunhos automáticos, os elegíveis são processados no agendador.
6. Em produção, use um cron real chamando `wp-cron.php` a cada 5 minutos (aba Integrações).

> Custo: cada texto gerado consome a cota do provedor selecionado. O teto diário (padrão 10) limita o volume, e o custo depende do modelo e da conta.

## Nome do site
Vá em **Configurações > Geral** e coloque **Título do site: De Olho na Bola**. Esse nome aparece nos e-mails da newsletter e na aba do navegador.
> As pastas continuam chamadas `bola-em-campo` (nome interno); o nome que o visitante vê já é De Olho na Bola.

## O que mudou
- **Identidade visual**: azul-marinho, azul elétrico, verde-água e superfícies claras; tipografia condensada (Barlow Condensed + Inter), animações discretas na rolagem e respeito à configuração de movimento reduzido do dispositivo.
- **Mobile first**: menu em "pílulas" rolável com o dedo, central de Notícias por assunto/time/campeonato, botão Assine sempre à vista, manchete em tela cheia, carrosséis por editoria, escudos genéricos dos clubes, ranking "Mais lidas", barra de progresso de leitura e botão de WhatsApp nos artigos.
- **Newsletter** (plugin): inscrição com **dupla confirmação** (LGPD), descadastro em 1 clique, lista no painel, exportação CSV e **resumo semanal** opcional.
  Formulários aparecem na home, no fim de cada artigo e no rodapé.

## Opção A — Instalar pelo painel do WordPress (mais fácil)
Dentro do zip há a pasta `instalar-pelo-painel/` com dois arquivos:
1. **Plugins > Adicionar novo > Enviar plugin** → escolha `bola-em-campo-core-plugin.zip` → Instalar. Em Multisite, faça isso no **Administrador da rede**; use **Ativar na rede** somente se todos os subsites devem usar o plugin. Para plugins específicos por site, deixe-o sem ativação na rede.
   (Se você tinha o plugin De Olho na Bola Core v2 ou o Article Automation Bridge, **desative e apague** antes.)
2. **Aparência > Temas > Adicionar novo > Enviar tema** → escolha `bola-em-campo-tema.zip` → Instalar. Em Multisite, instale pelo **Administrador da rede**; o tema pode ser selecionado e ativado separadamente em cada subsite.
3. **Configurações > Links permanentes** → escolha *Nome do post* → **Salvar** (necessário para `/clubes/`).

## Opção B — Instalar por arquivos (FTP/Gerenciador de arquivos/XAMPP)
Copie, mantendo as pastas:
- `wp-content/themes/bola-em-campo` → `wp-content/themes/`
- `wp-content/plugins/bola-em-campo-core` → `wp-content/plugins/`
Depois ative o **plugin**, em seguida o **tema**, e salve os Links permanentes.

## Atualizar o plugin para conexão com a plataforma central
Para que a plataforma valide permissões e envie rascunhos com proteção contra duplicação, atualize o plugin para **De Olho na Bola Core 3.2.2** em cada instalação WordPress conectada. Em seguida, na plataforma central, use um usuário técnico com senha de aplicação própria para cada site. O envio cria somente rascunhos; não publica automaticamente.

## Instalar por script no Windows
Depois de criar uma instalação WordPress, execute `instalar-site.py` com Python instalado. Se necessário, clique com o botão direito no arquivo e escolha **Executar com Python**. Na janela:
1. Selecione a pasta raiz do WordPress (a que contém `wp-config.php` e `wp-content`).
2. Confira ou selecione as pastas de origem do plugin e do tema. As pastas deste pacote já aparecem preenchidas por padrão.
3. Clique em **Copiar plugin e tema** e confirme. O script mescla os arquivos e substitui arquivos de mesmo nome, mantendo os demais.
4. No painel WordPress, ative primeiro o plugin e depois o tema; salve **Configurações > Links permanentes**.

O script copia arquivos para uma instalação existente; não cria o WordPress, banco de dados ou site/domínio.

## Estrutura para instalações separadas
Para cada site com uma instalação WordPress própria, use a pasta `estrutura-site/` deste pacote como modelo:
1. Copie a pasta `estrutura-site/wp-content/` para a raiz do WordPress daquele site.
2. Se já existir `wp-content/`, copie apenas `plugins/` e `themes/` para dentro dela, sem apagar nem substituir arquivos existentes.
3. Coloque cada plugin em uma pasta dentro de `wp-content/plugins/` e cada tema em uma pasta dentro de `wp-content/themes/`. Por exemplo, copie `wp-content/plugins/bola-em-campo-core` e `wp-content/themes/bola-em-campo` deste pacote para as pastas correspondentes.

Duplique o modelo para cada instalação separada. Ele fornece somente as pastas de plugins e temas; não cria o WordPress, o banco de dados nem configura domínio. Em uma rede Multisite, essas pastas são compartilhadas pela rede, não criadas individualmente para cada subsite.

## Atualizar uma instalação anterior para o tema 3.1.4 e plugin 3.2.2

1. Faça uma cópia do banco de dados e das pastas `wp-content/uploads`, do plugin e do tema.
2. No painel de Plugins, desative **De Olho na Bola Core**. Do ZIP completo, copie a pasta interna `bola-em-campo/wp-content/plugins/bola-em-campo-core/` para `C:\xampp\htdocs\wordpress\wp-content\plugins\bola-em-campo-core\`, substituindo os arquivos existentes. **Não copie a pasta externa `bola-em-campo` inteira para `plugins`**, pois isso cria um caminho duplicado e o WordPress continua carregando o código antigo.
3. Copie a pasta interna `bola-em-campo/wp-content/themes/bola-em-campo/` para `C:\xampp\htdocs\wordpress\wp-content\themes\bola-em-campo\`, substituindo os arquivos. Mantenha o tema **De Olho na Bola** ativo.
4. Reative o plugin e abra **Painel > Plugins instalados**; confirme **De Olho na Bola Core 3.2.2**. Em **Aparência > Temas > Detalhes do tema**, confirme a versão **3.1.4**. Limpe o cache do navegador/site.
5. Abra a aba **Notícias**: devem aparecer os atalhos e a seção por clubes. Na home, os placares aparecem quando a API tem chave e partidas encerradas disponíveis. Se não tiver, administradores verão um aviso com o que configurar.
6. Se houver erro de página ou rota, salve novamente **Configurações > Links permanentes**.

As duas categorias novas aparecem como atalhos dentro da central **Notícias**, sem aumentar o menu principal. A categoria **Jogos na TV** é editorial e requer apuração manual dos canais/horários; API-Football não fornece dados de transmissão.

Com a API-Football configurada, a home mostra **Últimos placares**. O leitor pode alternar/deslizar cada cartão entre placar e estatísticas de posse, chutes, chutes ao gol e escanteios. A disponibilidade depende da cobertura da competição e do plano da API; estatísticas ausentes são identificadas, não estimadas. O shortcode `[bec_partidas count="5"]` permite colocar o bloco em outra página (contagem aceita: 2–10 partidas).

## Configurar a newsletter (5 minutos)
1. Menu **De Olho na Bola > Newsletter**.
2. Preencha **Nome** e **E-mail do remetente** (use um e-mail do seu domínio, ex.: `contato@seudominio.com.br`).
3. **Envio de e-mail (importante):** muitas hospedagens barram ou mandam para o spam o e-mail padrão do WordPress. Instale um plugin de SMTP (ex.: *WP Mail SMTP* ou *FluentSMTP*) e conecte a um serviço (Brevo, Amazon SES, Gmail Workspace…). No XAMPP local o e-mail não sai sem isso.
4. Publique a página **Política de privacidade** (já criada em rascunho): o formulário linka para ela automaticamente quando está publicada.
5. Teste: inscreva seu próprio e-mail na home → abra o e-mail de confirmação → clique em **Confirmar inscrição**. Você deve ver o aviso verde "Inscrição confirmada".
6. Resumo semanal: marque **Enviar toda semana**, escolha dia e hora e clique **Salvar**. Use **Enviar teste para mim** para ver o e-mail antes. O envio sai em lotes (padrão 40 a cada 10 min) para respeitar limites da hospedagem.
   > O agendamento usa o WP-Cron, que só roda com visitas. Em produção, configure um cron real chamando `wp-cron.php` a cada 5–10 minutos.
7. Textos do bloco (título e chamada): **Aparência > Personalizar > De Olho na Bola — Topo do site**.

## Primeiros ajustes visuais
- **Logo**: Aparência > Personalizar > Identidade do site. Sem logo, o tema usa o logotipo em texto "DE [olho]LHO NA BOLA", com um olho no lugar do "O" de OLHO.
- **Faixa verde-limão do topo**: Personalizar > De Olho na Bola — Topo do site.
- **Menu**: criado automaticamente na ativação (Aparência > Menus).
- **Manchete da home**: marque um post como **fixo** para ele ser a manchete; senão vale o mais recente com imagem destacada.
- **Fontes do Google**: dá para desligar em Personalizar (usa fontes do sistema, mais leve).
- **Imagens destacadas**: use 1200×750 px ou maior (proporção 16:10). Use apenas imagens próprias ou licenciadas.

## Se algo der errado
- **Tela branca / erro fatal ao ativar**: renomeie a pasta `bola-em-campo-core` via FTP para desativar, e me envie a mensagem de erro (ative `WP_DEBUG` no `wp-config.php`).
- **`/clubes/` dá 404**: salve de novo Configurações > Links permanentes.
- **Inscrição não envia e-mail**: configure o SMTP (passo 3). Veja também a pasta de spam.
- **Site com cache (WP Rocket, LiteSpeed…)**: o formulário foi feito para funcionar com cache (não usa nonce). Limpe o cache após instalar o tema.
- **Voltar ao visual anterior**: reative o tema antigo em Aparência > Temas. Os inscritos ficam guardados no banco (tabela `wp_bec_subscribers`), mesmo se desativar o plugin.

## Avisos honestos
- Este código foi testado aqui com **verificação de sintaxe PHP, renderização dos templates com simulação do WordPress e captura de tela em 390 px e 1280 px**. Ele **não foi executado em um WordPress real**. Teste no XAMPP ou em um ambiente de testes antes de ir ao ar e relate qualquer erro.
- As páginas institucionais continuam em rascunho com `[REVISAR]`; faça a revisão jurídica da política de privacidade (LGPD) antes de coletar e-mails em produção.
