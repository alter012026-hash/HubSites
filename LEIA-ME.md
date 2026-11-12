# Estação Cinema — portal WordPress de filmes e séries

**Estação Cinema** é a marca pública do portal. Seu slogan é **“Sua próxima
parada: grandes histórias.”** Os diretórios e identificadores internos ainda
usam `tela-pop` por compatibilidade.

## Componentes

- `wp-content/plugins/tela-pop-core/`: fontes RSS, pautas, dossiê de fatos,
  geração por IA, SEO, fila de revisão e proteção contra publicação sem revisão.
- `wp-content/themes/tela-pop/`: tema responsivo, catálogo de filmes e séries,
  páginas de leitura, locomotiva no logotipo e modos claro/escuro.

O fluxo editorial é **RSS → pauta → dossiê → rascunho com IA → revisão humana →
publicação**. A IA não publica por conta própria.

## Fluxo editorial

1. Cadastre fontes RSS em **Tela Pop → Fontes e feeds**.
2. Consulte os feeds; os itens encontrados entram como pautas.
3. Abra uma pauta e escolha um modelo editorial.
4. Gere o rascunho e confira a fonte, os fatos e os alertas na fila de revisão.
5. Edite e conclua a revisão humana antes de publicar pelo WordPress.

O artigo gerado deve ser original. O sistema exige evidências no dossiê e não
deve completar lacunas com fatos inventados. A leitura de texto da fonte depende
da autorização registrada; feeds sem essa permissão fornecem apenas título,
resumo e link. Confirme dados mutáveis, como disponibilidade em streaming,
antes de publicar.

## Instalação e configuração

Consulte [INSTALACAO-WORDPRESS.md](INSTALACAO-WORDPRESS.md) para copiar os
arquivos-fonte para o WordPress, ativar o plugin e o tema e configurar os
provedores de IA e os feeds. Não há ZIPs de instalação versionados neste
repositório.

Para detalhes sobre evidências, geração e revisão, consulte
[FLUXO-EDITORIAL.md](FLUXO-EDITORIAL.md).

## Outro projeto no repositório

O portal **De Olho na Bola** e sua plataforma central Laravel ficam
separadamente em [`de-olho-na-bola/`](de-olho-na-bola/). Consulte o
[guia do projeto](de-olho-na-bola/LEIA-ME.md); não misture suas pastas com as da
Estação Cinema.
