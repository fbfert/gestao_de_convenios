## 1. Fundação de dados multi-item

- [x] 1.1 Criar migrations reversíveis para `solicitacao_itens` com `tenant_id`, vínculos, quantidade padrão, status operacional e índices.
- [x] 1.2 Criar migrations reversíveis para documentos de Solicitação e documentos por item em armazenamento privado.
- [x] 1.3 Adicionar `solicitacao_item_id` nullable em `guias` mantendo `solicitacao_id` legado.
- [x] 1.4 Implementar backfill de um item legado por Solicitação existente sem duplicar em reexecução.
- [x] 1.5 Criar models, relações e casts para itens e documentos com `BelongsToTenant`.
- [x] 1.6 Atualizar requests/resources/services de Solicitações para aceitar e retornar itens mantendo compatibilidade com campos legados.
- [x] 1.7 Cobrir criação multi-item, backfill, documentos e isolamento por tenant em testes backend.
- [x] 1.8 Atualizar tipos e telas React de Solicitações para exibir e editar itens sem quebrar o fluxo atual.

## 2. Configuração Unimed RDA

- [x] 2.1 Definir e persistir identificação canônica do driver Unimed RDA por Convênio.
- [x] 2.2 Criar tabela/model/resource/request/controller para credenciais Unimed por tenant com senha criptografada.
- [x] 2.3 Garantir que responses, logs, audit logs e testes não exponham senha ou token Unimed.
- [x] 2.4 Adicionar permissão administrativa adequada e sincronização para papéis existentes.
- [x] 2.5 Criar UI em Configurações para driver e credenciais Unimed.
- [x] 2.6 Cobrir tenant, permissão, preservação de senha e auditoria em testes.

## 3. Infraestrutura de automações

- [x] 3.1 Criar migrations/models para `automacao_execucoes` e `automacao_eventos`.
- [x] 3.2 Criar service Laravel para registrar execuções, eventos, transições de status e payload redigido.
- [x] 3.3 Criar client Laravel para worker local com token via env e fake para testes.
- [x] 3.4 Criar worker Node/Playwright mínimo com healthcheck e operação mockada sem acesso ao banco.
- [x] 3.5 Criar job base usando queue database e lock por tenant.
- [x] 3.6 Cobrir fila, fake do worker, locks, eventos e evidências privadas em testes.
- [x] 3.7 Documentar setup local da fila e worker mockado.

## 4. Gerar Guia Unimed

- [x] 4.1 Implementar elegibilidade do item para "Enviar para Unimed".
- [x] 4.2 Adicionar endpoint e ação frontend para enfileirar envio manual por item.
- [x] 4.3 Implementar operação real/mapeada de gerar Guia no worker sem dynaHash hardcoded.
- [x] 4.4 Criar Guia local vinculada ao item somente após sucesso ou confirmação idempotente.
- [x] 4.5 Classificar resultado incerto pós-submit e bloquear retry cego.
- [x] 4.6 Cobrir sucesso, inelegibilidade, concorrência, resultado incerto e confirmação idempotente em testes.
- [x] 4.7 Documentar fluxo de homologação da geração de Guia.

## 5. Revisão de Guias

- [x] 5.1 Atualizar `GET /api/guias/{id}` para incluir item e resumo de execução quando existirem.
- [x] 5.2 Atualizar listagem/detalhe de Guias com estados operacionais Unimed.
- [x] 5.3 Preservar ações existentes de finalizar/negar para Guias manuais conforme spec aprovada.
- [x] 5.4 Bloquear criação manual inconsistente para Convênio configurado como Unimed automatizada.
- [x] 5.5 Cobrir compatibilidade do detalhe, tenant e permissões em testes.

## 6. Acompanhamento, status e senha

- [x] 6.1 Implementar consulta manual de status da Guia Unimed.
- [x] 6.2 Implementar elegibilidade `due` default 24h para consulta automática.
- [x] 6.3 Implementar captura de senha e validade para Guias aprovadas incompletas.
- [x] 6.4 Atualizar scheduler para despachantes leves, locks por tenant e sem browser direto.
- [x] 6.5 Cobrir consulta, captura, due 24h e concorrência em testes.
- [x] 6.6 Documentar scripts 2 e 3.

## 7. Relatórios e reprocessamento

- [x] 7.1 Criar tela/rota de Automações com filtros, resumo, tabela e detalhe de execução.
- [x] 7.2 Exibir timeline de eventos e evidências privadas autorizadas.
- [x] 7.3 Adicionar avisos operacionais para execuções que precisam de atenção.
- [x] 7.4 Implementar reprocessamento manual permitido com vínculo à execução anterior.
- [x] 7.5 Bloquear reprocessamento automático ou manual inseguro de execução `uncertain`.
- [x] 7.6 Cobrir relatórios, avisos, read-only, tenant e reprocessamento em testes.

## 8. Robustez e operação

- [x] 8.1 Implementar catálogo de erros individuais e estruturais.
- [x] 8.2 Implementar circuit breaker para `PORTAL_STRUCTURE_CHANGED` com pausa do conector.
- [x] 8.3 Implementar reativação administrativa auditada.
- [x] 8.4 Implementar healthcheck backend -> worker e status administrativo.
- [x] 8.5 Implementar política de retenção para artefatos técnicos com dry-run e preservação de documentos médicos.
- [x] 8.6 Documentar systemd/Supervisor, deploy, rollback, runbooks e smoke tests.
- [x] 8.7 Cobrir circuit breaker, pausa, reativação, healthcheck e retenção em testes.

## 9. Homologação final

- [x] 9.1 Executar suíte backend completa e documentar exceções.
- [x] 9.2 Executar lint, build e E2E frontend com worker/portal mockado.
- [x] 9.3 Testar migrations e rollback em ambiente descartável com dados legados.
- [x] 9.4 Criar roteiro de teste assistido com portal real autorizado.
- [x] 9.5 Criar documentação de deploy, rollback e relatório GO/NO-GO.
- [x] 9.6 Validar OpenSpec e registrar specs lidas/comandos executados.
