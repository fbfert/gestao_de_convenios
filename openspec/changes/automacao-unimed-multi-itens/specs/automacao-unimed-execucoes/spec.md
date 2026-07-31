## ADDED Requirements

### Requirement: Execuções de automação rastreáveis
O sistema SHALL registrar cada automação Unimed em uma execução tenant-safe, contendo operação, status, entidade relacionada, idempotency key, timestamps e payload técnico redigido.

#### Scenario: Criar execução na fila
- **WHEN** uma operação Unimed for enfileirada
- **THEN** o sistema SHALL criar uma execução com status `queued` e idempotency key única para tenant, operação e alvo

#### Scenario: Registrar evento da execução
- **WHEN** uma execução mudar de estado ou receber resultado do worker
- **THEN** o sistema SHALL registrar evento vinculado à execução sem apagar histórico anterior

### Requirement: Worker local sem acesso ao banco
O sistema SHALL executar automações de navegador em um worker Node/Playwright local, autenticado por token de serviço, sem acesso direto ao banco de dados.

#### Scenario: Chamada ao worker
- **WHEN** o job Laravel iniciar uma automação
- **THEN** o sistema SHALL enviar ao worker apenas os dados necessários em memória e receber resultado estruturado

#### Scenario: Worker indisponível
- **WHEN** o worker local não responder
- **THEN** o sistema SHALL marcar a execução como falha operacional e manter o histórico para reprocessamento seguro

### Requirement: Locks por tenant
O sistema SHALL impedir automações Unimed concorrentes para a mesma credencial ou tenant quando a operação depender de sessão do portal.

#### Scenario: Execução concorrente bloqueada
- **WHEN** já existir execução `queued` ou `running` para o mesmo tenant e operação incompatível
- **THEN** o sistema SHALL impedir novo disparo concorrente e informar o estado atual ao operador

### Requirement: Evidências privadas e redigidas
O sistema SHALL armazenar screenshots, HTML dumps ou logs técnicos de falha somente em área privada e sem segredos.

#### Scenario: Falha com evidência
- **WHEN** o worker capturar evidência técnica de uma falha
- **THEN** o sistema SHALL vincular a evidência à execução e impedir acesso público direto
