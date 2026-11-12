# HubSites

Este repositório reúne **dois projetos independentes**. Cada um tem seu próprio
tema, plugin e documentação; não misture os arquivos nem instale os dois portais
na mesma base WordPress.

## Projetos

### Estação Cinema

Portal WordPress de filmes e séries. A marca pública é **Estação Cinema** e seu
slogan é **“Sua próxima parada: grandes histórias.”**

- `wp-content/themes/tela-pop/` — código-fonte do tema, identidade visual com
  locomotiva antiga e layouts responsivos.
- `wp-content/plugins/tela-pop-core/` — código-fonte do plugin editorial:
  fontes RSS, pautas, dossiês, geração de rascunhos com IA e revisão humana.
- `LEIA-ME.md` — visão geral do portal e fluxo editorial.
- `INSTALACAO-WORDPRESS.md` — instruções de instalação.
- `FLUXO-EDITORIAL.md` — operação editorial e revisão.

Os nomes `tela-pop` e `tela-pop-core` permanecem nos caminhos internos e em
alguns menus administrativos por compatibilidade. O WordPress deve usar o nome
e slogan acima em **Configurações → Geral**.

### De Olho na Bola

Portal de futebol e plataforma de administração de sites, mantidos isolados em
`de-olho-na-bola/`.

- `de-olho-na-bola/wp-content/` — tema e plugin WordPress do portal.
- `de-olho-na-bola/plataforma-central/` — aplicação Laravel.
- `de-olho-na-bola/LEIA-ME.md` — guia do projeto, instalação e funcionamento.

## Conteúdo e limites do repositório

O repositório versiona **código-fonte e documentação**, não uma instalação
completa do WordPress. Não inclui o núcleo `wp-admin`/`wp-includes`, cópias de
instalação ZIP prontas para enviar pelo painel, banco de dados do site nem
chaves e arquivos `.env` reais. Use os guias de cada projeto para preparar as
instalações e dependências.

## Documentação

- [Guia da Estação Cinema](LEIA-ME.md)
- [Instalação WordPress da Estação Cinema](INSTALACAO-WORDPRESS.md)
- [Fluxo editorial da Estação Cinema](FLUXO-EDITORIAL.md)
- [Guia De Olho na Bola](de-olho-na-bola/LEIA-ME.md)
- [Instalação Laravel da plataforma central](de-olho-na-bola/plataforma-central/README.md)
