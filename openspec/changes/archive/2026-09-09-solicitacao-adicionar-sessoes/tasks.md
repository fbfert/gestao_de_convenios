# Tarefas

Quatro commits, na ordem da seção 4 do brief. Cada um fecha verde por si.

## Commit 1 — Guia por item em convênio manual (R1)

- [x] 1.1 **Pré-condição — resultado (09/09/2026, cópia local do dump de produção de 08/09):** a query
      volta **vazia**, e pelo motivo certo. As 2.278 solicitações são todas da Unimed; os quatro
      convênios manuais (Celos, Particular, Pladisa, SC Saúde) existem cadastrados e têm **zero**
      solicitações. Não há backfill a decidir, e a correção é só para frente — o R1 conserta o
      defeito **antes** da primeira solicitação manual existir. Levantamentos de apoio: 0 solicitações
      sem item nenhum (o laço por itens não perde caso algum) e 0 guias com valor de preenchimento (a
      mudança de formato não colide com dado existente).
- [x] 1.2 `sincronizarGuiaDaSolicitacao()` percorre **todos** os itens sem guia, criando uma guia por
      item, em vez de só o primeiro.
- [x] 1.3 O valor de preenchimento de `numero_guia` passa a ser por item. Sem isso, as N guias nascem
      com o mesmo "número": o índice `(convenio_id, numero_guia)` **não é unique**, então não estoura
      — só faz qualquer busca por número devolver a guia errada.
- [x] 1.4 Reusar `GuiaStatus::PREFIXO_NUMERO_PLACEHOLDER`, que **já existe** (criada em
      `solicitacao-guias-por-item-e-info`). Não criar uma segunda constante.
- [x] 1.5 Item que já tem guia não é tocado. O caminho de **atualizar** a guia encontrada saiu junto:
      ele a devolvia para `under_review`, e repeti-lo dentro de um laço resetaria guias já aprovadas
      dos outros itens.
- [x] 1.6 Testes: `GuiaPorItemConvenioManualTest` — 3 itens geram 3 guias com números distintos;
      repetir a sincronização não duplica; item que já tem guia não é tocado.
