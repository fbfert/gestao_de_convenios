## Why

O fluxo atual trata cada Solicitação como uma unidade 1:1 com Guia e ainda cria Guia automaticamente ao aprovar a Solicitação. O plano aprovado para Automações Unimed exige uma base operacional diferente: uma Solicitação pode ter múltiplos itens, cada item representa especialidade + profissional + quantidade, e cada item pode gerar sua própria Guia via automação idempotente no portal Unimed.

Essa mudança precisa ser formalizada antes da implementação para preservar multi-tenancy, rastreabilidade, segurança de credenciais e compatibilidade gradual com dados legados.

## What Changes

- Adicionar modelo operacional de Solicitação com múltiplos itens, mantendo compatibilidade inicial com Solicitações legadas.
- Separar documentos gerais da Solicitação e documentos vinculados a item, com Pedido Médico obrigatório para o fluxo Unimed e anexos opcionais conforme decisão operacional.
- Adicionar identificação canônica do driver Unimed RDA por convênio, sem comparação frágil por nome.
- Adicionar configuração segura de credenciais Unimed por tenant, editável apenas por usuário autorizado, com senha criptografada e nunca retornada ao frontend.
- Criar infraestrutura de automações Unimed com execuções, eventos, idempotência, fila database, locks por tenant e worker Node/Playwright local.
- Implementar, em etapas posteriores, as operações de gerar Guia, consultar status e capturar senha/validade com rastreabilidade e sem retry cego após ação que possa ter criado Guia.
- Atualizar o módulo de Guias para exibir dados de item, execução e estados operacionais sem quebrar o endpoint existente de detalhe.
- Adicionar relatórios operacionais, avisos de atenção, reprocessamento seguro, scheduler por elegibilidade `due`, circuit breaker e documentação de operação.

Non-goals desta change:

- Não acessar a Unimed real em testes automatizados.
- Não remover colunas legadas de Solicitações ou Guias no primeiro corte.
- Não substituir o fluxo financeiro, conciliação ou analítico Unimed já aprovado.
- Não hardcodear regra de convênio, procedimento, dynaHash, horário operacional ou credencial.
- Não implementar envio automático da Guia na aprovação da Solicitação; o primeiro envio será manual pelo operador.

## Capabilities

### New Capabilities

- `solicitacoes-multi-itens`: Solicitações com múltiplos itens, quantidade por item, documentos gerais/por item e compatibilidade com dados legados.
- `configuracao-unimed-rda`: Configuração do driver Unimed RDA por convênio e credenciais seguras por tenant.
- `automacao-unimed-execucoes`: Execuções de automação Unimed com fila, worker local, eventos, idempotência, evidências privadas e estados operacionais.
- `automacao-unimed-gerar-guia`: Envio manual de item elegível para gerar Guia Unimed via worker Playwright, com criação local idempotente.
- `automacao-unimed-acompanhamento`: Consulta de status, captura de senha/validade, scheduler 24h por elegibilidade, relatórios, avisos e reprocessamento seguro.
- `automacao-unimed-operacao`: Robustez operacional com circuit breaker, saúde do worker, retenção de artefatos técnicos, runbook e preparação de deploy/rollback.

### Modified Capabilities

- `guia-detail`: O detalhe de guia continuará usando `GET /api/guias/{id}`, mas passará a expor vínculos operacionais adicionais quando existirem, incluindo item de Solicitação, execução de automação e estados de atenção, preservando os dados já exigidos pela spec aprovada.

## Impact

- Backend Laravel:
  - novas migrations para itens, documentos, configurações Unimed, execuções e eventos;
  - atualização de models/resources/requests/services de Solicitações e Guias;
  - novos services/jobs/commands para automações e worker client;
  - atualização de permissões, audit log, bindings tenant-safe e scheduler.
- Frontend React:
  - atualização das telas de Solicitações, Guias, Configurações, Dashboard e nova área de Automações/Relatórios;
  - novos tipos, hooks TanStack Query e estados de UI para itens, documentos, execuções e reprocessamento.
- Worker Node/Playwright:
  - novo serviço local autenticado por token, sem acesso direto ao banco e sem persistência de credenciais;
  - fixtures/fakes para testes automatizados sem chamar a Unimed real.
- Banco de dados:
  - backfill inicial de item legado por Solicitação;
  - manutenção de colunas legadas durante a transição;
  - índices por tenant, status, elegibilidade, idempotência e timestamps.
- Operação:
  - queue database, worker Laravel separado, worker Node local, documentação systemd/Supervisor, healthcheck e política de retenção.
