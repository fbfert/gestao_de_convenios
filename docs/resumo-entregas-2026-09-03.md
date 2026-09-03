# Entregas de 03/09/2026 — status Histórico (rastro do xlsx migrado), CRM/UF do médico, médico solicitante na guia

## Contexto: o arquivo `neurokids_guias_unimed_1.xlsx`

Investigação prévia confirmou que esse arquivo **não** entrou pela importação padrão do sistema —
um script PHP avulso de uma sessão anterior leu a planilha e criou 2238 guias diretamente, sem
Solicitação de origem, com Especialidade/Profissional marcados como `"A DEFINIR"` (bloqueado de
automação em `resumo-entregas-2026-09-02.md`, item 2). Pedido do usuário: dar a essas guias um
**rastro histórico Pedido → Guia**, sem reabrir automação — não uma reimportação, um registro.

946 dessas 2238 guias foram casadas manualmente contra o catálogo real de Profissional/Especialidade
(usando a especialidade já cadastrada do profissional identificado, nunca um palpite por texto —
ver "Erros e fixes" na memória de sessão) e corrigidas antes do trabalho abaixo.

## 1. Status Histórico em Solicitação — rastro Pedido → Guia sem reabrir automação

Novo status `'historico'` em Solicitação — nunca produzido por transição manual da tela (fora de
`STATUS_PERMITIDOS`, igual `'approved'`), só por backfill direto ligando uma Guia já existente a
uma Solicitação/Item reconstruídos para as 2238 guias do xlsx. `listar()` exclui esse status por
padrão; filtro `mostrar_historico` inverte pra mostrar só eles. Botão **"Mostrar Histórico"** na
tela de Solicitações, ao lado do indicador de Status ativo — mesmo padrão do botão "Mostrar guias
A DEFINIR". Itens já nascem com `status_operacional = 'guia_generated'`; Médico/CID ficam em
branco (não estavam na planilha).

Commit: `7f676ad`.

## 2. Guia de Solicitação Histórico nunca entra em automação Unimed — mesmo depois de corrigida

O bloqueio por "A DEFINIR" (02/09) está amarrado ao dado placeholder: assim que
Especialidade/Profissional forem corrigidos numa das 2238 guias migradas, elas deixam de ser "A
DEFINIR" e voltariam a ser elegíveis pra automação — o que não faz sentido pra um registro
histórico. Novo scope `Guia::naoHistorica()`/`ehHistorica()` checa se a Solicitação de origem
(via `solicitacaoItem.solicitacao`) tem status `'historico'`, **independente** de A DEFINIR —
continua excluída mesmo depois da correção. Aplicado nas duas queries em lote do job de fila e nos
dois `avaliar()` de automação (cobre também o clique manual).

Commit: `5031e74`.

## 3. Alerta de guias negadas não lista guia histórica

O card "Atenção" (negadas pendentes de revisão) só filtrava por status + ocultação manual — guias
ligadas a uma Solicitação Histórico apareciam pedindo "nova solicitação" assim que saíam de A
DEFINIR, mas são passado resolvido, não negação de verdade. A correção das 946 guias A DEFINIR
revelou 55 dessas indevidamente no card (de 57 exibidas, só 2 eram negações reais). Reusa o mesmo
scope `naoHistorica()`.

Commit: `6109d2a`.

## 4. Status Histórico em Guia — por resultado, não genérico

Pedido inicial do usuário foi um único status genérico `'historico'` na Guia; a resposta trouxe
uma correção de rota explícita: criar um status **composto por resultado**
(`historico_approved`, `historico_denied`, `historico_finalized`, ...), preservando o desfecho
real dentro do próprio valor de status em vez de perder essa informação. Implementado em
`GuiaStatus::paraHistorico()`/`ehHistorico()` (prefixo `historico_`). Botão **"Histórico"** na
tela de Guias, ao lado de "Mostrar guias A DEFINIR" — some da listagem padrão, só aparece sob
demanda. As 2238 guias (corrigidas e ainda A DEFINIR) foram migradas pro novo status: 366
`historico_approved`, 1512 `historico_finalized`, 184 `historico_denied`, 109
`historico_under_review`, 56 `historico_canceled`, 11 `historico_needs_verification`.

Dois sinais deliberadamente separados coexistem agora: `naoHistorica()`/`ehHistorica()` (item 2,
baseado na Solicitação vinculada — usado pra elegibilidade de automação) e
`semStatusHistorico()`/`comStatusHistorico()` (baseado no status da própria Guia — usado pra
listagem/exibição).

**Bug pego em QA ao vivo antes de ir pro ar:** com `mostrar_historico=1` e `mostrar_a_definir`
não marcado, o ramo padrão do filtro A DEFINIR ainda aplicava `comDadosDefinidos()`, escondendo as
1292 guias históricas que continuavam A DEFINIR (a lista mostrava 946 de 2238 esperadas).
Corrigido envolvendo o bloco A DEFINIR num `when(!mostrar_historico, ...)` — trava por teste
dedicado (`test_historico_mostra_mesmo_a_que_ainda_esta_a_definir`).

Commit: `6a5cd00`. Testes: `GuiasApiTest`.

## 5. Fix incidental: indicador de Convênio na lista de Solicitações

`currentConvenio` lia `form.convenio_id` (o convênio pré-selecionado em Nova Solicitação, sempre o
primeiro em ordem alfabética) em vez de `filters.convenio_id` — o indicador na barra de filtros
mostrava sempre "Convênio: Celos", sem relação com o filtro de fato aplicado na lista.

