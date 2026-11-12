# Instalação da Estação Cinema no WordPress

Estação Cinema é um projeto independente, com plugin e tema próprios. Instale-o em uma instalação WordPress separada para manter a base de conteúdo, as categorias e as configurações editoriais isoladas do De Olho na Bola. Os diretórios `tela-pop` e `tela-pop-core` permanecem com esses nomes por compatibilidade.

## Instalação por pastas

Obtenha os arquivos-fonte deste repositório e copie:

- `wp-content/plugins/tela-pop-core/` para a pasta `wp-content/plugins/` da instalação WordPress da Estação Cinema.
- `wp-content/themes/tela-pop/` para a pasta `wp-content/themes/` da instalação WordPress da Estação Cinema.

Ative primeiro o plugin **Tela Pop Core**, depois o tema **Estação Cinema**. Salve os links permanentes. Este repositório não contém ZIPs prontos para enviar pelo painel.

Em **Configurações → Geral**, defina o título do site como **Estação Cinema** e a descrição como **Sua próxima parada: grandes histórias.**

## Configurar IA e fontes

1. Em **Tela Pop → Configurações → Redação (IA)**, escolha GroqCloud ou OpenAI, informe a chave e o modelo e teste a conexão. A chave não vem no repositório.
2. Em **Tela Pop → Fontes e feeds**, cadastre feeds RSS de estúdios, distribuidoras, plataformas, festivais ou veículos de entretenimento. Marque a permissão de acordo com os termos da fonte; “oficial” não significa autorização para reproduzir uma matéria completa.
3. Comece no modo **Assistido**. Consulte as fontes manualmente e gere alguns rascunhos antes de habilitar o agendamento.
4. Em **Revisão humana**, confira a pauta e o post. A publicação exige fonte e revisão salva.

## Configurar o catálogo

Acesse **Catálogo → Adicionar título ao catálogo**. Preencha filme ou série, ano, plataforma confirmada e trailer oficial. Defina imagem destacada para o pôster e use categorias/tags para gênero, país ou franquia. O catálogo é independente dos posts editoriais gerados pela IA.

## Personalizar a marca

Em **Aparência → Personalizar → Estação Cinema — Identidade do site**, altere a frase da faixa superior, a marca da newsletter e o carregamento das fontes. Também é possível definir um logotipo próprio em **Identidade do site**. O tema usa uma paleta ameixa, violeta e coral, com modo claro/escuro e um logotipo ilustrado com locomotiva antiga.