- [x] 1.7 Dois testes existentes foram reescritos porque codificavam o defeito:
      `test_solicitacao_vira_aprovada...` forjava à mão a guia do segundo item ("fluxo legado só cria
      guia pro primeiro item"), e `test_solicitacao_vira_guia_gerada...` dependia de um item sem guia
      em convênio manual, estado que deixou de existir. O segundo passou a declarar
      `connector_driver = 'unimed_rda'`: o `ConvenioSeeder` cria os três convênios de teste como
      manuais, então não havia convênio com automação na semente.

## Commit 2 — Gate por item e status refletindo os itens (R2, R3)

- [x] 2.0 `App\Support\SolicitacaoStatus` (novo) passa a ser o lugar único das duas perguntas:
      `BLOQUEIAM_ENVIO` e `DERIVADAS_DOS_ITENS`. Sem ele, "a mesma regra nos dois lados" seria duas
      listas de strings soltas que divergem na primeira mudança. O front espelha em
      `STATUS_QUE_BLOQUEIAM_ENVIO` (`types.ts`), com o backend declarado como fonte de verdade.
- [x] 2.1 `canSend` deixa de exigir `solicitacao.status === 'ready_for_automation'`: passa a ser
      convênio com automação + item sem guia + sem execução ativa + solicitação fora de
      `under_review`, `denied` e `historico`.
- [x] 2.2 Mesma regra em `GerarGuiaUnimedService::avaliar()`, que exigia
      `status === 'ready_for_automation'`. **Correção ao brief:** a rota não estava aberta; o trabalho
      foi afrouxar a checagem existente, não acrescentar a que falta. Afrouxar só o front deixaria o
      botão habilitado e a API recusando — pior que o estado anterior.
- [x] 2.3 `sincronizarStatusComGuias()` passa a refletir os itens e pode regredir: item sem guia em
      `guia_gerada`/`approved` volta a `ready_for_automation`; todos com guia e nem todas aprovadas →
      `guia_gerada`; todos aprovados/finalizados → `approved`.
- [x] 2.4 `under_review`, `denied` e `historico` continuam intocados.
- [x] 2.5 O método segue **puro de status**: grava com `update()` direto e não chama `alterarStatus()`
      nem `sincronizarGuiaDaSolicitacao()`. Chamar `alterarStatus('ready_for_automation')` dispararia
      a criação de guias, que mexe em status de guia, que chamaria esta sincronização de novo. A
      criação em convênio manual é disparada explicitamente por quem altera o status. Decisão
      registrada em comentário no próprio método.
- [x] 2.6 Guarda do `!==` mantida: `Solicitacao` é Auditable, e gravar o mesmo valor a cada
      sincronização encheria a trilha de linhas sem mudança. Há teste travando isso.
- [x] 2.7 Testes: `SolicitacaoStatusRefleteItensTest`, nove cenários — regressão de `approved`,
      `guia_gerada`, `approved`, `denied`/`historico`/`under_review` intocados, idempotência sem ruído
      de auditoria, e as duas pontas da rota (recusa em `under_review`, aceita item sem guia em
      `approved`).

## Commit 3 — Vínculo, endpoint e quantidade da regra (R4, R5, R6)

- [x] 3.1 Migration `solicitacao_itens.renovacao_de_item_id`, nullable, FK para a própria tabela
      (`nullOnDelete`: apagar a origem não pode levar junto sessões pedidas de verdade). Relações
      `renovacaoDe`, `renovacoes` e o auxiliar `origemDaCadeia()` no model, e o campo no `fillable`.
- [x] 3.2 Migration `convenio_regras.sessoes_por_guia`, nullable. **Não** reinterpretar
      `qtd_autorizada_por_ciclo`, que é taxa de lançamento e continua servindo só à antecipação.
- [x] 3.3 Campo novo no CRUD de regras da tela de Convênios (feito no commit 4). Os dois campos
      numéricos ganharam **rótulo e frase de ajuda**: só placeholder deixava trocar um pelo outro em
      silêncio, e o estrago é pôr "1 por dia" onde vai o teto de 10 por guia.
- [x] 3.4 `ConvenioRegraService::vigente()` lê a regra por `(convenio_id, tipo_terapia)` respeitando
      `vigente_desde`/`vigente_ate` — a mesma leitura que `AntecipacaoService` fazia inline.
- [x] 3.5 `POST /solicitacoes/{solicitacao}/itens` com permissão `solicitacoes.manage` e
      `StoreSolicitacaoItemRequest` no padrão do `StoreSolicitacaoRequest`.
- [x] 3.6 Recusa em `denied` e `historico` via `SolicitacaoStatus::BLOQUEIAM_ADICAO`, lista nova ao
      lado de `BLOQUEIAM_ENVIO` — parecidas o bastante para alguém reusar a errada se ficassem em
      arquivos diferentes.
- [x] 3.7 Repetição de especialidade + profissional **é permitida**: sem unique, sem validação de
      duplicata.
- [x] 3.8 `renovacao_de_item_id` tem que ser item da mesma solicitação, e é normalizado para a
      **origem da cadeia** quando aponta para um item que já é renovação.
- [x] 3.9 Após criar, chama `sincronizarStatusComGuias` **e**, em convênio manual e só a partir de
      `derivaDosItens`, dispara explicitamente `sincronizarGuiaDaSolicitacao`. Em `under_review` não
      gera guia: isso pularia a análise que o status representa.
- [x] 3.10 Removidos os **dois** `?? 10` de `SolicitacaoService`. Sem quantidade informada e sem
      `sessoes_por_guia` vigente, a API **recusa (422)** em vez de arbitrar — é o que "não invente um
      número" significa na prática. Muda o contrato de criação de solicitação para convênio sem a
      regra cadastrada.
- [x] 3.11 `SolicitacaoResource` expõe `renovacao_de_item_id`, `posicao_na_cadeia` e
      `total_na_cadeia`, calculados sobre a coleção já carregada — sem consulta nova.
- [x] 3.12 `GET /solicitacoes/{solicitacao}/contexto-adicao` devolve os quatro dados dos avisos
      calculados no servidor: idade do pedido médico, sessões por guia, existência de item igual e
      soma da cadeia.
- [x] 3.13 Testes: `SolicitacaoAdicionarItemTest`, 12 cenários.
- [x] 3.14 `docs/schema.md` atualizado com as duas colunas novas; de quebra, `solicitacao_itens`
      passou a existir no documento e a lista de status de `solicitacoes` deixou de estar
      desatualizada.

## Commit 4 — Modal e entradas (R7, R8, R9)

- [x] 4.1 Entrada com ícone `+` **dentro** do menu de Ações da listagem, e botão "Adicionar sessões"
      no cabeçalho do `SolicitacaoGuiaModal`. Só aparecem com `solicitacoes.manage` e em status que
      aceita. Começou como botão AO LADO do menu, e a suíte E2E reprovou: a tabela é `table-fixed`
      com as larguras somando 100%, e o segundo botão transbordava a célula — o ícone da coluna Info
      passava a interceptar o clique.
- [x] 4.2 `AdicionarSessoesModal`, caminho A: repetir especialidade já pedida, listando os itens
      atuais, pré-preenchendo especialidade, profissional e quantidade, e gravando
      `renovacao_de_item_id`.
- [x] 4.3 Caminho B: especialidade nova, reaproveitando `SolicitacaoItensFields`.
- [x] 4.4 Os quatro avisos, vindos do backend, **todos não bloqueantes** — nenhum desabilita o botão
      de confirmar.
- [x] 4.5 Aviso sem fonte não aparece (convênio sem regra vigente = sem aviso de limite).
- [x] 4.6 Item de renovação identificado como continuação ("Fonoaudiologia · 2ª remessa") na coluna
      Itens e nas abas do modal.
- [x] 4.7 Tokens do design system, sem hex; ícone do `lucide-react`.
- [x] 4.8 Teste Playwright: adicionar repetido mostra o aviso e conclui; o item novo aparece como 2ª
      remessa; em convênio Unimed o botão de enviar habilita para o item novo.

## Fechamento

- [x] 5.1 Varrer o repo por `?? 10` ou outro limite de convênio hardcoded em Service/Controller.
- [x] 5.2 Confirmar constante única do valor de preenchimento em todo o repo.
- [x] 5.3 `php artisan test`, `npm run lint` e `npm run test:e2e` verdes.
- [x] 5.4 `docs/schema.md` atualizado com as duas colunas novas.
