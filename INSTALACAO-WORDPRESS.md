# Instalação do Tela Pop no WordPress

O Tela Pop é um projeto independente, com plugin e tema próprios. Instale-o em uma instalação WordPress separada para manter a base de conteúdo, as categorias e as configurações editoriais isoladas do De Olho na Bola.

## Opção A — enviar ZIPs pelo painel

1. Em **Plugins → Adicionar novo → Enviar plugin**, envie `instalar-pelo-painel/tela-pop-core.zip`, instale e ative.
2. Em **Aparência → Temas → Adicionar novo → Enviar tema**, envie `instalar-pelo-painel/tela-pop-tema.zip` e ative o tema **Tela Pop**.
3. Em **Configurações → Links permanentes**, selecione **Nome do post** e salve.
4. Abra o site como visitante. A ativação cria categorias e páginas institucionais em rascunho; revise e publique as páginas que for usar.

## Opção B — XAMPP por pastas

Copie:

- `wp-content/plugins/tela-pop-core/` para a pasta `wp-content/plugins/` da instalação do Tela Pop.
- `wp-content/themes/tela-pop/` para a pasta `wp-content/themes/` da instalação do Tela Pop.

Ative primeiro o **Tela Pop Core**, depois o tema. Salve os links permanentes.

## Configurar IA e fontes

1. Em **Tela Pop → Configurações → Redação (IA)**, escolha GroqCloud ou OpenAI, informe a chave e o modelo e teste a conexão. A chave não vem no pacote.
2. Em **Tela Pop → Fontes e feeds**, cadastre feeds RSS de estúdios, distribuidoras, plataformas, festivais ou veículos de entretenimento. Marque a permissão de acordo com os termos da fonte; “oficial” não significa autorização para reproduzir uma matéria completa.
3. Comece no modo **Assistido**. Consulte as fontes manualmente e gere alguns rascunhos antes de habilitar o agendamento.
4. Em **Revisão humana**, confira a pauta e o post. A publicação exige fonte e revisão salva.

## Configurar o catálogo

Acesse **Catálogo → Adicionar título ao catálogo**. Preencha filme ou série, ano, plataforma confirmada e trailer oficial. Defina imagem destacada para o pôster e use categorias/tags para gênero, país ou franquia. O catálogo é independente dos posts editoriais gerados pela IA.

## Personalizar a marca

Em **Aparência → Personalizar → Tela Pop — Topo do site**, altere a frase da faixa superior, a marca da newsletter e o carregamento das fontes. Também é possível definir um logotipo próprio em **Identidade do site**. O tema usa uma paleta própria ameixa, creme, dourado e coral, com modo claro/escuro.
