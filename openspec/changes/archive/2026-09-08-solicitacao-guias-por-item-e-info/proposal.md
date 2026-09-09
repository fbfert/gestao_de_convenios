## Why

Uma solicitação com várias especialidades gera várias guias, mas a tela mostra só uma — e, no lugar do número da guia, mostra o **id interno**. A atendente lê "#12" e liga para a operadora com um número que não existe lá.

## Constatações confirmadas no código

As cinco afirmações do brief foram verificadas antes de escrever esta spec. Todas se confirmam; duas têm ajuste de detalhe.

**1. `Solicitacao::guia()` é `hasOne` sem ordenação** — `api/app/Models/Solicitacao.php:65-68`. Desde a multi-especialidade cada `SolicitacaoItem` tem a sua guia, e este `hasOne` devolve *uma qualquer*, variando com a ordem física das linhas. O `SolicitacaoGuiaModal.tsx:201` consome exatamente isso (`solicitacao?.guia?.id`). Confirmado.

**2. A listagem mostra o id interno** — `Guia #{item.guia_id}` em `web/src/features/solicitacoes/SolicitacoesPage.tsx:863`. Confirmado.

**3. `numero_guia` tem três estados** — confirmado:
- número real da operadora;
- placeholder `GUIA-SOLICITACAO-{id}`, gerado em `SolicitacaoService::numeroGuiaDaSolicitacao()`;
- `null`, viabilizado pelo `ALTER TABLE guias MODIFY numero_guia VARCHAR(255) NULL` da migration `2026_08_03_200000_add_unimed_v2_foundation_fields`.

*Ajuste:* o brief situa o gerador na linha 335; hoje ele está em **355-358**. O arquivo cresceu com o change `guia-status-historico`, que passou a rotear as transições de status por `GuiaService::registrarTransicao`. O método é o mesmo.

**4. O badge só existe para Unimed** — `isUnimedRda && item.guia_id` em `SolicitacoesPage.tsx:866`, com o espelho em 904. Confirmado.

**5. Os eager loads da coluna Info já existem** — confirmado, com um ajuste importante de localização:

| Onde | Arquivo |
|---|---|
| `listar()` | `SolicitacaoService.php:53` |
| `store()`, `show()`, `update()`, `aprovar()`, `negar()`, **`updateStatus()`** | `SolicitacaoController.php`, em `->load([...])` por método |

*Ajuste:* o brief diz seis pontos e os atribui ao serviço. São **sete**, e seis deles vivem no controller, cada um com a sua lista literal repetida. `updateStatus()` não está no brief. Isso não muda o requisito — `itens.guia` está carregado em todos —, mas muda onde conferir, e significa que qualquer campo novo precisaria ser acrescentado em sete listas duplicadas. Fica registrado como candidato a change próprio (extrair a lista para uma constante), fora do escopo deste.

**Consumidor a mais de `item.guia_id`.** O brief cita `SolicitacaoAnexos.tsx:254` como o único ponto que impede remover a relação legada. Há um segundo uso no mesmo arquivo, `:308` (`travado={Boolean(item.guia_id)}`), e cinco em `SolicitacoesPage.tsx` (835, 842, 861, 866, 904). Relevante para o passo do R8 que remove `guia_id` do payload.

## What Changes

- `SolicitacaoResource` passa a expor, em cada item, um objeto `guia` com `id`, `numero_guia` e `status`.
- O modal monta **uma aba por item**, inclusive para item sem guia.
- A listagem passa a exibir o número da operadora, tratando os três estados — e nunca o id interno.
- O badge fixo "Guia gerada" vira o **status real** da guia, traduzido, para qualquer convênio.
- Coluna "Info" com quatro tooltips, sem nenhuma consulta nova.
- `solicitado_em` visível sob o nome do paciente.
- "Médico solicitante" vira "Médico" em todos os lugares.

## Capabilities

### New Capabilities
- `solicitacao-guias-por-item-e-info`: guias por item da solicitação, número da operadora em vez do id interno, e coluna de informação rápida na listagem.

### Modified Capabilities

## Impact

- API Laravel: apenas o formato do bloco de itens do `SolicitacaoResource` e uma constante para o prefixo do placeholder. Nenhum eager load novo, nenhuma migration.
- Frontend React: modal com abas, listagem com número/badge/data/coluna Info, tipos do item.
- Banco de dados: nenhuma alteração.

## Não-objetivos

**Remover `Solicitacao::guia()`.** Ela ainda decide, em `SolicitacaoAnexos.tsx:254`, se um anexo pode ser excluído.

Quando ela sair, caem **oito** eager loads da listagem: `guia.paciente`, `guia.convenio`, `guia.profissional`, `guia.especialidade`, `guia.solicitacaoItem.especialidade`, `guia.solicitacaoItem.profissional`, `guia.antecipacoes` e `guia.conciliacoes`. Hoje toda página de Solicitações carrega antecipações e conciliações de uma guia arbitrária **para não exibir nenhuma delas na lista**. Vira change próprio, com ganho de desempenho de graça.

Também fora de escopo: alterar `GuiaDetalheResumo`, mexer na geração de guias, replicar o padrão de abas em outras telas, e extrair para constante as sete listas de eager load duplicadas.
