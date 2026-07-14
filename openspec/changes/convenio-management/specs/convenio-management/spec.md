## ADDED Requirements

### Requirement: CRUD de convênios isolado por tenant
O sistema SHALL permitir a usuários com `convenios.manage` criar e atualizar somente convênios do próprio tenant, incluindo nome, descrição, tipo de conector e status, sem exclusão física.

#### Scenario: Criar convênio
- **WHEN** um usuário autorizado enviar nome, connector_type e ativo
- **THEN** o sistema SHALL criar o convênio com o tenant do usuário e retornar 201

#### Scenario: Acessar convênio externo
- **WHEN** um usuário solicitar a atualização de um convênio de outro tenant
- **THEN** o sistema SHALL retornar 404

### Requirement: Histórico de regras e valores
O sistema SHALL manter regras e valores por vigência, sem exclusão física, e SHALL encerrar automaticamente o vigente equivalente antes de criar substituto.

#### Scenario: Substituir uma regra vigente
- **WHEN** uma nova regra do mesmo tipo de terapia for criada
- **THEN** o sistema SHALL encerrar a anterior no dia anterior à nova vigência em uma transação
