# De Olho na Bola — Fluxo editorial e responsabilidades

```
RSS ──▶ 1 Coleta ──▶ 2 Prioridade ──▶ 3 Rascunho (IA) ──▶ 4 Termômetro + revisão ──▶ 5 Publicação
        sistema      nota orienta      evidências           PESSOA                     PESSOA
```

Regra de ouro: **nada é publicado sem uma pessoa**. A IA só entrega texto na Fila de revisão.

Este guia descreve o fluxo editorial que continua sendo executado localmente pelo plugin em cada WordPress. A nova plataforma central em `plataforma-central/` começa pela conexão de instalações independentes e pelo envio idempotente de rascunhos; coleta RSS, geração central de IA e revisão centralizada ficam para fases posteriores.

## Quem faz o quê

| Etapa | Responsável | O que faz | Arquivo do plugin |
|---|---|---|---|
| 1 Coleta | Sistema | Lê os feeds RSS ativos e guarda título, resumo curto, link e data original. Ignora itens sem data, com data futura ou publicados há mais de 72 horas. Se outra fonte noticiou o mesmo fato, guarda como **corroboração** (sobe a confiança). | `includes/sources.php` |
| 2 Triagem | Sistema | Dá uma nota de prioridade (0–100). A nota e a confiabilidade organizam a fila, sem impedir rascunhos recentes. Pautas RSS antigas ou sem data não podem ser geradas como notícia atual. | `includes/scoring.php`, `includes/pipeline.php` |
| 3a Dossiê | Sistema | Junta os **dados reais**: resumo do feed, texto da matéria (só fontes com uso liberado), outras fontes sobre o fato e jogos/classificação da API-Football. | `includes/dossier.php` |
| 3b Redação | IA | Escreve texto original **só com o dossiê**, atribuindo cada fato à fonte. Depois o sistema confere o texto: números, citações e nomes que não estão no dossiê viram **pontos a checar**. Mesmo com poucos dados, gera rascunho proporcionalmente curto e sinaliza as lacunas. | `includes/ai-writer.php` |
| 4 Revisão | **Pessoa** | Lê o texto e os pontos a checar, edita, aprova ou rejeita. Exige marcar “conferi” e ter fontes registradas. | `includes/review.php` |
| 5 Publicação | **Pessoa** | “Aprovar e publicar” (ou publicar pelo editor). A trava do plugin bloqueia texto sem revisão ou sem fonte. | `includes/article-sources.php` |
| Orquestração | Sistema | Rodada a cada 15 min, limites por rodada e por dia, aviso por e-mail, registro de atividade. | `includes/pipeline.php` |
| Newsletter | Sistema + pessoa | Inscrição com dupla confirmação, resumo semanal. | `includes/newsletter.php` |

## Onde mexer em cada coisa (menu **De Olho na Bola**)
- **Visão geral** — as 5 etapas com números, pautas mais fortes, atividade recente, checklist de configuração.
- **Fila de revisão** — o trabalho humano do dia.
- **Pautas** e **Fontes RSS** — cadastro e ajustes manuais.
- **Configurações** — abas: Fluxo automático · Redação (IA) · Dados esportivos · Regras editoriais · Integrações.

## Os três modos
- **Manual**: você aciona a coleta RSS e a geração. Uma pauta pode gerar rascunho sem aprovação prévia.
- **Assistido** (padrão): a coleta RSS roda automaticamente; você escolhe pautas e aciona a geração.
- **Rascunhos automáticos**: coleta e geração rodam automaticamente. A IA só trabalha com fonte confiável/confirmada e dados suficientes; os textos sempre vão para a Fila de revisão. O sistema nunca publica sozinho.

Em todos os modos, a **nota e a confiabilidade da fonte são sinais de qualidade**, não bloqueios. A IA gera um rascunho com o que o RSS trouxe, mesmo quando o material é curto; o termômetro e a lista de alertas destacam o que precisa de conferência. A pauta rejeitada ou já processada não entra novamente; o limite diário e erros da API continuam valendo. A exceção editorial é a atualidade: RSS sem data ou com mais de 72 horas é descartado, e uma pauta RSS antiga já existente não pode ser gerada como notícia atual.

