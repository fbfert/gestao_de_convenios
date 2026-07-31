## ADDED Requirements

### Requirement: Circuit breaker do conector Unimed
O sistema SHALL pausar automações automáticas do conector Unimed quando detectar falha estrutural como alteração do portal.

#### Scenario: Portal alterado
- **WHEN** uma execução classificar falha como `PORTAL_STRUCTURE_CHANGED`
- **THEN** o sistema SHALL interromper o lote, pausar automações automáticas do conector e avisar operador/admin

#### Scenario: Reativação auditada
- **WHEN** um administrador reativar automações após correção técnica
- **THEN** o sistema SHALL registrar AuditLog da reativação

### Requirement: Saúde do worker
O sistema SHALL disponibilizar status operacional do worker local para usuários administrativos sem expor segredos ou detalhes sensíveis.

#### Scenario: Worker disponível
- **WHEN** o backend consultar o healthcheck local com sucesso
- **THEN** o sistema SHALL mostrar status `available` ou equivalente na interface administrativa

#### Scenario: Worker indisponível
- **WHEN** o healthcheck falhar
- **THEN** o sistema SHALL mostrar status indisponível e impedir novos disparos automáticos que dependam do worker

### Requirement: Retenção de artefatos técnicos
O sistema SHALL aplicar política configurável de retenção para screenshots, dumps HTML e logs técnicos, sem remover documentos médicos.

#### Scenario: Limpeza dry-run
- **WHEN** o operador executar comando de limpeza em modo dry-run
- **THEN** o sistema SHALL listar artefatos técnicos candidatos sem removê-los

#### Scenario: Preservar documentos médicos
- **WHEN** a limpeza de retenção for executada
- **THEN** o sistema SHALL NOT remover Pedido Médico, Laudo, Plano, Relatório ou outros documentos de saúde

### Requirement: Runbook e deploy operacional
O sistema SHALL documentar instalação, operação, rollback e cenários de falha das Automações Unimed.

#### Scenario: Documentação de produção
- **WHEN** a automação estiver pronta para produção
- **THEN** o repositório SHALL conter runbook com queue worker, Node worker, scheduler, healthcheck, backup, rollback e reativação
