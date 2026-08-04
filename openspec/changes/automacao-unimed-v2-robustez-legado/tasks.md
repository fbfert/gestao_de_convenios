## 1. Backend Robustez

- [x] 1.1 Ler `AutomationErrorCatalog`, `UnimedCircuitBreakerService`, `VerificarGuiasDiarioJob`, scheduler e rotas Unimed.
- [x] 1.2 Ampliar catálogo estrutural para `LOGIN_ERROR`, `PORTAL_UNAVAILABLE`, `SESSION_LOST_UNRECOVERABLE`, `WORKER_INTERNAL_FATAL` e `CONFIGURATION_INVALID_GLOBAL`.
- [x] 1.3 Implementar política de retry limitado para `PORTAL_UNAVAILABLE` antes de pausar.
- [x] 1.4 Adaptar `VerificarGuiasDiarioJob` para ignorar convênios `unimed_rda` preservando demais conectores.
- [x] 1.5 Confirmar permissão dedicada em todas as rotas Unimed e cobrir 403 por rota.

## 2. Frontend

- [x] 2.1 Revisar tela de Configurações/Unimed para exibir estado pausado com motivo/data.
- [x] 2.2 Garantir botão de reativação visível e funcional apenas para usuário com permissão.
- [x] 2.3 Executar build/lint frontend pertinentes.

## 3. Testes e Documentação

- [x] 3.1 Cobrir novos erros estruturais, retry de portal indisponível, reativação auditada e job legado em testes backend.
- [x] 3.2 Criar `docs/automacao-unimed/v2-04-robustez-observabilidade.md`.
- [x] 3.3 Executar testes backend pertinentes.
- [x] 3.4 Executar `openspec validate automacao-unimed-v2-robustez-legado --strict`.
- [x] 3.5 Se validações passarem, comitar com `feat(unimed-v2): etapa 4 - robustez e limpeza do legado`.