Commit: `57b30b5`.

## 6. Investigação: por que solicitações do Dr. Lucas Igarashi salvaram como "médico não cooperado" na Unimed

Sonda ao vivo, somente leitura, contra `rda.unimedsc.com.br` (sem finalizar nada, sem expor a
credencial no transcript) confirmou que o Dr. Lucas Igarashi **é** cooperado ativo
("OK - Ativo", "Prestador da Rede Unimed") — mas a busca por CRM falhava porque o cadastro tinha
`"CRM-SC 25760"` (o campo do portal, `s_nr_crm`, espera só dígitos) e a busca por nome falhava
porque faltava o nome do meio real ("Yuji"). As duas buscas caindo, `selecionarPrestador()`
(`worker-unimed/src/operations/gerarGuia.js`) recorre ao fallback literal "não cooperado" — mesmo
o médico sendo cooperado de verdade. Registro corrigido (`nome` → "Lucas Yuji Igarashi", `crm` →
"25760"). As 3 guias já submetidas antes da correção (2267, 2269, 2270) foram enviadas à Unimed
com esse rótulo — corrigir o cadastro local não retroage sobre o que já foi submetido; se isso
tiver efeito de faturamento, precisa de acompanhamento manual junto à Unimed.

Nenhum commit — investigação e correção de dado (registro único).

## 7. CRM só números; UF vira campo próprio (`Medico.crm_uf`)

O achado do item 6 é sistêmico: `Medico.crm` guarda formatos livres e mistos (`"CRM 123456"`,
`"CRM-SC 25760"`, `"CRM/SC 33001"`) e o portal só busca por dígitos — qualquer prefixo ou sufixo
faz a busca por CRM falhar em silêncio. Separado em dois campos: `crm` (só dígitos, migration +
regra `regex:/^[0-9]+$/` em `StoreMedicoRequest`/`UpdateMedicoRequest`) e `crm_uf` (2 letras,
maiúsculo). Cobre: CRUD de Médicos (`MedicosPage.tsx` — dois inputs, um deles restrito a dígitos),
cadastro rápido dentro de "Ler Pedido Médico" (`SolicitacaoController::storeMedicoRapido` — o
antigo placeholder de texto `'PENDENTE'` vira `null`, já que não é numérico), e o prompt de
extração por IA em `PedidoMedicoAiService` (pedia `"CRM/SC 12345"` embutido; agora pede
`medico_crm`/`medico_crm_uf` como chaves separadas).

**Limpeza de dado retroativa em produção** (confirmada com o usuário antes de aplicar): dos 16
médicos cadastrados, 10 tinham CRM em formato misto — todos separados em `crm`/`crm_uf` (inclui
Fabiana Tybusch, Cristiane Farias Heinzen, Aline Niero de Carvalho, Otávio Cavalli de Bortoli,
Fabiana Stradioto Sartor e o próprio Lucas Yuji Igarashi), evitando que o mesmo bug de "não
cooperado" apareça pra eles na automação.

Commits: `f002e32` (split CRM/UF), `3837124` (UF junto do CRM nas sugestões da IA de "Ler Pedido
Médico" — CRM sozinho não identifica o médico, o número se repete entre estados; mesmo motivo pra
incluir `crm_uf` na chave de upsert dos seeders). Testes: `MedicosApiTest`,
`PedidoMedicoSolicitacaoApiTest`.

## 8. Guia: novos campos "Médico solicitante" e "Cooperado na Unimed"

Consequência direta do item 6/7: o dado de qual médico pediu a guia, e se a automação o achou como
cooperado no portal, já existia (Solicitação → Médico; `medico_strategy` no resultado da execução
que gerou a guia) mas não aparecia em lugar nenhum da tela. Detalhe da guia ganhou dois campos:
**Médico solicitante** (nome + CRM/UF, vindo da Solicitação vinculada) e, só para guias Unimed,
**Cooperado na Unimed** — badge Cooperado (achado por CRM ou nome) / Não cooperado (fallback) /
Não verificado (guia sem geração automatizada, manual ou importada).

Commit: `3837124` (mesmo commit do item 7, empacotados juntos). Testes:
`GuiasApiTest::test_detalhe_expoe_medico_solicitante_e_estrategia_unimed`.

## Verificação — como cada entrega foi validada

- `run-tests.sh` (backend, sqlite isolado) + `tsc -b`/`oxlint`/`verificar-design-system.mjs`
  (frontend) antes de cada deploy, com confirmação do usuário antes de recriar os containers.
- Itens 1–5 e 7–8: verificados ao vivo com usuário QA descartável (Playwright contra a produção
  real), scripts e usuários removidos depois.
- Item 6: sonda `.mjs` descartável, somente leitura (nunca clica "Selecionar" num resultado, nunca
  chega em finalizar), credencial nunca impressa no transcript (lida de um arquivo temporário
  criado dentro do container, copiado e apagado em seguida).
- Backup manual de produção (`backup-gescon.sh`) rodado antes da limpeza retroativa de CRM/UF em
  produção (item 7), além do cron diário de 01:15.

## Pendente

- As 3 guias do Dr. Lucas Igarashi já submetidas como "não cooperado" (item 6) não foram
  corrigidas junto à Unimed — só o cadastro local. Sem confirmação de que isso afeta faturamento.
- Distinção `finalized` vs `approved` (duplicada como "Aprovado" até 31/08) segue sem revisão
  estrutural — só o rótulo foi resolvido, não os dois status pro mesmo conceito.
