## Context

Convênios são multi-tenant e suas regras financeiras exigem histórico por vigência.

## Goals / Non-Goals

**Goals:** CRUD autorizado de convênios e gestão versionada de regras/valores.

**Non-Goals:** exclusão física ou regras financeiras hardcoded.

## Decisions

- O binding explícito de convênio inclui `tenant_id`, conforme ADR-13.
- Regras e valores novos encerram o vigente equivalente em transação.
- A UI usa o Select compartilhado e confirma encerramentos.

## Risks / Trade-offs

- [Alteração de vigência afeta cálculos futuros] → confirmação explícita e histórico imutável.
