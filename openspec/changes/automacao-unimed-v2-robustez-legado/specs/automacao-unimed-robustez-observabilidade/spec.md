## ADDED Requirements

### Requirement: Catálogo de erros estruturais Unimed
O sistema SHALL classificar erros globais do portal/worker Unimed como estruturais para acionar pausa administrativa do conector.

#### Scenario: Login inválido pausa conector
- **WHEN** uma execução Unimed retornar `LOGIN_ERROR`
- **THEN** o sistema SHALL pausar a credencial Unimed do tenant e registrar auditoria sem segredo

#### Scenario: Portal indisponível com retry limitado
- **WHEN** uma execução Unimed retornar `PORTAL_UNAVAILABLE` acima da política de retry limitada
- **THEN** o sistema SHALL pausar a credencial Unimed do tenant

#### Scenario: Falha estrutural irrecuperável
- **WHEN** uma execução Unimed retornar `SESSION_LOST_UNRECOVERABLE`, `WORKER_INTERNAL_FATAL` ou `CONFIGURATION_INVALID_GLOBAL`
- **THEN** o sistema SHALL tratar o erro como estrutural e pausar o conector

### Requirement: Reativação auditada
O sistema SHALL permitir reativar automações Unimed somente por usuário autorizado e SHALL registrar auditoria sem credenciais.

#### Scenario: Usuário autorizado reativa
- **WHEN** usuário com `configuracoes.unimed.manage` chamar reativação Unimed
- **THEN** a credencial SHALL voltar a ativa e a auditoria SHALL registrar a ação sem login/senha

#### Scenario: Usuário sem permissão bloqueado
- **WHEN** usuário sem `configuracoes.unimed.manage` acessar rotas Unimed administrativas
- **THEN** a API SHALL retornar 403

### Requirement: UI de conector pausado
O frontend SHALL exibir claramente quando o conector Unimed estiver pausado e SHALL oferecer reativação somente para usuários com permissão.

#### Scenario: Conector pausado visível
- **WHEN** a configuração Unimed retornar `automation_paused_at`
- **THEN** a UI SHALL exibir o estado pausado, motivo e data

#### Scenario: Reativar pela UI
- **WHEN** usuário autorizado clicar em reativar automações
- **THEN** a UI SHALL chamar o endpoint de reativação e atualizar o estado exibido