O termômetro (0–100) combina nota cadastrada da fonte, volume de dados no dossiê, corroboração por outras fontes, auditoria de números/citações/nomes e sinal declarado pela IA. **Não é garantia de veracidade nem bloqueia o rascunho**: use os componentes e alertas para decidir o que conferir.

## Como evitamos que a IA invente
1. Dossiê com dados reais e numerado; a IA é proibida de usar qualquer coisa fora dele.
2. Aspas só se a frase existir no dossiê.
3. Dados insuficientes = a IA gera um rascunho curto, marca baixa evidência no termômetro e pede conferência/complemento.
4. Conferência automática pós-texto: números, citações e nomes ausentes do dossiê aparecem como alerta; citação inventada rebaixa a confiança para “baixa”.
5. Teto por rodada e por dia (custo e carga de revisão).
6. Revisão humana obrigatória antes de publicar.

## Limites que você precisa conhecer
- **Direitos autorais**: a extração do texto integral só ocorre para fontes marcadas como *Licenciada*, quando existe autorização escrita e a opção “Ler a matéria completa” está ligada. Fontes *Oficial* sem autorização de reprodução e fontes *somente descoberta* contribuem com título, resumo e link do RSS. “Oficial” identifica a origem do comunicado; não significa licença para copiar uma página inteira.
- **Fatos apurados manualmente**: o editor pode acrescentar fatos e referências na pauta. Eles são claramente identificados no dossiê e também precisam ser conferidos antes da publicação.
- **Fontes relacionadas**: a semelhança entre títulos ajuda a encontrar cobertura relacionada, mas não prova que seja o mesmo fato ou uma confirmação independente. O editor verifica isso.
- **Extensão do artigo**: selecione Padrão (300–500 palavras) ou Detalhado (500–800) em Redação (IA). A meta de palavras vale somente se o dossiê sustentar; sem informação suficiente, o texto fica menor e aponta o que falta.
- **Mais conteúdo na pauta**: feeds de fontes marcadas como *Licenciada / autorizada por escrito* agora aproveitam o campo RSS `content:encoded` quando ele trouxer mais texto que a descrição. Para outras fontes, a coleta continua usando título, descrição e link. Confirme a autorização da fonte antes de marcar essa opção; o material é usado como base factual para reescrita e segue para revisão humana.
- A conferência automática **ajuda, não garante**: ela não sabe se o dossiê está certo. Quem garante é a revisão.
- Texto com poucas fontes tende a ser curto; é proposital.

## Provedor de IA

Na aba **Configurações > Redação (IA)** selecione OpenAI ou GroqCloud. Cada provedor mantém chave e modelo separados. A seleção inicial é OpenAI para preservar instalações existentes. O teste consulta a lista de modelos do provedor; a geração usa o respectivo endpoint Chat Completions. Os artigos continuam passando pelo mesmo dossiê, auditoria e fila de revisão.

## Navegação pública

A aba **Notícias** abre uma central mobile first com atalhos para últimas notícias, Brasileirão, Libertadores, Copa do Brasil, Resultados e Jogos na TV, além de páginas publicadas de clubes. Resultados e Jogos na TV são categorias editoriais para matérias revisadas pela equipe. A API-Football existente fornece partidas e placares; não fornece canais de transmissão, então horários e emissoras de TV precisam ser apurados e atualizados manualmente.

Na página inicial, com a API-Football configurada, o bloco **Últimos placares** mostra partidas encerradas da liga/temporada selecionada. Cada cartão desliza entre placar com escudos e estatísticas de posse, chutes, chutes ao gol e escanteios, quando a API tiver cobertura. As partidas ficam em cache por 6 horas e as estatísticas disponíveis por até 24 horas; respostas sem estatísticas são tentadas novamente após 30 minutos para controlar a cota. Também pode ser inserido em uma página com `[bec_partidas count="5"]`. A cobertura de estatísticas pode variar por competição.
