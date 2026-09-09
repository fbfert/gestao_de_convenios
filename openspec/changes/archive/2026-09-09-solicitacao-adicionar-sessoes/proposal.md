## Why

A Unimed libera 10 sessões por guia. Paciente que precisa de 20 no mês exige duas guias para a
mesma especialidade e o mesmo profissional, pedidas em momentos diferentes, sob o **mesmo pedido
médico**. Hoje não há como acrescentar nada a uma solicitação já criada: `SolicitacaoService::atualizar()`
mexe em médico, CIDs, data e observações, e não toca em `itens`. A saída da atendente é criar outra
solicitação do zero, duplicando paciente, médico, CID e anexos.

A ação "Adicionar sessões" depende de dois defeitos que hoje a tornariam inútil, e por isso eles
entram no mesmo change:

1. **Convênio manual gera guia só para o primeiro item.** `sincronizarGuiaDaSolicitacao()` faz
   `itens()->orderBy('id')->first()`. Já é defeito hoje, com multi-especialidade; com esta feature,
   item novo em convênio manual não geraria guia nenhuma.
2. **A solicitação aprovada não deixa enviar item novo.** `sincronizarStatusComGuias()` sai cedo
   quando o status não é `ready_for_automation` ou `guia_gerada`, e o gate de envio olha o status da
   **solicitação** em vez do item. O fluxo principal é pediu 10 → aprovou → quer mais 10; sem
   conserto, "Enviar para Unimed" nunca habilita.

## What Changes

- `sincronizarGuiaDaSolicitacao()` percorre todos os itens sem guia, e o valor de preenchimento de
  `numero_guia` passa a ser por item.
- O gate de envio à Unimed passa a ser por item, dos dois lados — front e backend.
- `sincronizarStatusComGuias()` passa a **refletir** os itens em vez de só evoluir, podendo regredir
  para `ready_for_automation` quando surge item sem guia.
- `solicitacao_itens.renovacao_de_item_id` liga o item novo ao item de origem da cadeia.
- `POST /solicitacoes/{solicitacao}/itens` acrescenta item a uma solicitação existente.
- `convenio_regras.sessoes_por_guia` passa a ser a fonte da quantidade padrão, e o `?? 10` sai do
  código.
- Modal "Adicionar sessões", com dois caminhos e quatro avisos não bloqueantes, alcançável pela
  listagem e pelo modal de detalhes.
- Item de renovação aparece identificado como continuação ("2ª remessa").

**BREAKING**: nenhuma quebra de contrato de API. O gate de envio fica **mais permissivo**, e o
status da solicitação passa a poder regredir — os dois são mudança de comportamento, não de formato.

## Correções ao brief, verificadas no código em 08/09/2026

O brief `docs/brief-adicionar-sessoes-na-solicitacao.md` foi conferido item a item. Quatro
constatações batem; duas não, e as duas mudam o trabalho.

**A rota de envio não está aberta.** O R2 e a tabela de riscos partem de "o botão some mas a rota
continua aceitando". `GerarGuiaUnimedService::avaliar()` já recusa quando o status não é
`ready_for_automation`, e mais estritamente que o front. Não há buraco de segurança hoje; o trabalho
do R2 é **afrouxar dos dois lados a checagem que existe**, não acrescentar a que falta.

