# Automação Unimed v2 - Etapa 4 Robustez e Observabilidade

## Escopo

Esta etapa fecha robustez operacional antes da homologação real: amplia o catálogo de erros estruturais, preserva o job legado para convênios não-Unimed, confirma a permissão dedicada e melhora a visibilidade de conector pausado.

## Erros Estruturais

Acionam pausa do conector Unimed RDA:

- `PORTAL_STRUCTURE_CHANGED`
- `LOGIN_ERROR`
- `SESSION_LOST_UNRECOVERABLE`
- `WORKER_INTERNAL_FATAL`
- `CONFIGURATION_INVALID_GLOBAL`

`PORTAL_UNAVAILABLE` usa retry limitado. O conector só pausa quando o resultado informar tentativa igual ou superior a `max_attempts` (padrão 3). Sem contador de tentativa, o erro não pausa imediatamente para evitar falso positivo por indisponibilidade temporária.

## Circuit Breaker

Quando um erro estrutural é confirmado, `UnimedCircuitBreakerService`:

- marca a credencial do tenant como inativa;
- preenche `automation_paused_at`;
- preenche `automation_paused_reason`;
- registra `AuditLog` com label do erro, sem login/senha.

A reativação administrativa limpa os campos de pausa, reativa a credencial e registra `unimed_rda.automation_reactivated`.

## Job Legado

Decisão: manter `VerificarGuiasDiarioJob`, mas excluir guias cujo convênio usa `connector_driver = unimed_rda`.

Motivo: o job ainda pode atender conectores legados ou futuros que usam `ConnectorResolver`. Para Unimed RDA, o motor novo (`EnfileirarConsultasUnimedDueJob` + worker local) é a fonte correta, com locks, idempotência e rastreabilidade por execução.

## Permissão

As rotas administrativas Unimed continuam protegidas por `configuracoes.unimed.manage`:

- configuração e credencial;
- healthcheck;
- reativação;
- mapeamentos de especialidade;
- mapeamentos de profissional.

## UI

A aba Unimed RDA em Configurações exibe motivo e data da pausa quando `automation_paused_at` está presente. O botão de reativação aparece nesse estado e fica bloqueado para usuário sem permissão.

## Rollback

Reverter esta etapa restaura o catálogo anterior e o job legado volta a enxergar guias Unimed. Guias e execuções já criadas não exigem migração de dados.
