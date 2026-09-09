## Context

O `GET /dashboard` já dispara treze `count()` por requisição e o painel faz polling de 30 segundos. Qualquer número novo precisa entrar sem multiplicar isso.

A fase anterior deixou duas fontes prontas: `guias.negada_em` (coluna, barata, indexada por `(tenant_id, negada_em)`) e `guia_status_historico` (a série, indexada por `(tenant_id, para, ocorrido_em)`).

## Goals / Non-Goals

**Goals:**
- Três linhas, cada uma abrindo a listagem já filtrada.
- Contagem por data de transição, não por data de criação.
- O card inteiro em duas consultas.
- Primeiro consumidor de `senha_alerta_dias`.

**Non-Goals:**
- Generalizar o padrão para as outras áreas.
- Remover o banner de guias negadas.
- Qualquer coisa que exija migration.

## Decisions

- **Duas consultas, e não seis.** A primeira é uma agregação condicional sobre `guias` (`SUM(CASE WHEN ...)`), que traz de uma vez as pendentes, as em análise e as duas faixas de senha vencendo. A segunda é sobre `guia_status_historico`, agrupada por `para`, e traz "hoje" e "na semana". Racional: seis `count()` a cada 30 segundos, somados aos treze que já existem, transformariam o painel na consulta mais cara do sistema.

- **"Hoje" e "na semana" contam guias distintas, não transições.** `COUNT(DISTINCT guia_id)`. Racional: uma guia negada duas vezes no mesmo dia é uma guia negada hoje, não duas.

- **O número grande é o que exige ação.** Em "Negadas", são as que ainda têm alerta visível (`alerta_negacao_ocultado_em` nulo); as ocultadas já foram tratadas por alguém e sairiam como cobrança repetida.

- **Guia histórica fica de fora.** O mesmo critério que o `GuiaAlertaNegacoes` já usa: rastro de migração é passado resolvido, e infla o card com trabalho que não existe.

- **`senha_alerta_dias` é lido por tenant, sem valor embutido no código.** Racional: é a regra de ouro do projeto (ADR-03), e este é o primeiro consumidor da configuração.

- **A URL da linha usa os filtros que a listagem já entende.** `status=denied&pendente=1` e `senha_vencendo=1` mapeiam para os filtros existentes `alerta_negacao_pendente` e `validade_senha_vencendo_em_dias`. Racional: nome de filtro na URL é contrato com quem cola link no chat; `pendente` e `senha_vencendo` dizem o que a pessoa quer, e a tradução para o filtro interno fica no controller.

- **O bloco `usuarios` continua na resposta.** Ver a correção de premissa no `proposal.md`: ele é consumido por `metricKey` nas telas de grupo.

## Risks / Trade-offs

- **[Agregação condicional em SQL cru]** -> `SUM(CASE WHEN ...)` não é portátil por natureza, mas a forma usada aqui é ANSI e roda igual em SQLite (desenvolvimento e testes) e MariaDB (produção). O que seria arriscado é função de data específica de fabricante; por isso as fronteiras de dia e semana são calculadas em PHP e entram como parâmetro.

- **[O card depende do backfill da fase anterior]** -> Guias antigas só aparecem em "hoje/na semana" se tiverem histórico. O backfill marca essas linhas com `origem='migracao'` e usa a data do evento da trilha — para as janelas curtas do card isso é irrelevante, porque nenhuma guia migrada transicionou nas últimas 24 horas.

- **[Duas fontes para a mesma verdade]** -> "Negadas pendentes" sai de `guias`, "negadas hoje" sai do histórico. Se as duas divergirem, é sinal de que alguém escreveu status por fora — e a trava da fase anterior torna isso impossível pelos caminhos de model.
