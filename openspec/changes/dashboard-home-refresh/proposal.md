## Why

A change `dashboard-home-refresh` existe desde 19/07/2026 com **apenas** o `.openspec.yaml` — nunca recebeu proposal, spec nem tasks. É o único item que reprova no `openspec validate --all`, com "Change must have at least one delta".

A saída fácil seria marcar `skip_specs: true`, que é o que o próprio erro sugere para refactor puro. Seria mentira: o comportamento que o nome anuncia **existe em produção e não está especificado em lugar nenhum**. O aplicativo desliga a reconsulta por foco globalmente (`App.tsx`), e o Dashboard e o card de Saúde a religam e ainda somam um intervalo curto de reconsulta. Nenhuma spec menciona isso — nem a `dashboard-home`, nem a `saude-componentes`.

Esta change é retroativa: descreve o que já está no ar e fecha o erro pela via honesta, escrevendo o delta que faltava em vez de silenciar a validação.

## What Changes

- Descreve a política de atualização das telas que ficam abertas: o padrão do aplicativo é **não** reconsultar sozinho, e as telas de acompanhamento são a exceção declarada.
- Fixa por que o Dashboard e a Saúde religam a reconsulta por foco: um painel de visão geral que responde sobre o passado é pior do que um painel vazio, porque não se anuncia desatualizado.
- Registra que o acompanhamento de execução de automação usa intervalo curto **enquanto** a execução não chega a status terminal, e para sozinho depois — não há WebSocket no projeto.
- Nenhuma mudança de código. O comportamento descrito já está implementado.

## Capabilities

### New Capabilities
- `dashboard-atualizacao-automatica`: quando uma tela se atualiza sozinha, com que frequência, e por que o padrão do aplicativo é o oposto disso.

### Modified Capabilities
<!-- Nenhuma. O delta entra como capability própria, toda em ADDED: `dashboard-home`
     e `saude-componentes` ainda não foram promovidas a `openspec/specs/`, e um
     MODIFIED sobre spec inexistente é recusado no arquivamento — é o aviso que
     outras cinco changes já carregam. -->

## Impact

**Web**
- `src/App.tsx` — `refetchOnWindowFocus: false` no padrão global do QueryClient
- `src/features/dashboard/DashboardPage.tsx` — religa o foco e usa 30s
- `src/features/saude/useSaude.ts` — mesma política do painel
- `src/features/alertas/useAlertas.ts` e `src/features/configuracoes/useClinicaSync.ts` — 60s
- `src/features/automacoes/useAutomacoes.ts` — 2,5s enquanto a execução não é terminal

**Não faz parte desta change**
- Trocar o poll por WebSocket ou SSE.
- Rever os intervalos. Os números descritos são os que estão em produção; mudá-los é outra conversa.
