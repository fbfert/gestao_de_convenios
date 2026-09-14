> **Change retroativa.** O comportamento já está em produção; nenhuma linha de
> código foi escrita por esta change. As tarefas abaixo foram marcadas em
> 14/09/2026 conferindo, uma a uma, o arquivo que implementa cada uma.
>
> A change existia desde 19/07/2026 contendo só o `.openspec.yaml`, e era o
> único item a reprovar no `openspec validate --all`. Foi preenchida em vez de
> silenciada com `skip_specs: true`: o comportamento que o nome anuncia existe e
> não estava descrito em spec nenhuma.

## 1. Política padrão

- [x] 1.1 Desligar a reconsulta por foco no padrão global das consultas — verificado em `web/src/App.tsx`, `defaultOptions.queries.refetchOnWindowFocus: false`.

## 2. Telas de acompanhamento

- [x] 2.1 Religar a reconsulta por foco e somar intervalo curto no painel inicial — verificado em `web/src/features/dashboard/DashboardPage.tsx` (`refetchOnWindowFocus: true`, `refetchInterval: 30000`), com o motivo registrado em comentário no próprio arquivo.
- [x] 2.2 Aplicar a mesma política ao card de saúde dos componentes — verificado em `web/src/features/saude/useSaude.ts`, mesmos dois valores e a justificativa de que um card de saúde que só muda no F5 responde sobre o passado.
- [x] 2.3 Usar intervalo mais longo em alertas e na sincronização com a clínica — verificado em `web/src/features/alertas/useAlertas.ts` e `web/src/features/configuracoes/useClinicaSync.ts`, ambos em 60s contra os 30s do painel.

## 3. Acompanhamento de execução

- [x] 3.1 Consultar o progresso em intervalo curto só enquanto a execução não for terminal, e parar depois — verificado em `web/src/features/automacoes/useAutomacoes.ts`: o `refetchInterval` é função do estado e devolve `false` fora dos status em andamento, e `false` quando não há acompanhamento ativo.

## 4. Pendências conhecidas

- [ ] 4.1 Nenhum teste cobre a política de atualização. O e2e não espera por reconsulta automática, e um `refetchInterval` removido por engano passaria despercebido nas três suítes — a tela só ficaria silenciosamente parada. Cobrir exigiria controlar o relógio no Playwright; fica registrado como risco conhecido, não como tarefa aberta de implementação.
