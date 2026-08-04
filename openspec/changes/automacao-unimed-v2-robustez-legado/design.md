## Context

O conector Unimed RDA agora tem worker real para gerar guia, consultar status e capturar senha/validade. Antes da homologação real, o sistema precisa reagir corretamente a erros estruturais, evitar concorrência com automações legadas e deixar claro para o operador quando o conector está pausado.

## Goals / Non-Goals

**Goals:**

- Classificar erros estruturais adicionais no catálogo existente.
- Acionar o circuit breaker para falhas globais e preservar falhas individuais como eventos operacionais.
- Impedir que `VerificarGuiasDiarioJob` processe guias de convênios `unimed_rda`.
- Garantir permissão dedicada em todas as rotas Unimed.
- Exibir pausa/reativação no frontend administrativo.

**Non-Goals:**

- Não criar novos fluxos de negócio para as automações 1, 2 ou 3.
- Não acessar o portal real.
- Não alterar regras de convênios não-Unimed além de preservar compatibilidade.

## Decisions

- **Adaptar o job legado em vez de removê-lo.** Ele pode continuar útil para outros conectores. A decisão conservadora é excluir explicitamente convênios `unimed_rda` do job legado.
- **Erros estruturais em catálogo central.** O circuit breaker continua consumindo `AutomationErrorCatalog`, evitando regras espalhadas em jobs/services.
- **Reativação somente administrativa.** O endpoint existente permanece protegido por `configuracoes.unimed.manage` e auditado sem segredos.

## Risks / Trade-offs

- `PORTAL_UNAVAILABLE` pode representar indisponibilidade temporária -> mitigação: tratar com retry limitado antes da pausa quando houver contagem/tentativa disponível no resultado.
- Job legado pode ser usado por conector futuro -> mitigação: filtro explícito só para `unimed_rda`, sem remover a classe.
- UI depende dos campos `automation_paused_at/reason` já existentes -> mitigação: validar resource e testes.
