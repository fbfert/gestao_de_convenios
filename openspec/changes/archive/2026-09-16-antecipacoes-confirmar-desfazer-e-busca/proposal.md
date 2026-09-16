## Why

A tela `/antecipacoes` decide o próximo ciclo de atendimento de um paciente, e hoje ela erra por falta de freio e de contexto:

- **Ignorar é irreversível e sem confirmação.** Um clique único em "Ignorar" grava uma `Antecipacao` com status `ignorada`, e a solicitação sai da fila de elegíveis **para sempre** — `AntecipacaoService::listarElegiveis()` exclui toda solicitação que tenha QUALQUER registro. A rota `DELETE` saiu em 16/09/2026 (commit `677da0e`), com razão: ela apagava o registro sem desfazer o item nem a guia gerados. Só que o corte foi largo demais e levou junto o único caminho de volta para quem clicou errado. Hoje não existe desfazer nenhum.
- **O histórico não é pesquisável.** Só há filtro por status. Achar "aquela antecipação da paciente X" em uma lista paginada de 20 em 20 é rolagem manual página por página.
- **A página e os filtros se perdem ao clicar num item.** `page` e o status vivem em `useState` local; clicar numa guia do histórico e voltar devolve a página 1 sem filtro — o resto do sistema já resolve isso com `useListaNaUrl`.
- **A linha não diz o suficiente.** No histórico, "Paciente · Convênio" não conta de qual solicitação veio, qual era a data prevista, nem por que foi ignorada. Nos elegíveis, a linha mostra só o nome e a contagem de itens: não dá para conferir a origem antes de decidir gerar.

## What Changes

- **Ignorar passa a pedir confirmação**, num modal que mostra paciente, convênio e data prevista, e oferece um campo opcional de **motivo** gravado em `observacoes`.
- **Desfazer uma antecipação ignorada**, com confirmação. Apaga o registro `ignorada`, devolvendo a solicitação à fila de elegíveis. Só vale para `ignorada`: `gerada` continua sem volta, porque desfazê-la teria de apagar itens e guias já criados — o motivo pelo qual o `DELETE` genérico saiu. A trilha de auditoria (`Auditable`) guarda quem ignorou e quem desfez.
- **Busca no histórico** por nome do paciente, número de guia, convênio e período da ação (De/Até sobre a data em que se gerou ou ignorou).
- **Paginação e filtros persistentes na URL** (`useListaNaUrl` + componente `Paginacao` compartilhado), sobrevivendo a clicar numa guia e voltar, ao F5 e ao botão Voltar.
- **Tooltip ao lado de "Paciente · Convênio" no histórico**, com o detalhe da ação: status, quando e por quem, solicitação de origem, data prevista, itens/guias gerados e o motivo registrado.
- **Modal de origem nos elegíveis**: clicar em "Paciente · Convênio" abre a solicitação de origem — data do pedido, médico, CIDs, e cada item com especialidade, profissional, guia e status.

## Capabilities

### Modified Capabilities
- `antecipacao`: confirmação para dispensar, reversão da dispensa, e busca/paginação persistente no histórico.

> **Ordem de arquivamento.** A capability `antecipacao` nasce na change `antecipacao-alerta-e-fila-de-elegiveis`. Esta change precisa ser arquivada **depois** daquela, senão o archive é recusado por MODIFIED sobre spec inexistente.

**Conflito registrado, conforme `AGENTS.md`.** Ao arquivar a change base, a requirement "Histórico de antecipações" dela dizia que o sistema SHALL permitir excluir um registro do histórico, com o cenário "Excluir registro não desfaz a geração". Estava certa quando foi escrita, em 13/09/2026, e ficou falsa três dias depois: o commit `677da0e` derrubou a rota `DELETE /antecipacoes/{antecipacao}` sem atualizar a change, e `AntecipacoesApiTest::test_historico_nao_pode_ser_apagado` prova o 405 desde então. A correção foi feita na própria change base (spec, tarefas 4.8 e 6.1, e a lista de rotas do proposal), e não aqui, porque é lá que o erro mora — esta change só herdaria o texto errado.

## Impact

**API**
- `app/Services/AntecipacaoService.php` — filtros do histórico e `desfazerIgnorada()`
- `app/Http/Controllers/AntecipacaoController.php` — `index` recebe os novos filtros, novo `desfazer`
- `app/Http/Requests/ListarAntecipacoesRequest.php` (novo)
- Rota nova `DELETE /antecipacoes/{antecipacao}/ignorada` (permissão `antecipacoes.manage`). A rota `DELETE /antecipacoes/{antecipacao}` continua inexistente (405), e o teste que prova isso permanece.

**Web**
- `features/antecipacoes/AntecipacoesPage.tsx`, `useAntecipacoes.ts`, `types.ts`
- `features/antecipacoes/IgnorarAntecipacaoModal.tsx` (novo)
- `features/antecipacoes/OrigemElegivelModal.tsx` (novo)
- `features/antecipacoes/AntecipacaoTooltipDetalhe.tsx` (novo)

**Não faz parte desta change**
- Desfazer uma antecipação `gerada` (exigiria apagar itens e guias criados).
- Busca na fila de Elegíveis — ela é curta e não pagina.
