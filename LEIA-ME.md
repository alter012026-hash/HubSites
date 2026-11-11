# Tela Pop — portal WordPress de filmes e séries

Projeto independente do portal De Olho na Bola. Usa o mesmo fluxo editorial: **busca RSS → pauta → geração por IA → revisão humana → publicação**.

## Identidade visual

- **Marca de trabalho:** Tela Pop.
- **Paleta:** ameixa quase preto `#17121D`, creme `#F7F4F1`, dourado `#FFC86A`, coral `#FF637E` e roxo `#74427F`.
- **Tipografia:** DM Serif Display nos títulos e Manrope nos textos e controles.
- **Navegação:** mobile first; no celular, marca e ações ficam no topo e o menu centralizado pode ser deslizado horizontalmente. No desktop, marca, menu e ações ficam em colunas independentes.
- **Home:** destaque editorial, últimas histórias, filmes, séries, críticas, lançamentos, guias de streaming, listas e catálogo.

## O pacote

- `wp-content/plugins/tela-pop-core/`: coleta RSS, pautas, dossiê, geração por IA, SEO, fila de revisão e trava de publicação.
- `wp-content/themes/tela-pop/`: tema responsivo, catálogo de filmes e séries, páginas de leitura e identidade visual.
- `instalar-pelo-painel/`: ZIPs do plugin e do tema, prontos para enviar no WordPress.

## Fluxo editorial

1. Cadastre fontes RSS de entretenimento em **Tela Pop → Fontes e feeds**.
2. Consulte os feeds. Os itens encontrados entram como pautas; a IA só usa os fatos presentes no dossiê.
3. Abra uma pauta e selecione um modelo: notícia, estreia, crítica, guia de streaming ou lista.
4. Gere o texto. A IA cria um post pendente de revisão; não publica por conta própria.
5. Confira a fonte, nomes, datas, elenco e disponibilidade. Edite e marque a revisão concluída antes de publicar.

O leitor de matéria completa só deve ser usado para fontes com autorização registrada. O sistema mantém RSS e referências como fonte, atribui fatos e sinaliza pontos a conferir. A disponibilidade em streaming pode mudar; confirme antes de publicar.

## Catálogo

O tipo de conteúdo **Filme ou série** guarda ficha independente do artigo: tipo, ano, plataformas e trailer oficial. Use a imagem destacada como pôster e confirme a disponibilidade antes de cadastrar. O portal pode publicar artigos pela fila de IA mesmo quando ainda não existe ficha de catálogo.

## Próximos passos

Consulte `INSTALACAO-WORDPRESS.md` para instalação no XAMPP e `FLUXO-EDITORIAL.md` para as etapas de configuração e uso.
