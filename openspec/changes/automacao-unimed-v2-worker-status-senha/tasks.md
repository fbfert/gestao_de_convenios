## 1. Worker Unimed

- [x] 1.1 Mapear a estrutura atual do `worker-unimed` e reutilizar helpers existentes de login, beneficiário, waits e status quando possível.
- [x] 1.2 Implementar operação `consult_status_batch` com fixtures para localizar guia e mapear `approved`, `under_review`, `denied` e `canceled`.
- [x] 1.3 Implementar operação `capture_authorization_data_batch` com paginação de "Exames em aberto", captura de `NR_SENHA` e `DT_VALIDADE_SENHA`, e outcome `NOT_FOUND_IN_OPEN_EXAMS`.
- [x] 1.4 Cobrir erros individuais, erros estruturais e ausência de retry inseguro nos testes do worker.

## 2. Backend Laravel

- [x] 2.1 Separar services Laravel para consulta de status e captura de senha/validade, mantendo Laravel como fonte de verdade e worker sem banco.
- [x] 2.2 Ajustar `ExecutarAutomacaoUnimedJob` e scheduler para despachar operações separadas por elegibilidade, com lock por tenant.
- [x] 2.3 Aplicar resultados sem sobrescrever senha/validade existentes com vazio e atualizando `unimed_last_checked_at` somente em consulta conclusiva.
- [x] 2.4 Cobrir elegibilidade, locks, continuação após erro individual, erro estrutural e persistência dos resultados em testes backend.

## 3. Frontend

- [x] 3.1 Atualizar hooks/tipos de Guias para operações manuais separadas de consultar status e buscar senha/validade.
- [x] 3.2 Atualizar lista/detalhe de Guias para ações elegíveis, última consulta e erro recente das operações Unimed.
- [x] 3.3 Executar lint/build frontend pertinentes e corrigir regressões.

## 4. Documentação e Validação

- [x] 4.1 Criar `docs/automacao-unimed/v2-03-worker-status-senha.md`.
- [x] 4.2 Executar testes/checks do worker e corrigir regressões.
- [x] 4.3 Executar testes backend pertinentes e corrigir regressões.
- [x] 4.4 Executar `openspec validate automacao-unimed-v2-worker-status-senha --strict`.
- [x] 4.5 Se validações relevantes passarem, comitar com `feat(unimed-v2): etapa 3 - worker real status e senha`.