**`qtd_autorizada_por_ciclo` não é "sessões por guia".** O R6 previa usá-lo como quantidade padrão. O
campo é uma **taxa**, emparelhada com `frequencia_lancamento` — o comentário em
`AntecipacaoService.php:107-114` diz isso com todas as letras ("descreve o ritmo de liberacao, ex.:
'1 por dia', nao o total da guia"), e as observações do `ConvenioRegraSeeder` confirmam: `qtd = 1`
com `frequencia = diaria` é gravado como "1 sessão por dia", e `qtd = 2` como "duas autorizações por
dia". Usá-lo como padrão poria **1** onde o brief espera 10 — trocaria um número errado hardcoded
por um número errado configurável, que é pior porque parece certo. O campo que significa "total
autorizado nesta guia" é `guias.sessoes_autorizadas`, e ele só existe depois que a operadora
responde, então não serve de padrão na criação.

**Decisão tomada (08/09/2026):** criar `convenio_regras.sessoes_por_guia`, nullable, ao lado do
campo existente, que fica intocado.

Ajustes menores: o valor de preenchimento já tem constante compartilhada
(`GuiaStatus::PREFIXO_NUMERO_PLACEHOLDER`), criada no change `solicitacao-guias-por-item-e-info` —
não criar outra. O `?? 10` está em **dois** pontos de `SolicitacaoService` (linhas 135 e 193), não um.
E o `canSend` lê `!item.guia`, não `!item.guia_id`, desde `remover-relacao-guia-legada`.

## Non-Goals

- **Modelar quantidade pedida + parcelas** (item guarda 20, guias somam 10+10). Mexeria no modelo, na
  automação e na conciliação. A coluna de vínculo é o degrau barato que mantém a porta aberta.
- **Alerta automático de saldo baixo.** Consome o vínculo, mas pertence a `central-de-alertas`.
- **Editar ou remover item existente.** Este change só adiciona.
- **Mexer em antecipação e conciliação.** N guias onde antes havia 1 muda o que a antecipação
  enxerga; merece atenção própria, com dado real na mão.
- **Recalcular status de solicitações antigas em massa.** Vale a partir da próxima transição de cada
  solicitação.
- **Reinterpretar `qtd_autorizada_por_ciclo`.** O campo continua sendo taxa de lançamento, e continua
  servindo só à antecipação.

## Dívidas assumidas

Registradas porque foram escolhas conscientes, e escolha consciente que não é escrita vira
descoberta cara depois.

**`TIPO_TERAPIA_PADRAO = 'especializada'` é fixo.** `convenio_regras` é por
`(convenio_id, tipo_terapia)`, e `solicitacao_itens` não guarda tipo de terapia nenhum — o valor
só nasce na guia, e já nascia fixo antes deste change. Consequência: convênio com regra
`convencional` de teto diferente terá a `especializada` lida **em silêncio**, sem erro e sem aviso.
Hoje não morde porque a única operação real (Unimed) é toda especializada. Resolver de verdade pede
tipo de terapia no item, o que arrasta automação e conciliação junto — change próprio.

**`STATUS_QUE_BLOQUEIAM_ENVIO` e `STATUS_QUE_BLOQUEIAM_ADICAO` estão duplicados em `types.ts`.**
O backend é a fonte de verdade e aplica as duas regras de novo, então divergir não abre buraco de
segurança — abre incoerência de tela: botão habilitado com a API recusando. O comentário aponta o
backend, mas **comentário não é trava**. São três strings e duas listas hoje; se crescerem, ou se
uma terceira lista aparecer, vale expô-las pela API em vez de repeti-las.

**`qtd_autorizada_por_ciclo` continua sem consumidor além de `AntecipacaoService:115`**, onde é
fallback de `guia.sessoes_autorizadas`. Este change não o adotou de propósito — ele é taxa de
lançamento, não teto por guia (ver a correção ao brief acima). Segue sendo um campo com CRUD, com
vigência e com quase nenhum uso; se a antecipação um dia parar de precisar dele, é candidato a sair.

## Capabilities

### Added Capabilities
- `solicitacao-adicionar-sessoes`: acrescentar itens a uma solicitação existente, com vínculo de
  renovação, quantidade padrão vinda da regra do convênio e avisos não bloqueantes.

## Impact

- Backend Laravel: `SolicitacaoService` (sincronizarGuiaDaSolicitacao, numeroGuiaDaSolicitacao,
  sincronizarStatusComGuias, criar), `SolicitacaoController`, `StoreSolicitacaoItemRequest` (novo),
  `SolicitacaoItem`, `SolicitacaoResource`, `ConvenioRegraService`, `GerarGuiaUnimedService`,
  `routes/api.php`.
- Migrations: `solicitacao_itens.renovacao_de_item_id` e `convenio_regras.sessoes_por_guia`.
- Frontend React: `SolicitacoesPage`, `SolicitacaoGuiaModal`, `AdicionarSessoesModal` (novo),
  `SolicitacaoItensFields`, `useSolicitacoes`, `types.ts`, `ConveniosPage` (campo novo na regra).
- Documentação: `docs/schema.md` (duas colunas novas) e ADR sobre o status refletir os itens.
